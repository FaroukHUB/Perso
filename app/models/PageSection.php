<?php
/**
 * PERSONNALY - Modèle PageSection
 * Gestion des sections de pages personnalisées
 */

require_once __DIR__ . '/../core/Database.php';

class PageSection
{
    private $db;

    // Types de sections autorisés
    const TYPES = [
        'hero' => 'Hero (Plein écran)',
        'featured_products' => 'Produits à la une',
        'featured_packs' => 'Packs / Idées à la une',
        'featured_category' => 'Catégorie à la une',
        'content_block' => 'Bloc contenu (texte + média)',
        'blog_slider' => 'Slider Blog',
        'newsletter' => 'Newsletter',
        'text_only' => 'Texte seul',
        'image_gallery' => 'Galerie d\'images',
        'video' => 'Vidéo',
        'faq' => 'FAQ / Accordéon',
        'testimonials' => 'Témoignages',
        'contact_form' => 'Formulaire de contact'
    ];

    const STATUSES = [
        'draft' => 'Brouillon',
        'active' => 'Actif'
    ];

    const MEDIA_TYPES = [
        'none' => 'Aucun',
        'image' => 'Image',
        'video' => 'Vidéo'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère une section par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM page_sections WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $section = $stmt->fetch();

        if ($section) {
            $section['config'] = json_decode($section['config_json'] ?? '{}', true) ?? [];
            $section['items'] = $this->getItems($id);
        }

        return $section ?: null;
    }

    /**
     * Liste les sections d'une page
     */
    public function findByPage(int $pageId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM page_sections WHERE page_id = ?';
        if ($activeOnly) {
            $sql .= ' AND status = "active"';
        }
        $sql .= ' ORDER BY sort_order ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$pageId]);
        $sections = $stmt->fetchAll();

        foreach ($sections as &$section) {
            $section['config'] = json_decode($section['config_json'] ?? '{}', true) ?? [];
            $section['items'] = $this->getItems($section['id']);
        }

        return $sections;
    }

    /**
     * Crée une nouvelle section
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO page_sections
             (page_id, type, title, subtitle, content, cta_text, cta_url, media_type, media_url, config_json, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['page_id'],
            $data['type'],
            $data['title'] ?? null,
            $data['subtitle'] ?? null,
            $data['content'] ?? null,
            $data['cta_text'] ?? null,
            $data['cta_url'] ?? null,
            $data['media_type'] ?? 'none',
            $data['media_url'] ?? null,
            json_encode($data['config'] ?? [], JSON_UNESCAPED_UNICODE),
            $data['sort_order'] ?? $this->getNextSortOrder($data['page_id']),
            $data['status'] ?? 'draft'
        ]);

        $sectionId = (int) $this->db->lastInsertId();

        // Ajouter les items si fournis
        if (!empty($data['items'])) {
            $this->setItems($sectionId, $data['items']);
        }

        return $sectionId;
    }

    /**
     * Met à jour une section
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = ['type', 'title', 'subtitle', 'content', 'cta_text', 'cta_url', 'media_type', 'media_url', 'sort_order', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        // Gestion du config JSON
        if (array_key_exists('config', $data)) {
            $fields[] = '`config_json` = ?';
            $values[] = json_encode($data['config'], JSON_UNESCAPED_UNICODE);
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = 'UPDATE page_sections SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);

        // Mettre à jour les items si fournis
        if (array_key_exists('items', $data)) {
            $this->setItems($id, $data['items']);
        }

        return $result;
    }

    /**
     * Supprime une section
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM page_sections WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Change le statut d'une section
     */
    public function toggleStatus(int $id): bool
    {
        $section = $this->findById($id);
        if (!$section) return false;

        $newStatus = $section['status'] === 'active' ? 'draft' : 'active';
        return $this->update($id, ['status' => $newStatus]);
    }

    /**
     * Met à jour l'ordre des sections d'une page
     */
    public function updateOrder(int $pageId, array $orderedIds): bool
    {
        $stmt = $this->db->prepare('UPDATE page_sections SET sort_order = ? WHERE id = ? AND page_id = ?');

        foreach ($orderedIds as $order => $id) {
            $stmt->execute([$order, $id, $pageId]);
        }

        return true;
    }

    // ========== GESTION DES ITEMS ==========

    /**
     * Récupère les items d'une section avec leurs données complètes
     */
    public function getItems(int $sectionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT si.*,
                    CASE
                        WHEN si.item_type = "product" THEN p.name
                        WHEN si.item_type = "pack" THEN pk.name
                        WHEN si.item_type = "blog" THEN bp.title
                    END as item_name,
                    CASE
                        WHEN si.item_type = "product" THEN p.image_front_url
                        WHEN si.item_type = "pack" THEN pk.cover_image_url
                        WHEN si.item_type = "blog" THEN bp.cover_image_url
                    END as item_image,
                    CASE
                        WHEN si.item_type = "product" THEN p.base_price
                        ELSE NULL
                    END as item_price,
                    CASE
                        WHEN si.item_type = "product" THEN p.active
                        WHEN si.item_type = "pack" THEN (pk.status = "active")
                        WHEN si.item_type = "blog" THEN (bp.status = "published")
                    END as item_active
             FROM page_section_items si
             LEFT JOIN products p ON si.item_type = "product" AND si.item_id = p.id
             LEFT JOIN packs pk ON si.item_type = "pack" AND si.item_id = pk.id
             LEFT JOIN blog_posts bp ON si.item_type = "blog" AND si.item_id = bp.id
             WHERE si.section_id = ?
             ORDER BY si.sort_order ASC'
        );
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll();
    }

    /**
     * Définit les items d'une section (remplace tous les existants)
     */
    public function setItems(int $sectionId, array $items): void
    {
        // Supprimer les associations existantes
        $stmt = $this->db->prepare('DELETE FROM page_section_items WHERE section_id = ?');
        $stmt->execute([$sectionId]);

        // Ajouter les nouvelles associations
        if (!empty($items)) {
            $stmt = $this->db->prepare(
                'INSERT INTO page_section_items (section_id, item_type, item_id, sort_order) VALUES (?, ?, ?, ?)'
            );
            foreach ($items as $order => $item) {
                $stmt->execute([
                    $sectionId,
                    $item['type'],
                    $item['id'],
                    $order
                ]);
            }
        }
    }

    // ========== HELPERS ==========

    /**
     * Récupère le prochain sort_order disponible pour une page
     */
    private function getNextSortOrder(int $pageId): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) FROM page_sections WHERE page_id = ?');
        $stmt->execute([$pageId]);
        return ((int) $stmt->fetchColumn()) + 1;
    }

    /**
     * Récupère les types disponibles
     */
    public function getTypes(): array
    {
        return self::TYPES;
    }

    /**
     * Récupère les statuts disponibles
     */
    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    /**
     * Récupère les types de média disponibles
     */
    public function getMediaTypes(): array
    {
        return self::MEDIA_TYPES;
    }
}
