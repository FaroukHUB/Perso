<?php
/**
 * PERSONNALY - Modèle Page
 * Gestion des pages personnalisées
 */

require_once __DIR__ . '/../core/Database.php';

class Page
{
    private $db;

    const STATUSES = [
        'draft' => 'Brouillon',
        'published' => 'Publié'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère une page par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pages WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Récupère une page par slug
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pages WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Liste toutes les pages
     */
    public function findAll(bool $publishedOnly = false): array
    {
        $sql = 'SELECT p.*,
                       (SELECT COUNT(*) FROM page_sections ps WHERE ps.page_id = p.id) as section_count
                FROM pages p';
        if ($publishedOnly) {
            $sql .= ' WHERE p.status = "published"';
        }
        $sql .= ' ORDER BY p.is_system DESC, p.title ASC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Crée une nouvelle page
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pages (title, slug, meta_title, meta_description, status, is_system, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['title'],
            $this->generateSlug($data['slug'] ?? $data['title']),
            $data['meta_title'] ?? null,
            $data['meta_description'] ?? null,
            $data['status'] ?? 'draft',
            $data['is_system'] ?? 0
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour une page
     */
    public function update(int $id, array $data): bool
    {
        $page = $this->findById($id);
        if (!$page) return false;

        $fields = [];
        $values = [];

        $allowedFields = ['title', 'meta_title', 'meta_description', 'status'];

        // Ne pas permettre de modifier le slug de la page d'accueil
        if (!$page['is_system']) {
            $allowedFields[] = 'slug';
        }

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'slug') {
                    $fields[] = "`$field` = ?";
                    $values[] = $this->generateSlug($data[$field], $id);
                } else {
                    $fields[] = "`$field` = ?";
                    $values[] = $data[$field];
                }
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = 'UPDATE pages SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Supprime une page (sauf pages système)
     */
    public function delete(int $id): bool
    {
        $page = $this->findById($id);
        if (!$page || $page['is_system']) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM pages WHERE id = ? AND is_system = 0');
        return $stmt->execute([$id]);
    }

    /**
     * Duplique une page
     */
    public function duplicate(int $id): ?int
    {
        $page = $this->findById($id);
        if (!$page) return null;

        // Créer la nouvelle page
        $newPageId = $this->create([
            'title' => $page['title'] . ' (copie)',
            'slug' => $page['slug'] . '-copie',
            'meta_title' => $page['meta_title'],
            'meta_description' => $page['meta_description'],
            'status' => 'draft',
            'is_system' => 0
        ]);

        // Copier les sections
        $sectionModel = new PageSection();
        $sections = $sectionModel->findByPage($id);

        foreach ($sections as $section) {
            $sectionData = [
                'page_id' => $newPageId,
                'type' => $section['type'],
                'title' => $section['title'],
                'subtitle' => $section['subtitle'],
                'content' => $section['content'],
                'cta_text' => $section['cta_text'],
                'cta_url' => $section['cta_url'],
                'media_type' => $section['media_type'],
                'media_url' => $section['media_url'],
                'config' => $section['config'],
                'sort_order' => $section['sort_order'],
                'status' => 'draft'
            ];

            $newSectionId = $sectionModel->create($sectionData);

            // Copier les items de la section
            if (!empty($section['items'])) {
                $items = [];
                foreach ($section['items'] as $item) {
                    $items[] = ['type' => $item['item_type'], 'id' => $item['item_id']];
                }
                $sectionModel->setItems($newSectionId, $items);
            }
        }

        return $newPageId;
    }

    /**
     * Génère un slug unique
     */
    private function generateSlug(string $text, ?int $excludeId = null): string
    {
        // Convertir en minuscules et remplacer les caractères spéciaux
        $slug = strtolower($text);
        $slug = preg_replace('/[àáâãäå]/u', 'a', $slug);
        $slug = preg_replace('/[èéêë]/u', 'e', $slug);
        $slug = preg_replace('/[ìíîï]/u', 'i', $slug);
        $slug = preg_replace('/[òóôõö]/u', 'o', $slug);
        $slug = preg_replace('/[ùúûü]/u', 'u', $slug);
        $slug = preg_replace('/[ç]/u', 'c', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Vérifier l'unicité
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Vérifie si un slug existe déjà
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM pages WHERE slug = ?';
        $params = [$slug];

        if ($excludeId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère les statuts disponibles
     */
    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    /**
     * Récupère une page avec ses sections
     */
    public function findWithSections(int $id): ?array
    {
        $page = $this->findById($id);
        if (!$page) return null;

        $sectionModel = new PageSection();
        $page['sections'] = $sectionModel->findByPage($id);

        return $page;
    }

    /**
     * Récupère une page par slug avec ses sections actives
     */
    public function findBySlugWithActiveSections(string $slug): ?array
    {
        $page = $this->findBySlug($slug);
        if (!$page || $page['status'] !== 'published') return null;

        $sectionModel = new PageSection();
        $page['sections'] = $sectionModel->findByPage($page['id'], true);

        return $page;
    }
}
