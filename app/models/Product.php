<?php
/**
 * PERSONNALY - Modèle Product
 */

require_once __DIR__ . '/../core/Database.php';

class Product
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Trouve un produit par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    /**
     * Liste tous les produits
     */
    public function findAll(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM products';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Liste les produits actifs (pour le front)
     */
    public function findActive(): array
    {
        return $this->findAll(true);
    }

    /**
     * Crée un nouveau produit
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products (name, description, base_price, sale_price, badge, badge_color, weight, category, image_front_url, image_back_url, available_sizes, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['base_price'],
            !empty($data['sale_price']) ? $data['sale_price'] : null,
            !empty($data['badge']) ? $data['badge'] : null,
            !empty($data['badge_color']) ? $data['badge_color'] : '#FF1493',
            $data['weight'] ?? null,
            $data['category'] ?? null,
            $data['image_front_url'] ?? null,
            $data['image_back_url'] ?? null,
            $data['available_sizes'] ?? null,
            $data['active'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un produit
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

        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Supprime un produit
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM products WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Active/désactive un produit
     */
    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->db->prepare('UPDATE products SET active = ? WHERE id = ?');
        return $stmt->execute([(int) $active, $id]);
    }

    /**
     * Compte les produits
     */
    public function count(bool $activeOnly = false): int
    {
        $sql = 'SELECT COUNT(*) FROM products';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $stmt = $this->db->query($sql);
        return (int) $stmt->fetchColumn();
    }
}
