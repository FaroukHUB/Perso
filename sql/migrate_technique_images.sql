-- PERSONNALY - Migration Images Techniques
-- Ajoute une colonne pour stocker les URLs des images de rendu réel par technique
-- À exécuter sur zajr1824_persosaas

-- Ajouter colonne images_json pour stocker les URLs des photos macro
ALTER TABLE customization_options
ADD COLUMN images_json TEXT NULL AFTER description;

-- Exemple de structure JSON attendue:
-- ["uploads/techniques/flex/macro1.jpg", "uploads/techniques/flex/macro2.jpg"]
