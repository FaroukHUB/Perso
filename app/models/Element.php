<?php
/**
 * PERSONNALY - Model Element
 * Gestion des elements (cliparts/formes)
 */

require_once __DIR__ . '/../core/Database.php';

class Element
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recupere tous les elements actifs
     */
    public function findAllActive(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT e.*, ec.name as category_name
                 FROM elements e
                 JOIN element_categories ec ON e.category_id = ec.id
                 WHERE e.active = 1 AND ec.active = 1
                 ORDER BY ec.sort_order ASC, e.sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere tous les elements (actifs ou non)
     */
    public function findAll(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT e.*, ec.name as category_name
                 FROM elements e
                 JOIN element_categories ec ON e.category_id = ec.id
                 ORDER BY ec.sort_order ASC, e.sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere les elements d'une categorie
     */
    public function findByCategoryId(int $categoryId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM elements WHERE category_id = ? ORDER BY sort_order ASC'
            );
            $stmt->execute([$categoryId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere les elements groupes par categorie
     */
    public function findAllGrouped(): array
    {
        $elements = $this->findAll();
        $grouped = [];
        foreach ($elements as $element) {
            $categoryName = $element['category_name'];
            if (!isset($grouped[$categoryName])) {
                $grouped[$categoryName] = [
                    'category_id' => $element['category_id'],
                    'elements' => []
                ];
            }
            $grouped[$categoryName]['elements'][] = $element;
        }
        return $grouped;
    }

    /**
     * Recupere les elements gratuits uniquement
     */
    public function findFreeElements(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT e.*, ec.name as category_name
                 FROM elements e
                 JOIN element_categories ec ON e.category_id = ec.id
                 WHERE e.active = 1 AND ec.active = 1 AND e.is_premium = 0
                 ORDER BY ec.sort_order ASC, e.sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere les elements premium uniquement
     */
    public function findPremiumElements(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT e.*, ec.name as category_name
                 FROM elements e
                 JOIN element_categories ec ON e.category_id = ec.id
                 WHERE e.active = 1 AND ec.active = 1 AND e.is_premium = 1
                 ORDER BY ec.sort_order ASC, e.sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere un element par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT e.*, ec.name as category_name
             FROM elements e
             JOIN element_categories ec ON e.category_id = ec.id
             WHERE e.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Cree un nouvel element
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'SELECT MAX(sort_order) as max_order FROM elements WHERE category_id = ?'
        );
        $stmt->execute([$data['category_id']]);
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO elements (name, image_path, category_id, is_premium, price, sort_order, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
        );
        $stmt->execute([
            $data['name'],
            $data['image_path'],
            $data['category_id'],
            $data['is_premium'] ?? 0,
            $data['price'] ?? null,
            $maxOrder + 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met a jour un element
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE elements SET name = ?, image_path = ?, category_id = ?, is_premium = ?, price = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['name'],
            $data['image_path'],
            $data['category_id'],
            $data['is_premium'] ?? 0,
            $data['price'] ?? null,
            $id
        ]);
    }

    /**
     * Met a jour sans modifier l'image
     */
    public function updateWithoutImage(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE elements SET name = ?, category_id = ?, is_premium = ?, price = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['name'],
            $data['category_id'],
            $data['is_premium'] ?? 0,
            $data['price'] ?? null,
            $id
        ]);
    }

    /**
     * Active/desactive un element
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE elements SET active = NOT active WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime un element
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM elements WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Reordonne les elements
     */
    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare('UPDATE elements SET sort_order = ? WHERE id = ?');
            $stmt->execute([$order, $id]);
            $order++;
        }
        return true;
    }
}
