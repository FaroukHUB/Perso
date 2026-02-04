<?php
/**
 * PERSONNALY - Header Template
 * Barre de navigation avec couleur de fond personnalisable
 *
 * Variables attendues (optionnelles):
 * - $cartCount : nombre d'articles dans le panier
 * - $brandingService : instance de BrandingService
 * - $hasPacks : boolean pour afficher le lien "Idées"
 * - $menuItems : tableau d'éléments de menu personnalisés (optionnel)
 */

// Charger les dépendances si pas déjà fait
if (!class_exists('ShopSettings')) {
    require_once __DIR__ . '/../models/ShopSettings.php';
}
if (!class_exists('BrandingService')) {
    require_once __DIR__ . '/../services/BrandingService.php';
}
if (!class_exists('Cart')) {
    require_once __DIR__ . '/../helpers/Cart.php';
}
if (!class_exists('Menu')) {
    require_once __DIR__ . '/../models/Menu.php';
}

// Initialiser les variables si non définies
if (!isset($cartCount)) {
    $cartCount = Cart::count();
}
if (!isset($brandingService)) {
    $brandingService = new BrandingService();
}
if (!isset($hasPacks)) {
    $hasPacks = false;
}

// Récupérer les couleurs du header
$settings = new ShopSettings();
$headerBgColor = $settings->get('header_bg_color', '#1a1a2e');
$headerTextColor = $settings->get('header_text_color', '#ffffff');

// Récupérer le logo
$logoUrl = $brandingService->getLogo(null, false); // false = fond sombre

// Charger le menu depuis la base de données si pas fourni
if (!isset($menuItems) || empty($menuItems)) {
    try {
        $menuModel = new Menu();
        $menuItems = $menuModel->getItemsByLocation('header_main');
    } catch (Exception $e) {
        $menuItems = [];
    }
}

// Inclure la top bar si activée
include __DIR__ . '/topbar.php';
?>
<!-- Navbar -->
<nav class="navbar" style="background: <?= htmlspecialchars($headerBgColor) ?>; --header-text-color: <?= htmlspecialchars($headerTextColor) ?>;">
    <style>
        .navbar { color: <?= htmlspecialchars($headerTextColor) ?>; }
        .navbar a, .navbar .navbar-brand, .navbar .navbar-nav a { color: <?= htmlspecialchars($headerTextColor) ?> !important; }
        .navbar .cart-nav-link svg { stroke: <?= htmlspecialchars($headerTextColor) ?>; }
    </style>
    <div class="container">
        <?php if ($logoUrl): ?>
            <a href="/" class="navbar-brand"><img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="navbar-logo"></a>
        <?php else: ?>
            <a href="/" class="navbar-brand"><?= htmlspecialchars($settings->getSiteName()) ?></a>
        <?php endif; ?>
        <div class="navbar-nav">
            <?php if (!empty($menuItems)): ?>
                <?php foreach ($menuItems as $item): ?>
                    <?php if (!empty($item['children'])): ?>
                        <div class="nav-dropdown">
                            <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-dropdown-toggle <?= !empty($item['css_class']) ? htmlspecialchars($item['css_class']) : '' ?>"
                               <?= !empty($item['open_new_tab']) ? 'target="_blank" rel="noopener"' : '' ?>>
                                <?= htmlspecialchars($item['label']) ?>
                                <svg class="dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </a>
                            <div class="nav-dropdown-menu">
                                <?php foreach ($item['children'] as $child): ?>
                                    <a href="<?= htmlspecialchars($child['url']) ?>" class="<?= !empty($child['css_class']) ? htmlspecialchars($child['css_class']) : '' ?>"
                                       <?= !empty($child['open_new_tab']) ? 'target="_blank" rel="noopener"' : '' ?>>
                                        <?= htmlspecialchars($child['label']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= !empty($item['css_class']) ? htmlspecialchars($item['css_class']) : '' ?>"
                           <?= !empty($item['open_new_tab']) ? 'target="_blank" rel="noopener"' : '' ?>>
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <a href="/#produits">Produits</a>
                <?php if ($hasPacks): ?><a href="/#inspirations">Idées</a><?php endif; ?>
                <a href="/#categories">Catégories</a>
                <a href="/#contact">Contact</a>
            <?php endif; ?>
            <a href="/public/cart.php" class="cart-nav-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                Panier
                <?php if ($cartCount > 0): ?><span class="cart-badge"><?= $cartCount ?></span><?php endif; ?>
            </a>
        </div>
    </div>
</nav>
