<?php
/**
 * PERSONNALY - Page d'accueil publique
 * Personnalisation textile familiale
 */

// Chargement des dépendances
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
    <title>PERSONNALY - Personnalisation Textile</title>
    <meta name="description" content="Personnalisation textile pour toute la famille. Créez vos vêtements uniques.">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }

        /* Header */
        header {
            background: #1a1a2e;
            color: white;
            padding: 20px 0;
        }
        header h1 { font-size: 1.8rem; }
        nav { margin-top: 10px; }
        nav a {
            color: #eee;
            text-decoration: none;
            margin-right: 20px;
        }
        nav a:hover { color: #fff; }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        .hero h2 { font-size: 2.5rem; margin-bottom: 20px; }
        .hero p { font-size: 1.2rem; opacity: 0.9; }

        /* Products */
        .products { padding: 60px 0; }
        .products h3 { text-align: center; margin-bottom: 40px; font-size: 2rem; }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        .product-card {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            transition: box-shadow 0.3s;
        }
        .product-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f5f5f5;
        }
        .product-info { padding: 20px; }
        .product-info h4 { margin-bottom: 10px; }
        .product-price {
            font-size: 1.3rem;
            color: #667eea;
            font-weight: bold;
        }

        /* Footer */
        footer {
            background: #1a1a2e;
            color: #aaa;
            padding: 40px 0;
            text-align: center;
        }
        footer a { color: #667eea; }

        /* Message si pas de produits */
        .no-products {
            text-align: center;
            padding: 60px;
            color: #666;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>PERSONNALY</h1>
            <nav>
                <a href="/">Accueil</a>
                <a href="#produits">Produits</a>
                <a href="#contact">Contact</a>
                <a href="/admin/login.php">Admin</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h2>Personnalisation Textile pour Toute la Famille</h2>
            <p>Hommes • Femmes • Enfants — Créez des vêtements uniques</p>
        </div>
    </section>

    <section class="products" id="produits">
        <div class="container">
            <h3>Nos Produits</h3>

            <?php if (empty($products)): ?>
                <div class="no-products">
                    <p>Aucun produit disponible pour le moment.</p>
                    <p>Revenez bientôt !</p>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <img src="/assets/img/placeholder.png" alt="<?= h($product['name']) ?>">
                            <div class="product-info">
                                <h4><?= h($product['name']) ?></h4>
                                <p><?= h($product['description'] ?? '') ?></p>
                                <p class="product-price"><?= formatPrice($product['base_price']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer id="contact">
        <div class="container">
            <p>&copy; <?= date('Y') ?> PERSONNALY - Tous droits réservés</p>
            <p><a href="https://personnaly.fr">personnaly.fr</a></p>
        </div>
    </footer>
</body>
</html>
