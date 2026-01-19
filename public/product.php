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
require_once __DIR__ . '/../app/models/Pack.php';

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

// === PACK / IDÉE : Chargement du preset (ONE-SHOT) ===
$packId = (int) get('pack_id', 0);
$pack = null;
$preset = null;

if ($packId > 0) {
    $packModel = new Pack();
    $pack = $packModel->findById($packId);

    // Vérifications silencieuses :
    // 1. Pack existe
    // 2. Pack actif
    // 3. Produit courant ∈ pack_products (ou pack sans produits = universel)
    $isValidPack = false;

    if ($pack && $pack['status'] === 'active') {
        // Vérifier si le produit courant appartient au pack
        $packProducts = $packModel->getProducts($packId);
        $packProductIds = array_column($packProducts, 'id');

        // Pack valide si : aucun produit lié (universel) OU produit courant dans la liste
        if (empty($packProductIds) || in_array($productId, $packProductIds)) {
            $isValidPack = true;
        }
    }

    if ($isValidPack) {
        // Décoder le preset JSON
        $presetJson = $pack['preset_json'] ?? '{}';
        $preset = is_string($presetJson) ? json_decode($presetJson, true) : $presetJson;

        // Valeurs par défaut si le preset est incomplet
        $preset = array_merge([
            'text' => '',
            'font' => '',
            'text_color' => '',
            'technique' => '',
            'position' => ['x' => 50, 'y' => 50],
            'view' => 'front'
        ], $preset ?? []);
    } else {
        // Pack invalide → ignorer silencieusement
        $pack = null;
        $preset = null;
    }
}

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
$colorSizes = []; // Pour stocker les tailles disponibles par couleur
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
            // Récupérer les tailles disponibles pour cette variante couleur
            $availableSizes = [];
            if (!empty($c['available_sizes'])) {
                $availableSizes = json_decode($c['available_sizes'], true) ?: [];
            }
            $colorSizes[$colorName] = $availableSizes;
            // Marquer le défaut
            if (!empty($c['is_default'])) {
                $defaultColorKey = $colorName;
            }
        }
    }
} else {
    $colors = ['blanc' => '#FFFFFF', 'noir' => '#1A1A2E', 'rose' => '#FF69B4', 'menthe' => '#3DFFC0', 'bleu' => '#4A90D9', 'gris' => '#6B7280'];
}

