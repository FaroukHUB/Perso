<?php
/**
 * PERSONNALY - Template de carte produit
 * Utilisé dans les sections featured_products et featured_category
 * Variable disponible: $product (tableau avec les données du produit)
 */
$hasSalePrice = !empty($product['sale_price']) && $product['sale_price'] > 0 && $product['sale_price'] < $product['base_price'];
$badgeText = $product['badge'] ?? '';
$badgeColor = $product['badge_color'] ?? '#FF1493';
// Auto-badge "Soldé" si prix soldé et pas de badge défini
if ($hasSalePrice && empty($badgeText)) {
    $badgeText = 'Soldé';
    $badgeColor = '#FF1493';
}
// Preset badge colors
$badgePresetColors = [
    'Soldé' => '#FF1493',
    'Nouveau' => '#3DFFC0',
    'Populaire' => '#8B5CF6',
    'Limité' => '#F59E0B',
];
if (isset($badgePresetColors[$badgeText]) && $badgeColor === '#FF1493') {
    $badgeColor = $badgePresetColors[$badgeText];
}
$badgeTextColor = in_array($badgeText, ['Nouveau']) ? '#1a1a2e' : '#ffffff';
?>
<div class="product-card">
    <div class="product-image">
        <?php if (!empty($badgeText)): ?>
            <span class="product-badge" style="background:<?= h($badgeColor) ?>;color:<?= $badgeTextColor ?>;">
                <?= h($badgeText) ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($product['category_names'])): ?>
            <span class="product-category badge badge-mint">
                <?= h($product['category_names'][0]) ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($product['image_front_url'])): ?>
            <img src="/public<?= h($product['image_front_url']) ?>" alt="<?= h($product['name']) ?>" loading="lazy">
        <?php else: ?>
            <div class="product-image-placeholder">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
            </div>
        <?php endif; ?>
    </div>
    <div class="product-info">
        <h3><?= h($product['name']) ?></h3>
        <p><?= h($product['description'] ?? 'Personnalisable avec votre design') ?></p>
        <div class="product-footer">
            <div class="product-footer-top">
                <?php if ($hasSalePrice): ?>
                    <div class="product-price-group">
                        <span class="product-price-old"><?= formatPrice($product['base_price']) ?></span>
                        <span class="product-price product-price-sale"><?= formatPrice($product['sale_price']) ?></span>
                    </div>
                <?php else: ?>
                    <span class="product-price"><?= formatPrice($product['base_price']) ?></span>
                <?php endif; ?>
            </div>
            <div class="product-buttons">
                <button type="button" class="product-btn product-btn-primary add-to-cart-btn"
                        data-product-id="<?= $product['id'] ?>"
                        data-product-name="<?= h($product['name']) ?>"
                        data-product-price="<?= $hasSalePrice ? $product['sale_price'] : $product['base_price'] ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    Ajouter au panier
                </button>
                <a href="/public/product.php?id=<?= $product['id'] ?>" class="product-btn product-btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Personnaliser
                </a>
            </div>
        </div>
    </div>
</div>
