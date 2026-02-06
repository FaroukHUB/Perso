-- Migration: Ajouter la colonne allow_direct_purchase à la table products
-- Date: 2026-02-06
-- Description: Permet de définir si un produit peut être acheté directement sans personnalisation

ALTER TABLE products
ADD COLUMN allow_direct_purchase TINYINT(1) NOT NULL DEFAULT 0
COMMENT 'Si 1, le produit peut être acheté directement sans personnalisation'
AFTER active;

-- Index pour optimiser les requêtes filtrant par allow_direct_purchase
ALTER TABLE products
ADD INDEX idx_allow_direct_purchase (allow_direct_purchase);

-- Par défaut, tous les produits existants nécessitent une personnalisation (0)
-- Si tu veux activer l'achat direct pour certains produits existants, exécute:
-- UPDATE products SET allow_direct_purchase = 1 WHERE id IN (1, 2, 3);
