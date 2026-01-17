-- PERSONNALY - Migration : Codes Promo
-- Système de codes promotionnels avec saisie client

-- Supprimer anciennes tables si elles existent
DROP TABLE IF EXISTS order_promo_rules;
DROP TABLE IF EXISTS promo_rules;

-- Table des codes promo
CREATE TABLE IF NOT EXISTS promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Le code que le client tape
    code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Code promo (ex: BIENVENUE20)',
    name VARCHAR(255) NOT NULL COMMENT 'Nom descriptif pour l''admin',
    description TEXT NULL COMMENT 'Description interne',

    -- Type de réduction
    discount_type ENUM('percentage', 'fixed_amount', 'free_shipping') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(10,2) DEFAULT 0 COMMENT 'Valeur (% ou montant fixe)',

    -- Conditions d'utilisation
    min_order_amount DECIMAL(10,2) NULL COMMENT 'Montant minimum de commande',
    max_discount DECIMAL(10,2) NULL COMMENT 'Réduction max (pour les %)',

    -- Limites d'utilisation
    max_uses INT NULL COMMENT 'Nb max d''utilisations totales (NULL = illimité)',
    max_uses_per_customer INT DEFAULT 1 COMMENT 'Nb max par client',
    current_uses INT DEFAULT 0 COMMENT 'Compteur utilisations',

    -- Période de validité
    start_date DATE NULL COMMENT 'Date début (NULL = immédiat)',
    end_date DATE NULL COMMENT 'Date fin (NULL = illimité)',

    -- Statut
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_code (code),
    INDEX idx_active (active),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des codes utilisés par commande
CREATE TABLE IF NOT EXISTS order_promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    promo_code_id INT NOT NULL,
    code_used VARCHAR(50) NOT NULL COMMENT 'Code utilisé (copie)',
    discount_applied DECIMAL(10,2) DEFAULT 0 COMMENT 'Réduction appliquée',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_order_promo (order_id, promo_code_id),
    INDEX idx_order (order_id),
    INDEX idx_promo_code (promo_code_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemples de codes promo
INSERT INTO promo_codes (code, name, discount_type, discount_value, min_order_amount, max_uses, active) VALUES
('BIENVENUE10', 'Code bienvenue -10%', 'percentage', 10, 30, NULL, 1),
('LIVRAISON', 'Livraison offerte', 'free_shipping', 0, 50, NULL, 1),
('PROMO20', 'Réduction 20€', 'fixed_amount', 20, 80, 100, 0);
