<?php
/**
 * PERSONNALY - Model SiteSetting
 * Gestion des paramètres du site
 */

require_once __DIR__ . '/../core/Database.php';

class SiteSetting
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function get(string $key, $default = null)
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    }

    public function set(string $key, $value): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        return $stmt->execute([$key, $value]);
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT setting_key, setting_value FROM site_settings');
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public function getLogo(): array
    {
        return [
            'type' => $this->get('logo_type', 'text'),
            'text' => $this->get('logo_text', 'PERSONNALY'),
            'image_url' => $this->get('logo_image_url')
        ];
    }

    public function setLogo(string $type, ?string $text = null, ?string $imageUrl = null): bool
    {
        $this->set('logo_type', $type);
        if ($text !== null) {
            $this->set('logo_text', $text);
        }
        if ($imageUrl !== null) {
            $this->set('logo_image_url', $imageUrl);
        }
        return true;
    }
}
