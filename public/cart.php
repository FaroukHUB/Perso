<?php
/**
 * PERSONNALY - Page Panier
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';

$success = '';
$error = '';

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

                        <div class="summary-row total">
                            <span>Total</span>
                            <span><?= formatPrice($cartTotal) ?></span>
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
                textColor: '#FF1493'
            });
        }
    </script>
</body>
</html>
