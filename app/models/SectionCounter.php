<?php
/**
 * PERSONNALY - Modèle SectionCounter
 * Compteurs animés (chiffres clés)
 */

require_once __DIR__ . '/../core/Database.php';

class SectionCounter
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM section_counters WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySection(int $sectionId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM section_counters WHERE section_id = ?';
        if ($activeOnly) {
            $sql .= ' AND status = "active"';
        }
        $sql .= ' ORDER BY sort_order ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO section_counters (section_id, value, suffix, prefix, label, icon, color, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['section_id'],
            (int) $data['value'],
            $data['suffix'] ?? null,
            $data['prefix'] ?? null,
            $data['label'],
            $data['icon'] ?? null,
            $data['color'] ?? null,
            $data['sort_order'] ?? $this->getNextSortOrder($data['section_id']),
            $data['status'] ?? 'active'
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        $allowedFields = ['value', 'suffix', 'prefix', 'label', 'icon', 'color', 'sort_order', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $values[] = $id;
        $sql = 'UPDATE section_counters SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM section_counters WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function deleteBySection(int $sectionId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM section_counters WHERE section_id = ?');
        return $stmt->execute([$sectionId]);
    }

    public function saveAll(int $sectionId, array $items): bool
    {
        $this->deleteBySection($sectionId);

        foreach ($items as $order => $item) {
            if (!empty($item['label']) && isset($item['value'])) {
                $this->create([
                    'section_id' => $sectionId,
                    'value' => $item['value'],
                    'suffix' => $item['suffix'] ?? null,
                    'prefix' => $item['prefix'] ?? null,
                    'label' => $item['label'],
                    'icon' => $item['icon'] ?? null,
                    'color' => $item['color'] ?? null,
                    'sort_order' => $order,
                    'status' => $item['status'] ?? 'active'
                ]);
            }
        }

        return true;
    }

    private function getNextSortOrder(int $sectionId): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) FROM section_counters WHERE section_id = ?');
        $stmt->execute([$sectionId]);
        return ((int) $stmt->fetchColumn()) + 1;
    }
}
