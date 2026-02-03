-- PERSONNALY - Migration couleurs header/footer
-- Ajoute les paramètres de personnalisation des couleurs

-- Couleur de fond du header (navbar)
INSERT INTO shop_settings (setting_key, setting_value, setting_type, setting_group, setting_label, setting_description, sort_order)
VALUES ('header_bg_color', '#1a1a2e', 'string', 'appearance', 'Couleur de fond du header', 'Couleur de fond de la barre de navigation', 100)
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label);

-- Couleur de texte du header
INSERT INTO shop_settings (setting_key, setting_value, setting_type, setting_group, setting_label, setting_description, sort_order)
VALUES ('header_text_color', '#ffffff', 'string', 'appearance', 'Couleur du texte du header', 'Couleur du texte de la barre de navigation', 101)
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label);

-- Couleur de fond du footer
INSERT INTO shop_settings (setting_key, setting_value, setting_type, setting_group, setting_label, setting_description, sort_order)
VALUES ('footer_bg_color', '#1a1a2e', 'string', 'appearance', 'Couleur de fond du footer', 'Couleur de fond du pied de page', 102)
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label);

-- Couleur de texte du footer
INSERT INTO shop_settings (setting_key, setting_value, setting_type, setting_group, setting_label, setting_description, sort_order)
VALUES ('footer_text_color', '#ffffff', 'string', 'appearance', 'Couleur du texte du footer', 'Couleur du texte du pied de page', 103)
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label);
