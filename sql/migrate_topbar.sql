-- Migration: Top Bar (Barre d'annonce)
-- Date: 2026-02-03

-- Paramètres pour la top bar promotionnelle
INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, sort_order)
VALUES
    ('topbar_enabled', '1', 'topbar', 'boolean', 'Activer la top bar', 'Afficher la barre d''annonce en haut du site', 1),
    ('topbar_text', 'Livraison GRATUITE dès 50€ d''achat ! Code promo: BIENVENUE10', 'topbar', 'string', 'Texte de la top bar', 'Message défilant affiché dans la barre', 2),
    ('topbar_link', '', 'topbar', 'string', 'Lien (optionnel)', 'URL vers laquelle le texte redirige au clic', 3),
    ('topbar_bg_color', '#1a1a2e', 'topbar', 'string', 'Couleur de fond', 'Couleur de fond de la barre', 4),
    ('topbar_text_color', '#ffffff', 'topbar', 'string', 'Couleur du texte', 'Couleur du texte de la barre', 5),
    ('topbar_font_family', 'inherit', 'topbar', 'string', 'Police', 'Police d''écriture (inherit = police du site)', 6),
    ('topbar_font_size', '14', 'topbar', 'string', 'Taille de police (px)', 'Taille du texte en pixels', 7),
    ('topbar_scroll_speed', '30', 'topbar', 'string', 'Vitesse de défilement (secondes)', 'Durée d''un cycle complet de défilement', 8)
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label);
