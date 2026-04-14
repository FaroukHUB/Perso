<?php
/**
 * PERSONNALY - Page d'accueil
 * Hero Slider, Trust Badges, Categories, How It Works, Products, Testimonials, Blog, Newsletter
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/HeroSlide.php';
require_once __DIR__ . '/../app/models/TrustBadge.php';
require_once __DIR__ . '/../app/models/HowItWorksStep.php';
require_once __DIR__ . '/../app/models/Testimonial.php';
require_once __DIR__ . '/../app/models/BlogPost.php';

$cartCount = Cart::count();

// Charger les données
$productModel = new Product();
$categoryModel = new Category();
$heroModel = new HeroSlide();
$badgeModel = new TrustBadge();
$stepModel = new HowItWorksStep();
$testimonialModel = new Testimonial();
$blogModel = new BlogPost();

$heroSlides = $heroModel->findAllActive();
$trustBadges = $badgeModel->findAllActive();
$categories = $categoryModel->findAllActive();
$howItWorksSteps = $stepModel->findAllActive();
$featuredProducts = $productModel->findFeatured(8);
$allProducts = $productModel->findActive();
$testimonials = $testimonialModel->findAllActive();
$blogPosts = $blogModel->findPublished(3);

// Ajouter les noms de catégories pour chaque produit
foreach ($featuredProducts as &$product) {
    $product['category_names'] = $categoryModel->getCategoryNamesByProduct($product['id']);
}
unset($product);
foreach ($allProducts as &$product) {
    $product['category_names'] = $categoryModel->getCategoryNamesByProduct($product['id']);
}
unset($product);

// Newsletter
$newsletterSuccess = false;
$newsletterError = '';
if (isPost() && isset($_POST['newsletter_email'])) {
    $email = trim($_POST['newsletter_email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('INSERT IGNORE INTO newsletter_subscribers (email, source, created_at) VALUES (?, ?, NOW())');
            $stmt->execute([$email, 'homepage']);
            $newsletterSuccess = true;
        } catch (Exception $e) {
            $newsletterError = 'Une erreur est survenue.';
        }
    } else {
        $newsletterError = 'Email invalide.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PERSONNALY - Personnalisation Textile pour Toute la Famille</title>
    <meta name="description" content="Créez des vêtements uniques pour hommes, femmes et enfants. Personnalisation textile de qualité.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        /* ===== HERO SLIDER ===== */
        .hero { min-height: 80vh; position: relative; overflow: hidden; padding-top: 70px; }
        .hero-slider { position: relative; height: 80vh; }
        .hero-slide {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            background: var(--gradient-dark); opacity: 0; transition: opacity 0.8s ease;
        }
        .hero-slide.active { opacity: 1; z-index: 1; }
        .hero-slide-bg {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover; background-position: center;
        }
        .hero-slide-bg::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(13,13,13,0.85), rgba(13,13,13,0.6));
        }
        .hero-slide::before {
            content: ''; position: absolute; width: 600px; height: 600px; background: var(--pink-main);
            border-radius: 50%; filter: blur(200px); opacity: 0.15; top: -200px; right: -100px;
        }
        .hero-content { position: relative; z-index: 2; text-align: center; max-width: 700px; padding: 0 20px; }
        .hero h1 { font-family: var(--font-display); font-size: 3rem; font-weight: 800; color: white; margin-bottom: 16px; line-height: 1.15; }
        .hero h1 span { background: var(--gradient-hero); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { font-size: 1.1rem; color: rgba(255,255,255,0.7); margin-bottom: 32px; line-height: 1.6; }
        .hero-cta {
            display: inline-flex; align-items: center; gap: 10px; background: var(--gradient-pink);
            color: white; padding: 14px 32px; border-radius: 50px; text-decoration: none; font-weight: 700;
            transition: all 0.3s;
        }
        .hero-cta:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(255,105,180,0.4); }
        .hero-dots { position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); display: flex; gap: 10px; z-index: 10; }
        .hero-dot { width: 12px; height: 12px; border-radius: 50%; background: rgba(255,255,255,0.3); cursor: pointer; transition: all 0.3s; }
        .hero-dot.active { background: var(--pink-main); transform: scale(1.2); }

        /* ===== TRUST BADGES ===== */
        .trust-section { background: var(--black); padding: 30px 0; }
        .trust-grid { display: flex; justify-content: center; flex-wrap: wrap; gap: 40px; }
        .trust-badge { display: flex; align-items: center; gap: 12px; color: white; }
        .trust-badge-icon { font-size: 28px; }
        .trust-badge-text { font-size: 14px; font-weight: 600; }

        /* ===== SECTIONS COMMUNES ===== */
        .section { padding: 80px 0; }
        .section-light { background: var(--gray-light); }
        .section-dark { background: var(--black); color: white; }
        .section-title { font-family: var(--font-display); font-size: 2rem; font-weight: 700; text-align: center; margin-bottom: 16px; }
        .section-subtitle { text-align: center; color: var(--gray); margin-bottom: 48px; font-size: 1.1rem; }
        .section-dark .section-subtitle { color: rgba(255,255,255,0.6); }

        /* ===== CATEGORIES ===== */
        .categories-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; max-width: 900px; margin: 0 auto; }
        .category-card {
            background: white; border-radius: 16px; padding: 24px; text-align: center;
            text-decoration: none; color: var(--black); box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s; border: 2px solid transparent;
        }
        .category-card:hover { transform: translateY(-4px); border-color: var(--pink-main); box-shadow: 0 8px 24px rgba(255,105,180,0.15); }
        .category-card img { width: 80px; height: 80px; object-fit: cover; border-radius: 12px; margin-bottom: 12px; }
        .category-card-name { font-weight: 600; font-size: 15px; }

        /* ===== HOW IT WORKS ===== */
        .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 32px; max-width: 900px; margin: 0 auto; }
        .step-card { text-align: center; padding: 32px 24px; }
        .step-icon { font-size: 48px; margin-bottom: 16px; }
        .step-image { width: 80px; height: 80px; object-fit: cover; border-radius: 16px; margin: 0 auto 16px; display: block; border: 3px solid var(--pink-main); }
        .step-title { font-weight: 700; font-size: 18px; margin-bottom: 8px; color: var(--pink-dark); }
        .step-desc { color: rgba(255,255,255,0.7); line-height: 1.6; }

        /* ===== PRODUCTS ===== */
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; }
        .product-card {
            background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s; text-decoration: none; color: inherit; display: block;
        }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .product-card-img { width: 100%; aspect-ratio: 1; object-fit: cover; background: #f5f5f5; }
        .product-card-body { padding: 16px; }
        .product-card-name { font-weight: 600; font-size: 16px; margin-bottom: 6px; }
        .product-card-cats { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
        .product-card-cat { font-size: 11px; padding: 2px 10px; border-radius: 50px; background: rgba(61,255,192,0.15); color: var(--mint-dark); font-weight: 500; }
        .product-card-price { font-weight: 700; font-size: 18px; color: var(--pink-dark); }
        .product-card-cta { display: block; text-align: center; padding: 12px; background: var(--gradient-pink); color: white; font-weight: 600; }
        .product-card-featured { position: relative; }
        .product-card-featured::before {
            content: 'Populaire'; position: absolute; top: 12px; left: 12px; background: var(--gradient-pink);
            color: white; font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 50px; z-index: 1;
        }

        /* ===== TESTIMONIALS ===== */
        .testimonials-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
        .testimonial-card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.1); }
        .testimonial-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .testimonial-avatar { width: 48px; height: 48px; border-radius: 50%; background: var(--gradient-pink); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px; }
        .testimonial-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .testimonial-name { font-weight: 600; color: white; }
        .testimonial-rating { color: #FFD700; }
        .testimonial-content { color: rgba(255,255,255,0.8); line-height: 1.6; font-style: italic; }

        /* ===== BLOG ===== */
        .blog-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
        .blog-card { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-decoration: none; color: inherit; }
        .blog-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .blog-card-img { width: 100%; height: 180px; object-fit: cover; background: #f5f5f5; }
        .blog-card-body { padding: 20px; }
        .blog-card-title { font-weight: 600; font-size: 16px; margin-bottom: 8px; color: var(--black); }
        .blog-card-excerpt { color: var(--gray); font-size: 14px; line-height: 1.5; }

        /* ===== NEWSLETTER ===== */
        .newsletter-section { background: var(--gradient-pink); padding: 60px 0; }
        .newsletter-content { text-align: center; max-width: 500px; margin: 0 auto; }
        .newsletter-content h2 { color: white; font-family: var(--font-display); font-size: 1.8rem; margin-bottom: 12px; }
        .newsletter-content p { color: rgba(255,255,255,0.9); margin-bottom: 24px; }
        .newsletter-form { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
        .newsletter-input { flex: 1; min-width: 250px; padding: 14px 20px; border: none; border-radius: 50px; font-size: 15px; }
        .newsletter-btn { background: var(--black); color: white; padding: 14px 28px; border: none; border-radius: 50px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .newsletter-btn:hover { transform: scale(1.05); }
        .newsletter-success { background: rgba(255,255,255,0.2); padding: 16px; border-radius: 12px; color: white; font-weight: 500; }

        /* ===== FOOTER ===== */
        .footer { background: var(--black); color: rgba(255,255,255,0.6); padding: 40px 0; text-align: center; }
        .footer a { color: var(--pink-main); text-decoration: none; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2rem; }
            .hero-slider { height: 70vh; }
            .trust-grid { gap: 20px; }
            .trust-badge-text { font-size: 12px; }
            .section { padding: 50px 0; }
            .section-title { font-size: 1.6rem; }
            .products-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        }
        @media (max-width: 480px) {
            .products-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- HERO SLIDER -->
    <?php if (!empty($heroSlides)): ?>
    <section class="hero">
        <div class="hero-slider">
            <?php foreach ($heroSlides as $index => $slide): ?>
                <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>">
                    <?php if (!empty($slide['image_url'])): ?>
                        <div class="hero-slide-bg" style="background-image: url('/public<?= h($slide['image_url']) ?>');"></div>
                    <?php endif; ?>
                    <div class="hero-content">
                        <h1><?= h($slide['title']) ?></h1>
                        <?php if (!empty($slide['subtitle'])): ?>
                            <p><?= h($slide['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($slide['cta_text'])): ?>
                            <a href="<?= h($slide['cta_url'] ?: '#produits') ?>" class="hero-cta">
                                <?= h($slide['cta_text']) ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($heroSlides) > 1): ?>
            <div class="hero-dots">
                <?php foreach ($heroSlides as $index => $slide): ?>
                    <div class="hero-dot <?= $index === 0 ? 'active' : '' ?>" data-slide="<?= $index ?>"></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- TRUST BADGES -->
    <?php if (!empty($trustBadges)): ?>
    <section class="trust-section">
        <div class="container">
            <div class="trust-grid">
                <?php foreach ($trustBadges as $badge): ?>
                    <div class="trust-badge">
                        <span class="trust-badge-icon"><?= h($badge['icon']) ?></span>
                        <span class="trust-badge-text"><?= h($badge['title']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CATEGORIES -->
    <?php if (!empty($categories)): ?>
    <section class="section section-light">
        <div class="container">
            <h2 class="section-title">Nos catégories</h2>
            <p class="section-subtitle">Trouvez le produit parfait pour chaque occasion</p>
            <div class="categories-grid">
                <?php foreach ($categories as $cat): ?>
                    <a href="/category.php?slug=<?= h($cat['slug']) ?>" class="category-card">
                        <?php if (!empty($cat['image_url'])): ?>
                            <img src="/public<?= h($cat['image_url']) ?>" alt="<?= h($cat['name']) ?>">
                        <?php else: ?>
                            <div style="width:80px;height:80px;background:#f0f0f0;border-radius:12px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:32px;">👕</div>
                        <?php endif; ?>
                        <div class="category-card-name"><?= h($cat['name']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- HOW IT WORKS -->
    <?php if (!empty($howItWorksSteps)): ?>
    <section class="section section-dark">
        <div class="container">
            <h2 class="section-title">Comment ça marche</h2>
            <p class="section-subtitle">Créez votre produit personnalisé en quelques clics</p>
            <div class="steps-grid">
                <?php foreach ($howItWorksSteps as $step): ?>
                    <div class="step-card">
                        <?php if (!empty($step['image_url'])): ?>
                            <img src="/public<?= h($step['image_url']) ?>" alt="<?= h($step['title']) ?>" class="step-image">
                        <?php elseif (!empty($step['icon'])): ?>
                            <div class="step-icon"><?= h($step['icon']) ?></div>
                        <?php endif; ?>
                        <div class="step-title"><?= h($step['title']) ?></div>
                        <?php if (!empty($step['description'])): ?>
                            <div class="step-desc"><?= h($step['description']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- FEATURED PRODUCTS -->
    <?php if (!empty($featuredProducts)): ?>
    <section class="section section-light">
        <div class="container">
            <h2 class="section-title">Nos produits populaires</h2>
            <p class="section-subtitle">Les favoris de nos clients</p>
            <div class="products-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <a href="/public/product.php?id=<?= $product['id'] ?>" class="product-card product-card-featured">
                        <?php if (!empty($product['image_front_url'])): ?>
                            <img src="/public<?= h($product['image_front_url']) ?>" alt="<?= h($product['name']) ?>" class="product-card-img">
                        <?php else: ?>
                            <div class="product-card-img" style="display:flex;align-items:center;justify-content:center;color:#ccc;font-size:48px;">👕</div>
                        <?php endif; ?>
                        <div class="product-card-body">
                            <div class="product-card-name"><?= h($product['name']) ?></div>
                            <?php if (!empty($product['category_names'])): ?>
                                <div class="product-card-cats">
                                    <?php foreach ($product['category_names'] as $catName): ?>
                                        <span class="product-card-cat"><?= h($catName) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="product-card-price"><?= number_format($product['base_price'], 2, ',', ' ') ?> &euro;</div>
                        </div>
                        <div class="product-card-cta">Personnaliser</div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ALL PRODUCTS -->
    <section class="section" id="produits">
        <div class="container">
            <h2 class="section-title">Tous nos produits</h2>
            <p class="section-subtitle">Découvrez notre collection complète</p>
            <?php if (empty($allProducts)): ?>
                <p style="text-align:center;color:var(--gray);">Aucun produit disponible pour le moment.</p>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($allProducts as $product): ?>
                        <a href="/public/product.php?id=<?= $product['id'] ?>" class="product-card">
                            <?php if (!empty($product['image_front_url'])): ?>
                                <img src="/public<?= h($product['image_front_url']) ?>" alt="<?= h($product['name']) ?>" class="product-card-img">
                            <?php else: ?>
                                <div class="product-card-img" style="display:flex;align-items:center;justify-content:center;color:#ccc;font-size:48px;">👕</div>
                            <?php endif; ?>
                            <div class="product-card-body">
                                <div class="product-card-name"><?= h($product['name']) ?></div>
                                <?php if (!empty($product['category_names'])): ?>
                                    <div class="product-card-cats">
                                        <?php foreach ($product['category_names'] as $catName): ?>
                                            <span class="product-card-cat"><?= h($catName) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="product-card-price"><?= number_format($product['base_price'], 2, ',', ' ') ?> &euro;</div>
                            </div>
                            <div class="product-card-cta">Personnaliser</div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <?php if (!empty($testimonials)): ?>
    <section class="section section-dark">
        <div class="container">
            <h2 class="section-title">Ce que disent nos clients</h2>
            <p class="section-subtitle">Des avis authentiques de notre communauté</p>
            <div class="testimonials-grid">
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="testimonial-card">
                        <div class="testimonial-header">
                            <div class="testimonial-avatar">
                                <?php if (!empty($testimonial['photo_url'])): ?>
                                    <img src="/public<?= h($testimonial['photo_url']) ?>" alt="">
                                <?php else: ?>
                                    <?= strtoupper(substr($testimonial['name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="testimonial-name"><?= h($testimonial['name']) ?></div>
                                <div class="testimonial-rating"><?= str_repeat('⭐', $testimonial['rating']) ?></div>
                            </div>
                        </div>
                        <div class="testimonial-content">"<?= h($testimonial['content']) ?>"</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- BLOG -->
    <?php if (!empty($blogPosts)): ?>
    <section class="section section-light">
        <div class="container">
            <h2 class="section-title">Notre blog</h2>
            <p class="section-subtitle">Conseils, inspirations et actualités</p>
            <div class="blog-grid">
                <?php foreach ($blogPosts as $post): ?>
                    <a href="#" class="blog-card">
                        <?php if (!empty($post['cover_image_url'])): ?>
                            <img src="/public<?= h($post['cover_image_url']) ?>" alt="" class="blog-card-img">
                        <?php else: ?>
                            <div class="blog-card-img" style="display:flex;align-items:center;justify-content:center;color:#ccc;font-size:32px;">📝</div>
                        <?php endif; ?>
                        <div class="blog-card-body">
                            <div class="blog-card-title"><?= h($post['title']) ?></div>
                            <?php if (!empty($post['excerpt'])): ?>
                                <div class="blog-card-excerpt"><?= h(substr($post['excerpt'], 0, 120)) ?>...</div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- NEWSLETTER -->
    <section class="newsletter-section">
        <div class="container">
            <div class="newsletter-content">
                <h2>Restez informé</h2>
                <p>Inscrivez-vous pour recevoir nos offres exclusives et nouveautés</p>
                <?php if ($newsletterSuccess): ?>
                    <div class="newsletter-success">Merci ! Vous êtes inscrit(e) à notre newsletter.</div>
                <?php else: ?>
                    <form method="post" class="newsletter-form">
                        <input type="email" name="newsletter_email" class="newsletter-input" placeholder="Votre email" required>
                        <button type="submit" class="newsletter-btn">S'inscrire</button>
                    </form>
                    <?php if ($newsletterError): ?>
                        <p style="color:white;margin-top:12px;"><?= h($newsletterError) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> PERSONNALY &mdash; Personnalisation textile de qualité</p>
        </div>
    </footer>

    <!-- SLIDER SCRIPT -->
    <?php if (count($heroSlides) > 1): ?>
    <script>
        (function() {
            const slides = document.querySelectorAll('.hero-slide');
            const dots = document.querySelectorAll('.hero-dot');
            let current = 0;
            let interval;

            function goTo(index) {
                slides[current].classList.remove('active');
                dots[current].classList.remove('active');
                current = index;
                if (current >= slides.length) current = 0;
                if (current < 0) current = slides.length - 1;
                slides[current].classList.add('active');
                dots[current].classList.add('active');
            }

            function next() { goTo(current + 1); }

            function startInterval() {
                interval = setInterval(next, 5000);
            }

            dots.forEach((dot, i) => {
                dot.addEventListener('click', () => {
                    clearInterval(interval);
                    goTo(i);
                    startInterval();
                });
            });

            startInterval();
        })();
    </script>
    <?php endif; ?>

</body>
</html>
