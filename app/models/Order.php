<?php
/**
 * PERSONNALY - Modèle Order
 */

require_once __DIR__ . '/../core/Database.php';

class Order
{
    private PDO $db;

    // Statuts possibles
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_CANCELLED = 'cancelled';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Trouve une commande par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, u.email as user_email
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    /**
     * Liste toutes les commandes
     */
    public function findAll(string $status = null): array
    {
        $sql = 'SELECT o.*, u.email as user_email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id';

        $params = [];
        if ($status) {
            $sql .= ' WHERE o.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY o.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Liste les commandes d'un utilisateur
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Crée une nouvelle commande
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO orders (user_id, total, status, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['user_id'],
            $data['total'],
            $data['status'] ?? self::STATUS_PENDING,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour le statut d'une commande
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE orders SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    /**
     * Met à jour une commande
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $id;

        $sql = 'UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Compte les commandes par statut
     */
    public function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Compte les nouvelles commandes (pending)
     */
    public function countNew(): int
    {
        return $this->countByStatus(self::STATUS_PENDING);
    }

    /**
     * Récupère les personnalisations d'une commande
     */
    public function getCustomizations(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM order_customizations WHERE order_id = ?'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Ajoute une personnalisation à une commande
     */
    public function addCustomization(int $orderId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO order_customizations (order_id, product_id, data_json, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([
            $orderId,
            $data['product_id'],
            json_encode($data['customization'], JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Calcule le total des ventes
     */
    public function getTotalSales(): float
    {
        $stmt = $this->db->query(
            "SELECT COALESCE(SUM(total), 0) FROM orders WHERE status NOT IN ('cancelled')"
        );
        return (float) $stmt->fetchColumn();
    }
}
