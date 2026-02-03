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

// Initialiser les variables si non définies
if (!isset($hasPacks)) {
    $hasPacks = false;
}

// Récupérer les paramètres
$settings = new ShopSettings();
$footerBgColor = $settings->get('footer_bg_color', '#1a1a2e');
$siteName = $settings->getSiteName();
$contactEmail = $settings->getContactEmail();
$footerContent = $settings->getFooterContent();
?>
<!-- Footer -->
<footer class="footer" id="contact" style="background: <?= htmlspecialchars($footerBgColor) ?>;">
    <div class="container">
        <div class="footer-content">
            <div class="footer-brand">
                <h3><?= htmlspecialchars($siteName) ?></h3>
                <p><?= htmlspecialchars($footerContent['description'] ?: 'Personnalisation textile de qualité pour toute la famille.') ?></p>
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
