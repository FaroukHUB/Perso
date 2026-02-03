<?php
/**
 * PERSONNALY - Conditions Générales de Vente (COMPLETES)
 * Page générée automatiquement depuis les paramètres admin
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';

$shopSettings = new ShopSettings();
$legal = $shopSettings->getLegalInfo();
$cgv = $shopSettings->getCgvSettings();
$siteName = $shopSettings->getSiteName();
$pageTitle = 'Conditions Générales de Vente';

if (!$shopSettings->areLegalPagesConfigured()) {
    $pageContent = '<p>Les conditions générales de vente seront bientôt disponibles.</p>';
} else {
    ob_start();
    ?>
    <p><em>Date de dernière mise à jour : <?= date('d/m/Y') ?></em></p>

    <h2>Article 1 - Objet et champ d'application</h2>
    <p>
        Les présentes Conditions Générales de Vente (ci-après "CGV") régissent l'ensemble des relations commerciales
        entre <strong><?= h($legal['company_name']) ?></strong> (ci-après "le Vendeur") et toute personne physique
        ou morale effectuant un achat sur le site <strong><?= h($legal['site_url']) ?></strong> (ci-après "le Client").
    </p>
    <p>
        Toute commande passée sur le site implique l'acceptation préalable, pleine et entière des présentes CGV.
        Le Vendeur se réserve le droit de modifier les CGV à tout moment. Les CGV applicables sont celles en vigueur
        à la date de la commande.
    </p>

    <h2>Article 2 - Identité du vendeur</h2>
    <div class="legal-info-box">
        <p>
            <strong><?= h($legal['company_name']) ?></strong><br>
            <?php if ($legal['company_type'] !== 'auto-entrepreneur' && $legal['capital']): ?>
            <?= h(strtoupper($legal['company_type'])) ?> au capital de <?= h($legal['capital']) ?><br>
            <?php elseif ($legal['company_type'] === 'auto-entrepreneur'): ?>
            Auto-entrepreneur<br>
            <?php endif; ?>
            Siège social : <?= h($legal['full_address']) ?><br>
            SIRET : <?= h($legal['siret']) ?><br>
            <?php if ($legal['tva_number']): ?>N° TVA : <?= h($legal['tva_number']) ?><br><?php endif; ?>
            <?php if ($legal['rcs']): ?>RCS : <?= h($legal['rcs']) ?><br><?php endif; ?>
            Email : <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a><br>
            <?php if ($legal['phone']): ?>Téléphone : <?= h($legal['phone']) ?><?php endif; ?>
        </p>
    </div>

    <h2>Article 3 - Produits</h2>
    <h3>3.1 Description des produits</h3>
    <p>
        Les produits proposés à la vente sont décrits et présentés avec la plus grande exactitude possible.
        Toutefois, si des erreurs ou omissions ont pu se produire quant à cette présentation, la responsabilité
        du Vendeur ne pourrait être engagée.
    </p>
    <p>
        Les photographies des produits ne sont pas contractuelles et ne sauraient engager la responsabilité du Vendeur.
        Les couleurs peuvent légèrement différer selon les écrans.
    </p>

    <h3>3.2 Produits personnalisés</h3>
    <p><?= h($cgv['custom_products_policy']) ?></p>

    <h3>3.3 Propriété intellectuelle des contenus fournis</h3>
    <p><?= h($cgv['custom_products_ip']) ?></p>

    <h3>3.4 Contenus interdits</h3>
    <p>Le Vendeur se réserve le droit de refuser toute personnalisation comportant :</p>
    <p><?= h($cgv['prohibited_content']) ?></p>

    <h2>Article 4 - Prix</h2>
    <p>
        Les prix des produits sont indiqués en euros (€) toutes taxes comprises (TTC).
        <?php if (!$legal['tva_number']): ?>
        TVA non applicable, article 293 B du Code Général des Impôts.
        <?php endif; ?>
    </p>
    <p>
        Les frais de livraison ne sont pas inclus dans le prix des produits et sont indiqués avant la validation
        de la commande.
        <?php if ($legal['free_shipping_threshold'] > 0): ?>
        La livraison est offerte à partir de <?= h($legal['free_shipping_threshold']) ?>€ d'achats.
        <?php endif; ?>
    </p>
    <p>
        Le Vendeur se réserve le droit de modifier ses prix à tout moment. Les produits sont facturés
        sur la base des tarifs en vigueur au moment de la validation de la commande.
    </p>

    <h2>Article 5 - Commande</h2>
    <h3>5.1 Processus de commande</h3>
    <p>Le Client passe commande selon le processus suivant :</p>
    <ol>
        <li>Sélection du ou des produits et ajout au panier</li>
        <li>Personnalisation du produit le cas échéant</li>
        <li>Validation du panier</li>
        <li>Identification ou création d'un compte client</li>
        <li>Choix du mode et de l'adresse de livraison</li>
        <li>Choix du mode de paiement</li>
        <li>Vérification et validation finale de la commande</li>
        <li>Paiement</li>
    </ol>

    <h3>5.2 Confirmation de commande</h3>
    <p><?= h($cgv['order_confirmation']) ?></p>

    <h3>5.3 Délai de fabrication</h3>
    <p>
        Pour les produits personnalisés, le délai de fabrication est de <strong><?= h($cgv['production_time']) ?></strong>
        à compter de la validation de la commande. Ce délai s'ajoute au délai de livraison.
    </p>

    <h3>5.4 Modification de commande</h3>
    <p><?= h($cgv['order_modification']) ?></p>

    <h3>5.5 Annulation de commande</h3>
    <p><?= h($cgv['order_cancellation']) ?></p>

    <h2>Article 6 - Paiement</h2>
    <h3>6.1 Moyens de paiement</h3>
    <p>Le paiement peut être effectué par : <?= h($cgv['payment_methods']) ?></p>

    <h3>6.2 Sécurité des paiements</h3>
    <p><?= h($cgv['payment_security']) ?></p>

    <h3>6.3 Débit</h3>
    <p><?= h($cgv['payment_debit_time']) ?></p>

    <?php if ($cgv['payment_installments'] && !empty($cgv['payment_installments_info'])): ?>
    <h3>6.4 Paiement en plusieurs fois</h3>
    <p><?= h($cgv['payment_installments_info']) ?></p>
    <?php endif; ?>

    <p>
        En cas de refus d'autorisation de paiement par carte bancaire de la part des organismes bancaires,
        la commande sera automatiquement annulée et le Client en sera informé par email.
    </p>

    <h2>Article 7 - Livraison</h2>
    <h3>7.1 Zones de livraison</h3>
    <p>Le Vendeur livre dans les zones suivantes : <?= h($cgv['delivery_zones']) ?></p>

    <h3>7.2 Délais de livraison</h3>
    <p>Les délais de livraison sont indicatifs et dépendent du mode de livraison choisi :</p>
    <ul>
        <li><strong>Livraison standard :</strong> <?= h($cgv['delivery_standard_time']) ?></li>
        <li><strong>Livraison express :</strong> <?= h($cgv['delivery_express_time']) ?></li>
    </ul>
    <p>
        Ces délais s'entendent à compter de l'expédition du colis (hors délai de fabrication pour les produits personnalisés).
        Le Vendeur ne saurait être tenu responsable des retards de livraison imputables au transporteur ou à des
        circonstances indépendantes de sa volonté.
    </p>

    <h3>7.3 Transporteurs</h3>
    <p>Les livraisons sont assurées par : <?= h($cgv['delivery_carriers']) ?></p>

    <h3>7.4 Suivi de livraison</h3>
    <p><?= h($cgv['delivery_tracking']) ?></p>

    <?php if ($cgv['delivery_insurance']): ?>
    <h3>7.5 Assurance</h3>
    <p>Tous les colis sont assurés contre la perte et les dommages pendant le transport.</p>
    <?php endif; ?>

    <h3>7.<?= $cgv['delivery_insurance'] ? '6' : '5' ?> Réception</h3>
    <p>
        À la réception du colis, le Client doit vérifier l'état de l'emballage et des produits.
        En cas d'anomalie (colis endommagé, ouvert, produit manquant ou détérioré), le Client doit :
    </p>
    <ul>
        <li>Émettre des réserves écrites sur le bon de livraison du transporteur</li>
        <li>Contacter le Vendeur dans les 48 heures avec photos à l'appui</li>
    </ul>

    <h2>Article 8 - Droit de rétractation</h2>
    <?php if ($legal['returns_days'] > 0): ?>
    <h3>8.1 Délai</h3>
    <p>
        Conformément aux articles L.221-18 et suivants du Code de la consommation, le Client dispose d'un délai
        de <strong><?= h($legal['returns_days']) ?> jours</strong> à compter de la réception du produit pour exercer
        son droit de rétractation, sans avoir à justifier de motifs ni à payer de pénalités.
    </p>

    <h3>8.2 Exceptions</h3>
    <p>
        <strong>Conformément à l'article L.221-28 du Code de la consommation</strong>, le droit de rétractation
        ne peut être exercé pour :
    </p>
    <ul>
        <li>Les produits confectionnés selon les spécifications du Client ou nettement personnalisés</li>
        <li>Les produits qui ont été descellés par le Client après la livraison et qui ne peuvent être renvoyés pour des raisons d'hygiène</li>
    </ul>

    <h3>8.3 Modalités</h3>
    <p>
        Pour exercer son droit de rétractation, le Client doit notifier sa décision par email à
        <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a> en indiquant clairement
        sa volonté de se rétracter.
    </p>
    <p>
        Pour plus de détails sur les conditions et la procédure de retour, consultez notre
        <a href="/politique-retour">Politique de Retour</a>.
    </p>

    <?php if ($legal['returns_free']): ?>
    <h3>8.4 Frais de retour</h3>
    <p>Les frais de retour sont pris en charge par le Vendeur.</p>
    <?php else: ?>
    <h3>8.4 Frais de retour</h3>
    <p>Les frais de retour sont à la charge du Client.</p>
    <?php endif; ?>
    <?php else: ?>
    <p>
        Conformément à l'article L.221-28 du Code de la consommation, le droit de rétractation ne s'applique pas
        aux produits personnalisés proposés sur ce site.
    </p>
    <?php endif; ?>

    <h2>Article 9 - Garanties</h2>
    <h3>9.1 Garantie légale de conformité</h3>
    <p>
        Conformément aux articles L.217-4 et suivants du Code de la consommation, le Vendeur est tenu de livrer
        un bien conforme au contrat et répond des défauts de conformité existant lors de la délivrance.
        Cette garantie s'applique pendant <strong><?= h($cgv['legal_warranty']) ?></strong> à compter de la délivrance du bien.
    </p>

    <h3>9.2 Garantie des vices cachés</h3>
    <p>
        Conformément aux articles 1641 et suivants du Code civil, le Client peut obtenir une réduction du prix
        ou la résolution de la vente en cas de vice caché rendant le produit impropre à l'usage auquel
        il est destiné.
    </p>

    <?php if ($cgv['commercial_warranty']): ?>
    <h3>9.3 Garantie commerciale</h3>
    <p>
        En plus des garanties légales, le Vendeur offre une garantie commerciale de
        <strong><?= h($cgv['commercial_warranty_duration']) ?></strong>.
    </p>
    <?php if (!empty($cgv['commercial_warranty_coverage'])): ?>
    <p>Cette garantie couvre : <?= h($cgv['commercial_warranty_coverage']) ?></p>
    <?php endif; ?>
    <?php endif; ?>

    <h2>Article 10 - Responsabilité</h2>
    <p>
        Le Vendeur ne saurait être tenu responsable de l'inexécution du contrat en cas de force majeure,
        de perturbation ou grève totale ou partielle des services postaux ou moyens de transport,
        ou en cas de rupture de stock.
    </p>
    <p>
        La responsabilité du Vendeur est limitée au montant de la commande et ne saurait être engagée
        pour de simples erreurs ou omissions qui auraient pu subsister malgré toutes les précautions
        prises dans la présentation des produits.
    </p>

    <h2>Article 11 - Données personnelles</h2>
    <p>
        Les données personnelles collectées lors de la commande sont nécessaires au traitement de celle-ci
        et sont traitées conformément au Règlement Général sur la Protection des Données (RGPD).
    </p>
    <p>
        Pour plus d'informations sur la collecte et le traitement de vos données personnelles,
        consultez notre <a href="/politique-confidentialite">Politique de Confidentialité</a>.
    </p>

    <h2>Article 12 - Propriété intellectuelle</h2>
    <p>
        Tous les éléments du site (textes, images, logos, graphismes, vidéos, sons, etc.) sont la propriété
        exclusive du Vendeur ou de ses partenaires et sont protégés par les lois relatives à la propriété
        intellectuelle.
    </p>
    <p>
        Toute reproduction, représentation, modification, publication, ou adaptation de tout ou partie des
        éléments du site est strictement interdite sans autorisation écrite préalable du Vendeur.
    </p>

    <h2>Article 13 - Réclamations et médiation</h2>
    <h3>13.1 Service client</h3>
    <p>
        Pour toute réclamation, le Client peut contacter le service client :
    </p>
    <ul>
        <li>Par email : <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a></li>
        <?php if ($legal['phone']): ?><li>Par téléphone : <?= h($legal['phone']) ?></li><?php endif; ?>
        <li>Par courrier : <?= h($legal['full_address']) ?></li>
    </ul>

    <h3>13.2 Médiation</h3>
    <p>
        Conformément aux dispositions du Code de la consommation concernant le règlement amiable des litiges,
        le Client peut recourir gratuitement au service de médiation proposé par le Vendeur.
    </p>
    <?php if (!empty($cgv['mediator_name'])): ?>
    <div class="legal-info-box">
        <p>
            <strong>Médiateur de la consommation :</strong><br>
            <?= h($cgv['mediator_name']) ?><br>
            <?php if (!empty($cgv['mediator_address'])): ?><?= nl2br(h($cgv['mediator_address'])) ?><br><?php endif; ?>
            <?php if (!empty($cgv['mediator_website'])): ?>Site web : <a href="<?= h($cgv['mediator_website']) ?>" target="_blank"><?= h($cgv['mediator_website']) ?></a><?php endif; ?>
        </p>
    </div>
    <?php else: ?>
    <p>
        Le Client peut également recourir à la plateforme de Règlement en Ligne des Litiges (RLL) de la
        Commission européenne accessible à l'adresse :
        <a href="https://ec.europa.eu/consumers/odr" target="_blank">https://ec.europa.eu/consumers/odr</a>
    </p>
    <?php endif; ?>

    <h2>Article 14 - Droit applicable et juridiction</h2>
    <p>
        Les présentes CGV sont soumises au <?= h($cgv['applicable_law']) ?>.
    </p>
    <p>
        En cas de litige et après tentative de recherche d'une solution amiable, compétence expresse est
        attribuée à <?= h($cgv['competent_court']) ?>, nonobstant pluralité de défendeurs ou appel en garantie.
    </p>

    <h2>Article 15 - Acceptation des CGV</h2>
    <p>
        Le Client reconnaît avoir pris connaissance des présentes CGV avant de passer commande et les accepter
        sans réserve. La validation de la commande vaut acceptation intégrale et sans réserve des présentes CGV.
    </p>
    <?php
    $pageContent = ob_get_clean();
}

include __DIR__ . '/../app/templates/legal-layout.php';
