-- =============================================
-- PERSONNALY - MVP P1/P2/P3
-- Tables: fonts, product_print_zones
-- Version: 2026-01-16 v2 (corrigé)
-- Compatible: MySQL 5.6+ / o2switch
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------
-- DROP dans l'ordre correct (FK d'abord)
-- ---------------------------------------------
DROP TABLE IF EXISTS `product_print_zones`;
DROP TABLE IF EXISTS `fonts`;

-- ---------------------------------------------
-- Table: fonts
-- Polices administrables (Google + Custom)
-- ---------------------------------------------
CREATE TABLE `fonts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Identification
    `name` VARCHAR(100) NOT NULL COMMENT 'Nom affiché (ex: Poppins Bold)',
    `family` VARCHAR(100) NOT NULL COMMENT 'CSS font-family (ex: Poppins)',
    `css_key` VARCHAR(50) NOT NULL COMMENT 'Identifiant CSS unique généré (ex: poppins_400_700)',

    -- Source
    `source` ENUM('google', 'custom') NOT NULL DEFAULT 'google',

    -- Google Fonts (source = google)
    `google_weights` VARCHAR(50) DEFAULT '400;700' COMMENT 'Weights séparés par ; (ex: 400;700)',
    `google_import_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL générée par PHP, jamais saisie',

    -- Custom Upload (source = custom)
    `custom_woff2_url` VARCHAR(255) DEFAULT NULL COMMENT 'Chemin fichier .woff2',
    `custom_woff_url` VARCHAR(255) DEFAULT NULL COMMENT 'Chemin fichier .woff (fallback)',

    -- Catégorisation
    `category` ENUM('sans-serif', 'serif', 'script', 'display', 'handwriting') NOT NULL DEFAULT 'sans-serif',

    -- État
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_css_key` (`css_key`),
    KEY `idx_active_sort` (`active`, `sort_order`),
    KEY `idx_category_active` (`category`, `active`),
    KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Table: product_print_zones
-- Zones d'impression par produit
-- ---------------------------------------------
CREATE TABLE `product_print_zones` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,

    -- Zone
    `zone_name` VARCHAR(50) NOT NULL COMMENT 'Identifiant technique (front, back...)',
    `zone_label` VARCHAR(100) NOT NULL COMMENT 'Libellé affiché (Devant, Dos...)',

    -- Position sur image (en % de l'image produit)
    `pos_x` DECIMAL(5,2) NOT NULL DEFAULT 50.00 COMMENT 'Position X centre en %',
    `pos_y` DECIMAL(5,2) NOT NULL DEFAULT 50.00 COMMENT 'Position Y centre en %',
    `width` DECIMAL(5,2) NOT NULL DEFAULT 40.00 COMMENT 'Largeur zone en %',
    `height` DECIMAL(5,2) NOT NULL DEFAULT 20.00 COMMENT 'Hauteur zone en %',

    -- Contraintes texte
    `max_chars` INT UNSIGNED NOT NULL DEFAULT 30,
    `max_lines` TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `default_font_id` INT UNSIGNED DEFAULT NULL,
    `allowed_fonts` TEXT DEFAULT NULL COMMENT 'JSON array des font_id autorisées, NULL = toutes',

    -- État
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_product_active` (`product_id`, `active`),
    KEY `idx_product_sort` (`product_id`, `sort_order`),
    CONSTRAINT `fk_printzone_product` FOREIGN KEY (`product_id`)
        REFERENCES `products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_printzone_default_font` FOREIGN KEY (`default_font_id`)
        REFERENCES `fonts`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------
-- Données initiales: fonts
-- google_import_url = NULL (généré par PHP)
-- ---------------------------------------------
INSERT INTO `fonts` (`name`, `family`, `css_key`, `source`, `google_weights`, `google_import_url`, `category`, `sort_order`) VALUES
('Poppins', 'Poppins', 'poppins_400_600_700', 'google', '400;600;700', NULL, 'sans-serif', 1),
('Playfair Display', 'Playfair Display', 'playfair_display_400_700', 'google', '400;700', NULL, 'serif', 2),
('Lobster', 'Lobster', 'lobster_400', 'google', '400', NULL, 'script', 3),
('Oswald', 'Oswald', 'oswald_400_700', 'google', '400;700', NULL, 'sans-serif', 4),
('Dancing Script', 'Dancing Script', 'dancing_script_400_700', 'google', '400;700', NULL, 'handwriting', 5),
('Bebas Neue', 'Bebas Neue', 'bebas_neue_400', 'google', '400', NULL, 'display', 6);

-- =============================================
-- FIN - Exécuter dans phpMyAdmin o2switch
-- =============================================
