-- Migration: Ajouter les colonnes Stripe et shipping_cost à la table orders

-- Ajouter la colonne pour stocker l'ID de session Stripe
ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_session_id VARCHAR(255) DEFAULT NULL AFTER notes;

-- Ajouter la colonne pour les frais de livraison
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(10,2) DEFAULT 0 AFTER total;

-- Index pour rechercher par session Stripe
CREATE INDEX IF NOT EXISTS idx_orders_stripe_session ON orders(stripe_session_id);

-- Mettre à jour le statut enum pour inclure pending_payment
-- Note: Si votre colonne status est un ENUM, vous devrez peut-être l'adapter manuellement
-- ALTER TABLE orders MODIFY COLUMN status ENUM('pending_payment', 'pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending';
