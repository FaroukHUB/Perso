<?php
/**
 * PERSONNALY - Politique de Retour
 * Page générée automatiquement depuis les paramètres admin
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';

$shopSettings = new ShopSettings();
$legal = $shopSettings->getLegalInfo();
$siteName = $shopSettings->getSiteName();
$pageTitle = 'Politique de Retour';

if (!$shopSettings->areLegalPagesConfigured()) {
    $pageContent = '<p>La politique de retour sera bientôt disponible.</p>';
} else {
    ob_start();
    ?>
    <h2>Notre engagement</h2>
    <p>
        Chez <strong><?= h($legal['site_name']) ?></strong>, votre satisfaction est notre priorité.
        Si un produit ne vous convient pas, nous mettons tout en œuvre pour vous offrir une
        expérience de retour simple et transparente.
    </p>

    <?php if ($legal['returns_days'] > 0): ?>
    <div class="legal-info-box">
        <p>
            <strong>✓ <?= h($legal['returns_days']) ?> jours</strong> pour changer d'avis<br>
            <?php if ($legal['returns_free']): ?>
            <strong>✓ Retours gratuits</strong> - nous prenons en charge les frais de retour<br>
            <?php endif; ?>
            <strong>✓ Remboursement rapide</strong> - sous 14 jours après réception
        </p>
    </div>

    <h2>Délai de rétractation</h2>
    <p>
        Conformément à la législation française, vous disposez d'un délai de <strong><?= h($legal['returns_days']) ?> jours</strong>
        à compter de la réception de votre commande pour exercer votre droit de rétractation,
        sans avoir à justifier de motifs.
    </p>

    <h2>Conditions de retour</h2>
    <p>Pour être accepté, votre retour doit respecter les conditions suivantes :</p>
    <ul>
        <li>Le produit doit être retourné dans son <strong>état d'origine</strong></li>
        <li>Le produit ne doit pas avoir été porté, lavé ou altéré</li>
        <li>Les <strong>étiquettes</strong> doivent être intactes et toujours attachées</li>
        <li>Le produit doit être retourné dans son <strong>emballage d'origine</strong></li>
    </ul>

    <h3>Produits non retournables</h3>
    <p>
        <strong>Important :</strong> Les <strong>produits personnalisés</strong> ou confectionnés selon
        vos spécifications ne peuvent pas faire l'objet d'un retour, sauf en cas de défaut de fabrication.
        Cette exception est conforme à l'article L.221-28 du Code de la consommation.
    </p>

    <h2>Comment retourner un produit ?</h2>
    <ol>
        <li>
            <strong>Contactez-nous</strong> par email à
            <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a>
            en indiquant votre numéro de commande et le(s) article(s) à retourner
        </li>
        <li>
            <strong>Recevez votre bon de retour</strong> - nous vous enverrons par email les instructions
            et l'étiquette de retour prépayée
            <?php if (!$legal['returns_free']): ?>(frais à votre charge)<?php endif; ?>
        </li>
        <li>
            <strong>Emballez soigneusement</strong> le(s) produit(s) dans leur emballage d'origine
        </li>
        <li>
            <strong>Déposez votre colis</strong> au point relais ou bureau de poste indiqué
        </li>
        <li>
            <strong>Suivez votre remboursement</strong> - nous vous notifions dès réception et traitement
        </li>
    </ol>

    <h2>Remboursement</h2>
    <p>
        Une fois votre retour reçu et vérifié, nous procéderons au remboursement dans un délai
        maximum de <strong>14 jours</strong>.
    </p>
    <p>
        Le remboursement sera effectué sur le même moyen de paiement que celui utilisé lors de
        votre commande (carte bancaire).
    </p>
    <p>
        <strong>Note :</strong> Les frais de livraison initiaux sont remboursés uniquement en cas
        de retour de la totalité de la commande.
    </p>

    <?php if ($legal['returns_free']): ?>
    <h2>Frais de retour</h2>
    <p>
        Bonne nouvelle ! <strong>Les frais de retour sont à notre charge.</strong>
        Utilisez simplement l'étiquette de retour prépayée que nous vous fournissons.
    </p>
    <?php else: ?>
    <h2>Frais de retour</h2>
    <p>
        Les frais de retour sont à la charge du client. Nous vous recommandons d'utiliser
        un service de livraison avec suivi pour garantir la bonne réception de votre colis.
    </p>
    <?php endif; ?>

    <?php else: ?>
    <div class="legal-info-box">
        <p>
            <strong>Information importante</strong><br>
            Compte tenu de la nature personnalisée de nos produits, le droit de rétractation
            ne s'applique pas conformément à l'article L.221-28 du Code de la consommation.
        </p>
    </div>
    <?php endif; ?>

    <h2>Produit défectueux ou erreur de livraison</h2>
    <p>
        Si vous recevez un produit défectueux ou si votre commande contient une erreur,
        contactez-nous immédiatement. Nous organiserons le retour à nos frais et procéderons
        à l'échange ou au remboursement intégral.
    </p>
    <p>
        Conservez tous les éléments (emballage, étiquettes) et prenez des photos du défaut
        pour faciliter le traitement de votre demande.
    </p>

    <h2>Garantie légale</h2>
    <p>
        Indépendamment du droit de rétractation, vous bénéficiez de la <strong>garantie légale
        de conformité</strong> (2 ans) et de la <strong>garantie contre les vices cachés</strong>.
    </p>
    <p>
        Ces garanties vous permettent d'obtenir la réparation ou le remplacement du produit,
        ou à défaut, le remboursement.
    </p>

    <h2>Contact</h2>
    <p>
        Une question sur notre politique de retour ? Notre équipe est là pour vous aider :
    </p>
    <ul>
        <li>Email : <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a></li>
        <?php if ($legal['phone']): ?><li>Téléphone : <?= h($legal['phone']) ?></li><?php endif; ?>
    </ul>
    <p>
        Nous nous engageons à répondre à toutes vos demandes dans un délai de 48h ouvrées.
    </p>
    <?php
    $pageContent = ob_get_clean();
}

include __DIR__ . '/../app/templates/legal-layout.php';
