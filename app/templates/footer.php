<?php
/**
 * PERSONNALY - Footer Template
 * Pied de page avec couleur de fond personnalisable
 *
 * Variables attendues (optionnelles):
 * - $hasPacks : boolean pour afficher le lien "Idées"
 */

// Charger les dépendances si pas déjà fait
if (!class_exists('ShopSettings')) {
    require_once __DIR__ . '/../models/ShopSettings.php';
}
if (!class_exists('BrandingService')) {
    require_once __DIR__ . '/../services/BrandingService.php';
}

// Initialiser les variables si non définies
if (!isset($hasPacks)) {
    $hasPacks = false;
}

// Récupérer les paramètres
$settings = new ShopSettings();
$footerBgColor = $settings->get('footer_bg_color', '#1a1a2e');
$footerTextColor = $settings->get('footer_text_color', '#ffffff');
$siteName = $settings->getSiteName();
$contactEmail = $settings->getContactEmail();
$footerContent = $settings->getFooterContent();
$socialLinks = $settings->getSocialLinks();

// Récupérer le logo pour fond sombre
$brandingService = new BrandingService();
$footerLogoUrl = $brandingService->getLogo(null, false); // false = fond sombre
?>
<!-- Footer -->
<footer class="footer" id="contact" style="background: <?= htmlspecialchars($footerBgColor) ?>;">
    <style>
        .footer, .footer p, .footer-bottom p { color: <?= htmlspecialchars($footerTextColor) ?> !important; }
        .footer h3, .footer h4 { color: <?= htmlspecialchars($footerTextColor) ?> !important; }
        .footer a, .footer-links a { color: <?= htmlspecialchars($footerTextColor) ?> !important; opacity: 0.85; }
        .footer a:hover { opacity: 1; }
        .footer-logo { max-height: 50px; width: auto; margin-bottom: 12px; }
    </style>
    <div class="container">
        <div class="footer-content">
            <div class="footer-brand">
                <?php if ($footerLogoUrl): ?>
                    <a href="/"><img src="<?= htmlspecialchars($footerLogoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="footer-logo"></a>
                <?php else: ?>
                    <h3><?= htmlspecialchars($siteName) ?></h3>
                <?php endif; ?>
                <p><?= htmlspecialchars($footerContent['description'] ?: 'Personnalisation textile de qualité pour toute la famille.') ?></p>
                <?php if (!empty($socialLinks)): ?>
                <div class="footer-social-links">
                    <?php foreach ($socialLinks as $social): ?>
                        <a href="<?= htmlspecialchars($social['url']) ?>" target="_blank" rel="noopener" class="social-icon" title="<?= htmlspecialchars($social['label']) ?>">
                            <?php include __DIR__ . '/social-icons/' . $social['icon'] . '.svg.php'; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="footer-links">
                <h4>Navigation</h4>
                <a href="/#produits">Produits</a>
                <?php if ($hasPacks): ?><a href="/#inspirations">Idées</a><?php endif; ?>
                <a href="/#categories">Catégories</a>
            </div>

            <div class="footer-links">
                <h4>Légal</h4>
                <a href="/cgv">CGV</a>
                <a href="/mentions-legales">Mentions légales</a>
                <a href="/politique-confidentialite">Confidentialité</a>
                <a href="/politique-retour">Retours</a>
            </div>

            <div class="footer-links">
                <h4>Contact</h4>
                <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> - Tous droits réservés</p>
        </div>
    </div>
</footer>
<?php
// Inclure le popup si activé
include __DIR__ . '/popup.php';
?>
