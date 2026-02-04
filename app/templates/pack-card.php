<?php
/**
 * PERSONNALY - Template de carte pack/idée
 * Utilisé dans les sections featured_packs
 * Variable disponible: $pack (tableau avec les données du pack)
 */

$typeLabels = [
    'technique' => 'Technique',
    'contextuel' => 'Contextuel',
    'thematique' => 'Thématique',
    'inspiration' => 'Inspiration'
];
?>
<div class="pack-card inspiration-card">
    <div class="pack-image inspiration-image">
        <span class="pack-type inspiration-type type-<?= h($pack['type'] ?? 'inspiration') ?>">
            <?= h($typeLabels[$pack['type']] ?? 'Idée') ?>
        </span>
        <?php if (!empty($pack['cover_image_url'])): ?>
            <img src="/public<?= h($pack['cover_image_url']) ?>" alt="<?= h($pack['name']) ?>" loading="lazy">
        <?php elseif (!empty($pack['first_product']['image_front_url'])): ?>
            <img src="/public<?= h($pack['first_product']['image_front_url']) ?>" alt="<?= h($pack['name']) ?>" loading="lazy">
        <?php else: ?>
            <div class="pack-image-placeholder">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
            </div>
        <?php endif; ?>
    </div>
    <div class="pack-info inspiration-info">
        <h3><?= h($pack['name']) ?></h3>
        <?php if (!empty($pack['description'])): ?>
            <p><?= h(mb_substr($pack['description'], 0, 100)) ?><?= mb_strlen($pack['description']) > 100 ? '...' : '' ?></p>
        <?php endif; ?>
        <a href="/public/pack.php?id=<?= $pack['id'] ?>" class="pack-cta inspiration-cta">
            Essayer cette idée
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
        </a>
    </div>
</div>
