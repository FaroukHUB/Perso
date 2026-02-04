<?php
/**
 * PERSONNALY - Template de carte produit
 * Utilisé dans les sections featured_products et featured_category
 * Variable disponible: $product (tableau avec les données du produit)
 */
?>
<div class="product-card">
    <div class="product-image">
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
            <span class="product-price"><?= formatPrice($product['base_price']) ?></span>
            <a href="/public/product.php?id=<?= $product['id'] ?>" class="product-btn">Personnaliser</a>
        </div>
    </div>
</div>
