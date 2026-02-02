<?php
/**
 * PERSONNALY - Page Panier
 * Design: Ultra-moderne 2026 • Girly • Rose + Vert Menthe + Noir
 * Refonte complète avec upsells, code promo, livraison, paiement
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/ProductUpsell.php';
require_once __DIR__ . '/../app/models/PromoCode.php';
require_once __DIR__ . '/../app/services/BoxtalService.php';

$success = '';
$error = '';

// AJAX: Validation code promo
if (isset($_GET['ajax']) && $_GET['ajax'] === 'validate_promo') {
    header('Content-Type: application/json');

    $code = trim($_POST['code'] ?? '');
    $cartTotal = Cart::getTotal();

    $promoModel = new PromoCode();
    $result = $promoModel->validateCode($code, $cartTotal);

    if ($result['valid']) {
        $_SESSION['promo_code'] = [
            'id' => $result['promo']['id'],
            'code' => $result['promo']['code'],
            'name' => $result['promo']['name'],
            'discount_type' => $result['promo']['discount_type'],
            'discount' => $result['discount'],
            'free_shipping' => $result['promo']['discount_type'] === 'free_shipping'
        ];
        echo json_encode([
            'success' => true,
            'message' => 'Code promo appliqué !',
            'discount' => $result['discount'],
            'discount_label' => $promoModel->getDiscountLabel($result['promo']),
            'free_shipping' => $result['promo']['discount_type'] === 'free_shipping'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['error']
        ]);
    }
    exit;
}

// AJAX: Retirer code promo
if (isset($_GET['ajax']) && $_GET['ajax'] === 'remove_promo') {
    header('Content-Type: application/json');
    unset($_SESSION['promo_code']);
    echo json_encode(['success' => true]);
    exit;
}

// Récupérer le code promo actuel en session
$appliedPromo = $_SESSION['promo_code'] ?? null;

// Service Boxtal pour les options de livraison
$boxtalService = new BoxtalService();

// AJAX: Changer méthode livraison
if (isset($_GET['ajax']) && $_GET['ajax'] === 'set_shipping') {
    header('Content-Type: application/json');
    $method = $_POST['method'] ?? 'standard';

    // Construire les options de livraison pour validation
    $ajaxCartItems = Cart::getItemsWithProducts();
    $ajaxCartTotal = Cart::getTotal();
    $ajaxCartWeight = $boxtalService->calculateCartWeight($ajaxCartItems);
    $ajaxRecipient = ['address' => '', 'city' => 'Paris', 'postcode' => '75001', 'country' => 'FR'];
    $ajaxRates = $boxtalService->getShippingRates($ajaxRecipient, $ajaxCartWeight, $ajaxCartTotal);

    $ajaxShippingOptions = [];
    foreach ($ajaxRates as $rate) {
        $ajaxShippingOptions[$rate['id']] = ['price' => $rate['price']];
    }

    if (isset($ajaxShippingOptions[$method])) {
        $_SESSION['shipping_method'] = $method;
        echo json_encode(['success' => true, 'price' => $ajaxShippingOptions[$method]['price']]);
    } else {
        // Accepter quand même si c'est une méthode standard/express de fallback
        $_SESSION['shipping_method'] = $method;
        echo json_encode(['success' => true, 'price' => 0]);
    }
    exit;
}

// Actions sur le panier
if (isPost()) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        if (isset($_POST['update_qty'])) {
            $itemKey = post('item_key', '');
            $quantity = (int) post('quantity', 1);
            Cart::updateQuantity($itemKey, $quantity);
            $success = 'Quantité mise à jour.';
        }

        if (isset($_POST['remove_item'])) {
            $itemKey = post('item_key', '');
            Cart::remove($itemKey);
            $success = 'Article supprimé du panier.';
        }

        if (isset($_POST['clear_cart'])) {
            Cart::clear();
            $success = 'Panier vidé.';
        }
    } else {
        $error = 'Session expirée. Veuillez réessayer.';
    }
}

$cartItems = Cart::getItemsWithProducts();
$cartTotal = Cart::getTotal();
$cartCount = Cart::count();

// Calculer le poids total du panier pour BoxtalService
$cartWeight = $boxtalService->calculateCartWeight($cartItems);

// Préparer l'adresse du destinataire (adresse par défaut pour l'estimation)
$defaultRecipient = [
    'address' => '',
    'city' => 'Paris',
    'postcode' => '75001',
    'country' => 'FR'
];

// Récupérer les tarifs de livraison dynamiques
$shippingRates = $boxtalService->getShippingRates($defaultRecipient, $cartWeight, $cartTotal);

// Convertir en format compatible
$shippingOptions = [];
foreach ($shippingRates as $rate) {
    $shippingOptions[$rate['id']] = [
        'label' => $rate['label'],
        'price' => $rate['price'],
        'delay' => $rate['delay'],
        'description' => $rate['description'] ?? '',
        'is_relay' => $rate['is_relay'] ?? false,
        'logo' => $rate['logo'] ?? ''
    ];
}

// Fallback si aucune option disponible
if (empty($shippingOptions)) {
    $shippingOptions = [
        'standard' => ['label' => 'Livraison standard', 'price' => 4.90, 'delay' => '3-5 jours ouvrés'],
        'express' => ['label' => 'Livraison express', 'price' => 9.90, 'delay' => '24-48h']
    ];
}

$selectedShipping = $_SESSION['shipping_method'] ?? array_key_first($shippingOptions);

// Vérifier que la méthode sélectionnée existe toujours
if (!isset($shippingOptions[$selectedShipping])) {
    $selectedShipping = array_key_first($shippingOptions);
    $_SESSION['shipping_method'] = $selectedShipping;
}

// Frais de livraison
$shippingCost = $shippingOptions[$selectedShipping]['price'];
if ($appliedPromo && !empty($appliedPromo['free_shipping'])) {
    $shippingCost = 0;
}

// Récupérer les suggestions de produits (upsells)
$upsellSuggestions = [];
$upsellSettings = [];
if (!Cart::isEmpty()) {
    $upsellModel = new ProductUpsell();
    $upsellSettings = $upsellModel->getSettings();

    if (($upsellSettings['enabled'] ?? '1') === '1' && ($upsellSettings['show_on_cart'] ?? '1') === '1') {
        $cartProductIds = [];
        foreach ($cartItems as $item) {
            $cartProductIds[] = $item['product_id'];
        }
        $cartProductIds = array_unique($cartProductIds);

        $upsellSuggestions = $upsellModel->getSuggestions(
            $cartProductIds,
            (int) ($upsellSettings['max_items'] ?? 4)
        );
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre Panier - PERSONNALY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.3);
        }

        body {
            background: linear-gradient(135deg, #fdf2f8 0%, #f0fdf9 50%, #fdf2f8 100%);
            min-height: 100vh;
        }

        /* ============ NAVBAR ============ */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(13, 13, 13, 0.95);
            backdrop-filter: blur(20px);
            padding: 15px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand {
            font-family: var(--font-display);
            font-size: 1.6rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        .navbar-actions a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        .navbar-actions a:hover {
            color: var(--pink-main);
        }
        .nav-cart-icon {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--gradient-pink);
            border-radius: var(--radius-full);
            color: white;
            font-weight: 600;
            font-size: 13px;
        }

        /* ============ PAGE HEADER ============ */
        .cart-page {
            padding: 40px 0 80px;
        }
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .page-title {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--black-soft);
            margin-bottom: 10px;
        }
        .page-title span {
            background: var(--gradient-pink);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .page-subtitle {
            color: var(--gray);
            font-size: 15px;
        }

        /* ============ ALERTS ============ */
        .alert {
            padding: 16px 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success {
            background: linear-gradient(135deg, rgba(61, 255, 192, 0.15), rgba(61, 255, 192, 0.05));
            color: var(--mint-dark);
            border: 1px solid rgba(61, 255, 192, 0.3);
        }
        .alert-error {
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.15), rgba(255, 105, 180, 0.05));
            color: var(--pink-dark);
            border: 1px solid rgba(255, 105, 180, 0.3);
        }

        /* ============ CART LAYOUT ============ */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 30px;
            align-items: start;
        }

        /* ============ CART ITEMS ============ */
        .cart-items-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .cart-items {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            border: 1px solid var(--glass-border);
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 25px;
            background: linear-gradient(135deg, rgba(255,105,180,0.05), rgba(61,255,192,0.05));
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        .cart-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cart-count-badge {
            background: var(--gradient-pink);
            color: white;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 700;
        }
        .clear-btn {
            color: var(--gray);
            font-size: 13px;
            cursor: pointer;
            border: none;
            background: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .clear-btn:hover {
            color: #dc3545;
        }

        /* ============ CART ITEM ============ */
        .cart-item {
            display: grid;
            grid-template-columns: 110px 1fr auto;
            gap: 20px;
            padding: 25px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            transition: background 0.2s;
        }
        .cart-item:hover {
            background: rgba(255,105,180,0.02);
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .item-image {
            width: 110px;
            height: 110px;
            background: linear-gradient(135deg, #f8f8f8, #f0f0f0);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .item-image:hover {
            border-color: var(--pink-main);
            transform: scale(1.02);
        }
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 8px;
        }
        .item-image .zoom-icon {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: var(--gradient-pink);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.2s;
        }
        .item-image:hover .zoom-icon {
            opacity: 1;
            transform: scale(1);
        }

        .item-details {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .item-details h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 10px;
        }
        .item-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }
        .item-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: white;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: var(--radius-full);
            font-size: 12px;
            color: var(--gray);
            font-weight: 500;
        }
        .item-tag strong {
            color: var(--black-soft);
        }
        .color-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.15);
        }
        .item-text-preview {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: linear-gradient(135deg, rgba(255,105,180,0.1), rgba(255,105,180,0.05));
            border-radius: var(--radius-md);
            font-size: 13px;
            color: var(--pink-dark);
            font-weight: 600;
            max-width: fit-content;
        }
        .item-unit-price {
            font-size: 14px;
            color: var(--gray);
            margin-top: 8px;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            min-width: 140px;
        }
        .item-subtotal {
            font-family: var(--font-display);
            font-size: 1.4rem;
            font-weight: 800;
            background: var(--gradient-pink);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Quantity Control */
        .qty-control {
            display: flex;
            align-items: center;
            background: white;
            border: 2px solid #e8e8e8;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .qty-btn {
            width: 38px;
            height: 38px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray);
            transition: all 0.2s;
        }
        .qty-btn:hover {
            background: var(--pink-light);
            color: var(--pink-dark);
        }
        .qty-value {
            width: 50px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            border-left: 1px solid #e8e8e8;
            border-right: 1px solid #e8e8e8;
        }

        .remove-btn {
            margin-top: 12px;
            color: var(--gray);
            font-size: 12px;
            cursor: pointer;
            border: none;
            background: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .remove-btn:hover {
            color: #dc3545;
        }

        /* ============ UPSELLS SECTION ============ */
        .upsells-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            border: 1px solid var(--glass-border);
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        .upsells-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .upsells-title svg {
            color: var(--pink-main);
        }
        .upsells-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .upsell-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 16px;
            border: 2px solid transparent;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .upsell-card:hover {
            border-color: var(--pink-light);
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(255,105,180,0.15);
        }
        .upsell-image {
            width: 100%;
            height: 120px;
            background: linear-gradient(135deg, #f8f8f8, #f0f0f0);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .upsell-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .upsell-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: var(--gradient-mint);
            color: var(--black-soft);
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .upsell-name {
            font-weight: 700;
            font-size: 14px;
            color: var(--black-soft);
        }
        .upsell-price {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .upsell-price .current {
            font-weight: 800;
            font-size: 16px;
            color: var(--pink-dark);
        }
        .upsell-price .original {
            font-size: 13px;
            color: var(--gray);
            text-decoration: line-through;
        }
        .upsell-cta {
            display: block;
            width: 100%;
            padding: 10px;
            background: var(--gradient-pink);
            color: white;
            text-align: center;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        .upsell-cta:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(255,105,180,0.4);
        }

        /* ============ CART SUMMARY ============ */
        .cart-summary {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            border: 1px solid var(--glass-border);
            padding: 30px;
            position: sticky;
            top: 100px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        .summary-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--black-soft);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Promo Code */
        .promo-section {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .promo-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 12px;
        }
        .promo-input-group {
            display: flex;
            gap: 10px;
        }
        .promo-input-group input {
            flex: 1;
            padding: 14px 16px;
            border: 2px solid #e8e8e8;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.2s;
        }
        .promo-input-group input:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255,105,180,0.1);
        }
        .promo-apply-btn {
            padding: 14px 20px;
            background: var(--gradient-mint);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 13px;
            color: var(--black-soft);
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .promo-apply-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(61,255,192,0.4);
        }
        .promo-apply-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .promo-error {
            margin-top: 10px;
            font-size: 12px;
            color: #dc3545;
            display: none;
        }
        .promo-error.show {
            display: block;
        }

        .promo-applied {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: linear-gradient(135deg, rgba(61,255,192,0.15), rgba(61,255,192,0.05));
            border: 2px dashed var(--mint-main);
            border-radius: var(--radius-md);
            animation: promoFadeIn 0.3s ease;
        }
        @keyframes promoFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .promo-applied svg {
            color: var(--mint-dark);
        }
        .promo-code-text {
            flex: 1;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--black-soft);
        }
        .promo-remove-btn {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.08);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
        }
        .promo-remove-btn:hover {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
        }
        .promo-success {
            margin-top: 10px;
            font-size: 12px;
            color: var(--mint-dark);
            font-weight: 600;
        }

        /* Shipping Section */
        .shipping-section {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .shipping-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 12px;
        }
        .shipping-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .shipping-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: white;
            border: 2px solid #e8e8e8;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
        }
        .shipping-option:hover {
            border-color: var(--pink-light);
        }
        .shipping-option.selected {
            border-color: var(--mint-main);
            background: linear-gradient(135deg, rgba(61,255,192,0.08), rgba(61,255,192,0.02));
        }
        .shipping-option input {
            display: none;
        }
        .shipping-radio {
            width: 20px;
            height: 20px;
            border: 2px solid #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .shipping-option.selected .shipping-radio {
            border-color: var(--mint-main);
        }
        .shipping-option.selected .shipping-radio::after {
            content: '';
            width: 10px;
            height: 10px;
            background: var(--gradient-mint);
            border-radius: 50%;
        }
        .shipping-info {
            flex: 1;
        }
        .shipping-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--black-soft);
        }
        .shipping-delay {
            font-size: 12px;
            color: var(--gray);
        }
        .shipping-price {
            font-weight: 700;
            font-size: 14px;
            color: var(--mint-dark);
        }
        .shipping-price.free {
            background: var(--gradient-mint);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Summary Rows */
        .summary-rows {
            margin-bottom: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            font-size: 14px;
        }
        .summary-row.discount {
            color: var(--mint-dark);
        }
        .summary-row.discount span:last-child {
            font-weight: 700;
        }
        .summary-row.total {
            font-size: 1.3rem;
            font-weight: 800;
            padding-top: 20px;
            margin-top: 10px;
            border-top: 2px solid var(--pink-light);
        }
        .summary-row.total span:last-child {
            font-family: var(--font-display);
            background: var(--gradient-pink);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Checkout Button */
        .checkout-btn {
            width: 100%;
            padding: 18px;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            background: var(--gradient-pink);
            border: none;
            border-radius: var(--radius-lg);
            color: white;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255,105,180,0.4);
        }

        /* Payment Trust */
        .payment-trust {
            padding: 20px;
            background: linear-gradient(135deg, rgba(0,0,0,0.02), rgba(0,0,0,0.04));
            border-radius: var(--radius-lg);
        }
        .payment-icons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        .payment-icon {
            width: 50px;
            height: 32px;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 800;
            color: var(--gray);
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .payment-icon.visa { color: #1A1F71; }
        .payment-icon.mc { color: #EB001B; }
        .payment-icon.amex { color: #006FCF; }
        .payment-icon.cb { color: #1D4F91; }
        .trust-badges {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .trust-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: var(--gray);
        }
        .trust-badge svg {
            width: 16px;
            height: 16px;
            color: var(--mint-main);
        }

        .continue-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
        }
        .continue-link:hover {
            color: var(--pink-main);
        }

        /* ============ EMPTY CART ============ */
        .empty-cart {
            text-align: center;
            padding: 80px 40px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            border: 1px solid var(--glass-border);
        }
        .empty-cart-icon {
            font-size: 5rem;
            margin-bottom: 25px;
            opacity: 0.6;
        }
        .empty-cart h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--black-soft);
            margin-bottom: 15px;
        }
        .empty-cart p {
            color: var(--gray);
            margin-bottom: 30px;
            font-size: 15px;
        }

        /* ============ FOOTER ============ */
        .site-footer {
            background: var(--black-soft);
            color: white;
            padding: 50px 0 30px;
            margin-top: 60px;
        }
        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        .footer-brand h3 {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .footer-brand p {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
            line-height: 1.7;
        }
        .footer-col h4 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 15px;
            color: white;
        }
        .footer-col ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .footer-col li {
            margin-bottom: 10px;
        }
        .footer-col a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }
        .footer-col a:hover {
            color: var(--pink-main);
        }
        .footer-bottom {
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: rgba(255,255,255,0.5);
        }
        .footer-reassurance {
            display: flex;
            gap: 30px;
        }
        .footer-reassurance span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 1100px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
            .cart-summary {
                position: static;
            }
            .footer-content {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            .cart-item {
                grid-template-columns: 90px 1fr;
                gap: 15px;
            }
            .item-actions {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding-top: 15px;
                border-top: 1px solid rgba(0,0,0,0.06);
            }
            .upsells-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .footer-bottom {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            .footer-reassurance {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        @media (max-width: 480px) {
            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .item-image {
                margin: 0 auto;
            }
            .item-tags {
                justify-content: center;
            }
            .item-text-preview {
                margin: 0 auto;
            }
            .item-actions {
                flex-direction: column;
                gap: 15px;
            }
            .upsells-grid {
                grid-template-columns: 1fr;
            }
            .promo-input-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="/" class="navbar-brand">PERSONNALY</a>
            <div class="navbar-actions">
                <a href="/">Accueil</a>
                <a href="/#produits">Nos Produits</a>
                <a href="/public/cart.php" class="nav-cart-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <?= $cartCount ?>
                </a>
            </div>
        </div>
    </nav>

    <!-- Cart Page -->
    <section class="cart-page">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Votre <span>Panier</span></h1>
                <?php if (!Cart::isEmpty()): ?>
                    <p class="page-subtitle"><?= $cartCount ?> article<?= $cartCount > 1 ? 's' : '' ?> dans votre panier</p>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <?= h($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <?php if (Cart::isEmpty()): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h2>Votre panier est vide</h2>
                    <p>Découvrez nos produits personnalisables et créez quelque chose d'unique !</p>
                    <a href="/" class="btn btn-primary">Découvrir nos produits</a>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <!-- Left Column: Items + Upsells -->
                    <div class="cart-items-section">
                        <!-- Cart Items -->
                        <div class="cart-items">
                            <div class="cart-header">
                                <h2>
                                    Vos articles
                                    <span class="cart-count-badge"><?= $cartCount ?></span>
                                </h2>
                                <form method="post" style="display: inline;">
                                    <?= csrfField() ?>
                                    <button type="submit" name="clear_cart" value="1" class="clear-btn"
                                            onclick="return confirm('Vider tout le panier ?')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                        Vider
                                    </button>
                                </form>
                            </div>

                            <?php foreach ($cartItems as $key => $item):
                                $colorHex = '#CCCCCC';
                                $colorName = $item['customization']['color'] ?? 'blanc';
                                // Essayer de trouver le code hex de la couleur
                                $colorMap = [
                                    'blanc' => '#FFFFFF', 'noir' => '#1A1A2E', 'rose' => '#FF69B4',
                                    'menthe' => '#3DFFC0', 'bleu' => '#4A90D9', 'gris' => '#6B7280',
                                    'rouge' => '#EF4444', 'vert' => '#10B981', 'jaune' => '#F59E0B'
                                ];
                                $colorHex = $colorMap[strtolower($colorName)] ?? '#CCCCCC';
                                $viewLabel = ($item['customization']['view'] ?? 'front') === 'back' ? 'Dos' : 'Face';
                            ?>
                                <div class="cart-item">
                                    <div class="item-image" onclick="openCartLightbox(this)"
                                         data-img="<?= !empty($item['product']['image_front_url']) ? '/public' . h($item['product']['image_front_url']) : '' ?>"
                                         data-text="<?= h($item['customization']['text'] ?? '') ?>"
                                         data-font="<?= h($item['customization']['font'] ?? 'Poppins') ?>"
                                         data-text-color="<?= h($item['customization']['text_color'] ?? '#FF1493') ?>"
                                         data-technique="<?= h($item['customization']['technique'] ?? 'flex') ?>"
                                         data-name="<?= h($item['product']['name']) ?>">
                                        <?php if (!empty($item['product']['image_front_url'])): ?>
                                            <img src="/public<?= h($item['product']['image_front_url']) ?>" alt="<?= h($item['product']['name']) ?>">
                                        <?php else: ?>
                                            <span style="font-size: 2.5rem;">👕</span>
                                        <?php endif; ?>
                                        <div class="zoom-icon">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="11" cy="11" r="8"/>
                                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="item-details">
                                        <h3><?= h($item['product']['name']) ?></h3>
                                        <div class="item-tags">
                                            <span class="item-tag">
                                                📏 <strong><?= h($item['customization']['size'] ?? 'M') ?></strong>
                                            </span>
                                            <span class="item-tag">
                                                <span class="color-dot" style="background-color: <?= $colorHex ?>"></span>
                                                <strong><?= ucfirst(h($colorName)) ?></strong>
                                            </span>
                                            <span class="item-tag">
                                                📍 <strong><?= $viewLabel ?></strong>
                                            </span>
                                            <?php if (!empty($item['customization']['technique'])): ?>
                                                <span class="item-tag">
                                                    ✨ <strong><?= ucfirst(h($item['customization']['technique'])) ?></strong>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($item['customization']['text'])): ?>
                                            <div class="item-text-preview">
                                                ✏️ "<?= h($item['customization']['text']) ?>"
                                            </div>
                                        <?php endif; ?>
                                        <div class="item-unit-price"><?= formatPrice($item['unit_price']) ?> / unité</div>
                                    </div>

                                    <div class="item-actions">
                                        <div class="item-subtotal"><?= formatPrice($item['subtotal']) ?></div>

                                        <form method="post" class="qty-form">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="item_key" value="<?= h($key) ?>">
                                            <input type="hidden" name="update_qty" value="1">
                                            <div class="qty-control">
                                                <button type="submit" name="quantity" value="<?= max(1, $item['quantity'] - 1) ?>" class="qty-btn">−</button>
                                                <span class="qty-value"><?= $item['quantity'] ?></span>
                                                <button type="submit" name="quantity" value="<?= $item['quantity'] + 1 ?>" class="qty-btn">+</button>
                                            </div>
                                        </form>

                                        <form method="post">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="item_key" value="<?= h($key) ?>">
                                            <button type="submit" name="remove_item" value="1" class="remove-btn">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                                </svg>
                                                Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Upsells Section -->
                        <?php if (!empty($upsellSuggestions)): ?>
                            <div class="upsells-section">
                                <h3 class="upsells-title">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                    </svg>
                                    <?= h($upsellSettings['title'] ?? 'Vous aimerez aussi') ?>
                                </h3>
                                <div class="upsells-grid">
                                    <?php foreach ($upsellSuggestions as $suggestion): ?>
                                        <div class="upsell-card">
                                            <div class="upsell-image">
                                                <?php if (!empty($suggestion['image'])): ?>
                                                    <img src="<?= h($suggestion['image']) ?>" alt="<?= h($suggestion['name']) ?>">
                                                <?php else: ?>
                                                    <span style="font-size: 2rem; opacity: 0.5;">👕</span>
                                                <?php endif; ?>
                                                <?php if (!empty($suggestion['badge'])): ?>
                                                    <span class="upsell-badge"><?= h($suggestion['badge']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="upsell-name"><?= h($suggestion['name']) ?></div>
                                            <div class="upsell-price">
                                                <?php if (!empty($suggestion['promo_price'])): ?>
                                                    <span class="original"><?= formatPrice($suggestion['price']) ?></span>
                                                    <span class="current"><?= formatPrice($suggestion['promo_price']) ?></span>
                                                <?php else: ?>
                                                    <span class="current"><?= formatPrice($suggestion['price']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="/public/product.php?id=<?= $suggestion['product_id'] ?>" class="upsell-cta">
                                                Personnaliser →
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Summary -->
                    <div class="cart-summary">
                        <h2 class="summary-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                <line x1="1" y1="10" x2="23" y2="10"/>
                            </svg>
                            Récapitulatif
                        </h2>

                        <!-- Promo Code Section -->
                        <div class="promo-section">
                            <div class="promo-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                                    <line x1="7" y1="7" x2="7.01" y2="7"/>
                                </svg>
                                Code promo
                            </div>

                            <div id="promoInputWrapper" style="<?= $appliedPromo ? 'display:none;' : '' ?>">
                                <div class="promo-input-group">
                                    <input type="text" id="promoCode" placeholder="Entrez votre code" autocomplete="off">
                                    <button type="button" id="applyPromoBtn" class="promo-apply-btn">Appliquer</button>
                                </div>
                                <p class="promo-error" id="promoError"></p>
                            </div>

                            <div id="promoApplied" style="<?= $appliedPromo ? '' : 'display:none;' ?>">
                                <div class="promo-applied">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                    </svg>
                                    <span class="promo-code-text" id="appliedCodeText"><?= h($appliedPromo['code'] ?? '') ?></span>
                                    <button type="button" class="promo-remove-btn" id="removePromoBtn" title="Retirer">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"/>
                                            <line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </button>
                                </div>
                                <p class="promo-success" id="promoSuccessText">
                                    <?php if ($appliedPromo): ?>
                                        <?php if (!empty($appliedPromo['free_shipping'])): ?>
                                            Livraison gratuite appliquée !
                                        <?php else: ?>
                                            -<?= formatPrice($appliedPromo['discount']) ?> de réduction !
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Shipping Section -->
                        <div class="shipping-section">
                            <div class="shipping-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="3" width="15" height="13"/>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                    <circle cx="5.5" cy="18.5" r="2.5"/>
                                    <circle cx="18.5" cy="18.5" r="2.5"/>
                                </svg>
                                Livraison
                            </div>
                            <div class="shipping-options">
                                <?php foreach ($shippingOptions as $key => $option): ?>
                                    <label class="shipping-option <?= $selectedShipping === $key ? 'selected' : '' ?>" data-method="<?= $key ?>">
                                        <input type="radio" name="shipping" value="<?= $key ?>" <?= $selectedShipping === $key ? 'checked' : '' ?>>
                                        <span class="shipping-radio"></span>
                                        <span class="shipping-info">
                                            <span class="shipping-name"><?= h($option['label']) ?></span>
                                            <span class="shipping-delay"><?= h($option['delay']) ?></span>
                                        </span>
                                        <span class="shipping-price <?= $option['price'] == 0 ? 'free' : '' ?>">
                                            <?= $option['price'] == 0 ? 'Gratuit' : formatPrice($option['price']) ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Summary Rows -->
                        <div class="summary-rows">
                            <div class="summary-row">
                                <span>Sous-total</span>
                                <span id="subtotalValue"><?= formatPrice($cartTotal) ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Livraison</span>
                                <span id="shippingValue"><?= $shippingCost == 0 ? 'Gratuit' : formatPrice($shippingCost) ?></span>
                            </div>
                            <div class="summary-row discount" id="discountRow" style="<?= ($appliedPromo && $appliedPromo['discount'] > 0) ? '' : 'display:none;' ?>">
                                <span>Réduction</span>
                                <span id="discountValue">-<?= formatPrice($appliedPromo['discount'] ?? 0) ?></span>
                            </div>
                            <?php
                            $finalTotal = $cartTotal + $shippingCost;
                            if ($appliedPromo && $appliedPromo['discount'] > 0) {
                                $finalTotal = max(0, $finalTotal - $appliedPromo['discount']);
                            }
                            ?>
                            <div class="summary-row total">
                                <span>Total</span>
                                <span id="finalTotal"><?= formatPrice($finalTotal) ?></span>
                            </div>
                        </div>

                        <!-- Checkout Button -->
                        <a href="/public/checkout.php" class="checkout-btn">
                            Passer commande
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>

                        <!-- Payment Trust Section -->
                        <div class="payment-trust">
                            <div class="payment-icons">
                                <div class="payment-icon visa">VISA</div>
                                <div class="payment-icon mc">MC</div>
                                <div class="payment-icon amex">AMEX</div>
                                <div class="payment-icon cb">CB</div>
                            </div>
                            <div class="trust-badges">
                                <div class="trust-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                    Paiement 100% sécurisé
                                </div>
                                <div class="trust-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                    </svg>
                                    Satisfait ou remboursé 14 jours
                                </div>
                                <div class="trust-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="3" width="15" height="13"/>
                                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                        <circle cx="5.5" cy="18.5" r="2.5"/>
                                        <circle cx="18.5" cy="18.5" r="2.5"/>
                                    </svg>
                                    Livraison offerte dès 0€
                                </div>
                            </div>
                        </div>

                        <a href="/" class="continue-link">← Continuer mes achats</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3>PERSONNALY</h3>
                    <p>Créez des produits uniques qui vous ressemblent. Personnalisation textile de qualité, made in France.</p>
                </div>
                <div class="footer-col">
                    <h4>Navigation</h4>
                    <ul>
                        <li><a href="/">Accueil</a></li>
                        <li><a href="/#produits">Nos produits</a></li>
                        <li><a href="/public/cart.php">Panier</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Informations</h4>
                    <ul>
                        <li><a href="#">Livraison</a></li>
                        <li><a href="#">Retours</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact</h4>
                    <ul>
                        <li><a href="mailto:contact@personnaly.fr">contact@personnaly.fr</a></li>
                        <li><a href="#">Nous contacter</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© 2026 PERSONNALY. Tous droits réservés.</span>
                <div class="footer-reassurance">
                    <span>🔒 Paiement sécurisé</span>
                    <span>🚚 Livraison gratuite</span>
                    <span>↩️ Retours 14 jours</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Lightbox Component -->
    <script src="/public/assets/js/lightbox.js"></script>
    <script>
        function openCartLightbox(el) {
            if (!window.PersonnalyLightbox) return;

            const imgSrc = el.dataset.img;
            if (!imgSrc) return;

            PersonnalyLightbox.open({
                imageSrc: imgSrc,
                imageAlt: el.dataset.name || 'Produit',
                text: el.dataset.text || null,
                font: (el.dataset.font || 'Poppins') + ', sans-serif',
                fontSize: '2rem',
                textX: 50,
                textY: 50,
                textColor: el.dataset.textColor || '#FF1493',
                technique: el.dataset.technique || 'flex'
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const cartTotal = <?= $cartTotal ?>;
            let currentShippingCost = <?= $shippingCost ?>;
            let currentDiscount = <?= $appliedPromo['discount'] ?? 0 ?>;

            // === Promo Code ===
            const promoInput = document.getElementById('promoCode');
            const applyBtn = document.getElementById('applyPromoBtn');
            const removeBtn = document.getElementById('removePromoBtn');
            const promoError = document.getElementById('promoError');
            const promoInputWrapper = document.getElementById('promoInputWrapper');
            const promoApplied = document.getElementById('promoApplied');
            const appliedCodeText = document.getElementById('appliedCodeText');
            const promoSuccessText = document.getElementById('promoSuccessText');
            const discountRow = document.getElementById('discountRow');
            const discountValue = document.getElementById('discountValue');
            const finalTotal = document.getElementById('finalTotal');
            const shippingValue = document.getElementById('shippingValue');

            function updateTotal() {
                let total = cartTotal + currentShippingCost - currentDiscount;
                total = Math.max(0, total);
                finalTotal.textContent = formatPrice(total);
            }

            if (applyBtn) {
                applyBtn.addEventListener('click', async function() {
                    const code = promoInput.value.trim().toUpperCase();
                    if (!code) {
                        showError('Veuillez entrer un code promo');
                        return;
                    }

                    applyBtn.disabled = true;
                    applyBtn.textContent = '...';

                    try {
                        const formData = new FormData();
                        formData.append('code', code);

                        const response = await fetch('/public/cart.php?ajax=validate_promo', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success) {
                            promoInputWrapper.style.display = 'none';
                            promoApplied.style.display = 'block';
                            appliedCodeText.textContent = code;

                            if (data.free_shipping) {
                                promoSuccessText.textContent = 'Livraison gratuite appliquée !';
                                discountRow.style.display = 'none';
                                currentShippingCost = 0;
                                shippingValue.textContent = 'Gratuit';
                            } else {
                                promoSuccessText.textContent = '-' + formatPrice(data.discount) + ' de réduction !';
                                discountRow.style.display = 'flex';
                                discountValue.textContent = '-' + formatPrice(data.discount);
                                currentDiscount = data.discount;
                            }

                            updateTotal();
                            hideError();
                        } else {
                            showError(data.message);
                        }
                    } catch (err) {
                        showError('Erreur de connexion. Réessayez.');
                    }

                    applyBtn.disabled = false;
                    applyBtn.textContent = 'Appliquer';
                });
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', async function() {
                    try {
                        await fetch('/public/cart.php?ajax=remove_promo', { method: 'POST' });

                        promoApplied.style.display = 'none';
                        promoInputWrapper.style.display = 'block';
                        promoInput.value = '';
                        discountRow.style.display = 'none';
                        currentDiscount = 0;

                        // Reset shipping display
                        const selectedOption = document.querySelector('.shipping-option.selected');
                        if (selectedOption) {
                            const method = selectedOption.dataset.method;
                            currentShippingCost = <?= json_encode($shippingOptions) ?>[method].price;
                            shippingValue.textContent = currentShippingCost == 0 ? 'Gratuit' : formatPrice(currentShippingCost);
                        }

                        updateTotal();
                    } catch (err) {
                        console.error('Erreur:', err);
                    }
                });
            }

            if (promoInput) {
                promoInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyBtn.click();
                    }
                });
            }

            function showError(msg) {
                promoError.textContent = msg;
                promoError.classList.add('show');
            }

            function hideError() {
                promoError.classList.remove('show');
            }

            // === Shipping Options ===
            const shippingOptions = document.querySelectorAll('.shipping-option');
            shippingOptions.forEach(option => {
                option.addEventListener('click', async function() {
                    const method = this.dataset.method;

                    // Update UI
                    shippingOptions.forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    this.querySelector('input').checked = true;

                    // Update price
                    const prices = <?= json_encode($shippingOptions) ?>;
                    currentShippingCost = prices[method].price;
                    shippingValue.textContent = currentShippingCost == 0 ? 'Gratuit' : formatPrice(currentShippingCost);
                    updateTotal();

                    // Save to session
                    try {
                        const formData = new FormData();
                        formData.append('method', method);
                        await fetch('/public/cart.php?ajax=set_shipping', {
                            method: 'POST',
                            body: formData
                        });
                    } catch (err) {
                        console.error('Erreur:', err);
                    }
                });
            });

            function formatPrice(amount) {
                return new Intl.NumberFormat('fr-FR', {
                    style: 'currency',
                    currency: 'EUR'
                }).format(amount);
            }
        });
    </script>
</body>
</html>
