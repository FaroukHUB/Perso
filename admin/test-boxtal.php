<?php
/**
 * Test de connexion Boxtal - Page de diagnostic
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Settings.php';

Auth::requireAdmin();

$settings = new Settings();
$credentials = $settings->getBoxtalCredentials();
$shipper = $settings->getShipperInfo();

echo "<h1>Test Boxtal API</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:15px;border-radius:8px;overflow:auto;}</style>";

// 1. Vérifier les credentials
echo "<h2>1. Credentials</h2>";
echo "<ul>";
echo "<li>Boxtal activé: " . ($settings->isBoxtalEnabled() ? '<span class="ok">Oui</span>' : '<span class="error">Non</span>') . "</li>";
echo "<li>Mode: " . htmlspecialchars($credentials['mode']) . "</li>";
echo "<li>Clé d'accès: " . (strlen($credentials['user']) > 10 ? '<span class="ok">' . substr($credentials['user'], 0, 10) . '...</span>' : '<span class="error">Manquante</span>') . "</li>";
echo "<li>Clé secrète: " . (strlen($credentials['api_key']) > 10 ? '<span class="ok">***' . substr($credentials['api_key'], -4) . '</span>' : '<span class="error">Manquante</span>') . "</li>";
echo "</ul>";

// 2. Vérifier les infos expéditeur
echo "<h2>2. Informations Expéditeur</h2>";
echo "<ul>";
echo "<li>Entreprise: " . (!empty($shipper['company']) ? '<span class="ok">' . htmlspecialchars($shipper['company']) . '</span>' : '<span class="error">Manquant</span>') . "</li>";
echo "<li>Adresse: " . (!empty($shipper['address']) ? '<span class="ok">' . htmlspecialchars($shipper['address']) . '</span>' : '<span class="error">Manquante</span>') . "</li>";
echo "<li>Code postal: " . (!empty($shipper['postcode']) ? '<span class="ok">' . htmlspecialchars($shipper['postcode']) . '</span>' : '<span class="error">Manquant</span>') . "</li>";
echo "<li>Ville: " . (!empty($shipper['city']) ? '<span class="ok">' . htmlspecialchars($shipper['city']) . '</span>' : '<span class="error">Manquante</span>') . "</li>";
echo "<li>Pays: " . (!empty($shipper['country']) ? '<span class="ok">' . htmlspecialchars($shipper['country']) . '</span>' : '<span class="error">Manquant</span>') . "</li>";
echo "</ul>";

// 3. Test API
echo "<h2>3. Test Appel API</h2>";

if (!$settings->isBoxtalEnabled()) {
    echo "<p class='error'>Boxtal n'est pas activé. Activez-le dans les paramètres.</p>";
} elseif (empty($credentials['user']) || empty($credentials['api_key'])) {
    echo "<p class='error'>Credentials manquants.</p>";
} elseif (empty($shipper['company']) || empty($shipper['postcode']) || empty($shipper['city'])) {
    echo "<p class='error'>Informations expéditeur incomplètes. Remplissez-les dans les paramètres.</p>";
} else {
    // Faire un appel test
    $apiUrl = $credentials['mode'] === 'live'
        ? 'https://api.boxtal.com/v3/shipping/quote'
        : 'https://api.boxtal.build/v3/shipping/quote';

    $payload = [
        'shipper' => [
            'company' => $shipper['company'],
            'street' => $shipper['address'],
            'city' => $shipper['city'],
            'zipCode' => $shipper['postcode'],
            'country' => $shipper['country'] ?: 'FR'
        ],
        'recipient' => [
            'firstName' => 'Test',
            'lastName' => 'Client',
            'street' => '1 rue de Paris',
            'city' => 'Paris',
            'zipCode' => '75001',
            'country' => 'FR'
        ],
        'parcels' => [
            ['weight' => 0.5, 'length' => 30, 'width' => 20, 'height' => 10]
        ],
        'orderValue' => 50
    ];

    echo "<h3>Requête envoyée à: " . htmlspecialchars($apiUrl) . "</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($credentials['user'] . ':' . $credentials['api_key']),
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "<h3>Réponse (HTTP $httpCode)</h3>";

    if ($curlError) {
        echo "<p class='error'>Erreur cURL: " . htmlspecialchars($curlError) . "</p>";
    } elseif ($httpCode !== 200) {
        echo "<p class='error'>Erreur HTTP $httpCode</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<p class='ok'>Succès!</p>";
        $data = json_decode($response, true);
        echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
    }
}

echo "<hr><p><a href='/admin/settings.php?tab=shipping'>← Retour aux paramètres</a></p>";
