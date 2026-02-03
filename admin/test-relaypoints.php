<?php
/**
 * Test des points relais Boxtal - Page de diagnostic
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Settings.php';
require_once __DIR__ . '/../app/services/BoxtalService.php';

Auth::requireAdmin();

$settings = new Settings();
$credentials = $settings->getBoxtalCredentials();
$boxtalService = new BoxtalService();

$carrier = $_GET['carrier'] ?? 'MONR';
$postcode = $_GET['postcode'] ?? '75001';

echo "<h1>Test Points Relais Boxtal</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:15px;border-radius:8px;overflow:auto;}</style>";

// Formulaire de test
echo "<form method='get' style='margin-bottom:20px;'>";
echo "<label>Transporteur: <input type='text' name='carrier' value='" . htmlspecialchars($carrier) . "' placeholder='MONR'></label> ";
echo "<label>Code postal: <input type='text' name='postcode' value='" . htmlspecialchars($postcode) . "' placeholder='75001'></label> ";
echo "<button type='submit'>Rechercher</button>";
echo "</form>";

echo "<p>Codes transporteurs point relais: MONR (Mondial Relay), SOGP (Relais Colis), UPSE (UPS Access Point), CHRP (Chronopost Relais), POFR (Colissimo)</p>";

if (!$settings->isBoxtalEnabled()) {
    echo "<p class='error'>Boxtal n'est pas activé. Activez-le dans les paramètres.</p>";
} elseif (empty($credentials['user']) || empty($credentials['api_key'])) {
    echo "<p class='error'>Credentials manquants.</p>";
} else {
    echo "<h2>Test API listpoints</h2>";

    // Test direct de l'API
    $apiBaseUrl = $credentials['mode'] === 'live'
        ? 'https://www.envoimoinscher.com/api/v1/'
        : 'https://test.envoimoinscher.com/api/v1/';

    $params = [
        'pays' => 'FR',
        'cp' => $postcode,
        'collecte' => 'retrait',
        'ope_code' => $carrier,
    ];

    $url = $apiBaseUrl . 'listpoints?' . http_build_query($params);

    echo "<p><strong>URL:</strong> " . htmlspecialchars($url) . "</p>";
    echo "<p><strong>Paramètres:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($params, JSON_PRETTY_PRINT)) . "</pre>";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($credentials['user'] . ':' . $credentials['api_key']),
            'Accept: application/xml'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "<h3>Réponse HTTP $httpCode</h3>";

    if ($curlError) {
        echo "<p class='error'>Erreur cURL: " . htmlspecialchars($curlError) . "</p>";
    } elseif ($httpCode !== 200) {
        echo "<p class='error'>Erreur HTTP</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<h4>Réponse XML brute:</h4>";
        echo "<pre style='max-height:400px;overflow:auto'>" . htmlspecialchars($response) . "</pre>";

        // Parser le XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml !== false) {
            echo "<h4>Structure XML:</h4>";
            echo "<pre>" . htmlspecialchars(print_r($xml, true)) . "</pre>";
        }
    }

    // Test via le service
    echo "<hr><h2>Test via BoxtalService</h2>";
    $points = $boxtalService->getRelayPoints($carrier, $postcode);
    echo "<p>Nombre de points trouvés: " . count($points) . "</p>";
    if (!empty($points)) {
        echo "<pre>" . htmlspecialchars(json_encode($points, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
    }
}

echo "<hr><p><a href='/admin/settings.php?tab=shipping'>← Retour aux paramètres</a></p>";
