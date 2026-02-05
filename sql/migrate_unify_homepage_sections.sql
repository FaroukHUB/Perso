-- =====================================================================
-- MIGRATION : Unification homepage_sections → page_sections
-- Copie les sections de la page d'accueil depuis l'ancien système
-- (homepage_sections / homepage_section_items) vers le nouveau système
-- unifié (page_sections / page_section_items).
-- =====================================================================

-- Trouver l'ID de la page d'accueil
SET @home_id = (SELECT id FROM pages WHERE slug = 'home' LIMIT 1);

-- Si la page home n'existe pas, la créer
INSERT INTO pages (title, slug, meta_title, meta_description, status, is_system)
SELECT 'Accueil', 'home', 'PERSONNALY - Personnalisation textile',
       'Créez des vêtements uniques personnalisés', 'published', 1
FROM DUAL
WHERE @home_id IS NULL;

SET @home_id = COALESCE(@home_id, LAST_INSERT_ID());

-- Copier les sections homepage → page_sections (seulement si pas déjà migrées)
-- On vérifie l'absence de sections pour cette page avant de migrer
INSERT INTO page_sections (page_id, type, title, subtitle, content, cta_text, cta_url, media_type, media_url, config_json, sort_order, status, created_at, updated_at)
SELECT @home_id, hs.type, hs.title, hs.subtitle, hs.content,
       hs.cta_text, hs.cta_url, hs.media_type, hs.media_url,
       hs.config_json, hs.sort_order, hs.status, hs.created_at, hs.updated_at
FROM homepage_sections hs
WHERE NOT EXISTS (
    SELECT 1 FROM page_sections ps
    WHERE ps.page_id = @home_id
);

-- Copier les items en faisant le mapping ancien section_id → nouveau section_id
-- Le mapping se fait par (type, sort_order) qui est unique par page
INSERT INTO page_section_items (section_id, item_type, item_id, sort_order)
SELECT ps.id, hsi.item_type, hsi.item_id, hsi.sort_order
FROM homepage_section_items hsi
JOIN homepage_sections hs ON hsi.section_id = hs.id
JOIN page_sections ps ON ps.page_id = @home_id
    AND ps.type = hs.type
    AND ps.sort_order = hs.sort_order
WHERE NOT EXISTS (
    SELECT 1 FROM page_section_items psi
    WHERE psi.section_id = ps.id
    AND psi.item_type = hsi.item_type
    AND psi.item_id = hsi.item_id
);

-- Note: Les tables homepage_sections et homepage_section_items sont conservées
-- comme backup. Elles peuvent être supprimées après validation.
-- DROP TABLE IF EXISTS homepage_section_items;
-- DROP TABLE IF EXISTS homepage_sections;
