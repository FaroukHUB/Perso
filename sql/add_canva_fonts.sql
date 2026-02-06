-- =============================================
-- PERSONNALY - Ajout des polices populaires Canva
-- Polices Google Fonts utilisées sur Canva
-- =============================================
-- Date: 2026-02-05
-- =============================================

SET NAMES utf8mb4;

-- Insérer les polices populaires Canva
INSERT INTO `fonts` (`name`, `family`, `css_key`, `source`, `google_weights`, `google_import_url`, `category`, `active`, `sort_order`) VALUES
('Amsterdam', 'Amsterdam', 'amsterdam_400', 'google', '400', 'https://fonts.googleapis.com/css2?family=Amsterdam&display=swap', 'script', 1, 10),
('Montserrat', 'Montserrat', 'montserrat_400_600_700', 'google', '400;600;700', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap', 'sans-serif', 1, 11),
('Raleway', 'Raleway', 'raleway_400_600_700', 'google', '400;600;700', 'https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700&display=swap', 'sans-serif', 1, 12),
('Open Sans', 'Open Sans', 'open_sans_400_600_700', 'google', '400;600;700', 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap', 'sans-serif', 1, 13),
('Lato', 'Lato', 'lato_400_700', 'google', '400;700', 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap', 'sans-serif', 1, 14),
('Roboto', 'Roboto', 'roboto_400_500_700', 'google', '400;500;700', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap', 'sans-serif', 1, 15),
('Oswald', 'Oswald', 'oswald_400_600_700', 'google', '400;600;700', 'https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&display=swap', 'sans-serif', 1, 16),
('Merriweather', 'Merriweather', 'merriweather_400_700', 'google', '400;700', 'https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap', 'serif', 1, 17),
('Source Sans Pro', 'Source Sans Pro', 'source_sans_pro_400_600_700', 'google', '400;600;700', 'https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap', 'sans-serif', 1, 18),
('Bebas Neue', 'Bebas Neue', 'bebas_neue_400', 'google', '400', 'https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap', 'display', 1, 19),
('Nunito', 'Nunito', 'nunito_400_600_700', 'google', '400;600;700', 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap', 'sans-serif', 1, 20),
('Ubuntu', 'Ubuntu', 'ubuntu_400_500_700', 'google', '400;500;700', 'https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap', 'sans-serif', 1, 21),
('Quicksand', 'Quicksand', 'quicksand_400_600_700', 'google', '400;600;700', 'https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600;700&display=swap', 'sans-serif', 1, 22),
('Lobster', 'Lobster', 'lobster_400', 'google', '400', 'https://fonts.googleapis.com/css2?family=Lobster&display=swap', 'display', 1, 23),
('Dancing Script', 'Dancing Script', 'dancing_script_400_700', 'google', '400;700', 'https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap', 'handwriting', 1, 24),
('Pacifico', 'Pacifico', 'pacifico_400', 'google', '400', 'https://fonts.googleapis.com/css2?family=Pacifico&display=swap', 'handwriting', 1, 25);

-- Vérification
SELECT id, name, family, category, active FROM fonts ORDER BY sort_order;
