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
    <link rel="stylesheet" href="/assets/css/configurator.css?v=<?= time() ?>">
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

                <!-- =============================================
                     MOBILE WIZARD MODE (Step-by-Step)
                     Affichage simplifié pour très petits écrans
                     ============================================= -->
                <div class="mobile-wizard" id="mobileWizard">
                    <!-- Progress Bar + Mini Preview -->
                    <div class="wizard-progress">
                        <div class="wizard-progress-steps">
                            <span class="wizard-step-dot active" data-step="1"></span>
                            <span class="wizard-step-dot" data-step="2"></span>
                            <span class="wizard-step-dot" data-step="3"></span>
                            <span class="wizard-step-dot" data-step="4"></span>
                            <span class="wizard-step-dot" data-step="5"></span>
                        </div>
                        <span class="wizard-step-label" id="wizardStepLabel">1/5 Texte</span>
                        <div class="wizard-mini-preview" id="wizardMiniPreview">
                            <?php if (!empty($product['image_front_url'])): ?>
                                <img src="/public<?= h($product['image_front_url']) ?>" alt="Preview" id="wizardPreviewImg">
                            <?php endif; ?>
                            <span class="mini-text" id="wizardMiniText" style="left: 50%; top: <?= $printZone['y'] + ($printZone['height'] / 2) ?>%;"></span>
                        </div>
                    </div>

                    <!-- STEP 1: Texte -->
                    <div class="wizard-step active" data-step="1">
                        <h2 class="wizard-step-title">Quel texte voulez-vous ?</h2>
                        <p class="wizard-step-subtitle">Saisissez le texte qui sera personnalisé sur votre produit</p>
                        <input type="text"
                               class="wizard-text-input"
                               id="wizardTextInput"
                               placeholder="Ex: Famille Dupont"
                               maxlength="<?= $printZone['max_chars'] ?? 50 ?>"
                               value="<?= $preset ? h($preset['text'] ?? '') : '' ?>">
                    </div>

                    <!-- STEP 2: Style (Police + Couleur texte) -->
                    <div class="wizard-step" data-step="2">
                        <h2 class="wizard-step-title">Choisissez le style</h2>
                        <p class="wizard-step-subtitle">Police et couleur de votre texte</p>

                        <h4 style="font-size: 13px; color: var(--gray); margin-bottom: 10px; text-transform: uppercase;">Police</h4>
                        <div class="wizard-fonts" id="wizardFonts">
                            <?php foreach ($fonts as $index => $font): ?>
                                <div class="wizard-font-btn <?= $index === $selectedFontIndex ? 'selected' : '' ?>"
                                     data-font="<?= h($font['value']) ?>"
                                     data-category="<?= h($font['category'] ?? 'sans-serif') ?>"
                                     style="font-family: '<?= h($font['value']) ?>', <?= h($font['category'] ?? 'sans-serif') ?>">
                                    <?= h($font['label']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h4 style="font-size: 13px; color: var(--gray); margin: 20px 0 10px; text-transform: uppercase;">Couleur du texte</h4>
                        <div class="wizard-colors" id="wizardTextColors">
                            <?php foreach ($textColors as $index => $tc): ?>
                                <div class="wizard-color-btn <?= $index === $selectedTextColorIndex ? 'selected' : '' ?>"
                                     style="background-color: <?= h($tc['hex']) ?>; <?= strtolower($tc['hex']) === '#ffffff' ? 'border: 2px solid #ddd;' : '' ?>"
                                     data-color="<?= h($tc['hex']) ?>"
                                     data-value="<?= h($tc['value']) ?>"
                                     title="<?= h($tc['label']) ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- STEP 3: Position -->
                    <div class="wizard-step" data-step="3">
                        <h2 class="wizard-step-title">Où placer le texte ?</h2>
                        <p class="wizard-step-subtitle">Choisissez la position sur le produit</p>

                        <div class="position-presets" id="wizardPositions">
                            <div class="position-preset-btn" data-position="top" data-y="<?= $printZone['y'] + 5 ?>">
                                <div class="position-preset-icon">
                                    <span class="pos-indicator"></span>
                                </div>
                                <div class="position-preset-info">
                                    <div class="position-preset-name">En haut</div>
                                    <div class="position-preset-desc">Proche du col</div>
                                </div>
                            </div>
                            <div class="position-preset-btn selected" data-position="center" data-y="<?= $printZone['y'] + ($printZone['height'] / 2) ?>">
                                <div class="position-preset-icon">
                                    <span class="pos-indicator"></span>
                                </div>
                                <div class="position-preset-info">
                                    <div class="position-preset-name">Au centre</div>
                                    <div class="position-preset-desc">Position classique</div>
                                </div>
                            </div>
                            <div class="position-preset-btn" data-position="bottom" data-y="<?= $printZone['y'] + $printZone['height'] - 5 ?>">
                                <div class="position-preset-icon">
                                    <span class="pos-indicator"></span>
                                </div>
                                <div class="position-preset-info">
                                    <div class="position-preset-name">En bas</div>
                                    <div class="position-preset-desc">Près de la ceinture</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: Produit (Taille + Couleur) -->
                    <div class="wizard-step" data-step="4">
                        <h2 class="wizard-step-title">Votre produit</h2>
                        <p class="wizard-step-subtitle">Taille et couleur du <?= strtolower(h($product['category'] ?? 'produit')) ?></p>

                        <h4 style="font-size: 13px; color: var(--gray); margin-bottom: 10px; text-transform: uppercase;">Taille</h4>
                        <div class="wizard-options-grid" id="wizardSizes">
                            <?php foreach ($sizes as $size): ?>
                                <div class="wizard-option <?= $size === 'M' ? 'selected' : '' ?>" data-size="<?= $size ?>">
                                    <?= $size ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h4 style="font-size: 13px; color: var(--gray); margin: 20px 0 10px; text-transform: uppercase;">Couleur</h4>
                        <div class="wizard-colors" id="wizardProductColors">
                            <?php
                            $firstColor = true;
                            foreach ($colors as $name => $hex):
                                $isDefault = ($hasColorVariants && $defaultColorKey) ? ($name === $defaultColorKey) : $firstColor;
                                $imgFront = $colorImages[$name]['front'] ?? '';
                                $imgBack = $colorImages[$name]['back'] ?? '';
                            ?>
                                <div class="wizard-color-btn <?= $isDefault ? 'selected' : '' ?>"
                                     style="background-color: <?= $hex ?>; <?= strtolower($hex) === '#ffffff' ? 'border: 2px solid #ddd;' : '' ?>"
                                     data-color="<?= $name ?>"
                                     data-hex="<?= $hex ?>"
                                     <?php if ($hasColorVariants && $imgFront): ?>
                                     data-image-front="<?= h($imgFront) ?>"
                                     data-image-back="<?= h($imgBack) ?>"
                                     <?php endif; ?>
                                     title="<?= ucfirst($name) ?>">
                                </div>
                            <?php
                                $firstColor = false;
                            endforeach;
                            ?>
                        </div>
                    </div>

                    <!-- STEP 5: Récapitulatif -->
                    <div class="wizard-step" data-step="5">
                        <h2 class="wizard-step-title">Votre création</h2>
                        <p class="wizard-step-subtitle">Vérifiez avant d'ajouter au panier</p>

                        <div class="wizard-summary-preview" id="wizardSummaryPreview">
                            <?php if (!empty($product['image_front_url'])): ?>
                                <img src="/public<?= h($product['image_front_url']) ?>" alt="<?= h($product['name']) ?>" id="wizardSummaryImg">
                            <?php endif; ?>
                            <span class="summary-text" id="wizardSummaryText"></span>
                        </div>

                        <div class="wizard-summary" id="wizardSummary">
                            <div class="wizard-summary-item">
                                <span class="wizard-summary-label">Texte</span>
                                <span class="wizard-summary-value" id="summaryText">-</span>
                            </div>
                            <div class="wizard-summary-item">
                                <span class="wizard-summary-label">Police</span>
                                <span class="wizard-summary-value" id="summaryFont">-</span>
                            </div>
                            <div class="wizard-summary-item">
                                <span class="wizard-summary-label">Taille</span>
                                <span class="wizard-summary-value" id="summarySize">M</span>
                            </div>
                            <div class="wizard-summary-item">
                                <span class="wizard-summary-label">Couleur produit</span>
                                <span class="wizard-summary-value" id="summaryColor">-</span>
                            </div>
                        </div>

                        <div class="wizard-total-price" id="wizardTotalPrice">
                            <?= formatPrice($product['base_price']) ?>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div class="wizard-nav">
                        <button type="button" class="wizard-btn wizard-btn-prev" id="wizardPrev" style="display: none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                            Retour
                        </button>
                        <button type="button" class="wizard-btn wizard-btn-next" id="wizardNext">
                            Suivant
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </button>
                        <button type="submit" class="wizard-btn wizard-btn-add" id="wizardAdd" style="display: none;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                <line x1="3" y1="6" x2="21" y2="6"/>
                                <path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                            Ajouter au panier
                        </button>
                    </div>
                </div>

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
                                       maxlength="<?= $printZone['max_chars'] ?? 50 ?>"
                                       value="<?= $preset ? h($preset['text'] ?? '') : '' ?>">
                            </div>
                        </div>

                        <!-- Police -->
                        <div class="config-section mobile-accordion">
                            <div class="accordion-header">Police</div>
                            <div class="accordion-content">
                                <h3 class="config-section-title">Style de police</h3>
                                <div class="font-selector-wrapper" id="fontSelector">
                                    <input type="hidden" name="font" id="fontInput" value="<?= h($selectedFont['value']) ?>">

                                    <div class="font-selector-trigger" id="fontTrigger">
                                        <span class="font-selector-preview" id="fontPreview"
                                              style="font-family: '<?= h($selectedFont['value']) ?>', <?= h($selectedFont['category'] ?? 'sans-serif') ?>">
                                            <?= h($selectedFont['label']) ?>
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
                                                <div class="font-list-item <?= $index === $selectedFontIndex ? 'selected' : '' ?>"
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
                                        <label class="text-color-option <?= $index === $selectedTextColorIndex ? 'selected' : '' ?>"
                                               style="background-color: <?= h($tc['hex']) ?>;"
                                               data-color="<?= h($tc['hex']) ?>"
                                               title="<?= h($tc['label']) ?>">
                                            <input type="radio" name="text_color" value="<?= h($tc['value']) ?>" <?= $index === $selectedTextColorIndex ? 'checked' : '' ?>>
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
                                <div class="technique-selector-wrapper" id="techniqueSelector">
                                    <input type="hidden" name="technique" id="techniqueInput" value="<?= h($selectedTechnique['value']) ?>">

                                    <div class="technique-selector-trigger" id="techniqueTrigger">
                                        <div class="technique-trigger-content">
                                            <span class="technique-selector-name" id="techniquePreviewName">
                                                <?= h($selectedTechnique['label']) ?>
                                            </span>
                                            <span class="technique-selector-desc" id="techniquePreviewDesc">
                                                <?= h($selectedTechnique['description'] ?? '') ?>
                                            </span>
                                        </div>
                                        <div class="technique-trigger-right">
                                            <span class="technique-selector-price" id="techniquePreviewPrice">
                                                <?= ($selectedTechnique['price'] ?? 0) == 0 ? 'Inclus' : '+' . formatPrice($selectedTechnique['price']) ?>
                                            </span>
                                            <svg class="technique-selector-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="6 9 12 15 18 9"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="technique-selector-dropdown" id="techniqueDropdown">
                                        <div class="technique-search-box">
                                            <input type="text" class="technique-search-input" id="techniqueSearch"
                                                   placeholder="Rechercher une technique...">
                                        </div>
                                        <div class="technique-list" id="techniqueList">
                                            <?php foreach ($techniques as $index => $tech): ?>
                                                <div class="technique-list-item <?= $index === $selectedTechniqueIndex ? 'selected' : '' ?>"
                                                     data-technique="<?= h($tech['value']) ?>"
                                                     data-label="<?= h($tech['label']) ?>"
                                                     data-description="<?= h($tech['description'] ?? '') ?>"
                                                     data-price="<?= $tech['price'] ?>">
                                                    <div class="technique-item-info">
                                                        <span class="technique-item-name"><?= h($tech['label']) ?></span>
                                                        <?php if (!empty($tech['description'])): ?>
                                                            <span class="technique-item-desc"><?= h($tech['description']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="technique-item-price <?= $tech['price'] == 0 ? 'free' : '' ?>">
                                                        <?= $tech['price'] == 0 ? 'Inclus' : '+' . formatPrice($tech['price']) ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
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

                            <!-- Bouton Voir le rendu réel - TOUJOURS VISIBLE -->
                            <button type="button" class="real-render-btn" id="realRenderBtn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21,15 16,10 5,21"/>
                                </svg>
                                Voir le rendu réel (photos)
                            </button>
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
                                <div class="size-options" id="sizeOptions">
                                    <?php foreach ($sizes as $size): ?>
                                        <label class="size-option <?= $size === 'M' ? 'selected' : '' ?>" data-size="<?= h($size) ?>">
                                            <input type="radio" name="size" value="<?= h($size) ?>" <?= $size === 'M' ? 'checked' : '' ?>>
                                            <?= h($size) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="size-unavailable-hint" id="sizeUnavailableHint" style="display: none; color: var(--gray); font-size: 12px; margin-top: 8px;">
                                    Certaines tailles ne sont pas disponibles pour cette couleur
                                </p>
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
    <!-- Modal Rendu Réel par technique -->
    <script src="/public/assets/js/real-render-modal.js"></script>
    <!-- Configurator V2 Modern 2026 -->
    <script src="/assets/js/configurator.js?v=<?= time() ?>"></script>

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

            // === TAILLES DISPONIBLES PAR COULEUR ===
            const colorSizes = <?= $colorSizesJson ?>;
            const hasColorSizes = Object.keys(colorSizes).length > 0;

            // === PRESET PACK (si défini) ===
            const preset = <?= $preset ? json_encode($preset) : 'null' ?>;

            // === ÉTAT ACTUEL ===
            let currentView = preset?.view || 'front';
            let zone = zones[currentView] || zones.front;

            // === ÉTAT DU DRAG ===
            let isDragging = false;
            let startX = 0, startY = 0;
            let currentX = 50, currentY = 50; // Position initiale (centre de la zone)

            // Position initiale : depuis preset OU centre de la zone
            if (preset && preset.position) {
                currentX = parseFloat(preset.position.x) || zone.x + (zone.width / 2);
                currentY = parseFloat(preset.position.y) || zone.y + (zone.height / 2);
            } else {
                currentX = zone.x + (zone.width / 2);
                currentY = zone.y + (zone.height / 2);
            }

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

            // === INITIALISATION PRESET (ONE-SHOT) ===
            // Flag global pour éviter toute ré-application du preset après interaction
            if (preset && !window.__PACK_PRESET_APPLIED) {
                window.__PACK_PRESET_APPLIED = true;

                // Déclencher l'événement input pour initialiser le preview si texte pré-rempli
                if (customText.value.trim()) {
                    customText.dispatchEvent(new Event('input'));
                }

                // Initialiser la vue depuis preset (si back)
                if (preset.view === 'back' && zones.back) {
                    const btnBack = document.getElementById('btnBack');
                    if (btnBack) {
                        setTimeout(() => btnBack.click(), 100);
                    }
                }
            } else if (!preset) {
                // Pas de preset : initialiser le texte si déjà rempli (cas normal)
                if (customText.value.trim()) {
                    customText.dispatchEvent(new Event('input'));
                }
            }

            // === SÉLECTION DES OPTIONS ===

            // Gestionnaire générique pour les options radio (technique géré par dropdown)
            document.querySelectorAll('.size-option, .color-option, .font-option, .text-color-option').forEach(option => {
                option.addEventListener('click', function() {
                    const parent = this.parentElement;
                    const baseClass = this.className.split(' ')[0];
                    parent.querySelectorAll('.' + baseClass).forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');

                    // Si c'est une couleur, filtrer les tailles disponibles
                    if (this.classList.contains('color-option')) {
                        const colorName = this.dataset.colorName;
                        filterSizesByColor(colorName);
                    }
                });
            });

            // === FILTRAGE DES TAILLES PAR COULEUR ===
            function filterSizesByColor(colorName) {
                if (!hasColorSizes) return;

                const sizeContainer = document.getElementById('sizeOptions');
                const sizeHint = document.getElementById('sizeUnavailableHint');
                if (!sizeContainer) return;

                const availableSizes = colorSizes[colorName] || [];
                const hasFilter = availableSizes.length > 0;

                // Si pas de tailles définies pour cette couleur, tout afficher
                if (!hasFilter) {
                    sizeContainer.querySelectorAll('.size-option').forEach(opt => {
                        opt.style.display = '';
                        opt.classList.remove('unavailable');
                    });
                    if (sizeHint) sizeHint.style.display = 'none';
                    return;
                }

                let hasUnavailable = false;
                let currentlySelected = sizeContainer.querySelector('.size-option.selected');
                let needsReselect = false;

                sizeContainer.querySelectorAll('.size-option').forEach(opt => {
                    const sizeValue = opt.dataset.size;
                    if (availableSizes.includes(sizeValue)) {
                        opt.style.display = '';
                        opt.classList.remove('unavailable');
                    } else {
                        opt.style.display = 'none';
                        opt.classList.add('unavailable');
                        hasUnavailable = true;
                        // Si la taille sélectionnée devient indisponible
                        if (opt.classList.contains('selected')) {
                            needsReselect = true;
                            opt.classList.remove('selected');
                            opt.querySelector('input').checked = false;
                        }
                    }
                });

                // Auto-sélectionner la première taille disponible si nécessaire
                if (needsReselect) {
                    const firstAvailable = sizeContainer.querySelector('.size-option:not(.unavailable)');
                    if (firstAvailable) {
                        firstAvailable.classList.add('selected');
                        firstAvailable.querySelector('input').checked = true;
                    }
                }

                // Afficher/masquer le message d'indication
                if (sizeHint) {
                    sizeHint.style.display = hasUnavailable ? 'block' : 'none';
                }
            }

            // Filtrer les tailles au chargement initial
            const initialColor = document.querySelector('.color-option.selected');
            if (initialColor && hasColorSizes) {
                filterSizesByColor(initialColor.dataset.colorName);
            }

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

            // === SÉLECTEUR DE TECHNIQUES DROPDOWN ===
            const techniqueSelector = document.getElementById('techniqueSelector');
            const techniqueTrigger = document.getElementById('techniqueTrigger');
            const techniqueDropdown = document.getElementById('techniqueDropdown');
            const techniqueSearch = document.getElementById('techniqueSearch');
            const techniqueList = document.getElementById('techniqueList');
            const techniqueInput = document.getElementById('techniqueInput');
            const techniquePreviewName = document.getElementById('techniquePreviewName');
            const techniquePreviewDesc = document.getElementById('techniquePreviewDesc');
            const techniquePreviewPrice = document.getElementById('techniquePreviewPrice');
            const techniqueIndicator = document.getElementById('techniqueIndicator');

            const techniqueLabels = {
                'flex': 'FLEX',
                'flock': 'FLOCK',
                'broderie': 'BRODERIE',
                'sublimation': 'SUBLIMATION'
            };

            // Ouvrir/fermer le dropdown technique
            techniqueTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = techniqueDropdown.classList.contains('open');
                if (isOpen) {
                    closeTechniqueDropdown();
                } else {
                    openTechniqueDropdown();
                }
            });

            function openTechniqueDropdown() {
                // Détection intelligente : ouvrir vers le haut si pas assez d'espace en bas
                const triggerRect = techniqueTrigger.getBoundingClientRect();
                const dropdownHeight = 320; // max-height du dropdown
                const spaceBelow = window.innerHeight - triggerRect.bottom;
                const spaceAbove = triggerRect.top;

                // Ouvrir vers le haut si pas assez d'espace en bas ET assez en haut
                const openUp = spaceBelow < dropdownHeight && spaceAbove > spaceBelow;

                techniqueTrigger.classList.add('open');
                techniqueDropdown.classList.add('open');

                if (openUp) {
                    techniqueTrigger.classList.add('open-up');
                    techniqueDropdown.classList.add('open-up');
                }

                techniqueSearch.value = '';
                filterTechniques('');
                setTimeout(() => techniqueSearch.focus(), 100);
            }

            function closeTechniqueDropdown() {
                techniqueTrigger.classList.remove('open', 'open-up');
                techniqueDropdown.classList.remove('open', 'open-up');
            }

            // Fermer au clic extérieur
            document.addEventListener('click', function(e) {
                if (techniqueSelector && !techniqueSelector.contains(e.target)) {
                    closeTechniqueDropdown();
                }
            });

            // Recherche de techniques
            techniqueSearch.addEventListener('input', function() {
                filterTechniques(this.value.toLowerCase());
            });

            function filterTechniques(query) {
                const items = techniqueList.querySelectorAll('.technique-list-item');
                items.forEach(item => {
                    const name = item.dataset.label.toLowerCase();
                    const desc = (item.dataset.description || '').toLowerCase();
                    if (name.includes(query) || desc.includes(query)) {
                        item.classList.remove('hidden');
                    } else {
                        item.classList.add('hidden');
                    }
                });
            }

            // Sélection d'une technique
            techniqueList.addEventListener('click', function(e) {
                const item = e.target.closest('.technique-list-item');
                if (!item) return;

                const technique = item.dataset.technique;
                const label = item.dataset.label;
                const description = item.dataset.description || '';
                const price = parseFloat(item.dataset.price) || 0;

                // Mettre à jour l'input hidden
                techniqueInput.value = technique;

                // Mettre à jour le preview du trigger
                techniquePreviewName.textContent = label;
                techniquePreviewDesc.textContent = description;
                techniquePreviewPrice.textContent = price === 0 ? 'Inclus' : '+' + formatPrice(price);

                // Mettre à jour la sélection visuelle
                techniqueList.querySelectorAll('.technique-list-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');

                // Mettre à jour la classe technique sur le texte preview
                const techLower = technique.toLowerCase();
                previewText.className = previewText.className.replace(/\btechnique-\S+/g, '').trim();
                previewText.classList.add('technique-' + techLower);

                // Mettre à jour l'indicateur
                if (techniqueIndicator) {
                    techniqueIndicator.textContent = techniqueLabels[techLower] || techLower.toUpperCase();
                }

                // Fermer le dropdown
                closeTechniqueDropdown();
            });

            // Fonction helper pour formater le prix
            function formatPrice(price) {
                return price.toFixed(2).replace('.', ',') + ' €';
            }

            // Initialiser la technique depuis la première option sélectionnée
            const firstTechnique = document.querySelector('.technique-list-item.selected');
            if (firstTechnique) {
                const technique = firstTechnique.dataset.technique.toLowerCase();
                previewText.classList.add('technique-' + technique);
                if (techniqueIndicator) {
                    techniqueIndicator.textContent = techniqueLabels[technique] || technique.toUpperCase();
                }
            }

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

                    // Ouvrir la lightbox avec zone d'impression et callback de synchronisation
                    PersonnalyLightbox.open({
                        imageSrc: imgSrc,
                        imageAlt: '<?= h($product['name']) ?>',
                        text: text || null,
                        font: font + ', ' + category,
                        fontSize: '2.5rem',
                        textX: currentX,
                        textY: currentY,
                        textColor: previewText.style.color || '#FF1493',
                        // Zone d'impression pour contraindre le drag
                        printZone: {
                            x: zone.x,
                            y: zone.y,
                            width: zone.width,
                            height: zone.height,
                            label: zone.label || 'Zone d\'impression'
                        },
                        // Callback de synchronisation : met à jour le configurateur principal
                        onPositionChange: function(newX, newY) {
                            currentX = newX;
                            currentY = newY;
                            updateTextPosition();
                        }
                    });
                });
            }

            // === MODAL RENDU RÉEL ===
            const realRenderBtn = document.getElementById('realRenderBtn');
            if (realRenderBtn && window.PersonnalyRealRender) {
                realRenderBtn.addEventListener('click', function() {
                    // Récupérer la technique sélectionnée (nouveau dropdown)
                    const techEl = document.querySelector('.technique-list-item.selected');
                    const technique = techEl ? techEl.dataset.technique : (techniqueInput ? techniqueInput.value : 'flex');

                    // Ouvrir le modal avec les images de cette technique
                    PersonnalyRealRender.open(technique);
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

            // ============================================
            // MOBILE WIZARD MODE
            // Interface pas-à-pas pour très petits écrans
            // ============================================

            (function initMobileWizard() {
                const wizard = document.getElementById('mobileWizard');
                if (!wizard) return;

                // Éléments du wizard
                const stepDots = wizard.querySelectorAll('.wizard-step-dot');
                const stepContents = wizard.querySelectorAll('.wizard-step');
                const stepLabel = document.getElementById('wizardStepLabel');
                const btnPrev = document.getElementById('wizardPrev');
                const btnNext = document.getElementById('wizardNext');
                const btnAdd = document.getElementById('wizardAdd');

                // Mini preview
                const miniText = document.getElementById('wizardMiniText');
                const miniPreviewImg = document.getElementById('wizardPreviewImg');

                // Step inputs
                const wizardTextInput = document.getElementById('wizardTextInput');
                const wizardFonts = document.getElementById('wizardFonts');
                const wizardTextColors = document.getElementById('wizardTextColors');
                const wizardPositions = document.getElementById('wizardPositions');
                const wizardSizes = document.getElementById('wizardSizes');
                const wizardProductColors = document.getElementById('wizardProductColors');

                // Summary elements
                const summaryText = document.getElementById('summaryText');
                const summaryFont = document.getElementById('summaryFont');
                const summarySize = document.getElementById('summarySize');
                const summaryColor = document.getElementById('summaryColor');
                const wizardSummaryText = document.getElementById('wizardSummaryText');
                const wizardSummaryImg = document.getElementById('wizardSummaryImg');

                // Labels des étapes
                const stepLabels = ['Texte', 'Style', 'Position', 'Produit', 'Récap'];

                let currentStep = 1;
                const totalSteps = 5;

                // État du wizard
                let wizardState = {
                    text: '',
                    font: '<?= h($selectedFont['value']) ?>',
                    fontLabel: '<?= h($selectedFont['label']) ?>',
                    fontCategory: '<?= h($selectedFont['category'] ?? 'sans-serif') ?>',
                    textColor: '<?= h($selectedTextColor['hex']) ?>',
                    textColorValue: '<?= h($selectedTextColor['value']) ?>',
                    positionY: <?= $printZone['y'] + ($printZone['height'] / 2) ?>,
                    positionX: 50,
                    size: 'M',
                    color: '<?= array_key_first($colors) ?>',
                    colorHex: '<?= reset($colors) ?>'
                };

                // Fonction pour changer d'étape
                function goToStep(step) {
                    if (step < 1 || step > totalSteps) return;

                    // Mettre à jour l'étape courante
                    currentStep = step;

                    // Mettre à jour les dots
                    stepDots.forEach((dot, idx) => {
                        dot.classList.remove('active', 'completed');
                        if (idx + 1 < step) {
                            dot.classList.add('completed');
                        } else if (idx + 1 === step) {
                            dot.classList.add('active');
                        }
                    });

                    // Mettre à jour le contenu
                    stepContents.forEach(content => {
                        content.classList.remove('active');
                        if (parseInt(content.dataset.step) === step) {
                            content.classList.add('active');
                        }
                    });

                    // Mettre à jour le label
                    stepLabel.textContent = step + '/' + totalSteps + ' ' + stepLabels[step - 1];

                    // Mettre à jour les boutons
                    btnPrev.style.display = step === 1 ? 'none' : 'flex';
                    btnNext.style.display = step === totalSteps ? 'none' : 'flex';
                    btnAdd.style.display = step === totalSteps ? 'flex' : 'none';

                    // Au dernier step, mettre à jour le récap
                    if (step === totalSteps) {
                        updateSummary();
                    }
                }

                // Mettre à jour le mini preview
                function updateMiniPreview() {
                    if (miniText) {
                        miniText.textContent = wizardState.text || '';
                        miniText.style.color = wizardState.textColor;
                        miniText.style.fontFamily = "'" + wizardState.font + "', " + wizardState.fontCategory;
                        miniText.style.top = (wizardState.positionY / 100 * 70) + '%';
                    }
                }

                // Mettre à jour le récapitulatif
                function updateSummary() {
                    if (summaryText) summaryText.textContent = wizardState.text || '-';
                    if (summaryFont) summaryFont.textContent = wizardState.fontLabel;
                    if (summarySize) summarySize.textContent = wizardState.size;
                    if (summaryColor) summaryColor.textContent = wizardState.color.charAt(0).toUpperCase() + wizardState.color.slice(1);

                    // Preview final
                    if (wizardSummaryText) {
                        wizardSummaryText.textContent = wizardState.text || '';
                        wizardSummaryText.style.color = wizardState.textColor;
                        wizardSummaryText.style.fontFamily = "'" + wizardState.font + "', " + wizardState.fontCategory;
                        wizardSummaryText.style.left = '50%';
                        wizardSummaryText.style.top = wizardState.positionY + '%';
                    }
                }

                // Synchroniser avec le formulaire principal
                function syncWithMainForm() {
                    // Texte
                    const mainTextInput = document.getElementById('customText');
                    if (mainTextInput) mainTextInput.value = wizardState.text;

                    // Police
                    const mainFontInput = document.getElementById('fontInput');
                    if (mainFontInput) mainFontInput.value = wizardState.font;

                    // Couleur texte
                    const mainTextColorInputs = document.querySelectorAll('input[name="text_color"]');
                    mainTextColorInputs.forEach(input => {
                        input.checked = input.value === wizardState.textColorValue;
                    });

                    // Position
                    const posXInput = document.getElementById('positionX');
                    const posYInput = document.getElementById('positionY');
                    if (posXInput) posXInput.value = wizardState.positionX;
                    if (posYInput) posYInput.value = wizardState.positionY;

                    // Taille
                    const mainSizeInputs = document.querySelectorAll('input[name="size"]');
                    mainSizeInputs.forEach(input => {
                        input.checked = input.value === wizardState.size;
                    });

                    // Couleur produit
                    const mainColorInputs = document.querySelectorAll('input[name="color"]');
                    mainColorInputs.forEach(input => {
                        input.checked = input.value === wizardState.color;
                    });
                }

                // === EVENT LISTENERS ===

                // Navigation
                btnNext.addEventListener('click', () => {
                    syncWithMainForm();
                    goToStep(currentStep + 1);
                });

                btnPrev.addEventListener('click', () => {
                    goToStep(currentStep - 1);
                });

                // Step 1: Texte
                if (wizardTextInput) {
                    wizardTextInput.addEventListener('input', function() {
                        wizardState.text = this.value.trim();
                        updateMiniPreview();
                    });
                    // Init depuis preset
                    if (wizardTextInput.value) {
                        wizardState.text = wizardTextInput.value.trim();
                        updateMiniPreview();
                    }
                }

                // Step 2: Polices
                if (wizardFonts) {
                    wizardFonts.addEventListener('click', function(e) {
                        const btn = e.target.closest('.wizard-font-btn');
                        if (!btn) return;

                        wizardFonts.querySelectorAll('.wizard-font-btn').forEach(b => b.classList.remove('selected'));
                        btn.classList.add('selected');

                        wizardState.font = btn.dataset.font;
                        wizardState.fontLabel = btn.textContent.trim();
                        wizardState.fontCategory = btn.dataset.category || 'sans-serif';
                        updateMiniPreview();
                    });
                }

                // Step 2: Couleur texte
                if (wizardTextColors) {
                    wizardTextColors.addEventListener('click', function(e) {
                        const btn = e.target.closest('.wizard-color-btn');
                        if (!btn) return;

                        wizardTextColors.querySelectorAll('.wizard-color-btn').forEach(b => b.classList.remove('selected'));
                        btn.classList.add('selected');

                        wizardState.textColor = btn.dataset.color;
                        wizardState.textColorValue = btn.dataset.value;
                        updateMiniPreview();
                    });
                }

                // Step 3: Position
                if (wizardPositions) {
                    wizardPositions.addEventListener('click', function(e) {
                        const btn = e.target.closest('.position-preset-btn');
                        if (!btn) return;

                        wizardPositions.querySelectorAll('.position-preset-btn').forEach(b => b.classList.remove('selected'));
                        btn.classList.add('selected');

                        wizardState.positionY = parseFloat(btn.dataset.y);
                        updateMiniPreview();
                    });
                }

                // Step 4: Taille
                if (wizardSizes) {
                    wizardSizes.addEventListener('click', function(e) {
                        const btn = e.target.closest('.wizard-option');
                        if (!btn) return;

                        wizardSizes.querySelectorAll('.wizard-option').forEach(b => b.classList.remove('selected'));
                        btn.classList.add('selected');

                        wizardState.size = btn.dataset.size;
                    });
                }

                // Step 4: Couleur produit
                if (wizardProductColors) {
                    wizardProductColors.addEventListener('click', function(e) {
                        const btn = e.target.closest('.wizard-color-btn');
                        if (!btn) return;

                        wizardProductColors.querySelectorAll('.wizard-color-btn').forEach(b => b.classList.remove('selected'));
                        btn.classList.add('selected');

                        wizardState.color = btn.dataset.color;
                        wizardState.colorHex = btn.dataset.hex;

                        // Mettre à jour l'image si variante disponible
                        if (btn.dataset.imageFront && miniPreviewImg) {
                            miniPreviewImg.src = btn.dataset.imageFront;
                        }
                        if (btn.dataset.imageFront && wizardSummaryImg) {
                            wizardSummaryImg.src = btn.dataset.imageFront;
                        }
                    });
                }

                // Synchroniser au submit
                const form = document.getElementById('customizationForm');
                if (form) {
                    form.addEventListener('submit', function() {
                        syncWithMainForm();
                    });
                }

                // Initialiser
                updateMiniPreview();

            })();

        })();
    </script>
</body>
</html>
