<?php
/**
 * PERSONNALY - Page 404
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/helpers/FontLoader.php';
require_once __DIR__ . '/../app/services/BrandingService.php';

$cartCount = Cart::count();
$brandingService = new BrandingService();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page non trouvée - PERSONNALY</title>
    <?= FontLoader::renderHead() ?>
    <?= $brandingService->renderStyleTag() ?>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .error-page {
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 60px 20px;
        }
        .error-content {
            max-width: 500px;
        }
        .error-code {
            font-size: 120px;
            font-weight: 700;
            color: var(--primary-color, #ff69b4);
            line-height: 1;
            margin-bottom: 20px;
        }
        .error-title {
            font-size: 28px;
            margin-bottom: 15px;
            color: #333;
        }
        .error-text {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../app/templates/header.php'; ?>

    <main class="error-page">
        <div class="error-content">
            <div class="error-code">404</div>
            <h1 class="error-title">Page non trouvée</h1>
            <p class="error-text">
                Désolé, la page que vous recherchez n'existe pas ou a été déplacée.
            </p>
            <div class="error-actions">
                <a href="/" class="btn btn-primary">Retour à l'accueil</a>
                <a href="/produits" class="btn btn-outline">Voir nos produits</a>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../app/templates/footer.php'; ?>
</body>
</html>
