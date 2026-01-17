-- PERSONNALY - Migration : VRAI système d'Upsells
-- Suggestions de produits complémentaires pour augmenter le panier

-- Table des produits suggérés (upsells)
-- Permet de définir quels produits suggérer en fonction du contenu du panier
CREATE TABLE IF NOT EXISTS product_upsells (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Configuration de la suggestion
    name VARCHAR(255) NOT NULL COMMENT 'Nom interne pour identifier cette règle',

    -- Type de déclencheur
    trigger_type ENUM('product', 'category', 'any') DEFAULT 'any' COMMENT 'Quand déclencher',
    trigger_value VARCHAR(255) NULL COMMENT 'ID produit ou catégorie (NULL pour any)',

    -- Produit suggéré
    suggested_product_id INT NOT NULL COMMENT 'ID du produit à suggérer',

    -- Personnalisation de l'affichage
    custom_title VARCHAR(255) NULL COMMENT 'Titre personnalisé (sinon nom du produit)',
    custom_description TEXT NULL COMMENT 'Description personnalisée',
    badge_text VARCHAR(50) NULL COMMENT 'Badge ex: "Bestseller", "-20%", "Nouveau"',

    -- Prix promotionnel optionnel
    promo_price DECIMAL(10,2) NULL COMMENT 'Prix promo spécial upsell (optionnel)',

    -- Gestion
    priority INT DEFAULT 0 COMMENT 'Ordre d''affichage',
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_trigger (trigger_type, trigger_value),
    INDEX idx_product (suggested_product_id),
    INDEX idx_active_priority (active, priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration globale des upsells
CREATE TABLE IF NOT EXISTS upsell_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paramètres par défaut
INSERT INTO upsell_settings (setting_key, setting_value) VALUES
('enabled', '1'),
('title', 'Vous aimerez aussi'),
('max_items', '4'),
('show_on_cart', '1'),
('show_on_checkout', '0'),
('fallback_to_category', '1') -- Si pas d'upsell spécifique, suggérer produits même catégorie
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Exemples d'upsells (à adapter selon vos produits)
-- INSERT INTO product_upsells (name, trigger_type, trigger_value, suggested_product_id, badge_text, priority, active) VALUES
-- ('Casquette avec T-shirt', 'category', '1', 5, 'Populaire', 100, 1),
-- ('Mug personnalisé', 'any', NULL, 3, 'Idée cadeau', 50, 1);
