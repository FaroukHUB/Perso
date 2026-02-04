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
require_once __DIR__ . '/../app/helpers/SchemaOrg.php';
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

// DEBUG: afficher pourquoi on tombe en 404
if (isset($_GET['debug']) && $_GET['debug'] === '2') {
    echo "<pre>DEBUG page.php (niveau 2):\n";
    echo "slug: $slug\n";
    echo "page trouvée: " . ($page ? "OUI" : "NON") . "\n";
    if ($page) {
        echo "page id: {$page['id']}\n";
        echo "page status: {$page['status']}\n";
    }
    echo "GET preview: " . ($_GET['preview'] ?? 'non défini') . "\n";
    echo "isBuilderPreview: " . ($isBuilderPreview ? 'true' : 'false') . "\n";
    echo "Condition 404: " . (!$isBuilderPreview && $page && $page['status'] !== 'published' ? 'OUI (404)' : 'NON (OK)') . "\n";
    echo "</pre>";
    exit;
}

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

        case 'faq':
            // Map config faq_items to section level for template
            $section['faq_items'] = [];
            if (!empty($section['config']['faq_items'])) {
                foreach ($section['config']['faq_items'] as $index => $item) {
                    $section['faq_items'][] = [
                        'id' => $index,
                        'question' => $item['question'] ?? '',
                        'answer' => $item['answer'] ?? '',
                        'status' => 'active'
                    ];
                }
            }
            break;

        case 'testimonials':
            // Map config testimonial_items to section level for template
            $section['testimonials'] = [];
            if (!empty($section['config']['testimonial_items'])) {
                foreach ($section['config']['testimonial_items'] as $index => $item) {
                    $section['testimonials'][] = [
                        'id' => $index,
                        'author_name' => $item['author'] ?? '',
                        'author_title' => $item['role'] ?? '',
                        'content' => $item['content'] ?? '',
                        'rating' => $item['rating'] ?? 5,
                        'author_photo' => $item['photo'] ?? '',
                        'status' => 'active'
                    ];
                }
            }
            break;

        case 'image_gallery':
            // Map config gallery_items to section level for template
            $section['gallery_images'] = [];
            if (!empty($section['config']['gallery_items'])) {
                foreach ($section['config']['gallery_items'] as $index => $item) {
                    $section['gallery_images'][] = [
                        'id' => $index,
                        'image_url' => $item['url'] ?? '',
                        'thumbnail_url' => $item['url'] ?? '',
                        'caption' => $item['caption'] ?? '',
                        'alt_text' => $item['caption'] ?? '',
                        'link_url' => '',
                        'status' => 'active'
                    ];
                }
            }
            break;

        case 'counter':
            // Map config counter_items to section level for template
            $section['counters'] = [];
            if (!empty($section['config']['counter_items'])) {
                foreach ($section['config']['counter_items'] as $index => $item) {
                    $section['counters'][] = [
                        'id' => $index,
                        'value' => $item['value'] ?? 0,
                        'suffix' => $item['suffix'] ?? '',
                        'prefix' => '',
                        'label' => $item['label'] ?? '',
                        'icon' => $item['icon'] ?? '',
                        'color' => '',
                        'status' => 'active'
                    ];
                }
            }
            break;

        case 'timeline':
            // Map config timeline_items to section level for template
            $section['timeline_steps'] = [];
            if (!empty($section['config']['timeline_items'])) {
                foreach ($section['config']['timeline_items'] as $index => $item) {
                    $section['timeline_steps'][] = [
                        'id' => $index,
                        'step_number' => $index + 1,
                        'title' => $item['title'] ?? '',
                        'description' => $item['description'] ?? '',
                        'date' => $item['date'] ?? '',
                        'icon' => $item['icon'] ?? '',
                        'image_url' => '',
                        'status' => 'active'
                    ];
                }
            }
            break;

        case 'logos':
            // Map config logo_items to section level for template
            $section['logos'] = [];
            if (!empty($section['config']['logo_items'])) {
                foreach ($section['config']['logo_items'] as $index => $item) {
                    $section['logos'][] = [
                        'id' => $index,
                        'name' => $item['name'] ?? '',
                        'logo_url' => $item['url'] ?? '',
                        'website_url' => $item['link'] ?? '',
                        'status' => 'active'
                    ];
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

/**
 * Génère les styles inline pour le titre d'une section
 */
function getTitleStyles(array $section): string {
    $styles = [];
    $typo = $section['config']['typography'] ?? [];

    // Police
    if (!empty($typo['font_family'])) {
        $styles[] = "font-family: '" . htmlspecialchars($typo['font_family']) . "', sans-serif";
    }

    // Taille
    $sizeMap = [
        'small' => '1.5rem',
        'medium' => '2rem',
        'large' => '2.5rem',
        'xlarge' => '3.5rem',
        'xxlarge' => '4.5rem'
    ];
    if (!empty($typo['title_size']) && isset($sizeMap[$typo['title_size']])) {
        $styles[] = 'font-size: ' . $sizeMap[$typo['title_size']];
    }

    // Couleur (supporte les dégradés)
    if (!empty($typo['title_color'])) {
        $color = $typo['title_color'];
        if (strpos($color, 'gradient') !== false) {
            $styles[] = 'background: ' . htmlspecialchars($color);
            $styles[] = '-webkit-background-clip: text';
            $styles[] = '-webkit-text-fill-color: transparent';
            $styles[] = 'background-clip: text';
        } else {
            $styles[] = 'color: ' . htmlspecialchars($color);
        }
    }

    // Gras
    if (!empty($typo['bold']) && ($typo['bold'] === '1' || $typo['bold'] === 1)) {
        $styles[] = 'font-weight: 700';
    }

    // Italique
    if (!empty($typo['italic']) && ($typo['italic'] === '1' || $typo['italic'] === 1)) {
        $styles[] = 'font-style: italic';
    }

    // Souligné
    if (!empty($typo['underline']) && ($typo['underline'] === '1' || $typo['underline'] === 1)) {
        $styles[] = 'text-decoration: underline';
    }

    // Majuscules
    if (!empty($typo['uppercase']) && ($typo['uppercase'] === '1' || $typo['uppercase'] === 1)) {
        $styles[] = 'text-transform: uppercase';
    }

    // Alignement
    if (!empty($typo['align'])) {
        $styles[] = 'text-align: ' . htmlspecialchars($typo['align']);
    }

    // Décalages
    $transforms = [];
    if (!empty($typo['offset_x']) && $typo['offset_x'] != 0) {
        $transforms[] = 'translateX(' . (int)$typo['offset_x'] . 'px)';
    }
    if (!empty($typo['offset_y']) && $typo['offset_y'] != 0) {
        $transforms[] = 'translateY(' . (int)$typo['offset_y'] . 'px)';
    }
    if (!empty($transforms)) {
        $styles[] = 'transform: ' . implode(' ', $transforms);
    }

    return empty($styles) ? '' : implode('; ', $styles);
}

/**
 * Génère les styles inline pour le sous-titre d'une section
 */
function getSubtitleStyles(array $section): string {
    $styles = [];
    $typo = $section['config']['typography'] ?? [];

    // Police du sous-titre
    if (!empty($typo['subtitle_font_family'])) {
        $styles[] = "font-family: '" . htmlspecialchars($typo['subtitle_font_family']) . "', sans-serif";
    }

    // Couleur (supporte les dégradés)
    if (!empty($typo['subtitle_color'])) {
        $color = $typo['subtitle_color'];
        if (strpos($color, 'gradient') !== false) {
            $styles[] = 'background: ' . htmlspecialchars($color);
            $styles[] = '-webkit-background-clip: text';
            $styles[] = '-webkit-text-fill-color: transparent';
            $styles[] = 'background-clip: text';
        } else {
            $styles[] = 'color: ' . htmlspecialchars($color);
        }
    }

    // Gras
    if (!empty($typo['subtitle_bold']) && ($typo['subtitle_bold'] === '1' || $typo['subtitle_bold'] === 1)) {
        $styles[] = 'font-weight: 700';
    }

    // Italique
    if (!empty($typo['subtitle_italic']) && ($typo['subtitle_italic'] === '1' || $typo['subtitle_italic'] === 1)) {
        $styles[] = 'font-style: italic';
    }

    // Souligné
    if (!empty($typo['subtitle_underline']) && ($typo['subtitle_underline'] === '1' || $typo['subtitle_underline'] === 1)) {
        $styles[] = 'text-decoration: underline';
    }

    // Alignement (hérité du titre)
    if (!empty($typo['align'])) {
        $styles[] = 'text-align: ' . htmlspecialchars($typo['align']);
    }

    return empty($styles) ? '' : implode('; ', $styles);
}

// Meta tags
$metaTitle = $page['meta_title'] ?: $page['title'] . ' - PERSONNALY';
$metaDescription = $page['meta_description'] ?: '';

// Schema.org configuration
$siteUrl = 'https://personnaly.fr'; // Could be loaded from settings
SchemaOrg::configure('PERSONNALY', $siteUrl);

// Collecter les FAQ pour Schema.org
$faqItems = [];
foreach ($sections as $s) {
    if ($s['type'] === 'faq' && !empty($s['faq_items'])) {
        foreach ($s['faq_items'] as $faq) {
            if ($faq['status'] === 'active') {
                $faqItems[] = $faq;
            }
        }
    }
}
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
    <link rel="canonical" href="<?= h($siteUrl . '/' . $page['slug']) ?>">
    <?= FontLoader::renderHead() ?>

    <!-- Schema.org Structured Data -->
    <?= SchemaOrg::webPage([
        'title' => $page['title'],
        'slug' => $page['slug'],
        'description' => $metaDescription,
        'datePublished' => $page['created_at'],
        'dateModified' => $page['updated_at'] ?? $page['created_at']
    ]) ?>
    <?php if (!empty($faqItems)): ?>
    <?= SchemaOrg::faqPage($faqItems) ?>
    <?php endif; ?>
    <style><?= $brandingService->getCSSVariables() ?></style>
    <link rel="stylesheet" href="/public/assets/css/style.css">
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
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
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
                        <h1 class="hero-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h1>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="hero-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['cta_text'])): ?>
                        <div class="hero-cta">
                            <a href="<?= h($section['cta_url'] ?: '#') ?>" class="btn btn-primary btn-lg"><?= h($section['cta_text']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'content_block' || $section['type'] === 'text_only'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
                <!-- Section Contenu -->
                <section class="section section-content" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
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
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
                <!-- Section Produits -->
                <section class="section section-products" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
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
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
                <!-- Section Packs -->
                <section class="section section-packs" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
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
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
                <!-- Section Newsletter -->
                <section class="section section-newsletter" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <form class="newsletter-form" action="/newsletter" method="post">
                            <input type="email" name="email" placeholder="Votre adresse email" required>
                            <button type="submit" class="btn btn-primary"><?= h($section['cta_text'] ?: 'S\'inscrire') ?></button>
                        </form>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'video'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
                <!-- Section Vidéo -->
                <section class="section section-video" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['media_url'])): ?>
                        <div class="video-wrapper">
                            <?php
                            $videoUrl = $section['media_url'];
                            // Détecter YouTube
                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoUrl, $matches)):
                            ?>
                            <iframe src="https://www.youtube.com/embed/<?= h($matches[1]) ?>" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                            <?php
                            // Détecter Vimeo
                            elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $matches)):
                            ?>
                            <iframe src="https://player.vimeo.com/video/<?= h($matches[1]) ?>" frameborder="0" allowfullscreen></iframe>
                            <?php else: ?>
                            <video controls>
                                <source src="<?= h($videoUrl) ?>" type="video/mp4">
                                Votre navigateur ne supporte pas les vidéos HTML5.
                            </video>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($section['content'])): ?>
                        <div class="video-description">
                            <?= nl2br(h($section['content'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'image_gallery'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                $galleryLayout = $section['config']['layout'] ?? 'grid';
                $columns = $section['config']['columns'] ?? 3;
                ?>
                <!-- Section Galerie d'images -->
                <section class="section section-gallery" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['gallery_images'])): ?>
                        <div class="gallery-grid gallery-cols-<?= (int)$columns ?>" data-layout="<?= h($galleryLayout) ?>">
                            <?php foreach ($section['gallery_images'] as $image): ?>
                            <?php if ($image['status'] === 'active'): ?>
                            <div class="gallery-item">
                                <?php if (!empty($image['link_url'])): ?>
                                <a href="<?= h($image['link_url']) ?>" target="_blank" rel="noopener">
                                <?php else: ?>
                                <a href="<?= h($image['image_url']) ?>" class="gallery-lightbox" data-caption="<?= h($image['caption'] ?? '') ?>">
                                <?php endif; ?>
                                    <img src="<?= h($image['thumbnail_url'] ?: $image['image_url']) ?>" alt="<?= h($image['alt_text'] ?? '') ?>" loading="lazy">
                                    <?php if (!empty($image['caption'])): ?>
                                    <span class="gallery-caption"><?= h($image['caption']) ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="text-align: center; padding: 40px;">
                            <p style="color: #888;">Galerie en cours de préparation</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'faq'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
                <!-- Section FAQ / Accordéon -->
                <section class="section section-faq" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['faq_items'])): ?>
                        <div class="faq-list">
                            <?php foreach ($section['faq_items'] as $index => $faq): ?>
                            <?php if ($faq['status'] === 'active'): ?>
                            <div class="faq-item" data-faq-id="<?= $faq['id'] ?>">
                                <button class="faq-question" aria-expanded="false" aria-controls="faq-answer-<?= $faq['id'] ?>">
                                    <span><?= h($faq['question']) ?></span>
                                    <svg class="faq-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </button>
                                <div class="faq-answer" id="faq-answer-<?= $faq['id'] ?>">
                                    <div class="faq-answer-content">
                                        <?= nl2br(h($faq['answer'])) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="text-align: center; padding: 40px;">
                            <p style="color: #888;">FAQ en cours de préparation</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'testimonials'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                $layout = $section['config']['layout'] ?? 'carousel';
                ?>
                <!-- Section Témoignages -->
                <section class="section section-testimonials" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['testimonials'])): ?>
                        <div class="testimonials-<?= h($layout) ?>">
                            <?php foreach ($section['testimonials'] as $testimonial): ?>
                            <?php if ($testimonial['status'] === 'active'): ?>
                            <div class="testimonial-card">
                                <?php if (!empty($testimonial['rating'])): ?>
                                <div class="testimonial-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg class="star <?= $i <= $testimonial['rating'] ? 'filled' : '' ?>" width="16" height="16" viewBox="0 0 24 24" fill="<?= $i <= $testimonial['rating'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                    </svg>
                                    <?php endfor; ?>
                                </div>
                                <?php endif; ?>
                                <blockquote class="testimonial-content">
                                    <?= nl2br(h($testimonial['content'])) ?>
                                </blockquote>
                                <div class="testimonial-author">
                                    <?php if (!empty($testimonial['author_photo'])): ?>
                                    <img class="author-photo" src="<?= h($testimonial['author_photo']) ?>" alt="<?= h($testimonial['author_name']) ?>">
                                    <?php else: ?>
                                    <div class="author-photo-placeholder">
                                        <?= strtoupper(substr($testimonial['author_name'], 0, 1)) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="author-info">
                                        <strong class="author-name"><?= h($testimonial['author_name']) ?></strong>
                                        <?php if (!empty($testimonial['author_title'])): ?>
                                        <span class="author-title"><?= h($testimonial['author_title']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="text-align: center; padding: 40px;">
                            <p style="color: #888;">Témoignages bientôt disponibles</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'contact_form'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                $showPhone = ($section['config']['show_phone'] ?? true) !== false;
                $showSubject = ($section['config']['show_subject'] ?? true) !== false;
                ?>
                <!-- Section Formulaire de contact -->
                <section class="section section-contact-form" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <form class="contact-form" action="/api/contact/submit.php" method="post" data-section-id="<?= $section['id'] ?>">
                            <input type="hidden" name="section_id" value="<?= $section['id'] ?>">
                            <input type="hidden" name="page_slug" value="<?= h($page['slug']) ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contact-name-<?= $section['id'] ?>">Nom *</label>
                                    <input type="text" id="contact-name-<?= $section['id'] ?>" name="name" required placeholder="Votre nom">
                                </div>
                                <div class="form-group">
                                    <label for="contact-email-<?= $section['id'] ?>">Email *</label>
                                    <input type="email" id="contact-email-<?= $section['id'] ?>" name="email" required placeholder="votre@email.com">
                                </div>
                            </div>
                            <?php if ($showPhone || $showSubject): ?>
                            <div class="form-row">
                                <?php if ($showPhone): ?>
                                <div class="form-group">
                                    <label for="contact-phone-<?= $section['id'] ?>">Téléphone</label>
                                    <input type="tel" id="contact-phone-<?= $section['id'] ?>" name="phone" placeholder="06 12 34 56 78">
                                </div>
                                <?php endif; ?>
                                <?php if ($showSubject): ?>
                                <div class="form-group">
                                    <label for="contact-subject-<?= $section['id'] ?>">Sujet</label>
                                    <input type="text" id="contact-subject-<?= $section['id'] ?>" name="subject" placeholder="Objet de votre message">
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="contact-message-<?= $section['id'] ?>">Message *</label>
                                <textarea id="contact-message-<?= $section['id'] ?>" name="message" rows="5" required placeholder="Votre message..."></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><?= h($section['cta_text'] ?: 'Envoyer') ?></button>
                            </div>
                            <div class="form-message" style="display: none;"></div>
                        </form>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'blog_slider'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
                <!-- Section Blog Slider -->
                <section class="section section-blog" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (empty($section['posts'])): ?>
                        <div class="empty-state" style="text-align: center; padding: 40px;">
                            <p style="color: #888;">Articles bientôt disponibles</p>
                        </div>
                        <?php else: ?>
                        <div class="blog-grid">
                            <?php foreach ($section['posts'] as $post): ?>
                            <article class="blog-card">
                                <?php if (!empty($post['cover_image_url'])): ?>
                                <a href="/article/<?= h($post['slug']) ?>" class="blog-card-image">
                                    <img src="<?= h($post['cover_image_url']) ?>" alt="<?= h($post['title']) ?>" loading="lazy">
                                </a>
                                <?php endif; ?>
                                <div class="blog-card-content">
                                    <time class="blog-card-date"><?= formatDate($post['published_at'] ?? $post['created_at'], 'd/m/Y') ?></time>
                                    <h3 class="blog-card-title">
                                        <a href="/article/<?= h($post['slug']) ?>"><?= h($post['title']) ?></a>
                                    </h3>
                                    <?php if (!empty($post['excerpt'])): ?>
                                    <p class="blog-card-excerpt"><?= h($post['excerpt']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($section['cta_text'])): ?>
                        <div class="section-cta">
                            <a href="<?= h($section['cta_url'] ?: '/blog') ?>" class="btn btn-primary"><?= h($section['cta_text']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'featured_category'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
                <!-- Section Catégorie à la une -->
                <section class="section section-category" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php elseif (!empty($section['category'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['category']['name']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (empty($section['category_products'])): ?>
                        <div class="empty-state" style="text-align: center; padding: 40px;">
                            <p style="color: #888;">Produits bientôt disponibles</p>
                        </div>
                        <?php else: ?>
                        <div class="products-grid">
                            <?php foreach ($section['category_products'] as $product): ?>
                            <?php include __DIR__ . '/../app/templates/product-card.php'; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($section['cta_text']) && !empty($section['category'])): ?>
                        <div class="section-cta">
                            <a href="<?= h($section['cta_url'] ?: '/categorie/' . $section['category']['slug']) ?>" class="btn btn-primary"><?= h($section['cta_text']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'counter'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                ?>
                <!-- Section Compteurs animés -->
                <section class="section section-counter" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['counters'])): ?>
                        <div class="counters-grid">
                            <?php foreach ($section['counters'] as $counter): ?>
                            <?php if ($counter['status'] === 'active'): ?>
                            <div class="counter-item" data-value="<?= (int)$counter['value'] ?>" <?= !empty($counter['color']) ? 'style="--counter-color: ' . h($counter['color']) . '"' : '' ?>>
                                <?php if (!empty($counter['icon'])): ?>
                                <div class="counter-icon"><?= $counter['icon'] ?></div>
                                <?php endif; ?>
                                <div class="counter-value">
                                    <span class="counter-prefix"><?= h($counter['prefix'] ?? '') ?></span>
                                    <span class="counter-number" data-target="<?= (int)$counter['value'] ?>">0</span>
                                    <span class="counter-suffix"><?= h($counter['suffix'] ?? '') ?></span>
                                </div>
                                <div class="counter-label"><?= h($counter['label']) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'timeline'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                $layout = $section['config']['layout'] ?? 'vertical';
                ?>
                <!-- Section Timeline/Étapes -->
                <section class="section section-timeline" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['timeline_steps'])): ?>
                        <div class="timeline timeline-<?= h($layout) ?>">
                            <?php foreach ($section['timeline_steps'] as $index => $step): ?>
                            <?php if ($step['status'] === 'active'): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker">
                                    <?php if (!empty($step['step_number'])): ?>
                                    <span class="timeline-number"><?= (int)$step['step_number'] ?></span>
                                    <?php elseif (!empty($step['icon'])): ?>
                                    <span class="timeline-icon"><?= $step['icon'] ?></span>
                                    <?php else: ?>
                                    <span class="timeline-number"><?= $index + 1 ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-content">
                                    <?php if (!empty($step['image_url'])): ?>
                                    <img class="timeline-image" src="<?= h($step['image_url']) ?>" alt="<?= h($step['title']) ?>" loading="lazy">
                                    <?php endif; ?>
                                    <?php if (!empty($step['date'])): ?>
                                    <span class="timeline-date"><?= h($step['date']) ?></span>
                                    <?php endif; ?>
                                    <h3 class="timeline-title"><?= h($step['title']) ?></h3>
                                    <?php if (!empty($step['description'])): ?>
                                    <p class="timeline-description"><?= nl2br(h($step['description'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'logos'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                $layout = $section['config']['layout'] ?? 'carousel';
                ?>
                <!-- Section Logos partenaires -->
                <section class="section section-logos" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['logos'])): ?>
                        <div class="logos-<?= h($layout) ?>">
                            <?php foreach ($section['logos'] as $logo): ?>
                            <?php if ($logo['status'] === 'active'): ?>
                            <div class="logo-item">
                                <?php if (!empty($logo['website_url'])): ?>
                                <a href="<?= h($logo['website_url']) ?>" target="_blank" rel="noopener" title="<?= h($logo['name']) ?>">
                                    <img src="<?= h($logo['logo_url']) ?>" alt="<?= h($logo['name']) ?>" loading="lazy">
                                </a>
                                <?php else: ?>
                                <img src="<?= h($logo['logo_url']) ?>" alt="<?= h($logo['name']) ?>" title="<?= h($logo['name']) ?>" loading="lazy">
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'google_map'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                $mapHeight = $section['config']['height'] ?? '400px';
                $mapZoom = $section['config']['zoom'] ?? 15;
                $mapLat = $section['config']['latitude'] ?? '';
                $mapLng = $section['config']['longitude'] ?? '';
                $mapStyle = $section['config']['style'] ?? 'roadmap';
                ?>
                <!-- Section Google Map -->
                <section class="section section-map" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['content'])): ?>
                        <div class="map-info">
                            <?= nl2br(h($section['content'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($mapLat && $mapLng): ?>
                    <div class="map-container" style="height: <?= h($mapHeight) ?>">
                        <div id="map-<?= $section['id'] ?>"
                             class="google-map"
                             data-lat="<?= h($mapLat) ?>"
                             data-lng="<?= h($mapLng) ?>"
                             data-zoom="<?= (int)$mapZoom ?>"
                             data-style="<?= h($mapStyle) ?>">
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="map-placeholder" style="height: <?= h($mapHeight) ?>; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                        <p style="color: #888;">Configurez les coordonnées GPS dans l'éditeur</p>
                    </div>
                    <?php endif; ?>
                </section>

                <?php elseif ($section['type'] === 'google_reviews'): ?>
                <?php
                $titleStyles = getTitleStyles($section);
                $subtitleStyles = getSubtitleStyles($section);
                $layout = $section['config']['layout'] ?? 'carousel';
                ?>
                <!-- Section Avis Google -->
                <section class="section section-google-reviews" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <div class="container">
                        <?php if (!empty($section['title'])): ?>
                        <h2 class="section-title" <?= $titleStyles ? 'style="' . $titleStyles . '"' : '' ?>><?= h($section['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle" <?= $subtitleStyles ? 'style="' . $subtitleStyles . '"' : '' ?>><?= h($section['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($section['google_stats']) && $section['google_stats']['total'] > 0): ?>
                        <div class="google-rating-summary">
                            <div class="rating-score">
                                <span class="score-value"><?= number_format($section['google_stats']['average_rating'], 1) ?></span>
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg class="star <?= $i <= round($section['google_stats']['average_rating']) ? 'filled' : '' ?>" width="20" height="20" viewBox="0 0 24 24" fill="<?= $i <= round($section['google_stats']['average_rating']) ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                    </svg>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-count"><?= (int)$section['google_stats']['total'] ?> avis Google</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($section['google_reviews'])): ?>
                        <div class="google-reviews-<?= h($layout) ?>">
                            <?php foreach ($section['google_reviews'] as $review): ?>
                            <div class="google-review-card">
                                <div class="review-header">
                                    <?php if (!empty($review['author_photo_url'])): ?>
                                    <img class="reviewer-photo" src="<?= h($review['author_photo_url']) ?>" alt="<?= h($review['author_name']) ?>">
                                    <?php else: ?>
                                    <div class="reviewer-photo-placeholder"><?= strtoupper(substr($review['author_name'], 0, 1)) ?></div>
                                    <?php endif; ?>
                                    <div class="reviewer-info">
                                        <strong class="reviewer-name"><?= h($review['author_name']) ?></strong>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <svg class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>" width="14" height="14" viewBox="0 0 24 24" fill="<?= $i <= $review['rating'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                            </svg>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <img class="google-logo" src="/public/assets/img/google-logo.svg" alt="Google" width="20">
                                </div>
                                <?php if (!empty($review['text'])): ?>
                                <p class="review-text"><?= h($review['text']) ?></p>
                                <?php endif; ?>
                                <time class="review-date"><?= formatDate($review['time'], 'd/m/Y') ?></time>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="text-align: center; padding: 40px;">
                            <p style="color: #888;">Avis Google bientôt disponibles</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php elseif ($section['type'] === 'separator'): ?>
                <!-- Section Séparateur -->
                <div class="section-separator" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <?php
                    $sepStyle = $section['config']['style'] ?? 'line';
                    $sepColor = $section['config']['color'] ?? '#e0e0e0';
                    $sepWidth = $section['config']['width'] ?? '100%';
                    $sepHeight = $section['config']['height'] ?? '1px';
                    ?>
                    <?php if ($sepStyle === 'line'): ?>
                    <hr style="border: none; height: <?= h($sepHeight) ?>; background: <?= h($sepColor) ?>; width: <?= h($sepWidth) ?>; margin: 2rem auto;">
                    <?php elseif ($sepStyle === 'dots'): ?>
                    <div style="text-align: center; padding: 2rem 0;">
                        <span style="color: <?= h($sepColor) ?>; font-size: 1.5rem; letter-spacing: 1rem;">• • •</span>
                    </div>
                    <?php elseif ($sepStyle === 'wave'): ?>
                    <svg viewBox="0 0 1200 60" preserveAspectRatio="none" style="width: 100%; height: 60px; fill: <?= h($sepColor) ?>;">
                        <path d="M0,30 C300,60 400,0 600,30 C800,60 900,0 1200,30 L1200,60 L0,60 Z"></path>
                    </svg>
                    <?php elseif ($sepStyle === 'space'): ?>
                    <div style="height: <?= h($sepHeight) ?>;"></div>
                    <?php endif; ?>
                </div>

                <?php elseif ($section['type'] === 'html_custom'): ?>
                <!-- Section HTML personnalisé -->
                <section class="section section-custom-html" style="<?= $sectionStyles ?>" data-section-id="<?= $section['id'] ?>">
                    <?php if (!empty($section['content'])): ?>
                    <?= $section['content'] ?>
                    <?php endif; ?>
                </section>

                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../app/templates/footer.php'; ?>

    <script>
    // FAQ Accordéon
    document.querySelectorAll('.faq-question').forEach(button => {
        button.addEventListener('click', () => {
            const item = button.closest('.faq-item');
            const isOpen = item.classList.contains('open');

            // Fermer tous les autres
            document.querySelectorAll('.faq-item.open').forEach(openItem => {
                if (openItem !== item) {
                    openItem.classList.remove('open');
                    openItem.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
                }
            });

            // Toggle current
            item.classList.toggle('open');
            button.setAttribute('aria-expanded', !isOpen);
        });
    });

    // Formulaire de contact AJAX
    document.querySelectorAll('.contact-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            const messageDiv = form.querySelector('.form-message');
            const originalText = submitBtn.textContent;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi en cours...';
            messageDiv.style.display = 'none';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form)
                });

                const data = await response.json();

                messageDiv.style.display = 'block';
                if (data.success) {
                    messageDiv.className = 'form-message success';
                    messageDiv.textContent = data.message || 'Message envoyé avec succès !';
                    form.reset();
                } else {
                    messageDiv.className = 'form-message error';
                    messageDiv.textContent = data.error || data.errors?.join(', ') || 'Une erreur est survenue';
                }
            } catch (error) {
                messageDiv.style.display = 'block';
                messageDiv.className = 'form-message error';
                messageDiv.textContent = 'Erreur de connexion. Veuillez réessayer.';
            }

            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });

    // Lightbox pour galerie
    document.querySelectorAll('.gallery-lightbox').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const overlay = document.createElement('div');
            overlay.className = 'lightbox-overlay';
            overlay.innerHTML = `
                <div class="lightbox-content">
                    <img src="${link.href}" alt="">
                    ${link.dataset.caption ? `<p class="lightbox-caption">${link.dataset.caption}</p>` : ''}
                    <button class="lightbox-close">&times;</button>
                </div>
            `;
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay || e.target.classList.contains('lightbox-close')) {
                    overlay.remove();
                }
            });
            document.body.appendChild(overlay);
        });
    });

    // Compteurs animés avec Intersection Observer
    const animateCounters = () => {
        const counters = document.querySelectorAll('.counter-number');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
                    entry.target.classList.add('animated');
                    const target = parseInt(entry.target.dataset.target);
                    const duration = 2000;
                    const startTime = performance.now();

                    const updateCounter = (currentTime) => {
                        const elapsed = currentTime - startTime;
                        const progress = Math.min(elapsed / duration, 1);

                        // Easing function pour un effet plus naturel
                        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                        const current = Math.floor(easeOutQuart * target);

                        entry.target.textContent = current.toLocaleString('fr-FR');

                        if (progress < 1) {
                            requestAnimationFrame(updateCounter);
                        } else {
                            entry.target.textContent = target.toLocaleString('fr-FR');
                        }
                    };

                    requestAnimationFrame(updateCounter);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => observer.observe(counter));
    };

    // Initialiser les compteurs
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', animateCounters);
    } else {
        animateCounters();
    }

    // Animation des timeline items au scroll
    const animateTimeline = () => {
        const items = document.querySelectorAll('.timeline-item');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.2 });

        items.forEach(item => observer.observe(item));
    };

    animateTimeline();

    // Logos carousel auto-scroll (si layout carousel)
    const logoCarousels = document.querySelectorAll('.logos-carousel');
    logoCarousels.forEach(carousel => {
        let scrollAmount = 0;
        const scrollSpeed = 1;
        const scrollInterval = setInterval(() => {
            scrollAmount += scrollSpeed;
            if (scrollAmount >= carousel.scrollWidth - carousel.clientWidth) {
                scrollAmount = 0;
            }
            carousel.scrollLeft = scrollAmount;
        }, 30);

        // Pause on hover
        carousel.addEventListener('mouseenter', () => clearInterval(scrollInterval));
    });
    </script>
    <style>
    .lightbox-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 2rem;
    }
    .lightbox-content {
        position: relative;
        max-width: 90vw;
        max-height: 90vh;
    }
    .lightbox-content img {
        max-width: 100%;
        max-height: 85vh;
        object-fit: contain;
    }
    .lightbox-caption {
        color: white;
        text-align: center;
        margin-top: 1rem;
    }
    .lightbox-close {
        position: absolute;
        top: -40px;
        right: 0;
        background: none;
        border: none;
        color: white;
        font-size: 2rem;
        cursor: pointer;
    }
    </style>
</body>
</html>
