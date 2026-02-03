<?php
/**
 * PERSONNALY - Page d'accueil dynamique
 * Rendu automatique des sections configurées en admin
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/helpers/FontLoader.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Pack.php';
require_once __DIR__ . '/../app/models/HomepageSection.php';
require_once __DIR__ . '/../app/models/BlogPost.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/services/BrandingService.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';

$cartCount = Cart::count();

// Branding dynamique
$brandingService = new BrandingService();

// Chargement des sections (toutes en mode preview builder, sinon actives uniquement)
$sectionModel = new HomepageSection();
$isBuilderPreview = isset($_GET['preview']) && $_GET['preview'] === 'builder';

if ($isBuilderPreview) {
    // Mode preview : charger toutes les sections avec leurs items
    $sections = $sectionModel->findAll();
    foreach ($sections as &$section) {
        $section['items'] = $sectionModel->getItems($section['id']);
    }
    unset($section);
} else {
    // Mode normal : seulement les sections actives
    $sections = $sectionModel->findActive();
}

// Modèles pour les données
$productModel = new Product();
$packModel = new Pack();
$blogModel = new BlogPost();
$categoryModel = new Category();

// Préparer les données pour chaque section
foreach ($sections as &$section) {
    switch ($section['type']) {
        case 'featured_products':
            // Charger les produits liés
            $section['products'] = [];
            if (!empty($section['items'])) {
                foreach ($section['items'] as $item) {
                    if ($item['item_type'] === 'product' && $item['item_active']) {
                        $product = $productModel->findById($item['item_id']);
                        if ($product && $product['active']) {
                            // Ajouter les noms des catégories du produit
                            $product['category_names'] = $categoryModel->getCategoryNamesByProduct($product['id']);
                            $section['products'][] = $product;
                        }
                    }
                }
            }
            break;

        case 'featured_packs':
            // Charger les packs liés
            $section['packs'] = [];
            if (!empty($section['items'])) {
                foreach ($section['items'] as $item) {
                    if ($item['item_type'] === 'pack' && $item['item_active']) {
                        $pack = $packModel->findById($item['item_id']);
                        if ($pack && $pack['status'] === 'active') {
                            $pack['first_product'] = $packModel->getFirstProduct($pack['id']);
                            $section['packs'][] = $pack;
                        }
                    }
                }
            }
            break;

        case 'blog_slider':
            // Charger les articles sélectionnés
            $section['posts'] = [];
            if (!empty($section['items'])) {
                foreach ($section['items'] as $item) {
                    if ($item['item_type'] === 'blog' && $item['item_active']) {
                        $post = $blogModel->findById($item['item_id']);
                        if ($post && $post['status'] === 'published') {
                            $section['posts'][] = $post;
                        }
                    }
                }
            }
            // Fallback: si aucun article sélectionné, charger les derniers publiés
            if (empty($section['posts'])) {
                $limit = $section['config']['limit'] ?? 6;
                $section['posts'] = $blogModel->findPublished($limit);
            }
            break;

        case 'featured_category':
            // Charger les produits de la catégorie
            $section['category'] = null;
            $section['category_products'] = [];
            if (!empty($section['config']['category_id'])) {
                $catId = (int) $section['config']['category_id'];
                $section['category'] = $categoryModel->findById($catId);
                if ($section['category'] && $section['category']['status'] === 'active') {
                    $limit = $section['config']['products_limit'] ?? 8;
                    $section['category_products'] = $categoryModel->getProducts($catId, $limit);
                }
            }
            break;
    }
}
unset($section);

// Vérifier si au moins une section featured_packs existe
$hasPacks = false;
foreach ($sections as $s) {
    if ($s['type'] === 'featured_packs' && !empty($s['packs'])) {
        $hasPacks = true;
        break;
    }
}

/**
 * Génère les styles inline pour une section
 * @param array $section
 * @param bool $hasBackgroundImage Si true, ne pas ajouter background-color (sera géré autrement)
 * @return string CSS inline
 */
