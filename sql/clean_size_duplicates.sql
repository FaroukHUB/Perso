-- Nettoyer les doublons dans la table sizes
-- Garde seulement la taille avec l'ID le plus élevé pour chaque (label, size_group_id)

-- Voir les doublons AVANT nettoyage
SELECT
    size_group_id,
    label,
    COUNT(*) as count,
    GROUP_CONCAT(id ORDER BY id) as ids
FROM sizes
GROUP BY size_group_id, label
HAVING COUNT(*) > 1
ORDER BY size_group_id, label;

-- ATTENTION: Sauvegarde avant d'exécuter la suppression!
-- Pour supprimer les doublons (garde l'ID le plus élevé):

DELETE s1 FROM sizes s1
INNER JOIN sizes s2
WHERE
    s1.size_group_id = s2.size_group_id
    AND s1.label = s2.label
    AND s1.id < s2.id;

-- Vérifier après nettoyage (devrait retourner 0 lignes):
SELECT
    size_group_id,
    label,
    COUNT(*) as count
FROM sizes
GROUP BY size_group_id, label
HAVING COUNT(*) > 1;

-- Ajouter une contrainte UNIQUE pour éviter les futurs doublons:
ALTER TABLE sizes
ADD UNIQUE KEY unique_size_per_group (size_group_id, label);
