<?php
/**
 * PERSONNALY - Page Produit avec Personnalisation
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 * P2: Drag & Drop contraint à la zone d'impression
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/helpers/FontLoader.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/CustomizationOption.php';
require_once __DIR__ . '/../app/models/Font.php';
require_once __DIR__ . '/../app/models/ProductPrintZone.php';
require_once __DIR__ . '/../app/models/ProductColor.php';
require_once __DIR__ . '/../app/models/ProductColorImage.php';

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

// Options de personnalisation depuis la base de données
$optionModel = new CustomizationOption();
$sizesFromDb = $optionModel->getSizes();
$textColorsFromDb = $optionModel->getTextColors();
$techniquesFromDb = $optionModel->getTechniques();

// Couleurs du produit avec images (nouveau système prioritaire)
$productColorImageModel = new ProductColorImage();
$colorVariants = $productColorImageModel->findByProduct($productId);
$hasColorVariants = !empty($colorVariants);

// Couleurs du produit (ancien système - fallback)
$productColorModel = new ProductColor();
$productColorsFromDb = $productColorModel->findByProduct($productId);

// Détermine quel système de couleurs utiliser
if ($hasColorVariants) {
    // Nouveau système : variantes couleur avec images
    $colorsFromDb = $colorVariants;
    $usingColorVariants = true;
    $usingProductColors = true;
} elseif (!empty($productColorsFromDb)) {
    // Ancien système : couleurs produit sans images
    $colorsFromDb = $productColorsFromDb;
    $usingColorVariants = false;
    $usingProductColors = true;
} else {
    // Fallback : couleurs globales
    $colorsFromDb = $optionModel->getColors();
    $usingColorVariants = false;
    $usingProductColors = false;
}

// Polices depuis la nouvelle table fonts (système administrable)
$fontModel = new Font();
$fontsFromDb = $fontModel->findActive();

// Fallback si la table n'existe pas encore
$sizes = !empty($sizesFromDb) ? array_column($sizesFromDb, 'value') : ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

// Construction du tableau des couleurs avec support des images par variante
$colors = [];
$colorImages = []; // Pour stocker les images de chaque variante
$defaultColorKey = null;

if (!empty($colorsFromDb)) {
    foreach ($colorsFromDb as $c) {
        // Support des trois formats (product_color_images, product_colors, customization_options)
        $colorName = $c['color_name'] ?? $c['value'] ?? 'unknown';
        $colors[$colorName] = $c['hex_code'] ?? '#CCCCCC';

        // Si c'est une variante avec images
        if ($hasColorVariants) {
            $colorImages[$colorName] = [
                'front' => !empty($c['image_front_url']) ? '/public' . $c['image_front_url'] : '',
                'back' => !empty($c['image_back_url']) ? '/public' . $c['image_back_url'] : '',
            ];
            // Marquer le défaut
            if (!empty($c['is_default'])) {
                $defaultColorKey = $colorName;
            }
        }
    }
} else {
    $colors = ['blanc' => '#FFFFFF', 'noir' => '#1A1A2E', 'rose' => '#FF69B4', 'menthe' => '#3DFFC0', 'bleu' => '#4A90D9', 'gris' => '#6B7280'];
}

// Si pas de couleur par défaut explicite, prendre la première
if ($hasColorVariants && !$defaultColorKey && !empty($colors)) {
    $defaultColorKey = array_key_first($colors);
}

// Adapter les polices au format attendu par le template
$fonts = [];
if (!empty($fontsFromDb)) {
    foreach ($fontsFromDb as $f) {
        $fonts[] = [
            'value' => $f['family'],
            'label' => $f['name'],
            'category' => $f['category']
        ];
    }
} else {
    // Fallback si table vide
    $fonts = [
        ['value' => 'Poppins', 'label' => 'Poppins', 'category' => 'sans-serif'],
        ['value' => 'Playfair Display', 'label' => 'Playfair Display', 'category' => 'serif'],
    ];
}

// Couleurs de texte (pour personnalisation)
$textColors = [];
if (!empty($textColorsFromDb)) {
    foreach ($textColorsFromDb as $tc) {
        $textColors[] = [
            'value' => $tc['value'],
            'label' => $tc['label'],
            'hex' => $tc['hex_code'] ?? '#000000'
        ];
    }
} else {
    // Fallback si table vide
    $textColors = [
        ['value' => 'noir', 'label' => 'Noir', 'hex' => '#1A1A2E'],
        ['value' => 'blanc', 'label' => 'Blanc', 'hex' => '#FFFFFF'],
        ['value' => 'rose', 'label' => 'Rose', 'hex' => '#FF69B4'],
        ['value' => 'menthe', 'label' => 'Menthe', 'hex' => '#3DFFC0'],
        ['value' => 'or', 'label' => 'Or', 'hex' => '#FFD700'],
        ['value' => 'argent', 'label' => 'Argent', 'hex' => '#C0C0C0'],
    ];
}

// Techniques d'impression
$techniques = [];
if (!empty($techniquesFromDb)) {
    foreach ($techniquesFromDb as $t) {
        $techniques[] = [
            'value' => $t['value'],
            'label' => $t['label'],
            'description' => $t['description'] ?? '',
            'price' => (float)($t['price'] ?? 0)
        ];
    }
} else {
    // Fallback si table vide
    $techniques = [
        ['value' => 'flex', 'label' => 'Flex', 'description' => 'Idéal pour textes et logos simples', 'price' => 0],
        ['value' => 'flock', 'label' => 'Flock', 'description' => 'Effet velours, toucher doux', 'price' => 2],
        ['value' => 'broderie', 'label' => 'Broderie', 'description' => 'Finition premium et durable', 'price' => 5],
    ];
}

// Zones d'impression depuis la base de données (front et back)
$printZoneModel = new ProductPrintZone();
$allZones = $printZoneModel->findByProduct($productId);

// Organiser les zones par vue (front/back)
$zones = ['front' => null, 'back' => null];
foreach ($allZones as $z) {
    $zoneName = strtolower($z['zone_name'] ?? 'front');
    if (in_array($zoneName, ['front', 'back'])) {
        $zones[$zoneName] = [
            'id' => (int) $z['id'],
            'x' => (float) $z['pos_x'],
            'y' => (float) $z['pos_y'],
            'width' => (float) $z['width'],
            'height' => (float) $z['height'],
            'max_chars' => (int) $z['max_chars'],
            'max_lines' => (int) $z['max_lines'],
            'label' => $z['zone_label']
        ];
    }
}

// Fallback pour la zone front si non définie
if (!$zones['front']) {
    $defaultZone = ProductPrintZone::getDefaultZone();
    $zones['front'] = [
        'id' => 0,
        'x' => (float) $defaultZone['pos_x'],
        'y' => (float) $defaultZone['pos_y'],
        'width' => (float) $defaultZone['width'],
        'height' => (float) $defaultZone['height'],
        'max_chars' => (int) $defaultZone['max_chars'],
        'max_lines' => (int) $defaultZone['max_lines'],
        'label' => $defaultZone['zone_label']
    ];
}

// Zone active par défaut
$printZone = $zones['front'];

// Vérifier si le produit a une image dos
$hasBackImage = !empty($product['image_back_url']);

// Traitement du formulaire d'ajout au panier
if (isPost() && isset($_POST['add_to_cart'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        // Récupérer la position en % (drag & drop)
        $posX = (float) post('position_x', 50);
        $posY = (float) post('position_y', 50);
        $zoneId = (int) post('position_zone_id', 1);

        // Valider que les coordonnées sont dans des limites raisonnables
        $posX = max(0, min(100, $posX));
        $posY = max(0, min(100, $posY));

        $customization = [
            'size' => post('size', 'M'),
            'color' => post('color', 'blanc'),
            'text' => trim(post('custom_text', '')),
            'text_color' => post('text_color', 'noir'),
            'font' => post('font', 'Poppins'),
            'technique' => post('technique', 'flex'),
            'view' => post('view', 'front'),
            'position' => [
                'x' => round($posX, 1),
                'y' => round($posY, 1),
                'zone_id' => $zoneId
            ],
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= h($product['name']) ?> - PERSONNALY</title>
    <meta name="description" content="<?= h($product['description'] ?? 'Personnalisez ce produit selon vos envies') ?>">
    <!-- Polices système (Inter pour UI) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Polices personnalisation (chargées dynamiquement depuis admin) -->
    <?= FontLoader::renderHead() ?>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/techniques.css?v=3">
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

        /* ===========================================
           3-COLUMN CONFIGURATOR LAYOUT (2026-ready)
           Left: Visual options | Center: Product Hero | Right: Product options
           =========================================== */

        .product-page { padding: 30px 0 100px; }

        /* Desktop: 3 columns */
        .configurator-layout {
            display: grid;
            grid-template-columns: 300px 1fr 320px;
            gap: 30px;
            align-items: start;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Column containers */
        .config-left,
        .config-right {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            position: sticky;
            top: 90px;
        }

        .config-center {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Section headers in columns */
        .config-section {
            margin-bottom: 20px;
        }
        .config-section:last-child {
            margin-bottom: 0;
        }
        .config-section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .config-section-title::before {
            content: '';
            width: 3px;
            height: 14px;
            background: var(--gradient-pink);
            border-radius: 2px;
        }

        /* Product info in right column */
        .product-info-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }
        .product-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--black-soft);
            margin-bottom: 8px;
            line-height: 1.2;
        }
        .product-price {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            color: var(--pink-dark);
        }

        /* Legacy grid fallback - not used */
        .product-grid {
            display: none;
        }

        /* Product Hero (Center Column) */
        .product-hero {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
            width: 100%;
            max-width: 500px;
        }

        /* Legacy class - keep for compatibility */
        .product-image-box {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
        }

        /* View Toggle (Face/Dos) */
        .view-toggle {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 25px;
        }
        .view-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-full);
            background: white;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.2s;
        }
        .view-btn:hover {
            border-color: var(--pink-light);
            color: var(--pink-main);
        }
        .view-btn.active {
            background: var(--gradient-pink);
            border-color: transparent;
            color: white;
            box-shadow: var(--shadow-pink);
        }
        .view-btn .view-icon {
            font-size: 1.1em;
        }
        .product-preview {
            width: 100%;
            max-width: 450px;
            height: 450px;
            margin: 0 auto;
            background: linear-gradient(135deg, #f8f8f8 0%, #e8e8e8 100%);
            border-radius: var(--radius-lg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: background-color 0.3s ease;
            overflow: hidden;
            touch-action: none; /* Empêche le scroll pendant le drag */
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        .preview-icon { font-size: 6rem; opacity: 0.6; }
        .preview-product-img {
            max-width: 85%;
            max-height: 360px;
            object-fit: contain;
            border-radius: var(--radius-md);
            pointer-events: none; /* L'image ne capture pas les events */
        }

        /* Zone d'impression (overlay) */
        .print-zone-overlay {
            position: absolute;
            border: 2px dashed rgba(255, 105, 180, 0.4);
            background: rgba(255, 105, 180, 0.03);
            border-radius: 8px;
            pointer-events: none;
            transition: border-color 0.3s, background-color 0.3s;
        }
        .print-zone-overlay.active {
            border-color: rgba(255, 105, 180, 0.7);
            background: rgba(255, 105, 180, 0.08);
        }
        .print-zone-label {
            position: absolute;
            bottom: 100%;
            left: 0;
            font-size: 10px;
            color: var(--pink-main);
            background: white;
            padding: 2px 6px;
            border-radius: 4px 4px 0 0;
            opacity: 0.8;
        }

        /* Texte draggable */
        .preview-text {
            position: absolute;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.5rem;
            max-width: 70%;
            text-align: center;
            word-break: break-word;
            color: var(--pink-dark);
            cursor: grab;
            user-select: none;
            -webkit-user-select: none;
            padding: 8px 12px;
            border-radius: 6px;
            transition: box-shadow 0.2s, transform 0.1s;
            transform: translate(-50%, -50%);
            text-shadow: 1px 1px 2px rgba(255,255,255,0.9);
            z-index: 10;
        }
        .preview-text:hover {
            box-shadow: 0 0 0 3px rgba(255, 105, 180, 0.2);
        }
        .preview-text.dragging {
            cursor: grabbing;
            box-shadow: 0 8px 25px rgba(255, 105, 180, 0.4);
            transform: translate(-50%, -50%) scale(1.05);
            z-index: 20;
        }
        .preview-text.empty {
            opacity: 0.4;
            font-style: italic;
        }

        /* Indication drag */
        .drag-hint {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            background: linear-gradient(135deg, rgba(255,105,180,0.1) 0%, rgba(61,255,192,0.1) 100%);
            border-radius: var(--radius-md);
            font-size: 14px;
            color: var(--gray);
            margin-top: 15px;
        }
        .drag-hint svg {
            color: var(--pink-main);
        }

        /* Bouton Zoom */
        .zoom-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 15;
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            transition: all 0.2s;
            color: var(--pink-main);
        }
        .zoom-btn:hover {
            background: var(--gradient-pink);
            color: white;
            transform: scale(1.1);
            box-shadow: var(--shadow-pink);
        }

        .product-category-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 5;
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

        /* Font Selection - Dropdown Scalable */
        .font-selector-wrapper {
            position: relative;
            margin-bottom: 25px;
        }
        .font-selector-trigger {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
        }
        .font-selector-trigger:hover {
            border-color: var(--pink-main);
        }
        .font-selector-trigger.open {
            border-color: var(--pink-main);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
        }
        .font-selector-preview {
            font-size: 18px;
            font-weight: 500;
        }
        .font-selector-arrow {
            transition: transform 0.2s;
        }
        .font-selector-trigger.open .font-selector-arrow {
            transform: rotate(180deg);
        }
        .font-selector-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid var(--pink-main);
            border-top: none;
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            max-height: 300px;
            overflow: hidden;
            display: none;
            z-index: 100;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .font-selector-dropdown.open {
            display: block;
        }
        .font-search-box {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }
        .font-search-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e5e5e5;
            border-radius: var(--radius-sm);
            font-size: 14px;
        }
        .font-search-input:focus {
            outline: none;
            border-color: var(--pink-main);
        }
        .font-list {
            max-height: 220px;
            overflow-y: auto;
        }
        .font-list-item {
            padding: 14px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.15s;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        .font-list-item:hover {
            background: rgba(255, 105, 180, 0.08);
        }
        .font-list-item.selected {
            background: linear-gradient(135deg, rgba(255,105,180,0.15) 0%, rgba(61,255,192,0.15) 100%);
        }
        .font-list-item.hidden {
            display: none;
        }
        .font-item-name {
            font-size: 16px;
        }
        .font-item-category {
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 3px 8px;
            background: rgba(0,0,0,0.05);
            border-radius: var(--radius-full);
        }
        .font-hidden-input {
            display: none;
        }

        /* Text Color Selection */
        .text-color-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
        }
        .text-color-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
            border: 3px solid transparent;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
        }
        .text-color-option:hover { transform: scale(1.1); }
        .text-color-option.selected {
            border-color: var(--pink-main);
            transform: scale(1.15);
            box-shadow: 0 4px 15px rgba(255, 105, 180, 0.4);
        }
        .text-color-option input { display: none; }
        .text-color-option[data-color="#FFFFFF"] {
            border: 2px solid #ddd;
        }
        .text-color-option .color-label {
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            color: var(--gray);
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .text-color-option:hover .color-label,
        .text-color-option.selected .color-label {
            opacity: 1;
        }

        /* Technique Selection */
        .technique-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 25px;
        }
        .technique-option {
            padding: 16px 20px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .technique-option:hover { border-color: var(--pink-main); }
        .technique-option.selected {
            background: linear-gradient(135deg, rgba(255,105,180,0.1) 0%, rgba(61,255,192,0.1) 100%);
            border-color: var(--pink-main);
        }
        .technique-option input { display: none; }
        .technique-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .technique-name {
            font-weight: 600;
            color: var(--black-soft);
        }
        .technique-desc {
            font-size: 12px;
            color: var(--gray);
        }
        .technique-price {
            font-weight: 700;
            color: var(--mint-dark);
            font-size: 14px;
        }
        .technique-price.free {
            color: var(--mint-main);
        }

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

        /* ===========================================
           RESPONSIVE / MOBILE ACCORDION
           =========================================== */

        /* Tablet: 2 columns */
        @media (max-width: 1200px) {
            .configurator-layout {
                grid-template-columns: 280px 1fr 280px;
                gap: 20px;
            }
        }

        /* Small tablet: Stack layout */
        @media (max-width: 1024px) {
            .configurator-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .config-left,
            .config-right {
                position: static;
            }
            .config-center {
                order: -1; /* Product hero first */
            }
            .product-hero {
                max-width: 100%;
            }
        }

        /* Mobile: Accordion + Sticky CTA */
        @media (max-width: 768px) {
            .product-page { padding: 15px 0 120px; }
            .configurator-layout { gap: 15px; }

            .config-left,
            .config-right {
                padding: 0;
                background: transparent;
            }

            .product-hero {
                padding: 20px;
            }
            .product-preview {
                height: 350px;
                max-width: 100%;
            }
            .preview-product-img {
                max-height: 280px;
            }

            /* Accordion sections */
            .mobile-accordion {
                background: white;
                border-radius: var(--radius-md);
                margin-bottom: 10px;
                overflow: hidden;
            }
            .accordion-header {
                padding: 16px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                cursor: pointer;
                background: white;
                font-weight: 600;
                color: var(--black-soft);
            }
            .accordion-header::after {
                content: '▼';
                font-size: 10px;
                color: var(--gray);
                transition: transform 0.2s;
            }
            .accordion-header.collapsed::after {
                transform: rotate(-90deg);
            }
            .accordion-content {
                padding: 0 20px 20px;
                transition: max-height 0.3s ease;
            }
            .accordion-content.collapsed {
                max-height: 0;
                padding: 0;
                overflow: hidden;
            }

            /* Config section mobile adjustments */
            .config-section {
                margin-bottom: 15px;
            }
            .config-section-title {
                font-size: 0.8rem;
            }

            /* Sticky CTA bar */
            .sticky-cta {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                padding: 15px 20px;
                box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
            }
            .sticky-cta .price-display {
                font-family: var(--font-display);
                font-size: 1.4rem;
                font-weight: 800;
                color: var(--pink-dark);
            }
            .sticky-cta .btn {
                flex: 1;
                max-width: 200px;
            }

            /* Hide desktop add to cart in mobile */
            .desktop-only-cta {
                display: none;
            }
        }

        /* Desktop: Show desktop CTA, hide sticky */
        @media (min-width: 769px) {
            .sticky-cta {
                display: none;
            }
            .mobile-accordion .accordion-header {
                display: none;
            }
            .accordion-content.collapsed {
                max-height: none;
                padding: initial;
                overflow: visible;
            }
        }

        @media (max-width: 600px) {
            .product-preview { height: 300px; }
            .preview-text { font-size: 1.1rem; }
            .preview-product-img { max-height: 240px; }
            .view-btn { padding: 10px 16px; font-size: 13px; }
            .drag-hint { font-size: 12px; padding: 10px; }
        }

        /* Legacy grid fallback - kept for reference */
        .product-grid { display: none; }
    </style>
</head>
<body<?php echo (isset($_GET['broderie']) && $_GET['broderie'] === 'b') ? ' class="broderie-variant-b"' : ''; ?>>
    <!-- SVG Filters pour techniques de personnalisation -->
    <?php include __DIR__ . '/assets/includes/svg-filters.php'; ?>

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

    <!-- Product Page - 3 Column Layout -->
    <section class="product-page">
        <div class="container">
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <?= h($success) ?> <a href="/public/cart.php">Voir le panier</a>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" id="customizationForm">
                <?= csrfField() ?>
                <input type="hidden" name="add_to_cart" value="1">
                <input type="hidden" name="position_x" id="positionX" value="50">
                <input type="hidden" name="position_y" id="positionY" value="50">
                <input type="hidden" name="position_zone_id" id="positionZoneId" value="<?= $printZone['id'] ?>">
                <input type="hidden" name="view" id="viewInput" value="front">

                <div class="configurator-layout">
                    <!-- ========================================
                         COLONNE GAUCHE: Options visuelles
                         (texte, police, couleur texte, technique)
                         ======================================== -->
                    <div class="config-left">
                        <!-- Texte personnalisé -->
                        <div class="config-section mobile-accordion">
                            <div class="accordion-header">Votre texte</div>
                            <div class="accordion-content">
                                <h3 class="config-section-title">Texte personnalisé</h3>
                                <input type="text"
                                       name="custom_text"
                                       id="customText"
                                       class="custom-text-input"
                                       placeholder="Ex: Famille Dupont, Team Papa..."
                                       maxlength="<?= $printZone['max_chars'] ?? 50 ?>">
                            </div>
                        </div>

                        <!-- Police -->
                        <div class="config-section mobile-accordion">
                            <div class="accordion-header">Police</div>
                            <div class="accordion-content">
                                <h3 class="config-section-title">Style de police</h3>
                                <div class="font-selector-wrapper" id="fontSelector">
                                    <input type="hidden" name="font" id="fontInput" value="<?= h($fonts[0]['value'] ?? 'Poppins') ?>">

                                    <div class="font-selector-trigger" id="fontTrigger">
                                        <span class="font-selector-preview" id="fontPreview"
                                              style="font-family: '<?= h($fonts[0]['value'] ?? 'Poppins') ?>', <?= h($fonts[0]['category'] ?? 'sans-serif') ?>">
                                            <?= h($fonts[0]['label'] ?? 'Sélectionner une police') ?>
                                        </span>
                                        <svg class="font-selector-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6 9 12 15 18 9"/>
                                        </svg>
                                    </div>

                                    <div class="font-selector-dropdown" id="fontDropdown">
                                        <div class="font-search-box">
                                            <input type="text" class="font-search-input" id="fontSearch"
                                                   placeholder="Rechercher une police...">
                                        </div>
                                        <div class="font-list" id="fontList">
                                            <?php foreach ($fonts as $index => $font): ?>
                                                <div class="font-list-item <?= $index === 0 ? 'selected' : '' ?>"
                                                     data-font="<?= h($font['value']) ?>"
                                                     data-label="<?= h($font['label']) ?>"
                                                     data-category="<?= h($font['category'] ?? 'sans-serif') ?>"
                                                     style="font-family: '<?= h($font['value']) ?>', <?= h($font['category'] ?? 'sans-serif') ?>">
                                                    <span class="font-item-name"><?= h($font['label']) ?></span>
                                                    <span class="font-item-category"><?= h($font['category'] ?? 'sans-serif') ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Couleur du texte -->
                        <div class="config-section mobile-accordion">
                            <div class="accordion-header">Couleur du texte</div>
                            <div class="accordion-content">
                                <h3 class="config-section-title">Couleur du texte</h3>
                                <div class="text-color-options">
                                    <?php foreach ($textColors as $index => $tc): ?>
                                        <label class="text-color-option <?= $index === 0 ? 'selected' : '' ?>"
                                               style="background-color: <?= h($tc['hex']) ?>;"
                                               data-color="<?= h($tc['hex']) ?>"
                                               title="<?= h($tc['label']) ?>">
                                            <input type="radio" name="text_color" value="<?= h($tc['value']) ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                            <span class="color-label"><?= h($tc['label']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Technique d'impression -->
                        <div class="config-section mobile-accordion">
                            <div class="accordion-header">Technique</div>
                            <div class="accordion-content">
                                <h3 class="config-section-title">Technique d'impression</h3>
                                <div class="technique-options">
                                    <?php foreach ($techniques as $index => $tech): ?>
                                        <label class="technique-option <?= $index === 0 ? 'selected' : '' ?>"
                                               data-price="<?= $tech['price'] ?>"
                                               data-technique="<?= h($tech['value']) ?>">
                                            <input type="radio" name="technique" value="<?= h($tech['value']) ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                            <div class="technique-info">
                                                <span class="technique-name"><?= h($tech['label']) ?></span>
                                                <?php if (!empty($tech['description'])): ?>
                                                    <span class="technique-desc"><?= h($tech['description']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="technique-price <?= $tech['price'] == 0 ? 'free' : '' ?>">
                                                <?= $tech['price'] == 0 ? 'Inclus' : '+' . formatPrice($tech['price']) ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================
                         COLONNE CENTRALE: Produit Hero
                         (Preview live, Face/Dos, Zoom)
                         ======================================== -->
                    <div class="config-center">
                        <div class="product-hero">
                            <?php if ($hasBackImage): ?>
                            <!-- Bascule Face/Dos -->
                            <div class="view-toggle">
                                <button type="button" class="view-btn active" data-view="front" id="btnFront">
                                    <span class="view-icon">👕</span> Face
                                </button>
                                <button type="button" class="view-btn" data-view="back" id="btnBack">
                                    <span class="view-icon">🔄</span> Dos
                                </button>
                            </div>
                            <?php endif; ?>

                            <div class="product-preview" id="productPreview">
                                <!-- Bouton Zoom -->
                                <button type="button" class="zoom-btn" id="zoomBtn" title="Agrandir la preview">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                        <line x1="11" y1="8" x2="11" y2="14"/>
                                        <line x1="8" y1="11" x2="14" y2="11"/>
                                    </svg>
                                </button>

                                <span class="product-category-badge badge badge-pink">
                                    <?= h($product['category'] ?? 'Textile') ?>
                                </span>

                                <!-- Zone d'impression (overlay visuel) -->
                                <div class="print-zone-overlay" id="printZone"
                                     style="left: <?= $printZone['x'] ?>%; top: <?= $printZone['y'] ?>%; width: <?= $printZone['width'] ?>%; height: <?= $printZone['height'] ?>%;">
                                    <span class="print-zone-label"><?= h($printZone['label'] ?? 'Zone d\'impression') ?></span>
                                </div>

                                <?php if (!empty($product['image_front_url'])): ?>
                                    <img src="/public<?= h($product['image_front_url']) ?>"
                                         alt="<?= h($product['name']) ?> - Face"
                                         class="preview-product-img"
                                         id="previewImage"
                                         data-front="/public<?= h($product['image_front_url']) ?>"
                                         data-back="<?= !empty($product['image_back_url']) ? '/public' . h($product['image_back_url']) : '' ?>">
                                <?php else: ?>
                                    <span class="preview-icon">👕</span>
                                <?php endif; ?>

                                <!-- Texte draggable avec style technique -->
                                <span class="preview-text empty technique-flex" id="previewText">Votre texte</span>

                                <!-- Indicateur technique -->
                                <span class="technique-indicator" id="techniqueIndicator">FLEX</span>
                            </div>

                            <!-- Indication drag -->
                            <div class="drag-hint">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 9l-3 3 3 3"/>
                                    <path d="M9 5l3-3 3 3"/>
                                    <path d="M15 19l-3 3-3-3"/>
                                    <path d="M19 9l3 3-3 3"/>
                                    <line x1="2" y1="12" x2="22" y2="12"/>
                                    <line x1="12" y1="2" x2="12" y2="22"/>
                                </svg>
                                Glissez pour positionner
                            </div>
                        </div>
                    </div>

                    <!-- ========================================
                         COLONNE DROITE: Options produit
                         (Nom, Prix, Taille, Couleur, Quantité, CTA)
                         ======================================== -->
                    <div class="config-right">
                        <!-- Info produit -->
                        <div class="product-info-header">
                            <h1 class="product-title"><?= h($product['name']) ?></h1>
                            <div class="product-price" id="productPrice"><?= formatPrice($product['base_price']) ?></div>
                        </div>

                        <!-- Taille -->
                        <div class="config-section mobile-accordion">
                            <div class="accordion-header">Taille</div>
                            <div class="accordion-content">
                                <h3 class="config-section-title">Taille</h3>
                                <div class="size-options">
                                    <?php foreach ($sizes as $size): ?>
                                        <label class="size-option <?= $size === 'M' ? 'selected' : '' ?>">
                                            <input type="radio" name="size" value="<?= $size ?>" <?= $size === 'M' ? 'checked' : '' ?>>
                                            <?= $size ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Couleur -->
                        <div class="config-section mobile-accordion">
                            <div class="accordion-header">Couleur</div>
                            <div class="accordion-content">
                                <h3 class="config-section-title">Couleur du produit</h3>
                                <div class="color-options">
                                    <?php
                                    $firstColor = true;
                                    foreach ($colors as $name => $hex):
                                        $isDefault = ($hasColorVariants && $defaultColorKey)
                                            ? ($name === $defaultColorKey)
                                            : $firstColor;
                                        $imgFront = $colorImages[$name]['front'] ?? '';
                                        $imgBack = $colorImages[$name]['back'] ?? '';
                                    ?>
                                        <label class="color-option <?= $isDefault ? 'selected' : '' ?>"
                                               style="background-color: <?= $hex ?>; <?= strtolower($hex) === '#ffffff' ? 'border: 1px solid #ddd;' : '' ?>"
                                               title="<?= ucfirst($name) ?>"
                                               data-color="<?= $hex ?>"
                                               data-color-name="<?= h($name) ?>"
                                               <?php if ($hasColorVariants && $imgFront): ?>
                                               data-image-front="<?= h($imgFront) ?>"
                                               data-image-back="<?= h($imgBack) ?>"
                                               <?php endif; ?>>
                                            <input type="radio" name="color" value="<?= $name ?>" <?= $isDefault ? 'checked' : '' ?>>
                                        </label>
                                    <?php
                                        $firstColor = false;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Quantité -->
                        <div class="config-section mobile-accordion">
                            <div class="accordion-header">Quantité</div>
                            <div class="accordion-content">
                                <h3 class="config-section-title">Quantité</h3>
                                <div class="quantity-row">
                                    <div class="quantity-selector">
                                        <button type="button" class="qty-btn" id="qtyMinus">−</button>
                                        <input type="number" name="quantity" id="qtyInput" class="qty-input" value="1" min="1" max="99">
                                        <button type="button" class="qty-btn" id="qtyPlus">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bouton Ajouter (Desktop) -->
                        <div class="desktop-only-cta">
                            <button type="submit" class="btn btn-primary add-to-cart-btn">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                    <line x1="3" y1="6" x2="21" y2="6"/>
                                    <path d="M16 10a4 4 0 0 1-8 0"/>
                                </svg>
                                Ajouter au panier
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sticky CTA Mobile -->
                <div class="sticky-cta">
                    <span class="price-display"><?= formatPrice($product['base_price']) ?></span>
                    <button type="submit" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                            <line x1="3" y1="6" x2="21" y2="6"/>
                            <path d="M16 10a4 4 0 0 1-8 0"/>
                        </svg>
                        Ajouter
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Lightbox Component (chargé AVANT le JS inline qui l'utilise) -->
    <script src="/public/assets/js/lightbox.js"></script>

    <script>
        // ============================================
        // PERSONNALY - Drag & Drop Preview System
        // Mobile-first, contraint à la zone d'impression
        // ============================================

        (function() {
            'use strict';

            // === ÉLÉMENTS DOM ===
            const previewText = document.getElementById('previewText');
            const productPreview = document.getElementById('productPreview');
            const printZone = document.getElementById('printZone');
            const customText = document.getElementById('customText');
            const positionXInput = document.getElementById('positionX');
            const positionYInput = document.getElementById('positionY');
            const viewInput = document.getElementById('viewInput');
            const previewImage = document.getElementById('previewImage');

            // === ZONES D'IMPRESSION (front et back) ===
            const zones = {
                front: <?= json_encode($zones['front']) ?>,
                back: <?= $zones['back'] ? json_encode($zones['back']) : 'null' ?>
            };

            // === ÉTAT ACTUEL ===
            let currentView = 'front';
            let zone = zones.front;

            // === ÉTAT DU DRAG ===
            let isDragging = false;
            let startX = 0, startY = 0;
            let currentX = 50, currentY = 50; // Position initiale (centre de la zone)

            // Calculer le centre de la zone comme position par défaut
            currentX = zone.x + (zone.width / 2);
            currentY = zone.y + (zone.height / 2);

            // Appliquer position initiale
            updateTextPosition();

            // === GESTION BASCULE FACE/DOS ===
            const btnFront = document.getElementById('btnFront');
            const btnBack = document.getElementById('btnBack');

            function switchView(view) {
                if (view === currentView) return;

                // Vérifier qu'il y a une image dos si on switch vers 'back'
                if (view === 'back' && previewImage && !previewImage.dataset.back) return;

                currentView = view;
                // Utiliser la zone du dos si elle existe, sinon fallback sur la zone front
                zone = zones[view] || zones.front;

                // Update hidden input
                viewInput.value = view;

                // Update buttons
                if (btnFront && btnBack) {
                    btnFront.classList.toggle('active', view === 'front');
                    btnBack.classList.toggle('active', view === 'back');
                }

                // Update image
                if (previewImage) {
                    const imgSrc = view === 'front' ? previewImage.dataset.front : previewImage.dataset.back;
                    if (imgSrc) previewImage.src = imgSrc;
                }

                // Update print zone overlay
                printZone.style.left = zone.x + '%';
                printZone.style.top = zone.y + '%';
                printZone.style.width = zone.width + '%';
                printZone.style.height = zone.height + '%';
                printZone.querySelector('.print-zone-label').textContent = zone.label || 'Zone d\'impression';

                // Reset text position to center of new zone
                currentX = zone.x + (zone.width / 2);
                currentY = zone.y + (zone.height / 2);
                updateTextPosition();
            }

            if (btnFront) btnFront.addEventListener('click', () => switchView('front'));
            if (btnBack) btnBack.addEventListener('click', () => switchView('back'));

            // === FONCTIONS UTILITAIRES ===

            function getEventCoords(e) {
                if (e.touches && e.touches.length > 0) {
                    return { x: e.touches[0].clientX, y: e.touches[0].clientY };
                }
                return { x: e.clientX, y: e.clientY };
            }

            function updateTextPosition() {
                previewText.style.left = currentX + '%';
                previewText.style.top = currentY + '%';

                // Mettre à jour les hidden inputs
                positionXInput.value = currentX.toFixed(1);
                positionYInput.value = currentY.toFixed(1);
            }

            function constrainToZone(x, y) {
                // Contraindre X dans la zone
                const minX = zone.x;
                const maxX = zone.x + zone.width;
                x = Math.max(minX, Math.min(maxX, x));

                // Contraindre Y dans la zone
                const minY = zone.y;
                const maxY = zone.y + zone.height;
                y = Math.max(minY, Math.min(maxY, y));

                return { x, y };
            }

            // === DRAG & DROP HANDLERS ===

            function startDrag(e) {
                // Ne pas démarrer si pas de texte
                if (previewText.classList.contains('empty')) return;

                e.preventDefault();
                isDragging = true;

                const coords = getEventCoords(e);
                startX = coords.x;
                startY = coords.y;

                previewText.classList.add('dragging');
                printZone.classList.add('active');
            }

            function drag(e) {
                if (!isDragging) return;
                e.preventDefault();

                const coords = getEventCoords(e);
                const rect = productPreview.getBoundingClientRect();

                // Calculer la nouvelle position en % du preview
                let newX = ((coords.x - rect.left) / rect.width) * 100;
                let newY = ((coords.y - rect.top) / rect.height) * 100;

                // Contraindre à la zone d'impression
                const constrained = constrainToZone(newX, newY);
                currentX = constrained.x;
                currentY = constrained.y;

                updateTextPosition();
            }

            function endDrag(e) {
                if (!isDragging) return;

                isDragging = false;
                previewText.classList.remove('dragging');
                printZone.classList.remove('active');
            }

            // === EVENT LISTENERS (Mobile prioritaire) ===

            // Touch events (mobile)
            previewText.addEventListener('touchstart', startDrag, { passive: false });
            document.addEventListener('touchmove', drag, { passive: false });
            document.addEventListener('touchend', endDrag, { passive: true });

            // Mouse events (desktop)
            previewText.addEventListener('mousedown', startDrag);
            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', endDrag);

            // Empêcher le drag natif de l'élément
            previewText.addEventListener('dragstart', e => e.preventDefault());

            // === LIVE PREVIEW DU TEXTE ===

            customText.addEventListener('input', function() {
                const text = this.value.trim();

                if (text) {
                    previewText.textContent = text;
                    previewText.classList.remove('empty');
                } else {
                    previewText.textContent = 'Votre texte';
                    previewText.classList.add('empty');
                }

                // Ajuster la taille du texte si trop long
                adjustTextSize(text);
            });

            function adjustTextSize(text) {
                // Réduire la taille si le texte est long
                if (text.length > 30) {
                    previewText.style.fontSize = '1rem';
                } else if (text.length > 20) {
                    previewText.style.fontSize = '1.2rem';
                } else {
                    previewText.style.fontSize = '1.5rem';
                }
            }

            // === SÉLECTION DES OPTIONS ===

            // Gestionnaire générique pour les options radio
            document.querySelectorAll('.size-option, .color-option, .font-option, .text-color-option, .technique-option').forEach(option => {
                option.addEventListener('click', function() {
                    const parent = this.parentElement;
                    const baseClass = this.className.split(' ')[0];
                    parent.querySelectorAll('.' + baseClass).forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });

            // === SÉLECTEUR DE POLICES DROPDOWN ===
            const fontSelector = document.getElementById('fontSelector');
            const fontTrigger = document.getElementById('fontTrigger');
            const fontDropdown = document.getElementById('fontDropdown');
            const fontSearch = document.getElementById('fontSearch');
            const fontList = document.getElementById('fontList');
            const fontInput = document.getElementById('fontInput');
            const fontPreview = document.getElementById('fontPreview');

            // Ouvrir/fermer le dropdown
            fontTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = fontDropdown.classList.contains('open');
                if (isOpen) {
                    closeFontDropdown();
                } else {
                    openFontDropdown();
                }
            });

            function openFontDropdown() {
                fontTrigger.classList.add('open');
                fontDropdown.classList.add('open');
                fontSearch.value = '';
                filterFonts('');
                setTimeout(() => fontSearch.focus(), 100);
            }

            function closeFontDropdown() {
                fontTrigger.classList.remove('open');
                fontDropdown.classList.remove('open');
            }

            // Fermer au clic extérieur
            document.addEventListener('click', function(e) {
                if (!fontSelector.contains(e.target)) {
                    closeFontDropdown();
                }
            });

            // Recherche de polices
            fontSearch.addEventListener('input', function() {
                filterFonts(this.value.toLowerCase());
            });

            function filterFonts(query) {
                const items = fontList.querySelectorAll('.font-list-item');
                items.forEach(item => {
                    const name = item.dataset.label.toLowerCase();
                    const category = item.dataset.category.toLowerCase();
                    if (name.includes(query) || category.includes(query)) {
                        item.classList.remove('hidden');
                    } else {
                        item.classList.add('hidden');
                    }
                });
            }

            // Sélection d'une police
            fontList.addEventListener('click', function(e) {
                const item = e.target.closest('.font-list-item');
                if (!item) return;

                const font = item.dataset.font;
                const label = item.dataset.label;
                const category = item.dataset.category || 'sans-serif';

                // Mettre à jour l'input hidden
                fontInput.value = font;

                // Mettre à jour le preview du trigger
                fontPreview.textContent = label;
                fontPreview.style.fontFamily = "'" + font + "', " + category;

                // Mettre à jour la sélection visuelle
                fontList.querySelectorAll('.font-list-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');

                // Mettre à jour le texte preview du produit
                previewText.style.fontFamily = "'" + font + "', " + category;

                // Fermer le dropdown
                closeFontDropdown();
            });

            // Initialiser le style de la première police sélectionnée
            const firstFont = document.querySelector('.font-list-item.selected');
            if (firstFont) {
                const font = firstFont.dataset.font;
                const category = firstFont.dataset.category || 'sans-serif';
                previewText.style.fontFamily = "'" + font + "', " + category;
            }

            // Initialiser la couleur du texte depuis la première option sélectionnée
            const firstTextColor = document.querySelector('.text-color-option.selected');
            if (firstTextColor) {
                previewText.style.color = firstTextColor.dataset.color;
            }

            // Color preview (couleur du produit + images par variante)
            document.querySelectorAll('.color-option').forEach(option => {
                option.addEventListener('click', function() {
                    const color = this.dataset.color;
                    const imageFront = this.dataset.imageFront;
                    const imageBack = this.dataset.imageBack;

                    // Si cette variante a des images, les utiliser
                    if (imageFront && previewImage) {
                        previewImage.src = imageFront;
                        previewImage.dataset.front = imageFront;
                        if (imageBack) {
                            previewImage.dataset.back = imageBack;
                        }
                        // Reset sur la vue "front" quand on change de couleur
                        if (currentView === 'back' && imageBack) {
                            previewImage.src = imageBack;
                        }
                        // Pas de changement de fond si on a une vraie image
                        productPreview.style.backgroundColor = '';
                    } else {
                        // Fallback : simuler la couleur avec le fond
                        productPreview.style.backgroundColor = color === '#FFFFFF' ? '#f8f8f8' : color;
                    }
                });
            });

            // Text color preview (couleur du texte personnalisé)
            document.querySelectorAll('.text-color-option').forEach(option => {
                option.addEventListener('click', function() {
                    const color = this.dataset.color;
                    previewText.style.color = color;
                    // Note: l'ombre est gérée par la classe technique
                });
            });

            // (Font preview géré par le nouveau sélecteur dropdown)

            // Technique preview - applique le style visuel distinct
            const techniqueIndicator = document.getElementById('techniqueIndicator');
            const techniqueLabels = {
                'flex': 'FLEX',
                'flock': 'FLOCK',
                'broderie': 'BRODERIE',
                'sublimation': 'SUBLIMATION'
            };

            document.querySelectorAll('.technique-option').forEach(option => {
                option.addEventListener('click', function() {
                    const technique = this.dataset.technique.toLowerCase();

                    // Retirer toutes les classes de technique (toutes les variantes possibles)
                    previewText.className = previewText.className.replace(/\btechnique-\S+/g, '').trim();

                    // Ajouter la nouvelle classe (en minuscules)
                    previewText.classList.add('technique-' + technique);

                    // Mettre à jour l'indicateur
                    if (techniqueIndicator) {
                        techniqueIndicator.textContent = techniqueLabels[technique] || technique.toUpperCase();
                    }
                });
            });

            // === QUANTITÉ ===

            const qtyInput = document.getElementById('qtyInput');
            document.getElementById('qtyMinus').addEventListener('click', () => {
                if (qtyInput.value > 1) qtyInput.value = parseInt(qtyInput.value) - 1;
            });
            document.getElementById('qtyPlus').addEventListener('click', () => {
                if (qtyInput.value < 99) qtyInput.value = parseInt(qtyInput.value) + 1;
            });

            // === LIGHTBOX / ZOOM ===
            const zoomBtn = document.getElementById('zoomBtn');
            if (zoomBtn && window.PersonnalyLightbox) {
                zoomBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Récupérer l'image actuelle
                    const imgSrc = previewImage ? previewImage.src : '';
                    const text = customText.value.trim();
                    // Récupérer la police depuis le nouveau sélecteur dropdown
                    const fontEl = document.querySelector('.font-list-item.selected');
                    const font = fontEl ? fontEl.dataset.font : (fontInput ? fontInput.value : 'Poppins');
                    const category = fontEl ? (fontEl.dataset.category || 'sans-serif') : 'sans-serif';

                    // Récupérer la technique sélectionnée
                    const techEl = document.querySelector('.technique-option.selected');
                    const technique = techEl ? techEl.dataset.technique : 'flex';

                    // Ouvrir la lightbox
                    PersonnalyLightbox.open({
                        imageSrc: imgSrc,
                        imageAlt: '<?= h($product['name']) ?>',
                        text: text || null,
                        font: font + ', ' + category,
                        fontSize: '2.5rem',
                        textX: currentX,
                        textY: currentY,
                        textColor: previewText.style.color || '#FF1493',
                        technique: technique
                    });
                });
            }

            // === MOBILE ACCORDION ===
            // Gestion des accordions pour mobile uniquement
            function isMobile() {
                return window.innerWidth <= 768;
            }

            document.querySelectorAll('.accordion-header').forEach(header => {
                header.addEventListener('click', function() {
                    if (!isMobile()) return; // Ne rien faire sur desktop

                    const content = this.nextElementSibling;
                    const isCollapsed = this.classList.contains('collapsed');

                    // Toggle l'état
                    if (isCollapsed) {
                        this.classList.remove('collapsed');
                        content.classList.remove('collapsed');
                    } else {
                        this.classList.add('collapsed');
                        content.classList.add('collapsed');
                    }
                });
            });

            // Initialiser l'état des accordions au chargement
            function initAccordions() {
                const headers = document.querySelectorAll('.accordion-header');
                if (isMobile()) {
                    // Sur mobile: tout fermer sauf le premier (texte)
                    headers.forEach((header, index) => {
                        const content = header.nextElementSibling;
                        if (index > 0) {
                            header.classList.add('collapsed');
                            content.classList.add('collapsed');
                        }
                    });
                } else {
                    // Sur desktop: tout ouvrir
                    headers.forEach(header => {
                        const content = header.nextElementSibling;
                        header.classList.remove('collapsed');
                        content.classList.remove('collapsed');
                    });
                }
            }

            // Initialiser au chargement et au resize
            initAccordions();
            window.addEventListener('resize', initAccordions);

        })();
    </script>
</body>
</html>
