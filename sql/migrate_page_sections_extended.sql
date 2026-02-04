-- =============================================
-- PERSONNALY - Migration : Extension Page Sections
-- Nouvelles tables pour FAQ, Témoignages, Galerie
-- =============================================
-- À exécuter dans phpMyAdmin sur o2switch
-- Date: 2026-02-04
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- Table: section_faq_items
-- Questions/Réponses pour sections FAQ
-- =============================================
CREATE TABLE IF NOT EXISTS `section_faq_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL COMMENT 'Section page_sections parente',
    `question` VARCHAR(500) NOT NULL COMMENT 'Question',
    `answer` TEXT NOT NULL COMMENT 'Réponse (peut contenir du HTML)',
    `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Icône optionnelle',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_section_order` (`section_id`, `sort_order`),
    CONSTRAINT `fk_faq_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: section_testimonials
-- Témoignages clients pour sections Testimonials
-- =============================================
CREATE TABLE IF NOT EXISTS `section_testimonials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL COMMENT 'Section page_sections parente',
    `author_name` VARCHAR(100) NOT NULL COMMENT 'Nom du client',
    `author_title` VARCHAR(100) DEFAULT NULL COMMENT 'Titre/fonction (ex: "Cliente depuis 2023")',
    `author_photo` VARCHAR(500) DEFAULT NULL COMMENT 'URL photo du client',
    `content` TEXT NOT NULL COMMENT 'Texte du témoignage',
    `rating` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Note sur 5 (optionnel)',
    `company` VARCHAR(100) DEFAULT NULL COMMENT 'Entreprise (optionnel)',
    `source` VARCHAR(50) DEFAULT NULL COMMENT 'Source (google, trustpilot, direct...)',
    `source_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL du témoignage original',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_section_order` (`section_id`, `sort_order`),
    KEY `idx_rating` (`rating`),
    CONSTRAINT `fk_testimonial_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: section_gallery_images
-- Images pour sections Galerie
-- =============================================
CREATE TABLE IF NOT EXISTS `section_gallery_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL COMMENT 'Section page_sections parente',
    `image_url` VARCHAR(500) NOT NULL COMMENT 'URL de l''image',
    `thumbnail_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL miniature (optionnel)',
    `alt_text` VARCHAR(255) DEFAULT NULL COMMENT 'Texte alternatif',
    `caption` VARCHAR(500) DEFAULT NULL COMMENT 'Légende',
    `link_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL de destination au clic',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_section_order` (`section_id`, `sort_order`),
    CONSTRAINT `fk_gallery_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: contact_form_submissions
-- Soumissions du formulaire de contact
-- =============================================
CREATE TABLE IF NOT EXISTS `contact_form_submissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED DEFAULT NULL COMMENT 'Section source (optionnel)',
    `page_slug` VARCHAR(100) DEFAULT NULL COMMENT 'Page source',
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `is_spam` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_is_read` (`is_read`),
    KEY `idx_created` (`created_at`),
    KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- FIN DE LA MIGRATION
-- =============================================