// JSON pour JavaScript - tailles par couleur
$colorSizesJson = json_encode($colorSizes);

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
    <link rel="stylesheet" href="/public/assets/css/configurator.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/public/assets/css/techniques.css?v=3">
    <!-- Konva.js pour le canvas configurateur -->
    <script src="https://unpkg.com/konva@9/konva.min.js"></script>
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
           3-COLUMN CONFIGURATOR LAYOUT
           >> STYLES DÉPLACÉS DANS configurator.css <<
           =========================================== */

        .product-page { padding: 30px 0 100px; }

        /* Styles config-section, product-info, product-hero
           >> DÉPLACÉS DANS configurator.css << */

        /* View Toggle, Product Preview, Print Zone, Zoom
           >> STYLES DÉPLACÉS DANS configurator.css << */

        /* Form Section, Customization, Size, Color
           >> STYLES DÉPLACÉS DANS configurator.css << */

        /* Font Selection, Text Color Selection
           >> STYLES DÉPLACÉS DANS configurator.css << */

        /* Technique Selection, Real Render Btn, Text Input, Quantity
           >> STYLES DÉPLACÉS DANS configurator.css << */

        /* Add to Cart, Alerts, Responsive/Mobile Accordion
           >> STYLES DÉPLACÉS DANS configurator.css << */

        /* Mobile Wizard Mode
           >> STYLES DÉPLACÉS DANS configurator.css << */
    </style>
    <?php
    // === Pré-calcul des sélections pour le head (évite undefined variable) ===
    $defaultFont = $fonts[0] ?? ['value' => 'Poppins', 'label' => 'Poppins', 'category' => 'sans-serif'];
    $defaultTextColor = $textColors[0] ?? ['value' => 'noir', 'label' => 'Noir', 'hex' => '#1A1A2E'];
    $defaultTechnique = $techniques[0] ?? ['value' => 'flex', 'label' => 'Flex', 'description' => '', 'price' => 0];
    ?>
    <script>
        // Données pour le configurateur JS
        window.__PRODUCT_DATA = {
            id: <?= $product['id'] ?>,
            name: "<?= addslashes(h($product['name'])) ?>",
            basePrice: <?= $product['base_price'] ?>
        };
        window.__FONTS_DATA = <?= json_encode($fonts) ?>;
        window.__TEXT_COLORS_DATA = <?= json_encode($textColors) ?>;
        window.__TECHNIQUES_DATA = <?= json_encode($techniques) ?>;
        window.__SELECTED_FONT = "<?= h($defaultFont['value']) ?>";
        window.__SELECTED_TEXT_COLOR = "<?= h($defaultTextColor['value']) ?>";
        window.__SELECTED_TECHNIQUE = "<?= h($defaultTechnique['value']) ?>";
    </script>
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

            <?php if ($pack): ?>
                <div class="pack-info-banner" style="margin-bottom: 20px; padding: 16px 20px; background: linear-gradient(135deg, rgba(255,105,180,0.08) 0%, rgba(61,255,192,0.08) 100%); border-radius: 12px; border-left: 4px solid var(--pink-main);">
                    <strong>Idée : <?= h($pack['name']) ?></strong>
                    <span style="display: block; font-size: 13px; color: var(--gray); margin-top: 4px;">
                        Configuration pré-remplie — Vous pouvez tout modifier librement
                    </span>
                </div>
            <?php endif; ?>

            <?php
            // === Calcul des indices par défaut pour le preset ===
            $selectedFontIndex = 0;
            $selectedTextColorIndex = 0;
            $selectedTechniqueIndex = 0;

            if ($preset) {
                // Trouver l'index de la police du preset
                if (!empty($preset['font'])) {
                    foreach ($fonts as $idx => $f) {
                        if ($f['value'] === $preset['font']) {
                            $selectedFontIndex = $idx;
                            break;
                        }
                    }
                }

                // Trouver l'index de la couleur de texte du preset
                if (!empty($preset['text_color'])) {
                    foreach ($textColors as $idx => $tc) {
                        if ($tc['value'] === $preset['text_color']) {
                            $selectedTextColorIndex = $idx;
                            break;
                        }
                    }
                }

                // Trouver l'index de la technique du preset
                if (!empty($preset['technique'])) {
                    foreach ($techniques as $idx => $t) {
                        if ($t['value'] === $preset['technique']) {
                            $selectedTechniqueIndex = $idx;
                            break;
                        }
                    }
                }
            }

            // Récupérer les valeurs sélectionnées
            $selectedFont = $fonts[$selectedFontIndex] ?? $fonts[0] ?? ['value' => 'Poppins', 'label' => 'Poppins', 'category' => 'sans-serif'];
            $selectedTextColor = $textColors[$selectedTextColorIndex] ?? $textColors[0] ?? ['value' => 'noir', 'label' => 'Noir', 'hex' => '#1A1A2E'];
            $selectedTechnique = $techniques[$selectedTechniqueIndex] ?? $techniques[0] ?? ['value' => 'flex', 'label' => 'Flex', 'description' => '', 'price' => 0];
            ?>

            <form method="post" id="customizationForm">
                <?= csrfField() ?>
                <input type="hidden" name="add_to_cart" value="1">
                <input type="hidden" name="position_x" id="positionX" value="<?= $preset ? h($preset['position']['x'] ?? 50) : 50 ?>">
                <input type="hidden" name="position_y" id="positionY" value="<?= $preset ? h($preset['position']['y'] ?? 50) : 50 ?>">
                <input type="hidden" name="position_zone_id" id="positionZoneId" value="<?= $printZone['id'] ?>">
                <input type="hidden" name="view" id="viewInput" value="<?= $preset ? h($preset['view'] ?? 'front') : 'front' ?>">
                <!-- Inputs pour le panier -->
                <input type="hidden" name="custom_text" id="customTextInput" value="<?= $preset ? h($preset['text'] ?? '') : '' ?>">
                <input type="hidden" name="font" id="fontInput" value="<?= h($selectedFont['value']) ?>">
                <input type="hidden" name="text_color" id="textColorInput" value="<?= h($selectedTextColor['value']) ?>">
                <input type="hidden" name="technique" id="techniqueInput" value="<?= h($selectedTechnique['value']) ?>">

                <!-- ========================================
                     CONFIGURATOR V2 - Canva/YourSurprise style
                     ======================================== -->
                <div class="configurator-v2">
                    <!-- Onglets verticaux (gauche) -->
                    <div class="cfg-tabs-bar">
                        <button type="button" class="cfg-tab active" data-tab="text">
                            <span class="cfg-tab-icon">✏️</span>
                            <span class="cfg-tab-label">Texte</span>
                        </button>
                        <button type="button" class="cfg-tab" data-tab="image">
                            <span class="cfg-tab-icon">🖼️</span>
                            <span class="cfg-tab-label">Image</span>
                        </button>
                        <button type="button" class="cfg-tab" data-tab="design">
                            <span class="cfg-tab-icon">🎨</span>
                            <span class="cfg-tab-label">Design</span>
                        </button>
                        <button type="button" class="cfg-tab" data-tab="layers">
                            <span class="cfg-tab-icon">📚</span>
                            <span class="cfg-tab-label">Calques</span>
                        </button>
                    </div>

                    <!-- Panneau d'options (centre-gauche) -->
                    <div class="cfg-tools">
                        <div class="cfg-tool-panel active" data-panel="text">
                            <h3 class="cfg-panel-title">Ajouter du texte</h3>
                            <p class="cfg-panel-desc">Saisissez votre texte personnalisé</p>
                            <input type="text" class="cfg-input" placeholder="Votre texte ici..." id="cfgTextInput">
                            <button type="button" class="cfg-add-btn" id="cfgAddText">
                                <span>+ Ajouter le texte</span>
                            </button>

                            <div class="cfg-section">
                                <h4 class="cfg-section-title">Police</h4>
                                <select class="cfg-select" id="cfgFontSelect">
                                    <?php foreach ($fonts as $font): ?>
                                    <option value="<?= h($font['value']) ?>"><?= h($font['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="cfg-section">
                                <h4 class="cfg-section-title">Couleur du texte</h4>
                                <div class="cfg-color-grid">
                                    <?php foreach ($textColors as $tc): ?>
                                    <button type="button" class="cfg-color-btn"
                                            style="background-color: <?= h($tc['hex']) ?>"
                                            data-color="<?= h($tc['hex']) ?>"
                                            title="<?= h($tc['label']) ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="cfg-tool-panel" data-panel="image">
                            <h3 class="cfg-panel-title">Ajouter une image</h3>
                            <p class="cfg-panel-desc">Téléchargez votre propre image</p>
                            <div class="cfg-upload-zone" id="cfgUploadZone">
                                <span class="cfg-upload-icon">📤</span>
                                <span>Glissez ou cliquez pour upload</span>
                            </div>
                        </div>

                        <div class="cfg-tool-panel" data-panel="design">
                            <h3 class="cfg-panel-title">Éléments design</h3>
                            <p class="cfg-panel-desc">Formes et décorations</p>
                            <p style="color: #999; font-size: 13px;">Bientôt disponible...</p>
                        </div>

                        <div class="cfg-tool-panel" data-panel="layers">
                            <h3 class="cfg-panel-title">Calques</h3>
                            <p class="cfg-panel-desc">Gérez vos éléments</p>
                            <div class="cfg-layers-list" id="cfgLayersList">
                                <p style="color: #999; font-size: 13px;">Aucun élément ajouté</p>
                            </div>
                        </div>
                    </div>

                    <!-- Canvas central -->
                    <div class="cfg-canvas">
                        <div class="cfg-canvas-header">
                            <div class="cfg-view-toggle">
                                <button type="button" class="cfg-view-btn active" data-view="front">👕 Face</button>
                                <?php if ($hasBackImage): ?>
                                <button type="button" class="cfg-view-btn" data-view="back">🔄 Dos</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="cfg-stage-container" id="cfgStageContainer">
                            <!-- Zone d'impression -->
                            <div class="cfg-print-zone" id="cfgPrintZone"
                                 style="left: <?= $printZone['x'] ?>%; top: <?= $printZone['y'] ?>%; width: <?= $printZone['width'] ?>%; height: <?= $printZone['height'] ?>%;">
                                <span class="cfg-zone-label"><?= h($printZone['label'] ?? 'Zone d\'impression') ?></span>
                            </div>
                            <!-- Image produit de fond -->
                            <?php if (!empty($product['image_front_url'])): ?>
                            <img src="/public<?= h($product['image_front_url']) ?>"
                                 alt="<?= h($product['name']) ?>"
                                 class="cfg-product-bg"
                                 id="cfgProductImg"
                                 data-front="/public<?= h($product['image_front_url']) ?>"
                                 data-back="<?= !empty($product['image_back_url']) ? '/public' . h($product['image_back_url']) : '' ?>">
                            <?php endif; ?>
                        </div>
                        <div class="cfg-canvas-footer">
                            <span class="cfg-hint">↔️ Glissez pour repositionner vos éléments</span>
                        </div>
                    </div>

                    <!-- Drawer contextuel (droite) -->
                    <div class="cfg-drawer" id="cfgDrawer">
                        <div class="cfg-drawer-header">
                            <h4 class="cfg-drawer-title">Propriétés</h4>
                            <button type="button" class="cfg-drawer-close" id="cfgDrawerClose">✕</button>
                        </div>
                        <div class="cfg-drawer-content">
                            <!-- Rempli dynamiquement par JS -->
                            <p style="color: #999;">Sélectionnez un élément</p>
                        </div>
                    </div>
                </div>

                <!-- Options produit (sous le configurator) -->
                <div class="cfg-product-options">
                    <div class="cfg-product-header">
                        <h1 class="cfg-product-title"><?= h($product['name']) ?></h1>
                        <div class="cfg-product-price"><?= formatPrice($product['base_price']) ?></div>
                    </div>

                    <div class="cfg-options-row">
                        <div class="cfg-option-group">
                            <label class="cfg-option-label">Taille</label>
                            <div class="cfg-size-options">
                                <?php foreach ($sizes as $size): ?>
                                <label class="cfg-size-btn <?= $size === 'M' ? 'selected' : '' ?>">
                                    <input type="radio" name="size" value="<?= h($size) ?>" <?= $size === 'M' ? 'checked' : '' ?>>
                                    <?= h($size) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="cfg-option-group">
                            <label class="cfg-option-label">Couleur</label>
                            <div class="cfg-color-options">
                                <?php
                                $firstColor = true;
                                foreach ($colors as $name => $hex):
                                    $isDefault = $firstColor;
                                ?>
                                <label class="cfg-product-color <?= $isDefault ? 'selected' : '' ?>"
                                       style="background-color: <?= $hex ?>"
                                       title="<?= ucfirst($name) ?>">
                                    <input type="radio" name="color" value="<?= $name ?>" <?= $isDefault ? 'checked' : '' ?>>
                                </label>
                                <?php
                                    $firstColor = false;
                                endforeach;
                                ?>
                            </div>
                        </div>

                        <div class="cfg-option-group">
                            <label class="cfg-option-label">Quantité</label>
                            <div class="cfg-qty-selector">
                                <button type="button" class="cfg-qty-btn" id="cfgQtyMinus">−</button>
                                <input type="number" name="quantity" class="cfg-qty-input" value="1" min="1" max="99">
                                <button type="button" class="cfg-qty-btn" id="cfgQtyPlus">+</button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="cfg-add-cart-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                            <line x1="3" y1="6" x2="21" y2="6"/>
                            <path d="M16 10a4 4 0 0 1-8 0"/>
                        </svg>
                        Ajouter au panier
                    </button>
                </div>

            </form>
        </div>
    </section>

    <!-- Configurator V2 Modern 2026 -->
    <script src="/public/assets/js/configurator.js?v=<?= time() ?>"></script>
    <script>
        window.__PRODUCT_DATA = {
            ...window.__PRODUCT_DATA,
            imageFront: "<?= !empty($product['image_front_url']) ? '/public' . h($product['image_front_url']) : '' ?>",
            imageBack: "<?= !empty($product['image_back_url']) ? '/public' . h($product['image_back_url']) : '' ?>",
            printZones: {
                front: <?= json_encode($zones['front']) ?>,
                back: <?= $zones['back'] ? json_encode($zones['back']) : 'null' ?>
            }
        };
        window.__COLOR_IMAGES = <?= json_encode($colorImages) ?>;
    </script>
</body>
</html>