function getSectionInlineStyles(array $section, bool $hasBackgroundImage = false): string {
    $styles = [];
    $style = $section['config']['style'] ?? [];

    // Background color (seulement si pas d'image de fond)
    if (!empty($style['background_color']) && !$hasBackgroundImage) {
        $styles[] = 'background: ' . htmlspecialchars($style['background_color']) . ' !important';
    }

    // Text color (avec !important pour écraser le CSS)
    if (!empty($style['text_color'])) {
        $styles[] = 'color: ' . htmlspecialchars($style['text_color']) . ' !important';
    }

    // Padding Y
    $paddingMap = [
        'none' => '0',
        'small' => '2rem',
        'medium' => '4rem',
        'large' => '6rem',
        'xlarge' => '8rem'
    ];
    $paddingY = $style['padding_y'] ?? 'medium';
    if (isset($paddingMap[$paddingY])) {
        $styles[] = 'padding-top: ' . $paddingMap[$paddingY] . ' !important';
        $styles[] = 'padding-bottom: ' . $paddingMap[$paddingY] . ' !important';
    }

    return !empty($styles) ? implode('; ', $styles) : '';
}

/**
 * Génère les styles inline pour un titre
 * @param array $section
 * @return string CSS inline
 */
function getTitleStyles(array $section): string {
    $styles = [];
    $typo = $section['config']['typography'] ?? [];

    // Font family
    if (!empty($typo['font_family'])) {
        $styles[] = "font-family: '" . htmlspecialchars($typo['font_family']) . "', sans-serif";
    }

    // Title size
    $sizeMap = [
        'small' => '1.5rem',
        'medium' => '2rem',
        'large' => '2.5rem',
        'xlarge' => '3.5rem'
    ];
    if (!empty($typo['title_size']) && isset($sizeMap[$typo['title_size']])) {
        $styles[] = 'font-size: ' . $sizeMap[$typo['title_size']];
    }

    // Title color (supporte les dégradés)
    if (!empty($typo['title_color'])) {
        $color = $typo['title_color'];
        if (strpos($color, 'gradient') !== false) {
            // Appliquer un dégradé au texte
            $styles[] = 'background: ' . htmlspecialchars($color);
            $styles[] = '-webkit-background-clip: text';
            $styles[] = 'background-clip: text';
            $styles[] = '-webkit-text-fill-color: transparent';
        } else {
            $styles[] = 'color: ' . htmlspecialchars($color);
        }
    }

    // Bold
    if (!empty($typo['bold']) && $typo['bold'] === '1') {
        $styles[] = 'font-weight: 800';
    }

    // Italic
    if (!empty($typo['italic']) && $typo['italic'] === '1') {
        $styles[] = 'font-style: italic';
    }

    // Underline
    if (!empty($typo['underline']) && $typo['underline'] === '1') {
        $styles[] = 'text-decoration: underline';
    }

    // Uppercase
    if (!empty($typo['uppercase']) && $typo['uppercase'] === '1') {
        $styles[] = 'text-transform: uppercase';
        $styles[] = 'letter-spacing: 2px';
    }

    // Alignment
    if (!empty($typo['align'])) {
        $styles[] = 'text-align: ' . htmlspecialchars($typo['align']);
    }

    // Offset Y (margin-top)
    if (!empty($typo['offset_y']) && $typo['offset_y'] != 0) {
        $styles[] = 'margin-top: ' . (int)$typo['offset_y'] . 'px';
    }

    // Offset X (transform translateX)
    if (!empty($typo['offset_x']) && $typo['offset_x'] != 0) {
        $styles[] = 'transform: translateX(' . (int)$typo['offset_x'] . 'px)';
    }

    return !empty($styles) ? implode('; ', $styles) : '';
}

/**
 * Génère les styles inline pour un sous-titre
 * @param array $section
 * @return string CSS inline
 */
