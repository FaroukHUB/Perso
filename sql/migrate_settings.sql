-- Migration: Table settings pour configuration dynamique
-- Stocke les paramètres API (Stripe, Boxtal) et autres configurations

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    is_encrypted TINYINT(1) DEFAULT 0,
    category VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_category (category),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valeurs par défaut
INSERT INTO settings (setting_key, setting_value, is_encrypted, category, description) VALUES
-- Stripe
('stripe_enabled', '0', 0, 'payment', 'Activer les paiements Stripe'),
('stripe_mode', 'test', 0, 'payment', 'Mode Stripe: test ou live'),
('stripe_test_public_key', '', 1, 'payment', 'Clé publique Stripe (test)'),
('stripe_test_secret_key', '', 1, 'payment', 'Clé secrète Stripe (test)'),
('stripe_live_public_key', '', 1, 'payment', 'Clé publique Stripe (live)'),
('stripe_live_secret_key', '', 1, 'payment', 'Clé secrète Stripe (live)'),
('stripe_webhook_secret', '', 1, 'payment', 'Secret webhook Stripe'),

-- Boxtal
('boxtal_enabled', '0', 0, 'shipping', 'Activer Boxtal pour la livraison'),
('boxtal_mode', 'test', 0, 'shipping', 'Mode Boxtal: test ou live'),
('boxtal_user', '', 1, 'shipping', 'Utilisateur API Boxtal'),
('boxtal_api_key', '', 1, 'shipping', 'Clé API Boxtal'),
('boxtal_default_weight', '500', 0, 'shipping', 'Poids par défaut par article (grammes)'),

-- Expéditeur (pour Boxtal)
('shipper_company', '', 0, 'shipping', 'Nom de l''entreprise expéditrice'),
('shipper_address', '', 0, 'shipping', 'Adresse de l''expéditeur'),
('shipper_city', '', 0, 'shipping', 'Ville de l''expéditeur'),
('shipper_postcode', '', 0, 'shipping', 'Code postal de l''expéditeur'),
('shipper_country', 'FR', 0, 'shipping', 'Pays de l''expéditeur'),
('shipper_phone', '', 0, 'shipping', 'Téléphone de l''expéditeur'),
('shipper_email', '', 0, 'shipping', 'Email de l''expéditeur'),

-- Livraison manuelle (si Boxtal désactivé)
('shipping_free_threshold', '50', 0, 'shipping', 'Montant minimum pour livraison gratuite (€)'),
('shipping_standard_price', '4.90', 0, 'shipping', 'Prix livraison standard (€)'),
('shipping_express_price', '9.90', 0, 'shipping', 'Prix livraison express (€)'),

-- Général
('currency', 'EUR', 0, 'general', 'Devise'),
('tax_rate', '20', 0, 'general', 'Taux de TVA (%)'),
('order_email', '', 0, 'general', 'Email pour notifications commandes')

ON DUPLICATE KEY UPDATE setting_key = setting_key;
