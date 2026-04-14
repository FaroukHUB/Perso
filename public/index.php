<?php
/**
 * PERSONNALY - Page d'accueil
 * Affiche les produits du catalogue
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Category.php';

$cartCount = Cart::count();

$productModel = new Product();
$categoryModel = new Category();

// Tous les produits actifs
$products = $productModel->findActive();

// Ajouter les noms de catégories pour chaque produit
foreach ($products as &$product) {
    $product['category_names'] = $categoryModel->getCategoryNamesByProduct($product['id']);
}
unset($product);

// Catégories actives
$categories = $categoryModel->findAllActive();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PERSONNALY - Personnalisation Textile pour Toute la Famille</title>
    <meta name="description" content="Créez des vêtements uniques pour hommes, femmes et enfants. Personnalisation textile de qualité.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        /* ===== NAVBAR ===== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(13, 13, 13, 0.95);
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
        .navbar-nav {
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .navbar-nav a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            transition: color var(--transition-fast);
        }
        .navbar-nav a:hover { color: var(--pink-main); }
        .cart-nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--gradient-mint);
            color: var(--black) !important;
            padding: 10px 18px;
            border-radius: var(--radius-full);
            font-weight: 600;
            transition: all var(--transition-normal);
        }
        .cart-nav-link:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-mint);
            color: var(--black) !important;
        }
        .cart-badge {
            background: var(--pink-main);
            color: white;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: var(--radius-full);
        }

        /* ===== HERO ===== */
        .hero {
            min-height: 60vh;
            display: flex;
            align-items: center;
            background: var(--gradient-dark);
            position: relative;
            overflow: hidden;
            padding-top: 80px;
        }
        .hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: var(--pink-main);
            border-radius: 50%;
            filter: blur(200px);
            opacity: 0.15;
            top: -200px;
            right: -100px;
        }
        .hero::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: var(--mint-main);
            border-radius: 50%;
            filter: blur(180px);
            opacity: 0.1;
            bottom: -100px;
            left: -50px;
        }
        .hero-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 700px;
            margin: 0 auto;
        }
        .hero h1 {
            font-family: var(--font-display);
            font-size: 3rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: var(--spacing-md);
            line-height: 1.15;
        }
        .hero h1 span {
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: var(--spacing-xl);
            line-height: 1.6;
        }
        .hero-cta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--gradient-pink);
            color: white;
            padding: 14px 32px;
            border-radius: var(--radius-full);
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            transition: all var(--transition-normal);
        }
        .hero-cta:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-pink);
        }

        /* ===== PRODUITS ===== */
        .products-section {
            padding: var(--spacing-xxl) 0;
            background: var(--gray-light);
        }
        .section-title {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: var(--spacing-xl);
            color: var(--black);
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: var(--spacing-lg);
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-md);
        }
        .product-card {
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-normal);
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .product-card-img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            background: #f0f0f0;
        }
        .product-card-body {
            padding: var(--spacing-md);
        }
        .product-card-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 6px;
            color: var(--black);
        }
        .product-card-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        }
        .product-card-cat {
            font-size: 11px;
            padding: 2px 10px;
            border-radius: var(--radius-full);
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-dark);
            font-weight: 500;
        }
        .product-card-price {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--pink-dark);
        }
        .product-card-cta {
            display: block;
            text-align: center;
            padding: 10px;
            background: var(--gradient-pink);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: opacity var(--transition-fast);
        }
        .product-card-cta:hover {
            opacity: 0.9;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: var(--spacing-xxl);
            color: var(--gray);
        }

        /* ===== FOOTER ===== */
        .footer {
            background: var(--black);
            color: rgba(255, 255, 255, 0.6);
            padding: var(--spacing-xl) 0;
            text-align: center;
            font-size: 0.9rem;
        }
        .footer a {
            color: var(--pink-main);
            text-decoration: none;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2rem; }
            .hero p { font-size: 1rem; }
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--spacing-md);
            }
            .navbar-nav { gap: 15px; }
        }
        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="container">
            <a href="/public/" class="navbar-brand">PERSONNALY</a>
            <div class="navbar-nav">
                <a href="/public/">Accueil</a>
                <a href="/public/cart.php" class="cart-nav-link">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    Panier
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Créez des vêtements <span>uniques</span></h1>
                <p>Personnalisez vos textiles avec broderie, flocage et plus encore. Pour toute la famille.</p>
                <a href="#produits" class="hero-cta">
                    Découvrir nos produits
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <!-- PRODUITS -->
    <section class="products-section" id="produits">
        <div class="container">
            <h2 class="section-title">Nos produits</h2>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <p>Aucun produit disponible pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <a href="/public/product.php?id=<?= $product['id'] ?>" class="product-card">
                            <?php if (!empty($product['image_front_url'])): ?>
                                <img src="/public<?= h($product['image_front_url']) ?>" alt="<?= h($product['name']) ?>" class="product-card-img">
                            <?php else: ?>
                                <div class="product-card-img" style="display:flex;align-items:center;justify-content:center;color:#ccc;font-size:3rem;">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="product-card-body">
                                <div class="product-card-name"><?= h($product['name']) ?></div>
                                <?php if (!empty($product['category_names'])): ?>
                                    <div class="product-card-categories">
                                        <?php foreach ($product['category_names'] as $catName): ?>
                                            <span class="product-card-cat"><?= h($catName) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="product-card-price"><?= number_format($product['base_price'], 2, ',', ' ') ?> &euro;</div>
                            </div>
                            <div class="product-card-cta">Personnaliser</div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> PERSONNALY &mdash; Personnalisation textile</p>
        </div>
    </footer>

</body>
</html>
