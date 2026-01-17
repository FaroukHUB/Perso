-- PERSONNALY - Migration : Système d'Upsells (Suggestions produits)
-- Suggère plusieurs produits complémentaires sur la page panier

-- Table des produits suggérés (upsells)
DROP TABLE IF EXISTS product_upsells;
CREATE TABLE IF NOT EXISTS product_upsells (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Produit suggéré
    product_id INT UNSIGNED NOT NULL COMMENT 'ID du produit à suggérer',

    -- Personnalisation de l'affichage
    custom_title VARCHAR(255) NULL COMMENT 'Titre personnalisé (sinon nom du produit)',
    custom_description TEXT NULL COMMENT 'Description courte',
    badge_text VARCHAR(50) NULL COMMENT 'Badge ex: "Bestseller", "-20%", "Nouveau"',

    -- Prix promotionnel optionnel
    promo_price DECIMAL(10,2) NULL COMMENT 'Prix promo spécial upsell (optionnel)',

    -- Gestion
    priority INT DEFAULT 0 COMMENT 'Ordre d''affichage (plus haut = premier)',
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_product (product_id),
    INDEX idx_active_priority (active, priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration globale des upsells
DROP TABLE IF EXISTS upsell_settings;
CREATE TABLE IF NOT EXISTS upsell_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paramètres par défaut
INSERT INTO upsell_settings (setting_key, setting_value) VALUES
('enabled', '1'),
('title', 'Complétez votre commande'),
('subtitle', 'Ces articles pourraient vous plaire'),
('max_items', '4'),
('show_on_cart', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
