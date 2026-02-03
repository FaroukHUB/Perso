<?php
/**
 * PERSONNALY - Page Article de Blog
 * Affiche le contenu complet d'un article
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/helpers/FontLoader.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/BlogPost.php';
require_once __DIR__ . '/../app/services/BrandingService.php';

$cartCount = Cart::count();
$brandingService = new BrandingService();
$blogModel = new BlogPost();

// Récupération de l'article par slug
$slug = trim($_GET['slug'] ?? '');
$article = null;

if (!empty($slug)) {
    $article = $blogModel->findBySlug($slug);
}

// Article introuvable ou non publié
if (!$article || $article['status'] !== 'published') {
    http_response_code(404);
    $pageTitle = "Article non trouvé";
    $notFound = true;
} else {
    $pageTitle = htmlspecialchars($article['title']) . ' - Blog PERSONNALY';
    $notFound = false;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?php if (!$notFound && !empty($article['excerpt'])): ?>
    <meta name="description" content="<?= h($article['excerpt']) ?>">
    <?php endif; ?>
    <?php
    $favicon = $brandingService->getFavicon();
    if ($favicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= h($favicon) ?>">
    <?php endif; ?>
    <?= $brandingService->getFontLinks() ?>
    <?= FontLoader::renderHead() ?>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <?= $brandingService->getStyleBlock() ?>
    <style>
        /* Navbar */
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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
            transition: color 0.2s;
        }
        .navbar-nav a:hover { color: var(--pink-main); }
        .cart-nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--gradient-pink);
            padding: 10px 20px;
            border-radius: 50px;
            color: white !important;
        }
        .cart-badge {
            background: var(--mint-main);
            color: var(--black);
            font-size: 12px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 50px;
            margin-left: 4px;
        }

        /* Article */
        .article-page {
            padding-top: 100px;
            min-height: 100vh;
            background: var(--gray-light);
        }
        .article-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }
        .article-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 30px;
            transition: color 0.2s;
        }
        .article-back:hover {
            color: var(--pink-main);
        }
        .article-cover {
            width: 100%;
            aspect-ratio: 16/9;
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 30px;
            background: linear-gradient(135deg, rgba(255,105,180,0.1) 0%, rgba(61,255,192,0.1) 100%);
        }
        .article-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .article-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            color: var(--gray);
            font-size: 14px;
        }
        .article-meta svg {
            width: 16px;
            height: 16px;
        }
        .article-title {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--black-soft);
            margin-bottom: 30px;
            line-height: 1.2;
        }
        .article-content {
            background: white;
            border-radius: var(--radius-lg);
            padding: 40px;
            box-shadow: var(--shadow-sm);
        }
        .article-content h1 { font-size: 2rem; margin: 2em 0 0.5em; color: var(--black-soft); }
        .article-content h2 { font-size: 1.5rem; margin: 1.5em 0 0.5em; color: var(--black-soft); }
        .article-content h3 { font-size: 1.25rem; margin: 1.5em 0 0.5em; color: var(--black-soft); }
        .article-content p { margin: 0 0 1.2em; line-height: 1.8; color: var(--black); }
        .article-content a { color: var(--pink-main); }
        .article-content img {
            max-width: 100%;
            height: auto;
            border-radius: var(--radius-md);
            margin: 1.5em 0;
        }
        .article-content blockquote {
            border-left: 4px solid var(--pink-main);
            margin: 1.5em 0;
            padding: 1em 1.5em;
            background: var(--gray-light);
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            font-style: italic;
        }
        .article-content ul, .article-content ol {
            margin: 1em 0;
            padding-left: 2em;
        }
        .article-content li { margin-bottom: 0.5em; line-height: 1.6; }

        /* 404 */
        .not-found {
            text-align: center;
            padding: 100px 20px;
        }
        .not-found h1 {
            font-family: var(--font-display);
            font-size: 6rem;
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
        .not-found p {
            color: var(--gray);
            font-size: 1.2rem;
            margin-bottom: 30px;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gradient-pink);
            color: white;
            padding: 14px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255,105,180,0.3);
        }

        @media (max-width: 768px) {
            .navbar-nav a:not(.cart-nav-link) { display: none; }
            .article-title { font-size: 1.8rem; }
            .article-content { padding: 24px; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../app/templates/header.php'; ?>

    <main class="article-page">
        <?php if ($notFound): ?>
            <!-- 404 -->
            <div class="not-found">
                <h1>404</h1>
                <p>Cet article n'existe pas ou n'est plus disponible.</p>
                <a href="/" class="btn-home">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Retour à l'accueil
                </a>
            </div>
        <?php else: ?>
            <div class="article-container">
                <a href="/" class="article-back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Retour à l'accueil
                </a>

                <?php if (!empty($article['cover_image_url'])): ?>
                <div class="article-cover">
                    <img src="/public<?= h($article['cover_image_url']) ?>" alt="<?= h($article['title']) ?>">
                </div>
                <?php endif; ?>

                <div class="article-meta">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <?= $article['published_at'] ? date('d/m/Y', strtotime($article['published_at'])) : date('d/m/Y', strtotime($article['created_at'])) ?>
                    </span>
                </div>

                <h1 class="article-title"><?= h($article['title']) ?></h1>

                <div class="article-content">
                    <?= $article['content'] ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../app/templates/footer.php'; ?>
</body>
</html>
