-- =====================================================
-- PERSONNALY - Shop Settings Migration
-- Configuration centralisée pour tous les paramètres
-- =====================================================

-- Table principale des paramètres
CREATE TABLE IF NOT EXISTS shop_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    setting_type ENUM('text', 'textarea', 'number', 'boolean', 'json', 'email', 'url', 'color') DEFAULT 'text',
    setting_label VARCHAR(255),
    setting_description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group (setting_group),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- GROUPE: general - Informations générales
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('site_name', 'PERSONNALY', 'general', 'text', 'Nom du site', 'Nom de la marque affiché partout sur le site', 1),
('site_description', 'Créez des produits uniques qui vous ressemblent. Personnalisation textile de qualité, made in France.', 'general', 'textarea', 'Description du site', 'Description courte pour le footer', 2),
('contact_email', 'contact@personnaly.fr', 'general', 'email', 'Email de contact', 'Email affiché pour le contact client', 3),
('contact_phone', '', 'general', 'text', 'Téléphone de contact', 'Numéro de téléphone (optionnel)', 4),
('copyright_text', '© 2026 PERSONNALY. Tous droits réservés.', 'general', 'text', 'Texte copyright', 'Texte affiché en bas de page', 5),
('currency', 'EUR', 'general', 'text', 'Devise', 'Code devise (EUR, USD, etc.)', 6),
('locale', 'fr-FR', 'general', 'text', 'Locale', 'Format de langue (fr-FR, en-US, etc.)', 7)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: shipping - Livraison
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('free_shipping_enabled', '1', 'shipping', 'boolean', 'Livraison gratuite activée', 'Activer la livraison gratuite à partir d''un certain montant', 1),
('free_shipping_threshold', '50', 'shipping', 'number', 'Seuil livraison gratuite', 'Montant minimum pour la livraison gratuite (en €)', 2),
('shipping_default_country', 'FR', 'shipping', 'text', 'Pays par défaut', 'Code pays par défaut pour l''estimation', 3),
('shipping_default_city', 'Paris', 'shipping', 'text', 'Ville par défaut', 'Ville par défaut pour l''estimation', 4),
('shipping_default_postcode', '75001', 'shipping', 'text', 'Code postal par défaut', 'Code postal par défaut pour l''estimation', 5),
('shipping_fallback_standard_price', '4.90', 'shipping', 'number', 'Prix standard (fallback)', 'Prix livraison standard si Boxtal indisponible', 6),
('shipping_fallback_standard_label', 'Livraison standard', 'shipping', 'text', 'Label standard (fallback)', 'Nom de la livraison standard', 7),
('shipping_fallback_standard_delay', '3-5 jours ouvrés', 'shipping', 'text', 'Délai standard (fallback)', 'Délai affiché pour la livraison standard', 8),
('shipping_fallback_express_price', '9.90', 'shipping', 'number', 'Prix express (fallback)', 'Prix livraison express si Boxtal indisponible', 9),
('shipping_fallback_express_label', 'Livraison express', 'shipping', 'text', 'Label express (fallback)', 'Nom de la livraison express', 10),
('shipping_fallback_express_delay', '24-48h', 'shipping', 'text', 'Délai express (fallback)', 'Délai affiché pour la livraison express', 11)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: returns - Retours et remboursements
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('returns_enabled', '1', 'returns', 'boolean', 'Retours activés', 'Autoriser les retours produits', 1),
('returns_days', '14', 'returns', 'number', 'Délai de retour (jours)', 'Nombre de jours pour effectuer un retour', 2),
('returns_free', '1', 'returns', 'boolean', 'Retours gratuits', 'Les frais de retour sont pris en charge', 3),
('returns_conditions', 'Produit non porté, dans son emballage d''origine avec étiquettes.', 'returns', 'textarea', 'Conditions de retour', 'Conditions pour accepter un retour', 4),
('refund_method', 'original', 'returns', 'text', 'Mode de remboursement', 'original = même moyen de paiement, credit = avoir', 5)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: payments - Moyens de paiement
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('payment_methods', '["visa", "mastercard", "amex", "cb"]', 'payments', 'json', 'Moyens de paiement acceptés', 'Liste des moyens de paiement à afficher', 1),
('payment_visa_enabled', '1', 'payments', 'boolean', 'Visa', 'Accepter les cartes Visa', 2),
('payment_mastercard_enabled', '1', 'payments', 'boolean', 'Mastercard', 'Accepter les cartes Mastercard', 3),
('payment_amex_enabled', '1', 'payments', 'boolean', 'American Express', 'Accepter les cartes Amex', 4),
('payment_cb_enabled', '1', 'payments', 'boolean', 'Carte Bancaire', 'Accepter les cartes CB', 5),
('payment_paypal_enabled', '0', 'payments', 'boolean', 'PayPal', 'Accepter PayPal', 6),
('payment_apple_pay_enabled', '0', 'payments', 'boolean', 'Apple Pay', 'Accepter Apple Pay', 7),
('payment_google_pay_enabled', '0', 'payments', 'boolean', 'Google Pay', 'Accepter Google Pay', 8)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: trust_badges - Badges de confiance
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('trust_badge_1_enabled', '1', 'trust_badges', 'boolean', 'Badge 1 activé', 'Afficher le premier badge', 1),
('trust_badge_1_icon', 'lock', 'trust_badges', 'text', 'Badge 1 icône', 'Icône: lock, check, truck, shield, star', 2),
('trust_badge_1_text', 'Paiement 100% sécurisé', 'trust_badges', 'text', 'Badge 1 texte', 'Texte du premier badge', 3),
('trust_badge_2_enabled', '1', 'trust_badges', 'boolean', 'Badge 2 activé', 'Afficher le deuxième badge', 4),
('trust_badge_2_icon', 'check', 'trust_badges', 'text', 'Badge 2 icône', 'Icône: lock, check, truck, shield, star', 5),
('trust_badge_2_text', 'Satisfait ou remboursé 14 jours', 'trust_badges', 'text', 'Badge 2 texte', 'Texte du deuxième badge', 6),
('trust_badge_3_enabled', '1', 'trust_badges', 'boolean', 'Badge 3 activé', 'Afficher le troisième badge', 7),
('trust_badge_3_icon', 'truck', 'trust_badges', 'text', 'Badge 3 icône', 'Icône: lock, check, truck, shield, star', 8),
('trust_badge_3_text', 'Livraison offerte dès 50€', 'trust_badges', 'text', 'Badge 3 texte', 'Texte du troisième badge', 9),
('trust_badge_4_enabled', '0', 'trust_badges', 'boolean', 'Badge 4 activé', 'Afficher le quatrième badge (optionnel)', 10),
('trust_badge_4_icon', 'shield', 'trust_badges', 'text', 'Badge 4 icône', 'Icône: lock, check, truck, shield, star', 11),
('trust_badge_4_text', '', 'trust_badges', 'text', 'Badge 4 texte', 'Texte du quatrième badge', 12)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: footer - Contenu du footer
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('footer_reassurance_1', '🔒 Paiement sécurisé', 'footer', 'text', 'Reassurance 1', 'Premier élément de réassurance', 1),
('footer_reassurance_2', '🚚 Livraison gratuite dès 50€', 'footer', 'text', 'Reassurance 2', 'Deuxième élément de réassurance', 2),
('footer_reassurance_3', '↩️ Retours 14 jours', 'footer', 'text', 'Reassurance 3', 'Troisième élément de réassurance', 3),
('footer_col1_title', 'Navigation', 'footer', 'text', 'Titre colonne 1', 'Titre de la première colonne', 4),
('footer_col2_title', 'Informations', 'footer', 'text', 'Titre colonne 2', 'Titre de la deuxième colonne', 5),
('footer_col3_title', 'Contact', 'footer', 'text', 'Titre colonne 3', 'Titre de la troisième colonne', 6),
('footer_links', '[{"label":"Livraison","url":"/livraison"},{"label":"Retours","url":"/retours"},{"label":"FAQ","url":"/faq"},{"label":"CGV","url":"/cgv"},{"label":"Mentions légales","url":"/mentions-legales"}]', 'footer', 'json', 'Liens du footer', 'Liste des liens informatifs', 7)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: cart - Panier
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('cart_title', 'Votre Panier', 'cart', 'text', 'Titre du panier', 'Titre affiché sur la page panier', 1),
('cart_empty_title', 'Votre panier est vide', 'cart', 'text', 'Titre panier vide', 'Titre quand le panier est vide', 2),
('cart_empty_description', 'Découvrez nos produits personnalisables et créez quelque chose d''unique !', 'cart', 'textarea', 'Description panier vide', 'Message quand le panier est vide', 3),
('cart_empty_button', 'Découvrir nos produits', 'cart', 'text', 'Bouton panier vide', 'Texte du bouton CTA panier vide', 4),
('cart_items_title', 'Vos articles', 'cart', 'text', 'Titre articles', 'Titre de la section articles', 5),
('cart_clear_button', 'Vider', 'cart', 'text', 'Bouton vider', 'Texte du bouton vider le panier', 6),
('cart_summary_title', 'Récapitulatif', 'cart', 'text', 'Titre récapitulatif', 'Titre de la section résumé', 7),
('cart_promo_label', 'Code promo', 'cart', 'text', 'Label code promo', 'Label du champ code promo', 8),
('cart_promo_placeholder', 'Entrez votre code', 'cart', 'text', 'Placeholder code promo', 'Placeholder du champ', 9),
('cart_promo_button', 'Appliquer', 'cart', 'text', 'Bouton appliquer', 'Texte du bouton appliquer', 10),
('cart_shipping_label', 'Livraison', 'cart', 'text', 'Label livraison', 'Label de la section livraison', 11),
('cart_subtotal_label', 'Sous-total', 'cart', 'text', 'Label sous-total', 'Label du sous-total', 12),
('cart_discount_label', 'Réduction', 'cart', 'text', 'Label réduction', 'Label de la réduction', 13),
('cart_total_label', 'Total', 'cart', 'text', 'Label total', 'Label du total', 14),
('cart_checkout_button', 'Passer commande', 'cart', 'text', 'Bouton commander', 'Texte du bouton checkout', 15),
('cart_continue_link', '← Continuer mes achats', 'cart', 'text', 'Lien continuer', 'Texte du lien retour shopping', 16)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: messages - Messages système
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('msg_quantity_updated', 'Quantité mise à jour.', 'messages', 'text', 'Quantité mise à jour', 'Message après modification quantité', 1),
('msg_item_removed', 'Article supprimé du panier.', 'messages', 'text', 'Article supprimé', 'Message après suppression article', 2),
('msg_cart_cleared', 'Panier vidé.', 'messages', 'text', 'Panier vidé', 'Message après vidage panier', 3),
('msg_session_expired', 'Session expirée. Veuillez réessayer.', 'messages', 'text', 'Session expirée', 'Message erreur CSRF', 4),
('msg_promo_applied', 'Code promo appliqué !', 'messages', 'text', 'Promo appliquée', 'Message succès code promo', 5),
('msg_free_shipping_applied', 'Livraison gratuite appliquée !', 'messages', 'text', 'Livraison gratuite', 'Message livraison gratuite', 6),
('msg_connection_error', 'Erreur de connexion. Réessayez.', 'messages', 'text', 'Erreur connexion', 'Message erreur AJAX', 7),
('msg_enter_promo', 'Veuillez entrer un code promo', 'messages', 'text', 'Entrez code promo', 'Message code promo vide', 8),
('msg_discount_applied', 'de réduction !', 'messages', 'text', 'Réduction appliquée', 'Suffixe message réduction', 9)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: defaults - Valeurs par défaut produits
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('default_size', 'M', 'defaults', 'text', 'Taille par défaut', 'Taille sélectionnée par défaut', 1),
('default_color', 'blanc', 'defaults', 'text', 'Couleur par défaut', 'Couleur sélectionnée par défaut', 2),
('default_quantity', '1', 'defaults', 'number', 'Quantité par défaut', 'Quantité par défaut dans le panier', 3)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: navbar - Navigation
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
('navbar_links', '[{"label":"Accueil","url":"/"},{"label":"Nos Produits","url":"/#produits"}]', 'navbar', 'json', 'Liens de navigation', 'Liens affichés dans la navbar', 1),
('navbar_show_cart', '1', 'navbar', 'boolean', 'Afficher panier', 'Afficher l''icône panier dans la navbar', 2)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Confirmation
SELECT 'Shop settings migration completed successfully!' AS status;
SELECT setting_group, COUNT(*) as count FROM shop_settings GROUP BY setting_group ORDER BY setting_group;
