-- Migration: Changer le champ 'size' en 'available_sizes' (JSON) pour stocker plusieurs tailles par variante couleur
-- Date: 2026-01-18

-- 1. Ajouter la nouvelle colonne
ALTER TABLE product_color_images ADD COLUMN available_sizes TEXT DEFAULT NULL AFTER hex_code;

-- 2. Migrer les données existantes (convertir size en JSON array)
UPDATE product_color_images
SET available_sizes = CASE
    WHEN size IS NOT NULL AND size != '' THEN CONCAT('["', size, '"]')
    ELSE NULL
END;

-- 3. Supprimer l'ancienne colonne
ALTER TABLE product_color_images DROP COLUMN size;

-- 4. Ajouter une colonne 'size_group' aux options pour grouper les tailles
ALTER TABLE customization_options ADD COLUMN size_group VARCHAR(50) DEFAULT NULL AFTER type;

-- 5. Mettre à jour les tailles existantes avec des groupes
UPDATE customization_options SET size_group = 'Lettres' WHERE type = 'size' AND value IN ('XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL');
UPDATE customization_options SET size_group = 'Chiffres' WHERE type = 'size' AND value REGEXP '^[0-9]+$';
UPDATE customization_options SET size_group = 'Enfants' WHERE type = 'size' AND value LIKE '%ans%';

-- 6. Insérer les tailles de base si elles n'existent pas
INSERT IGNORE INTO customization_options (type, size_group, value, label, sort_order, active, created_at) VALUES
('size', 'Lettres', 'XS', 'XS', 1, 1, NOW()),
('size', 'Lettres', 'S', 'S', 2, 1, NOW()),
('size', 'Lettres', 'M', 'M', 3, 1, NOW()),
('size', 'Lettres', 'L', 'L', 4, 1, NOW()),
('size', 'Lettres', 'XL', 'XL', 5, 1, NOW()),
('size', 'Lettres', 'XXL', 'XXL', 6, 1, NOW()),
('size', 'Lettres', '3XL', '3XL', 7, 1, NOW()),
('size', 'Chiffres', '36', '36', 10, 1, NOW()),
('size', 'Chiffres', '38', '38', 11, 1, NOW()),
('size', 'Chiffres', '40', '40', 12, 1, NOW()),
('size', 'Chiffres', '42', '42', 13, 1, NOW()),
('size', 'Chiffres', '44', '44', 14, 1, NOW()),
('size', 'Chiffres', '46', '46', 15, 1, NOW()),
('size', 'Chiffres', '48', '48', 16, 1, NOW()),
('size', 'Enfants', '2 ans', '2 ans', 20, 1, NOW()),
('size', 'Enfants', '4 ans', '4 ans', 21, 1, NOW()),
('size', 'Enfants', '6 ans', '6 ans', 22, 1, NOW()),
('size', 'Enfants', '8 ans', '8 ans', 23, 1, NOW()),
('size', 'Enfants', '10 ans', '10 ans', 24, 1, NOW()),
('size', 'Enfants', '12 ans', '12 ans', 25, 1, NOW());
