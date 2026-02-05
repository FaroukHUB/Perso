-- =============================================
-- PERSONNALY - Migration : Simplification Typographie
-- Ajouter police tertiaire + simplifier le système
-- =============================================
-- Date: 2026-02-05
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Ajouter la police tertiaire (pour les paragraphes)
ALTER TABLE `branding_settings`
ADD COLUMN `font_tertiary_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID police tertiaire (paragraphes)' AFTER `font_secondary_id`;

-- Créer la contrainte FK
ALTER TABLE `branding_settings`
ADD CONSTRAINT `fk_branding_font_tertiary` FOREIGN KEY (`font_tertiary_id`)
    REFERENCES `fonts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Lier une police par défaut pour tertiary (Inter ou Open Sans)
UPDATE `branding_settings`
SET `font_tertiary_id` = (SELECT id FROM fonts WHERE family = 'Inter' LIMIT 1)
WHERE `client_id` IS NULL AND `font_tertiary_id` IS NULL;

-- Simplifier typography_scale (garder que H1, H2, Paragraphe)
UPDATE `branding_settings`
SET `typography_scale` = JSON_OBJECT(
    'h1', JSON_OBJECT(
        'font', 'primary',
        'weight', '700'
    ),
    'h2', JSON_OBJECT(
        'font', 'primary',
        'weight', '600'
    ),
    'paragraph', JSON_OBJECT(
        'font', 'tertiary',
        'weight', '400'
    )
)
WHERE `client_id` IS NULL;

-- Simplifier button_styles (juste bg_color)
UPDATE `branding_settings`
SET `button_styles` = JSON_OBJECT(
    'primary', JSON_OBJECT('bg_color', '#6366F1'),
    'secondary', JSON_OBJECT('bg_color', '#8B5CF6'),
    'danger', JSON_OBJECT('bg_color', '#EF4444'),
    'success', JSON_OBJECT('bg_color', '#10B981'),
    'outline', JSON_OBJECT('bg_color', 'transparent')
)
WHERE `client_id` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- Vérification
SELECT
    id,
    client_id,
    font_primary_id,
    font_secondary_id,
    font_tertiary_id,
    JSON_PRETTY(typography_scale) as typo_simplified,
    JSON_PRETTY(button_styles) as buttons_simplified
FROM branding_settings
WHERE client_id IS NULL;
