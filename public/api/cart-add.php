<?php
/**
 * PERSONNALY - API Ajout Panier Direct (Sans personnalisation)
 * POST /public/api/cart-add.php
 *
 * Permet d'ajouter un produit au panier sans personnalisation.
 * SÉCURITÉ : Le prix est TOUJOURS récupéré depuis la BDD.
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
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Charger les dépendances
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/models/Product.php';

// Lire le JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
    exit;
}

// =========================================
// VALIDATION DES DONNÉES
// =========================================

$productId = isset($data['product_id']) ? (int) $data['product_id'] : 0;
$quantity = isset($data['quantity']) ? max(1, (int) $data['quantity']) : 1;

// Valider product_id
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
    exit;
}

// =========================================
// VÉRIFICATION PRODUIT
// =========================================

$productModel = new Product();
$product = $productModel->findById($productId);

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
    exit;
}

if (!$product['active']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Produit non disponible']);
    exit;
}

// Vérifier si le produit autorise l'achat direct
if (empty($product['allow_direct_purchase'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ce produit nécessite une personnalisation']);
    exit;
}

// =========================================
// CALCUL DU PRIX (TOUJOURS DEPUIS BDD)
// =========================================

// Utiliser le prix soldé si disponible, sinon le prix de base
$finalPrice = !empty($product['sale_price']) && $product['sale_price'] > 0 && $product['sale_price'] < $product['base_price']
    ? (float) $product['sale_price']
    : (float) $product['base_price'];

// =========================================
// AJOUT AU PANIER
// =========================================

$cartItem = [
    'product_id' => $productId,
    'product_name' => $product['name'],
    'product_image' => $product['image_front_url'] ?? null,
    'quantity' => $quantity,
    'price' => $finalPrice,
    'type' => 'direct_purchase', // Type spécial pour achat direct
    'direct_purchase' => true,
    'size' => null, // Pas de taille pour achat direct standard
    'color' => null,
    'technique' => null,
    'layers' => [], // Pas de personnalisation
];

try {
    Cart::add($cartItem);
    $cartCount = Cart::count();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Produit ajouté au panier',
        'cart_count' => $cartCount,
        'item' => [
            'product_name' => $product['name'],
            'price' => $finalPrice,
            'quantity' => $quantity
        ]
    ]);
} catch (Exception $e) {
    error_log('Erreur ajout panier direct: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'ajout au panier'
    ]);
}
