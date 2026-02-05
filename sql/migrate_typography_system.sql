-- =============================================
-- PERSONNALY - Migration : Système de Typographie Centralisé
-- Extension du branding pour polices et couleurs complètes
-- =============================================
-- A exécuter dans phpMyAdmin sur o2switch
-- Date: 2026-02-05
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- ÉTAPE 1 : Ajouter les nouvelles colonnes
-- =============================================

-- Relations vers table fonts (remplace les anciens champs font_primary/font_secondary)
ALTER TABLE `branding_settings`
ADD COLUMN `font_primary_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID police principale (FK fonts)' AFTER `client_id`,
ADD COLUMN `font_secondary_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID police secondaire (FK fonts)' AFTER `font_primary_id`;

-- Systèmes JSON pour configuration avancée
ALTER TABLE `branding_settings`
ADD COLUMN `typography_scale` JSON DEFAULT NULL COMMENT 'Échelle typographique complète (H1-H6, body, small)' AFTER `font_secondary_url`,
ADD COLUMN `button_styles` JSON DEFAULT NULL COMMENT 'Styles des boutons (primary, secondary, danger, success)' AFTER `shadow_intensity`,
ADD COLUMN `color_system` JSON DEFAULT NULL COMMENT 'Système de couleurs avancé (variants, hover, disabled)' AFTER `button_styles`;

-- Créer les contraintes de clés étrangères
ALTER TABLE `branding_settings`
ADD CONSTRAINT `fk_branding_font_primary` FOREIGN KEY (`font_primary_id`)
    REFERENCES `fonts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT `fk_branding_font_secondary` FOREIGN KEY (`font_secondary_id`)
    REFERENCES `fonts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- =============================================
-- ÉTAPE 2 : Mettre à jour la configuration globale existante
-- =============================================

-- Lier les fonts existantes par leur ID (Poppins = 1, Inter par défaut ou Playfair Display = 2)
UPDATE `branding_settings`
SET
    `font_primary_id` = (SELECT id FROM fonts WHERE family = 'Poppins' LIMIT 1),
    `font_secondary_id` = (SELECT id FROM fonts WHERE family = 'Playfair Display' LIMIT 1)
WHERE `client_id` IS NULL;

-- Insérer l'échelle typographique par défaut
UPDATE `branding_settings`
SET `typography_scale` = JSON_OBJECT(
    'h1', JSON_OBJECT(
        'font', 'primary',
        'size', '3rem',
        'weight', '700',
        'line_height', '1.2'
    ),
    'h2', JSON_OBJECT(
        'font', 'primary',
        'size', '2.5rem',
        'weight', '600',
        'line_height', '1.3'
    ),
    'h3', JSON_OBJECT(
        'font', 'primary',
        'size', '2rem',
        'weight', '600',
        'line_height', '1.4'
    ),
    'h4', JSON_OBJECT(
        'font', 'primary',
        'size', '1.5rem',
        'weight', '500',
        'line_height', '1.4'
    ),
    'h5', JSON_OBJECT(
        'font', 'secondary',
        'size', '1.25rem',
        'weight', '500',
        'line_height', '1.5'
    ),
    'h6', JSON_OBJECT(
        'font', 'secondary',
        'size', '1rem',
        'weight', '500',
        'line_height', '1.5'
    ),
    'body', JSON_OBJECT(
        'font', 'secondary',
        'size', '1rem',
        'weight', '400',
        'line_height', '1.6'
    ),
    'small', JSON_OBJECT(
        'font', 'secondary',
        'size', '0.875rem',
        'weight', '400',
        'line_height', '1.5'
    ),
    'lead', JSON_OBJECT(
        'font', 'secondary',
        'size', '1.125rem',
        'weight', '400',
        'line_height', '1.7'
    )
)
WHERE `client_id` IS NULL;

