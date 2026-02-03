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

-- =====================================================
-- GROUPE: legal - Informations légales de l'entreprise
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
-- Informations entreprise
('legal_company_name', '', 'legal', 'text', 'Raison sociale', 'Nom légal de l''entreprise', 1),
('legal_company_type', 'auto-entrepreneur', 'legal', 'text', 'Forme juridique', 'SARL, SAS, Auto-entrepreneur, etc.', 2),
('legal_siret', '', 'legal', 'text', 'SIRET', 'Numéro SIRET (14 chiffres)', 3),
('legal_siren', '', 'legal', 'text', 'SIREN', 'Numéro SIREN (9 chiffres)', 4),
('legal_tva_number', '', 'legal', 'text', 'N° TVA Intracommunautaire', 'Numéro de TVA (si applicable)', 5),
('legal_rcs', '', 'legal', 'text', 'RCS', 'Ville d''immatriculation RCS', 6),
('legal_capital', '', 'legal', 'text', 'Capital social', 'Montant du capital (si applicable)', 7),
-- Adresse
('legal_address', '', 'legal', 'text', 'Adresse', 'Adresse du siège social', 10),
('legal_postcode', '', 'legal', 'text', 'Code postal', 'Code postal du siège', 11),
('legal_city', '', 'legal', 'text', 'Ville', 'Ville du siège social', 12),
('legal_country', 'France', 'legal', 'text', 'Pays', 'Pays du siège social', 13),
-- Contact
('legal_phone', '', 'legal', 'text', 'Téléphone', 'Téléphone professionnel', 15),
('legal_email', '', 'legal', 'email', 'Email', 'Email de contact légal', 16),
-- Responsable
('legal_director_name', '', 'legal', 'text', 'Directeur de publication', 'Nom du responsable/gérant', 20),
('legal_director_title', 'Gérant', 'legal', 'text', 'Fonction', 'Titre du responsable', 21),
-- Hébergement
('legal_host_name', 'OVH', 'legal', 'text', 'Nom de l''hébergeur', 'Société qui héberge le site', 25),
('legal_host_address', '2 rue Kellermann, 59100 Roubaix, France', 'legal', 'text', 'Adresse de l''hébergeur', 'Adresse complète', 26),
('legal_host_phone', '', 'legal', 'text', 'Téléphone hébergeur', 'Numéro de contact', 27),
-- DPO / RGPD
('legal_dpo_name', '', 'legal', 'text', 'DPO (Délégué à la protection des données)', 'Nom du DPO si applicable', 30),
('legal_dpo_email', '', 'legal', 'email', 'Email DPO', 'Email de contact pour RGPD', 31),
-- Données collectées
('legal_data_collected', 'nom, prénom, adresse email, adresse postale, numéro de téléphone', 'legal', 'textarea', 'Données collectées', 'Types de données personnelles collectées', 35),
('legal_data_purpose', 'traitement des commandes, livraison, service client, newsletter (avec consentement)', 'legal', 'textarea', 'Finalités du traitement', 'Pourquoi ces données sont collectées', 36),
('legal_data_retention', '3 ans après la dernière commande', 'legal', 'text', 'Durée de conservation', 'Combien de temps les données sont gardées', 37),
('legal_cookies_used', 'cookies de session, cookies de panier, cookies analytiques (avec consentement)', 'legal', 'textarea', 'Cookies utilisés', 'Types de cookies sur le site', 38),
-- Pages générées (contenu)
('legal_pages_generated', '0', 'legal', 'boolean', 'Pages générées', 'Indique si les pages ont été générées', 50)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: cgv - Conditions Générales de Vente détaillées
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
-- Commandes
('cgv_order_confirmation', 'Un email de confirmation vous est envoyé dès validation de votre commande.', 'cgv', 'textarea', 'Confirmation de commande', 'Comment la commande est confirmée', 1),
('cgv_order_modification', 'Toute modification de commande doit être demandée dans les 2 heures suivant la commande, avant mise en production.', 'cgv', 'textarea', 'Modification de commande', 'Politique de modification', 2),
('cgv_order_cancellation', 'L''annulation est possible uniquement avant la mise en production du produit personnalisé.', 'cgv', 'textarea', 'Annulation de commande', 'Conditions d''annulation', 3),
('cgv_production_time', '3 à 5 jours ouvrés', 'cgv', 'text', 'Délai de fabrication', 'Temps de production des articles personnalisés', 4),
-- Livraison
('cgv_delivery_zones', 'France métropolitaine, DOM-TOM, Belgique, Suisse, Luxembourg', 'cgv', 'textarea', 'Zones de livraison', 'Pays/régions livrés', 10),
('cgv_delivery_standard_time', '3 à 5 jours ouvrés', 'cgv', 'text', 'Délai livraison standard', 'Délai pour la livraison standard', 11),
('cgv_delivery_express_time', '24 à 48 heures', 'cgv', 'text', 'Délai livraison express', 'Délai pour la livraison express', 12),
('cgv_delivery_carriers', 'Colissimo, Mondial Relay, Chronopost', 'cgv', 'text', 'Transporteurs', 'Liste des transporteurs utilisés', 13),
('cgv_delivery_tracking', 'Un numéro de suivi vous est communiqué par email dès l''expédition de votre colis.', 'cgv', 'textarea', 'Suivi de livraison', 'Information sur le suivi', 14),
('cgv_delivery_signature', '0', 'cgv', 'boolean', 'Signature requise', 'La signature est-elle requise à la livraison', 15),
('cgv_delivery_insurance', '1', 'cgv', 'boolean', 'Assurance incluse', 'Les colis sont-ils assurés', 16),
-- Paiement
('cgv_payment_methods', 'Carte bancaire (Visa, Mastercard, CB, American Express) via Stripe', 'cgv', 'textarea', 'Moyens de paiement', 'Détail des moyens de paiement acceptés', 20),
('cgv_payment_security', 'Tous les paiements sont sécurisés par Stripe. Vos données bancaires ne transitent jamais par nos serveurs et sont chiffrées en SSL/TLS.', 'cgv', 'textarea', 'Sécurité des paiements', 'Information sur la sécurité', 21),
('cgv_payment_debit_time', 'Le débit est effectué immédiatement à la validation de la commande.', 'cgv', 'textarea', 'Moment du débit', 'Quand le paiement est débité', 22),
('cgv_payment_installments', '0', 'cgv', 'boolean', 'Paiement en plusieurs fois', 'Proposez-vous le paiement en plusieurs fois', 23),
('cgv_payment_installments_info', '', 'cgv', 'textarea', 'Détails paiement fractionné', 'Conditions du paiement en plusieurs fois', 24),
-- Garanties
('cgv_legal_warranty', '2 ans', 'cgv', 'text', 'Garantie légale de conformité', 'Durée de la garantie légale', 30),
('cgv_commercial_warranty', '0', 'cgv', 'boolean', 'Garantie commerciale', 'Proposez-vous une garantie commerciale supplémentaire', 31),
('cgv_commercial_warranty_duration', '', 'cgv', 'text', 'Durée garantie commerciale', 'Durée de la garantie commerciale', 32),
('cgv_commercial_warranty_coverage', '', 'cgv', 'textarea', 'Couverture garantie', 'Ce que couvre la garantie commerciale', 33),
-- Produits personnalisés
('cgv_custom_products_policy', 'Les produits personnalisés sont fabriqués selon vos spécifications. Nous ne pouvons être tenus responsables des erreurs dues aux informations fournies par le client (textes, images, choix de personnalisation).', 'cgv', 'textarea', 'Politique produits personnalisés', 'Responsabilité sur la personnalisation', 40),
('cgv_custom_products_ip', 'Vous garantissez détenir les droits sur les contenus (textes, images, logos) que vous nous transmettez pour personnalisation. Tout contenu illégal, diffamatoire ou portant atteinte aux droits de tiers sera refusé.', 'cgv', 'textarea', 'Propriété intellectuelle client', 'Droits sur les contenus fournis', 41),
('cgv_prohibited_content', 'Contenu à caractère pornographique, violent, raciste, discriminatoire, incitant à la haine, ou portant atteinte aux droits de propriété intellectuelle de tiers.', 'cgv', 'textarea', 'Contenus interdits', 'Types de contenus refusés', 42),
-- Litiges
('cgv_mediator_name', '', 'cgv', 'text', 'Nom du médiateur', 'Médiateur de la consommation', 50),
('cgv_mediator_address', '', 'cgv', 'textarea', 'Adresse du médiateur', 'Coordonnées complètes du médiateur', 51),
('cgv_mediator_website', '', 'cgv', 'url', 'Site du médiateur', 'URL du site de médiation', 52),
('cgv_applicable_law', 'droit français', 'cgv', 'text', 'Droit applicable', 'Quel droit s''applique', 53),
('cgv_competent_court', 'les tribunaux du ressort de notre siège social', 'cgv', 'text', 'Tribunal compétent', 'Juridiction compétente', 54)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- GROUPE: return_policy - Politique de retour détaillée
-- =====================================================
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order) VALUES
-- Conditions générales
('return_standard_products', '1', 'return_policy', 'boolean', 'Retour produits standards', 'Les produits non personnalisés peuvent être retournés', 1),
('return_custom_products', '0', 'return_policy', 'boolean', 'Retour produits personnalisés', 'Les produits personnalisés peuvent être retournés', 2),
('return_custom_exception', 'Conformément à l''article L.221-28 du Code de la consommation, les produits personnalisés ou confectionnés selon vos spécifications ne peuvent faire l''objet d''un retour, sauf défaut de fabrication avéré.', 'return_policy', 'textarea', 'Exception personnalisés', 'Texte explicatif pour les produits personnalisés', 3),
-- Conditions du produit
('return_product_condition', 'non porté, non lavé, avec étiquettes d''origine attachées', 'return_policy', 'text', 'État du produit requis', 'Dans quel état le produit doit être retourné', 10),
('return_original_packaging', '1', 'return_policy', 'boolean', 'Emballage d''origine requis', 'Le produit doit être dans son emballage d''origine', 11),
('return_complete_product', 'Le produit doit être retourné complet avec tous ses accessoires (housses, étiquettes, etc.)', 'return_policy', 'textarea', 'Produit complet', 'Éléments à retourner avec le produit', 12),
-- Processus de retour
('return_request_method', 'email', 'return_policy', 'text', 'Méthode de demande', 'Comment demander un retour (email, formulaire, téléphone)', 20),
('return_request_info', 'Envoyez un email avec votre numéro de commande et les articles à retourner. Vous recevrez sous 48h les instructions et l''étiquette de retour.', 'return_policy', 'textarea', 'Instructions de demande', 'Comment faire la demande de retour', 21),
('return_label_provided', '1', 'return_policy', 'boolean', 'Étiquette fournie', 'Fournissez-vous une étiquette de retour', 22),
('return_drop_points', 'Bureau de poste, points relais Mondial Relay, Colissimo', 'return_policy', 'text', 'Points de dépôt', 'Où déposer le colis de retour', 23),
('return_address', '', 'return_policy', 'textarea', 'Adresse de retour', 'Adresse complète pour les retours (si différente du siège)', 24),
-- Remboursement
('return_refund_delay', '14 jours', 'return_policy', 'text', 'Délai de remboursement', 'Délai maximum après réception du retour', 30),
('return_refund_method', 'Le remboursement est effectué sur le même moyen de paiement utilisé lors de la commande.', 'return_policy', 'textarea', 'Mode de remboursement', 'Comment le remboursement est effectué', 31),
('return_shipping_refund', 'Les frais de livraison initiaux sont remboursés uniquement en cas de retour de la totalité de la commande.', 'return_policy', 'textarea', 'Remboursement frais de port', 'Politique sur les frais de port', 32),
('return_partial_refund', 'En cas de produit retourné incomplet ou endommagé, un remboursement partiel pourra être appliqué.', 'return_policy', 'textarea', 'Remboursement partiel', 'Conditions de remboursement partiel', 33),
-- Échange
('return_exchange_available', '1', 'return_policy', 'boolean', 'Échange possible', 'Proposez-vous l''échange en plus du remboursement', 40),
('return_exchange_info', 'L''échange est possible sous réserve de disponibilité. Contactez-nous pour vérifier les stocks avant de retourner l''article.', 'return_policy', 'textarea', 'Conditions d''échange', 'Comment fonctionne l''échange', 41),
('return_size_exchange', 'Pour un échange de taille, retournez l''article et passez une nouvelle commande. Vous serez remboursé dès réception du retour.', 'return_policy', 'textarea', 'Échange de taille', 'Procédure pour changer de taille', 42),
-- Produits défectueux
('return_defective_policy', 'Si vous recevez un produit défectueux ou non conforme, contactez-nous dans les 48h suivant la réception avec des photos du défaut.', 'return_policy', 'textarea', 'Produits défectueux', 'Que faire en cas de défaut', 50),
('return_defective_evidence', 'photos du produit et du défaut, photo de l''emballage', 'return_policy', 'text', 'Preuves demandées', 'Documents/photos à fournir', 51),
('return_defective_resolution', 'Remplacement du produit ou remboursement intégral à votre choix, frais de retour pris en charge.', 'return_policy', 'textarea', 'Résolution défaut', 'Comment le problème est résolu', 52),
('return_defective_delay', '48 heures', 'return_policy', 'text', 'Délai de signalement', 'Dans quel délai signaler un défaut', 53),
-- Erreur de livraison
('return_wrong_item_policy', 'Si vous recevez un article différent de votre commande, contactez-nous immédiatement. Nous organisons le retour à nos frais et vous envoyons le bon article en priorité.', 'return_policy', 'textarea', 'Erreur de livraison', 'Procédure en cas d''erreur', 60)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Confirmation
SELECT 'Shop settings migration completed successfully!' AS status;
SELECT setting_group, COUNT(*) as count FROM shop_settings GROUP BY setting_group ORDER BY setting_group;
