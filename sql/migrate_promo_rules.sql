-- PERSONNALY - Migration : Renommer upsells en promo_rules
-- Ce système gère les règles promotionnelles conditionnelles
-- (SI condition ALORS offre/réduction)

-- Option 1: Si la table upsells existe, la renommer
-- RENAME TABLE upsells TO promo_rules;

-- Option 2: Créer une nouvelle table (si upsells n'existe pas encore)
CREATE TABLE IF NOT EXISTS promo_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Identification
    name VARCHAR(255) NOT NULL COMMENT 'Nom interne pour l''admin',
    description TEXT NULL COMMENT 'Description optionnelle',

    -- Condition de déclenchement
    condition_type ENUM('panier_min', 'produit_specifique', 'technique_specifique', 'categorie', 'quantite_min') NOT NULL,
    condition_value VARCHAR(255) NOT NULL COMMENT 'Valeur: montant, product_id, technique_value, category_id, quantité',

    -- Offre proposée
    offer_type ENUM('produit', 'option', 'reduction', 'livraison_gratuite') NOT NULL,
    offer_value VARCHAR(255) NOT NULL COMMENT 'Valeur: product_id, option_id, pourcentage, ou vide',
    offer_label VARCHAR(255) NULL COMMENT 'Texte personnalisé pour l''offre',

    -- Réduction appliquée
    discount_type ENUM('pourcentage', 'montant_fixe', 'aucun') DEFAULT 'aucun',
    discount_value DECIMAL(10,2) DEFAULT 0 COMMENT 'Pourcentage ou montant de réduction',

    -- Affichage
    display_title VARCHAR(255) NULL COMMENT 'Titre affiché au client',
    display_image VARCHAR(255) NULL COMMENT 'Image de l''offre',
    display_position ENUM('cart', 'checkout', 'both') DEFAULT 'both',

    -- Gestion
    priority INT DEFAULT 0 COMMENT 'Ordre d''affichage (plus haut = prioritaire)',
    max_uses INT DEFAULT NULL COMMENT 'Limite d''utilisations (NULL = illimité)',
    current_uses INT DEFAULT 0 COMMENT 'Compteur d''utilisations',

    -- Période de validité
    start_date DATE NULL COMMENT 'Date de début (NULL = immédiat)',
    end_date DATE NULL COMMENT 'Date de fin (NULL = illimité)',

    -- Statut
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_condition_type (condition_type),
    INDEX idx_active (active),
    INDEX idx_priority (priority DESC),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de suivi des règles promo utilisées par commande
CREATE TABLE IF NOT EXISTS order_promo_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    promo_rule_id INT NOT NULL,
    discount_applied DECIMAL(10,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_order_promo (order_id, promo_rule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemples de règles promotionnelles
INSERT INTO promo_rules (name, condition_type, condition_value, offer_type, offer_value, offer_label, discount_type, discount_value, display_title, display_position, priority, active) VALUES
('Offre -10% dès 50€', 'panier_min', '50', 'reduction', '10', '-10% sur votre commande', 'pourcentage', 10, 'Félicitations ! -10% offerts', 'checkout', 100, 1),
('Livraison gratuite dès 80€', 'panier_min', '80', 'livraison_gratuite', '', 'Livraison offerte', 'aucun', 0, 'Livraison gratuite offerte !', 'both', 90, 1),
('Promo broderie -15%', 'technique_specifique', 'broderie', 'reduction', '15', '-15% sur la broderie', 'pourcentage', 15, 'Spécial Broderie : -15%', 'cart', 80, 0);
