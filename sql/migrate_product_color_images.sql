-- ============================================
-- PERSONNALY - Migration: Images par coloris
-- Chaque variante couleur d'un produit peut avoir
-- ses propres images (face + dos)
-- ============================================

-- Table des variantes couleur avec images
CREATE TABLE IF NOT EXISTS product_color_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    color_name VARCHAR(50) NOT NULL,              -- "Noir", "Blanc", "Rose"
    hex_code VARCHAR(7) NOT NULL DEFAULT '#CCCCCC', -- "#1A1A2E"
    image_front_url VARCHAR(255) DEFAULT NULL,    -- Photo face de cette couleur
    image_back_url VARCHAR(255) DEFAULT NULL,     -- Photo dos de cette couleur (optionnel)
    is_default TINYINT(1) DEFAULT 0,              -- Variante affichée par défaut
    sort_order INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Contraintes
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_product_color (product_id, color_name),
    INDEX idx_product_sort (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- LOGIQUE D'UTILISATION
-- ============================================
--
-- Si product_color_images existe pour un produit:
--   → Afficher ces couleurs avec leurs images spécifiques
--   → Au clic sur une couleur, changer l'image du produit
--
-- Sinon (pas d'entrées pour ce produit):
--   → Utiliser les images par défaut du produit (image_front_url, image_back_url)
--   → Utiliser les couleurs de product_colors ou customization_options
--   → Le fond de la preview simule la couleur (comportement actuel)
-- ============================================
