<?php
/**
 * PERSONNALY - Modèle Popup
 * Popups promotionnels
 */

require_once __DIR__ . '/../core/Database.php';

class Popup
{
    private $db;

    const TRIGGER_TYPES = [
        'delay' => 'Après un délai',
        'scroll' => 'Au scroll',
        'exit_intent' => 'Intention de sortie',
        'click' => 'Au clic'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM popups WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $popup = $stmt->fetch();
        if ($popup) {
            $popup['pages'] = json_decode($popup['pages'] ?? '["all"]', true);
        }
        return $popup ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM popups ORDER BY created_at DESC');
        $popups = $stmt->fetchAll();
        foreach ($popups as &$popup) {
            $popup['pages'] = json_decode($popup['pages'] ?? '["all"]', true);
        }
        return $popups;
    }

    /**
     * Récupère le popup actif (raccourci)
     */
    public function getActivePopup(): ?array
    {
        return $this->getActiveForPage('all', false);
    }

    /**
     * Récupère le popup actif pour une page donnée
     */
    public function getActiveForPage(string $pageSlug = 'all', bool $isMobile = false): ?array
    {
        $now = date('Y-m-d H:i:s');
        $sql = 'SELECT * FROM popups
                WHERE status = "active"
                AND (start_date IS NULL OR start_date <= ?)
                AND (end_date IS NULL OR end_date >= ?)';

        if ($isMobile) {
            $sql .= ' AND show_on_mobile = 1';
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 10';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$now, $now]);
        $popups = $stmt->fetchAll();

        foreach ($popups as $popup) {
            $pages = json_decode($popup['pages'] ?? '["all"]', true);
            if (in_array('all', $pages) || in_array($pageSlug, $pages)) {
                $popup['pages'] = $pages;
                return $popup;
            }
        }

        return null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO popups
             (name, title, content, image_url, cta_text, cta_url, background_color, text_color, overlay_color, width,
              trigger_type, trigger_value, trigger_selector, show_once, show_on_mobile, pages, start_date, end_date, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['name'],
            $data['title'] ?? null,
            $data['content'] ?? null,
            $data['image_url'] ?? null,
            $data['cta_text'] ?? null,
            $data['cta_url'] ?? null,
            $data['background_color'] ?? '#FFFFFF',
            $data['text_color'] ?? '#1A1A1A',
            $data['overlay_color'] ?? 'rgba(0,0,0,0.5)',
            $data['width'] ?? '500px',
            $data['trigger_type'] ?? 'delay',
            $data['trigger_value'] ?? '3000',
            $data['trigger_selector'] ?? null,
            $data['show_once'] ?? 1,
            $data['show_on_mobile'] ?? 1,
            json_encode($data['pages'] ?? ['all']),
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['status'] ?? 'draft'
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        $allowedFields = [
            'name', 'title', 'content', 'image_url', 'cta_text', 'cta_url',
            'background_color', 'text_color', 'overlay_color', 'width',
            'trigger_type', 'trigger_value', 'trigger_selector',
            'show_once', 'show_on_mobile', 'start_date', 'end_date', 'status'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        if (array_key_exists('pages', $data)) {
            $fields[] = '`pages` = ?';
            $values[] = json_encode($data['pages']);
        }

        if (empty($fields)) return false;

        $values[] = $id;
        $sql = 'UPDATE popups SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM popups WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function incrementViews(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE popups SET views = views + 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function incrementClicks(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE popups SET clicks = clicks + 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function getTriggerTypes(): array
    {
        return self::TRIGGER_TYPES;
    }
}
