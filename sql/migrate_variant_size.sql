-- Migration: Ajouter la taille aux variantes produit
-- Date: 2026-01-18
-- Description: Ajoute la colonne 'size' à la table product_color_images
-- pour permettre de gérer taille + couleur dans une seule variante

ALTER TABLE product_color_images
    ADD COLUMN size VARCHAR(50) DEFAULT NULL AFTER hex_code;

-- Index pour recherche rapide par taille
CREATE INDEX idx_variant_size ON product_color_images(product_id, size);
