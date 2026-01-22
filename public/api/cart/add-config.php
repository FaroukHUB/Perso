<?php
/**
 * PERSONNALY - API Ajout Panier (Editor V2)
 * POST /public/api/cart/add-config.php
 *
 * SÉCURITÉ : Le prix est RECALCULÉ côté backend.
 * Le frontend peut envoyer un prix estimé, mais le backend décide.
 */

header('Content-Type: application/json; charset=utf-8');
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

// Charger les dépendances
require_once __DIR__ . '/../../../app/core/Database.php';
require_once __DIR__ . '/../../../app/helpers/Cart.php';
require_once __DIR__ . '/../../../app/models/Product.php';
require_once __DIR__ . '/../../../app/models/CustomizationOption.php';

// Lire le JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Données JSON invalides']);
    exit;
}

// =========================================
// VALIDATION DES DONNÉES
// =========================================

$productId = isset($data['product_id']) ? (int) $data['product_id'] : 0;
$colorId = isset($data['color_id']) ? (int) $data['color_id'] : 0;
$size = $data['size'] ?? null;
$technique = $data['technique'] ?? 'dtg';
$view = $data['view'] ?? 'front';
$layers = $data['layers'] ?? [];
$quantity = isset($data['quantity']) ? max(1, (int) $data['quantity']) : 1;

// Valider product_id
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID produit invalide']);
    exit;
}

// Valider présence de layers
if (empty($layers)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Aucune personnalisation ajoutée']);
    exit;
}

// =========================================
// VÉRIFICATION PRODUIT
// =========================================

$productModel = new Product();
$product = $productModel->findById($productId);

if (!$product) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Produit introuvable']);
    exit;
}

if (!$product['active']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Produit non disponible']);
    exit;
}

$basePrice = (float) $product['base_price'];

// =========================================
// VÉRIFICATION TECHNIQUE + RÉCUPÉRATION PRIX
// =========================================

$optionModel = new CustomizationOption();
$techniques = $optionModel->getTechniques();

$techniquePrice = 0;
$techniqueValid = false;
$techniqueLabel = 'Standard';

foreach ($techniques as $tech) {
    if ($tech['value'] === $technique) {
        $techniquePrice = (float) ($tech['price'] ?? 0);
        $techniqueLabel = $tech['label'];
        $techniqueValid = true;
        break;
    }
}

// Si technique non trouvée, utiliser DTG par défaut (prix 0)
if (!$techniqueValid) {
    $technique = 'dtg';
    $techniquePrice = 0;
    $techniqueLabel = 'DTG';
}

// =========================================
// CALCUL DU PRIX FINAL (BACKEND = VÉRITÉ)
// =========================================

$calculatedPrice = $basePrice + $techniquePrice;

// =========================================
// CONSTRUCTION DE LA PERSONNALISATION
// =========================================

$customization = [
    'source' => 'editor-v2',
    'view' => $view,
    'color_id' => $colorId,
    'size' => $size,
    'technique' => [
        'value' => $technique,
        'label' => $techniqueLabel,
        'price' => $techniquePrice
    ],
    'layers' => $layers,
    'prices' => [
        'base' => $basePrice,
        'technique' => $techniquePrice,
        'total' => $calculatedPrice
    ],
    'created_at' => date('Y-m-d H:i:s')
];

// =========================================
// AJOUT AU PANIER
// =========================================

try {
    Cart::add($productId, $customization, $calculatedPrice, $quantity);

    echo json_encode([
        'ok' => true,
        'message' => 'Produit ajouté au panier',
        'calculated_price' => $calculatedPrice,
        'redirect' => '/public/cart.php'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erreur lors de l\'ajout au panier'
    ]);
}
