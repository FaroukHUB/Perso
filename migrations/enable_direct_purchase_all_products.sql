-- Migration: Activer l'achat direct pour tous les produits
-- Date: 2026-02-08
-- Description: Active allow_direct_purchase pour tous les produits existants

-- Activer l'achat direct pour tous les produits
UPDATE products
SET allow_direct_purchase = 1
WHERE allow_direct_purchase = 0;

-- Changer la valeur par défaut pour les futurs produits
ALTER TABLE products
MODIFY COLUMN allow_direct_purchase TINYINT(1) NOT NULL DEFAULT 1
COMMENT 'Si 1, le produit peut être acheté directement sans personnalisation';
