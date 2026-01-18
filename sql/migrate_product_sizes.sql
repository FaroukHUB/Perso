-- Migration: Ajouter les tailles disponibles par produit
-- Date: 2026-01-18
-- Description: Ajoute la colonne 'available_sizes' à la table products
-- pour stocker les tailles disponibles pour chaque produit (JSON)

ALTER TABLE products
    ADD COLUMN available_sizes TEXT DEFAULT NULL AFTER image_back_url;

-- Exemple de valeur: ["XS","S","M","L","XL","XXL"] ou ["36","38","40","42"]
