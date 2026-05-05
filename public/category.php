<?php
/**
 * PERSONNALY - Page Catégorie
 * Affiche les produits d'une catégorie spécifique
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Category.php';

$cartCount = Cart::count();
$categoryModel = new Category();
$categories = $categoryModel->findAllActive();

// Récupérer le slug de l'URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: /');
    exit;
}

// Trouver la catégorie
$category = $categoryModel->findBySlug($slug);

if (!$category || $category['status'] !== 'active') {
    header('HTTP/1.0 404 Not Found');
    $pageTitle = 'Catégorie non trouvée';
    $products = [];
} else {
    $pageTitle = htmlspecialchars($category['name']);
    $products = $categoryModel->getProducts($category['id']);

    // Ajouter les noms de catégories pour chaque produit
    foreach ($products as &$product) {
        $product['category_names'] = $categoryModel->getCategoryNamesByProduct($product['id']);
    }
    unset($product);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - PERSONNALY</title>
    <meta name="description" content="<?= $category ? htmlspecialchars($category['description'] ?? 'Découvrez notre collection ' . $category['name']) : 'Catégorie non trouvée' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        body { padding-top: 70px; }

        /* ===== CATEGORY HEADER ===== */
        .category-header {
            background: linear-gradient(135deg, rgba(255,105,180,0.1), rgba(61,255,192,0.05));
            padding: 60px 0;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .category-header h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 5vw, 3rem);
            margin-bottom: 15px;
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .category-header p {
            color: rgba(255,255,255,0.7);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        .category-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid var(--pink-main);
        }
        .product-count {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 50px;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.8);
        }

        /* ===== PRODUCTS GRID ===== */
        .products-section {
            padding: 60px 0;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        .product-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(255,105,180,0.2);
            border-color: var(--pink-main);
        }
        .product-card-image {
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
            background: rgba(255,255,255,0.05);
        }
        .product-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .product-card:hover .product-card-image img {
            transform: scale(1.05);
        }
        .product-card-content {
            padding: 20px;
        }
        .product-card-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 10px;
        }
        .product-card-categories span {
            font-size: 0.75rem;
            padding: 3px 10px;
            background: rgba(255,105,180,0.15);
            color: var(--pink-main);
            border-radius: 50px;
        }
        .product-card h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: var(--white);
        }
        .product-card-price {
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 700;
            background: var(--gradient-mint);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .product-card-price span {
            font-size: 0.9rem;
            font-weight: 400;
            -webkit-text-fill-color: rgba(255,255,255,0.5);
        }
        .product-card-btn {
            display: block;
            margin-top: 15px;
            padding: 12px;
            background: var(--gradient-pink);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .product-card-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 20px rgba(255,105,180,0.4);
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        .empty-state svg {
            width: 80px;
            height: 80px;
            color: rgba(255,255,255,0.3);
            margin-bottom: 20px;
        }
        .empty-state h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--white);
        }
        .empty-state p {
            color: rgba(255,255,255,0.6);
            margin-bottom: 30px;
        }
        .empty-state a {
            display: inline-block;
            padding: 12px 30px;
            background: var(--gradient-pink);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
        }

        /* ===== 404 STATE ===== */
        .not-found {
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <?php if (!$category): ?>
        <!-- 404 Not Found -->
        <section class="not-found">
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M16 16s-1.5-2-4-2-4 2-4 2"/>
                    <line x1="9" y1="9" x2="9.01" y2="9"/>
                    <line x1="15" y1="9" x2="15.01" y2="9"/>
                </svg>
                <h2>Catégorie non trouvée</h2>
                <p>Cette catégorie n'existe pas ou a été supprimée.</p>
                <a href="/">Retour à l'accueil</a>
            </div>
        </section>
    <?php else: ?>
        <!-- Category Header -->
        <section class="category-header">
            <div class="container">
                <?php if (!empty($category['image_url'])): ?>
                    <img src="<?= htmlspecialchars($category['image_url']) ?>" alt="<?= htmlspecialchars($category['name']) ?>" class="category-image">
                <?php endif; ?>
                <h1><?= htmlspecialchars($category['name']) ?></h1>
                <?php if (!empty($category['description'])): ?>
                    <p><?= htmlspecialchars($category['description']) ?></p>
                <?php endif; ?>
                <span class="product-count"><?= count($products) ?> produit<?= count($products) > 1 ? 's' : '' ?></span>
            </div>
        </section>

        <!-- Products Grid -->
        <section class="products-section">
            <div class="container">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                            <line x1="7" y1="7" x2="7.01" y2="7"/>
                        </svg>
                        <h2>Aucun produit</h2>
                        <p>Cette catégorie ne contient pas encore de produits.</p>
                        <a href="/">Voir tous les produits</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <article class="product-card">
                                <div class="product-card-image">
                                    <?php if (!empty($product['image_front_url'])): ?>
                                        <img src="<?= htmlspecialchars($product['image_front_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                    <?php else: ?>
                                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.3);">
                                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                <polyline points="21 15 16 10 5 21"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-card-content">
                                    <?php if (!empty($product['category_names'])): ?>
                                        <div class="product-card-categories">
                                            <?php foreach (array_slice($product['category_names'], 0, 2) as $catName): ?>
                                                <span><?= htmlspecialchars($catName) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                                    <div class="product-card-price">
                                        <?= number_format($product['base_price'], 2, ',', ' ') ?> € <span>TTC</span>
                                    </div>
                                    <a href="/configurator.php?product=<?= $product['id'] ?>" class="product-card-btn">Personnaliser</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
