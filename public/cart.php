<?php
/**
 * PERSONNALY - Page Panier
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/ProductUpsell.php';
require_once __DIR__ . '/../app/models/PromoCode.php';

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
        // Stocker le code promo en session
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

// Actions sur le panier
if (isPost()) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        // Mise à jour quantité
        if (isset($_POST['update_qty'])) {
            $itemKey = post('item_key', '');
            $quantity = (int) post('quantity', 1);
            Cart::updateQuantity($itemKey, $quantity);
            $success = 'Quantité mise à jour.';
        }

        // Suppression article
        if (isset($_POST['remove_item'])) {
            $itemKey = post('item_key', '');
            Cart::remove($itemKey);
            $success = 'Article supprimé du panier.';
        }

        // Vider le panier
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

// Récupérer les suggestions de produits (vrais upsells)
$upsellSuggestions = [];
$upsellSettings = [];
if (!Cart::isEmpty()) {
    $upsellModel = new ProductUpsell();
    $upsellSettings = $upsellModel->getSettings();

    // Vérifier si les upsells sont activés et affichés sur le panier
    if (($upsellSettings['enabled'] ?? '1') === '1' && ($upsellSettings['show_on_cart'] ?? '1') === '1') {
        // Récupérer les IDs des produits et catégories du panier
        $cartProductIds = [];
        $cartCategoryIds = [];

        foreach ($cartItems as $item) {
            $cartProductIds[] = $item['product_id'];
            if (!empty($item['product']['category_id'])) {
                $cartCategoryIds[] = $item['product']['category_id'];
            }
        }

        $cartProductIds = array_unique($cartProductIds);
        $cartCategoryIds = array_unique($cartCategoryIds);

        // Récupérer les suggestions
        $upsellSuggestions = $upsellModel->getSuggestionsForCart(
            $cartProductIds,
            $cartCategoryIds,
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
        body { background: var(--gray-light); min-height: 100vh; }

        /* Navbar */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(13, 13, 13, 0.98);
            backdrop-filter: blur(10px);
            padding: 15px 0;
        }
        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .navbar-actions { display: flex; align-items: center; gap: 20px; }
        .navbar-actions a { color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .navbar-actions a:hover { color: var(--pink-main); }

        /* Cart Page */
        .cart-page { padding: 40px 0 80px; }
        .page-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--black-soft);
            margin-bottom: 30px;
        }
        .page-title span { color: var(--pink-main); }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 25px;
            font-weight: 500;
        }
        .alert-success {
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-dark);
            border-left: 4px solid var(--mint-main);
        }
        .alert-error {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
            border-left: 4px solid var(--pink-main);
        }

        /* Cart Layout */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
            align-items: start;
        }

        /* Cart Items */
        .cart-items {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .cart-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .clear-btn {
            color: var(--gray);
            font-size: 14px;
            cursor: pointer;
            border: none;
            background: none;
            transition: color 0.2s;
        }
        .clear-btn:hover { color: var(--pink-dark); }

        /* Cart Item */
        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 20px;
            padding: 25px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            align-items: center;
        }
        .cart-item:last-child { border-bottom: none; }
        .item-image {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            position: relative;
            cursor: pointer;
            overflow: hidden;
        }
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 8px;
        }
        .item-image:hover .zoom-overlay {
            opacity: 1;
        }
        .zoom-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 105, 180, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.2s;
            border-radius: var(--radius-md);
        }
        .item-details h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--black-soft);
            margin-bottom: 8px;
        }
        .item-customization {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .customization-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--gray-light);
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 12px;
            color: var(--gray);
        }
        .customization-tag strong { color: var(--black-soft); }
        .item-price {
            font-weight: 700;
            color: var(--pink-dark);
        }
        .item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 15px;
        }
        .item-subtotal {
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--black-soft);
        }

        /* Quantity Control */
        .qty-control {
            display: flex;
            align-items: center;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        .qty-btn {
            width: 36px;
            height: 36px;
            border: none;
            background: var(--gray-light);
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .qty-btn:hover { background: var(--pink-light); }
        .qty-value {
            width: 45px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* Remove Button */
        .remove-btn {
            color: var(--gray);
            font-size: 13px;
            cursor: pointer;
            border: none;
            background: none;
            transition: color 0.2s;
        }
        .remove-btn:hover { color: #dc3545; }

        /* Cart Summary */
        .cart-summary {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            position: sticky;
            top: 100px;
        }
        .summary-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 15px;
        }
        .summary-row.total {
            font-size: 1.3rem;
            font-weight: 700;
            padding-top: 15px;
            margin-top: 20px;
            border-top: 2px solid var(--pink-light);
        }
        .summary-row.total span:last-child {
            color: var(--pink-dark);
            font-family: var(--font-display);
        }
        .checkout-btn {
            width: 100%;
            margin-top: 25px;
            padding: 18px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .continue-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }
        .continue-link:hover { color: var(--pink-main); }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 80px 40px;
        }
        .empty-cart-icon {
            font-size: 5rem;
            margin-bottom: 25px;
            opacity: 0.4;
        }
        .empty-cart h2 {
            font-size: 1.5rem;
            color: var(--black-soft);
            margin-bottom: 15px;
        }
        .empty-cart p {
            color: var(--gray);
            margin-bottom: 30px;
        }

        /* Promo Code Section */
        .promo-section {
            margin: 20px 0;
            padding: 20px 0;
            border-top: 1px solid rgba(0,0,0,0.06);
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .promo-input-wrapper label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
            margin-bottom: 10px;
        }
        .promo-input-group {
            display: flex;
            gap: 10px;
        }
        .promo-input-group input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: border-color 0.2s;
        }
        .promo-input-group input:focus {
            outline: none;
            border-color: var(--pink-main);
        }
        .apply-promo-btn {
            padding: 12px 20px;
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
        .apply-promo-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(61, 255, 192, 0.3);
        }
        .apply-promo-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .promo-error {
            margin: 10px 0 0;
            font-size: 13px;
            color: #dc3545;
            display: none;
        }
        .promo-error.show { display: block; }

        .promo-applied {
            animation: promoFadeIn 0.3s ease;
        }
        @keyframes promoFadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .promo-badge-applied {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, rgba(61, 255, 192, 0.15) 0%, rgba(61, 255, 192, 0.05) 100%);
            border: 2px dashed var(--mint-main);
            border-radius: var(--radius-md);
            padding: 12px 16px;
        }
        .promo-badge-applied svg {
            color: var(--mint-dark);
        }
        .promo-code-text {
            flex: 1;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--black-soft);
        }
        .remove-promo-btn {
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
        .remove-promo-btn:hover {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        .promo-success-text {
            margin: 10px 0 0;
            font-size: 13px;
            color: var(--mint-dark);
            font-weight: 500;
        }

        .discount-row {
            color: var(--mint-dark);
        }
        .discount-value {
            font-weight: 700;
            color: var(--mint-dark);
        }

        /* Responsive */
        @media (max-width: 968px) {
            .cart-layout { grid-template-columns: 1fr; }
            .cart-summary { position: static; }
        }
        @media (max-width: 600px) {
            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .item-image { margin: 0 auto; }
            .item-actions { align-items: center; }
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
                <a href="/#produits">Produits</a>
            </div>
        </div>
    </nav>

    <!-- Cart Page -->
    <section class="cart-page">
        <div class="container">
            <h1 class="page-title">Votre <span>Panier</span></h1>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <?php if (Cart::isEmpty()): ?>
                <div class="cart-items">
                    <div class="empty-cart">
                        <div class="empty-cart-icon">🛒</div>
                        <h2>Votre panier est vide</h2>
                        <p>Découvrez nos produits et commencez à personnaliser !</p>
                        <a href="/" class="btn btn-primary">Voir les produits</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <div class="cart-header">
                            <h2><?= $cartCount ?> article<?= $cartCount > 1 ? 's' : '' ?></h2>
                            <form method="post" style="display: inline;">
                                <?= csrfField() ?>
                                <button type="submit" name="clear_cart" value="1" class="clear-btn"
                                        onclick="return confirm('Vider le panier ?')">
                                    Vider le panier
                                </button>
                            </form>
                        </div>

                        <?php foreach ($cartItems as $key => $item): ?>
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
                                        👕
                                    <?php endif; ?>
                                    <div class="zoom-overlay">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="11" y1="8" x2="11" y2="14"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="item-details">
                                    <h3><?= h($item['product']['name']) ?></h3>
                                    <div class="item-customization">
                                        <span class="customization-tag">
                                            Taille: <strong><?= h($item['customization']['size'] ?? 'M') ?></strong>
                                        </span>
                                        <span class="customization-tag">
                                            Couleur: <strong><?= ucfirst(h($item['customization']['color'] ?? 'blanc')) ?></strong>
                                        </span>
                                        <?php if (!empty($item['customization']['text'])): ?>
                                            <span class="customization-tag">
                                                Texte: <strong>"<?= h($item['customization']['text']) ?>"</strong>
                                            </span>
                                        <?php endif; ?>
                                        <span class="customization-tag">
                                            Position: <strong><?= ucfirst(h($item['customization']['position'] ?? 'centre')) ?></strong>
                                        </span>
                                        <?php if (!empty($item['customization']['technique'])): ?>
                                            <span class="customization-tag">
                                                Technique: <strong><?= ucfirst(h($item['customization']['technique'])) ?></strong>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-price"><?= formatPrice($item['unit_price']) ?> / unité</div>
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
                                            ✕ Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Upsells Section (Suggestions de produits) -->
                    <?php if (!empty($upsellSuggestions)): ?>
                        <div class="upsells-section" style="grid-column: 1 / -1; order: 10;">
                            <h3 class="upsells-title">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                    <polyline points="7.5 4.21 12 6.81 16.5 4.21"/>
                                </svg>
                                <?= h($upsellSettings['title'] ?? 'Vous aimerez aussi') ?>
                            </h3>
                            <div class="upsells-grid">
                                <?php foreach ($upsellSuggestions as $suggestion): ?>
                                    <div class="upsell-card">
                                        <div class="upsell-icon">
                                            <?php if (!empty($suggestion['image'])): ?>
                                                <img src="<?= h($suggestion['image']) ?>" alt="<?= h($suggestion['name']) ?>">
                                            <?php else: ?>
                                                <span style="font-size: 1.5rem;">👕</span>
                                            <?php endif; ?>
                                            <?php if (!empty($suggestion['badge'])): ?>
                                                <span class="upsell-badge-overlay"><?= h($suggestion['badge']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="upsell-content">
                                            <div class="upsell-label"><?= h($suggestion['name']) ?></div>
                                            <?php if (!empty($suggestion['description'])): ?>
                                                <div class="upsell-desc"><?= h($suggestion['description']) ?></div>
                                            <?php endif; ?>
                                            <div class="upsell-product-price">
                                                <?php if (!empty($suggestion['promo_price'])): ?>
                                                    <span class="original"><?= formatPrice($suggestion['price']) ?></span>
                                                    <span class="discounted"><?= formatPrice($suggestion['promo_price']) ?></span>
                                                <?php else: ?>
                                                    <span class="discounted"><?= formatPrice($suggestion['price']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <a href="/public/configurateur.php?id=<?= $suggestion['product_id'] ?>" class="upsell-add-btn">
                                            Personnaliser
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Summary -->
                    <div class="cart-summary">
                        <h2 class="summary-title">Récapitulatif</h2>

                        <div class="summary-row">
                            <span>Sous-total</span>
                            <span><?= formatPrice($cartTotal) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Livraison</span>
                            <span style="color: var(--mint-dark);">Gratuite</span>
                        </div>

                        <!-- Section Code Promo -->
                        <div class="promo-section">
                            <div class="promo-input-wrapper" id="promoInputWrapper" style="<?= $appliedPromo ? 'display:none;' : '' ?>">
                                <label for="promoCode">Code promo</label>
                                <div class="promo-input-group">
                                    <input type="text" id="promoCode" placeholder="Entrez votre code"
                                           style="text-transform: uppercase;">
                                    <button type="button" id="applyPromoBtn" class="apply-promo-btn">
                                        Appliquer
                                    </button>
                                </div>
                                <p class="promo-error" id="promoError"></p>
                            </div>

                            <div class="promo-applied" id="promoApplied" style="<?= $appliedPromo ? '' : 'display:none;' ?>">
                                <div class="promo-badge-applied">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                                        <line x1="7" y1="7" x2="7.01" y2="7"/>
                                    </svg>
                                    <span class="promo-code-text" id="appliedCodeText"><?= h($appliedPromo['code'] ?? '') ?></span>
                                    <button type="button" class="remove-promo-btn" id="removePromoBtn" title="Retirer">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </button>
                                </div>
                                <p class="promo-success-text" id="promoSuccessText">
                                    <?php if ($appliedPromo): ?>
                                        <?php if ($appliedPromo['free_shipping']): ?>
                                            Livraison gratuite appliquée !
                                        <?php else: ?>
                                            Réduction de <?= formatPrice($appliedPromo['discount']) ?> appliquée !
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Afficher réduction si code promo appliqué -->
                        <div class="summary-row discount-row" id="discountRow" style="<?= ($appliedPromo && $appliedPromo['discount'] > 0) ? '' : 'display:none;' ?>">
                            <span>Réduction</span>
                            <span class="discount-value" id="discountValue">-<?= formatPrice($appliedPromo['discount'] ?? 0) ?></span>
                        </div>

                        <?php
                        $finalTotal = $cartTotal;
                        if ($appliedPromo && $appliedPromo['discount'] > 0) {
                            $finalTotal = max(0, $cartTotal - $appliedPromo['discount']);
                        }
                        ?>

                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="finalTotal"><?= formatPrice($finalTotal) ?></span>
                        </div>

                        <a href="/public/checkout.php" class="btn btn-primary checkout-btn">
                            Passer commande
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>

                        <a href="/" class="continue-link">← Continuer mes achats</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

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

        // Gestion code promo
        document.addEventListener('DOMContentLoaded', function() {
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

            const cartTotal = <?= $cartTotal ?>;

            // Appliquer code promo
            if (applyBtn) {
                applyBtn.addEventListener('click', async function() {
                    const code = promoInput.value.trim().toUpperCase();

                    if (!code) {
                        showError('Veuillez entrer un code promo');
                        return;
                    }

                    applyBtn.disabled = true;
                    applyBtn.textContent = 'Vérification...';

                    try {
                        const formData = new FormData();
                        formData.append('code', code);

                        const response = await fetch('/public/cart.php?ajax=validate_promo', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Masquer input, afficher badge
                            promoInputWrapper.style.display = 'none';
                            promoApplied.style.display = 'block';
                            appliedCodeText.textContent = code;

                            if (data.free_shipping) {
                                promoSuccessText.textContent = 'Livraison gratuite appliquée !';
                                discountRow.style.display = 'none';
                            } else {
                                promoSuccessText.textContent = 'Réduction de ' + formatPrice(data.discount) + ' appliquée !';
                                discountRow.style.display = 'flex';
                                discountValue.textContent = '-' + formatPrice(data.discount);
                                finalTotal.textContent = formatPrice(Math.max(0, cartTotal - data.discount));
                            }

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

            // Retirer code promo
            if (removeBtn) {
                removeBtn.addEventListener('click', async function() {
                    try {
                        await fetch('/public/cart.php?ajax=remove_promo', { method: 'POST' });

                        // Réafficher input
                        promoApplied.style.display = 'none';
                        promoInputWrapper.style.display = 'block';
                        promoInput.value = '';
                        discountRow.style.display = 'none';
                        finalTotal.textContent = formatPrice(cartTotal);
                    } catch (err) {
                        console.error('Erreur:', err);
                    }
                });
            }

            // Validation sur Entrée
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
