<?php
/**
 * PERSONNALY - Modèle SectionGallery
 * Gestion des images de galerie liées aux sections
 */

require_once __DIR__ . '/../core/Database.php';

class SectionGallery
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère une image par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM section_gallery_images WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Liste les images d'une section
     */
    public function findBySection(int $sectionId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM section_gallery_images WHERE section_id = ?';
        if ($activeOnly) {
            $sql .= ' AND status = "active"';
        }
        $sql .= ' ORDER BY sort_order ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll();
    }

    /**
     * Crée une nouvelle image
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO section_gallery_images
             (section_id, image_url, thumbnail_url, alt_text, caption, link_url, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['section_id'],
            $data['image_url'],
            $data['thumbnail_url'] ?? null,
            $data['alt_text'] ?? null,
            $data['caption'] ?? null,
            $data['link_url'] ?? null,
            $data['sort_order'] ?? $this->getNextSortOrder($data['section_id']),
            $data['status'] ?? 'active'
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour une image
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = ['image_url', 'thumbnail_url', 'alt_text', 'caption', 'link_url', 'sort_order', 'status'];

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
        $sql = 'UPDATE section_gallery_images SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Supprime une image
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM section_gallery_images WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime toutes les images d'une section
     */
    public function deleteBySection(int $sectionId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM section_gallery_images WHERE section_id = ?');
        return $stmt->execute([$sectionId]);
    }

    /**
     * Met à jour l'ordre des images
     */
    public function updateOrder(int $sectionId, array $orderedIds): bool
    {
        $stmt = $this->db->prepare('UPDATE section_gallery_images SET sort_order = ? WHERE id = ? AND section_id = ?');

        foreach ($orderedIds as $order => $id) {
            $stmt->execute([$order, $id, $sectionId]);
        }

        return true;
    }

    /**
     * Sauvegarde en masse les images
     */
    public function saveAll(int $sectionId, array $items): bool
    {
        // Supprimer les existants
        $this->deleteBySection($sectionId);

        // Insérer les nouveaux
        foreach ($items as $order => $item) {
            if (!empty($item['image_url'])) {
                $this->create([
                    'section_id' => $sectionId,
                    'image_url' => $item['image_url'],
                    'thumbnail_url' => $item['thumbnail_url'] ?? null,
                    'alt_text' => $item['alt_text'] ?? null,
                    'caption' => $item['caption'] ?? null,
                    'link_url' => $item['link_url'] ?? null,
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
        $stmt = $this->db->prepare('SELECT MAX(sort_order) FROM section_gallery_images WHERE section_id = ?');
        $stmt->execute([$sectionId]);
        return ((int) $stmt->fetchColumn()) + 1;
    }
}
