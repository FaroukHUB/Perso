<?php
/**
 * PERSONNALY - Politique de Confidentialité
 * Page générée automatiquement depuis les paramètres admin
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';

$shopSettings = new ShopSettings();
$legal = $shopSettings->getLegalInfo();
$siteName = $shopSettings->getSiteName();
$pageTitle = 'Politique de Confidentialité';

if (!$shopSettings->areLegalPagesConfigured()) {
    $pageContent = '<p>La politique de confidentialité sera bientôt disponible.</p>';
} else {
    ob_start();
    ?>
    <h2>1. Introduction</h2>
    <p>
        La présente Politique de Confidentialité décrit la manière dont <strong><?= h($legal['company_name']) ?></strong>
        collecte, utilise et protège les données personnelles des utilisateurs du site <strong><?= h($legal['site_url']) ?></strong>.
    </p>
    <p>
        Nous nous engageons à protéger votre vie privée et à traiter vos données personnelles conformément
        au Règlement Général sur la Protection des Données (RGPD) et à la loi Informatique et Libertés.
    </p>

    <h2>2. Responsable du traitement</h2>
    <div class="legal-info-box">
        <p>
            <strong><?= h($legal['company_name']) ?></strong><br>
            <?= h($legal['full_address']) ?><br>
            Email : <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a>
            <?php if ($legal['dpo_name'] || $legal['dpo_email']): ?>
            <br><br>
            <strong>Délégué à la Protection des Données (DPO) :</strong><br>
            <?php if ($legal['dpo_name']): ?><?= h($legal['dpo_name']) ?><br><?php endif; ?>
            <?php if ($legal['dpo_email']): ?>Email : <a href="mailto:<?= h($legal['dpo_email']) ?>"><?= h($legal['dpo_email']) ?></a><?php endif; ?>
            <?php endif; ?>
        </p>
    </div>

    <h2>3. Données collectées</h2>
    <p>Nous collectons les données personnelles suivantes :</p>
    <ul>
        <?php
        $dataItems = array_map('trim', explode(',', $legal['data_collected']));
        foreach ($dataItems as $item):
            if (!empty($item)):
        ?>
        <li><?= h(ucfirst($item)) ?></li>
        <?php
            endif;
        endforeach;
        ?>
    </ul>

    <h3>Données collectées automatiquement</h3>
    <p>
        Lors de votre navigation sur notre site, nous collectons automatiquement certaines informations
        techniques : adresse IP, type de navigateur, pages visitées, durée des visites.
    </p>

    <h2>4. Finalités du traitement</h2>
    <p>Vos données personnelles sont collectées pour les finalités suivantes :</p>
    <ul>
        <?php
        $purposes = array_map('trim', explode(',', $legal['data_purpose']));
        foreach ($purposes as $purpose):
            if (!empty($purpose)):
        ?>
        <li><?= h(ucfirst($purpose)) ?></li>
        <?php
            endif;
        endforeach;
        ?>
    </ul>

    <h2>5. Base légale du traitement</h2>
    <p>Le traitement de vos données repose sur les bases légales suivantes :</p>
    <ul>
        <li><strong>Exécution du contrat :</strong> pour le traitement et la livraison de vos commandes</li>
        <li><strong>Obligations légales :</strong> pour la facturation et la comptabilité</li>
        <li><strong>Intérêt légitime :</strong> pour améliorer nos services et la sécurité du site</li>
        <li><strong>Consentement :</strong> pour l'envoi de newsletters et communications marketing</li>
    </ul>

    <h2>6. Durée de conservation</h2>
    <p>
        Vos données personnelles sont conservées pendant une durée de <strong><?= h($legal['data_retention']) ?></strong>.
    </p>
    <p>
        Les données de facturation sont conservées pendant 10 ans conformément aux obligations légales.
    </p>

    <h2>7. Destinataires des données</h2>
    <p>Vos données peuvent être transmises aux destinataires suivants :</p>
    <ul>
        <li><strong>Services internes :</strong> service client, logistique, comptabilité</li>
        <li><strong>Prestataires de paiement :</strong> Stripe (traitement des paiements sécurisés)</li>
        <li><strong>Transporteurs :</strong> pour la livraison de vos commandes</li>
        <li><strong>Hébergeur :</strong> <?= h($legal['host_name']) ?> (hébergement du site)</li>
    </ul>
    <p>
        Ces prestataires n'ont accès qu'aux données strictement nécessaires à l'exécution de leurs services
        et s'engagent à respecter la confidentialité de vos données.
    </p>

    <h2>8. Transferts hors UE</h2>
    <p>
        Certains de nos prestataires peuvent être situés hors de l'Union Européenne.
        Dans ce cas, nous nous assurons que des garanties appropriées sont mises en place
        (clauses contractuelles types, certifications, etc.) pour protéger vos données.
    </p>

    <h2>9. Cookies</h2>
    <p>Notre site utilise des cookies pour :</p>
    <ul>
        <?php
        $cookies = array_map('trim', explode(',', $legal['cookies_used']));
        foreach ($cookies as $cookie):
            if (!empty($cookie)):
        ?>
        <li><?= h(ucfirst($cookie)) ?></li>
        <?php
            endif;
        endforeach;
        ?>
    </ul>

    <h3>Types de cookies utilisés</h3>
    <ul>
        <li><strong>Cookies essentiels :</strong> nécessaires au fonctionnement du site (session, panier)</li>
        <li><strong>Cookies de performance :</strong> pour analyser l'utilisation du site (avec consentement)</li>
        <li><strong>Cookies marketing :</strong> pour personnaliser les publicités (avec consentement)</li>
    </ul>

    <p>
        Vous pouvez gérer vos préférences de cookies à tout moment via les paramètres de votre navigateur
        ou notre bandeau de consentement.
    </p>

    <h2>10. Vos droits</h2>
    <p>Conformément au RGPD, vous disposez des droits suivants :</p>
    <ul>
        <li><strong>Droit d'accès :</strong> obtenir une copie de vos données personnelles</li>
        <li><strong>Droit de rectification :</strong> corriger des données inexactes ou incomplètes</li>
        <li><strong>Droit à l'effacement :</strong> demander la suppression de vos données</li>
        <li><strong>Droit à la limitation :</strong> restreindre le traitement de vos données</li>
        <li><strong>Droit à la portabilité :</strong> recevoir vos données dans un format structuré</li>
        <li><strong>Droit d'opposition :</strong> vous opposer au traitement de vos données</li>
        <li><strong>Droit de retrait du consentement :</strong> retirer votre consentement à tout moment</li>
    </ul>

    <h3>Comment exercer vos droits ?</h3>
    <p>
        Pour exercer vos droits, envoyez-nous un email à
        <a href="mailto:<?= h($legal['dpo_email'] ?: $legal['email']) ?>"><?= h($legal['dpo_email'] ?: $legal['email']) ?></a>
        en précisant votre demande et en joignant une copie de votre pièce d'identité.
    </p>
    <p>
        Nous nous engageons à répondre à votre demande dans un délai d'un mois.
    </p>

    <h2>11. Sécurité des données</h2>
    <p>
        Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger
        vos données personnelles contre tout accès non autorisé, modification, divulgation ou destruction :
    </p>
    <ul>
        <li>Chiffrement SSL/TLS des communications</li>
        <li>Stockage sécurisé des données</li>
        <li>Accès restreint aux données personnelles</li>
        <li>Formation du personnel à la protection des données</li>
    </ul>

    <h2>12. Réclamation</h2>
    <p>
        Si vous estimez que le traitement de vos données personnelles constitue une violation du RGPD,
        vous avez le droit d'introduire une réclamation auprès de la CNIL :
    </p>
    <div class="legal-info-box">
        <p>
            <strong>Commission Nationale de l'Informatique et des Libertés (CNIL)</strong><br>
            3 Place de Fontenoy, TSA 80715<br>
            75334 Paris Cedex 07<br>
            Site web : <a href="https://www.cnil.fr" target="_blank">www.cnil.fr</a>
        </p>
    </div>

    <h2>13. Modifications</h2>
    <p>
        Nous nous réservons le droit de modifier cette politique de confidentialité à tout moment.
        Les modifications prendront effet dès leur publication sur le site.
        Nous vous encourageons à consulter régulièrement cette page.
    </p>

    <h2>14. Contact</h2>
    <p>
        Pour toute question concernant cette politique de confidentialité ou le traitement de vos données,
        contactez-nous à : <a href="mailto:<?= h($legal['dpo_email'] ?: $legal['email']) ?>"><?= h($legal['dpo_email'] ?: $legal['email']) ?></a>
    </p>
    <?php
    $pageContent = ob_get_clean();
}

include __DIR__ . '/../app/templates/legal-layout.php';
