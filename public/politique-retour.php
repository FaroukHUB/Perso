<?php
/**
 * PERSONNALY - Politique de Retour (COMPLETE)
 * Page générée automatiquement depuis les paramètres admin
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';

$shopSettings = new ShopSettings();
$legal = $shopSettings->getLegalInfo();
$return = $shopSettings->getReturnPolicySettings();
$siteName = $shopSettings->getSiteName();
$pageTitle = 'Politique de Retour';

if (!$shopSettings->areLegalPagesConfigured()) {
    $pageContent = '<p>La politique de retour sera bientôt disponible.</p>';
} else {
    ob_start();
    ?>
    <h2>Notre engagement</h2>
    <p>
        Chez <strong><?= h($legal['site_name']) ?></strong>, votre satisfaction est notre priorité absolue.
        Nous mettons tout en œuvre pour que votre expérience d'achat soit parfaite, de la commande à la réception.
        Si toutefois un produit ne correspondait pas à vos attentes, nous sommes là pour vous accompagner.
    </p>

    <!-- Résumé rapide -->
    <div class="legal-info-box">
        <p>
            <?php if ($legal['returns_days'] > 0): ?>
            <strong>✓ <?= h($legal['returns_days']) ?> jours</strong> pour changer d'avis<br>
            <?php endif; ?>
            <?php if ($legal['returns_free']): ?>
            <strong>✓ Retours gratuits</strong> - nous prenons en charge les frais<br>
            <?php endif; ?>
            <?php if ($return['label_provided']): ?>
            <strong>✓ Étiquette de retour fournie</strong><br>
            <?php endif; ?>
            <strong>✓ Remboursement sous <?= h($return['refund_delay']) ?></strong>
        </p>
    </div>

    <h2>1. Droit de rétractation</h2>
    <?php if ($legal['returns_days'] > 0): ?>
    <p>
        Conformément aux articles L.221-18 et suivants du Code de la consommation, vous disposez d'un délai de
        <strong><?= h($legal['returns_days']) ?> jours</strong> à compter de la réception de votre commande pour
        nous notifier votre souhait de vous rétracter, sans avoir à justifier de motifs.
    </p>
    <?php else: ?>
    <p>
        Compte tenu de la nature personnalisée de nos produits, le délai de rétractation légal de 14 jours
        ne s'applique pas conformément à l'article L.221-28 du Code de la consommation.
    </p>
    <?php endif; ?>

    <h2>2. Produits concernés</h2>

    <?php if ($return['standard_products']): ?>
    <h3>2.1 Produits standards (non personnalisés)</h3>
    <p>
        Les produits standards peuvent être retournés dans le délai de rétractation à condition de respecter
        les conditions ci-dessous.
    </p>
    <?php endif; ?>

    <h3>2.<?= $return['standard_products'] ? '2' : '1' ?> Produits personnalisés</h3>
    <?php if ($return['custom_products']): ?>
    <p>
        Les produits personnalisés peuvent également être retournés sous certaines conditions spécifiques.
        Contactez notre service client pour étudier votre demande.
    </p>
    <?php else: ?>
    <p><?= h($return['custom_exception']) ?></p>
    <p>
        <strong>Exceptions :</strong> Si vous recevez un produit personnalisé présentant un défaut de fabrication
        (erreur de notre part dans l'impression, défaut du textile, etc.), nous procéderons bien entendu à son
        remplacement ou à son remboursement.
    </p>
    <?php endif; ?>

    <h2>3. Conditions d'acceptation du retour</h2>
    <p>Pour être accepté, votre retour doit respecter les conditions suivantes :</p>
    <ul>
        <li>
            <strong>État du produit :</strong> <?= h($return['product_condition']) ?>
        </li>
        <?php if ($return['original_packaging']): ?>
        <li>
            <strong>Emballage :</strong> Le produit doit être retourné dans son emballage d'origine
        </li>
        <?php endif; ?>
        <li>
            <strong>Complétude :</strong> <?= h($return['complete_product']) ?>
        </li>
        <li>
            <strong>Délai :</strong> Le retour doit être effectué dans les <?= h($legal['returns_days']) ?> jours
            suivant la réception
        </li>
    </ul>

    <div class="legal-info-box">
        <p>
            <strong>⚠️ Important :</strong> Tout produit retourné incomplet, endommagé (hors défaut à la réception),
            porté, lavé ou sans ses étiquettes pourra faire l'objet d'un refus de remboursement ou d'un
            remboursement partiel.
        </p>
    </div>

    <h2>4. Comment effectuer un retour ?</h2>
    <h3>Étape 1 : Demandez votre retour</h3>
    <p><?= h($return['request_info']) ?></p>

    <?php
    $contactMethod = $return['request_method'];
    switch ($contactMethod) {
        case 'email':
            echo '<p>Contactez-nous par email : <a href="mailto:' . h($legal['email']) . '">' . h($legal['email']) . '</a></p>';
            break;
        case 'formulaire':
            echo '<p>Utilisez le formulaire de retour disponible dans votre espace client.</p>';
            break;
        case 'compte':
            echo '<p>Connectez-vous à votre espace client et accédez à la section "Mes commandes" pour initier un retour.</p>';
            break;
        case 'telephone':
            if ($legal['phone']) {
                echo '<p>Appelez notre service client au ' . h($legal['phone']) . '</p>';
            }
            break;
    }
    ?>

    <h3>Étape 2 : Préparez votre colis</h3>
    <ul>
        <li>Emballez soigneusement le(s) article(s) dans l'emballage d'origine si possible</li>
        <li>Joignez une copie de votre facture ou bon de commande</li>
        <li>Indiquez clairement le motif de votre retour</li>
    </ul>

    <?php if ($return['label_provided']): ?>
    <h3>Étape 3 : Utilisez l'étiquette de retour</h3>
    <p>
        Nous vous fournirons une étiquette de retour prépayée. Imprimez-la et collez-la sur votre colis.
        <?php if ($legal['returns_free']): ?>
        Les frais de retour sont à notre charge.
        <?php endif; ?>
    </p>
    <?php else: ?>
    <h3>Étape 3 : Expédiez votre colis</h3>
    <p>
        Expédiez votre colis à l'adresse indiquée. Nous vous recommandons d'utiliser un service avec suivi.
        Les frais de retour sont à votre charge.
    </p>
    <?php endif; ?>

    <h3>Étape 4 : Déposez votre colis</h3>
    <p>Points de dépôt disponibles : <?= h($return['drop_points']) ?></p>

    <?php if (!empty($return['address'])): ?>
    <h3>Adresse de retour</h3>
    <div class="legal-info-box">
        <p><?= nl2br(h($return['address'])) ?></p>
    </div>
    <?php else: ?>
    <h3>Adresse de retour</h3>
    <div class="legal-info-box">
        <p>
            <?= h($legal['company_name']) ?><br>
            Service Retours<br>
            <?= h($legal['full_address']) ?>
        </p>
    </div>
    <?php endif; ?>

    <h2>5. Remboursement</h2>
    <h3>5.1 Délai de remboursement</h3>
    <p>
        Une fois votre retour reçu et vérifié par nos équipes, nous procéderons au remboursement dans un délai
        maximum de <strong><?= h($return['refund_delay']) ?></strong>.
    </p>

    <h3>5.2 Mode de remboursement</h3>
    <p><?= h($return['refund_method']) ?></p>

    <h3>5.3 Frais de livraison</h3>
    <p><?= h($return['shipping_refund']) ?></p>

    <h3>5.4 Remboursement partiel</h3>
    <p><?= h($return['partial_refund']) ?></p>

    <?php if ($return['exchange_available']): ?>
    <h2>6. Échange</h2>
    <p><?= h($return['exchange_info']) ?></p>

    <h3>Échange de taille</h3>
    <p><?= h($return['size_exchange']) ?></p>
    <?php endif; ?>

    <h2><?= $return['exchange_available'] ? '7' : '6' ?>. Produit défectueux ou erreur de livraison</h2>

    <h3>Produit défectueux</h3>
    <p><?= h($return['defective_policy']) ?></p>

    <p><strong>Éléments à fournir :</strong> <?= h($return['defective_evidence']) ?></p>

    <p><strong>Délai de signalement :</strong> <?= h($return['defective_delay']) ?> après réception</p>

    <p><strong>Résolution :</strong> <?= h($return['defective_resolution']) ?></p>

    <h3>Erreur de livraison</h3>
    <p><?= h($return['wrong_item_policy']) ?></p>

    <h2><?= $return['exchange_available'] ? '8' : '7' ?>. Garanties légales</h2>
    <p>
        Indépendamment du droit de rétractation, vous bénéficiez des garanties légales suivantes :
    </p>
    <ul>
        <li>
            <strong>Garantie légale de conformité</strong> (articles L.217-4 à L.217-14 du Code de la consommation) :
            pendant 2 ans à compter de la délivrance du bien
        </li>
        <li>
            <strong>Garantie contre les vices cachés</strong> (articles 1641 à 1649 du Code civil) :
            vous pouvez demander la résolution de la vente ou une réduction du prix
        </li>
    </ul>

    <h2><?= $return['exchange_available'] ? '9' : '8' ?>. Contact</h2>
    <p>
        Notre équipe est à votre disposition pour toute question concernant votre retour :
    </p>
    <ul>
        <li><strong>Email :</strong> <a href="mailto:<?= h($legal['email']) ?>"><?= h($legal['email']) ?></a></li>
        <?php if ($legal['phone']): ?>
        <li><strong>Téléphone :</strong> <?= h($legal['phone']) ?></li>
        <?php endif; ?>
    </ul>
    <p>
        Nous nous engageons à répondre à toutes vos demandes dans un délai de 48h ouvrées.
    </p>

    <div class="legal-info-box" style="margin-top: 30px;">
        <p>
            <strong>📄 Documents associés</strong><br>
            • <a href="/cgv">Conditions Générales de Vente</a><br>
            • <a href="/mentions-legales">Mentions Légales</a><br>
            • <a href="/politique-confidentialite">Politique de Confidentialité</a>
        </p>
    </div>
    <?php
    $pageContent = ob_get_clean();
}

include __DIR__ . '/../app/templates/legal-layout.php';
