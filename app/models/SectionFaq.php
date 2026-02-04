<?php
/**
 * PERSONNALY - Modèle SectionFaq
 * Gestion des questions/réponses FAQ liées aux sections
 */

require_once __DIR__ . '/../core/Database.php';

class SectionFaq
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère un item FAQ par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM section_faq_items WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Liste les items FAQ d'une section
     */
    public function findBySection(int $sectionId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM section_faq_items WHERE section_id = ?';
        if ($activeOnly) {
            $sql .= ' AND status = "active"';
        }
        $sql .= ' ORDER BY sort_order ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll();
    }

    /**
     * Crée un nouvel item FAQ
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO section_faq_items (section_id, question, answer, icon, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['section_id'],
            $data['question'],
            $data['answer'],
            $data['icon'] ?? null,
            $data['sort_order'] ?? $this->getNextSortOrder($data['section_id']),
            $data['status'] ?? 'active'
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un item FAQ
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = ['question', 'answer', 'icon', 'sort_order', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = 'UPDATE section_faq_items SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Supprime un item FAQ
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM section_faq_items WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime tous les items d'une section
     */
    public function deleteBySection(int $sectionId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM section_faq_items WHERE section_id = ?');
        return $stmt->execute([$sectionId]);
    }

    /**
     * Met à jour l'ordre des items
     */
    public function updateOrder(int $sectionId, array $orderedIds): bool
    {
        $stmt = $this->db->prepare('UPDATE section_faq_items SET sort_order = ? WHERE id = ? AND section_id = ?');

        foreach ($orderedIds as $order => $id) {
            $stmt->execute([$order, $id, $sectionId]);
        }

        return true;
    }

    /**
     * Sauvegarde en masse les items FAQ (remplace tous les existants)
     */
    public function saveAll(int $sectionId, array $items): bool
    {
        // Supprimer les existants
        $this->deleteBySection($sectionId);

        // Insérer les nouveaux
        foreach ($items as $order => $item) {
            if (!empty($item['question']) && !empty($item['answer'])) {
                $this->create([
                    'section_id' => $sectionId,
                    'question' => $item['question'],
                    'answer' => $item['answer'],
                    'icon' => $item['icon'] ?? null,
                    'sort_order' => $order,
                    'status' => $item['status'] ?? 'active'
                ]);
            }
        }

        return true;
    }

    /**
     * Récupère le prochain sort_order
     */
    private function getNextSortOrder(int $sectionId): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) FROM section_faq_items WHERE section_id = ?');
        $stmt->execute([$sectionId]);
        return ((int) $stmt->fetchColumn()) + 1;
    }
}
