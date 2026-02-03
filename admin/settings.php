<?php
/**
 * PERSONNALY - Admin : Paramètres
 * Configuration compte, paiement (Stripe), livraison (Boxtal)
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/Settings.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';

Auth::requireAdmin();

$shopSettings = new ShopSettings();

$orderModel = new Order();
$settingsModel = new Settings();
$pendingOrders = $orderModel->countNew();
$user = Auth::getUser();

$success = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'account';

// Traitement des formulaires
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {

    // Changement de mot de passe
    if (isset($_POST['change_password'])) {
        $current = post('current_password', '');
        $new = post('new_password', '');
        $confirm = post('confirm_password', '');

        if (empty($current) || empty($new)) {
            $error = 'Veuillez remplir tous les champs.';
        } elseif ($new !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($new) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();

            if (Auth::verifyPassword($current, $userData['password_hash'])) {
                $newHash = Auth::hashPassword($new);
                $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([$newHash, $user['id']]);
                $success = 'Mot de passe mis à jour avec succès.';
            } else {
                $error = 'Mot de passe actuel incorrect.';
            }
        }
        $activeTab = 'account';
    }

    // Paramètres Stripe
    if (isset($_POST['save_stripe'])) {
        $settingsModel->setMultiple([
            'stripe_enabled' => post('stripe_enabled', '0'),
            'stripe_mode' => post('stripe_mode', 'test'),
            'stripe_test_public_key' => post('stripe_test_public_key', ''),
            'stripe_test_secret_key' => post('stripe_test_secret_key', ''),
            'stripe_live_public_key' => post('stripe_live_public_key', ''),
            'stripe_live_secret_key' => post('stripe_live_secret_key', ''),
            'stripe_webhook_secret' => post('stripe_webhook_secret', '')
        ]);
        $success = 'Paramètres Stripe enregistrés.';
        $activeTab = 'payment';
    }

    // Paramètres Boxtal / Livraison
    if (isset($_POST['save_shipping'])) {
        $settingsModel->setMultiple([
            'boxtal_enabled' => post('boxtal_enabled', '0'),
            'boxtal_mode' => post('boxtal_mode', 'test'),
            'boxtal_user' => post('boxtal_user', ''),
            'boxtal_api_key' => post('boxtal_api_key', ''),
            'boxtal_default_weight' => post('boxtal_default_weight', '500'),
            'shipper_company' => post('shipper_company', ''),
            'shipper_address' => post('shipper_address', ''),
            'shipper_city' => post('shipper_city', ''),
            'shipper_postcode' => post('shipper_postcode', ''),
            'shipper_country' => post('shipper_country', 'FR'),
            'shipper_phone' => post('shipper_phone', ''),
            'shipper_email' => post('shipper_email', ''),
            'shipping_free_threshold' => post('shipping_free_threshold', '50'),
            'shipping_standard_price' => post('shipping_standard_price', '4.90'),
            'shipping_express_price' => post('shipping_express_price', '9.90')
        ]);
        $success = 'Paramètres de livraison enregistrés.';
        $activeTab = 'shipping';
    }

    // Paramètres Marketing (Brevo + WhatsApp)
    if (isset($_POST['save_marketing'])) {
        $settingsModel->setMultiple([
            // Brevo
            'brevo_enabled' => post('brevo_enabled', '0'),
            'brevo_api_key' => post('brevo_api_key', ''),
            'brevo_sender_name' => post('brevo_sender_name', ''),
            'brevo_sender_email' => post('brevo_sender_email', ''),
            'brevo_daily_limit' => post('brevo_daily_limit', '300'),
            // WhatsApp
            'whatsapp_enabled' => post('whatsapp_enabled', '0'),
            'whatsapp_phone_id' => post('whatsapp_phone_id', ''),
            'whatsapp_access_token' => post('whatsapp_access_token', ''),
            'whatsapp_business_id' => post('whatsapp_business_id', ''),
            'whatsapp_verify_token' => post('whatsapp_verify_token', ''),
            'whatsapp_monthly_limit' => post('whatsapp_monthly_limit', '1000')
        ]);
        $success = 'Paramètres marketing enregistrés.';
        $activeTab = 'marketing';
    }

    // Paramètres Boutique (général)
    if (isset($_POST['save_shop_general'])) {
        $shopSettings->setMultiple([
            'site_name' => post('site_name', 'PERSONNALY'),
            'site_description' => post('site_description', ''),
            'contact_email' => post('contact_email', ''),
            'contact_phone' => post('contact_phone', ''),
            'copyright_text' => post('copyright_text', '')
        ]);
        $shopSettings->clearCache();
        $success = 'Paramètres généraux enregistrés.';
        $activeTab = 'shop';
    }

    // Paramètres Livraison boutique
    if (isset($_POST['save_shop_shipping'])) {
        $shopSettings->setMultiple([
            'free_shipping_enabled' => isset($_POST['free_shipping_enabled']) ? '1' : '0',
            'free_shipping_threshold' => post('free_shipping_threshold', '50'),
            'shipping_default_country' => post('shipping_default_country', 'FR'),
            'shipping_default_city' => post('shipping_default_city', 'Paris'),
            'shipping_default_postcode' => post('shipping_default_postcode', '75001'),
            'shipping_fallback_standard_price' => post('shipping_fallback_standard_price', '4.90'),
            'shipping_fallback_standard_label' => post('shipping_fallback_standard_label', 'Livraison standard'),
            'shipping_fallback_standard_delay' => post('shipping_fallback_standard_delay', '3-5 jours ouvrés'),
            'shipping_fallback_express_price' => post('shipping_fallback_express_price', '9.90'),
            'shipping_fallback_express_label' => post('shipping_fallback_express_label', 'Livraison express'),
            'shipping_fallback_express_delay' => post('shipping_fallback_express_delay', '24-48h')
        ]);
        $shopSettings->clearCache();
        $success = 'Paramètres de livraison enregistrés.';
        $activeTab = 'shop_shipping';
    }

    // Paramètres Retours
    if (isset($_POST['save_shop_returns'])) {
        $shopSettings->setMultiple([
            'returns_enabled' => isset($_POST['returns_enabled']) ? '1' : '0',
            'returns_days' => post('returns_days', '14'),
            'returns_free' => isset($_POST['returns_free']) ? '1' : '0',
            'returns_conditions' => post('returns_conditions', '')
        ]);
        $shopSettings->clearCache();
        $success = 'Paramètres de retours enregistrés.';
        $activeTab = 'shop_returns';
    }

    // Paramètres Paiements affichés
    if (isset($_POST['save_shop_payments'])) {
        $shopSettings->setMultiple([
            'payment_visa_enabled' => isset($_POST['payment_visa_enabled']) ? '1' : '0',
            'payment_mastercard_enabled' => isset($_POST['payment_mastercard_enabled']) ? '1' : '0',
            'payment_amex_enabled' => isset($_POST['payment_amex_enabled']) ? '1' : '0',
            'payment_cb_enabled' => isset($_POST['payment_cb_enabled']) ? '1' : '0',
            'payment_paypal_enabled' => isset($_POST['payment_paypal_enabled']) ? '1' : '0',
            'payment_apple_pay_enabled' => isset($_POST['payment_apple_pay_enabled']) ? '1' : '0',
            'payment_google_pay_enabled' => isset($_POST['payment_google_pay_enabled']) ? '1' : '0'
        ]);
        $shopSettings->clearCache();
        $success = 'Moyens de paiement enregistrés.';
        $activeTab = 'shop_payments';
    }

    // Paramètres Trust Badges
    if (isset($_POST['save_shop_badges'])) {
        $shopSettings->setMultiple([
            'trust_badge_1_enabled' => isset($_POST['trust_badge_1_enabled']) ? '1' : '0',
            'trust_badge_1_icon' => post('trust_badge_1_icon', 'lock'),
            'trust_badge_1_text' => post('trust_badge_1_text', ''),
            'trust_badge_2_enabled' => isset($_POST['trust_badge_2_enabled']) ? '1' : '0',
            'trust_badge_2_icon' => post('trust_badge_2_icon', 'check'),
            'trust_badge_2_text' => post('trust_badge_2_text', ''),
            'trust_badge_3_enabled' => isset($_POST['trust_badge_3_enabled']) ? '1' : '0',
            'trust_badge_3_icon' => post('trust_badge_3_icon', 'truck'),
            'trust_badge_3_text' => post('trust_badge_3_text', ''),
            'trust_badge_4_enabled' => isset($_POST['trust_badge_4_enabled']) ? '1' : '0',
            'trust_badge_4_icon' => post('trust_badge_4_icon', 'shield'),
            'trust_badge_4_text' => post('trust_badge_4_text', '')
        ]);
        $shopSettings->clearCache();
        $success = 'Badges de confiance enregistrés.';
        $activeTab = 'shop_badges';
    }

    // Paramètres Top Bar
    if (isset($_POST['save_shop_topbar'])) {
        // DEBUG: Afficher les données reçues
        error_log("DEBUG TopBar POST: " . print_r($_POST, true));

        $result = $shopSettings->setMultiple([
            'topbar_enabled' => isset($_POST['topbar_enabled']) ? '1' : '0',
            'topbar_text' => post('topbar_text', ''),
            'topbar_link' => post('topbar_link', ''),
            'topbar_bg_color' => post('topbar_bg_color', '#1a1a2e'),
            'topbar_text_color' => post('topbar_text_color', '#ffffff'),
            'topbar_font_family' => post('topbar_font_family', 'inherit'),
            'topbar_font_size' => post('topbar_font_size', '14'),
            'topbar_scroll_speed' => post('topbar_scroll_speed', '30')
        ]);
        error_log("DEBUG TopBar setMultiple result: " . ($result ? 'true' : 'false'));

        $shopSettings->clearCache();
        $success = 'Paramètres de la top bar enregistrés.';
        $activeTab = 'shop_topbar';
    }

    // Paramètres Apparence (couleurs header/footer)
    if (isset($_POST['save_shop_appearance'])) {
        $shopSettings->setMultiple([
            'header_bg_color' => post('header_bg_color', '#1a1a2e'),
            'header_text_color' => post('header_text_color', '#ffffff'),
            'footer_bg_color' => post('footer_bg_color', '#1a1a2e'),
            'footer_text_color' => post('footer_text_color', '#ffffff')
        ]);
        $shopSettings->clearCache();
        $success = 'Couleurs enregistrées.';
        $activeTab = 'shop_appearance';
    }

    // Paramètres Footer
    if (isset($_POST['save_shop_footer'])) {
        $shopSettings->setMultiple([
            'footer_reassurance_1' => post('footer_reassurance_1', ''),
            'footer_reassurance_2' => post('footer_reassurance_2', ''),
            'footer_reassurance_3' => post('footer_reassurance_3', ''),
            'footer_col1_title' => post('footer_col1_title', 'Navigation'),
            'footer_col2_title' => post('footer_col2_title', 'Informations'),
            'footer_col3_title' => post('footer_col3_title', 'Contact')
        ]);
        $shopSettings->clearCache();
        $success = 'Paramètres footer enregistrés.';
        $activeTab = 'shop_footer';
    }

    // Paramètres Pages légales
    if (isset($_POST['save_shop_legal'])) {
        $shopSettings->setMultiple([
            // Entreprise
            'legal_company_name' => post('legal_company_name', ''),
            'legal_company_type' => post('legal_company_type', 'auto-entrepreneur'),
            'legal_siret' => post('legal_siret', ''),
            'legal_siren' => post('legal_siren', ''),
            'legal_tva_number' => post('legal_tva_number', ''),
            'legal_rcs' => post('legal_rcs', ''),
            'legal_capital' => post('legal_capital', ''),
            // Adresse
            'legal_address' => post('legal_address', ''),
            'legal_postcode' => post('legal_postcode', ''),
            'legal_city' => post('legal_city', ''),
            'legal_country' => post('legal_country', 'France'),
            // Contact
            'legal_phone' => post('legal_phone', ''),
            'legal_email' => post('legal_email', ''),
            // Responsable
            'legal_director_name' => post('legal_director_name', ''),
            'legal_director_title' => post('legal_director_title', 'Gérant'),
            // Hébergeur
            'legal_host_name' => post('legal_host_name', 'OVH'),
            'legal_host_address' => post('legal_host_address', ''),
            'legal_host_phone' => post('legal_host_phone', ''),
            // RGPD
            'legal_dpo_name' => post('legal_dpo_name', ''),
            'legal_dpo_email' => post('legal_dpo_email', ''),
            'legal_data_collected' => post('legal_data_collected', ''),
            'legal_data_purpose' => post('legal_data_purpose', ''),
            'legal_data_retention' => post('legal_data_retention', ''),
            'legal_cookies_used' => post('legal_cookies_used', ''),
            // Marquer comme généré
            'legal_pages_generated' => '1'
        ]);
        $shopSettings->clearCache();
        $success = 'Informations légales enregistrées. Les pages CGV, Mentions légales et Politique de confidentialité sont maintenant générées automatiquement.';
        $activeTab = 'shop_legal';
    }

    // Paramètres CGV détaillés
    if (isset($_POST['save_shop_cgv'])) {
        $shopSettings->setMultiple([
            // Commandes
            'cgv_order_confirmation' => post('cgv_order_confirmation', ''),
            'cgv_order_modification' => post('cgv_order_modification', ''),
            'cgv_order_cancellation' => post('cgv_order_cancellation', ''),
            'cgv_production_time' => post('cgv_production_time', ''),
            // Livraison
            'cgv_delivery_zones' => post('cgv_delivery_zones', ''),
            'cgv_delivery_standard_time' => post('cgv_delivery_standard_time', ''),
            'cgv_delivery_express_time' => post('cgv_delivery_express_time', ''),
            'cgv_delivery_carriers' => post('cgv_delivery_carriers', ''),
            'cgv_delivery_tracking' => post('cgv_delivery_tracking', ''),
            'cgv_delivery_signature' => isset($_POST['cgv_delivery_signature']) ? '1' : '0',
            'cgv_delivery_insurance' => isset($_POST['cgv_delivery_insurance']) ? '1' : '0',
            // Paiement
            'cgv_payment_methods' => post('cgv_payment_methods', ''),
            'cgv_payment_security' => post('cgv_payment_security', ''),
            'cgv_payment_debit_time' => post('cgv_payment_debit_time', ''),
            'cgv_payment_installments' => isset($_POST['cgv_payment_installments']) ? '1' : '0',
            'cgv_payment_installments_info' => post('cgv_payment_installments_info', ''),
            // Garanties
            'cgv_legal_warranty' => post('cgv_legal_warranty', '2 ans'),
            'cgv_commercial_warranty' => isset($_POST['cgv_commercial_warranty']) ? '1' : '0',
            'cgv_commercial_warranty_duration' => post('cgv_commercial_warranty_duration', ''),
            'cgv_commercial_warranty_coverage' => post('cgv_commercial_warranty_coverage', ''),
            // Produits personnalisés
            'cgv_custom_products_policy' => post('cgv_custom_products_policy', ''),
            'cgv_custom_products_ip' => post('cgv_custom_products_ip', ''),
            'cgv_prohibited_content' => post('cgv_prohibited_content', ''),
            // Litiges
            'cgv_mediator_name' => post('cgv_mediator_name', ''),
            'cgv_mediator_address' => post('cgv_mediator_address', ''),
            'cgv_mediator_website' => post('cgv_mediator_website', ''),
            'cgv_applicable_law' => post('cgv_applicable_law', 'droit français'),
            'cgv_competent_court' => post('cgv_competent_court', '')
        ]);
        $shopSettings->clearCache();
        $success = 'Paramètres CGV enregistrés.';
        $activeTab = 'shop_cgv';
    }

    // Paramètres Politique de retour détaillés
    if (isset($_POST['save_shop_return_policy'])) {
        $shopSettings->setMultiple([
            // Conditions générales
            'return_standard_products' => isset($_POST['return_standard_products']) ? '1' : '0',
            'return_custom_products' => isset($_POST['return_custom_products']) ? '1' : '0',
            'return_custom_exception' => post('return_custom_exception', ''),
            // Conditions produit
            'return_product_condition' => post('return_product_condition', ''),
            'return_original_packaging' => isset($_POST['return_original_packaging']) ? '1' : '0',
            'return_complete_product' => post('return_complete_product', ''),
            // Processus
            'return_request_method' => post('return_request_method', 'email'),
            'return_request_info' => post('return_request_info', ''),
            'return_label_provided' => isset($_POST['return_label_provided']) ? '1' : '0',
            'return_drop_points' => post('return_drop_points', ''),
            'return_address' => post('return_address', ''),
            // Remboursement
            'return_refund_delay' => post('return_refund_delay', '14 jours'),
            'return_refund_method' => post('return_refund_method', ''),
            'return_shipping_refund' => post('return_shipping_refund', ''),
            'return_partial_refund' => post('return_partial_refund', ''),
            // Échange
            'return_exchange_available' => isset($_POST['return_exchange_available']) ? '1' : '0',
            'return_exchange_info' => post('return_exchange_info', ''),
            'return_size_exchange' => post('return_size_exchange', ''),
            // Défectueux
            'return_defective_policy' => post('return_defective_policy', ''),
            'return_defective_evidence' => post('return_defective_evidence', ''),
            'return_defective_resolution' => post('return_defective_resolution', ''),
            'return_defective_delay' => post('return_defective_delay', '48 heures'),
            'return_wrong_item_policy' => post('return_wrong_item_policy', '')
        ]);
        $shopSettings->clearCache();
        $success = 'Politique de retour enregistrée.';
        $activeTab = 'shop_return_policy';
    }
}

// Récupérer les paramètres actuels
$stripeSettings = $settingsModel->getByCategory('payment');
$shippingSettings = $settingsModel->getByCategory('shipping');
$marketingSettings = $settingsModel->getByCategory('marketing');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title"><span>Paramètres</span></h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="settings-tabs">
                <a href="?tab=account" class="tab <?= $activeTab === 'account' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Mon compte
                </a>
                <a href="?tab=shop" class="tab <?= strpos($activeTab, 'shop') === 0 ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Boutique
                </a>
                <a href="?tab=payment" class="tab <?= $activeTab === 'payment' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Paiement (Stripe)
                </a>
                <a href="?tab=shipping" class="tab <?= $activeTab === 'shipping' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="3" width="15" height="13"/>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                        <circle cx="5.5" cy="18.5" r="2.5"/>
                        <circle cx="18.5" cy="18.5" r="2.5"/>
                    </svg>
                    Livraison (Boxtal)
                </a>
                <a href="?tab=marketing" class="tab <?= $activeTab === 'marketing' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Marketing
                </a>
            </div>

            <!-- Tab Content -->
            <div class="tab-content">

                <!-- ACCOUNT TAB -->
                <?php if ($activeTab === 'account'): ?>
                <div class="settings-grid">
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Mon compte</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?= h($user['email']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Rôle</span>
                                <span class="info-value"><span class="badge badge-pink">Admin</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Changer le mot de passe</h3>
                        </div>
                        <form method="post" class="card-body">
                            <?= csrfField() ?>
                            <input type="hidden" name="change_password" value="1">

                            <div class="form-group">
                                <label class="form-label">Mot de passe actuel</label>
                                <input type="password" name="current_password" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Nouveau mot de passe</label>
                                <input type="password" name="new_password" class="form-input" required minlength="6">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" class="form-input" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Mettre à jour</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PAYMENT TAB (STRIPE) -->
                <?php if ($activeTab === 'payment'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_stripe" value="1">

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #635BFF;">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                    <line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                Configuration Stripe
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box info-box-blue">
                                <strong>Pour obtenir vos clés Stripe :</strong><br>
                                1. Connectez-vous sur <a href="https://dashboard.stripe.com" target="_blank">dashboard.stripe.com</a><br>
                                2. Allez dans Développeurs > Clés API<br>
                                3. Copiez les clés publique et secrète
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="stripe_enabled" value="1"
                                            <?= ($stripeSettings['stripe_enabled']['value'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Activer les paiements Stripe
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Mode</label>
                                    <select name="stripe_mode" class="form-input">
                                        <option value="test" <?= ($stripeSettings['stripe_mode']['value'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test (sandbox)</option>
                                        <option value="live" <?= ($stripeSettings['stripe_mode']['value'] ?? 'test') === 'live' ? 'selected' : '' ?>>Production (live)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="section-divider">
                                <span>Clés TEST (sandbox)</span>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Clé publique (test)</label>
                                    <input type="text" name="stripe_test_public_key" class="form-input font-mono"
                                           placeholder="pk_test_..."
                                           value="<?= h($stripeSettings['stripe_test_public_key']['value'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Clé secrète (test)</label>
                                    <input type="password" name="stripe_test_secret_key" class="form-input font-mono"
                                           placeholder="sk_test_..."
                                           value="<?= h($stripeSettings['stripe_test_secret_key']['value'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="section-divider">
                                <span>Clés LIVE (production)</span>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Clé publique (live)</label>
                                    <input type="text" name="stripe_live_public_key" class="form-input font-mono"
                                           placeholder="pk_live_..."
                                           value="<?= h($stripeSettings['stripe_live_public_key']['value'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Clé secrète (live)</label>
                                    <input type="password" name="stripe_live_secret_key" class="form-input font-mono"
                                           placeholder="sk_live_..."
                                           value="<?= h($stripeSettings['stripe_live_secret_key']['value'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="section-divider">
                                <span>Webhook (optionnel)</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Secret Webhook</label>
                                <input type="password" name="stripe_webhook_secret" class="form-input font-mono"
                                       placeholder="whsec_..."
                                       value="<?= h($stripeSettings['stripe_webhook_secret']['value'] ?? '') ?>">
                                <small class="form-hint">Pour recevoir les notifications de paiement en temps réel</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Enregistrer les paramètres Stripe
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- SHIPPING TAB (BOXTAL) -->
                <?php if ($activeTab === 'shipping'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shipping" value="1">

                    <!-- Boxtal Config -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #00B8D4;">
                                    <rect x="1" y="3" width="15" height="13"/>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                    <circle cx="5.5" cy="18.5" r="2.5"/>
                                    <circle cx="18.5" cy="18.5" r="2.5"/>
                                </svg>
                                Configuration Boxtal
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box info-box-cyan">
                                <strong>Pour obtenir vos clés Boxtal :</strong><br>
                                1. Créez un compte sur <a href="https://www.boxtal.com" target="_blank">boxtal.com</a><br>
                                2. Allez dans Mon compte > API<br>
                                3. Générez vos clés d'accès
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="boxtal_enabled" value="1"
                                            <?= ($shippingSettings['boxtal_enabled']['value'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Activer Boxtal (calcul automatique)
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Mode</label>
                                    <select name="boxtal_mode" class="form-input">
                                        <option value="test" <?= ($shippingSettings['boxtal_mode']['value'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test (sandbox)</option>
                                        <option value="live" <?= ($shippingSettings['boxtal_mode']['value'] ?? 'test') === 'live' ? 'selected' : '' ?>>Production (live)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Clé d'accès Boxtal</label>
                                    <input type="text" name="boxtal_user" class="form-input"
                                           placeholder="Ex: 0JXTU98QPOLMQJ92J61..."
                                           value="<?= h($shippingSettings['boxtal_user']['value'] ?? '') ?>">
                                    <small class="form-hint">Disponible dans Boxtal > Développeur > Applications</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Clé secrète Boxtal</label>
                                    <input type="password" name="boxtal_api_key" class="form-input"
                                           placeholder="Ex: 0d90af56-7a95-4b76-..."
                                           value="<?= h($shippingSettings['boxtal_api_key']['value'] ?? '') ?>">
                                    <small class="form-hint">Copiez-la lors de la création, non récupérable ensuite</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Poids par défaut par article (grammes)</label>
                                <input type="number" name="boxtal_default_weight" class="form-input" style="max-width: 200px;"
                                       value="<?= h($shippingSettings['boxtal_default_weight']['value'] ?? '500') ?>">
                                <small class="form-hint">Utilisé si le poids du produit n'est pas défini</small>
                            </div>

                            <div class="form-group" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                                <a href="/admin/test-boxtal.php" class="btn btn-secondary" target="_blank">
                                    Tester la connexion Boxtal
                                </a>
                                <small class="form-hint" style="display: block; margin-top: 8px;">
                                    Ouvre une page de diagnostic pour vérifier que les credentials fonctionnent
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Shipper Info -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Adresse de l'expéditeur</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Nom de l'entreprise</label>
                                <input type="text" name="shipper_company" class="form-input"
                                       placeholder="PERSONNALY"
                                       value="<?= h($shippingSettings['shipper_company']['value'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Adresse</label>
                                <input type="text" name="shipper_address" class="form-input"
                                       placeholder="123 rue de la Mode"
                                       value="<?= h($shippingSettings['shipper_address']['value'] ?? '') ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Code postal</label>
                                    <input type="text" name="shipper_postcode" class="form-input"
                                           placeholder="75001"
                                           value="<?= h($shippingSettings['shipper_postcode']['value'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Ville</label>
                                    <input type="text" name="shipper_city" class="form-input"
                                           placeholder="Paris"
                                           value="<?= h($shippingSettings['shipper_city']['value'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pays</label>
                                    <select name="shipper_country" class="form-input">
                                        <option value="FR" <?= ($shippingSettings['shipper_country']['value'] ?? 'FR') === 'FR' ? 'selected' : '' ?>>France</option>
                                        <option value="BE" <?= ($shippingSettings['shipper_country']['value'] ?? '') === 'BE' ? 'selected' : '' ?>>Belgique</option>
                                        <option value="CH" <?= ($shippingSettings['shipper_country']['value'] ?? '') === 'CH' ? 'selected' : '' ?>>Suisse</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" name="shipper_phone" class="form-input"
                                           placeholder="0612345678"
                                           value="<?= h($shippingSettings['shipper_phone']['value'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="shipper_email" class="form-input"
                                           placeholder="expeditions@personnaly.fr"
                                           value="<?= h($shippingSettings['shipper_email']['value'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Shipping Rates (fallback) -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Tarifs manuels (si Boxtal désactivé)</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box">
                                Ces tarifs sont utilisés si Boxtal est désactivé ou en cas d'erreur API.
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Livraison gratuite à partir de (€)</label>
                                    <input type="number" step="0.01" name="shipping_free_threshold" class="form-input"
                                           value="<?= h($shippingSettings['shipping_free_threshold']['value'] ?? '50') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix livraison standard (€)</label>
                                    <input type="number" step="0.01" name="shipping_standard_price" class="form-input"
                                           value="<?= h($shippingSettings['shipping_standard_price']['value'] ?? '4.90') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix livraison express (€)</label>
                                    <input type="number" step="0.01" name="shipping_express_price" class="form-input"
                                           value="<?= h($shippingSettings['shipping_express_price']['value'] ?? '9.90') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Enregistrer les paramètres livraison
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- MARKETING TAB (Brevo + WhatsApp) -->
                <?php if ($activeTab === 'marketing'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_marketing" value="1">

                    <!-- Brevo (Email) -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #0B996E;">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                                Brevo (Email Marketing)
                            </h3>
                            <span class="badge badge-green">300 emails/jour gratuits</span>
                        </div>
                        <div class="card-body">
                            <div class="info-box info-box-green">
                                <strong>Pour obtenir votre clé API Brevo :</strong><br>
                                1. Créez un compte sur <a href="https://app.brevo.com" target="_blank">app.brevo.com</a><br>
                                2. Allez dans Paramètres > Clés API et SMTP<br>
                                3. Générez une clé API v3
                            </div>

                            <?php
                            $brevoSent = (int) ($marketingSettings['brevo_daily_sent']['value'] ?? 0);
                            $brevoLimit = (int) ($marketingSettings['brevo_daily_limit']['value'] ?? 300);
                            $brevoPercent = $brevoLimit > 0 ? min(100, round(($brevoSent / $brevoLimit) * 100)) : 0;
                            ?>
                            <div class="usage-stats">
                                <div class="usage-header">
                                    <span>Utilisation aujourd'hui</span>
                                    <span class="usage-count"><?= $brevoSent ?> / <?= $brevoLimit ?> emails</span>
                                </div>
                                <div class="usage-bar">
                                    <div class="usage-fill" style="width: <?= $brevoPercent ?>%; background: <?= $brevoPercent > 80 ? '#ff6b6b' : '#0B996E' ?>;"></div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="brevo_enabled" value="1"
                                            <?= ($marketingSettings['brevo_enabled']['value'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Activer Brevo
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Limite quotidienne</label>
                                    <input type="number" name="brevo_daily_limit" class="form-input" style="max-width: 150px;"
                                           value="<?= h($marketingSettings['brevo_daily_limit']['value'] ?? '300') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Clé API Brevo</label>
                                <input type="password" name="brevo_api_key" class="form-input font-mono"
                                       placeholder="xkeysib-..."
                                       value="<?= h($marketingSettings['brevo_api_key']['value'] ?? '') ?>">
                            </div>

                            <div class="section-divider">
                                <span>Expéditeur</span>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nom de l'expéditeur</label>
                                    <input type="text" name="brevo_sender_name" class="form-input"
                                           placeholder="PERSONNALY"
                                           value="<?= h($marketingSettings['brevo_sender_name']['value'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email de l'expéditeur</label>
                                    <input type="email" name="brevo_sender_email" class="form-input"
                                           placeholder="contact@personnaly.fr"
                                           value="<?= h($marketingSettings['brevo_sender_email']['value'] ?? '') ?>">
                                    <small class="form-hint">Doit être vérifié dans Brevo</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WhatsApp Business -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #25D366;">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                </svg>
                                WhatsApp Business API
                            </h3>
                            <span class="badge badge-green">1000 conversations/mois gratuites</span>
                        </div>
                        <div class="card-body">
                            <div class="info-box info-box-whatsapp">
                                <strong>Pour configurer WhatsApp Business API :</strong><br>
                                1. Créez une app sur <a href="https://developers.facebook.com" target="_blank">Meta for Developers</a><br>
                                2. Ajoutez le produit "WhatsApp"<br>
                                3. Configurez votre numéro de téléphone business<br>
                                4. Récupérez les identifiants dans la section "API Setup"
                            </div>

                            <?php
                            $whatsappSent = (int) ($marketingSettings['whatsapp_monthly_sent']['value'] ?? 0);
                            $whatsappLimit = (int) ($marketingSettings['whatsapp_monthly_limit']['value'] ?? 1000);
                            $whatsappPercent = $whatsappLimit > 0 ? min(100, round(($whatsappSent / $whatsappLimit) * 100)) : 0;
                            ?>
                            <div class="usage-stats">
                                <div class="usage-header">
                                    <span>Utilisation ce mois</span>
                                    <span class="usage-count"><?= $whatsappSent ?> / <?= $whatsappLimit ?> messages</span>
                                </div>
                                <div class="usage-bar">
                                    <div class="usage-fill" style="width: <?= $whatsappPercent ?>%; background: <?= $whatsappPercent > 80 ? '#ff6b6b' : '#25D366' ?>;"></div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="whatsapp_enabled" value="1"
                                            <?= ($marketingSettings['whatsapp_enabled']['value'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Activer WhatsApp
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Limite mensuelle</label>
                                    <input type="number" name="whatsapp_monthly_limit" class="form-input" style="max-width: 150px;"
                                           value="<?= h($marketingSettings['whatsapp_monthly_limit']['value'] ?? '1000') ?>">
                                </div>
                            </div>

                            <div class="section-divider">
                                <span>Identifiants API</span>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Phone Number ID</label>
                                    <input type="text" name="whatsapp_phone_id" class="form-input font-mono"
                                           placeholder="123456789012345"
                                           value="<?= h($marketingSettings['whatsapp_phone_id']['value'] ?? '') ?>">
                                    <small class="form-hint">ID du numéro WhatsApp Business</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Business Account ID</label>
                                    <input type="text" name="whatsapp_business_id" class="form-input font-mono"
                                           placeholder="123456789012345"
                                           value="<?= h($marketingSettings['whatsapp_business_id']['value'] ?? '') ?>">
                                    <small class="form-hint">Pour récupérer les templates</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Access Token</label>
                                <input type="password" name="whatsapp_access_token" class="form-input font-mono"
                                       placeholder="EAAG..."
                                       value="<?= h($marketingSettings['whatsapp_access_token']['value'] ?? '') ?>">
                                <small class="form-hint">Token permanent recommandé (System User Token)</small>
                            </div>

                            <div class="section-divider">
                                <span>Webhook (optionnel)</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Verify Token</label>
                                <input type="text" name="whatsapp_verify_token" class="form-input font-mono"
                                       placeholder="mon_token_secret"
                                       value="<?= h($marketingSettings['whatsapp_verify_token']['value'] ?? '') ?>">
                                <small class="form-hint">Token personnalisé pour vérifier le webhook</small>
                            </div>

                            <div class="info-box" style="margin-top: 16px; margin-bottom: 0;">
                                <strong>URL du Webhook :</strong><br>
                                <code style="font-size: 12px; background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px;">
                                    <?= h((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'votre-domaine.com')) ?>/api/webhooks/whatsapp.php
                                </code>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Enregistrer les paramètres marketing
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- SHOP TAB (Boutique Settings) -->
                <?php if (strpos($activeTab, 'shop') === 0): ?>

                <!-- Sub-tabs for shop settings -->
                <div class="sub-tabs">
                    <a href="?tab=shop" class="sub-tab <?= $activeTab === 'shop' ? 'active' : '' ?>">Général</a>
                    <a href="?tab=shop_topbar" class="sub-tab <?= $activeTab === 'shop_topbar' ? 'active' : '' ?>">📢 Top Bar</a>
                    <a href="?tab=shop_appearance" class="sub-tab <?= $activeTab === 'shop_appearance' ? 'active' : '' ?>">🎨 Apparence</a>
                    <a href="?tab=shop_shipping" class="sub-tab <?= $activeTab === 'shop_shipping' ? 'active' : '' ?>">Livraison</a>
                    <a href="?tab=shop_returns" class="sub-tab <?= $activeTab === 'shop_returns' ? 'active' : '' ?>">Retours</a>
                    <a href="?tab=shop_payments" class="sub-tab <?= $activeTab === 'shop_payments' ? 'active' : '' ?>">Paiements affichés</a>
                    <a href="?tab=shop_badges" class="sub-tab <?= $activeTab === 'shop_badges' ? 'active' : '' ?>">Badges confiance</a>
                    <a href="?tab=shop_footer" class="sub-tab <?= $activeTab === 'shop_footer' ? 'active' : '' ?>">Footer</a>
                    <a href="?tab=shop_legal" class="sub-tab <?= $activeTab === 'shop_legal' ? 'active' : '' ?>">📄 Entreprise</a>
                    <a href="?tab=shop_cgv" class="sub-tab <?= $activeTab === 'shop_cgv' ? 'active' : '' ?>">📋 CGV</a>
                    <a href="?tab=shop_return_policy" class="sub-tab <?= $activeTab === 'shop_return_policy' ? 'active' : '' ?>">↩️ Politique retour</a>
                </div>

                <!-- GENERAL -->
                <?php if ($activeTab === 'shop'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_general" value="1">

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Informations générales</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Nom du site / Marque</label>
                                <input type="text" name="site_name" class="form-input"
                                       value="<?= h($shopSettings->get('site_name', 'PERSONNALY')) ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Description du site</label>
                                <textarea name="site_description" class="form-input" rows="3"><?= h($shopSettings->get('site_description', '')) ?></textarea>
                                <small class="form-hint">Affichée dans le footer</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Email de contact</label>
                                    <input type="email" name="contact_email" class="form-input"
                                           value="<?= h($shopSettings->get('contact_email', '')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Téléphone (optionnel)</label>
                                    <input type="text" name="contact_phone" class="form-input"
                                           value="<?= h($shopSettings->get('contact_phone', '')) ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Texte copyright</label>
                                <input type="text" name="copyright_text" class="form-input"
                                       value="<?= h($shopSettings->get('copyright_text', '© ' . date('Y') . ' PERSONNALY')) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Enregistrer</button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- TOP BAR (Barre d'annonce) -->
                <?php if ($activeTab === 'shop_topbar'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_topbar" value="1">

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Barre d'annonce (Top Bar)</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-switch">
                                    <input type="checkbox" name="topbar_enabled" value="1"
                                           <?= $shopSettings->get('topbar_enabled', true) ? 'checked' : '' ?>>
                                    <span class="switch-slider"></span>
                                    <span class="switch-label">Activer la top bar</span>
                                </label>
                                <small class="form-hint">Afficher la barre d'annonce en haut du site</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Texte de l'annonce</label>
                                <input type="text" name="topbar_text" class="form-input"
                                       value="<?= h($shopSettings->get('topbar_text', 'Livraison GRATUITE dès 50€ d\'achat !')) ?>"
                                       placeholder="Ex: Livraison GRATUITE dès 50€ d'achat ! Code promo: BIENVENUE10">
                                <small class="form-hint">Ce texte défilera dans la barre d'annonce</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Lien (optionnel)</label>
                                <input type="url" name="topbar_link" class="form-input"
                                       value="<?= h($shopSettings->get('topbar_link', '')) ?>"
                                       placeholder="https://exemple.com/promo">
                                <small class="form-hint">URL vers laquelle le texte redirige au clic (laisser vide si pas de lien)</small>
                            </div>
                        </div>
                    </div>

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Style de la top bar</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Couleur de fond</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="color" name="topbar_bg_color" id="topbarBgColor"
                                               value="<?= h($shopSettings->get('topbar_bg_color', '#1a1a2e')) ?>"
                                               style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer;">
                                        <input type="text" class="form-input" id="topbarBgColorText" style="width: 120px;"
                                               value="<?= h($shopSettings->get('topbar_bg_color', '#1a1a2e')) ?>"
                                               oninput="document.getElementById('topbarBgColor').value = this.value"
                                               onchange="document.getElementById('topbarBgColor').value = this.value">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Couleur du texte</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="color" name="topbar_text_color" id="topbarTextColor"
                                               value="<?= h($shopSettings->get('topbar_text_color', '#ffffff')) ?>"
                                               style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer;">
                                        <input type="text" class="form-input" id="topbarTextColorText" style="width: 120px;"
                                               value="<?= h($shopSettings->get('topbar_text_color', '#ffffff')) ?>"
                                               oninput="document.getElementById('topbarTextColor').value = this.value"
                                               onchange="document.getElementById('topbarTextColor').value = this.value">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Police d'écriture</label>
                                    <select name="topbar_font_family" class="form-input" id="topbarFontFamily">
                                        <option value="inherit" <?= $shopSettings->get('topbar_font_family', 'inherit') === 'inherit' ? 'selected' : '' ?>>Police du site (par défaut)</option>
                                        <option value="Arial, sans-serif" <?= $shopSettings->get('topbar_font_family') === 'Arial, sans-serif' ? 'selected' : '' ?>>Arial</option>
                                        <option value="'Helvetica Neue', Helvetica, sans-serif" <?= $shopSettings->get('topbar_font_family') === "'Helvetica Neue', Helvetica, sans-serif" ? 'selected' : '' ?>>Helvetica</option>
                                        <option value="Georgia, serif" <?= $shopSettings->get('topbar_font_family') === 'Georgia, serif' ? 'selected' : '' ?>>Georgia</option>
                                        <option value="'Times New Roman', serif" <?= $shopSettings->get('topbar_font_family') === "'Times New Roman', serif" ? 'selected' : '' ?>>Times New Roman</option>
                                        <option value="'Courier New', monospace" <?= $shopSettings->get('topbar_font_family') === "'Courier New', monospace" ? 'selected' : '' ?>>Courier New</option>
                                        <option value="Verdana, sans-serif" <?= $shopSettings->get('topbar_font_family') === 'Verdana, sans-serif' ? 'selected' : '' ?>>Verdana</option>
                                        <option value="'Trebuchet MS', sans-serif" <?= $shopSettings->get('topbar_font_family') === "'Trebuchet MS', sans-serif" ? 'selected' : '' ?>>Trebuchet MS</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Taille de police (px)</label>
                                    <input type="number" name="topbar_font_size" class="form-input" id="topbarFontSize"
                                           value="<?= h($shopSettings->get('topbar_font_size', '14')) ?>"
                                           min="10" max="24" step="1">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Vitesse de défilement (secondes)</label>
                                <input type="number" name="topbar_scroll_speed" class="form-input" id="topbarScrollSpeed"
                                       value="<?= h($shopSettings->get('topbar_scroll_speed', '30')) ?>"
                                       min="5" max="120" step="5">
                                <small class="form-hint">Durée d'un cycle complet de défilement (plus grand = plus lent)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Aperçu en temps réel</h3>
                        </div>
                        <div class="card-body">
                            <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <!-- Topbar preview -->
                                <div id="topbarPreview" style="background: <?= h($shopSettings->get('topbar_bg_color', '#1a1a2e')) ?>; padding: 8px 20px; text-align: center; overflow: hidden; position: relative;">
                                    <span id="topbarPreviewText" style="color: <?= h($shopSettings->get('topbar_text_color', '#ffffff')) ?>; font-family: <?= h($shopSettings->get('topbar_font_family', 'inherit')) ?>; font-size: <?= h($shopSettings->get('topbar_font_size', '14')) ?>px; white-space: nowrap; display: inline-block; animation: topbar-marquee 10s linear infinite;">
                                        <?= h($shopSettings->get('topbar_text', 'Livraison GRATUITE dès 50€ d\'achat !')) ?>
                                    </span>
                                </div>
                                <!-- Header placeholder -->
                                <div style="background: #1a1a2e; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #fff; font-weight: 600;"><?= h($shopSettings->get('site_name', 'PERSONNALY')) ?></span>
                                    <div style="display: flex; gap: 15px; color: #fff;">
                                        <span style="font-size: 13px; opacity: 0.9;">Produits</span>
                                        <span style="font-size: 13px; opacity: 0.9;">Panier</span>
                                    </div>
                                </div>
                                <!-- Content placeholder -->
                                <div style="height: 60px; background: #fafafa; display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 12px;">
                                    Contenu de la page...
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Enregistrer</button>
                    </div>
                </form>

                <style>
                @keyframes topbar-marquee {
                    0% { transform: translateX(100%); }
                    100% { transform: translateX(-100%); }
                }
                </style>

                <script>
                // Live preview for topbar
                document.getElementById('topbarBgColor').addEventListener('input', function(e) {
                    document.getElementById('topbarPreview').style.background = e.target.value;
                    document.getElementById('topbarBgColorText').value = e.target.value;
                });
                document.getElementById('topbarTextColor').addEventListener('input', function(e) {
                    document.getElementById('topbarPreviewText').style.color = e.target.value;
                    document.getElementById('topbarTextColorText').value = e.target.value;
                });
                document.getElementById('topbarFontFamily').addEventListener('change', function(e) {
                    document.getElementById('topbarPreviewText').style.fontFamily = e.target.value;
                });
                document.getElementById('topbarFontSize').addEventListener('input', function(e) {
                    document.getElementById('topbarPreviewText').style.fontSize = e.target.value + 'px';
                });
                document.querySelector('input[name="topbar_text"]').addEventListener('input', function(e) {
                    document.getElementById('topbarPreviewText').textContent = e.target.value;
                });
                </script>
                <?php endif; ?>

                <!-- APPEARANCE (Header/Footer Colors) -->
                <?php if ($activeTab === 'shop_appearance'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_appearance" value="1">

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Couleurs du Header (Navigation)</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Couleur de fond</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="color" name="header_bg_color" id="headerBgColor"
                                               value="<?= h($shopSettings->get('header_bg_color', '#1a1a2e')) ?>"
                                               style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer;">
                                        <input type="text" class="form-input" style="width: 120px;"
                                               value="<?= h($shopSettings->get('header_bg_color', '#1a1a2e')) ?>"
                                               oninput="document.getElementById('headerBgColor').value = this.value"
                                               onchange="document.getElementById('headerBgColor').value = this.value">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Couleur du texte</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="color" name="header_text_color" id="headerTextColor"
                                               value="<?= h($shopSettings->get('header_text_color', '#ffffff')) ?>"
                                               style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer;">
                                        <input type="text" class="form-input" style="width: 120px;"
                                               value="<?= h($shopSettings->get('header_text_color', '#ffffff')) ?>"
                                               oninput="document.getElementById('headerTextColor').value = this.value"
                                               onchange="document.getElementById('headerTextColor').value = this.value">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Couleurs du Footer (Pied de page)</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Couleur de fond</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="color" name="footer_bg_color" id="footerBgColor"
                                               value="<?= h($shopSettings->get('footer_bg_color', '#1a1a2e')) ?>"
                                               style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer;">
                                        <input type="text" class="form-input" style="width: 120px;"
                                               value="<?= h($shopSettings->get('footer_bg_color', '#1a1a2e')) ?>"
                                               oninput="document.getElementById('footerBgColor').value = this.value"
                                               onchange="document.getElementById('footerBgColor').value = this.value">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Couleur du texte</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="color" name="footer_text_color" id="footerTextColor"
                                               value="<?= h($shopSettings->get('footer_text_color', '#ffffff')) ?>"
                                               style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer;">
                                        <input type="text" class="form-input" style="width: 120px;"
                                               value="<?= h($shopSettings->get('footer_text_color', '#ffffff')) ?>"
                                               oninput="document.getElementById('footerTextColor').value = this.value"
                                               onchange="document.getElementById('footerTextColor').value = this.value">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Aperçu en temps réel</h3>
                        </div>
                        <div class="card-body">
                            <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <!-- Header preview -->
                                <div id="headerPreview" style="background: <?= h($shopSettings->get('header_bg_color', '#1a1a2e')) ?>; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                                    <span id="headerPreviewBrand" style="color: <?= h($shopSettings->get('header_text_color', '#ffffff')) ?>; font-weight: 600;"><?= h($shopSettings->get('site_name', 'PERSONNALY')) ?></span>
                                    <div id="headerPreviewNav" style="display: flex; gap: 15px; color: <?= h($shopSettings->get('header_text_color', '#ffffff')) ?>;">
                                        <span style="font-size: 13px; opacity: 0.9;">Produits</span>
                                        <span style="font-size: 13px; opacity: 0.9;">Contact</span>
                                        <span style="font-size: 13px; opacity: 0.9;">Panier</span>
                                    </div>
                                </div>
                                <!-- Content placeholder -->
                                <div style="height: 80px; background: #fafafa; display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 12px;">
                                    Contenu de la page...
                                </div>
                                <!-- Footer preview -->
                                <div id="footerPreview" style="background: <?= h($shopSettings->get('footer_bg_color', '#1a1a2e')) ?>; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                                    <span id="footerPreviewBrand" style="color: <?= h($shopSettings->get('footer_text_color', '#ffffff')) ?>; font-size: 12px; opacity: 0.9;"><?= h($shopSettings->get('site_name', 'PERSONNALY')) ?></span>
                                    <span id="footerPreviewCopy" style="color: <?= h($shopSettings->get('footer_text_color', '#ffffff')) ?>; font-size: 11px; opacity: 0.7;">© <?= date('Y') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Enregistrer</button>
                    </div>
                </form>

                <script>
                // Live preview for header colors
                document.getElementById('headerBgColor').addEventListener('input', function(e) {
                    document.getElementById('headerPreview').style.background = e.target.value;
                    this.nextElementSibling.value = e.target.value;
                });
                document.getElementById('headerTextColor').addEventListener('input', function(e) {
                    const color = e.target.value;
                    document.getElementById('headerPreviewBrand').style.color = color;
                    document.getElementById('headerPreviewNav').style.color = color;
                    this.nextElementSibling.value = color;
                });
                // Live preview for footer colors
                document.getElementById('footerBgColor').addEventListener('input', function(e) {
                    document.getElementById('footerPreview').style.background = e.target.value;
                    this.nextElementSibling.value = e.target.value;
                });
                document.getElementById('footerTextColor').addEventListener('input', function(e) {
                    const color = e.target.value;
                    document.getElementById('footerPreviewBrand').style.color = color;
                    document.getElementById('footerPreviewCopy').style.color = color;
                    this.nextElementSibling.value = color;
                });
                </script>
                <?php endif; ?>

                <!-- SHIPPING DISPLAY -->
                <?php if ($activeTab === 'shop_shipping'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_shipping" value="1">

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Livraison gratuite</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="free_shipping_enabled" value="1"
                                            <?= $shopSettings->get('free_shipping_enabled', false) ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Activer la livraison gratuite
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Seuil (€)</label>
                                    <input type="number" step="0.01" name="free_shipping_threshold" class="form-input"
                                           value="<?= h($shopSettings->get('free_shipping_threshold', 50)) ?>">
                                    <small class="form-hint">Livraison gratuite à partir de ce montant</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Adresse par défaut (estimation)</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box">
                                Utilisée pour estimer les frais de port avant que le client entre son adresse.
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Ville</label>
                                    <input type="text" name="shipping_default_city" class="form-input"
                                           value="<?= h($shopSettings->get('shipping_default_city', 'Paris')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Code postal</label>
                                    <input type="text" name="shipping_default_postcode" class="form-input"
                                           value="<?= h($shopSettings->get('shipping_default_postcode', '75001')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pays</label>
                                    <select name="shipping_default_country" class="form-input">
                                        <option value="FR" <?= $shopSettings->get('shipping_default_country', 'FR') === 'FR' ? 'selected' : '' ?>>France</option>
                                        <option value="BE" <?= $shopSettings->get('shipping_default_country', 'FR') === 'BE' ? 'selected' : '' ?>>Belgique</option>
                                        <option value="CH" <?= $shopSettings->get('shipping_default_country', 'FR') === 'CH' ? 'selected' : '' ?>>Suisse</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Tarifs de secours (si Boxtal indisponible)</h3>
                        </div>
                        <div class="card-body">
                            <div class="section-divider"><span>Livraison Standard</span></div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="shipping_fallback_standard_label" class="form-input"
                                           value="<?= h($shopSettings->get('shipping_fallback_standard_label', 'Livraison standard')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix (€)</label>
                                    <input type="number" step="0.01" name="shipping_fallback_standard_price" class="form-input"
                                           value="<?= h($shopSettings->get('shipping_fallback_standard_price', 4.90)) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Délai</label>
                                    <input type="text" name="shipping_fallback_standard_delay" class="form-input"
                                           value="<?= h($shopSettings->get('shipping_fallback_standard_delay', '3-5 jours ouvrés')) ?>">
                                </div>
                            </div>

                            <div class="section-divider"><span>Livraison Express</span></div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="shipping_fallback_express_label" class="form-input"
                                           value="<?= h($shopSettings->get('shipping_fallback_express_label', 'Livraison express')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix (€)</label>
                                    <input type="number" step="0.01" name="shipping_fallback_express_price" class="form-input"
                                           value="<?= h($shopSettings->get('shipping_fallback_express_price', 9.90)) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Délai</label>
                                    <input type="text" name="shipping_fallback_express_delay" class="form-input"
                                           value="<?= h($shopSettings->get('shipping_fallback_express_delay', '24-48h')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Enregistrer</button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- RETURNS -->
                <?php if ($activeTab === 'shop_returns'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_returns" value="1">

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Politique de retours</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="returns_enabled" value="1"
                                            <?= $shopSettings->get('returns_enabled', true) ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Autoriser les retours
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="returns_free" value="1"
                                            <?= $shopSettings->get('returns_free', true) ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Retours gratuits
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Délai de retour (jours)</label>
                                <input type="number" name="returns_days" class="form-input" style="max-width: 150px;"
                                       value="<?= h($shopSettings->get('returns_days', 14)) ?>">
                                <small class="form-hint">Nombre de jours après réception pour demander un retour</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Conditions de retour</label>
                                <textarea name="returns_conditions" class="form-input" rows="4"><?= h($shopSettings->get('returns_conditions', '')) ?></textarea>
                                <small class="form-hint">Ex: "Produit non porté, dans son emballage d'origine..."</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Enregistrer</button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- PAYMENT METHODS DISPLAY -->
                <?php if ($activeTab === 'shop_payments'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_payments" value="1">

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Moyens de paiement à afficher</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box">
                                Sélectionnez les moyens de paiement à afficher sur le site. Cela n'active pas le paiement,
                                c'est uniquement pour l'affichage (les icônes dans le panier).
                            </div>

                            <div class="payment-methods-grid">
                                <label class="payment-method-item">
                                    <input type="checkbox" name="payment_visa_enabled" value="1"
                                        <?= $shopSettings->get('payment_visa_enabled', true) ? 'checked' : '' ?>>
                                    <span class="payment-icon" style="color: #1A1F71;">VISA</span>
                                    <span>Visa</span>
                                </label>
                                <label class="payment-method-item">
                                    <input type="checkbox" name="payment_mastercard_enabled" value="1"
                                        <?= $shopSettings->get('payment_mastercard_enabled', true) ? 'checked' : '' ?>>
                                    <span class="payment-icon" style="color: #EB001B;">MC</span>
                                    <span>Mastercard</span>
                                </label>
                                <label class="payment-method-item">
                                    <input type="checkbox" name="payment_amex_enabled" value="1"
                                        <?= $shopSettings->get('payment_amex_enabled', true) ? 'checked' : '' ?>>
                                    <span class="payment-icon" style="color: #006FCF;">AMEX</span>
                                    <span>American Express</span>
                                </label>
                                <label class="payment-method-item">
                                    <input type="checkbox" name="payment_cb_enabled" value="1"
                                        <?= $shopSettings->get('payment_cb_enabled', true) ? 'checked' : '' ?>>
                                    <span class="payment-icon" style="color: #1D4F91;">CB</span>
                                    <span>Carte Bancaire</span>
                                </label>
                                <label class="payment-method-item">
                                    <input type="checkbox" name="payment_paypal_enabled" value="1"
                                        <?= $shopSettings->get('payment_paypal_enabled', false) ? 'checked' : '' ?>>
                                    <span class="payment-icon" style="color: #003087;">PP</span>
                                    <span>PayPal</span>
                                </label>
                                <label class="payment-method-item">
                                    <input type="checkbox" name="payment_apple_pay_enabled" value="1"
                                        <?= $shopSettings->get('payment_apple_pay_enabled', false) ? 'checked' : '' ?>>
                                    <span class="payment-icon" style="color: #000;">AP</span>
                                    <span>Apple Pay</span>
                                </label>
                                <label class="payment-method-item">
                                    <input type="checkbox" name="payment_google_pay_enabled" value="1"
                                        <?= $shopSettings->get('payment_google_pay_enabled', false) ? 'checked' : '' ?>>
                                    <span class="payment-icon" style="color: #4285F4;">GP</span>
                                    <span>Google Pay</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Enregistrer</button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- TRUST BADGES -->
                <?php if ($activeTab === 'shop_badges'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_badges" value="1">

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Badges de confiance</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box">
                                Ces badges sont affichés dans le panier pour rassurer le client (paiement sécurisé, retours, etc.)
                            </div>

                            <?php for ($i = 1; $i <= 4; $i++): ?>
                            <div class="badge-config-row">
                                <div class="form-group" style="flex: 0 0 auto;">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="trust_badge_<?= $i ?>_enabled" value="1"
                                            <?= $shopSettings->get("trust_badge_{$i}_enabled", $i <= 3) ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                    </label>
                                </div>
                                <div class="form-group" style="flex: 0 0 150px;">
                                    <label class="form-label">Icône <?= $i ?></label>
                                    <select name="trust_badge_<?= $i ?>_icon" class="form-input">
                                        <option value="lock" <?= $shopSettings->get("trust_badge_{$i}_icon") === 'lock' ? 'selected' : '' ?>>🔒 Cadenas</option>
                                        <option value="check" <?= $shopSettings->get("trust_badge_{$i}_icon") === 'check' ? 'selected' : '' ?>>✓ Check</option>
                                        <option value="truck" <?= $shopSettings->get("trust_badge_{$i}_icon") === 'truck' ? 'selected' : '' ?>>🚚 Camion</option>
                                        <option value="shield" <?= $shopSettings->get("trust_badge_{$i}_icon") === 'shield' ? 'selected' : '' ?>>🛡️ Bouclier</option>
                                        <option value="star" <?= $shopSettings->get("trust_badge_{$i}_icon") === 'star' ? 'selected' : '' ?>>⭐ Étoile</option>
                                        <option value="heart" <?= $shopSettings->get("trust_badge_{$i}_icon") === 'heart' ? 'selected' : '' ?>>❤️ Cœur</option>
                                        <option value="return" <?= $shopSettings->get("trust_badge_{$i}_icon") === 'return' ? 'selected' : '' ?>>↩️ Retour</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Texte <?= $i ?></label>
                                    <input type="text" name="trust_badge_<?= $i ?>_text" class="form-input"
                                           placeholder="Ex: Paiement 100% sécurisé"
                                           value="<?= h($shopSettings->get("trust_badge_{$i}_text", '')) ?>">
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Enregistrer</button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- FOOTER -->
                <?php if ($activeTab === 'shop_footer'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_footer" value="1">

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Éléments de réassurance</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box">
                                Affichés en bas du footer (ex: 🔒 Paiement sécurisé, 🚚 Livraison gratuite...)
                            </div>
                            <div class="form-group">
                                <label class="form-label">Réassurance 1</label>
                                <input type="text" name="footer_reassurance_1" class="form-input"
                                       placeholder="🔒 Paiement sécurisé"
                                       value="<?= h($shopSettings->get('footer_reassurance_1', '')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Réassurance 2</label>
                                <input type="text" name="footer_reassurance_2" class="form-input"
                                       placeholder="🚚 Livraison gratuite dès 50€"
                                       value="<?= h($shopSettings->get('footer_reassurance_2', '')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Réassurance 3</label>
                                <input type="text" name="footer_reassurance_3" class="form-input"
                                       placeholder="↩️ Retours 14 jours"
                                       value="<?= h($shopSettings->get('footer_reassurance_3', '')) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Titres des colonnes</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Colonne 1</label>
                                    <input type="text" name="footer_col1_title" class="form-input"
                                           value="<?= h($shopSettings->get('footer_col1_title', 'Navigation')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Colonne 2</label>
                                    <input type="text" name="footer_col2_title" class="form-input"
                                           value="<?= h($shopSettings->get('footer_col2_title', 'Informations')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Colonne 3</label>
                                    <input type="text" name="footer_col3_title" class="form-input"
                                           value="<?= h($shopSettings->get('footer_col3_title', 'Contact')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Enregistrer</button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- LEGAL PAGES -->
                <?php if ($activeTab === 'shop_legal'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_legal" value="1">

                    <?php if ($shopSettings->get('legal_pages_generated', false)): ?>
                    <div class="alert alert-success" style="margin-bottom: 24px;">
                        ✅ Vos pages légales sont générées ! Elles sont accessibles sur :
                        <ul style="margin: 10px 0 0 20px;">
                            <li><a href="/mentions-legales" target="_blank">/mentions-legales</a></li>
                            <li><a href="/cgv" target="_blank">/cgv</a> (Conditions Générales de Vente)</li>
                            <li><a href="/politique-confidentialite" target="_blank">/politique-confidentialite</a></li>
                            <li><a href="/politique-retour" target="_blank">/politique-retour</a></li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="info-box" style="margin-bottom: 24px; background: linear-gradient(135deg, rgba(99,91,255,0.1) 0%, rgba(255,105,180,0.1) 100%);">
                        <strong>📄 Génération automatique des pages légales</strong><br>
                        Remplissez les informations ci-dessous et vos pages CGV, Mentions légales, Politique de confidentialité
                        et Politique de retour seront générées automatiquement et accessibles sur votre site.
                    </div>

                    <!-- Informations entreprise -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">🏢 Informations de l'entreprise</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Raison sociale *</label>
                                    <input type="text" name="legal_company_name" class="form-input" required
                                           placeholder="Ex: PERSONNALY"
                                           value="<?= h($shopSettings->get('legal_company_name', '')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Forme juridique</label>
                                    <select name="legal_company_type" class="form-input">
                                        <option value="auto-entrepreneur" <?= $shopSettings->get('legal_company_type') === 'auto-entrepreneur' ? 'selected' : '' ?>>Auto-entrepreneur</option>
                                        <option value="ei" <?= $shopSettings->get('legal_company_type') === 'ei' ? 'selected' : '' ?>>Entreprise Individuelle (EI)</option>
                                        <option value="eurl" <?= $shopSettings->get('legal_company_type') === 'eurl' ? 'selected' : '' ?>>EURL</option>
                                        <option value="sarl" <?= $shopSettings->get('legal_company_type') === 'sarl' ? 'selected' : '' ?>>SARL</option>
                                        <option value="sas" <?= $shopSettings->get('legal_company_type') === 'sas' ? 'selected' : '' ?>>SAS</option>
                                        <option value="sasu" <?= $shopSettings->get('legal_company_type') === 'sasu' ? 'selected' : '' ?>>SASU</option>
                                        <option value="sa" <?= $shopSettings->get('legal_company_type') === 'sa' ? 'selected' : '' ?>>SA</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">SIRET (14 chiffres) *</label>
                                    <input type="text" name="legal_siret" class="form-input font-mono" required
                                           placeholder="12345678901234" maxlength="14"
                                           value="<?= h($shopSettings->get('legal_siret', '')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">N° TVA Intracommunautaire</label>
                                    <input type="text" name="legal_tva_number" class="form-input font-mono"
                                           placeholder="FR12345678901 (si assujetti)"
                                           value="<?= h($shopSettings->get('legal_tva_number', '')) ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">RCS (ville)</label>
                                    <input type="text" name="legal_rcs" class="form-input"
                                           placeholder="Paris (si applicable)"
                                           value="<?= h($shopSettings->get('legal_rcs', '')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Capital social</label>
                                    <input type="text" name="legal_capital" class="form-input"
                                           placeholder="1 000 € (si applicable)"
                                           value="<?= h($shopSettings->get('legal_capital', '')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Adresse -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">📍 Adresse du siège social</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Adresse *</label>
                                <input type="text" name="legal_address" class="form-input" required
                                       placeholder="123 rue de la Mode"
                                       value="<?= h($shopSettings->get('legal_address', '')) ?>">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Code postal *</label>
                                    <input type="text" name="legal_postcode" class="form-input" required
                                           placeholder="75001"
                                           value="<?= h($shopSettings->get('legal_postcode', '')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Ville *</label>
                                    <input type="text" name="legal_city" class="form-input" required
                                           placeholder="Paris"
                                           value="<?= h($shopSettings->get('legal_city', '')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pays</label>
                                    <input type="text" name="legal_country" class="form-input"
                                           value="<?= h($shopSettings->get('legal_country', 'France')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact & Responsable -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">👤 Contact & Responsable</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" name="legal_phone" class="form-input"
                                           placeholder="01 23 45 67 89"
                                           value="<?= h($shopSettings->get('legal_phone', '')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="legal_email" class="form-input" required
                                           placeholder="contact@entreprise.fr"
                                           value="<?= h($shopSettings->get('legal_email', '')) ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nom du directeur de publication *</label>
                                    <input type="text" name="legal_director_name" class="form-input" required
                                           placeholder="Jean Dupont"
                                           value="<?= h($shopSettings->get('legal_director_name', '')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Fonction</label>
                                    <input type="text" name="legal_director_title" class="form-input"
                                           placeholder="Gérant"
                                           value="<?= h($shopSettings->get('legal_director_title', 'Gérant')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hébergeur -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">🌐 Hébergeur du site</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nom de l'hébergeur *</label>
                                    <input type="text" name="legal_host_name" class="form-input" required
                                           placeholder="OVH, Ionos, O2Switch..."
                                           value="<?= h($shopSettings->get('legal_host_name', 'OVH')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Téléphone hébergeur</label>
                                    <input type="text" name="legal_host_phone" class="form-input"
                                           placeholder="09 72 10 10 07"
                                           value="<?= h($shopSettings->get('legal_host_phone', '')) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Adresse de l'hébergeur</label>
                                <input type="text" name="legal_host_address" class="form-input"
                                       placeholder="2 rue Kellermann, 59100 Roubaix, France"
                                       value="<?= h($shopSettings->get('legal_host_address', '2 rue Kellermann, 59100 Roubaix, France')) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- RGPD -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">🔒 Données personnelles (RGPD)</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">DPO (Délégué à la protection des données)</label>
                                    <input type="text" name="legal_dpo_name" class="form-input"
                                           placeholder="Optionnel - Nom du DPO si vous en avez un"
                                           value="<?= h($shopSettings->get('legal_dpo_name', '')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email DPO / Contact RGPD</label>
                                    <input type="email" name="legal_dpo_email" class="form-input"
                                           placeholder="rgpd@entreprise.fr"
                                           value="<?= h($shopSettings->get('legal_dpo_email', '')) ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Données personnelles collectées</label>
                                <textarea name="legal_data_collected" class="form-input" rows="2"
                                          placeholder="nom, prénom, adresse email, adresse postale, numéro de téléphone"><?= h($shopSettings->get('legal_data_collected', 'nom, prénom, adresse email, adresse postale, numéro de téléphone')) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Finalités du traitement</label>
                                <textarea name="legal_data_purpose" class="form-input" rows="2"
                                          placeholder="traitement des commandes, livraison, service client..."><?= h($shopSettings->get('legal_data_purpose', 'traitement des commandes, livraison, service client, newsletter (avec consentement)')) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Durée de conservation</label>
                                <input type="text" name="legal_data_retention" class="form-input"
                                       placeholder="3 ans après la dernière commande"
                                       value="<?= h($shopSettings->get('legal_data_retention', '3 ans après la dernière commande')) ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Cookies utilisés</label>
                                <textarea name="legal_cookies_used" class="form-input" rows="2"
                                          placeholder="cookies de session, cookies de panier..."><?= h($shopSettings->get('legal_cookies_used', 'cookies de session, cookies de panier, cookies analytiques (avec consentement)')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            Enregistrer et générer les pages légales
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- CGV DETAILLEES -->
                <?php if ($activeTab === 'shop_cgv'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_cgv" value="1">

                    <div class="info-box" style="margin-bottom: 24px;">
                        <strong>📋 Conditions Générales de Vente</strong><br>
                        Ces informations complètent les données de l'onglet "Entreprise" pour générer des CGV complètes et conformes.
                    </div>

                    <!-- Commandes -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">📦 Commandes</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Confirmation de commande</label>
                                <textarea name="cgv_order_confirmation" class="form-input" rows="2"
                                    placeholder="Ex: Un email de confirmation vous est envoyé dès validation..."><?= h($shopSettings->get('cgv_order_confirmation', 'Un email de confirmation vous est envoyé dès validation de votre commande.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Délai de fabrication (produits personnalisés)</label>
                                <input type="text" name="cgv_production_time" class="form-input"
                                       placeholder="Ex: 3 à 5 jours ouvrés"
                                       value="<?= h($shopSettings->get('cgv_production_time', '3 à 5 jours ouvrés')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Modification de commande</label>
                                <textarea name="cgv_order_modification" class="form-input" rows="2"
                                    placeholder="Dans quel délai peut-on modifier une commande ?"><?= h($shopSettings->get('cgv_order_modification', 'Toute modification de commande doit être demandée dans les 2 heures suivant la commande, avant mise en production.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Annulation de commande</label>
                                <textarea name="cgv_order_cancellation" class="form-input" rows="2"
                                    placeholder="Conditions d'annulation"><?= h($shopSettings->get('cgv_order_cancellation', 'L\'annulation est possible uniquement avant la mise en production du produit personnalisé.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Livraison -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">🚚 Livraison</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Zones de livraison</label>
                                <textarea name="cgv_delivery_zones" class="form-input" rows="2"
                                    placeholder="Ex: France métropolitaine, DOM-TOM, Belgique..."><?= h($shopSettings->get('cgv_delivery_zones', 'France métropolitaine, DOM-TOM, Belgique, Suisse, Luxembourg')) ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Délai livraison standard</label>
                                    <input type="text" name="cgv_delivery_standard_time" class="form-input"
                                           placeholder="3 à 5 jours ouvrés"
                                           value="<?= h($shopSettings->get('cgv_delivery_standard_time', '3 à 5 jours ouvrés')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Délai livraison express</label>
                                    <input type="text" name="cgv_delivery_express_time" class="form-input"
                                           placeholder="24 à 48 heures"
                                           value="<?= h($shopSettings->get('cgv_delivery_express_time', '24 à 48 heures')) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Transporteurs utilisés</label>
                                <input type="text" name="cgv_delivery_carriers" class="form-input"
                                       placeholder="Colissimo, Mondial Relay, Chronopost..."
                                       value="<?= h($shopSettings->get('cgv_delivery_carriers', 'Colissimo, Mondial Relay, Chronopost')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Suivi de livraison</label>
                                <textarea name="cgv_delivery_tracking" class="form-input" rows="2"><?= h($shopSettings->get('cgv_delivery_tracking', 'Un numéro de suivi vous est communiqué par email dès l\'expédition de votre colis.')) ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="cgv_delivery_signature" value="1"
                                            <?= $shopSettings->get('cgv_delivery_signature', false) ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Signature requise à la livraison
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="cgv_delivery_insurance" value="1"
                                            <?= $shopSettings->get('cgv_delivery_insurance', true) ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Colis assurés
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paiement -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">💳 Paiement</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Moyens de paiement acceptés</label>
                                <textarea name="cgv_payment_methods" class="form-input" rows="2"><?= h($shopSettings->get('cgv_payment_methods', 'Carte bancaire (Visa, Mastercard, CB, American Express) via Stripe')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Sécurité des paiements</label>
                                <textarea name="cgv_payment_security" class="form-input" rows="2"><?= h($shopSettings->get('cgv_payment_security', 'Tous les paiements sont sécurisés par Stripe. Vos données bancaires ne transitent jamais par nos serveurs et sont chiffrées en SSL/TLS.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Moment du débit</label>
                                <textarea name="cgv_payment_debit_time" class="form-input" rows="2"><?= h($shopSettings->get('cgv_payment_debit_time', 'Le débit est effectué immédiatement à la validation de la commande.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label toggle-label">
                                    <input type="checkbox" name="cgv_payment_installments" value="1"
                                        <?= $shopSettings->get('cgv_payment_installments', false) ? 'checked' : '' ?>>
                                    <span class="toggle-switch"></span>
                                    Paiement en plusieurs fois disponible
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Détails paiement en plusieurs fois (si activé)</label>
                                <textarea name="cgv_payment_installments_info" class="form-input" rows="2"
                                    placeholder="Ex: Paiement en 3 ou 4 fois sans frais via Alma..."><?= h($shopSettings->get('cgv_payment_installments_info', '')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Garanties -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">🛡️ Garanties</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Garantie légale de conformité</label>
                                <input type="text" name="cgv_legal_warranty" class="form-input"
                                       placeholder="2 ans"
                                       value="<?= h($shopSettings->get('cgv_legal_warranty', '2 ans')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label toggle-label">
                                    <input type="checkbox" name="cgv_commercial_warranty" value="1"
                                        <?= $shopSettings->get('cgv_commercial_warranty', false) ? 'checked' : '' ?>>
                                    <span class="toggle-switch"></span>
                                    Garantie commerciale supplémentaire
                                </label>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Durée garantie commerciale</label>
                                    <input type="text" name="cgv_commercial_warranty_duration" class="form-input"
                                           placeholder="Ex: 1 an"
                                           value="<?= h($shopSettings->get('cgv_commercial_warranty_duration', '')) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Ce que couvre la garantie commerciale</label>
                                <textarea name="cgv_commercial_warranty_coverage" class="form-input" rows="2"
                                    placeholder="Ex: Défauts de fabrication, coutures..."><?= h($shopSettings->get('cgv_commercial_warranty_coverage', '')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Produits personnalisés -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">✏️ Produits personnalisés</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Politique produits personnalisés</label>
                                <textarea name="cgv_custom_products_policy" class="form-input" rows="3"><?= h($shopSettings->get('cgv_custom_products_policy', 'Les produits personnalisés sont fabriqués selon vos spécifications. Nous ne pouvons être tenus responsables des erreurs dues aux informations fournies par le client (textes, images, choix de personnalisation).')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Propriété intellectuelle (contenus fournis par le client)</label>
                                <textarea name="cgv_custom_products_ip" class="form-input" rows="3"><?= h($shopSettings->get('cgv_custom_products_ip', 'Vous garantissez détenir les droits sur les contenus (textes, images, logos) que vous nous transmettez pour personnalisation. Tout contenu illégal, diffamatoire ou portant atteinte aux droits de tiers sera refusé.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contenus interdits</label>
                                <textarea name="cgv_prohibited_content" class="form-input" rows="2"><?= h($shopSettings->get('cgv_prohibited_content', 'Contenu à caractère pornographique, violent, raciste, discriminatoire, incitant à la haine, ou portant atteinte aux droits de propriété intellectuelle de tiers.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Litiges -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">⚖️ Litiges et médiation</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Nom du médiateur de la consommation</label>
                                <input type="text" name="cgv_mediator_name" class="form-input"
                                       placeholder="Ex: FEVAD, CM2C..."
                                       value="<?= h($shopSettings->get('cgv_mediator_name', '')) ?>">
                                <small class="form-hint">Obligatoire depuis 2016 pour les sites e-commerce</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Adresse du médiateur</label>
                                <textarea name="cgv_mediator_address" class="form-input" rows="2"
                                    placeholder="Adresse complète du médiateur"><?= h($shopSettings->get('cgv_mediator_address', '')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Site web du médiateur</label>
                                <input type="url" name="cgv_mediator_website" class="form-input"
                                       placeholder="https://..."
                                       value="<?= h($shopSettings->get('cgv_mediator_website', '')) ?>">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Droit applicable</label>
                                    <input type="text" name="cgv_applicable_law" class="form-input"
                                           value="<?= h($shopSettings->get('cgv_applicable_law', 'droit français')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tribunal compétent</label>
                                    <input type="text" name="cgv_competent_court" class="form-input"
                                           placeholder="Ex: tribunaux de Paris"
                                           value="<?= h($shopSettings->get('cgv_competent_court', 'les tribunaux du ressort de notre siège social')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Enregistrer les CGV
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- POLITIQUE DE RETOUR DETAILLEE -->
                <?php if ($activeTab === 'shop_return_policy'): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_shop_return_policy" value="1">

                    <div class="info-box" style="margin-bottom: 24px;">
                        <strong>↩️ Politique de Retour</strong><br>
                        Configurez votre politique de retour en détail pour générer une page complète et rassurante pour vos clients.
                    </div>

                    <!-- Conditions générales -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">📋 Conditions générales de retour</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="return_standard_products" value="1"
                                            <?= $shopSettings->get('return_standard_products', true) ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Retour produits standards accepté
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label toggle-label">
                                        <input type="checkbox" name="return_custom_products" value="1"
                                            <?= $shopSettings->get('return_custom_products', false) ? 'checked' : '' ?>>
                                        <span class="toggle-switch"></span>
                                        Retour produits personnalisés accepté
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Exception pour produits personnalisés</label>
                                <textarea name="return_custom_exception" class="form-input" rows="3"><?= h($shopSettings->get('return_custom_exception', 'Conformément à l\'article L.221-28 du Code de la consommation, les produits personnalisés ou confectionnés selon vos spécifications ne peuvent faire l\'objet d\'un retour, sauf défaut de fabrication avéré.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Conditions du produit -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">📦 État du produit pour retour</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">État requis du produit</label>
                                <input type="text" name="return_product_condition" class="form-input"
                                       placeholder="Ex: non porté, non lavé, avec étiquettes..."
                                       value="<?= h($shopSettings->get('return_product_condition', 'non porté, non lavé, avec étiquettes d\'origine attachées')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label toggle-label">
                                    <input type="checkbox" name="return_original_packaging" value="1"
                                        <?= $shopSettings->get('return_original_packaging', true) ? 'checked' : '' ?>>
                                    <span class="toggle-switch"></span>
                                    Emballage d'origine requis
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Produit complet (accessoires à retourner)</label>
                                <textarea name="return_complete_product" class="form-input" rows="2"><?= h($shopSettings->get('return_complete_product', 'Le produit doit être retourné complet avec tous ses accessoires (housses, étiquettes, etc.)')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Processus de retour -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">🔄 Processus de retour</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Comment demander un retour ?</label>
                                <select name="return_request_method" class="form-input">
                                    <option value="email" <?= $shopSettings->get('return_request_method') === 'email' ? 'selected' : '' ?>>Par email</option>
                                    <option value="formulaire" <?= $shopSettings->get('return_request_method') === 'formulaire' ? 'selected' : '' ?>>Via formulaire en ligne</option>
                                    <option value="compte" <?= $shopSettings->get('return_request_method') === 'compte' ? 'selected' : '' ?>>Depuis l'espace client</option>
                                    <option value="telephone" <?= $shopSettings->get('return_request_method') === 'telephone' ? 'selected' : '' ?>>Par téléphone</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Instructions de demande de retour</label>
                                <textarea name="return_request_info" class="form-input" rows="3"><?= h($shopSettings->get('return_request_info', 'Envoyez un email avec votre numéro de commande et les articles à retourner. Vous recevrez sous 48h les instructions et l\'étiquette de retour.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label toggle-label">
                                    <input type="checkbox" name="return_label_provided" value="1"
                                        <?= $shopSettings->get('return_label_provided', true) ? 'checked' : '' ?>>
                                    <span class="toggle-switch"></span>
                                    Étiquette de retour fournie
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Points de dépôt</label>
                                <input type="text" name="return_drop_points" class="form-input"
                                       placeholder="Bureau de poste, points relais..."
                                       value="<?= h($shopSettings->get('return_drop_points', 'Bureau de poste, points relais Mondial Relay, Colissimo')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Adresse de retour (si différente du siège)</label>
                                <textarea name="return_address" class="form-input" rows="2"
                                    placeholder="Laissez vide pour utiliser l'adresse du siège social"><?= h($shopSettings->get('return_address', '')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Remboursement -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">💰 Remboursement</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Délai de remboursement</label>
                                <input type="text" name="return_refund_delay" class="form-input"
                                       placeholder="Ex: 14 jours après réception"
                                       value="<?= h($shopSettings->get('return_refund_delay', '14 jours')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mode de remboursement</label>
                                <textarea name="return_refund_method" class="form-input" rows="2"><?= h($shopSettings->get('return_refund_method', 'Le remboursement est effectué sur le même moyen de paiement utilisé lors de la commande.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Remboursement des frais de port</label>
                                <textarea name="return_shipping_refund" class="form-input" rows="2"><?= h($shopSettings->get('return_shipping_refund', 'Les frais de livraison initiaux sont remboursés uniquement en cas de retour de la totalité de la commande.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Conditions de remboursement partiel</label>
                                <textarea name="return_partial_refund" class="form-input" rows="2"><?= h($shopSettings->get('return_partial_refund', 'En cas de produit retourné incomplet ou endommagé, un remboursement partiel pourra être appliqué.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Échange -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">🔁 Échange</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label toggle-label">
                                    <input type="checkbox" name="return_exchange_available" value="1"
                                        <?= $shopSettings->get('return_exchange_available', true) ? 'checked' : '' ?>>
                                    <span class="toggle-switch"></span>
                                    Échange disponible (en plus du remboursement)
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Conditions d'échange</label>
                                <textarea name="return_exchange_info" class="form-input" rows="2"><?= h($shopSettings->get('return_exchange_info', 'L\'échange est possible sous réserve de disponibilité. Contactez-nous pour vérifier les stocks avant de retourner l\'article.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Procédure échange de taille</label>
                                <textarea name="return_size_exchange" class="form-input" rows="2"><?= h($shopSettings->get('return_size_exchange', 'Pour un échange de taille, retournez l\'article et passez une nouvelle commande. Vous serez remboursé dès réception du retour.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Produits défectueux -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">⚠️ Produits défectueux / Erreur de livraison</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Politique produits défectueux</label>
                                <textarea name="return_defective_policy" class="form-input" rows="2"><?= h($shopSettings->get('return_defective_policy', 'Si vous recevez un produit défectueux ou non conforme, contactez-nous dans les 48h suivant la réception avec des photos du défaut.')) ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Délai de signalement</label>
                                    <input type="text" name="return_defective_delay" class="form-input"
                                           value="<?= h($shopSettings->get('return_defective_delay', '48 heures')) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Preuves demandées</label>
                                    <input type="text" name="return_defective_evidence" class="form-input"
                                           placeholder="Photos du produit, de l'emballage..."
                                           value="<?= h($shopSettings->get('return_defective_evidence', 'photos du produit et du défaut, photo de l\'emballage')) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Résolution (défaut confirmé)</label>
                                <textarea name="return_defective_resolution" class="form-input" rows="2"><?= h($shopSettings->get('return_defective_resolution', 'Remplacement du produit ou remboursement intégral à votre choix, frais de retour pris en charge.')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Erreur de livraison (mauvais article)</label>
                                <textarea name="return_wrong_item_policy" class="form-input" rows="2"><?= h($shopSettings->get('return_wrong_item_policy', 'Si vous recevez un article différent de votre commande, contactez-nous immédiatement. Nous organisons le retour à nos frais et vous envoyons le bon article en priorité.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Enregistrer la politique de retour
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <?php endif; ?>

            </div>
        </main>
    </div>

    <style>
        .alert { padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); font-weight: 500; }
        .alert-success { background: rgba(61, 255, 192, 0.15); color: var(--mint-dark); border-left: 4px solid var(--mint-main); }
        .alert-error { background: rgba(255, 105, 180, 0.15); color: var(--pink-dark); border-left: 4px solid var(--pink-main); }

        /* Tabs */
        .settings-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--gray-light);
            padding-bottom: 0;
        }
        .tab {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            color: var(--gray);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab:hover {
            color: var(--pink-main);
        }
        .tab.active {
            color: var(--pink-main);
            border-bottom-color: var(--pink-main);
        }
        .tab svg {
            opacity: 0.7;
        }
        .tab.active svg {
            opacity: 1;
        }

        /* Cards */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: var(--spacing-lg);
        }
        .data-card {
            margin-bottom: 24px;
        }
        .card-body {
            padding: 24px;
        }
        .data-card-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--black-soft);
            font-size: 14px;
        }
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            font-size: 14px;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255,105,180,0.1);
        }
        .form-hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: var(--gray);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-light);
        }
        .btn-lg {
            padding: 14px 28px;
            font-size: 15px;
        }
        .font-mono {
            font-family: 'SF Mono', 'Fira Code', 'Monaco', monospace;
            font-size: 13px;
        }

        /* Toggle */
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            user-select: none;
        }
        .toggle-label input {
            display: none;
        }
        .toggle-switch {
            width: 48px;
            height: 26px;
            background: #ddd;
            border-radius: 13px;
            position: relative;
            transition: all 0.2s;
        }
        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .toggle-label input:checked + .toggle-switch {
            background: var(--gradient-mint);
        }
        .toggle-label input:checked + .toggle-switch::after {
            left: 25px;
        }

        /* Info boxes */
        .info-box {
            padding: 16px 20px;
            background: linear-gradient(135deg, rgba(255,105,180,0.08) 0%, rgba(61,255,192,0.08) 100%);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--pink-main);
            font-size: 13px;
            color: var(--black-soft);
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .info-box a {
            color: var(--pink-main);
            font-weight: 600;
        }
        .info-box-blue {
            background: linear-gradient(135deg, rgba(99,91,255,0.08) 0%, rgba(99,91,255,0.04) 100%);
            border-left-color: #635BFF;
        }
        .info-box-cyan {
            background: linear-gradient(135deg, rgba(0,184,212,0.08) 0%, rgba(0,184,212,0.04) 100%);
            border-left-color: #00B8D4;
        }
        .info-box-green {
            background: linear-gradient(135deg, rgba(11,153,110,0.08) 0%, rgba(11,153,110,0.04) 100%);
            border-left-color: #0B996E;
        }
        .info-box-whatsapp {
            background: linear-gradient(135deg, rgba(37,211,102,0.08) 0%, rgba(37,211,102,0.04) 100%);
            border-left-color: #25D366;
        }

        /* Usage stats */
        .usage-stats {
            background: var(--gray-lighter);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 24px;
        }
        .usage-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .usage-count {
            font-weight: 600;
        }
        .usage-bar {
            height: 8px;
            background: rgba(0,0,0,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        .usage-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Badge variants */
        .badge-green {
            background: linear-gradient(135deg, #0B996E 0%, #0d7a58 100%);
            color: white;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Dividers */
        .section-divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: var(--gray);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section-divider::before,
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gray-light);
        }
        .section-divider span {
            padding: 0 16px;
        }

        /* Info row */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-light);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 500;
            color: var(--gray);
        }
        .info-value {
            font-weight: 600;
            color: var(--black-soft);
        }

        /* Sub-tabs */
        .sub-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .sub-tab {
            padding: 10px 18px;
            background: var(--gray-lighter);
            border-radius: var(--radius-md);
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .sub-tab:hover {
            background: var(--pink-light);
            color: var(--pink-dark);
        }
        .sub-tab.active {
            background: var(--gradient-pink);
            color: white;
        }

        /* Payment methods grid */
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }
        .payment-method-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: var(--gray-lighter);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .payment-method-item:hover {
            border-color: var(--pink-light);
        }
        .payment-method-item input {
            width: 18px;
            height: 18px;
            accent-color: var(--pink-main);
        }
        .payment-method-item input:checked + .payment-icon {
            opacity: 1;
        }
        .payment-method-item .payment-icon {
            width: 40px;
            height: 26px;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 800;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .payment-method-item span:last-child {
            font-weight: 500;
            color: var(--black-soft);
        }

        /* Badge config row */
        .badge-config-row {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            padding: 16px;
            background: var(--gray-lighter);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
        }
        .badge-config-row .form-group {
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .settings-tabs {
                flex-wrap: wrap;
            }
            .tab {
                padding: 12px 16px;
                font-size: 13px;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .settings-grid {
                grid-template-columns: 1fr;
            }
            .badge-config-row {
                flex-direction: column;
                align-items: stretch;
            }
            .badge-config-row .form-group {
                flex: 1 !important;
            }
        }
    </style>
</body>
</html>
