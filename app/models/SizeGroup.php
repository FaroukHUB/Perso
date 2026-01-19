<?php
/**
 * PERSONNALY - Model SizeGroup
 * Gestion des groupes de tailles (Lettres, Chiffres, Enfants, Personnalise)
 */

require_once __DIR__ . '/../core/Database.php';

class SizeGroup
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recupere tous les groupes actifs
     */
    public function findAllActive(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM size_groups WHERE active = 1 ORDER BY sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere tous les groupes (actifs ou non)
     */
    public function findAll(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM size_groups ORDER BY sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere un groupe par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM size_groups WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Recupere un groupe par nom
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM size_groups WHERE name = ?');
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Cree un nouveau groupe
     */
    public function create(string $name): int
    {
        // Recuperer le prochain sort_order
        $stmt = $this->db->prepare('SELECT MAX(sort_order) as max_order FROM size_groups');
        $stmt->execute();
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO size_groups (name, sort_order, active, created_at) VALUES (?, ?, 1, NOW())'
        );
        $stmt->execute([$name, $maxOrder + 1]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Cree un groupe si inexistant et retourne son ID
     */
    public function findOrCreate(string $name): int
    {
        $existing = $this->findByName($name);
        if ($existing) {
            return (int) $existing['id'];
        }
        return $this->create($name);
    }

    /**
     * Met a jour un groupe
     */
    public function update(int $id, string $name): bool
    {
        $stmt = $this->db->prepare('UPDATE size_groups SET name = ? WHERE id = ?');
        return $stmt->execute([$name, $id]);
    }

    /**
     * Active/desactive un groupe
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE size_groups SET active = NOT active WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime un groupe
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM size_groups WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Reordonne les groupes
     */
    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare('UPDATE size_groups SET sort_order = ? WHERE id = ?');
            $stmt->execute([$order, $id]);
            $order++;
        }
        return true;
    }
}
