<?php
/**
 * PERSONNALY - Test de connexion à la base de données
 * SUPPRIMER CE FICHIER APRÈS LE TEST !
 */

require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Test de connexion MySQL ===\n\n";

try {
    $db = Database::getInstance();
    echo "✅ Connexion réussie !\n\n";

    // Test requête
    $stmt = $db->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo "Version MySQL: " . $result['version'] . "\n\n";

    // Vérifier les tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "⚠️ Aucune table trouvée.\n";
        echo "→ Exécuter sql/schema.sql dans phpMyAdmin\n";
    } else {
        echo "Tables existantes:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Erreur de connexion:\n";
    echo $e->getMessage() . "\n";
}

echo "\n=== Fin du test ===\n";
echo "\n⚠️ SUPPRIMER ce fichier après le test !\n";
