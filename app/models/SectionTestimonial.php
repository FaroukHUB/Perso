<?php
/**
 * PERSONNALY - Modèle SectionTestimonial
 * Gestion des témoignages liés aux sections
 */

require_once __DIR__ . '/../core/Database.php';

class SectionTestimonial
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère un témoignage par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM section_testimonials WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Liste les témoignages d'une section
     */
    public function findBySection(int $sectionId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM section_testimonials WHERE section_id = ?';
        if ($activeOnly) {
            $sql .= ' AND status = "active"';
        }
        $sql .= ' ORDER BY sort_order ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll();
    }

    /**
     * Crée un nouveau témoignage
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO section_testimonials
             (section_id, author_name, author_title, author_photo, content, rating, company, source, source_url, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['section_id'],
            $data['author_name'],
            $data['author_title'] ?? null,
            $data['author_photo'] ?? null,
            $data['content'],
            $data['rating'] ?? null,
            $data['company'] ?? null,
            $data['source'] ?? null,
            $data['source_url'] ?? null,
            $data['sort_order'] ?? $this->getNextSortOrder($data['section_id']),
            $data['status'] ?? 'active'
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un témoignage
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = ['author_name', 'author_title', 'author_photo', 'content', 'rating', 'company', 'source', 'source_url', 'sort_order', 'status'];

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
        $sql = 'UPDATE section_testimonials SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Supprime un témoignage
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM section_testimonials WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime tous les témoignages d'une section
     */
    public function deleteBySection(int $sectionId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM section_testimonials WHERE section_id = ?');
        return $stmt->execute([$sectionId]);
    }

    /**
     * Met à jour l'ordre des témoignages
     */
    public function updateOrder(int $sectionId, array $orderedIds): bool
    {
        $stmt = $this->db->prepare('UPDATE section_testimonials SET sort_order = ? WHERE id = ? AND section_id = ?');

        foreach ($orderedIds as $order => $id) {
            $stmt->execute([$order, $id, $sectionId]);
        }

        return true;
    }

    /**
     * Sauvegarde en masse les témoignages
     */
    public function saveAll(int $sectionId, array $items): bool
    {
        // Supprimer les existants
        $this->deleteBySection($sectionId);

        // Insérer les nouveaux
        foreach ($items as $order => $item) {
            if (!empty($item['author_name']) && !empty($item['content'])) {
                $this->create([
                    'section_id' => $sectionId,
                    'author_name' => $item['author_name'],
                    'author_title' => $item['author_title'] ?? null,
                    'author_photo' => $item['author_photo'] ?? null,
                    'content' => $item['content'],
                    'rating' => !empty($item['rating']) ? (int)$item['rating'] : null,
                    'company' => $item['company'] ?? null,
                    'source' => $item['source'] ?? null,
                    'source_url' => $item['source_url'] ?? null,
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
        $stmt = $this->db->prepare('SELECT MAX(sort_order) FROM section_testimonials WHERE section_id = ?');
        $stmt->execute([$sectionId]);
        return ((int) $stmt->fetchColumn()) + 1;
    }
}
