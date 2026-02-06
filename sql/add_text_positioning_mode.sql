/**
 * Ajout du paramètre de contrôle de positionnement du texte
 * Permet aux admins de choisir si les clients peuvent déplacer librement le texte
 * ou si l'emplacement est fixe/prédéfini
 */

-- Paramètre principal : Mode de positionnement (free, preset, fixed)
INSERT IGNORE INTO `shop_settings` (
    `setting_key`,
    `setting_value`,
    `setting_type`,
    `setting_group`,
    `setting_label`,
    `setting_description`,
    `sort_order`
) VALUES (
    'text_positioning_mode',
    'free',
    'string',
    'customization',
    'Mode de positionnement du texte',
    'Contrôle comment les clients peuvent positionner le texte sur les produits',
    10
);

-- Position fixe X (si mode = fixed)
INSERT IGNORE INTO `shop_settings` (
    `setting_key`,
    `setting_value`,
    `setting_type`,
    `setting_group`,
    `setting_label`,
    `setting_description`,
    `sort_order`
) VALUES (
    'text_fixed_position_x',
    '50',
    'number',
    'customization',
    'Position X fixe (%)',
    'Position horizontale du texte quand le mode est fixé (en pourcentage)',
    11
);

-- Position fixe Y (si mode = fixed)
INSERT IGNORE INTO `shop_settings` (
    `setting_key`,
    `setting_value`,
    `setting_type`,
    `setting_group`,
    `setting_label`,
    `setting_description`,
    `sort_order`
) VALUES (
    'text_fixed_position_y',
    '40',
    'number',
    'customization',
    'Position Y fixe (%)',
    'Position verticale du texte quand le mode est fixé (en pourcentage)',
    12
);

-- Zones prédéfinies (si mode = preset)
INSERT IGNORE INTO `shop_settings` (
    `setting_key`,
    `setting_value`,
    `setting_type`,
    `setting_group`,
    `setting_label`,
    `setting_description`,
    `sort_order`
) VALUES (
    'text_preset_zones',
    '[{"id":"center","label":"Centré","x":50,"y":50},{"id":"top_left","label":"Haut gauche","x":15,"y":15},{"id":"top_right","label":"Haut droite","x":85,"y":15},{"id":"bottom_left","label":"Bas gauche","x":15,"y":85},{"id":"bottom_right","label":"Bas droite","x":85,"y":85}]',
    'json',
    'customization',
    'Zones prédéfinies',
    'Liste des emplacements prédéfinis pour le texte (format JSON)',
    13
);
