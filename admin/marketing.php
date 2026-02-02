<?php
/**
 * PERSONNALY - Admin : Marketing
 * Envoi de campagnes email (Brevo) et WhatsApp
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/Settings.php';
require_once __DIR__ . '/../app/services/BrevoService.php';
require_once __DIR__ . '/../app/services/WhatsAppService.php';

Auth::requireAdmin();

$orderModel = new Order();
$pendingOrders = $orderModel->countNew();
$user = Auth::getUser();

$brevoService = new BrevoService();
$whatsappService = new WhatsAppService();

$success = '';
$error = '';
$result = null;

// Traitement des envois
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {

    // Test email
    if (isset($_POST['test_email'])) {
        $email = post('test_email_to', '');
        $subject = post('test_email_subject', 'Test PERSONNALY');
        $message = post('test_email_message', '');

        if (empty($email)) {
            $error = 'Veuillez entrer une adresse email.';
        } else {
            $result = $brevoService->sendEmail([
                'to' => [$email],
                'subject' => $subject,
                'html' => nl2br(h($message)),
                'text' => $message
            ]);

            if ($result['success']) {
                $success = "Email de test envoyé à {$email} !";
            } else {
                $error = 'Erreur: ' . ($result['error'] ?? 'Erreur inconnue');
            }
        }
    }

    // Test WhatsApp
    if (isset($_POST['test_whatsapp'])) {
        $phone = post('test_whatsapp_to', '');
        $message = post('test_whatsapp_message', '');

        if (empty($phone)) {
            $error = 'Veuillez entrer un numéro de téléphone.';
        } else {
            $result = $whatsappService->sendTextMessage($phone, $message);

            if ($result['success']) {
                $success = "Message WhatsApp envoyé à {$phone} !";
            } else {
                $error = 'Erreur: ' . ($result['error'] ?? 'Erreur inconnue');
            }
        }
    }
}

// Récupérer les statistiques
$brevoStats = $brevoService->getStats();
$whatsappStats = $whatsappService->getStats();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing - PERSONNALY Admin</title>
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
                <h1 class="page-title"><span>Marketing</span></h1>
                <a href="settings.php?tab=marketing" class="btn btn-outline">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    Paramètres API
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card stat-brevo">
                    <div class="stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Brevo (Email)</div>
                        <div class="stat-value"><?= $brevoStats['daily_remaining'] ?> <small>/ <?= $brevoStats['daily_limit'] ?></small></div>
                        <div class="stat-sublabel">emails restants aujourd'hui</div>
                    </div>
                    <div class="stat-status <?= $brevoStats['enabled'] ? 'active' : 'inactive' ?>">
                        <?= $brevoStats['enabled'] ? 'Actif' : 'Inactif' ?>
                    </div>
                </div>

                <div class="stat-card stat-whatsapp">
                    <div class="stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">WhatsApp</div>
                        <div class="stat-value"><?= $whatsappStats['monthly_remaining'] ?> <small>/ <?= $whatsappStats['monthly_limit'] ?></small></div>
                        <div class="stat-sublabel">messages restants ce mois</div>
                    </div>
                    <div class="stat-status <?= $whatsappStats['enabled'] ? 'active' : 'inactive' ?>">
                        <?= $whatsappStats['enabled'] ? 'Actif' : 'Inactif' ?>
                    </div>
                </div>
            </div>

            <!-- Test Forms -->
            <div class="marketing-grid">
                <!-- Test Email -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #0B996E;">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            Envoyer un email de test
                        </h3>
                    </div>
                    <?php if ($brevoStats['enabled']): ?>
                    <form method="post" class="card-body">
                        <?= csrfField() ?>
                        <input type="hidden" name="test_email" value="1">

                        <div class="form-group">
                            <label class="form-label">Email destinataire</label>
                            <input type="email" name="test_email_to" class="form-input"
                                   placeholder="test@example.com" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sujet</label>
                            <input type="text" name="test_email_subject" class="form-input"
                                   value="Test PERSONNALY" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea name="test_email_message" class="form-input" rows="4"
                                      placeholder="Votre message..." required>Ceci est un email de test envoyé depuis PERSONNALY.</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Envoyer l'email
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="card-body">
                        <div class="disabled-notice">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                            </svg>
                            <p>Brevo n'est pas configuré</p>
                            <a href="settings.php?tab=marketing" class="btn btn-outline btn-sm">Configurer</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Test WhatsApp -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #25D366;">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                            </svg>
                            Envoyer un WhatsApp de test
                        </h3>
                    </div>
                    <?php if ($whatsappStats['enabled']): ?>
                    <form method="post" class="card-body">
                        <?= csrfField() ?>
                        <input type="hidden" name="test_whatsapp" value="1">

                        <div class="form-group">
                            <label class="form-label">Numéro de téléphone</label>
                            <input type="tel" name="test_whatsapp_to" class="form-input"
                                   placeholder="+33612345678" required>
                            <small class="form-hint">Format international avec indicatif pays</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea name="test_whatsapp_message" class="form-input" rows="4"
                                      placeholder="Votre message..." required>Ceci est un message de test envoyé depuis PERSONNALY.</textarea>
                        </div>

                        <div class="info-box info-box-warning">
                            <strong>Note :</strong> Pour envoyer un message à un nouveau contact, vous devez d'abord utiliser un template approuvé par Meta. Ce test ne fonctionnera qu'avec des contacts qui ont déjà initié une conversation.
                        </div>

                        <button type="submit" class="btn btn-primary btn-whatsapp">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Envoyer le message
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="card-body">
                        <div class="disabled-notice">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                            </svg>
                            <p>WhatsApp n'est pas configuré</p>
                            <a href="settings.php?tab=marketing" class="btn btn-outline btn-sm">Configurer</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info Section -->
            <div class="data-card">
                <div class="data-card-header">
                    <h3 class="data-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Informations importantes
                    </h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <h4>Brevo (Email)</h4>
                            <ul>
                                <li>300 emails gratuits par jour</li>
                                <li>Compteur réinitialisé à minuit</li>
                                <li>L'email expéditeur doit être vérifié</li>
                                <li>Idéal pour newsletters et confirmations</li>
                            </ul>
                        </div>
                        <div class="info-item">
                            <h4>WhatsApp Business</h4>
                            <ul>
                                <li>1000 conversations gratuites par mois</li>
                                <li>Templates requis pour initier un contact</li>
                                <li>Messages libres dans une conversation active (24h)</li>
                                <li>Idéal pour notifications urgentes</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <style>
        .alert { padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); font-weight: 500; }
        .alert-success { background: rgba(61, 255, 192, 0.15); color: var(--mint-dark); border-left: 4px solid var(--mint-main); }
        .alert-error { background: rgba(255, 105, 180, 0.15); color: var(--pink-dark); border-left: 4px solid var(--pink-main); }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow-soft);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        .stat-brevo::before { background: #0B996E; }
        .stat-whatsapp::before { background: #25D366; }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-brevo .stat-icon {
            background: rgba(11, 153, 110, 0.1);
            color: #0B996E;
        }
        .stat-whatsapp .stat-icon {
            background: rgba(37, 211, 102, 0.1);
            color: #25D366;
        }
        .stat-content { flex: 1; }
        .stat-label {
            font-size: 13px;
            color: var(--gray);
            font-weight: 500;
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--black-soft);
        }
        .stat-value small {
            font-size: 14px;
            color: var(--gray);
            font-weight: 500;
        }
        .stat-sublabel {
            font-size: 12px;
            color: var(--gray);
            margin-top: 2px;
        }
        .stat-status {
            position: absolute;
            top: 16px;
            right: 16px;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .stat-status.active {
            background: rgba(61, 255, 192, 0.2);
            color: var(--mint-dark);
        }
        .stat-status.inactive {
            background: rgba(0,0,0,0.05);
            color: var(--gray);
        }

        /* Marketing Grid */
        .marketing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Cards */
        .data-card {
            margin-bottom: 0;
        }
        .card-body {
            padding: 24px;
        }
        .data-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
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
            font-family: inherit;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255,105,180,0.1);
        }
        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }
        .form-hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: var(--gray);
        }

        /* Buttons */
        .btn-whatsapp {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        }
        .btn-whatsapp:hover {
            background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
        }

        /* Info boxes */
        .info-box {
            padding: 14px 18px;
            background: linear-gradient(135deg, rgba(255,105,180,0.08) 0%, rgba(61,255,192,0.08) 100%);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--pink-main);
            font-size: 13px;
            color: var(--black-soft);
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .info-box-warning {
            background: linear-gradient(135deg, rgba(255,193,7,0.1) 0%, rgba(255,152,0,0.08) 100%);
            border-left-color: #FFC107;
        }

        /* Disabled notice */
        .disabled-notice {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        .disabled-notice svg {
            opacity: 0.3;
            margin-bottom: 16px;
        }
        .disabled-notice p {
            margin-bottom: 16px;
            font-weight: 500;
        }

        /* Info grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        .info-item h4 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--black-soft);
        }
        .info-item ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .info-item li {
            font-size: 13px;
            color: var(--gray);
            padding: 6px 0;
            padding-left: 20px;
            position: relative;
        }
        .info-item li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 12px;
            width: 6px;
            height: 6px;
            background: var(--mint-main);
            border-radius: 50%;
        }

        @media (max-width: 768px) {
            .marketing-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
