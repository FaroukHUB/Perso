-- =============================================
-- PERSONNALY - Migration COMPLETE Page Builder
-- Toutes les tables pour le Page Builder avancé
-- =============================================
-- À exécuter dans phpMyAdmin sur o2switch
-- Date: 2026-02-04
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- TABLE: pages
-- Pages personnalisées créées par l'admin
-- =============================================
CREATE TABLE IF NOT EXISTS `pages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL COMMENT 'Titre de la page',
    `slug` VARCHAR(255) NOT NULL COMMENT 'URL de la page (ex: a-propos)',
    `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'Titre SEO',
    `meta_description` TEXT DEFAULT NULL COMMENT 'Description SEO',
    `status` ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = page système non supprimable',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: page_sections
-- Sections des pages (tous les types)
-- =============================================
DROP TABLE IF EXISTS `page_sections`;
CREATE TABLE `page_sections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id` INT UNSIGNED NOT NULL COMMENT 'Page parente',
    `type` ENUM(
        'hero',
        'featured_products',
        'featured_packs',
        'featured_category',
        'content_block',
        'blog_slider',
        'newsletter',
        'text_only',
        'image_gallery',
        'video',
        'faq',
        'testimonials',
        'contact_form',
        'counter',
        'timeline',
        'logos',
        'google_map',
        'google_reviews',
        'separator',
        'html_custom'
    ) NOT NULL COMMENT 'Type de section',
    `title` VARCHAR(255) DEFAULT NULL,
    `subtitle` VARCHAR(500) DEFAULT NULL,
    `content` TEXT DEFAULT NULL,
    `cta_text` VARCHAR(100) DEFAULT NULL,
    `cta_url` VARCHAR(500) DEFAULT NULL,
    `media_type` ENUM('none', 'image', 'video') NOT NULL DEFAULT 'none',
    `media_url` VARCHAR(500) DEFAULT NULL,
    `config_json` JSON DEFAULT NULL COMMENT 'Configuration additionnelle',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'draft',
    `is_template` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = section réutilisable',
    `template_name` VARCHAR(100) DEFAULT NULL COMMENT 'Nom si template',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_page_order` (`page_id`, `sort_order`),
    KEY `idx_status` (`status`),
    KEY `idx_template` (`is_template`),
    CONSTRAINT `fk_page_sections_page` FOREIGN KEY (`page_id`)
        REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: page_section_items
-- Éléments liés (produits, packs, articles)
-- =============================================
CREATE TABLE IF NOT EXISTS `page_section_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('product', 'pack', 'blog') NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_section_item` (`section_id`, `item_type`, `item_id`),
    KEY `idx_section` (`section_id`),
    CONSTRAINT `fk_page_section_items_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: section_faq_items
-- Questions/Réponses FAQ
-- =============================================
CREATE TABLE IF NOT EXISTS `section_faq_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL,
    `question` VARCHAR(500) NOT NULL,
    `answer` TEXT NOT NULL,
    `icon` VARCHAR(50) DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_section_order` (`section_id`, `sort_order`),
    CONSTRAINT `fk_faq_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: section_testimonials
-- Témoignages clients
-- =============================================
CREATE TABLE IF NOT EXISTS `section_testimonials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL,
    `author_name` VARCHAR(100) NOT NULL,
    `author_title` VARCHAR(100) DEFAULT NULL,
    `author_photo` VARCHAR(500) DEFAULT NULL,
    `content` TEXT NOT NULL,
    `rating` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Note sur 5',
    `company` VARCHAR(100) DEFAULT NULL,
    `source` VARCHAR(50) DEFAULT NULL COMMENT 'google, trustpilot, direct...',
    `source_url` VARCHAR(500) DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_section_order` (`section_id`, `sort_order`),
    CONSTRAINT `fk_testimonial_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: section_gallery_images
