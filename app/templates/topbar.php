<?php
/**
 * PERSONNALY - Top Bar (Barre d'annonce promotionnelle)
 * Affiche un message défilant en haut du site
 */

// Charger ShopSettings si pas déjà fait
if (!isset($shopSettings)) {
    require_once __DIR__ . '/../models/ShopSettings.php';
    $shopSettings = new ShopSettings();
}

// Vérifier si la top bar est activée
$topbarEnabled = $shopSettings->get('topbar_enabled', true);

if (!$topbarEnabled) {
    return;
}

// Récupérer les paramètres
$topbarText = $shopSettings->get('topbar_text', 'Livraison GRATUITE dès 50€ d\'achat !');
$topbarLink = $shopSettings->get('topbar_link', '');
$topbarBgColor = $shopSettings->get('topbar_bg_color', '#1a1a2e');
$topbarTextColor = $shopSettings->get('topbar_text_color', '#ffffff');
$topbarFontFamily = $shopSettings->get('topbar_font_family', 'inherit');
$topbarFontSize = $shopSettings->get('topbar_font_size', '14');
$topbarScrollSpeed = $shopSettings->get('topbar_scroll_speed', '30');

// Ne pas afficher si pas de texte
if (empty(trim($topbarText))) {
    return;
}
?>
<div class="topbar" id="topbar" style="
    background: <?= htmlspecialchars($topbarBgColor) ?>;
    color: <?= htmlspecialchars($topbarTextColor) ?>;
    font-family: <?= htmlspecialchars($topbarFontFamily) ?>;
    font-size: <?= htmlspecialchars($topbarFontSize) ?>px;
">
    <div class="topbar-content">
        <?php if (!empty($topbarLink)): ?>
            <a href="<?= htmlspecialchars($topbarLink) ?>" class="topbar-link" style="color: <?= htmlspecialchars($topbarTextColor) ?>;">
                <span class="topbar-text"><?= htmlspecialchars($topbarText) ?></span>
                <span class="topbar-text"><?= htmlspecialchars($topbarText) ?></span>
            </a>
        <?php else: ?>
            <span class="topbar-text"><?= htmlspecialchars($topbarText) ?></span>
            <span class="topbar-text"><?= htmlspecialchars($topbarText) ?></span>
        <?php endif; ?>
    </div>
    <button class="topbar-close" onclick="closeTopbar()" aria-label="Fermer">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>
</div>

<style>
.topbar {
    position: relative;
    width: 100%;
    padding: 8px 40px;
    overflow: hidden;
    z-index: 1001;
    text-align: center;
}

.topbar-content {
    display: inline-flex;
    white-space: nowrap;
    animation: topbar-scroll <?= htmlspecialchars($topbarScrollSpeed) ?>s linear infinite;
}

.topbar-text {
    display: inline-block;
    padding: 0 50px;
    font-weight: 500;
    letter-spacing: 0.5px;
}

.topbar-link {
    text-decoration: none;
    display: inline-flex;
}

.topbar-link:hover {
    text-decoration: underline;
}

.topbar-close {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 5px;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.topbar-close:hover {
    opacity: 1;
}

@keyframes topbar-scroll {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-50%);
    }
}

/* Pause animation on hover */
.topbar:hover .topbar-content {
    animation-play-state: paused;
}

/* Hide on small screens if needed */
@media (max-width: 480px) {
    .topbar {
        font-size: 12px !important;
        padding: 6px 30px;
    }
    .topbar-text {
        padding: 0 30px;
    }
}
</style>

<script>
function closeTopbar() {
    const topbar = document.getElementById('topbar');
    if (topbar) {
        topbar.style.display = 'none';
        // Stocker dans sessionStorage pour ne pas réafficher pendant la session
        sessionStorage.setItem('topbar_closed', '1');
    }
}

// Vérifier si déjà fermée pendant cette session
document.addEventListener('DOMContentLoaded', function() {
    if (sessionStorage.getItem('topbar_closed') === '1') {
        const topbar = document.getElementById('topbar');
        if (topbar) {
            topbar.style.display = 'none';
        }
    }
});
</script>
