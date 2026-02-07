<?php
/**
 * PERSONNALY - Model Size
 * Gestion des tailles (liees aux groupes)
 */

require_once __DIR__ . '/../core/Database.php';

class Size
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recupere toutes les tailles actives
     */
    public function findAllActive(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT s.*, sg.name as group_name
                 FROM sizes s
                 JOIN size_groups sg ON s.size_group_id = sg.id
                 WHERE s.active = 1 AND sg.active = 1
                 ORDER BY sg.sort_order ASC, s.sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere toutes les tailles (actives ou non)
     */
    public function findAll(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT s.*, sg.name as group_name
                 FROM sizes s
                 JOIN size_groups sg ON s.size_group_id = sg.id
                 ORDER BY sg.sort_order ASC, s.sort_order ASC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere les tailles d'un groupe
     */
    public function findByGroupId(int $groupId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM sizes WHERE size_group_id = ? ORDER BY sort_order ASC'
            );
            $stmt->execute([$groupId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Recupere les tailles groupees par groupe
     */
    public function findAllGrouped(): array
    {
        $sizes = $this->findAll();
        $grouped = [];
        foreach ($sizes as $size) {
            $groupName = $size['group_name'];
            if (!isset($grouped[$groupName])) {
                $grouped[$groupName] = [
                    'group_id' => $size['size_group_id'],
                    'sizes' => []
                ];
            }
            $grouped[$groupName]['sizes'][] = $size;
        }
        return $grouped;
    }

    /**
     * Recupere une taille par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, sg.name as group_name
             FROM sizes s
             JOIN size_groups sg ON s.size_group_id = sg.id
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Cree une nouvelle taille
     */
    public function create(array $data): int
    {
        // Recuperer le prochain sort_order pour ce groupe
        $stmt = $this->db->prepare(
            'SELECT MAX(sort_order) as max_order FROM sizes WHERE size_group_id = ?'
        );
        $stmt->execute([$data['size_group_id']]);
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO sizes (label, size_group_id, sort_order, active, created_at)
             VALUES (?, ?, ?, 1, NOW())'
        );
        $stmt->execute([
            $data['label'],
            $data['size_group_id'],
            $maxOrder + 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met a jour une taille
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sizes SET label = ?, size_group_id = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['label'],
            $data['size_group_id'],
            $id
        ]);
    }

    /**
     * Active/desactive une taille
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE sizes SET active = NOT active WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime une taille
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM sizes WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Reordonne les tailles d'un groupe
     */
    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare('UPDATE sizes SET sort_order = ? WHERE id = ?');
            $stmt->execute([$order, $id]);
            $order++;
        }
        return true;
    }

    /**
     * Retourne un tableau simple [label => label] pour les selects
     */
    public function getSimpleList(): array
    {
        $sizes = $this->findAllActive();
        $result = [];
        foreach ($sizes as $size) {
            $result[$size['label']] = $size['label'];
        }
        return $result;
    }

    /**
     * Recupere les tailles groupees par groupe (format compatible avec l'ancien systeme)
     * Utilise pour category-form.php et product-form.php
     * Retourne: ['Lettres' => [['value' => 'XS', 'label' => 'XS', ...], ...], ...]
     */
    public function getSizesGroupedForCategories(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT s.*, sg.name as group_name
                 FROM sizes s
                 JOIN size_groups sg ON s.size_group_id = sg.id
                 WHERE s.active = 1 AND sg.active = 1
                 ORDER BY sg.sort_order ASC, s.sort_order ASC'
            );
            $stmt->execute();
            $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Grouper par group_name avec format compatible
            $grouped = [];
            foreach ($sizes as $size) {
                $groupName = $size['group_name'] ?: 'Autres';
                if (!isset($grouped[$groupName])) {
                    $grouped[$groupName] = [];
                }
                // Format compatible avec l'ancien systeme (value = label pour les tailles)
                $grouped[$groupName][] = [
                    'id' => $size['id'],
                    'value' => $size['label'],  // value = label pour compatibilite
                    'label' => $size['label'],
                    'size_group' => $groupName,
                    'size_group_id' => $size['size_group_id'],
                    'sort_order' => $size['sort_order'],
                    'active' => $size['active']
                ];
            }
            return $grouped;
        } catch (PDOException $e) {
            return [];
        }
    }
}
