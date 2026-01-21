<?php
/**
 * PERSONNALY - Page Éditeur V2 (POC)
 * Version DÉCOUPLÉE - aucune dépendance backend
 * Mock statique uniquement
 */

// ============================================
// MOCK STATIQUE - AUCUN BACKEND
// ============================================
$productId = 1;
$productName = 'T-Shirt Classic';
$basePrice = 29.90;

// Image produit mockée (SVG local)
$productImages = [
    'front' => '/editor-v2/tshirt-front.svg',
    'back' => null
];

// Zone d'impression mockée (en %)
$printZone = [
    'id' => 1,
    'x' => 30,
    'y' => 20,
    'width' => 40,
    'height' => 50
];

// Helper d'échappement HTML (standalone)
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditeur V2 - <?= h($productName) ?></title>
    <link rel="stylesheet" href="/editor-v2/editor.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
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
        .v2-badge {
            background: #3DFFC0;
            color: #1A1A2E;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
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
    <div style="display: flex; align-items: center;">
        <h1>Éditeur V2 - <?= h($productName) ?></h1>
        <span class="v2-badge">POC</span>
    </div>
    <a href="/" class="v2-back-link">← Retour accueil</a>
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
                <button id="btnBack" class="ps-btn" disabled>Dos</button>
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
    productName: <?= json_encode($productName) ?>,
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
