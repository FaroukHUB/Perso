<?php
/**
 * PERSONNALY - Modèle PromoBanner
 * Bannières promotionnelles (top bar)
 */

require_once __DIR__ . '/../core/Database.php';

class PromoBanner
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM promo_banners WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $banner = $stmt->fetch();
        if ($banner) {
            $banner['pages'] = json_decode($banner['pages'] ?? '["all"]', true);
        }
        return $banner ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM promo_banners ORDER BY priority DESC, created_at DESC');
        $banners = $stmt->fetchAll();
        foreach ($banners as &$banner) {
            $banner['pages'] = json_decode($banner['pages'] ?? '["all"]', true);
        }
        return $banners;
    }

    /**
     * Récupère la bannière active (raccourci)
     */
    public function getActiveBanner(): ?array
    {
        return $this->getActiveForPage('all');
    }

    /**
     * Récupère la bannière active pour une page donnée
     */
    public function getActiveForPage(string $pageSlug = 'all'): ?array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'SELECT * FROM promo_banners
             WHERE status = "active"
             AND (start_date IS NULL OR start_date <= ?)
             AND (end_date IS NULL OR end_date >= ?)
             ORDER BY priority DESC
             LIMIT 10'
        );
        $stmt->execute([$now, $now]);
        $banners = $stmt->fetchAll();

        foreach ($banners as $banner) {
            $pages = json_decode($banner['pages'] ?? '["all"]', true);
            if (in_array('all', $pages) || in_array($pageSlug, $pages)) {
                $banner['pages'] = $pages;
                return $banner;
            }
        }

        return null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO promo_banners
             (message, link_url, link_text, background_color, text_color, icon, start_date, end_date, pages, is_dismissible, priority, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['message'],
            $data['link_url'] ?? null,
            $data['link_text'] ?? null,
            $data['background_color'] ?? '#FF69B4',
            $data['text_color'] ?? '#FFFFFF',
            $data['icon'] ?? null,
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            json_encode($data['pages'] ?? ['all']),
            $data['is_dismissible'] ?? 1,
            $data['priority'] ?? 0,
            $data['status'] ?? 'draft'
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        $allowedFields = ['message', 'link_url', 'link_text', 'background_color', 'text_color', 'icon', 'start_date', 'end_date', 'is_dismissible', 'priority', 'status'];

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
        $sql = 'UPDATE promo_banners SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM promo_banners WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function toggleStatus(int $id): bool
    {
        $banner = $this->findById($id);
        if (!$banner) return false;

        $newStatus = $banner['status'] === 'active' ? 'draft' : 'active';
        return $this->update($id, ['status' => $newStatus]);
    }
}