-- Insérer les styles de boutons par défaut
UPDATE `branding_settings`
SET `button_styles` = JSON_OBJECT(
    'primary', JSON_OBJECT(
        'bg_color', '#6366F1',
        'text_color', '#FFFFFF',
        'hover_bg', '#4F46E5',
        'hover_text', '#FFFFFF',
        'border_color', 'transparent',
        'border_width', '0px'
    ),
    'secondary', JSON_OBJECT(
        'bg_color', '#E5E7EB',
        'text_color', '#1F2937',
        'hover_bg', '#D1D5DB',
        'hover_text', '#111827',
        'border_color', 'transparent',
        'border_width', '0px'
    ),
    'danger', JSON_OBJECT(
        'bg_color', '#EF4444',
        'text_color', '#FFFFFF',
        'hover_bg', '#DC2626',
        'hover_text', '#FFFFFF',
        'border_color', 'transparent',
        'border_width', '0px'
    ),
    'success', JSON_OBJECT(
        'bg_color', '#10B981',
        'text_color', '#FFFFFF',
        'hover_bg', '#059669',
        'hover_text', '#FFFFFF',
        'border_color', 'transparent',
        'border_width', '0px'
    ),
    'outline', JSON_OBJECT(
        'bg_color', 'transparent',
        'text_color', '#6366F1',
        'hover_bg', '#6366F1',
        'hover_text', '#FFFFFF',
        'border_color', '#6366F1',
        'border_width', '2px'
    )
)
WHERE `client_id` IS NULL;

-- Insérer le système de couleurs avancé
UPDATE `branding_settings`
SET `color_system` = JSON_OBJECT(
    'primary', JSON_OBJECT(
        'base', '#6366F1',
        'hover', '#4F46E5',
        'active', '#4338CA',
        'disabled', '#A5B4FC',
        'text_on', '#FFFFFF'
    ),
    'secondary', JSON_OBJECT(
        'base', '#8B5CF6',
        'hover', '#7C3AED',
        'active', '#6D28D9',
        'disabled', '#C4B5FD',
        'text_on', '#FFFFFF'
    ),
    'accent', JSON_OBJECT(
        'base', '#F59E0B',
        'hover', '#D97706',
        'active', '#B45309',
        'disabled', '#FCD34D',
        'text_on', '#FFFFFF'
    ),
    'text', JSON_OBJECT(
        'primary', '#1F2937',
        'secondary', '#6B7280',
        'tertiary', '#9CA3AF',
        'disabled', '#D1D5DB',
        'on_dark', '#FFFFFF'
    ),
    'background', JSON_OBJECT(
        'primary', '#FFFFFF',
        'secondary', '#F9FAFB',
        'tertiary', '#F3F4F6',
        'inverse', '#1F2937'
    ),
    'border', JSON_OBJECT(
        'primary', '#E5E7EB',
        'secondary', '#D1D5DB',
        'focus', '#6366F1'
    ),
    'status', JSON_OBJECT(
        'success', '#10B981',
        'success_bg', '#D1FAE5',
        'warning', '#F59E0B',
        'warning_bg', '#FEF3C7',
        'error', '#EF4444',
        'error_bg', '#FEE2E2',
        'info', '#3B82F6',
        'info_bg', '#DBEAFE'
    )
)
WHERE `client_id` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- VÉRIFICATION : Afficher la config créée
-- =============================================
SELECT
    id,
    client_id,
    font_primary_id,
    font_secondary_id,
    JSON_PRETTY(typography_scale) as typography,
    JSON_PRETTY(button_styles) as buttons,
    created_at
FROM branding_settings
WHERE client_id IS NULL;

-- =============================================
-- FIN DE LA MIGRATION
-- Notes:
-- - Les anciennes colonnes font_primary/font_primary_url sont conservées pour rétrocompatibilité
-- - Peuvent être supprimées après validation complète du système
-- - La DB est désormais la seule source de vérité (plus de constantes PHP)
-- =============================================
