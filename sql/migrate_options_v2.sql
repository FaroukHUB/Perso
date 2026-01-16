-- =============================================
-- PERSONNALY - Migration Options v2
-- Ajout techniques de personnalisation avec prix
-- =============================================

SET NAMES utf8mb4;

-- Ajouter les nouvelles colonnes
ALTER TABLE `customization_options`
    ADD COLUMN `price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix additionnel (pour techniques)' AFTER `hex_code`,
    ADD COLUMN `description` TEXT DEFAULT NULL COMMENT 'Description détaillée' AFTER `price`;

-- Modifier l'ENUM pour les nouveaux types
ALTER TABLE `customization_options`
    MODIFY COLUMN `type` ENUM('size', 'color', 'text_color', 'technique') NOT NULL;

-- Supprimer les anciennes données position et font (maintenant gérées ailleurs)
DELETE FROM `customization_options` WHERE `type` IN ('position', 'font');

-- ---------------------------------------------
-- Données par défaut : Couleurs de texte
-- ---------------------------------------------
INSERT INTO `customization_options` (`type`, `value`, `label`, `hex_code`, `sort_order`, `active`) VALUES
('text_color', 'noir', 'Noir', '#1A1A2E', 1, 1),
('text_color', 'blanc', 'Blanc', '#FFFFFF', 2, 1),
('text_color', 'or', 'Doré', '#D4AF37', 3, 1),
('text_color', 'argent', 'Argenté', '#C0C0C0', 4, 1),
('text_color', 'rose', 'Rose', '#FF69B4', 5, 1),
('text_color', 'bleu', 'Bleu Marine', '#1E3A5F', 6, 1),
('text_color', 'rouge', 'Rouge', '#DC143C', 7, 1),
('text_color', 'vert', 'Vert Forêt', '#228B22', 8, 1);

-- ---------------------------------------------
-- Données par défaut : Techniques de personnalisation
-- ---------------------------------------------
INSERT INTO `customization_options` (`type`, `value`, `label`, `price`, `description`, `sort_order`, `active`) VALUES
('technique', 'broderie', 'Broderie', 8.00, 'Technique premium, très durable. Idéal pour logos et textes élégants. Rendu haut de gamme avec du fil.', 1, 1),
('technique', 'flocage', 'Flocage (Flex)', 5.00, 'Transfert vinyle découpé. Parfait pour noms, numéros et textes simples. Finition mate ou brillante.', 2, 1),
('technique', 'serigraphie', 'Sérigraphie', 4.00, 'Impression par pochoir. Économique pour grandes quantités. Couleurs vives et durables.', 3, 1),
('technique', 'dtg', 'Impression Numérique (DTG)', 6.00, 'Impression directe sur textile. Idéal pour designs complexes et photos. Dégradés possibles.', 4, 1),
('technique', 'sublimation', 'Sublimation', 7.00, 'Impression par transfert de chaleur. Pour polyester uniquement. Couleurs éclatantes, très durable.', 5, 1),
('technique', 'transfert', 'Transfert Classique', 3.00, 'Solution économique. Bon rapport qualité/prix pour petites séries.', 6, 1);

-- =============================================
-- FIN - Exécuter dans phpMyAdmin
-- =============================================
