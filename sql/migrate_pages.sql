-- =============================================
-- PERSONNALY - Migration : Pages personnalisées & Menus
-- Extension du Page Builder pour créer des pages dynamiques
-- =============================================
-- À exécuter dans phpMyAdmin sur o2switch
-- Date: 2026-02-03
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- Table: pages
-- Pages personnalisées créées par l'admin
-- =============================================
CREATE TABLE IF NOT EXISTS `pages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL COMMENT 'Titre de la page',
    `slug` VARCHAR(255) NOT NULL COMMENT 'URL de la page (ex: a-propos)',
    `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'Titre SEO',
    `meta_description` TEXT DEFAULT NULL COMMENT 'Description SEO',
    `status` ENUM('draft', 'published') NOT NULL DEFAULT 'draft' COMMENT 'Statut publication',
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = page système non supprimable (homepage)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: page_sections
-- Sections des pages personnalisées
-- Structure identique à homepage_sections pour réutiliser les composants
-- =============================================
CREATE TABLE IF NOT EXISTS `page_sections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id` INT UNSIGNED NOT NULL COMMENT 'Page parente',
    `type` ENUM('hero', 'featured_products', 'featured_packs', 'featured_category', 'content_block', 'blog_slider', 'newsletter', 'text_only', 'image_gallery', 'video', 'faq', 'testimonials', 'contact_form') NOT NULL COMMENT 'Type de section',
    `title` VARCHAR(255) DEFAULT NULL COMMENT 'Titre de la section',
    `subtitle` VARCHAR(500) DEFAULT NULL COMMENT 'Sous-titre ou description courte',
    `content` TEXT DEFAULT NULL COMMENT 'Contenu texte',
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
    KEY `idx_page_order` (`page_id`, `sort_order`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_page_sections_page` FOREIGN KEY (`page_id`)
        REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: page_section_items
-- Éléments liés à une section de page (produits, packs, etc.)
-- =============================================
CREATE TABLE IF NOT EXISTS `page_section_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('product', 'pack', 'blog') NOT NULL COMMENT 'Type élément',
    `item_id` INT UNSIGNED NOT NULL COMMENT 'ID du produit, pack ou article',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre dans la section',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_section_item` (`section_id`, `item_type`, `item_id`),
    KEY `idx_section` (`section_id`),
    CONSTRAINT `fk_page_section_items_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: menus
-- Menus de navigation du site
-- =============================================
CREATE TABLE IF NOT EXISTS `menus` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nom du menu (header, footer, etc.)',
    `location` VARCHAR(50) NOT NULL COMMENT 'Emplacement (header_main, footer_links, etc.)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: menu_items
-- Éléments de menu
-- =============================================
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `menu_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL COMMENT 'Parent pour sous-menus',
    `label` VARCHAR(100) NOT NULL COMMENT 'Texte affiché',
    `url` VARCHAR(500) DEFAULT NULL COMMENT 'URL personnalisée',
    `page_id` INT UNSIGNED DEFAULT NULL COMMENT 'Lien vers une page interne',
    `link_type` ENUM('custom', 'page', 'category', 'home', 'products', 'packs', 'blog', 'contact') NOT NULL DEFAULT 'custom' COMMENT 'Type de lien',
    `link_target` VARCHAR(100) DEFAULT NULL COMMENT 'ID ou slug selon link_type',
    `open_new_tab` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ouvrir dans nouvel onglet',
    `css_class` VARCHAR(100) DEFAULT NULL COMMENT 'Classes CSS personnalisées',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre dans le menu',
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active' COMMENT 'Statut',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_menu_order` (`menu_id`, `sort_order`),
    KEY `idx_parent` (`parent_id`),
    CONSTRAINT `fk_menu_items_menu` FOREIGN KEY (`menu_id`)
        REFERENCES `menus` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_menu_items_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_menu_items_page` FOREIGN KEY (`page_id`)
        REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- DONNÉES INITIALES
-- =============================================

-- Page d'accueil système (non supprimable)
INSERT INTO `pages` (`title`, `slug`, `meta_title`, `meta_description`, `status`, `is_system`) VALUES
('Accueil', 'home', 'PERSONNALY - Personnalisation textile', 'Créez des vêtements uniques personnalisés pour toute la famille', 'published', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Menus par défaut
INSERT INTO `menus` (`name`, `location`) VALUES
('Menu Principal', 'header_main'),
('Menu Footer', 'footer_main'),
('Liens Légaux', 'footer_legal')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Éléments de menu par défaut
INSERT INTO `menu_items` (`menu_id`, `label`, `link_type`, `url`, `sort_order`, `status`)
SELECT m.id, 'Accueil', 'home', '/', 0, 'active'
FROM `menus` m WHERE m.location = 'header_main'
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT INTO `menu_items` (`menu_id`, `label`, `link_type`, `url`, `sort_order`, `status`)
SELECT m.id, 'Nos Produits', 'products', '/produits', 1, 'active'
FROM `menus` m WHERE m.location = 'header_main'
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT INTO `menu_items` (`menu_id`, `label`, `link_type`, `url`, `sort_order`, `status`)
SELECT m.id, 'Idées Cadeaux', 'packs', '/packs', 2, 'active'
FROM `menus` m WHERE m.location = 'header_main'
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- Liens légaux footer
INSERT INTO `menu_items` (`menu_id`, `label`, `link_type`, `url`, `sort_order`, `status`)
SELECT m.id, 'Mentions légales', 'custom', '/mentions-legales', 0, 'active'
FROM `menus` m WHERE m.location = 'footer_legal'
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT INTO `menu_items` (`menu_id`, `label`, `link_type`, `url`, `sort_order`, `status`)
SELECT m.id, 'CGV', 'custom', '/cgv', 1, 'active'
FROM `menus` m WHERE m.location = 'footer_legal'
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT INTO `menu_items` (`menu_id`, `label`, `link_type`, `url`, `sort_order`, `status`)
SELECT m.id, 'Politique de confidentialité', 'custom', '/politique-confidentialite', 2, 'active'
FROM `menus` m WHERE m.location = 'footer_legal'
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT INTO `menu_items` (`menu_id`, `label`, `link_type`, `url`, `sort_order`, `status`)
SELECT m.id, 'Politique de retour', 'custom', '/politique-retour', 3, 'active'
FROM `menus` m WHERE m.location = 'footer_legal'
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- =============================================
-- FIN DE LA MIGRATION
-- =============================================
