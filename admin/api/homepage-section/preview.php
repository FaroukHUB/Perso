<?php
/**
 * API de prévisualisation en direct pour les sections Homepage
 * Renvoie le HTML de la section sans sauvegarder en base
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../app/helpers/functions.php';
require_once __DIR__ . '/../../../app/core/Database.php';
require_once __DIR__ . '/../../../app/core/Auth.php';
require_once __DIR__ . '/../../../app/models/Product.php';
require_once __DIR__ . '/../../../app/models/Pack.php';
require_once __DIR__ . '/../../../app/models/BlogPost.php';
require_once __DIR__ . '/../../../app/models/Category.php';
require_once __DIR__ . '/../../../app/services/BrandingService.php';

// Vérifier l'authentification admin
if (!Auth::isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

// Récupérer les données POST
$type = $_POST['type'] ?? 'hero';
$title = trim($_POST['title'] ?? '');
$subtitle = trim($_POST['subtitle'] ?? '');
$content = trim($_POST['content'] ?? '');
$ctaText = trim($_POST['cta_text'] ?? '');
$ctaUrl = trim($_POST['cta_url'] ?? '');
$mediaType = $_POST['media_type'] ?? 'none';
$mediaUrl = $_POST['media_url'] ?? '';

// Config spécifique
$config = [];

// Hero config
if ($type === 'hero') {
    $config['badge'] = trim($_POST['hero_badge'] ?? '');
    $config['highlight'] = trim($_POST['hero_highlight'] ?? '');
    $config['cta2_text'] = trim($_POST['hero_cta2_text'] ?? '');
    $config['cta2_url'] = trim($_POST['hero_cta2_url'] ?? '');

    // Ordre des éléments
    if (!empty($_POST['hero_elements_order']) && is_array($_POST['hero_elements_order'])) {
        $validElements = ['badge', 'title', 'subtitle', 'buttons'];
        $order = array_filter($_POST['hero_elements_order'], function($el) use ($validElements) {
            return in_array($el, $validElements);
        });
        $config['elements_order'] = array_values($order);
    }
}

// Style visuel
$config['style'] = [
    'background_color' => !empty($_POST['style_bg_color']) ? $_POST['style_bg_color'] : null,
    'text_color' => !empty($_POST['style_text_color']) ? $_POST['style_text_color'] : null,
    'padding_y' => $_POST['style_padding_y'] ?? 'medium',
];

// Catégorie sélectionnée (pour featured_category)
if ($type === 'featured_category' && !empty($_POST['category_id'])) {
    $config['category_id'] = (int) $_POST['category_id'];
    $config['products_limit'] = (int) ($_POST['products_limit'] ?? 8);
}

// Media additionnels
if ($type === 'content_block' && !empty($_POST['existing_media_urls'])) {
    $additionalMedia = [];
    foreach ($_POST['existing_media_urls'] as $url) {
        if (!empty($url)) {
            $additionalMedia[] = ['type' => 'image', 'url' => $url];
        }
    }
    $config['additional_media'] = $additionalMedia;
}

// Construire la section virtuelle
$section = [
    'id' => 0,
    'type' => $type,
    'title' => $title,
    'subtitle' => $subtitle,
    'content' => $content,
    'cta_text' => $ctaText,
    'cta_url' => $ctaUrl,
    'media_type' => $mediaType,
    'media_url' => $mediaUrl,
    'config' => $config,
    'items' => []
];

// Charger les données liées selon le type
$productModel = new Product();
$packModel = new Pack();
$blogModel = new BlogPost();
$categoryModel = new Category();

switch ($type) {
    case 'featured_products':
        $section['products'] = [];
        if (!empty($_POST['product_ids']) && is_array($_POST['product_ids'])) {
            foreach ($_POST['product_ids'] as $pid) {
                $product = $productModel->findById((int)$pid);
                if ($product && $product['active']) {
                    $product['category_names'] = $categoryModel->getCategoryNamesByProduct($product['id']);
                    $section['products'][] = $product;
                }
            }
        }
        break;

    case 'featured_packs':
        $section['packs'] = [];
        if (!empty($_POST['pack_ids']) && is_array($_POST['pack_ids'])) {
            foreach ($_POST['pack_ids'] as $pid) {
                $pack = $packModel->findById((int)$pid);
                if ($pack && $pack['status'] === 'active') {
                    $pack['first_product'] = $packModel->getFirstProduct($pack['id']);
                    $section['packs'][] = $pack;
                }
            }
        }
        break;

    case 'blog_slider':
        $section['posts'] = [];
        if (!empty($_POST['blog_ids']) && is_array($_POST['blog_ids'])) {
            foreach ($_POST['blog_ids'] as $bid) {
                $post = $blogModel->findById((int)$bid);
                if ($post && $post['status'] === 'published') {
                    $section['posts'][] = $post;
                }
            }
        }
        if (empty($section['posts'])) {
            $section['posts'] = $blogModel->findPublished(6);
        }
        break;

    case 'featured_category':
        $section['category'] = null;
        $section['category_products'] = [];
        if (!empty($config['category_id'])) {
            $section['category'] = $categoryModel->findById($config['category_id']);
            if ($section['category'] && $section['category']['status'] === 'active') {
                $limit = $config['products_limit'] ?? 8;
                $section['category_products'] = $categoryModel->getProducts($config['category_id'], $limit);
            }
        }
        break;
}

// Fonction pour générer les styles inline
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

// Générer le HTML de la section
ob_start();

switch ($type):

    // ===== HERO =====
    case 'hero':
        $hasHeroImage = $mediaType === 'image' && !empty($mediaUrl);
        $sectionStyles = getSectionInlineStyles($section, $hasHeroImage);
        $heroStyleParts = [];

        if ($hasHeroImage) {
            $imgUrl = $mediaUrl;
            if (strpos($imgUrl, '/public') !== 0 && strpos($imgUrl, 'http') !== 0) {
                $imgUrl = '/public' . $imgUrl;
            }
            $heroStyleParts[] = 'background-image: url(\'' . h($imgUrl) . '\')';
        }

        if ($sectionStyles) {
            $heroStyleParts[] = $sectionStyles;
        }
        $heroStyle = !empty($heroStyleParts) ? 'style="' . implode('; ', $heroStyleParts) . '"' : '';

        $heroBadge = $config['badge'] ?? '';
        $heroHighlight = $config['highlight'] ?? '';
        $heroCta2Text = $config['cta2_text'] ?? '';
        $heroCta2Url = $config['cta2_url'] ?? '';
?>
<section class="hero <?= $hasHeroImage ? 'hero-with-bg' : '' ?>" <?= $heroStyle ?>>
    <div class="container">
        <div class="hero-content">
            <?php
            $elementsOrder = $config['elements_order'] ?? ['badge', 'title', 'subtitle', 'buttons'];

            foreach ($elementsOrder as $element):
                switch ($element):
                    case 'badge':
                        if ($heroBadge): ?>
            <div class="hero-badge"><?= h($heroBadge) ?></div>
            <?php   endif;
                        break;

                    case 'title':
                        if ($title): ?>
            <h1><?= h($title) ?><?php if ($heroHighlight): ?> <span><?= h($heroHighlight) ?></span><?php endif; ?></h1>
            <?php   endif;
                        break;

                    case 'subtitle':
                        if ($subtitle): ?>
            <p><?= h($subtitle) ?></p>
            <?php   endif;
                        break;

                    case 'buttons':
                        if ($ctaText || $heroCta2Text): ?>
            <div class="hero-buttons">
                <?php if ($ctaUrl && $ctaText): ?>
                    <a href="<?= h($ctaUrl) ?>" class="btn btn-primary">
                        <?= h($ctaText) ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                <?php endif; ?>
                <?php if ($heroCta2Text && $heroCta2Url): ?>
                    <a href="<?= h($heroCta2Url) ?>" class="btn btn-dark"><?= h($heroCta2Text) ?></a>
                <?php endif; ?>
            </div>
            <?php   endif;
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
<section class="products-section" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
    <div class="container">
        <div class="section-header">
            <h2><?= h($title ?: 'Nos Produits') ?></h2>
            <?php if ($subtitle): ?>
                <p><?= h($subtitle) ?></p>
            <?php endif; ?>
        </div>

        <?php if (empty($section['products'])): ?>
            <div class="empty-products">
                <div class="empty-products-icon">👕</div>
                <h3>Sélectionnez des produits</h3>
                <p class="text-muted">Aucun produit sélectionné pour cette section.</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($section['products'] as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if (!empty($product['category_names'])): ?>
                                <span class="product-category badge badge-mint"><?= h($product['category_names'][0]) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($product['image_front_url'])): ?>
                                <img src="/public<?= h($product['image_front_url']) ?>" alt="<?= h($product['name']) ?>">
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3><?= h($product['name']) ?></h3>
                            <p><?= h($product['description'] ?? 'Personnalisable') ?></p>
                            <div class="product-footer">
                                <span class="product-price"><?= formatPrice($product['base_price']) ?></span>
                                <span class="product-btn">Personnaliser</span>
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
        if (empty($section['category'])): ?>
<section class="products-section">
    <div class="container">
        <div class="empty-products">
            <div class="empty-products-icon">📁</div>
            <h3>Sélectionnez une catégorie</h3>
            <p class="text-muted">Aucune catégorie sélectionnée.</p>
        </div>
    </div>
</section>
<?php   else:
            $cat = $section['category'];
            $sectionStyles = getSectionInlineStyles($section);
?>
<section class="products-section" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
    <div class="container">
        <div class="section-header">
            <h2><?= h($title ?: $cat['name']) ?></h2>
            <?php if ($subtitle): ?>
                <p><?= h($subtitle) ?></p>
            <?php elseif (!empty($cat['description'])): ?>
                <p><?= h($cat['description']) ?></p>
            <?php endif; ?>
        </div>

        <?php if (empty($section['category_products'])): ?>
            <div class="empty-products">
                <div class="empty-products-icon">👕</div>
                <h3>Aucun produit</h3>
                <p class="text-muted">Cette catégorie ne contient pas encore de produits.</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($section['category_products'] as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <span class="product-category badge badge-mint"><?= h($cat['name']) ?></span>
                            <?php if (!empty($product['image_front_url'])): ?>
                                <img src="/public<?= h($product['image_front_url']) ?>" alt="<?= h($product['name']) ?>">
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3><?= h($product['name']) ?></h3>
                            <p><?= h($product['description'] ?? 'Personnalisable') ?></p>
                            <div class="product-footer">
                                <span class="product-price"><?= formatPrice($product['base_price']) ?></span>
                                <span class="product-btn">Personnaliser</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($ctaText) && !empty($ctaUrl)): ?>
            <div class="section-cta" style="text-align: center; margin-top: 30px;">
                <a href="<?= h($ctaUrl) ?>" class="btn btn-primary"><?= h($ctaText) ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
        endif;
        break;

    // ===== FEATURED PACKS =====
    case 'featured_packs':
        $typeLabels = [
            'technique' => 'Technique',
            'contextuel' => 'Contextuel',
            'thematique' => 'Thématique',
            'inspiration' => 'Inspiration'
        ];
        $sectionStyles = getSectionInlineStyles($section);
?>
<section class="inspirations-section" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
    <div class="container">
        <div class="section-header">
            <h2><?= h($title ?: 'Nos Idées Tendance') ?></h2>
            <?php if ($subtitle): ?>
                <p><?= h($subtitle) ?></p>
            <?php endif; ?>
        </div>

        <?php if (empty($section['packs'])): ?>
            <div class="empty-products" style="color: white;">
                <div class="empty-products-icon">✨</div>
                <h3>Sélectionnez des packs</h3>
                <p>Aucun pack sélectionné pour cette section.</p>
            </div>
        <?php else: ?>
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
                                <img src="/public<?= h($pack['cover_image_url']) ?>" alt="<?= h($pack['name']) ?>">
                            <?php endif; ?>
                        </div>
                        <div class="inspiration-info">
                            <h3><?= h($pack['name']) ?></h3>
                            <?php if (!empty($pack['description'])): ?>
                                <p><?= h($pack['description']) ?></p>
                            <?php endif; ?>
                            <span class="inspiration-cta">
                                Essayer cette idée
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
        break;

    // ===== CONTENT BLOCK =====
    case 'content_block':
        $additionalMedia = $config['additional_media'] ?? [];
        $hasMultipleMedia = !empty($additionalMedia);
        $hasMainMedia = $mediaType !== 'none' && !empty($mediaUrl);
        $isGalleryMode = $hasMultipleMedia && !$hasMainMedia;
        $sectionStyles = getSectionInlineStyles($section);
?>
<section class="content-block-section" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
    <div class="container">
        <?php if ($isGalleryMode): ?>
            <div class="content-block-inner gallery-mode">
                <div class="content-block-text" style="text-align: center; max-width: 800px; margin: 0 auto;">
                    <?php if ($title): ?><h2><?= h($title) ?></h2><?php endif; ?>
                    <?php if ($content): ?><p><?= nl2br(h($content)) ?></p><?php endif; ?>
                    <?php if ($ctaUrl && $ctaText): ?>
                        <a href="<?= h($ctaUrl) ?>" class="btn btn-primary"><?= h($ctaText) ?></a>
                    <?php endif; ?>
                </div>
                <div class="content-block-gallery">
                    <?php foreach ($additionalMedia as $media): ?>
                        <div class="gallery-card">
                            <img src="/public<?= h($media['url']) ?>" alt="">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="content-block-inner">
                <div class="content-block-text">
                    <?php if ($title): ?><h2><?= h($title) ?></h2><?php endif; ?>
                    <?php if ($content): ?><p><?= nl2br(h($content)) ?></p><?php endif; ?>
                    <?php if ($ctaUrl && $ctaText): ?>
                        <a href="<?= h($ctaUrl) ?>" class="btn btn-primary"><?= h($ctaText) ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($hasMainMedia): ?>
                    <div class="content-block-media">
                        <?php if ($mediaType === 'video'): ?>
                            <video src="/public<?= h($mediaUrl) ?>" autoplay muted loop playsinline></video>
                        <?php else: ?>
                            <img src="/public<?= h($mediaUrl) ?>" alt="<?= h($title) ?>" class="main-media">
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
        $sectionStyles = getSectionInlineStyles($section);
?>
<section class="blog-section" <?= $sectionStyles ? 'style="' . $sectionStyles . '"' : '' ?>>
    <div class="container">
        <div class="section-header">
            <h2><?= h($title ?: 'Notre Blog') ?></h2>
            <?php if ($subtitle): ?>
                <p><?= h($subtitle) ?></p>
            <?php endif; ?>
        </div>

        <?php if (empty($section['posts'])): ?>
            <div class="empty-products">
                <div class="empty-products-icon">📝</div>
                <h3>Sélectionnez des articles</h3>
                <p class="text-muted">Aucun article sélectionné.</p>
            </div>
        <?php else: ?>
            <div class="blog-slider">
                <?php foreach ($section['posts'] as $post): ?>
                    <div class="blog-card">
                        <div class="blog-card-image">
                            <?php if (!empty($post['cover_image_url'])): ?>
                                <img src="/public<?= h($post['cover_image_url']) ?>" alt="<?= h($post['title']) ?>">
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
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
        break;

    // ===== NEWSLETTER =====
    case 'newsletter':
        $sectionStyles = getSectionInlineStyles($section);
        $newsletterStyle = $sectionStyles;
        if ($mediaType === 'image' && !empty($mediaUrl)) {
            $newsletterStyle .= ($newsletterStyle ? '; ' : '') . 'background-image: url(\'/public' . h($mediaUrl) . '\')';
        }
?>
<section class="newsletter-section" style="<?= $newsletterStyle ?>">
    <div class="newsletter-overlay"></div>
    <div class="container">
        <div class="newsletter-content">
            <?php if ($title): ?>
                <h2><?= h($title) ?></h2>
            <?php endif; ?>
            <?php if ($subtitle): ?>
                <p class="newsletter-subtitle"><?= h($subtitle) ?></p>
            <?php endif; ?>

            <div class="newsletter-form">
                <div class="newsletter-input-group">
                    <input type="email" placeholder="Votre adresse email" class="newsletter-input" disabled>
                    <button type="button" class="newsletter-btn" disabled>
                        <?= h($ctaText ?: 'S\'inscrire') ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>

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

$html = ob_get_clean();

// Réponse JSON
echo json_encode([
    'success' => true,
    'html' => $html,
    'type' => $type
]);
