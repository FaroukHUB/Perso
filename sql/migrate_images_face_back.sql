-- =============================================
-- PERSONNALY - Migration Images Face/Dos
-- Ajout support 2 images par produit
-- =============================================

SET NAMES utf8mb4;

-- Renommer image_url en image_front_url
ALTER TABLE `products`
    CHANGE COLUMN `image_url` `image_front_url` VARCHAR(255) DEFAULT NULL;

-- Ajouter colonne image_back_url
ALTER TABLE `products`
    ADD COLUMN `image_back_url` VARCHAR(255) DEFAULT NULL AFTER `image_front_url`;

-- =============================================
-- FIN - Exécuter dans phpMyAdmin
-- =============================================
