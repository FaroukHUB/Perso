-- =============================================================================
-- PERSONNALY - Migration : Groupes de Tailles + Designs + Elements
-- Date : 2026-01-19
-- =============================================================================

-- =============================================================================
-- 1. GROUPES DE TAILLES
-- =============================================================================

-- Table des groupes de tailles
CREATE TABLE IF NOT EXISTS size_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des tailles (liee aux groupes)
CREATE TABLE IF NOT EXISTS sizes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(50) NOT NULL,
    size_group_id INT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (size_group_id) REFERENCES size_groups(id) ON DELETE CASCADE,
    INDEX idx_size_group (size_group_id),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des groupes par defaut
INSERT IGNORE INTO size_groups (name, sort_order) VALUES
    ('Lettres', 1),
    ('Chiffres', 2),
    ('Enfants', 3),
    ('Personnalise', 4);

-- Migration des tailles existantes depuis customization_options vers sizes
-- (uniquement si des tailles existent dans l'ancienne table)
INSERT INTO sizes (label, size_group_id, sort_order, active)
SELECT
    co.label,
    sg.id,
    co.sort_order,
    co.active
FROM customization_options co
JOIN size_groups sg ON sg.name = COALESCE(co.size_group, 'Personnalise')
WHERE co.type = 'size'
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- =============================================================================
-- 2. DESIGNS (IDEES CADEAUX)
-- =============================================================================

-- Table des categories de designs
CREATE TABLE IF NOT EXISTS design_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des designs
CREATE TABLE IF NOT EXISTS designs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES design_categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des categories de designs par defaut
INSERT IGNORE INTO design_categories (name, sort_order) VALUES
    ('Sport', 1),
    ('Anniversaire', 2),
    ('Noel', 3),
    ('Fete des meres', 4),
    ('Fete des peres', 5),
    ('Mariage', 6),
    ('Naissance', 7),
    ('Humour', 8);

-- =============================================================================
-- 3. ELEMENTS (CLIPARTS / FORMES)
-- =============================================================================

-- Table des categories d'elements
CREATE TABLE IF NOT EXISTS element_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des elements
CREATE TABLE IF NOT EXISTS elements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    is_premium TINYINT(1) DEFAULT 0,
    price DECIMAL(10, 2) DEFAULT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES element_categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_active (active),
    INDEX idx_premium (is_premium)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des categories d'elements par defaut
INSERT IGNORE INTO element_categories (name, sort_order) VALUES
    ('Formes', 1),
    ('Icones', 2),
    ('Animaux', 3),
    ('Nature', 4),
    ('Coeurs & Amour', 5),
    ('Etoiles & Magie', 6),
    ('Sport', 7),
    ('Musique', 8),
    ('Islamic', 9),
    ('Lettres decoratives', 10);

-- =============================================================================
-- FIN DE LA MIGRATION
-- =============================================================================

-- Note: Apres cette migration, les tailles sont dans la nouvelle table 'sizes'
-- Les anciennes tailles dans customization_options peuvent etre conservees
-- pour reference ou supprimees manuellement une fois la migration validee.
