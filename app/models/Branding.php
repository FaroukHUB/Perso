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
    // NOTE: Plus de constantes hardcodées !
    // La DB est la seule source de vérité.
    // Les valeurs par défaut sont insérées en DB via migrate_typography_system.sql
    // =========================================

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
     * 2. Config globale (client_id = NULL) - REQUIS en DB
     *
     * Note: Plus de fallback hardcodé. La DB est la source de vérité.
     * Si la config globale n'existe pas, une exception est levée.
     *
     * @param int|null $clientId ID client ou null pour global
     * @return array Configuration complete avec toutes les valeurs
     * @throws Exception Si aucune config globale n'existe en DB
     */
    public function resolveBranding(?int $clientId = null): array
    {
        // 1. Chercher config globale (REQUIS)
        $globalConfig = $this->findGlobal();

        if (!$globalConfig) {
            throw new Exception(
                'Branding: Configuration globale manquante en DB. ' .
                'Veuillez exécuter la migration migrate_typography_system.sql'
            );
        }

        // 2. Si un client est spécifié, chercher sa config et merger
        if ($clientId !== null) {
            $clientConfig = $this->findByClientId($clientId);

            if ($clientConfig) {
                // Merger: client override global
                // On garde toutes les clés de global, et on override avec les valeurs client non-null
                foreach ($clientConfig as $key => $value) {
                    if ($value !== null && $key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
                        $globalConfig[$key] = $value;
                    }
                }
            }
        }

        // Décoder les JSON si présents
        if (!empty($globalConfig['typography_scale']) && is_string($globalConfig['typography_scale'])) {
            $globalConfig['typography_scale'] = json_decode($globalConfig['typography_scale'], true);
        }
        if (!empty($globalConfig['button_styles']) && is_string($globalConfig['button_styles'])) {
            $globalConfig['button_styles'] = json_decode($globalConfig['button_styles'], true);
        }
        if (!empty($globalConfig['color_system']) && is_string($globalConfig['color_system'])) {
            $globalConfig['color_system'] = json_decode($globalConfig['color_system'], true);
        }

        return $globalConfig;
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
        // Encoder les JSON si nécessaire
        $typographyScale = isset($data['typography_scale']) && is_array($data['typography_scale'])
            ? json_encode($data['typography_scale'])
            : ($data['typography_scale'] ?? null);

        $buttonStyles = isset($data['button_styles']) && is_array($data['button_styles'])
            ? json_encode($data['button_styles'])
            : ($data['button_styles'] ?? null);

        $colorSystem = isset($data['color_system']) && is_array($data['color_system'])
            ? json_encode($data['color_system'])
            : ($data['color_system'] ?? null);

        $stmt = $this->db->prepare(
            'INSERT INTO branding_settings (
                client_id,
                font_primary_id, font_secondary_id,
                font_primary, font_primary_url, font_secondary, font_secondary_url,
                typography_scale,
                color_primary, color_secondary, color_accent,
                color_text, color_text_light,
                color_background, color_surface,
                color_button, color_button_text,
                border_radius, shadow_intensity,
                button_styles, color_system,
                logo_url, logo_light_url, favicon_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        return $stmt->execute([
            $data['client_id'] ?? null,
            $data['font_primary_id'] ?? null,
            $data['font_secondary_id'] ?? null,
            $data['font_primary'] ?? null,
            $data['font_primary_url'] ?? null,
            $data['font_secondary'] ?? null,
            $data['font_secondary_url'] ?? null,
            $typographyScale,
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
            $buttonStyles,
            $colorSystem,
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
            'font_primary_id', 'font_secondary_id',
            'font_primary', 'font_primary_url', 'font_secondary', 'font_secondary_url',
            'typography_scale', 'button_styles', 'color_system',
            'color_primary', 'color_secondary', 'color_accent',
            'color_text', 'color_text_light',
            'color_background', 'color_surface',
            'color_button', 'color_button_text',
            'border_radius', 'shadow_intensity',
            'logo_url', 'logo_light_url', 'favicon_url'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                // Encoder les JSON si nécessaire
                if (in_array($field, ['typography_scale', 'button_styles', 'color_system']) && is_array($value)) {
                    $value = json_encode($value);
                }

                $fields[] = "$field = ?";
                $values[] = $value;
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
    // MÉTHODES D'ACCÈS AUX DONNÉES JSON
    // =========================================

    /**
     * Récupère l'échelle typographique (décodée)
     *
     * @param int|null $clientId
     * @return array Typography scale (h1, h2, h3, etc.)
     */
    public function getTypographyScale(?int $clientId = null): array
    {
        $config = $this->resolveBranding($clientId);
        return $config['typography_scale'] ?? [];
    }

    /**
     * Récupère les styles de boutons (décodés)
     *
     * @param int|null $clientId
     * @return array Button styles (primary, secondary, danger, success)
     */
    public function getButtonStyles(?int $clientId = null): array
    {
        $config = $this->resolveBranding($clientId);
        return $config['button_styles'] ?? [];
    }

    /**
     * Récupère le système de couleurs (décodé)
     *
     * @param int|null $clientId
     * @return array Color system (variants, hover, disabled, etc.)
     */
    public function getColorSystem(?int $clientId = null): array
    {
        $config = $this->resolveBranding($clientId);
        return $config['color_system'] ?? [];
    }

    /**
     * Récupère la police principale (objet Font)
     *
     * @param int|null $clientId
     * @return array|null Font data or null
     */
    public function getFontPrimary(?int $clientId = null): ?array
    {
        $config = $this->resolveBranding($clientId);
        $fontId = $config['font_primary_id'] ?? null;

        if (!$fontId) {
            return null;
        }

        require_once __DIR__ . '/Font.php';
        $fontModel = new Font();
        return $fontModel->findById($fontId);
    }

    /**
     * Récupère la police secondaire (objet Font)
     *
     * @param int|null $clientId
     * @return array|null Font data or null
     */
    public function getFontSecondary(?int $clientId = null): ?array
    {
        $config = $this->resolveBranding($clientId);
        $fontId = $config['font_secondary_id'] ?? null;

        if (!$fontId) {
            return null;
        }

        require_once __DIR__ . '/Font.php';
        $fontModel = new Font();
        return $fontModel->findById($fontId);
    }

    /**
     * Récupère la police tertiaire (paragraphes)
     */
    public function getFontTertiary(?int $clientId = null): ?array
    {
        $config = $this->resolveBranding($clientId);
        $fontId = $config['font_tertiary_id'] ?? null;

        if (!$fontId) {
            return null;
        }

        require_once __DIR__ . '/Font.php';
        $fontModel = new Font();
        return $fontModel->findById($fontId);
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
