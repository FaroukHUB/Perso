-- =============================================
-- PERSONNALY - Migration : Branding Multi-Client
-- Theming et personnalisation visuelle par client
-- =============================================
-- A executer dans phpMyAdmin sur o2switch
-- Date: 2026-01-24
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- Table: branding_settings
-- Configuration visuelle globale ou par client
-- client_id = NULL => configuration globale (defaut)
-- =============================================
CREATE TABLE IF NOT EXISTS `branding_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = config globale, sinon ID client specifique',

    -- Polices
    `font_primary` VARCHAR(100) DEFAULT NULL COMMENT 'Nom famille police principale (ex: Poppins)',
    `font_primary_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL Google Fonts ou CDN',
    `font_secondary` VARCHAR(100) DEFAULT NULL COMMENT 'Nom famille police secondaire',
    `font_secondary_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL Google Fonts ou CDN',

    -- Couleurs principales
    `color_primary` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur principale hex (#XXXXXX)',
    `color_secondary` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur secondaire hex',
    `color_accent` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur accent hex',

    -- Couleurs texte
    `color_text` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur texte principal',
    `color_text_light` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur texte secondaire/clair',

    -- Couleurs fond
    `color_background` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur fond principal',
    `color_surface` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur surface (cartes, modales)',

    -- Couleurs boutons
    `color_button` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur fond bouton principal',
    `color_button_text` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur texte bouton principal',

    -- Style UI
    `border_radius` ENUM('none', 'small', 'medium', 'large', 'full') NOT NULL DEFAULT 'medium' COMMENT 'Arrondi des coins',
    `shadow_intensity` ENUM('none', 'subtle', 'medium', 'strong') NOT NULL DEFAULT 'subtle' COMMENT 'Intensite ombres',

    -- Assets
    `logo_url` VARCHAR(500) DEFAULT NULL COMMENT 'Logo principal (fond clair)',
    `logo_light_url` VARCHAR(500) DEFAULT NULL COMMENT 'Logo variante (fond sombre)',
    `favicon_url` VARCHAR(500) DEFAULT NULL COMMENT 'Favicon',

    -- Metadata
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_client` (`client_id`),
    CONSTRAINT `fk_branding_client` FOREIGN KEY (`client_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: homepage_section_styles
-- Styles visuels par section de homepage
-- Separe du contenu (homepage_sections existant)
-- =============================================
CREATE TABLE IF NOT EXISTS `homepage_section_styles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = style global, sinon client specifique',
    `section_key` VARCHAR(50) NOT NULL COMMENT 'Cle section: hero, featured_products, testimonials, etc.',

    -- Fond
    `background_color` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur fond hex',
    `background_image` VARCHAR(500) DEFAULT NULL COMMENT 'URL image fond',
    `background_overlay` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur overlay sur image',
    `background_overlay_opacity` DECIMAL(3,2) DEFAULT NULL COMMENT 'Opacite overlay (0.00-1.00)',

    -- Texte
    `text_color_override` VARCHAR(7) DEFAULT NULL COMMENT 'Override couleur texte pour cette section',

    -- Espacement
    `padding_y` ENUM('none', 'small', 'medium', 'large', 'xlarge') NOT NULL DEFAULT 'medium' COMMENT 'Padding vertical',

    -- Affichage
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre affichage',
    `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Section active/visible',

    -- Metadata
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_client_section` (`client_id`, `section_key`),
    KEY `idx_section_key` (`section_key`),
    KEY `idx_active_order` (`active`, `sort_order`),
    CONSTRAINT `fk_section_style_client` FOREIGN KEY (`client_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- DONNEES INITIALES : Configuration globale par defaut
-- =============================================
INSERT INTO `branding_settings` (
    `client_id`,
    `font_primary`, `font_primary_url`,
    `font_secondary`, `font_secondary_url`,
    `color_primary`, `color_secondary`, `color_accent`,
    `color_text`, `color_text_light`,
    `color_background`, `color_surface`,
    `color_button`, `color_button_text`,
    `border_radius`, `shadow_intensity`
) VALUES (
    NULL, -- Config globale
    'Poppins', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
    'Inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap',
    '#6366F1', '#8B5CF6', '#F59E0B',
    '#1F2937', '#6B7280',
    '#FFFFFF', '#F9FAFB',
    '#6366F1', '#FFFFFF',
    'medium', 'subtle'
);

-- =============================================
-- DONNEES INITIALES : Styles sections homepage par defaut
-- =============================================
INSERT INTO `homepage_section_styles` (`client_id`, `section_key`, `background_color`, `padding_y`, `sort_order`, `active`) VALUES
(NULL, 'hero', '#FFFFFF', 'large', 0, 1),
(NULL, 'featured_products', '#F9FAFB', 'large', 1, 1),
(NULL, 'featured_packs', '#FFFFFF', 'large', 2, 1),
(NULL, 'testimonials', '#F3F4F6', 'large', 3, 1),
(NULL, 'blog_slider', '#FFFFFF', 'medium', 4, 1),
(NULL, 'newsletter', '#6366F1', 'medium', 5, 1);

-- =============================================
-- FIN DE LA MIGRATION
-- =============================================
