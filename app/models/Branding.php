<?php
/**
 * PERSONNALY - Model Branding
 * Gestion des parametres de branding multi-client
 *
 * Resolution: client_id specifique > global (NULL) > constantes PHP
 */

require_once __DIR__ . '/../core/Database.php';

class Branding
{
    private PDO $db;
    private static ?bool $tableExistsCache = null;

    // =========================================
    // CONSTANTES PAR DEFAUT (fallback ultime)
    // =========================================
    public const DEFAULTS = [
        'font_primary' => 'Poppins',
        'font_primary_url' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
        'font_secondary' => 'Inter',
        'font_secondary_url' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap',
        'color_primary' => '#6366F1',
        'color_secondary' => '#8B5CF6',
        'color_accent' => '#F59E0B',
        'color_text' => '#1F2937',
        'color_text_light' => '#6B7280',
        'color_background' => '#FFFFFF',
        'color_surface' => '#F9FAFB',
        'color_button' => '#6366F1',
        'color_button_text' => '#FFFFFF',
        'border_radius' => 'medium',
        'shadow_intensity' => 'subtle',
        'logo_url' => null,
        'logo_light_url' => null,
        'favicon_url' => null,
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================
    // LECTURE
    // =========================================

    /**
     * Verifie si la table branding_settings existe
     */
    public function tableExists(): bool
    {
        if (self::$tableExistsCache !== null) {
            return self::$tableExistsCache;
        }
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'branding_settings'");
            self::$tableExistsCache = $stmt->rowCount() > 0;
            return self::$tableExistsCache;
        } catch (Exception $e) {
            self::$tableExistsCache = false;
            return false;
        }
    }