function getSubtitleStyles(array $section): string {
    $styles = [];
    $typo = $section['config']['typography'] ?? [];

    // Font family (séparé du titre, fallback sur font_family si non défini)
    $subtitleFont = $typo['subtitle_font_family'] ?? $typo['font_family'] ?? null;
    if (!empty($subtitleFont)) {
        $styles[] = "font-family: '" . htmlspecialchars($subtitleFont) . "', sans-serif";
    }

    // Subtitle color (supporte les dégradés)
    if (!empty($typo['subtitle_color'])) {
        $color = $typo['subtitle_color'];
        if (strpos($color, 'gradient') !== false) {
            // Appliquer un dégradé au texte
            $styles[] = 'background: ' . htmlspecialchars($color);
            $styles[] = '-webkit-background-clip: text';
            $styles[] = 'background-clip: text';
            $styles[] = '-webkit-text-fill-color: transparent';
        } else {
            $styles[] = 'color: ' . htmlspecialchars($color);
        }
    }

    // Bold
    if (!empty($typo['subtitle_bold']) && $typo['subtitle_bold'] === '1') {
        $styles[] = 'font-weight: 700';
    }

    // Italic
    if (!empty($typo['subtitle_italic']) && $typo['subtitle_italic'] === '1') {
        $styles[] = 'font-style: italic';
    }

    // Underline
    if (!empty($typo['subtitle_underline']) && $typo['subtitle_underline'] === '1') {
        $styles[] = 'text-decoration: underline';
    }

    return !empty($styles) ? implode('; ', $styles) : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PERSONNALY - Personnalisation Textile pour Toute la Famille</title>
    <meta name="description" content="Créez des vêtements uniques pour hommes, femmes et enfants. Personnalisation textile de qualité.">
    <?php
    // Favicon dynamique depuis branding
    $favicon = $brandingService->getFavicon();
    if ($favicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= h($favicon) ?>">
    <?php endif; ?>
    <?= $brandingService->getFontLinks() ?>
    <!-- Polices dynamiques depuis la base de données -->
    <?= FontLoader::renderHead() ?>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <?= $brandingService->getStyleBlock() ?>
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
        .navbar-logo {
            height: 40px;
            width: auto;
            max-width: 200px;
            object-fit: contain;
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

        /* ===== HERO SECTION ===== */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: var(--gradient-dark);
            position: relative;
            overflow: hidden;
            padding-top: 80px;
        }
        .hero-with-bg {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .hero-with-bg::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(13, 13, 13, 0.85) 0%, rgba(13, 13, 13, 0.7) 100%);
            z-index: 0;
        }
        .hero-with-bg .container {
            position: relative;
            z-index: 1;
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
        /* Override: hérite la couleur du parent si définie inline */
        .hero[style*="color"] h1,
        .hero[style*="color"] p,
        .hero[style*="color"] .hero-badge {
            color: inherit !important;
        }
        .hero[style*="color"] h1 span {
            background: none !important;
            -webkit-text-fill-color: inherit !important;
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

        /* ===== PRODUCTS SECTION ===== */
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
            aspect-ratio: 4/3;
            background: linear-gradient(145deg, #fafafa 0%, #f0f0f0 100%);
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
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 20px;
            box-sizing: border-box;
        }
        .product-image:has(img)::before { display: none; }
        .product-category {
            position: absolute;
            top: 15px;
            left: 15px;
        }
        .product-info { padding: var(--spacing-lg); }
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

        /* ===== INSPIRATIONS/PACKS SECTION ===== */
        .inspirations-section {
            padding: 100px 0;
            background: var(--black-soft);
            position: relative;
            overflow: hidden;
        }
        .inspirations-section::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: var(--pink-main);
            border-radius: 50%;
            filter: blur(200px);
            opacity: 0.08;
            top: -100px;
            left: -100px;
        }
        .inspirations-section .section-header h2,
        .inspirations-section .section-header p { color: var(--white); }
        .inspirations-section .section-header p { color: rgba(255, 255, 255, 0.7); }
        .inspirations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--spacing-lg);
            position: relative;
            z-index: 1;
        }
        .inspiration-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all var(--transition-normal);
            backdrop-filter: blur(10px);
        }
        .inspiration-card:hover {
            transform: translateY(-8px);
            border-color: var(--pink-main);
            box-shadow: 0 20px 40px rgba(255, 105, 180, 0.15);
        }
        .inspiration-image {
            aspect-ratio: 16/10;
            background: linear-gradient(135deg, rgba(255,105,180,0.2) 0%, rgba(61,255,192,0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .inspiration-image::before {
            content: '✨';
            font-size: 3rem;
            opacity: 0.5;
        }
        .inspiration-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .inspiration-image:has(img)::before { display: none; }
        .inspiration-type {
            position: absolute;
            top: 12px;
            left: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            background: rgba(0, 0, 0, 0.6);
            color: var(--white);
            backdrop-filter: blur(4px);
        }
        .inspiration-type.type-technique { background: var(--pink-main); }
        .inspiration-type.type-contextuel { background: var(--mint-dark); color: var(--black); }
        .inspiration-type.type-thematique { background: #9b59b6; }
        .inspiration-type.type-inspiration { background: #3498db; }
        .inspiration-info { padding: 20px; }
        .inspiration-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 8px;
        }
        .inspiration-info p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .inspiration-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gradient-pink);
            color: var(--white);
            padding: 10px 20px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition-fast);
        }
        .inspiration-cta:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-pink);
        }
        .inspiration-cta svg { width: 16px; height: 16px; }

        /* ===== CONTENT BLOCK SECTION ===== */
        .content-block-section {
            padding: 80px 0;
            background: var(--white);
        }
        .content-block-section.alt-bg {
            background: var(--gray-light);
        }
        .content-block-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }
        .content-block-inner.media-left {
            direction: rtl;
        }
        .content-block-inner.media-left > * {
            direction: ltr;
        }
        .content-block-inner.gallery-mode {
            grid-template-columns: 1fr;
            gap: 40px;
        }
        .content-block-text h2 {
            font-size: 2.2rem;
            margin-bottom: var(--spacing-md);
        }
        .content-block-text p {
            color: var(--gray);
            font-size: 1.05rem;
            line-height: 1.8;
            margin-bottom: var(--spacing-lg);
        }
        .content-block-media {
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        .content-block-media img,
        .content-block-media video {
            width: 100%;
            display: block;
        }
        .content-block-media .main-media {
            border-radius: var(--radius-lg);
        }
        /* Galerie de cartes individuelles */
        .content-block-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--spacing-lg);
        }
        .gallery-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-normal);
        }
        .gallery-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        .gallery-card img {
            width: 100%;
            aspect-ratio: 4/3;
            object-fit: cover;
            display: block;
        }

        /* ===== BLOG SLIDER SECTION ===== */
        .blog-section {
            padding: 100px 0;
            background: var(--gray-light);
        }
        .blog-slider {
            display: flex;
            gap: var(--spacing-lg);
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 20px;
        }
        .blog-slider::-webkit-scrollbar { height: 6px; }
        .blog-slider::-webkit-scrollbar-track { background: var(--gray-light); border-radius: 10px; }
        .blog-slider::-webkit-scrollbar-thumb { background: var(--pink-main); border-radius: 10px; }
        .blog-card {
            min-width: 320px;
            max-width: 320px;
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            scroll-snap-align: start;
            transition: all var(--transition-normal);
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        .blog-card-image {
            aspect-ratio: 16/10;
            background: linear-gradient(135deg, rgba(255,105,180,0.15) 0%, rgba(61,255,192,0.15) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .blog-card-image::before {
            content: '📝';
            font-size: 2.5rem;
            opacity: 0.5;
        }
        .blog-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .blog-card-image:has(img)::before { display: none; }
        .blog-card-content { padding: 20px; }
        .blog-card-content h3 {
            font-size: 1rem;
            margin-bottom: 8px;
            color: var(--black-soft);
        }
        .blog-card-content p {
            font-size: 0.9rem;
            color: var(--gray);
            line-height: 1.5;
        }
        .blog-card-date {
            font-size: 12px;
            color: var(--pink-main);
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* ===== NEWSLETTER SECTION ===== */
        .newsletter-section {
            position: relative;
            padding: 100px 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: var(--black-soft);
        }
        .newsletter-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(13, 13, 13, 0.9) 0%, rgba(30, 30, 30, 0.85) 100%);
            z-index: 0;
        }
        .newsletter-section .container {
            position: relative;
            z-index: 1;
        }
        .newsletter-content {
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
        }
        .newsletter-content h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: var(--spacing-md);
        }
        .newsletter-subtitle {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: var(--spacing-xl);
            line-height: 1.6;
        }
        .newsletter-form {
            margin-bottom: var(--spacing-lg);
        }
        .newsletter-input-group {
            display: flex;
            gap: 12px;
            max-width: 500px;
            margin: 0 auto;
        }
        .newsletter-input {
            flex: 1;
            padding: 16px 24px;
            font-size: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-full);
            background: rgba(255, 255, 255, 0.08);
            color: var(--white);
            outline: none;
            transition: all var(--transition-fast);
        }
        .newsletter-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .newsletter-input:focus {
            border-color: var(--pink-main);
            background: rgba(255, 255, 255, 0.12);
        }
        .newsletter-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 16px 28px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--white);
            background: var(--gradient-pink);
            border: none;
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: all var(--transition-normal);
            white-space: nowrap;
        }
        .newsletter-btn:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-pink);
        }
        .newsletter-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .newsletter-message {
            margin-top: var(--spacing-md);
            padding: 12px 20px;
            border-radius: var(--radius-md);
            font-weight: 500;
            display: none;
        }
        .newsletter-message.show {
            display: block;
        }
        .newsletter-message.success {
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-main);
            border: 1px solid rgba(61, 255, 192, 0.3);
        }
        .newsletter-message.error {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-main);
            border: 1px solid rgba(255, 105, 180, 0.3);
        }
        .newsletter-privacy {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.4);
            line-height: 1.6;
        }
        @media (max-width: 768px) {
            .newsletter-content h2 {
                font-size: 1.8rem;
            }
            .newsletter-input-group {
                flex-direction: column;
            }
            .newsletter-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* ===== FOOTER ===== */
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
        .footer-links a:hover { color: var(--pink-main); }
        .footer-bottom {
            text-align: center;
            padding-top: var(--spacing-lg);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.4);
            font-size: 14px;
        }

        /* ===== EMPTY STATE ===== */
        .empty-products {
            text-align: center;
            padding: var(--spacing-xxl);
        }
        .empty-products-icon {
            font-size: 5rem;
            margin-bottom: var(--spacing-lg);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .navbar-nav { display: none; }
            .hero-categories { flex-direction: column; align-items: center; }
            .content-block-inner {
                grid-template-columns: 1fr;
            }
            .content-block-inner.media-left {
                direction: ltr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../app/templates/header.php'; ?>

    <?php
    // Rendu dynamique des sections
    $contentBlockIndex = 0;
    foreach ($sections as $section):
        switch ($section['type']):

            // ===== HERO =====
            case 'hero':
                $hasHeroImage = $section['media_type'] === 'image' && !empty($section['media_url']);
                $sectionStyles = getSectionInlineStyles($section, $hasHeroImage);
                $heroStyleParts = [];

                // Image de fond
                if ($hasHeroImage) {
                    $imgUrl = $section['media_url'];
                    // Ne pas ajouter /public si déjà présent
                    if (strpos($imgUrl, '/public') !== 0 && strpos($imgUrl, 'http') !== 0) {
                        $imgUrl = '/public' . $imgUrl;
                    }
                    $heroStyleParts[] = 'background-image: url(\'' . h($imgUrl) . '\')';
                }

                if ($sectionStyles) {
                    $heroStyleParts[] = $sectionStyles;
                }
                $heroStyle = !empty($heroStyleParts) ? 'style="' . implode('; ', $heroStyleParts) . '"' : '';

                // Config du hero
                $heroBadge = $section['config']['badge'] ?? '';
                $heroHighlight = $section['config']['highlight'] ?? '';
                $heroCta2Text = $section['config']['cta2_text'] ?? '';
                $heroCta2Url = $section['config']['cta2_url'] ?? '';
    ?>
    <section class="hero <?= $hasHeroImage ? 'hero-with-bg' : '' ?>" data-section-id="<?= $section['id'] ?>" <?= $heroStyle ?>>
        <div class="container">
            <div class="hero-content">
                <?php
                // Ordre des éléments (par défaut: badge, title, subtitle, buttons)
                $elementsOrder = $section['config']['elements_order'] ?? ['badge', 'title', 'subtitle', 'buttons'];

                foreach ($elementsOrder as $element):
                    switch ($element):
                        case 'badge':
                            if ($heroBadge): ?>
                <div class="hero-badge">
                    <?= h($heroBadge) ?>
                </div>
                <?php       endif;
                            break;

                        case 'title':
                            if ($section['title']):
                                $titleStyle = getTitleStyles($section);
                            ?>
                <h1<?= $titleStyle ? ' style="' . $titleStyle . '"' : '' ?>><?= h($section['title']) ?><?php if ($heroHighlight): ?> <span><?= h($heroHighlight) ?></span><?php endif; ?></h1>
                <?php       endif;
                            break;

                        case 'subtitle':
                            if ($section['subtitle']):
                                $subtitleStyle = getSubtitleStyles($section);
                            ?>
                <p<?= $subtitleStyle ? ' style="' . $subtitleStyle . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                <?php       endif;
                            break;

                        case 'buttons':
                            if ($section['cta_text'] || $heroCta2Text): ?>
                <div class="hero-buttons">
                    <?php if ($section['cta_url'] && $section['cta_text']): ?>
                        <a href="<?= h($section['cta_url']) ?>" class="btn btn-primary">
                            <?= h($section['cta_text']) ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                    <?php if ($heroCta2Text && $heroCta2Url): ?>
                        <a href="<?= h($heroCta2Url) ?>" class="btn btn-dark"><?= h($heroCta2Text) ?></a>
                    <?php endif; ?>
                </div>
                <?php       endif;
                            break;
                    endswitch;
                endforeach;
                ?>
            </div>
        </div>
    </section>
    <?php
            break;

            // ===== FEATURED PRODUCTS =====
            case 'featured_products':
                $sectionStyles = getSectionInlineStyles($section);
    ?>
    <section class="products-section" id="produits" data-section-id="<?= $section['id'] ?>" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
        <div class="container">
            <div class="section-header">
                <?php $titleStyle = getTitleStyles($section); $subtitleStyle = getSubtitleStyles($section); ?>
                <h2<?= $titleStyle ? ' style="' . $titleStyle . '"' : '' ?>><?= h($section['title'] ?: 'Nos Produits') ?></h2>
                <?php if ($section['subtitle']): ?>
                    <p<?= $subtitleStyle ? ' style="' . $subtitleStyle . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                <?php endif; ?>
            </div>

            <?php if (empty($section['products'])): ?>
                <div class="empty-products">
                    <div class="empty-products-icon">👕</div>
                    <h3>Produits bientôt disponibles</h3>
                    <p class="text-muted">Notre catalogue est en cours de préparation.</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($section['products'] as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if (!empty($product['category_names'])): ?>
                                    <span class="product-category badge badge-mint">
                                        <?= h($product['category_names'][0]) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($product['image_front_url'])): ?>
                                    <?= picture($product['image_front_url'], $product['name']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h3><?= h($product['name']) ?></h3>
                                <p><?= h($product['description'] ?? 'Personnalisable avec votre design') ?></p>
                                <div class="product-footer">
                                    <span class="product-price"><?= formatPrice($product['base_price']) ?></span>
                                    <a href="/public/product.php?id=<?= $product['id'] ?>" class="product-btn">Personnaliser</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
            break;

            // ===== FEATURED CATEGORY =====
            case 'featured_category':
                if (empty($section['category']) || empty($section['category_products'])) break;
                $cat = $section['category'];
                $sectionStyles = getSectionInlineStyles($section);
    ?>
    <section class="category-section" id="categorie-<?= h($cat['slug']) ?>" data-section-id="<?= $section['id'] ?>" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
        <div class="container">
            <div class="section-header">
                <?php $titleStyle = getTitleStyles($section); $subtitleStyle = getSubtitleStyles($section); ?>
                <h2<?= $titleStyle ? ' style="' . $titleStyle . '"' : '' ?>><?= h($section['title'] ?: $cat['name']) ?></h2>
                <?php if ($section['subtitle']): ?>
                    <p<?= $subtitleStyle ? ' style="' . $subtitleStyle . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                <?php elseif (!empty($cat['description'])): ?>
                    <p<?= $subtitleStyle ? ' style="' . $subtitleStyle . '"' : '' ?>><?= h($cat['description']) ?></p>
                <?php endif; ?>
            </div>

            <div class="products-grid">
                <?php foreach ($section['category_products'] as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <span class="product-category badge badge-mint">
                                <?= h($cat['name']) ?>
                            </span>
                            <?php if (!empty($product['image_front_url'])): ?>
                                <?= picture($product['image_front_url'], $product['name']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3><?= h($product['name']) ?></h3>
                            <p><?= h($product['description'] ?? 'Personnalisable avec votre design') ?></p>
                            <div class="product-footer">
                                <span class="product-price"><?= formatPrice($product['base_price']) ?></span>
                                <a href="/public/product.php?id=<?= $product['id'] ?>" class="product-btn">Personnaliser</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($section['cta_text']) && !empty($section['cta_url'])): ?>
                <div class="section-cta" style="text-align: center; margin-top: 30px;">
                    <a href="<?= h($section['cta_url']) ?>" class="btn btn-primary">
                        <?= h($section['cta_text']) ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
            break;

            // ===== FEATURED PACKS =====
            case 'featured_packs':
                if (empty($section['packs'])) break;
                $typeLabels = [
                    'technique' => 'Technique',
                    'contextuel' => 'Contextuel',
                    'thematique' => 'Thématique',
                    'inspiration' => 'Inspiration'
                ];
                $sectionStyles = getSectionInlineStyles($section);
    ?>
    <section class="inspirations-section" id="inspirations" data-section-id="<?= $section['id'] ?>" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
        <div class="container">
            <div class="section-header">
                <?php $titleStyle = getTitleStyles($section); $subtitleStyle = getSubtitleStyles($section); ?>
                <h2<?= $titleStyle ? ' style="' . $titleStyle . '"' : '' ?>><?= h($section['title'] ?: 'Nos Idées Tendance') ?></h2>
                <?php if ($section['subtitle']): ?>
                    <p<?= $subtitleStyle ? ' style="' . $subtitleStyle . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                <?php endif; ?>
            </div>

            <div class="inspirations-grid">
                <?php foreach ($section['packs'] as $pack):
                    if (!$pack['first_product']) continue;
                ?>
                    <div class="inspiration-card">
                        <div class="inspiration-image">
                            <span class="inspiration-type type-<?= h($pack['type']) ?>">
                                <?= h($typeLabels[$pack['type']] ?? 'Idée') ?>
                            </span>
                            <?php if (!empty($pack['cover_image_url'])): ?>
                                <?= picture($pack['cover_image_url'], $pack['name']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="inspiration-info">
                            <h3><?= h($pack['name']) ?></h3>
                            <?php if (!empty($pack['description'])): ?>
                                <p><?= h($pack['description']) ?></p>
                            <?php endif; ?>
                            <a href="/public/product.php?id=<?= $pack['first_product']['id'] ?>&pack_id=<?= $pack['id'] ?>" class="inspiration-cta">
                                Essayer cette idée
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
            break;

            // ===== CONTENT BLOCK =====
            case 'content_block':
                $contentBlockIndex++;
                $altBg = ($contentBlockIndex % 2 === 0) ? 'alt-bg' : '';
                $mediaLeft = ($section['config']['media_position'] ?? 'right') === 'left';
                $additionalMedia = $section['config']['additional_media'] ?? [];
                $hasMultipleMedia = !empty($additionalMedia);
                $hasMainMedia = $section['media_type'] !== 'none' && !empty($section['media_url']);
                // Mode galerie si plusieurs images additionnelles (sans image principale)
                $isGalleryMode = $hasMultipleMedia && !$hasMainMedia;
                $sectionStyles = getSectionInlineStyles($section);
    ?>
    <section class="content-block-section <?= $altBg ?>" data-section-id="<?= $section['id'] ?>" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
        <div class="container">
            <?php if ($isGalleryMode): ?>
                <!-- Mode Galerie : texte au-dessus, images en grille -->
                <div class="content-block-inner gallery-mode">
                    <div class="content-block-text" style="text-align: center; max-width: 800px; margin: 0 auto;">
                        <?php $titleStyle = getTitleStyles($section); $subtitleStyle = getSubtitleStyles($section); ?>
                        <?php if ($section['title']): ?>
                            <h2<?= $titleStyle ? ' style="' . $titleStyle . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if ($section['content']): ?>
                            <p<?= $subtitleStyle ? ' style="' . $subtitleStyle . '"' : '' ?>><?= nl2br(h($section['content'])) ?></p>
                        <?php endif; ?>
                        <?php if ($section['cta_url'] && $section['cta_text']): ?>
                            <a href="<?= h($section['cta_url']) ?>" class="btn btn-primary"><?= h($section['cta_text']) ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="content-block-gallery">
                        <?php foreach ($additionalMedia as $media): ?>
                            <div class="gallery-card">
                                <?= picture($media['url'], '') ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Mode classique : texte + média côte à côte -->
                <div class="content-block-inner <?= $mediaLeft ? 'media-left' : '' ?>">
                    <div class="content-block-text">
                        <?php $titleStyle = getTitleStyles($section); $subtitleStyle = getSubtitleStyles($section); ?>
                        <?php if ($section['title']): ?>
                            <h2<?= $titleStyle ? ' style="' . $titleStyle . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if ($section['content']): ?>
                            <p<?= $subtitleStyle ? ' style="' . $subtitleStyle . '"' : '' ?>><?= nl2br(h($section['content'])) ?></p>
                        <?php endif; ?>
                        <?php if ($section['cta_url'] && $section['cta_text']): ?>
                            <a href="<?= h($section['cta_url']) ?>" class="btn btn-primary"><?= h($section['cta_text']) ?></a>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasMainMedia): ?>
                        <div class="content-block-media">
                            <?php if ($section['media_type'] === 'video'): ?>
                                <video src="/public<?= h($section['media_url']) ?>" autoplay muted loop playsinline></video>
                            <?php else: ?>
                                <?= picture($section['media_url'], $section['title'], 'main-media') ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
            break;

            // ===== BLOG SLIDER =====
            case 'blog_slider':
                if (empty($section['posts'])) break;
                $sectionStyles = getSectionInlineStyles($section);
    ?>
    <section class="blog-section" data-section-id="<?= $section['id'] ?>" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
        <div class="container">
            <div class="section-header">
                <?php $titleStyle = getTitleStyles($section); $subtitleStyle = getSubtitleStyles($section); ?>
                <h2<?= $titleStyle ? ' style="' . $titleStyle . '"' : '' ?>><?= h($section['title'] ?: 'Notre Blog') ?></h2>
                <?php if ($section['subtitle']): ?>
                    <p<?= $subtitleStyle ? ' style="' . $subtitleStyle . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                <?php endif; ?>
            </div>

            <div class="blog-slider">
                <?php foreach ($section['posts'] as $post): ?>
                    <a href="/public/article.php?slug=<?= h($post['slug']) ?>" class="blog-card">
                        <div class="blog-card-image">
                            <?php if (!empty($post['cover_image_url'])): ?>
                                <?= picture($post['cover_image_url'], $post['title']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="blog-card-content">
                            <?php if ($post['published_at']): ?>
                                <div class="blog-card-date"><?= date('d M Y', strtotime($post['published_at'])) ?></div>
                            <?php endif; ?>
                            <h3><?= h($post['title']) ?></h3>
                            <?php if ($post['excerpt']): ?>
                                <p><?= h(substr($post['excerpt'], 0, 120)) ?>...</p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
            break;

            // ===== NEWSLETTER =====
            case 'newsletter':
                $sectionStyles = getSectionInlineStyles($section);
                $newsletterStyle = $sectionStyles;
                if ($section['media_type'] === 'image' && !empty($section['media_url'])) {
                    $newsletterStyle .= ($newsletterStyle ? '; ' : '') . 'background-image: url(\'/public' . h($section['media_url']) . '\')';
                }
    ?>
    <section class="newsletter-section" data-section-id="<?= $section['id'] ?>" style="<?= $newsletterStyle ?>">
        <div class="newsletter-overlay"></div>
        <div class="container">
            <div class="newsletter-content">
                <?php $titleStyle = getTitleStyles($section); $subtitleStyle = getSubtitleStyles($section); ?>
                <?php if ($section['title']): ?>
                    <h2<?= $titleStyle ? ' style="' . $titleStyle . '"' : '' ?>><?= h($section['title']) ?></h2>
                <?php endif; ?>
                <?php if ($section['subtitle']): ?>
                    <p class="newsletter-subtitle"<?= $subtitleStyle ? ' style="' . $subtitleStyle . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                <?php endif; ?>

                <form class="newsletter-form" id="newsletterForm" data-section-id="<?= $section['id'] ?>">
                    <div class="newsletter-input-group">
                        <input type="email" name="email" placeholder="Votre adresse email" required class="newsletter-input">
                        <button type="submit" class="newsletter-btn" data-original-text="<?= h($section['cta_text'] ?: 'S\'inscrire') ?>">
                            <?= h($section['cta_text'] ?: 'S\'inscrire') ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                    <div class="newsletter-message" id="newsletterMessage"></div>
                </form>

                <p class="newsletter-privacy">
                    En vous inscrivant, vous acceptez notre politique de confidentialité.<br>
                    Désabonnement possible à tout moment.
                </p>
            </div>
        </div>
    </section>
    <?php
            break;

        endswitch;
    endforeach;
    ?>

    <?php include __DIR__ . '/../app/templates/footer.php'; ?>

    <!-- Newsletter AJAX Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('newsletterForm');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const emailInput = form.querySelector('input[name="email"]');
            const submitBtn = form.querySelector('button[type="submit"]');
            const messageDiv = document.getElementById('newsletterMessage');
            const email = emailInput.value.trim();

            if (!email) return;

            // Disable form during submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Inscription en cours...';
            messageDiv.className = 'newsletter-message';
            messageDiv.textContent = '';

            // AJAX request
            fetch('/public/api/newsletter-subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    source: 'homepage'
                })
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.className = 'newsletter-message show ' + (data.success ? 'success' : 'error');
                messageDiv.textContent = data.message;

                if (data.success) {
                    emailInput.value = '';
                }
            })
            .catch(error => {
                messageDiv.className = 'newsletter-message show error';
                messageDiv.textContent = 'Une erreur est survenue. Veuillez réessayer.';
            })
            .finally(() => {
                submitBtn.disabled = false;
                const originalText = submitBtn.getAttribute('data-original-text') || "S'inscrire";
                submitBtn.innerHTML = originalText + ' <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
            });
        });
    });
    </script>
</body>
</html>
