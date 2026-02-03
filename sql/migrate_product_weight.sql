-- Migration: Ajouter le poids aux produits
-- Date: 2026-02-03

-- Ajouter la colonne poids (en grammes) à la table products
ALTER TABLE `products`
ADD COLUMN `weight` INT UNSIGNED DEFAULT NULL COMMENT 'Poids du produit en grammes' AFTER `base_price`;

-- Mettre un poids par défaut de 500g pour les produits existants
UPDATE `products` SET `weight` = 500 WHERE `weight` IS NULL;
