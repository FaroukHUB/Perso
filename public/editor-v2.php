<?php
/**
 * PERSONNALY - Page Éditeur V2 (POC)
 * Route standalone pour tester le nouvel éditeur
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/ProductPrintZone.php';
require_once __DIR__ . '/../app/models/ProductColorImage.php';

// Récupération du produit (si fourni, sinon produit par défaut)
$productId = (int) get('id', 1); // Par défaut produit ID 1
$productModel = new Product();
$product = $productModel->findById($productId);

// Produit introuvable ou inactif → redirection
if (!$product || !$product['active']) {
    redirect('/');
}

// Zones d'impression
$printZoneModel = new ProductPrintZone();
$allZones = $printZoneModel->findByProduct($productId);

// Images couleur pour face/dos
$colorImageModel = new ProductColorImage();
$allColorImages = $colorImageModel->getByProduct($productId);

// Organiser les images par vue
$productImages = [
    'front' => '/public/assets/images/products/default-front.png',
    'back' => null
];

foreach ($allColorImages as $img) {
    $view = strtolower($img['view'] ?? 'front');
    if ($view === 'front' || $view === 'back') {
        $productImages[$view] = $img['image_path'];
    }
}

// Zone d'impression principale (front par défaut)
$printZone = null;
foreach ($allZones as $z) {
    if (strtolower($z['zone_name']) === 'front') {
        $printZone = [
            'id' => (int) $z['id'],
            'x' => (float) $z['pos_x'],
            'y' => (float) $z['pos_y'],
            'width' => (float) $z['width'],
            'height' => (float) $z['height']
        ];
        break;
    }
}

// Prix de base
$basePrice = (float) $product['base_price'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditeur V2 - <?= h($product['name']) ?></title>
    <link rel="stylesheet" href="/editor-v2/editor.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .v2-header {
            background: #1A1A2E;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .v2-header h1 {
            margin: 0;
            font-size: 18px;
        }
        .v2-back-link {
            color: #3DFFC0;
            text-decoration: none;
            font-size: 14px;
        }
        .v2-back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="v2-header">
    <h1>Éditeur V2 - <?= h($product['name']) ?></h1>
    <a href="/public/product.php?id=<?= $productId ?>" class="v2-back-link">← Retour éditeur classique</a>
</div>

<!-- Inclure l'éditeur V2 -->
<div class="ps-editor">

    <!-- PREVIEW -->
    <div class="ps-preview">
        <div class="ps-product-frame">
            <img class="ps-product-image" src="<?= h($productImages['front']) ?>" alt="Produit" id="productImage" />
            <div class="ps-print-area" id="printArea">
                <!-- layers texte / design -->
            </div>
            <!-- Debug border -->
            <div class="ps-print-area-debug"></div>
        </div>
    </div>

    <!-- CONTROLS -->
    <div class="ps-controls">

        <!-- View Toggle -->
        <div class="ps-control-group">
            <h3>Vue</h3>
            <div class="ps-view-toggle">
                <button id="btnFront" class="ps-btn active">Face</button>
                <button id="btnBack" class="ps-btn" <?= $productImages['back'] ? '' : 'disabled' ?>>Dos</button>
            </div>
        </div>

        <!-- Texte -->
        <div class="ps-control-group">
            <h3>Texte</h3>
            <button id="btnAddText" class="ps-btn ps-btn-primary">+ Ajouter du texte</button>

            <div id="textControls" class="ps-text-controls" style="display: none;">
                <input type="text" id="textInput" class="ps-input" placeholder="Votre texte...">
                <div class="ps-text-style">
                    <select id="fontFamily" class="ps-select">
                        <option value="Arial">Arial</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Comic Sans MS">Comic Sans MS</option>
                    </select>
                    <input type="color" id="textColor" class="ps-color" value="#000000">
                </div>
            </div>
        </div>

        <!-- Prix -->
        <div class="ps-control-group">
            <h3>Prix</h3>
            <div class="ps-price">
                <div class="ps-price-line">
                    <span>Base</span>
                    <span id="priceBase"><?= number_format($basePrice, 2, ',', ' ') ?> €</span>
                </div>
                <div class="ps-price-line">
                    <span>Technique</span>
                    <span id="priceTechnique">0,00 €</span>
                </div>
                <div class="ps-price-line ps-price-total">
                    <span>Total</span>
                    <span id="priceTotal"><?= number_format($basePrice, 2, ',', ' ') ?> €</span>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Data pour JS -->
<script>
window.__PRODUCT_DATA_V2 = {
    productId: <?= $productId ?>,
    productName: <?= json_encode($product['name']) ?>,
    basePrice: <?= $basePrice ?>,
    imageFront: <?= json_encode($productImages['front']) ?>,
    imageBack: <?= json_encode($productImages['back']) ?>,
    printZone: <?= json_encode($printZone) ?>
};
</script>

<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script src="/editor-v2/editor.js"></script>

</body>
</html>
