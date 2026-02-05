-- Migration: Add sale_price and badge to products
-- Permet de gérer les prix soldés et les badges personnalisables sur les produits

ALTER TABLE products ADD COLUMN sale_price DECIMAL(10,2) DEFAULT NULL AFTER base_price;
ALTER TABLE products ADD COLUMN badge VARCHAR(50) DEFAULT NULL AFTER sale_price;
ALTER TABLE products ADD COLUMN badge_color VARCHAR(7) DEFAULT '#FF1493' AFTER badge;
