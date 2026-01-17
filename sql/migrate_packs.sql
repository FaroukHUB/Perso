-- =============================================
-- PERSONNALY - Migration Packs / Idées
-- Système de suggestions / préconfigurations
-- =============================================
-- À exécuter dans phpMyAdmin sur o2switch
-- Date: 2026-01-17
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------
-- Table: packs
-- Packs / Idées de personnalisation
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `packs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT 'Nom du pack (ex: Idée Cadeau Élégante)',
    `slug` VARCHAR(255) NOT NULL COMMENT 'Slug URL-friendly',
    `description` TEXT COMMENT 'Description courte pour affichage client',
    `type` ENUM('technique', 'contextuel', 'thematique', 'inspiration') NOT NULL DEFAULT 'inspiration' COMMENT 'Type de pack',
    `cover_image_url` VARCHAR(500) DEFAULT NULL COMMENT 'Image de couverture',
    `preset_json` JSON NOT NULL COMMENT 'Préconfiguration design (texte, police, couleur, technique, position...)',
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'draft' COMMENT 'Brouillon ou actif',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_status` (`status`),
    KEY `idx_type` (`type`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Table: pack_products
-- Liaison packs <-> produits (N:N)
-- Un pack peut contenir plusieurs produits
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `pack_products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pack_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre dans le pack',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pack_product` (`pack_id`, `product_id`),
    KEY `idx_pack` (`pack_id`),
    KEY `idx_product` (`product_id`),
    CONSTRAINT `fk_pack_products_pack` FOREIGN KEY (`pack_id`) REFERENCES `packs` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pack_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- STRUCTURE DU PRESET JSON
-- =============================================
-- Le champ preset_json contient la préconfiguration :
-- {
--   "text": "Texte par défaut",       // optionnel
--   "font": "Poppins",                 // optionnel
--   "text_color": "#FF1493",           // optionnel
--   "technique": "flex",               // optionnel
--   "position": {                      // optionnel
--     "x": 50,
--     "y": 50
--   },
--   "view": "front",                   // optionnel (front/back)
--   "text_size": "medium"              // optionnel
-- }
--
-- Tout ce qui n'est pas défini reste LIBRE pour le client.
-- =============================================

-- =============================================
-- DONNÉES DE DÉMONSTRATION (optionnel)
-- =============================================
-- Exemple de pack
INSERT INTO `packs` (`name`, `slug`, `description`, `type`, `preset_json`, `status`, `sort_order`) VALUES
('Cadeau Personnalisé Élégant', 'cadeau-elegant', 'Une idée cadeau raffinée avec une police élégante', 'contextuel', '{"text": "Pour toi", "font": "Playfair Display", "text_color": "#D4AF37", "technique": "broderie"}', 'active', 1),
('Team Family', 'team-family', 'Design moderne pour toute la famille', 'thematique', '{"text": "Team", "font": "Poppins", "text_color": "#FF1493", "technique": "flex"}', 'active', 2);

-- =============================================
-- FIN DE LA MIGRATION
-- =============================================
