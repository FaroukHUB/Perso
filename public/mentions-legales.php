<?php
/**
 * PERSONNALY - Mentions Légales
 * Page générée automatiquement depuis les paramètres admin
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';

$shopSettings = new ShopSettings();
$legal = $shopSettings->getLegalInfo();
$siteName = $shopSettings->getSiteName();
$pageTitle = 'Mentions Légales';

// Si non configuré, afficher un message
if (!$shopSettings->areLegalPagesConfigured()) {
    $pageContent = '<p>Les mentions légales seront bientôt disponibles.</p>';
} else {
    // Générer le contenu
    ob_start();
    ?>
    <h2>1. Éditeur du site</h2>
    <p>Le site <strong><?= h($legal['site_url']) ?></strong> est édité par :</p>
    <div class="legal-info-box">
        <p>
            <strong><?= h($legal['company_name']) ?></strong><br>
            <?php if ($legal['company_type'] !== 'auto-entrepreneur'): ?>
            <?= h(strtoupper($legal['company_type'])) ?>
            <?php if ($legal['capital']): ?> au capital de <?= h($legal['capital']) ?><?php endif; ?><br>
            <?php else: ?>
            Auto-entrepreneur<br>
            <?php endif; ?>
            <?= h($legal['full_address']) ?><br>
            <?php if ($legal['phone']): ?>Tél : <?= h($legal['phone']) ?><br><?php endif; ?>
            Email : <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a>
        </p>
    </div>

    <h2>2. Identification</h2>
    <ul>
        <li><strong>SIRET :</strong> <?= h($legal['siret']) ?></li>
        <?php if ($legal['siren']): ?><li><strong>SIREN :</strong> <?= h($legal['siren']) ?></li><?php endif; ?>
        <?php if ($legal['tva_number']): ?><li><strong>N° TVA Intracommunautaire :</strong> <?= h($legal['tva_number']) ?></li><?php endif; ?>
        <?php if ($legal['rcs']): ?><li><strong>RCS :</strong> <?= h($legal['rcs']) ?></li><?php endif; ?>
    </ul>

    <h2>3. Directeur de la publication</h2>
    <p>
        Le directeur de la publication est <strong><?= h($legal['director_name']) ?></strong><?php if ($legal['director_title']): ?>, <?= h($legal['director_title']) ?><?php endif; ?>.
    </p>

    <h2>4. Hébergement</h2>
    <p>Le site est hébergé par :</p>
    <div class="legal-info-box">
        <p>
            <strong><?= h($legal['host_name']) ?></strong><br>
            <?php if ($legal['host_address']): ?><?= h($legal['host_address']) ?><br><?php endif; ?>
            <?php if ($legal['host_phone']): ?>Tél : <?= h($legal['host_phone']) ?><?php endif; ?>
        </p>
    </div>

    <h2>5. Propriété intellectuelle</h2>
    <p>
        L'ensemble du contenu de ce site (textes, images, vidéos, logos, graphismes, etc.) est la propriété exclusive de
        <strong><?= h($legal['company_name']) ?></strong> ou de ses partenaires. Toute reproduction, représentation,
        modification, publication, transmission, ou plus généralement toute exploitation non autorisée du site ou de
        ses éléments est interdite et constitue une contrefaçon sanctionnée par les articles L.335-2 et suivants du
        Code de la Propriété Intellectuelle.
    </p>

    <h2>6. Données personnelles</h2>
    <p>
        Conformément au Règlement Général sur la Protection des Données (RGPD) et à la loi Informatique et Libertés,
        vous disposez d'un droit d'accès, de rectification, de suppression et de portabilité de vos données personnelles.
    </p>
    <p>
        Pour plus d'informations sur le traitement de vos données personnelles, veuillez consulter notre
        <a href="/politique-confidentialite">Politique de Confidentialité</a>.
    </p>
    <?php if ($legal['dpo_email'] || $legal['email']): ?>
    <p>
        Pour exercer vos droits, contactez-nous à :
        <a href="mailto:<?= h($legal['dpo_email'] ?: $legal['email']) ?>"><?= h($legal['dpo_email'] ?: $legal['email']) ?></a>
    </p>
    <?php endif; ?>

    <h2>7. Cookies</h2>
    <p>
        Ce site utilise des cookies pour améliorer votre expérience de navigation. Pour en savoir plus sur notre
        utilisation des cookies, consultez notre <a href="/politique-confidentialite">Politique de Confidentialité</a>.
    </p>

    <h2>8. Limitation de responsabilité</h2>
    <p>
        <?= h($legal['company_name']) ?> s'efforce d'assurer l'exactitude et la mise à jour des informations diffusées
        sur ce site. Toutefois, <?= h($legal['company_name']) ?> ne peut garantir l'exactitude, la précision ou
        l'exhaustivité des informations mises à disposition sur ce site.
    </p>
    <p>
        <?= h($legal['company_name']) ?> décline toute responsabilité pour toute imprécision, inexactitude ou
        omission portant sur des informations disponibles sur ce site.
    </p>

    <h2>9. Droit applicable</h2>
    <p>
        Les présentes mentions légales sont soumises au droit français. En cas de litige, les tribunaux français
        seront seuls compétents.
    </p>
    <?php
    $pageContent = ob_get_clean();
}

// Inclure le template
include __DIR__ . '/../app/templates/legal-layout.php';
