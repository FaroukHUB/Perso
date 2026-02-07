-- Migration: Ajouter la colonne preview_image_url à order_customizations
-- Date: 2026-02-07
-- Description: Permet de stocker l'image du design personnalisé pour affichage dans les commandes

ALTER TABLE order_customizations
ADD COLUMN preview_image_url VARCHAR(255) DEFAULT NULL
COMMENT 'URL de l\'image du design personnalisé généré'
AFTER data_json;

-- Index pour optimiser les requêtes
ALTER TABLE order_customizations
ADD INDEX idx_preview_image (preview_image_url);
