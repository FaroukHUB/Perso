-- =============================================
-- PERSONNALY - Migration : Couleurs par Produit
-- Permet d'associer des couleurs spécifiques à chaque produit
-- =============================================

-- Table de liaison produits-couleurs
CREATE TABLE IF NOT EXISTS `product_colors` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `color_name` VARCHAR(50) NOT NULL COMMENT 'Nom de la couleur (ex: blanc, noir)',
    `hex_code` VARCHAR(7) NOT NULL COMMENT 'Code hexadécimal (#FFFFFF)',
    `sort_order` INT NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_active` (`active`),
    UNIQUE KEY `uk_product_color` (`product_id`, `color_name`),
    CONSTRAINT `fk_product_colors_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- NOTE: Exécuter ce script dans phpMyAdmin
-- Si un produit n'a pas de couleurs définies,
-- le système utilisera les couleurs par défaut
-- =============================================
