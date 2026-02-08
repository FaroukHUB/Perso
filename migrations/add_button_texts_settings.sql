-- Migration: Ajouter les textes des boutons administrables
-- Date: 2026-02-08

INSERT INTO shop_settings (setting_key, setting_value, setting_type, description) VALUES
('btn_add_to_cart_text', 'Ajouter au panier', 'string', 'Texte du bouton "Ajouter au panier" sur les cartes produits'),
('btn_customize_text', 'Personnaliser', 'string', 'Texte du bouton "Personnaliser" sur les cartes produits'),
('btn_buy_now_text', 'Acheter maintenant', 'string', 'Texte du bouton achat rapide'),
('btn_add_to_cart_loading', 'Ajout...', 'string', 'Texte du bouton pendant l\'ajout au panier'),
('btn_add_to_cart_success', 'Ajouté !', 'string', 'Texte du bouton après ajout réussi'),
('btn_add_to_cart_error', 'Erreur', 'string', 'Texte du bouton en cas d\'erreur')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    description = VALUES(description);
