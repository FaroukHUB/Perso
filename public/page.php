<?php
/**
 * PERSONNALY - Rendu de page personnalisée
 * Affiche les pages créées via le Page Builder
 */

// Afficher les erreurs PHP pour debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/helpers/FontLoader.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Page.php';
require_once __DIR__ . '/../app/models/PageSection.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Pack.php';
require_once __DIR__ . '/../app/models/BlogPost.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/services/BrandingService.php';

$cartCount = Cart::count();

// Branding dynamique
$brandingService = new BrandingService();

// Récupérer le slug de la page (normaliser en minuscules)
$slug = strtolower(trim($_GET['slug'] ?? ''));

// DEBUG: afficher le slug reçu (à supprimer après)
if (isset($_GET['debug'])) {
    $pageModel = new Page();
    $page = $pageModel->findBySlug($slug);
    $sectionModel = new PageSection();
    $sections = $page ? $sectionModel->findByPage($page['id'], false) : [];

    echo '<pre>DEBUG page.php:';
    echo "\nslug reçu: \"$slug\"";
    echo "\npage trouvée: " . ($page ? "OUI (id={$page['id']}, title={$page['title']})" : "NON");
    echo "\nnombre de sections: " . count($sections);
    if ($sections) {
        foreach ($sections as $s) {
            echo "\n  - Section #{$s['id']}: type={$s['type']}, status={$s['status']}, page_id={$s['page_id']}";
        }
    }
    echo "\n\nGET: " . print_r($_GET, true);
    echo '</pre>';
    exit;
}

if (empty($slug)) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

// Charger la page
$pageModel = new Page();
$page = $pageModel->findBySlug($slug);

// Mode preview builder (permet de voir les pages brouillon)
$isBuilderPreview = isset($_GET['preview']) && $_GET['preview'] === 'builder';

// Vérifier que la page existe
if (!$page) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

