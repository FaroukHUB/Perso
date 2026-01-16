-- =============================================
-- PERSONNALY - Options de Personnalisation
-- Tables pour tailles, couleurs, positions
-- =============================================

SET NAMES utf8mb4;

-- ---------------------------------------------
-- Table: customization_options
-- Options de personnalisation administrables
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `customization_options` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` ENUM('size', 'color', 'position', 'font') NOT NULL,
    `value` VARCHAR(50) NOT NULL,
    `label` VARCHAR(100) NOT NULL,
    `hex_code` VARCHAR(7) DEFAULT NULL COMMENT 'Code couleur hex pour type=color',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type_active` (`type`, `active`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Données par défaut : Tailles
-- ---------------------------------------------
INSERT INTO `customization_options` (`type`, `value`, `label`, `sort_order`, `active`) VALUES
('size', 'XS', 'XS', 1, 1),
('size', 'S', 'S', 2, 1),
('size', 'M', 'M', 3, 1),
('size', 'L', 'L', 4, 1),
('size', 'XL', 'XL', 5, 1),
('size', 'XXL', 'XXL', 6, 1);

-- ---------------------------------------------
-- Données par défaut : Couleurs
-- ---------------------------------------------
INSERT INTO `customization_options` (`type`, `value`, `label`, `hex_code`, `sort_order`, `active`) VALUES
('color', 'blanc', 'Blanc', '#FFFFFF', 1, 1),
('color', 'noir', 'Noir', '#1A1A2E', 2, 1),
('color', 'rose', 'Rose', '#FF69B4', 3, 1),
('color', 'menthe', 'Vert Menthe', '#3DFFC0', 4, 1),
('color', 'bleu', 'Bleu', '#4A90D9', 5, 1),
('color', 'gris', 'Gris', '#6B7280', 6, 1);

-- ---------------------------------------------
-- Données par défaut : Positions
-- ---------------------------------------------
INSERT INTO `customization_options` (`type`, `value`, `label`, `sort_order`, `active`) VALUES
('position', 'centre', 'Centre', 1, 1),
('position', 'gauche', 'Gauche', 2, 1),
('position', 'droite', 'Droite', 3, 1),
('position', 'dos', 'Dos', 4, 1);

-- ---------------------------------------------
-- Données par défaut : Polices
-- ---------------------------------------------
INSERT INTO `customization_options` (`type`, `value`, `label`, `sort_order`, `active`) VALUES
('font', 'Poppins', 'Poppins (Moderne)', 1, 1),
('font', 'Playfair Display', 'Playfair (Élégant)', 2, 1),
('font', 'Lobster', 'Lobster (Script)', 3, 1),
('font', 'Oswald', 'Oswald (Impact)', 4, 1),
('font', 'Dancing Script', 'Dancing Script (Cursif)', 5, 1),
('font', 'Bebas Neue', 'Bebas Neue (Bold)', 6, 1);

-- =============================================
-- FIN - Exécuter dans phpMyAdmin
-- =============================================
