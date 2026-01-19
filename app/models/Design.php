<?php
/**
 * PERSONNALY - Model Design
 * Gestion des designs (Idees cadeaux)
 */

require_once __DIR__ . '/../core/Database.php';

class Design
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recupere tous les designs actifs
     */
    public function findAllActive(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT d.*, dc.name as category_name
                 FROM designs d
                 JOIN design_categories dc ON d.category_id = dc.id
                 WHERE d.active = 1 AND dc.active = 1
                 ORDER BY dc.sort_order ASC, d.sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere tous les designs (actifs ou non)
     */
    public function findAll(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT d.*, dc.name as category_name
                 FROM designs d
                 JOIN design_categories dc ON d.category_id = dc.id
                 ORDER BY dc.sort_order ASC, d.sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere les designs d'une categorie
     */
    public function findByCategoryId(int $categoryId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM designs WHERE category_id = ? ORDER BY sort_order ASC'
            );
            $stmt->execute([$categoryId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere les designs groupes par categorie
     */
    public function findAllGrouped(): array
    {
        $designs = $this->findAll();
        $grouped = [];
        foreach ($designs as $design) {
            $categoryName = $design['category_name'];
            if (!isset($grouped[$categoryName])) {
                $grouped[$categoryName] = [
                    'category_id' => $design['category_id'],
                    'designs' => []
                ];
            }
            $grouped[$categoryName]['designs'][] = $design;
        }
        return $grouped;
    }

    /**
     * Recupere un design par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, dc.name as category_name
             FROM designs d
             JOIN design_categories dc ON d.category_id = dc.id
             WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Cree un nouveau design
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'SELECT MAX(sort_order) as max_order FROM designs WHERE category_id = ?'
        );
        $stmt->execute([$data['category_id']]);
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO designs (name, image_path, category_id, sort_order, active, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())'
        );
        $stmt->execute([
            $data['name'],
            $data['image_path'],
            $data['category_id'],
            $maxOrder + 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met a jour un design
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE designs SET name = ?, image_path = ?, category_id = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['name'],
            $data['image_path'],
            $data['category_id'],
            $id
        ]);
    }

    /**
     * Met a jour uniquement le nom et la categorie (sans image)
     */
    public function updateWithoutImage(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE designs SET name = ?, category_id = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['name'],
            $data['category_id'],
            $id
        ]);
    }

    /**
     * Active/desactive un design
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE designs SET active = NOT active WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime un design
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM designs WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Reordonne les designs
     */
    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare('UPDATE designs SET sort_order = ? WHERE id = ?');
            $stmt->execute([$order, $id]);
            $order++;
        }
        return true;
    }
}
