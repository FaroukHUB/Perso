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
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:15px;border-radius:8px;overflow:auto;max-height:400px;}</style>";

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
    $apiBaseUrl = $credentials['mode'] === 'live'
        ? 'https://www.envoimoinscher.com/api/v1/'
        : 'https://test.envoimoinscher.com/api/v1/';

    // Tester plusieurs endpoints
    $endpoints = ['parcelshop', 'listpoints', 'points'];

    foreach ($endpoints as $endpoint) {
        echo "<h2>Test API: $endpoint</h2>";

        $params = [
            'pays' => 'FR',
            'cp' => $postcode,
            'collecte' => 'retrait',
            'ope_code' => $carrier,
        ];

        $url = $apiBaseUrl . $endpoint . '?' . http_build_query($params);

        echo "<p><strong>URL:</strong> <code>" . htmlspecialchars($url) . "</code></p>";

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

        echo "<p><strong>HTTP Code:</strong> $httpCode</p>";

        if ($curlError) {
            echo "<p class='error'>Erreur cURL: " . htmlspecialchars($curlError) . "</p>";
        } elseif ($httpCode === 404) {
            echo "<p class='error'>Endpoint non trouvé (404)</p>";
        } elseif ($httpCode !== 200) {
            echo "<p class='error'>Erreur HTTP $httpCode</p>";
            if (!empty($response)) {
                echo "<pre>" . htmlspecialchars(substr($response, 0, 1000)) . "</pre>";
            }
        } else {
            echo "<p class='ok'>Succès!</p>";
            echo "<h4>Réponse:</h4>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
        echo "<hr>";
    }

    // Test via le service
    echo "<h2>Test via BoxtalService->getRelayPoints()</h2>";
    $points = $boxtalService->getRelayPoints($carrier, $postcode);
    echo "<p>Nombre de points trouvés: <strong>" . count($points) . "</strong></p>";
    if (!empty($points)) {
        echo "<pre>" . htmlspecialchars(json_encode($points, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
    } else {
        echo "<p class='error'>Aucun point relais trouvé</p>";
    }
}

echo "<hr><p><a href='/admin/settings.php?tab=shipping'>← Retour aux paramètres</a></p>";