// Si pas en mode preview, la page doit être publiée
if (!$isBuilderPreview && $page['status'] !== 'published') {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

// Charger les sections
$sectionModel = new PageSection();

// En mode preview, charger toutes les sections (y compris brouillons)
// Sinon, seulement les actives
$sections = $sectionModel->findByPage($page['id'], !$isBuilderPreview);

// Modèles pour les données
$productModel = new Product();
$packModel = new Pack();
$blogModel = new BlogPost();
$categoryModel = new Category();

// Préparer les données pour chaque section
foreach ($sections as &$section) {
    switch ($section['type']) {
        case 'featured_products':
            $section['products'] = [];
            if (!empty($section['items'])) {
                foreach ($section['items'] as $item) {
                    if ($item['item_type'] === 'product' && $item['item_active']) {
                        $product = $productModel->findById($item['item_id']);
                        if ($product && $product['active']) {
                            $product['category_names'] = $categoryModel->getCategoryNamesByProduct($product['id']);
                            $section['products'][] = $product;
                        }
                    }
                }
            }
            break;

        case 'featured_packs':
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
            if (empty($section['posts'])) {
                $limit = $section['config']['limit'] ?? 6;
                $section['posts'] = $blogModel->findPublished($limit);
            }
            break;

        case 'featured_category':
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

/**
 * Génère les styles inline pour une section
 */
function getSectionInlineStyles(array $section, bool $hasBackgroundImage = false): string {
    $styles = [];
    $style = $section['config']['style'] ?? [];

    if (!empty($style['background_color']) && !$hasBackgroundImage) {
        $styles[] = 'background: ' . htmlspecialchars($style['background_color']) . ' !important';
    }

    if (!empty($style['text_color'])) {
        $styles[] = 'color: ' . htmlspecialchars($style['text_color']) . ' !important';
    }

    $paddingMap = [
        'none' => '0',
        'small' => '2rem',
        'medium' => '4rem',
        'large' => '6rem'
    ];
    if (!empty($style['padding_y'])) {
        $padding = $paddingMap[$style['padding_y']] ?? '4rem';
        $styles[] = 'padding-top: ' . $padding;
        $styles[] = 'padding-bottom: ' . $padding;
    }

    return empty($styles) ? '' : implode('; ', $styles);
}

// Meta tags
$metaTitle = $page['meta_title'] ?: $page['title'] . ' - PERSONNALY';
$metaDescription = $page['meta_description'] ?: '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($metaTitle) ?></title>
    <?php if ($metaDescription): ?>
    <meta name="description" content="<?= h($metaDescription) ?>">
    <?php endif; ?>
    <?= FontLoader::renderHead() ?>
    <?= $brandingService->renderStyleTag() ?>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/homepage.css">
</head>
<body>
    <?php include __DIR__ . '/../app/templates/header.php'; ?>

    <main class="custom-page">
        <?php if (empty($sections)): ?>
        <section class="section section-empty" style="padding: 80px 20px; text-align: center;">
            <div class="container">
                <h1><?= h($page['title']) ?></h1>
                <p style="color: #888;">Cette page est en cours de construction.</p>
            </div>
        </section>
        <?php else: ?>
            <?php foreach ($sections as $section): ?>
                <?php
                $sectionStyles = getSectionInlineStyles($section, !empty($section['media_url']) && $section['type'] === 'hero');
                ?>

                <?php if ($section['type'] === 'hero'): ?>
                <!-- Section Hero -->
                <section class="section section-hero" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <?php if (!empty($section['media_url'])): ?>
                        <?php if ($section['media_type'] === 'video'): ?>
                        <video class="hero-video" autoplay muted loop playsinline>
                            <source src="<?= h($section['media_url']) ?>" type="video/mp4">
                        </video>
                        <?php else: ?>
                        <div class="hero-image" style="background-image: url('<?= h($section['media_url']) ?>')"></div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="hero-overlay"></div>
                    <div class="hero-content container">
                        <?php if (!empty($section['config']['badge'])): ?>
                        <span class="hero-badge"><?= h($section['config']['badge']) ?></span>
                        <?php endif; ?>
                        <h1 class="hero-title"><?= h($section['title']) ?></h1>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="hero-subtitle"><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['cta_text'])): ?>
                        <div class="hero-cta">
                            <a href="<?= h($section['cta_url'] ?: '#') ?>" class="btn btn-primary btn-lg"><?= h($section['cta_text']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'content_block' || $section['type'] === 'text_only'): ?>
                <!-- Section Contenu -->
                <section class="section section-content" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title"><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle"><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <div class="content-wrapper">
                            <?php if (!empty($section['media_url']) && $section['type'] === 'content_block'): ?>
                            <div class="content-media">
                                <?php if ($section['media_type'] === 'video'): ?>
                                <video controls>
                                    <source src="<?= h($section['media_url']) ?>" type="video/mp4">
                                </video>
                                <?php else: ?>
                                <img src="<?= h($section['media_url']) ?>" alt="<?= h($section['title']) ?>">
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($section['content'])): ?>
                            <div class="content-text">
                                <?= nl2br(h($section['content'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($section['cta_text'])): ?>
                        <div class="section-cta">
                            <a href="<?= h($section['cta_url'] ?: '#') ?>" class="btn btn-primary"><?= h($section['cta_text']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'featured_products'): ?>
                <!-- Section Produits -->
                <section class="section section-products" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title"><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle"><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (empty($section['products'])): ?>
                        <div class="empty-state" style="text-align: center; padding: 40px;">
                            <p style="color: #888;">Produits bientôt disponibles</p>
                        </div>
                        <?php else: ?>
                        <div class="products-grid">
                            <?php foreach ($section['products'] as $product): ?>
                            <?php include __DIR__ . '/../app/templates/product-card.php'; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($section['cta_text'])): ?>
                        <div class="section-cta">
                            <a href="<?= h($section['cta_url'] ?: '/produits') ?>" class="btn btn-primary"><?= h($section['cta_text']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'featured_packs'): ?>
                <!-- Section Packs -->
                <section class="section section-packs" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title"><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle"><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (empty($section['packs'])): ?>
                        <div class="empty-state" style="text-align: center; padding: 40px;">
                            <p style="color: #888;">Packs bientôt disponibles</p>
                        </div>
                        <?php else: ?>
                        <div class="packs-grid">
                            <?php foreach ($section['packs'] as $pack): ?>
                            <?php include __DIR__ . '/../app/templates/pack-card.php'; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($section['cta_text'])): ?>
                        <div class="section-cta">
                            <a href="<?= h($section['cta_url'] ?: '/packs') ?>" class="btn btn-primary"><?= h($section['cta_text']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'newsletter'): ?>
                <!-- Section Newsletter -->
                <section class="section section-newsletter" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title"><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle"><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <form class="newsletter-form" action="/newsletter" method="post">
                            <input type="email" name="email" placeholder="Votre adresse email" required>
                            <button type="submit" class="btn btn-primary"><?= h($section['cta_text'] ?: 'S\'inscrire') ?></button>
                        </form>
                    </div>
                </section>

                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../app/templates/footer.php'; ?>

    <script src="/public/assets/js/cart.js"></script>
</body>
</html>
