<?php
/**
 * PERSONNALY - Sitemap XML Generator
 * Génère automatiquement un sitemap.xml pour le SEO
 */

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Page.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Pack.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/BlogPost.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';

header('Content-Type: application/xml; charset=utf-8');

// Configuration
$settings = new ShopSettings();
$siteUrl = rtrim($settings->get('site_url', 'https://personnaly.fr'), '/');

// Début du sitemap
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Page d'accueil (priorité maximale)
outputUrl($siteUrl . '/', date('Y-m-d'), 'daily', '1.0');

// Pages personnalisées
$pageModel = new Page();
$pages = $pageModel->findPublished();
foreach ($pages as $page) {
    if ($page['slug'] === 'home') continue; // Skip home, already added
    $url = $siteUrl . '/' . $page['slug'];
    $lastmod = $page['updated_at'] ?? $page['created_at'];
    $priority = $page['is_system'] ? '0.9' : '0.7';
    outputUrl($url, $lastmod, 'weekly', $priority);
}

// Produits
$productModel = new Product();
$products = $productModel->findAll(true); // only active
foreach ($products as $product) {
    $url = $siteUrl . '/produit/' . ($product['slug'] ?? $product['id']);
    $lastmod = $product['updated_at'] ?? $product['created_at'];
    outputUrl($url, $lastmod, 'weekly', '0.8');
}

// Packs
$packModel = new Pack();
$packs = $packModel->findAllActive();
foreach ($packs as $pack) {
    $url = $siteUrl . '/pack/' . ($pack['slug'] ?? $pack['id']);
    $lastmod = $pack['updated_at'] ?? $pack['created_at'];
    outputUrl($url, $lastmod, 'weekly', '0.7');
}

// Catégories
$categoryModel = new Category();
$categories = $categoryModel->findAllActive();
foreach ($categories as $category) {
    $url = $siteUrl . '/categorie/' . ($category['slug'] ?? $category['id']);
    $lastmod = $category['updated_at'] ?? $category['created_at'];
    outputUrl($url, $lastmod, 'weekly', '0.6');
}

// Articles de blog
$blogModel = new BlogPost();
$posts = $blogModel->findPublished(100); // Max 100 articles
foreach ($posts as $post) {
    $url = $siteUrl . '/article/' . ($post['slug'] ?? $post['id']);
    $lastmod = $post['updated_at'] ?? $post['published_at'] ?? $post['created_at'];
    outputUrl($url, $lastmod, 'monthly', '0.6');
}

// Pages légales statiques
$legalPages = ['cgv', 'mentions-legales', 'politique-confidentialite', 'politique-retour'];
foreach ($legalPages as $slug) {
    outputUrl($siteUrl . '/' . $slug, date('Y-m-d'), 'yearly', '0.3');
}

echo '</urlset>';

/**
 * Affiche une URL au format sitemap
 */
function outputUrl(string $loc, ?string $lastmod, string $changefreq, string $priority): void
{
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    if ($lastmod) {
        $date = date('Y-m-d', strtotime($lastmod));
        echo "    <lastmod>$date</lastmod>\n";
    }
    echo "    <changefreq>$changefreq</changefreq>\n";
    echo "    <priority>$priority</priority>\n";
    echo "  </url>\n";
}
