-- =============================================
-- PERSONNALY - Migration P5 : Page d'accueil dynamique
-- Sections administrables + Blog
-- =============================================
-- À exécuter dans phpMyAdmin sur o2switch
-- Date: 2026-01-17
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- Table: homepage_sections
-- Sections configurables de la page d'accueil
-- =============================================
CREATE TABLE IF NOT EXISTS `homepage_sections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` ENUM('hero', 'featured_products', 'featured_packs', 'content_block', 'blog_slider') NOT NULL COMMENT 'Type de section',
    `title` VARCHAR(255) DEFAULT NULL COMMENT 'Titre de la section',
    `subtitle` VARCHAR(500) DEFAULT NULL COMMENT 'Sous-titre ou description courte',
    `content` TEXT DEFAULT NULL COMMENT 'Contenu texte (pour content_block)',
    `cta_text` VARCHAR(100) DEFAULT NULL COMMENT 'Texte du bouton CTA',
    `cta_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL du CTA',
    `media_type` ENUM('none', 'image', 'video') NOT NULL DEFAULT 'none' COMMENT 'Type de média',
    `media_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL image ou vidéo',
    `config_json` JSON DEFAULT NULL COMMENT 'Configuration additionnelle (couleurs, layout...)',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre affichage',
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'draft' COMMENT 'Statut publication',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_order` (`status`, `sort_order`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: homepage_section_items
-- Éléments liés à une section (produits, packs)
-- =============================================
CREATE TABLE IF NOT EXISTS `homepage_section_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('product', 'pack') NOT NULL COMMENT 'Type élément',
    `item_id` INT UNSIGNED NOT NULL COMMENT 'ID du produit ou pack',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre dans la section',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_section_item` (`section_id`, `item_type`, `item_id`),
    KEY `idx_section` (`section_id`),
    CONSTRAINT `fk_section_items_section` FOREIGN KEY (`section_id`)
        REFERENCES `homepage_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: blog_posts
-- Articles de blog (affichage slider uniquement)
-- =============================================
CREATE TABLE IF NOT EXISTS `blog_posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL COMMENT 'Titre article',
    `slug` VARCHAR(255) NOT NULL COMMENT 'Slug URL',
    `excerpt` TEXT DEFAULT NULL COMMENT 'Résumé court pour slider',
    `content` LONGTEXT DEFAULT NULL COMMENT 'Contenu complet (optionnel MVP)',
    `cover_image_url` VARCHAR(500) DEFAULT NULL COMMENT 'Image de couverture',
    `status` ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME DEFAULT NULL COMMENT 'Date publication',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_status_date` (`status`, `published_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- DONNÉES INITIALES (Section Hero par défaut)
-- =============================================
INSERT INTO `homepage_sections` (`type`, `title`, `subtitle`, `cta_text`, `cta_url`, `sort_order`, `status`) VALUES
('hero', 'Créez des vêtements uniques pour toute la famille', 'Personnalisation textile de qualité. Choisissez votre style, nous faisons le reste.', 'Découvrir nos produits', '#produits', 0, 'active');

-- =============================================
-- FIN DE LA MIGRATION
-- =============================================