    /**
     * Recupere la config globale (client_id = NULL)
     */
    public function findGlobal(): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        try {
            $stmt = $this->db->query(
                'SELECT * FROM branding_settings WHERE client_id IS NULL LIMIT 1'
            );
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Recupere la config d'un client specifique
     */
    public function findByClientId(int $clientId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM branding_settings WHERE client_id = ? LIMIT 1'
        );
        $stmt->execute([$clientId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Recupere une config par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM branding_settings WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Resolution complete du branding avec cascade:
     * 1. Config client specifique (si clientId fourni)
     * 2. Config globale (client_id = NULL)
     * 3. Constantes PHP par defaut
     *
     * @param int|null $clientId ID client ou null pour global
     * @return array Configuration complete avec toutes les valeurs
     */
    public function resolveBranding(?int $clientId = null): array
    {
        $clientConfig = null;
        $globalConfig = null;

        // 1. Chercher config client si ID fourni
        if ($clientId !== null) {
            $clientConfig = $this->findByClientId($clientId);
        }

        // 2. Chercher config globale
        $globalConfig = $this->findGlobal();

        // 3. Merger avec priorite: client > global > defaults
        $resolved = self::DEFAULTS;

        // Appliquer config globale
        if ($globalConfig) {
            foreach ($resolved as $key => $default) {
                if (isset($globalConfig[$key]) && $globalConfig[$key] !== null) {
                    $resolved[$key] = $globalConfig[$key];
                }
            }
        }

        // Appliquer config client (override global)
        if ($clientConfig) {
            foreach ($resolved as $key => $default) {
                if (isset($clientConfig[$key]) && $clientConfig[$key] !== null) {
                    $resolved[$key] = $clientConfig[$key];
                }
            }
        }

        return $resolved;
    }

    // =========================================
    // CREATION / MODIFICATION
    // =========================================

    /**
     * Cree ou met a jour la config globale
     */
    public function upsertGlobal(array $data): bool
    {
        $existing = $this->findGlobal();

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->create(array_merge($data, ['client_id' => null]));
    }

    /**
     * Cree ou met a jour la config d'un client
     */
    public function upsertForClient(int $clientId, array $data): bool
    {
        $existing = $this->findByClientId($clientId);

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->create(array_merge($data, ['client_id' => $clientId]));
    }

    /**
     * Cree une nouvelle config
     */
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO branding_settings (
                client_id,
                font_primary, font_primary_url, font_secondary, font_secondary_url,
                color_primary, color_secondary, color_accent,
                color_text, color_text_light,
                color_background, color_surface,
                color_button, color_button_text,
                border_radius, shadow_intensity,
                logo_url, logo_light_url, favicon_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        return $stmt->execute([
            $data['client_id'] ?? null,
            $data['font_primary'] ?? null,
            $data['font_primary_url'] ?? null,
            $data['font_secondary'] ?? null,
            $data['font_secondary_url'] ?? null,
            $data['color_primary'] ?? null,
            $data['color_secondary'] ?? null,
            $data['color_accent'] ?? null,
            $data['color_text'] ?? null,
            $data['color_text_light'] ?? null,
            $data['color_background'] ?? null,
            $data['color_surface'] ?? null,
            $data['color_button'] ?? null,
            $data['color_button_text'] ?? null,
            $data['border_radius'] ?? 'medium',
            $data['shadow_intensity'] ?? 'subtle',
            $data['logo_url'] ?? null,
            $data['logo_light_url'] ?? null,
            $data['favicon_url'] ?? null,
        ]);
    }

    /**
     * Met a jour une config existante
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = [
            'font_primary', 'font_primary_url', 'font_secondary', 'font_secondary_url',
            'color_primary', 'color_secondary', 'color_accent',
            'color_text', 'color_text_light',
            'color_background', 'color_surface',
            'color_button', 'color_button_text',
            'border_radius', 'shadow_intensity',
            'logo_url', 'logo_light_url', 'favicon_url'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;

        $stmt = $this->db->prepare(
            'UPDATE branding_settings SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?'
        );

        return $stmt->execute($values);
    }

    /**
     * Supprime une config (sauf globale)
     */
    public function delete(int $id): bool
    {
        // Ne pas permettre suppression de la config globale
        $config = $this->findById($id);
        if ($config && $config['client_id'] === null) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM branding_settings WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // =========================================
    // STYLES SECTIONS HOMEPAGE
    // =========================================

    /**
     * Recupere les styles de sections pour un client (ou global)
     */
    public function getSectionStyles(?int $clientId = null): array
    {
        if ($clientId !== null) {
            // Chercher config client d'abord
            $stmt = $this->db->prepare(
                'SELECT * FROM homepage_section_styles
                 WHERE client_id = ? AND active = 1
                 ORDER BY sort_order ASC'
            );
            $stmt->execute([$clientId]);
            $styles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($styles)) {
                return $styles;
            }
        }

        // Fallback: config globale
        $stmt = $this->db->query(
            'SELECT * FROM homepage_section_styles
             WHERE client_id IS NULL AND active = 1
             ORDER BY sort_order ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recupere le style d'une section specifique
     */
    public function getSectionStyle(string $sectionKey, ?int $clientId = null): ?array
    {
        // Defaults par section
        $defaults = [
            'background_color' => '#FFFFFF',
            'background_image' => null,
            'background_overlay' => null,
            'background_overlay_opacity' => null,
            'text_color_override' => null,
            'padding_y' => 'medium',
        ];

        if ($clientId !== null) {
            $stmt = $this->db->prepare(
                'SELECT * FROM homepage_section_styles
                 WHERE client_id = ? AND section_key = ? AND active = 1'
            );
            $stmt->execute([$clientId, $sectionKey]);
            $style = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($style) {
                return array_merge($defaults, array_filter($style, fn($v) => $v !== null));
            }
        }

        // Fallback: config globale
        $stmt = $this->db->prepare(
            'SELECT * FROM homepage_section_styles
             WHERE client_id IS NULL AND section_key = ? AND active = 1'
        );
        $stmt->execute([$sectionKey]);
        $style = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($style) {
            return array_merge($defaults, array_filter($style, fn($v) => $v !== null));
        }

        return $defaults;
    }

    /**
     * Met a jour le style d'une section
     */
    public function upsertSectionStyle(string $sectionKey, array $data, ?int $clientId = null): bool
    {
        // Verifier si existe
        if ($clientId !== null) {
            $stmt = $this->db->prepare(
                'SELECT id FROM homepage_section_styles WHERE client_id = ? AND section_key = ?'
            );
            $stmt->execute([$clientId, $sectionKey]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT id FROM homepage_section_styles WHERE client_id IS NULL AND section_key = ?'
            );
            $stmt->execute([$sectionKey]);
        }

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update
            $stmt = $this->db->prepare(
                'UPDATE homepage_section_styles SET
                 background_color = ?, background_image = ?,
                 background_overlay = ?, background_overlay_opacity = ?,
                 text_color_override = ?, padding_y = ?,
                 sort_order = ?, active = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            return $stmt->execute([
                $data['background_color'] ?? null,
                $data['background_image'] ?? null,
                $data['background_overlay'] ?? null,
                $data['background_overlay_opacity'] ?? null,
                $data['text_color_override'] ?? null,
                $data['padding_y'] ?? 'medium',
                $data['sort_order'] ?? 0,
                $data['active'] ?? 1,
                $existing['id']
            ]);
        }

        // Insert
        $stmt = $this->db->prepare(
            'INSERT INTO homepage_section_styles (
                client_id, section_key,
                background_color, background_image,
                background_overlay, background_overlay_opacity,
                text_color_override, padding_y,
                sort_order, active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        return $stmt->execute([
            $clientId,
            $sectionKey,
            $data['background_color'] ?? null,
            $data['background_image'] ?? null,
            $data['background_overlay'] ?? null,
            $data['background_overlay_opacity'] ?? null,
            $data['text_color_override'] ?? null,
            $data['padding_y'] ?? 'medium',
            $data['sort_order'] ?? 0,
            $data['active'] ?? 1,
        ]);
    }

    // =========================================
    // UTILITAIRES
    // =========================================

    /**
     * Liste toutes les configs (pour admin)
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT bs.*, u.email as client_email
             FROM branding_settings bs
             LEFT JOIN users u ON bs.client_id = u.id
             ORDER BY bs.client_id IS NULL DESC, u.email ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valeurs possibles pour border_radius
     */
    public static function getBorderRadiusOptions(): array
    {
        return [
            'none' => '0px',
            'small' => '4px',
            'medium' => '8px',
            'large' => '12px',
            'full' => '9999px',
        ];
    }

    /**
     * Valeurs possibles pour shadow_intensity
     */
    public static function getShadowOptions(): array
    {
        return [
            'none' => 'none',
            'subtle' => '0 1px 3px rgba(0,0,0,0.1)',
            'medium' => '0 4px 6px rgba(0,0,0,0.1)',
            'strong' => '0 10px 25px rgba(0,0,0,0.15)',
        ];
    }

    /**
     * Valeurs possibles pour padding_y
     */
    public static function getPaddingOptions(): array
    {
        return [
            'none' => '0',
            'small' => '2rem',
            'medium' => '4rem',
            'large' => '6rem',
            'xlarge' => '8rem',
        ];
    }
}
