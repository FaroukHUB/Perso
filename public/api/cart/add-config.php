<?php
/**
 * PERSONNALY - API Ajout Panier (Editor V2)
 * POST /public/api/cart/add-config.php
 *
 * Reçoit la configuration produit depuis l'éditeur V2
 * et l'ajoute au panier existant via Cart::add()
 */

header('Content-Type: application/json');

// Autoriser CORS pour dev local
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Charger le helper Cart
require_once __DIR__ . '/../../../app/helpers/Cart.php';

// Lire le JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Données invalides']);
    exit;
}

// Validation minimale
$productId = isset($data['productId']) ? (int) $data['productId'] : 0;
$productName = $data['productName'] ?? 'Produit personnalisé';
$price = isset($data['price']) ? (float) $data['price'] : 0;
$technique = $data['technique'] ?? 'dtg';
$view = $data['view'] ?? 'front';
$layers = $data['layers'] ?? [];

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID produit invalide']);
    exit;
}

if ($price <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Prix invalide']);
    exit;
}

if (empty($layers)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Aucune personnalisation']);
    exit;
}

// Construire la personnalisation
$customization = [
    'source' => 'editor-v2',
    'view' => $view,
    'technique' => $technique,
    'layers' => $layers,
    'created_at' => date('Y-m-d H:i:s')
];

// Ajouter au panier
try {
    Cart::add($productId, $customization, $price, 1);

    echo json_encode([
        'ok' => true,
        'message' => 'Produit ajouté au panier',
        'redirect' => '/public/cart.php'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
