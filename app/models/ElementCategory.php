<?php
/**
 * PERSONNALY - Model ElementCategory
 * Gestion des categories d'elements (cliparts/formes)
 */

require_once __DIR__ . '/../core/Database.php';

class ElementCategory
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recupere toutes les categories actives
     */
    public function findAllActive(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM element_categories WHERE active = 1 ORDER BY sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere toutes les categories (actives ou non)
     */
    public function findAll(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM element_categories ORDER BY sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere une categorie par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM element_categories WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Recupere une categorie par nom
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM element_categories WHERE name = ?');
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Cree une nouvelle categorie
     */
    public function create(string $name): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) as max_order FROM element_categories');
        $stmt->execute();
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO element_categories (name, sort_order, active, created_at) VALUES (?, ?, 1, NOW())'
        );
        $stmt->execute([$name, $maxOrder + 1]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Cree une categorie si inexistante et retourne son ID
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
     * Met a jour une categorie
     */
    public function update(int $id, string $name): bool
    {
        $stmt = $this->db->prepare('UPDATE element_categories SET name = ? WHERE id = ?');
        return $stmt->execute([$name, $id]);
    }

    /**
     * Active/desactive une categorie
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE element_categories SET active = NOT active WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime une categorie
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM element_categories WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Reordonne les categories
     */
    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare('UPDATE element_categories SET sort_order = ? WHERE id = ?');
            $stmt->execute([$order, $id]);
            $order++;
        }
        return true;
    }

    /**
     * Compte le nombre d'elements dans une categorie
     */
    public function countElements(int $categoryId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM elements WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        return (int) $stmt->fetchColumn();
    }
}
