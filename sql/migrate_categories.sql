-- PERSONNALY - Migration Catégories Produits
-- À exécuter sur zajr1824_persosaas

-- Table des catégories
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image_url VARCHAR(255),
    sort_order INT UNSIGNED DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison produits-catégories (relation N:N)
CREATE TABLE IF NOT EXISTS product_categories (
    product_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories par défaut (optionnel)
INSERT INTO categories (name, slug, description, sort_order, status) VALUES
('Homme', 'homme', 'Vêtements et accessoires pour homme', 1, 'active'),
('Femme', 'femme', 'Vêtements et accessoires pour femme', 2, 'active'),
('Enfant', 'enfant', 'Vêtements et accessoires pour enfant', 3, 'active'),
('Accessoires', 'accessoires', 'Casquettes, sacs, et autres accessoires', 4, 'active');
