-- =============================================
-- PERSONNALY - Migration P5.8 : Section Newsletter
-- =============================================
-- À exécuter dans phpMyAdmin sur o2switch
-- Date: 2026-01-17
-- =============================================

SET NAMES utf8mb4;

-- =============================================
-- 1. Ajouter le type 'newsletter' à l'ENUM
-- =============================================
ALTER TABLE `homepage_sections`
MODIFY COLUMN `type` ENUM('hero', 'featured_products', 'featured_packs', 'content_block', 'blog_slider', 'newsletter') NOT NULL COMMENT 'Type de section';

-- =============================================
-- 2. Table des abonnés newsletter
-- =============================================
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `source` VARCHAR(100) NOT NULL DEFAULT 'homepage' COMMENT 'Origine inscription',
    `status` ENUM('active', 'unsubscribed') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FIN DE LA MIGRATION
-- =============================================