-- Images de galerie
-- =============================================
CREATE TABLE IF NOT EXISTS `section_gallery_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL,
    `image_url` VARCHAR(500) NOT NULL,
    `thumbnail_url` VARCHAR(500) DEFAULT NULL,
    `alt_text` VARCHAR(255) DEFAULT NULL,
    `caption` VARCHAR(500) DEFAULT NULL,
    `link_url` VARCHAR(500) DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_section_order` (`section_id`, `sort_order`),
    CONSTRAINT `fk_gallery_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: section_counters
-- Compteurs animés (chiffres clés)
-- =============================================
CREATE TABLE IF NOT EXISTS `section_counters` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL,
    `value` INT NOT NULL COMMENT 'Valeur numérique',
    `suffix` VARCHAR(20) DEFAULT NULL COMMENT 'Ex: +, %, €, k',
    `prefix` VARCHAR(20) DEFAULT NULL COMMENT 'Ex: €, $',
    `label` VARCHAR(100) NOT NULL COMMENT 'Ex: Clients satisfaits',
    `icon` VARCHAR(100) DEFAULT NULL COMMENT 'Classe icône ou SVG',
    `color` VARCHAR(20) DEFAULT NULL COMMENT 'Couleur accent',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_section_order` (`section_id`, `sort_order`),
    CONSTRAINT `fk_counter_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: section_timeline_steps
-- Étapes timeline/processus
-- =============================================
CREATE TABLE IF NOT EXISTS `section_timeline_steps` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL,
    `step_number` INT UNSIGNED DEFAULT NULL COMMENT 'Numéro étape (optionnel)',
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `icon` VARCHAR(100) DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `date` VARCHAR(50) DEFAULT NULL COMMENT 'Date ou période (optionnel)',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_section_order` (`section_id`, `sort_order`),
    CONSTRAINT `fk_timeline_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: section_logos
-- Logos partenaires/clients
-- =============================================
CREATE TABLE IF NOT EXISTS `section_logos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nom partenaire',
    `logo_url` VARCHAR(500) NOT NULL,
    `website_url` VARCHAR(500) DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_section_order` (`section_id`, `sort_order`),
    CONSTRAINT `fk_logo_section` FOREIGN KEY (`section_id`)
        REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: google_reviews_cache
-- Cache des avis Google (API Google Places)
-- =============================================
CREATE TABLE IF NOT EXISTS `google_reviews_cache` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `place_id` VARCHAR(100) NOT NULL COMMENT 'Google Place ID',
    `author_name` VARCHAR(100) NOT NULL,
    `author_photo_url` VARCHAR(500) DEFAULT NULL,
    `rating` TINYINT UNSIGNED NOT NULL,
    `text` TEXT DEFAULT NULL,
    `time` DATETIME NOT NULL COMMENT 'Date avis original',
    `language` VARCHAR(10) DEFAULT 'fr',
    `fetched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_place` (`place_id`),
    KEY `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: promo_banners
