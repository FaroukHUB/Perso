<?php
/**
 * TEST DEBUG - Isoler le problème du freeze
 */

// Afficher TOUTES les erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- TEST 1: PHP démarre OK -->\n";
flush();

// Test 1: Charger les dépendances de base
try {
    require_once __DIR__ . '/../app/core/Auth.php';
    echo "<!-- TEST 2: Auth.php chargé OK -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR Auth: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/../app/helpers/functions.php';
    echo "<!-- TEST 3: functions.php chargé OK -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR functions: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/../app/core/Database.php';
    echo "<!-- TEST 4: Database.php chargé OK -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR Database: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/../app/models/CustomizationOption.php';
    echo "<!-- TEST 5: CustomizationOption.php chargé OK -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR CustomizationOption: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/../app/models/SizeGroup.php';
    echo "<!-- TEST 6: SizeGroup.php chargé OK -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR SizeGroup: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/../app/models/Size.php';
    echo "<!-- TEST 7: Size.php chargé OK -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR Size: " . $e->getMessage());
}

// Test 2: Instancier les modèles
try {
    $sizeModel = new Size();
    echo "<!-- TEST 8: Size() instancié OK -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR instanciation Size: " . $e->getMessage());
}

try {
    $sizeGroupModel = new SizeGroup();
    echo "<!-- TEST 9: SizeGroup() instancié OK -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR instanciation SizeGroup: " . $e->getMessage());
}

// Test 3: Appeler findAllGrouped()
echo "<!-- TEST 10: Appel findAllGrouped()... -->\n";
flush();

try {
    $startTime = microtime(true);
    $sizes = $sizeModel->findAllGrouped();
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    echo "<!-- TEST 11: findAllGrouped() OK en {$duration}ms - " . count($sizes) . " groupes -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR findAllGrouped: " . $e->getMessage());
}

// Test 4: Appeler findAllActive()
echo "<!-- TEST 12: Appel findAllActive()... -->\n";
flush();

try {
    $startTime = microtime(true);
    $sizeGroups = $sizeGroupModel->findAllActive();
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    echo "<!-- TEST 13: findAllActive() OK en {$duration}ms - " . count($sizeGroups) . " groupes -->\n";
    flush();
} catch (Exception $e) {
    die("ERREUR findAllActive: " . $e->getMessage());
}

echo "\n<!-- ========================================= -->\n";
echo "<!-- TOUS LES TESTS PASSENT! -->\n";
echo "<!-- LE PROBLÈME EST AILLEURS -->\n";
echo "<!-- ========================================= -->\n";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Debug Options</title>
</head>
<body>
    <h1>✅ TOUS LES TESTS PHP SONT OK!</h1>
    <p>Si tu vois ce message, le PHP fonctionne correctement.</p>
    <p>Le problème doit être ailleurs (Auth, Session, etc.)</p>

    <h2>Détails:</h2>
    <ul>
        <li>Groupes de tailles chargés: <?= count($sizes) ?></li>
        <li>Tailles actives: <?= count($sizeGroups) ?></li>
    </ul>

    <h3>Données brutes:</h3>
    <pre><?php print_r($sizes); ?></pre>
</body>
</html>
