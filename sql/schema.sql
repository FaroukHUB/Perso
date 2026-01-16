-- =============================================
-- PERSONNALY - Schéma Base de Données
-- Personnalisation textile familiale
-- =============================================
-- Compatible phpMyAdmin / MySQL 5.7+
-- À exécuter dans phpMyAdmin sur o2switch
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------
-- Table: users
-- Utilisateurs (admin et clients)
-- ---------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    `first_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Table: products
-- Catalogue de produits personnalisables
-- ---------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `base_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `category` VARCHAR(100) DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`active`),
    KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Table: orders
-- Commandes clients
-- ---------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'accepted', 'in_progress', 'completed', 'shipped', 'cancelled') NOT NULL DEFAULT 'pending',
    `notes` TEXT,
    `shipping_address` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Table: order_customizations
-- Personnalisations par commande (JSON flexible)
-- ---------------------------------------------
DROP TABLE IF EXISTS `order_customizations`;
CREATE TABLE `order_customizations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `data_json` JSON NOT NULL COMMENT 'Toutes les options de personnalisation',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_product` (`product_id`),
    CONSTRAINT `fk_customizations_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_customizations_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Données initiales
-- ---------------------------------------------

-- Admin par défaut (mot de passe: admin123)
-- IMPORTANT: Changer le mot de passe en production !
INSERT INTO `users` (`email`, `password_hash`, `role`, `first_name`, `last_name`) VALUES
('admin@personnaly.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'PERSONNALY');

-- Produits de démonstration
INSERT INTO `products` (`name`, `description`, `base_price`, `category`, `active`) VALUES
('T-Shirt Homme Classique', 'T-shirt 100% coton, personnalisable', 19.90, 'Homme', 1),
('T-Shirt Femme Ajusté', 'T-shirt coupe ajustée, personnalisable', 19.90, 'Femme', 1),
('T-Shirt Enfant', 'T-shirt enfant 100% coton, personnalisable', 14.90, 'Enfant', 1),
('Sweat à Capuche Unisexe', 'Sweat confortable avec capuche', 39.90, 'Unisexe', 1),
('Polo Homme Premium', 'Polo en coton piqué', 29.90, 'Homme', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- FIN DU SCHÉMA
-- =============================================
--
-- NOTES D'UTILISATION :
--
-- 1. Copier ce script dans phpMyAdmin
-- 2. Sélectionner la base zajr1824_persosaas
-- 3. Onglet "SQL" > Coller > Exécuter
--
-- Le compte admin par défaut :
-- Email: admin@personnaly.fr
-- Mot de passe: admin123
--
-- ⚠️ CHANGER LE MOT DE PASSE IMMÉDIATEMENT !
-- =============================================
