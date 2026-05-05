<?php
/**
 * PERSONNALY - Footer
 */

// Charger le logo si pas déjà fait
if (!class_exists('SiteSetting')) {
    require_once __DIR__ . '/../../app/models/SiteSetting.php';
}
if (!isset($siteLogo)) {
    $siteSettingModel = new SiteSetting();
    $siteLogo = $siteSettingModel->getLogo();
}
?>
<!-- ===== FOOTER ===== -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Logo & Description -->
            <div class="footer-brand">
                <a href="/" class="footer-logo">
                    <?php if ($siteLogo['type'] === 'image' && !empty($siteLogo['image_url'])): ?>
                        <img src="/public<?= htmlspecialchars($siteLogo['image_url']) ?>" alt="<?= htmlspecialchars($siteLogo['text']) ?>">
                    <?php else: ?>
                        <?= htmlspecialchars($siteLogo['text'] ?: 'PERSONNALY') ?>
                    <?php endif; ?>
                </a>
                <p class="footer-desc">Personnalisation textile de qualité pour toute la famille. Créez des vêtements uniques qui vous ressemblent.</p>
            </div>

            <!-- Navigation -->
            <div class="footer-col">
                <h4>Navigation</h4>
                <ul>
                    <li><a href="/">Accueil</a></li>
                    <li><a href="/products.php">Produits</a></li>
                    <li><a href="/blog.php">Blog</a></li>
                    <li><a href="/contact.php">Contact</a></li>
                </ul>
            </div>

            <!-- Informations -->
            <div class="footer-col">
                <h4>Informations</h4>
                <ul>
                    <li><a href="/mentions-legales.php">Mentions légales</a></li>
                    <li><a href="/cgv.php">CGV</a></li>
                    <li><a href="/livraison.php">Livraison</a></li>
                    <li><a href="/faq.php">FAQ</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="footer-col">
                <h4>Contact</h4>
                <ul class="footer-contact">
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <a href="mailto:contact@personnaly.fr">contact@personnaly.fr</a>
                    </li>
                </ul>
                <div class="footer-social">
                    <a href="#" aria-label="Facebook">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                        </svg>
                    </a>
                    <a href="#" aria-label="Instagram">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteLogo['text'] ?: 'PERSONNALY') ?>. Tous droits réservés.</p>
        </div>
    </div>
</footer>

<style>
.site-footer {
    background: var(--black);
    color: rgba(255,255,255,0.7);
    padding: 60px 0 30px;
    margin-top: 60px;
}
.footer-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 40px;
}
.footer-brand { max-width: 280px; }
.footer-logo {
    display: inline-block;
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 800;
    text-decoration: none;
    background: var(--gradient-hero);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 15px;
}
.footer-logo img {
    height: 40px;
    width: auto;
}
.footer-desc {
    font-size: 14px;
    line-height: 1.6;
    color: rgba(255,255,255,0.6);
}
.footer-col h4 {
    color: white;
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 20px;
}
.footer-col ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.footer-col li {
    margin-bottom: 10px;
}
.footer-col a {
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    font-size: 14px;
    transition: color 0.2s;
}
.footer-col a:hover {
    color: var(--pink-main);
}
.footer-contact li {
    display: flex;
    align-items: center;
    gap: 8px;
}
.footer-contact svg {
    color: var(--pink-main);
    flex-shrink: 0;
}
.footer-social {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}
.footer-social a {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.7);
    transition: all 0.2s;
}
.footer-social a:hover {
    background: var(--pink-main);
    color: white;
}
.footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.1);
    margin-top: 40px;
    padding-top: 20px;
    text-align: center;
}
.footer-bottom p {
    font-size: 13px;
    color: rgba(255,255,255,0.5);
    margin: 0;
}

@media (max-width: 900px) {
    .footer-grid {
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    .footer-brand {
        grid-column: span 2;
        max-width: 100%;
    }
}
@media (max-width: 500px) {
    .footer-grid {
        grid-template-columns: 1fr;
    }
    .footer-brand {
        grid-column: span 1;
    }
}
</style>
