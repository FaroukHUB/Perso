<?php
/**
 * PERSONNALY - Modèle SectionTemplate
 * Templates de sections réutilisables
 */

require_once __DIR__ . '/../core/Database.php';

class SectionTemplate
{
    private $db;

    const CATEGORIES = [
        'hero' => 'Hero / En-tête',
        'content' => 'Contenu',
        'commerce' => 'E-commerce',
        'social' => 'Social Proof',
        'contact' => 'Contact',
        'other' => 'Autre'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM section_templates WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $template = $stmt->fetch();
        if ($template) {
            $template['section_data'] = json_decode($template['section_data'], true);
        }
        return $template ?: null;
    }

    public function findAll(?string $category = null, bool $globalOnly = false): array
    {
        $sql = 'SELECT * FROM section_templates WHERE 1=1';
        $params = [];

        if ($category) {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }

        if ($globalOnly) {
            $sql .= ' AND is_global = 1';
        }

        $sql .= ' ORDER BY usage_count DESC, name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $templates = $stmt->fetchAll();

        foreach ($templates as &$template) {
            $template['section_data'] = json_decode($template['section_data'], true);
        }

        return $templates;
    }

    /**
     * Crée un template à partir d'une section existante
     */
    public function createFromSection(int $sectionId, string $name, ?string $description = null): int
    {
        require_once __DIR__ . '/PageSection.php';
        $sectionModel = new PageSection();
        $section = $sectionModel->findById($sectionId);

        if (!$section) {
            throw new Exception('Section introuvable');
        }

        // Préparer les données du template
        $sectionData = [
            'type' => $section['type'],
            'title' => $section['title'],
            'subtitle' => $section['subtitle'],
            'content' => $section['content'],
            'cta_text' => $section['cta_text'],
            'cta_url' => $section['cta_url'],
            'media_type' => $section['media_type'],
            'media_url' => $section['media_url'],
            'config' => $section['config'],
            'items' => $section['items'] ?? [],
            'faq_items' => $section['faq_items'] ?? [],
            'testimonials' => $section['testimonials'] ?? [],
            'gallery_images' => $section['gallery_images'] ?? [],
            'counters' => $section['counters'] ?? [],
            'timeline_steps' => $section['timeline_steps'] ?? [],
            'logos' => $section['logos'] ?? []
        ];

        // Déterminer la catégorie
        $category = $this->getCategoryForType($section['type']);

        return $this->create([
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'section_data' => $sectionData,
            'is_global' => 0
        ]);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO section_templates (name, description, category, thumbnail_url, section_data, is_global, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['category'] ?? 'other',
            $data['thumbnail_url'] ?? null,
            json_encode($data['section_data'], JSON_UNESCAPED_UNICODE),
            $data['is_global'] ?? 0,
            $data['created_by'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        $allowedFields = ['name', 'description', 'category', 'thumbnail_url', 'is_global'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        if (array_key_exists('section_data', $data)) {
            $fields[] = '`section_data` = ?';
            $values[] = json_encode($data['section_data'], JSON_UNESCAPED_UNICODE);
        }

        if (empty($fields)) return false;

        $values[] = $id;
        $sql = 'UPDATE section_templates SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM section_templates WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function incrementUsage(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE section_templates SET usage_count = usage_count + 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function getCategories(): array
    {
        return self::CATEGORIES;
    }

    private function getCategoryForType(string $type): string
    {
        $typeCategories = [
            'hero' => 'hero',
            'content_block' => 'content',
            'text_only' => 'content',
            'video' => 'content',
            'image_gallery' => 'content',
            'featured_products' => 'commerce',
            'featured_packs' => 'commerce',
            'featured_category' => 'commerce',
            'testimonials' => 'social',
            'google_reviews' => 'social',
            'counter' => 'social',
            'logos' => 'social',
            'faq' => 'contact',
            'contact_form' => 'contact',
            'newsletter' => 'contact',
            'google_map' => 'contact'
        ];

        return $typeCategories[$type] ?? 'other';
    }

    /**
     * Applique un template à une page
     */
    public function applyToPage(int $templateId, int $pageId): int
    {
        $template = $this->findById($templateId);
        if (!$template) {
            throw new Exception('Template introuvable');
        }

        require_once __DIR__ . '/PageSection.php';
        $sectionModel = new PageSection();

        $data = $template['section_data'];
        $data['page_id'] = $pageId;
        $data['status'] = 'draft';

        $sectionId = $sectionModel->create($data);

        // Créer les items associés selon le type
        if (!empty($data['faq_items'])) {
            require_once __DIR__ . '/SectionFaq.php';
            $faqModel = new SectionFaq();
            $faqModel->saveAll($sectionId, $data['faq_items']);
        }

        if (!empty($data['testimonials'])) {
            require_once __DIR__ . '/SectionTestimonial.php';
            $testimonialModel = new SectionTestimonial();
            $testimonialModel->saveAll($sectionId, $data['testimonials']);
        }

        if (!empty($data['gallery_images'])) {
            require_once __DIR__ . '/SectionGallery.php';
            $galleryModel = new SectionGallery();
            $galleryModel->saveAll($sectionId, $data['gallery_images']);
        }

        if (!empty($data['counters'])) {
            require_once __DIR__ . '/SectionCounter.php';
            $counterModel = new SectionCounter();
            $counterModel->saveAll($sectionId, $data['counters']);
        }

        if (!empty($data['timeline_steps'])) {
            require_once __DIR__ . '/SectionTimeline.php';
            $timelineModel = new SectionTimeline();
            $timelineModel->saveAll($sectionId, $data['timeline_steps']);
        }

        if (!empty($data['logos'])) {
            require_once __DIR__ . '/SectionLogo.php';
            $logoModel = new SectionLogo();
            $logoModel->saveAll($sectionId, $data['logos']);
        }

        $this->incrementUsage($templateId);

        return $sectionId;
    }
}
