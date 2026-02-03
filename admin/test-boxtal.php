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
    // Faire un appel test avec l'API v1 (EnvoiMoinsCher)
    $apiBaseUrl = $credentials['mode'] === 'live'
        ? 'https://www.envoimoinscher.com/api/v1/'
        : 'https://test.envoimoinscher.com/api/v1/';

    $params = [
        'expediteur.type' => 'entreprise',
        'expediteur.pays' => $shipper['country'] ?: 'FR',
        'expediteur.code_postal' => $shipper['postcode'],
        'expediteur.ville' => $shipper['city'],
        'expediteur.adresse' => $shipper['address'],
        'expediteur.civilite' => 'M',
        'expediteur.prenom' => 'Service',
        'expediteur.nom' => 'Expedition',
        'destinataire.type' => 'particulier',
        'destinataire.pays' => 'FR',
        'destinataire.code_postal' => '75001',
        'destinataire.ville' => 'Paris',
        'destinataire.adresse' => '1 rue de Paris',
        'destinataire.civilite' => 'M',
        'destinataire.prenom' => 'Client',
        'destinataire.nom' => 'Test',
        'colis_1.poids' => 0.5,
        'colis_1.longueur' => 30,
        'colis_1.largeur' => 20,
        'colis_1.hauteur' => 10,
        'code_contenu' => 10120,
        'collecte' => date('Y-m-d', strtotime('+1 day')),
    ];

    $apiUrl = $apiBaseUrl . 'cotation?' . http_build_query($params);

    echo "<h3>API v1 (EnvoiMoinsCher)</h3>";
    echo "<p>URL: " . htmlspecialchars($apiBaseUrl . 'cotation') . "</p>";
    echo "<h4>Paramètres:</h4>";
    echo "<pre>" . htmlspecialchars(json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($credentials['user'] . ':' . $credentials['api_key']),
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
        echo "<p class='ok'>Succès! Connexion API fonctionnelle.</p>";

        // L'API v1 retourne du XML
        if (!empty($response)) {
            // Essayer de parser le XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response);

            if ($xml !== false) {
                echo "<h4>Transporteurs disponibles:</h4>";
                echo "<ul>";
                $count = 0;
                foreach ($xml->shipment as $shipment) {
                    $carrier = (string)$shipment->offer->operator->label;
                    $service = (string)$shipment->offer->service->label;
                    $price = (string)$shipment->offer->price['tax-inclusive'];
                    echo "<li><strong>$carrier</strong> - $service : " . number_format((float)$price, 2, ',', ' ') . " €</li>";
                    $count++;
                    if ($count >= 10) {
                        echo "<li>... et plus</li>";
                        break;
                    }
                }
                echo "</ul>";

                echo "<h4>Réponse XML brute:</h4>";
                echo "<pre style='max-height:300px;overflow:auto'>" . htmlspecialchars($response) . "</pre>";
            } else {
                echo "<h4>Réponse brute:</h4>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
        } else {
            echo "<p>Réponse vide - vérifiez vos paramètres</p>";
        }
    }
}

echo "<hr><p><a href='/admin/settings.php?tab=shipping'>← Retour aux paramètres</a></p>";
