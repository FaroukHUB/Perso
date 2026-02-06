/**
 * Migration: Positionnement par produit + Filtrage intelligent des tailles
 *
 * Permet:
 * - Contrôle du positionnement du texte par produit (free/preset/fixed)
 * - Filtrage automatique des tailles selon la catégorie (ex: Bébé → tailles enfants uniquement)
 */

-- ============================================
-- 1. Ajouter positionnement par produit
-- ============================================

ALTER TABLE products
ADD COLUMN text_positioning_mode ENUM('free', 'preset', 'fixed') DEFAULT 'free' COMMENT 'Mode de positionnement du texte' AFTER available_sizes,
ADD COLUMN text_fixed_position_x INT DEFAULT 50 COMMENT 'Position X fixe (%)' AFTER text_positioning_mode,
ADD COLUMN text_fixed_position_y INT DEFAULT 40 COMMENT 'Position Y fixe (%)' AFTER text_fixed_position_x,
ADD COLUMN text_preset_zones JSON DEFAULT NULL COMMENT 'Zones prédéfinies JSON' AFTER text_fixed_position_y;

-- Valeurs par défaut pour les zones prédéfinies
UPDATE products
SET text_preset_zones = '[
    {"id":"center","label":"Centré","x":50,"y":50},
    {"id":"top_left","label":"Haut gauche","x":15,"y":15},
    {"id":"top_right","label":"Haut droite","x":85,"y":15},
    {"id":"bottom_left","label":"Bas gauche","x":15,"y":85},
    {"id":"bottom_right","label":"Bas droite","x":85,"y":85}
]'
WHERE text_preset_zones IS NULL;

-- ============================================
-- 2. Ajouter groupes de tailles par catégorie
-- ============================================

ALTER TABLE categories
ADD COLUMN allowed_size_groups JSON DEFAULT NULL COMMENT 'Groupes de tailles autorisés (Lettres, Chiffres, Enfants)' AFTER image_url;

-- Définir les groupes de tailles par défaut pour catégories existantes
UPDATE categories SET allowed_size_groups = '["Lettres", "Chiffres"]' WHERE slug IN ('homme', 'femme', 'unisexe');
UPDATE categories SET allowed_size_groups = '["Enfants"]' WHERE slug IN ('enfant', 'bebe');
UPDATE categories SET allowed_size_groups = '["Lettres", "Chiffres", "Enfants"]' WHERE slug IN ('accessoires', 'tous');

-- ============================================
-- Notes d'utilisation
-- ============================================

/**
 * POSITIONNEMENT DU TEXTE:
 * - free: Le client peut déplacer le texte où il veut (par défaut)
 * - preset: Le client choisit parmi des zones prédéfinies
 * - fixed: Position fixe imposée (pas de choix client)
 *
 * FILTRAGE DES TAILLES:
 * - Si une catégorie a allowed_size_groups = ["Enfants"]
 * - Seules les tailles du groupe "Enfants" s'afficheront dans le formulaire produit
 * - Si le produit appartient à plusieurs catégories, c'est l'union des groupes autorisés
 * - Si allowed_size_groups est NULL, toutes les tailles sont disponibles
 */
