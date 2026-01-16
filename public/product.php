<?php
/**
 * PERSONNALY - Page Produit avec Personnalisation
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';

// Récupération du produit
$productId = (int) get('id', 0);
$productModel = new Product();
$product = $productModel->findById($productId);

// Produit introuvable ou inactif
if (!$product || !$product['active']) {
    redirect('/');
}

$success = '';
$error = '';

// Options de personnalisation disponibles
$sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
$colors = [
    'blanc' => '#FFFFFF',
    'noir' => '#1A1A2E',
    'rose' => '#FF69B4',
    'menthe' => '#3DFFC0',
    'bleu' => '#4A90D9',
    'gris' => '#6B7280',
];
$positions = ['centre', 'gauche', 'droite', 'dos'];

// Traitement du formulaire d'ajout au panier
if (isPost() && isset($_POST['add_to_cart'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $customization = [
            'size' => post('size', 'M'),
            'color' => post('color', 'blanc'),
            'text' => trim(post('custom_text', '')),
            'position' => post('position', 'centre'),
            'quantity' => max(1, (int) post('quantity', 1)),
        ];

        $quantity = $customization['quantity'];
        unset($customization['quantity']);

        Cart::add($productId, $customization, (float) $product['base_price'], $quantity);
        $success = 'Produit ajouté au panier !';
    } else {
        $error = 'Session expirée. Veuillez réessayer.';
    }
}

$cartCount = Cart::count();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($product['name']) ?> - PERSONNALY</title>
    <meta name="description" content="<?= h($product['description'] ?? 'Personnalisez ce produit selon vos envies') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        body { background: var(--gray-light); }

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
        .cart-link {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--gradient-mint);
            color: var(--black) !important;
            padding: 10px 20px;
            border-radius: var(--radius-full);
            font-weight: 600;
        }
        .cart-badge {
            background: var(--pink-main);
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: var(--radius-full);
        }

        /* Product Page Layout */
        .product-page { padding: 40px 0 80px; }
        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: start;
        }

        /* Product Image */
        .product-image-box {
            background: white;
            border-radius: var(--radius-lg);
            padding: 40px;
            text-align: center;
            position: sticky;
            top: 100px;
        }
        .product-preview {
            width: 100%;
            max-width: 400px;
            height: 400px;
            margin: 0 auto;
            background: linear-gradient(135deg, #f8f8f8 0%, #e8e8e8 100%);
            border-radius: var(--radius-lg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: background-color 0.3s ease;
        }
        .preview-icon { font-size: 6rem; opacity: 0.6; }
        .preview-text {
            position: absolute;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.5rem;
            max-width: 80%;
            text-align: center;
            word-break: break-word;
            color: var(--pink-dark);
        }
        .product-category-badge {
            position: absolute;
            top: 20px;
            left: 20px;
        }

        /* Form Section */
        .product-form-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 40px;
        }
        .product-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--black-soft);
            margin-bottom: 10px;
        }
        .product-description {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .product-price {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--pink-dark);
            margin-bottom: 30px;
        }

        /* Customization Form */
        .customization-section {
            border-top: 1px solid rgba(0,0,0,0.08);
            padding-top: 30px;
            margin-top: 20px;
        }
        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: var(--gradient-pink);
            border-radius: 2px;
        }

        /* Size Selection */
        .size-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
        }
        .size-option {
            width: 50px;
            height: 50px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .size-option:hover { border-color: var(--pink-main); }
        .size-option.selected {
            background: var(--gradient-pink);
            border-color: var(--pink-main);
            color: white;
        }
        .size-option input { display: none; }

        /* Color Selection */
        .color-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
        }
        .color-option {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
            border: 3px solid transparent;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .color-option:hover { transform: scale(1.1); }
        .color-option.selected {
            border-color: var(--pink-main);
            transform: scale(1.15);
            box-shadow: 0 4px 15px rgba(255, 105, 180, 0.4);
        }
        .color-option input { display: none; }

        /* Position Selection */
        .position-options {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 25px;
        }
        .position-option {
            padding: 12px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
        }
        .position-option:hover { border-color: var(--mint-main); }
        .position-option.selected {
            background: var(--gradient-mint);
            border-color: var(--mint-main);
            color: var(--black);
        }
        .position-option input { display: none; }

        /* Text Input */
        .custom-text-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            font-size: 16px;
            transition: all 0.2s;
            margin-bottom: 25px;
        }
        .custom-text-input:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.1);
        }

        /* Quantity */
        .quantity-row {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .quantity-selector {
            display: flex;
            align-items: center;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        .qty-btn {
            width: 45px;
            height: 45px;
            border: none;
            background: var(--gray-light);
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .qty-btn:hover { background: var(--pink-light); }
        .qty-input {
            width: 60px;
            height: 45px;
            border: none;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* Add to Cart Button */
        .add-to-cart-btn {
            width: 100%;
            padding: 18px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

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
        .alert-success a {
            color: var(--mint-dark);
            font-weight: 700;
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .product-grid { grid-template-columns: 1fr; gap: 30px; }
            .product-image-box { position: static; }
        }
        @media (max-width: 600px) {
            .position-options { grid-template-columns: repeat(2, 1fr); }
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
                <a href="/public/cart.php" class="cart-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    Panier
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </nav>

    <!-- Product Page -->
    <section class="product-page">
        <div class="container">
            <div class="product-grid">
                <!-- Image / Preview -->
                <div class="product-image-box">
                    <div class="product-preview" id="productPreview">
                        <span class="product-category-badge badge badge-pink">
                            <?= h($product['category'] ?? 'Textile') ?>
                        </span>
                        <span class="preview-icon">👕</span>
                        <span class="preview-text" id="previewText"></span>
                    </div>
                    <p style="color: var(--gray); margin-top: 20px; font-size: 14px;">
                        Aperçu de votre personnalisation
                    </p>
                </div>

                <!-- Form -->
                <div class="product-form-section">
                    <h1 class="product-title"><?= h($product['name']) ?></h1>
                    <p class="product-description">
                        <?= h($product['description'] ?? 'Un produit de qualité premium, personnalisable selon vos envies.') ?>
                    </p>
                    <div class="product-price"><?= formatPrice($product['base_price']) ?></div>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= h($success) ?> <a href="/public/cart.php">Voir le panier</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="post" id="customizationForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="add_to_cart" value="1">

                        <!-- Taille -->
                        <div class="customization-section">
                            <h3 class="section-title">Taille</h3>
                            <div class="size-options">
                                <?php foreach ($sizes as $size): ?>
                                    <label class="size-option <?= $size === 'M' ? 'selected' : '' ?>">
                                        <input type="radio" name="size" value="<?= $size ?>" <?= $size === 'M' ? 'checked' : '' ?>>
                                        <?= $size ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Couleur -->
                        <div class="customization-section">
                            <h3 class="section-title">Couleur du produit</h3>
                            <div class="color-options">
                                <?php foreach ($colors as $name => $hex): ?>
                                    <label class="color-option <?= $name === 'blanc' ? 'selected' : '' ?>"
                                           style="background-color: <?= $hex ?>; <?= $name === 'blanc' ? 'border: 1px solid #ddd;' : '' ?>"
                                           title="<?= ucfirst($name) ?>"
                                           data-color="<?= $hex ?>">
                                        <input type="radio" name="color" value="<?= $name ?>" <?= $name === 'blanc' ? 'checked' : '' ?>>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Texte personnalisé -->
                        <div class="customization-section">
                            <h3 class="section-title">Votre texte personnalisé</h3>
                            <input type="text"
                                   name="custom_text"
                                   id="customText"
                                   class="custom-text-input"
                                   placeholder="Ex: Famille Dupont, Team Papa..."
                                   maxlength="50">
                        </div>

                        <!-- Position -->
                        <div class="customization-section">
                            <h3 class="section-title">Position du texte</h3>
                            <div class="position-options">
                                <?php foreach ($positions as $pos): ?>
                                    <label class="position-option <?= $pos === 'centre' ? 'selected' : '' ?>">
                                        <input type="radio" name="position" value="<?= $pos ?>" <?= $pos === 'centre' ? 'checked' : '' ?>>
                                        <?= ucfirst($pos) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Quantité -->
                        <div class="customization-section">
                            <h3 class="section-title">Quantité</h3>
                            <div class="quantity-row">
                                <div class="quantity-selector">
                                    <button type="button" class="qty-btn" id="qtyMinus">−</button>
                                    <input type="number" name="quantity" id="qtyInput" class="qty-input" value="1" min="1" max="99">
                                    <button type="button" class="qty-btn" id="qtyPlus">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- Bouton Ajouter -->
                        <button type="submit" class="btn btn-primary add-to-cart-btn">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                <line x1="3" y1="6" x2="21" y2="6"/>
                                <path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                            Ajouter au panier
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Selection handling
        document.querySelectorAll('.size-option, .color-option, .position-option').forEach(option => {
            option.addEventListener('click', function() {
                const parent = this.parentElement;
                parent.querySelectorAll(this.className.split(' ')[0].replace('.', '')).forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
            });
        });

        // Live preview of custom text
        const customText = document.getElementById('customText');
        const previewText = document.getElementById('previewText');
        const productPreview = document.getElementById('productPreview');

        customText.addEventListener('input', function() {
            previewText.textContent = this.value;
        });

        // Color preview
        document.querySelectorAll('.color-option').forEach(option => {
            option.addEventListener('click', function() {
                const color = this.dataset.color;
                productPreview.style.backgroundColor = color === '#FFFFFF' ? '#f8f8f8' : color;
                // Adjust text color for dark backgrounds
                const isDark = ['#1A1A2E', '#6B7280'].includes(color);
                previewText.style.color = isDark ? '#FF69B4' : '#FF1493';
            });
        });

        // Quantity controls
        const qtyInput = document.getElementById('qtyInput');
        document.getElementById('qtyMinus').addEventListener('click', () => {
            if (qtyInput.value > 1) qtyInput.value = parseInt(qtyInput.value) - 1;
        });
        document.getElementById('qtyPlus').addEventListener('click', () => {
            if (qtyInput.value < 99) qtyInput.value = parseInt(qtyInput.value) + 1;
        });
    </script>
</body>
</html>
