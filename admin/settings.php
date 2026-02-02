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

Auth::requireAdmin();

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
                                    <label class="form-label">Utilisateur API</label>
                                    <input type="text" name="boxtal_user" class="form-input"
                                           placeholder="Votre login Boxtal"
                                           value="<?= h($shippingSettings['boxtal_user']['value'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Clé API</label>
                                    <input type="password" name="boxtal_api_key" class="form-input"
                                           placeholder="Votre clé secrète"
                                           value="<?= h($shippingSettings['boxtal_api_key']['value'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Poids par défaut par article (grammes)</label>
                                <input type="number" name="boxtal_default_weight" class="form-input" style="max-width: 200px;"
                                       value="<?= h($shippingSettings['boxtal_default_weight']['value'] ?? '500') ?>">
                                <small class="form-hint">Utilisé si le poids du produit n'est pas défini</small>
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
        }
    </style>
</body>
</html>