-- Bannières promotionnelles (top bar)
-- =============================================
CREATE TABLE IF NOT EXISTS `promo_banners` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message` VARCHAR(500) NOT NULL,
    `link_url` VARCHAR(500) DEFAULT NULL,
    `link_text` VARCHAR(100) DEFAULT NULL,
    `background_color` VARCHAR(20) DEFAULT '#FF69B4',
    `text_color` VARCHAR(20) DEFAULT '#FFFFFF',
    `icon` VARCHAR(100) DEFAULT NULL,
    `start_date` DATETIME DEFAULT NULL COMMENT 'Date début affichage',
    `end_date` DATETIME DEFAULT NULL COMMENT 'Date fin affichage',
    `pages` JSON DEFAULT NULL COMMENT '["all"] ou ["home", "products"]',
    `is_dismissible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Peut être fermée',
    `priority` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre si plusieurs actives',
    `status` ENUM('draft', 'active', 'scheduled') NOT NULL DEFAULT 'draft',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_dates` (`status`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: popups
-- Popups promotionnels
-- =============================================
CREATE TABLE IF NOT EXISTS `popups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nom interne',
    `title` VARCHAR(255) DEFAULT NULL,
    `content` TEXT DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `cta_text` VARCHAR(100) DEFAULT NULL,
    `cta_url` VARCHAR(500) DEFAULT NULL,
    `background_color` VARCHAR(20) DEFAULT '#FFFFFF',
    `text_color` VARCHAR(20) DEFAULT '#1A1A1A',
    `overlay_color` VARCHAR(30) DEFAULT 'rgba(0,0,0,0.5)',
    `width` VARCHAR(20) DEFAULT '500px',
    `trigger_type` ENUM('delay', 'scroll', 'exit_intent', 'click') NOT NULL DEFAULT 'delay',
    `trigger_value` VARCHAR(50) DEFAULT '3000' COMMENT 'ms pour delay, % pour scroll',
    `trigger_selector` VARCHAR(100) DEFAULT NULL COMMENT 'Sélecteur CSS si click',
    `show_once` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Afficher 1 fois par session',
    `show_on_mobile` TINYINT(1) NOT NULL DEFAULT 1,
    `pages` JSON DEFAULT NULL COMMENT '["all"] ou ["home", "products"]',
    `start_date` DATETIME DEFAULT NULL,
    `end_date` DATETIME DEFAULT NULL,
    `status` ENUM('draft', 'active', 'scheduled') NOT NULL DEFAULT 'draft',
    `views` INT UNSIGNED NOT NULL DEFAULT 0,
    `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: contact_form_submissions
-- Soumissions formulaire contact
-- =============================================
CREATE TABLE IF NOT EXISTS `contact_form_submissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` INT UNSIGNED DEFAULT NULL,
    `page_slug` VARCHAR(100) DEFAULT NULL,
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
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: menus
-- Menus de navigation
-- =============================================
CREATE TABLE IF NOT EXISTS `menus` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(50) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: menu_items
-- Éléments de menu
-- =============================================
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `menu_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `label` VARCHAR(100) NOT NULL,
    `url` VARCHAR(500) DEFAULT NULL,
    `page_id` INT UNSIGNED DEFAULT NULL,
    `link_type` ENUM('custom', 'page', 'category', 'home', 'products', 'packs', 'blog', 'contact') NOT NULL DEFAULT 'custom',
    `link_target` VARCHAR(100) DEFAULT NULL,
    `open_new_tab` TINYINT(1) NOT NULL DEFAULT 0,
    `css_class` VARCHAR(100) DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_menu_order` (`menu_id`, `sort_order`),
    KEY `idx_parent` (`parent_id`),
    CONSTRAINT `fk_menu_items_menu` FOREIGN KEY (`menu_id`)
        REFERENCES `menus` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_menu_items_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: section_templates
-- Templates de sections réutilisables
-- =============================================
CREATE TABLE IF NOT EXISTS `section_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `category` VARCHAR(50) DEFAULT NULL COMMENT 'hero, content, commerce...',
    `thumbnail_url` VARCHAR(500) DEFAULT NULL,
    `section_data` JSON NOT NULL COMMENT 'Données complètes de la section',
    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_global` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = visible par tous',
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_global` (`is_global`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: page_history
-- Historique des modifications (undo/redo)
-- =============================================
CREATE TABLE IF NOT EXISTS `page_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` ENUM('create', 'update', 'delete', 'reorder') NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'page, section, faq_item...',
    `entity_id` INT UNSIGNED NOT NULL,
    `data_before` JSON DEFAULT NULL,
    `data_after` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_page_date` (`page_id`, `created_at`),
    CONSTRAINT `fk_history_page` FOREIGN KEY (`page_id`)
        REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: media_library
-- Médiathèque centrale
-- =============================================
CREATE TABLE IF NOT EXISTS `media_library` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL COMMENT 'Taille en bytes',
    `width` INT UNSIGNED DEFAULT NULL COMMENT 'Largeur image',
    `height` INT UNSIGNED DEFAULT NULL COMMENT 'Hauteur image',
    `url` VARCHAR(500) NOT NULL,
    `thumbnail_url` VARCHAR(500) DEFAULT NULL,
    `alt_text` VARCHAR(255) DEFAULT NULL,
    `folder` VARCHAR(100) DEFAULT 'general',
    `tags` JSON DEFAULT NULL COMMENT '["produit", "hero"]',
    `uploaded_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_folder` (`folder`),
    KEY `idx_mime` (`mime_type`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: site_settings (si n'existe pas)
-- Paramètres globaux du site
-- =============================================
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('text', 'textarea', 'number', 'boolean', 'json', 'image', 'color') NOT NULL DEFAULT 'text',
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `label` VARCHAR(100) DEFAULT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`setting_key`),
    KEY `idx_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- DONNÉES INITIALES
-- =============================================

-- Page d'accueil système
INSERT INTO `pages` (`title`, `slug`, `meta_title`, `meta_description`, `status`, `is_system`) VALUES
('Accueil', 'home', 'PERSONNALY - Personnalisation textile', 'Créez des vêtements uniques personnalisés', 'published', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Menus par défaut
INSERT INTO `menus` (`name`, `location`) VALUES
('Menu Principal', 'header_main'),
('Menu Footer', 'footer_main'),
('Liens Légaux', 'footer_legal')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Settings Google Maps et Reviews
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `label`, `description`) VALUES
('google_maps_api_key', '', 'text', 'integrations', 'Clé API Google Maps', 'Clé API pour afficher les cartes et avis Google'),
('google_place_id', '', 'text', 'integrations', 'Google Place ID', 'ID de votre établissement Google Business'),
('google_reviews_cache_hours', '24', 'number', 'integrations', 'Cache avis Google (heures)', 'Durée du cache des avis Google')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- =============================================
-- FIN DE LA MIGRATION
-- =============================================
