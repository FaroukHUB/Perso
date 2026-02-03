<?php
/**
 * PERSONNALY - Conditions Générales de Vente
 * Page générée automatiquement depuis les paramètres admin
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';

$shopSettings = new ShopSettings();
$legal = $shopSettings->getLegalInfo();
$siteName = $shopSettings->getSiteName();
$pageTitle = 'Conditions Générales de Vente';

if (!$shopSettings->areLegalPagesConfigured()) {
    $pageContent = '<p>Les conditions générales de vente seront bientôt disponibles.</p>';
} else {
    ob_start();
    ?>
    <h2>Article 1 - Objet</h2>
    <p>
        Les présentes Conditions Générales de Vente (CGV) régissent les relations contractuelles entre
        <strong><?= h($legal['company_name']) ?></strong>, ci-après dénommée "le Vendeur", et toute personne
        physique ou morale souhaitant procéder à un achat via le site internet <strong><?= h($legal['site_url']) ?></strong>,
        ci-après dénommée "le Client".
    </p>
    <p>
        Toute commande passée sur le site implique l'acceptation sans réserve des présentes CGV.
    </p>

    <h2>Article 2 - Identité du vendeur</h2>
    <div class="legal-info-box">
        <p>
            <strong><?= h($legal['company_name']) ?></strong><br>
            <?= h($legal['full_address']) ?><br>
            SIRET : <?= h($legal['siret']) ?><br>
            <?php if ($legal['tva_number']): ?>TVA : <?= h($legal['tva_number']) ?><br><?php endif; ?>
            Email : <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a>
            <?php if ($legal['phone']): ?><br>Tél : <?= h($legal['phone']) ?><?php endif; ?>
        </p>
    </div>

    <h2>Article 3 - Produits et services</h2>
    <p>
        Les produits proposés à la vente sont ceux présentés sur le site au moment de la consultation.
        Les photographies des produits sont aussi fidèles que possible mais ne peuvent assurer une
        similitude parfaite avec le produit réel, notamment en ce qui concerne les couleurs.
    </p>
    <p>
        Les produits personnalisés sont fabriqués selon les spécifications fournies par le Client.
        Le Vendeur ne saurait être tenu responsable des erreurs de personnalisation dues à des
        informations incorrectes fournies par le Client.
    </p>

    <h2>Article 4 - Prix</h2>
    <p>
        Les prix sont indiqués en euros (€) et s'entendent toutes taxes comprises (TTC).
        <?php if (!$legal['tva_number']): ?>
        TVA non applicable, art. 293 B du CGI.
        <?php endif; ?>
    </p>
    <p>
        Les frais de livraison ne sont pas compris dans les prix affichés et sont calculés lors de la
        commande en fonction du mode de livraison choisi.
        <?php if ($legal['free_shipping_threshold'] > 0): ?>
        La livraison est offerte à partir de <?= h($legal['free_shipping_threshold']) ?>€ d'achats.
        <?php endif; ?>
    </p>
    <p>
        Le Vendeur se réserve le droit de modifier ses prix à tout moment. Les produits seront
        facturés sur la base des tarifs en vigueur au moment de la validation de la commande.
    </p>

    <h2>Article 5 - Commande</h2>
    <p>
        Pour passer commande, le Client doit suivre le processus suivant :
    </p>
    <ol>
        <li>Sélection du ou des produits et ajout au panier</li>
        <li>Personnalisation du produit (le cas échéant)</li>
        <li>Validation du panier</li>
        <li>Identification ou création d'un compte client</li>
        <li>Choix du mode de livraison</li>
        <li>Choix du mode de paiement et paiement</li>
        <li>Confirmation de la commande</li>
    </ol>
    <p>
        La commande est définitivement validée après paiement intégral du prix. Un email de confirmation
        est envoyé au Client récapitulant les détails de sa commande.
    </p>

    <h2>Article 6 - Paiement</h2>
    <p>
        Le paiement s'effectue en ligne par carte bancaire (Visa, MasterCard, CB) via la plateforme
        sécurisée Stripe. Toutes les données bancaires sont cryptées et ne transitent pas par nos serveurs.
    </p>
    <p>
        Le débit de la carte est effectué au moment de la validation de la commande. En cas de refus
        d'autorisation de paiement par carte bancaire, la commande sera automatiquement annulée.
    </p>

    <h2>Article 7 - Livraison</h2>
    <p>
        Les produits sont livrés à l'adresse indiquée par le Client lors de la commande.
        Les délais de livraison sont donnés à titre indicatif et dépendent du transporteur choisi.
    </p>
    <p>
        En cas de retard de livraison, le Vendeur en informera le Client dans les meilleurs délais.
        Un retard de livraison ne peut donner lieu à aucune pénalité ou annulation de commande,
        sauf retard excessif (supérieur à 30 jours).
    </p>
    <p>
        À réception, le Client doit vérifier l'état du colis et signaler toute anomalie
        (colis endommagé, ouvert, etc.) au transporteur.
    </p>

    <h2>Article 8 - Droit de rétractation</h2>
    <?php if ($legal['returns_days'] > 0): ?>
    <p>
        Conformément à l'article L.221-18 du Code de la consommation, le Client dispose d'un délai
        de <strong><?= h($legal['returns_days']) ?> jours</strong> à compter de la réception du produit pour
        exercer son droit de rétractation, sans avoir à justifier de motifs ni à payer de pénalités.
    </p>
    <p>
        <strong>Exception :</strong> Conformément à l'article L.221-28 du Code de la consommation,
        le droit de rétractation ne s'applique pas aux produits personnalisés ou confectionnés selon
        les spécifications du Client.
    </p>
    <?php if ($legal['returns_free']): ?>
    <p>
        Les frais de retour sont à la charge du Vendeur.
    </p>
    <?php else: ?>
    <p>
        Les frais de retour sont à la charge du Client.
    </p>
    <?php endif; ?>
    <p>
        Pour exercer ce droit, le Client doit notifier sa décision par email à
        <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a>.
        Pour plus d'informations, consultez notre <a href="/politique-retour">Politique de Retour</a>.
    </p>
    <?php else: ?>
    <p>
        Compte tenu de la nature personnalisée des produits vendus, le droit de rétractation
        ne s'applique pas conformément à l'article L.221-28 du Code de la consommation.
    </p>
    <?php endif; ?>

    <h2>Article 9 - Garanties</h2>
    <p>
        Tous les produits vendus bénéficient de la garantie légale de conformité (articles L.217-4
        et suivants du Code de la consommation) et de la garantie contre les vices cachés
        (articles 1641 et suivants du Code civil).
    </p>
    <p>
        En cas de produit non conforme ou défectueux, le Client peut contacter le service client
        pour obtenir un échange ou un remboursement.
    </p>

    <h2>Article 10 - Responsabilité</h2>
    <p>
        Le Vendeur ne saurait être tenu responsable des dommages résultant d'une mauvaise
        utilisation du produit acheté. Sa responsabilité ne pourra être engagée pour un
        dommage résultant du fait d'un tiers ou de la faute du Client.
    </p>

    <h2>Article 11 - Données personnelles</h2>
    <p>
        Les informations recueillies lors de la commande sont nécessaires au traitement de
        celle-ci. Elles sont traitées conformément au RGPD.
        Pour plus d'informations, consultez notre
        <a href="/politique-confidentialite">Politique de Confidentialité</a>.
    </p>

    <h2>Article 12 - Propriété intellectuelle</h2>
    <p>
        Tous les éléments du site (textes, images, logos, etc.) sont protégés par le droit
        d'auteur. Toute reproduction est interdite sans autorisation préalable.
    </p>
    <p>
        Les créations personnalisées réalisées à la demande du Client restent la propriété
        intellectuelle de ce dernier concernant les éléments qu'il a fournis.
    </p>

    <h2>Article 13 - Droit applicable et litiges</h2>
    <p>
        Les présentes CGV sont soumises au droit français. En cas de litige, une solution
        amiable sera recherchée avant toute action judiciaire.
    </p>
    <p>
        Conformément aux articles L.616-1 et R.616-1 du Code de la consommation, le Client
        peut recourir gratuitement à un médiateur de la consommation en vue de la résolution
        amiable du litige.
    </p>
    <p>
        À défaut de résolution amiable, les tribunaux français seront seuls compétents.
    </p>

    <h2>Article 14 - Service client</h2>
    <p>
        Pour toute question ou réclamation, le Client peut contacter le service client :
    </p>
    <ul>
        <li>Par email : <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a></li>
        <?php if ($legal['phone']): ?><li>Par téléphone : <?= h($legal['phone']) ?></li><?php endif; ?>
    </ul>
    <?php
    $pageContent = ob_get_clean();
}

include __DIR__ . '/../app/templates/legal-layout.php';
