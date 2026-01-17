<?php
/**
 * PERSONNALY - API : Upsells applicables
 * Retourne les upsells applicables au contexte du panier
 *
 * Usage: /api/upsells.php?position=cart (ou checkout)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/models/Upsell.php';
require_once __DIR__ . '/../app/models/Product.php';

$position = $_GET['position'] ?? 'cart';

// Construire le contexte du panier
$cartItems = Cart::getItemsWithProducts();
$cartTotal = Cart::getTotal();

$products = [];
$techniques = [];
$categories = [];
$quantity = 0;

foreach ($cartItems as $item) {
    $products[] = $item['product_id'];
    $quantity += $item['quantity'];

    if (!empty($item['customization']['technique'])) {
        $techniques[] = $item['customization']['technique'];
    }

    // TODO: récupérer les catégories du produit
}

$context = [
    'cart_total' => $cartTotal,
    'products' => array_unique($products),
    'techniques' => array_unique($techniques),
    'categories' => array_unique($categories),
    'quantity' => $quantity
];

$upsellModel = new Upsell();
$productModel = new Product();

$applicableUpsells = $upsellModel->findApplicable($context, $position);

// Enrichir avec les données produits si nécessaire
$result = [];
foreach ($applicableUpsells as $upsell) {
    $data = [
        'id' => $upsell['id'],
        'name' => $upsell['name'],
        'display_title' => $upsell['display_title'] ?: $upsell['name'],
        'offer_label' => $upsell['offer_label'],
        'offer_type' => $upsell['offer_type'],
        'discount_type' => $upsell['discount_type'],
        'discount_value' => $upsell['discount_value'],
        'display_image' => $upsell['display_image'],
    ];

    // Si l'offre est un produit, récupérer ses infos
    if ($upsell['offer_type'] === 'produit' && !empty($upsell['offer_value'])) {
        $product = $productModel->findById((int) $upsell['offer_value']);
        if ($product) {
            $data['product'] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'image' => $product['image_front_url'],
            ];

            // Calculer le prix réduit
            if ($upsell['discount_type'] === 'pourcentage') {
                $data['product']['discounted_price'] = $product['price'] * (1 - $upsell['discount_value'] / 100);
            } elseif ($upsell['discount_type'] === 'montant_fixe') {
                $data['product']['discounted_price'] = max(0, $product['price'] - $upsell['discount_value']);
            }
        }
    }

    $result[] = $data;
}

echo json_encode([
    'success' => true,
    'context' => [
        'cart_total' => $cartTotal,
        'quantity' => $quantity
    ],
    'upsells' => $result
]);
