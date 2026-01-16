<?php
/**
 * PERSONNALY - Page d'accueil publique
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';

// Récupération des produits actifs
$productModel = new Product();
$products = $productModel->findActive();
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
        /* Page-specific styles */
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

        .navbar-nav a:hover {
            color: var(--pink-main);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
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
            width: 800px;
            height: 800px;
            background: var(--pink-main);
            border-radius: 50%;
            filter: blur(200px);
            opacity: 0.2;
            top: -300px;
            right: -200px;
            animation: pulse 8s ease-in-out infinite;
        }

        .hero::after {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: var(--mint-main);
            border-radius: 50%;
            filter: blur(180px);
            opacity: 0.15;
            bottom: -200px;
            left: -100px;
            animation: pulse 6s ease-in-out infinite reverse;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 20px;
            border-radius: var(--radius-full);
            margin-bottom: var(--spacing-lg);
            color: var(--mint-main);
            font-size: 14px;
            font-weight: 600;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: var(--spacing-lg);
            line-height: 1.1;
        }

        .hero h1 span {
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: var(--spacing-xl);
            line-height: 1.7;
        }

        .hero-buttons {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
            flex-wrap: wrap;
        }

        .hero-categories {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
            margin-top: var(--spacing-xxl);
        }

        .category-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.08);
            padding: 12px 24px;
            border-radius: var(--radius-full);
            color: var(--white);
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all var(--transition-normal);
        }

        .category-pill:hover {
            background: rgba(255, 105, 180, 0.2);
            border-color: var(--pink-main);
        }

        /* Products Section */
        .products-section {
            padding: 100px 0;
            background: var(--gray-light);
        }

        .section-header {
            text-align: center;
            margin-bottom: var(--spacing-xxl);
        }

        .section-header h2 {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-md);
        }

        .section-header p {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-lg);
        }

        .product-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all var(--transition-normal);
            box-shadow: var(--shadow-sm);
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .product-image {
            height: 250px;
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .product-image::before {
            content: '👕';
            font-size: 4rem;
            opacity: 0.5;
        }

        .product-category {
            position: absolute;
            top: 15px;
            left: 15px;
        }

        .product-info {
            padding: var(--spacing-lg);
        }

        .product-info h3 {
            font-size: 1.1rem;
            margin-bottom: var(--spacing-sm);
            color: var(--black-soft);
        }

        .product-info p {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: var(--spacing-md);
            line-height: 1.5;
        }

        .product-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .product-price {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--pink-dark);
        }

        .product-btn {
            background: var(--gradient-mint);
            color: var(--black);
            padding: 10px 20px;
            border-radius: var(--radius-full);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all var(--transition-normal);
        }

        .product-btn:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-mint);
        }

        /* Footer */
        .footer {
            background: var(--gradient-dark);
            color: var(--white);
            padding: 60px 0 30px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .footer-brand h3 {
            font-family: var(--font-display);
            font-size: 1.5rem;
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: var(--spacing-sm);
        }

        .footer-brand p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
        }

        .footer-links h4 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--mint-main);
            margin-bottom: var(--spacing-md);
        }

        .footer-links a {
            display: block;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            padding: 6px 0;
            font-size: 14px;
            transition: color var(--transition-fast);
        }

        .footer-links a:hover {
            color: var(--pink-main);
        }

        .footer-bottom {
            text-align: center;
            padding-top: var(--spacing-lg);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.4);
            font-size: 14px;
        }

        /* Empty state */
        .empty-products {
            text-align: center;
            padding: var(--spacing-xxl);
        }

        .empty-products-icon {
            font-size: 5rem;
            margin-bottom: var(--spacing-lg);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .navbar-nav { display: none; }
            .hero-categories { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="/" class="navbar-brand">PERSONNALY</a>
            <div class="navbar-nav">
                <a href="#produits">Produits</a>
                <a href="#categories">Catégories</a>
                <a href="#contact">Contact</a>
                <a href="/admin/login.php" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px;">
                    Admin
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">
                    ✨ Nouveau — Personnalisation en ligne
                </div>
                <h1>Créez des vêtements <span>uniques</span> pour toute la famille</h1>
                <p>
                    Personnalisez vos t-shirts, sweats et polos avec vos propres designs.
                    Qualité premium, livraison rapide, satisfaction garantie.
                </p>
                <div class="hero-buttons">
                    <a href="#produits" class="btn btn-primary">
                        Découvrir nos produits
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <a href="#categories" class="btn btn-dark">
                        Voir les catégories
                    </a>
                </div>

                <div class="hero-categories" id="categories">
                    <div class="category-pill">👨 Homme</div>
                    <div class="category-pill">👩 Femme</div>
                    <div class="category-pill">👶 Enfant</div>
                    <div class="category-pill">👨‍👩‍👧‍👦 Famille</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products -->
    <section class="products-section" id="produits">
        <div class="container">
            <div class="section-header">
                <h2>Nos <span class="text-gradient">Produits</span></h2>
                <p>Découvrez notre sélection de vêtements personnalisables pour toute la famille</p>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-products">
                    <div class="empty-products-icon">👕</div>
                    <h3>Produits bientôt disponibles</h3>
                    <p class="text-muted">Notre catalogue est en cours de préparation. Revenez très vite !</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <span class="product-category badge badge-pink">
                                    <?= h($product['category'] ?? 'Textile') ?>
                                </span>
                            </div>
                            <div class="product-info">
                                <h3><?= h($product['name']) ?></h3>
                                <p><?= h($product['description'] ?? 'Personnalisable avec votre design') ?></p>
                                <div class="product-footer">
                                    <span class="product-price"><?= formatPrice($product['base_price']) ?></span>
                                    <a href="#" class="product-btn">Personnaliser</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3>PERSONNALY</h3>
                    <p>Personnalisation textile de qualité pour toute la famille.</p>
                </div>

                <div class="footer-links">
                    <h4>Navigation</h4>
                    <a href="#produits">Produits</a>
                    <a href="#categories">Catégories</a>
                    <a href="#">FAQ</a>
                </div>

                <div class="footer-links">
                    <h4>Légal</h4>
                    <a href="#">CGV</a>
                    <a href="#">Mentions légales</a>
                    <a href="#">Politique de confidentialité</a>
                </div>

                <div class="footer-links">
                    <h4>Contact</h4>
                    <a href="mailto:contact@personnaly.fr">contact@personnaly.fr</a>
                    <a href="#">Instagram</a>
                    <a href="#">Facebook</a>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> PERSONNALY - Tous droits réservés</p>
            </div>
        </div>
    </footer>
</body>
</html>
