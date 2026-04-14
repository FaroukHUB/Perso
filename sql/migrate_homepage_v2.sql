-- =============================================
-- PERSONNALY - Migration Homepage V2
-- Hero Slider, Trust Badges, How It Works, Testimonials, Featured Products
-- =============================================

SET NAMES utf8mb4;

-- ---------------------------------------------
-- Table: hero_slides
-- Slides du carrousel hero
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `hero_slides` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `subtitle` VARCHAR(500) DEFAULT NULL,
    `cta_text` VARCHAR(100) DEFAULT NULL,
    `cta_url` VARCHAR(500) DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Table: trust_badges
-- Badges de confiance (livraison, qualité, etc.)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `trust_badges` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(100) NOT NULL,
    `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Emoji ou nom icône',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Table: how_it_works_steps
-- Étapes "Comment ça marche"
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `how_it_works_steps` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(100) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Emoji ou nom icône',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Table: testimonials
-- Témoignages clients
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `testimonials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `photo_url` VARCHAR(500) DEFAULT NULL,
    `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1 à 5 étoiles',
    `content` TEXT NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Ajout colonne is_featured sur products
-- ---------------------------------------------
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `is_featured` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`;
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_featured` (`is_featured`, `active`);

-- ---------------------------------------------
-- Données de démonstration
-- ---------------------------------------------

-- Hero slides
INSERT INTO `hero_slides` (`title`, `subtitle`, `cta_text`, `cta_url`, `sort_order`, `active`) VALUES
('Créez des vêtements uniques', 'Personnalisez vos textiles avec broderie, flocage et plus encore. Pour toute la famille.', 'Découvrir', '#produits', 1, 1),
('Qualité professionnelle', 'Des finitions soignées pour des créations qui durent dans le temps.', 'Voir les produits', '#produits', 2, 1);

-- Trust badges
INSERT INTO `trust_badges` (`title`, `icon`, `sort_order`, `active`) VALUES
('Livraison rapide', '🚚', 1, 1),
('Qualité garantie', '✨', 2, 1),
('Satisfait ou remboursé', '💯', 3, 1),
('Paiement sécurisé', '🔒', 4, 1);

-- How it works
INSERT INTO `how_it_works_steps` (`title`, `description`, `icon`, `sort_order`, `active`) VALUES
('Choisissez', 'Sélectionnez le produit qui vous plaît parmi notre catalogue.', '1️⃣', 1, 1),
('Personnalisez', 'Ajoutez votre texte, choisissez la police et la technique.', '2️⃣', 2, 1),
('Commandez', 'Validez votre panier et recevez votre création chez vous.', '3️⃣', 3, 1);

-- Testimonials
INSERT INTO `testimonials` (`name`, `rating`, `content`, `sort_order`, `active`) VALUES
('Marie L.', 5, 'Super qualité de broderie ! Le t-shirt pour l''anniversaire de mon fils était parfait.', 1, 1),
('Thomas D.', 5, 'Livraison rapide et le rendu est exactement comme sur le site. Je recommande !', 2, 1),
('Sophie M.', 4, 'Très satisfaite de ma commande. Le configurateur est facile à utiliser.', 3, 1);

-- =============================================
-- FIN DE LA MIGRATION
-- =============================================
