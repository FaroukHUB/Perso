<?php
/**
 * PERSONNALY - Admin : Éditeur de campagne email
 * Inspiré de Brevo - Éditeur complet avec rich text, destinataires, aperçu
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Settings.php';
require_once __DIR__ . '/../app/services/BrevoService.php';

Auth::requireAdmin();

$orderModel = new Order();
$pendingOrders = $orderModel->countNew();
$userAuth = Auth::getUser();
$db = Database::getInstance();
$brevoService = new BrevoService();
$brevoStats = $brevoService->getStats();
$userModel = new User();

// Charger ou créer une campagne
$campaignId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$campaign = null;
$isEdit = false;

if ($campaignId) {
    $stmt = $db->prepare('SELECT * FROM email_campaigns WHERE id = ?');
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        redirect('/admin/campaigns.php?error=Campagne introuvable');
    }
    $isEdit = true;
}

// Récupérer les contacts disponibles
$clients = $userModel->findAll();
$clientsCount = count($clients);

// Abonnés newsletter
$newsletterCount = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'");
    $newsletterCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$success = $_GET['success'] ?? '';
$error = '';

// === AJAX: Upload d'image ===
if (isPost() && post('action') === 'upload_campaign_image') {
    header('Content-Type: application/json');
    if (!verifyCsrf(post('csrf_token', ''))) {
        echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
        exit;
    }
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../public/uploads/campaigns/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Format non supporté']);
            exit;
        }
        $filename = 'camp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename)) {
            echo json_encode(['success' => true, 'url' => '/public/uploads/campaigns/' . $filename]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur upload']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Aucun fichier']);
    }
    exit;
}

// === AJAX: Récupérer les destinataires ===
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_recipients') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? 'all_clients';
    $recipients = [];

    if ($type === 'all_clients' || $type === 'custom') {
        $stmt = $db->query("SELECT id, email, role, created_at FROM users WHERE role = 'client' ORDER BY email ASC");
        $recipients['clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($type === 'newsletter' || $type === 'custom') {
        try {
            $stmt = $db->query("SELECT id, email, source, created_at FROM newsletter_subscribers WHERE status = 'active' ORDER BY email ASC");
            $recipients['newsletter'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $recipients['newsletter'] = [];
        }
    }

    echo json_encode($recipients);
    exit;
}

// === AJAX: Envoyer la campagne ===
if (isPost() && post('action') === 'send_campaign') {
    header('Content-Type: application/json');
    if (!verifyCsrf(post('csrf_token', ''))) {
        echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
        exit;
    }

    $sendCampaignId = (int)post('campaign_id', 0);
    $stmt = $db->prepare('SELECT * FROM email_campaigns WHERE id = ?');
    $stmt->execute([$sendCampaignId]);
    $sendCampaign = $stmt->fetch();

    if (!$sendCampaign) {
        echo json_encode(['success' => false, 'error' => 'Campagne introuvable']);
        exit;
    }

    if (!$brevoService->isEnabled()) {
        echo json_encode(['success' => false, 'error' => 'Brevo n\'est pas configuré. Allez dans Paramètres > Marketing.']);
        exit;
    }

    // Récupérer les destinataires
    $stmt = $db->prepare('SELECT email, name FROM campaign_recipients WHERE campaign_id = ? AND status = "pending"');
    $stmt->execute([$sendCampaignId]);
    $pendingRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendingRecipients)) {
        echo json_encode(['success' => false, 'error' => 'Aucun destinataire en attente']);
        exit;
    }

    // Construire le HTML de l'email avec le template
    $emailHtml = buildCampaignEmail($sendCampaign);

    // Mettre à jour le statut
    $db->prepare('UPDATE email_campaigns SET status = "sending" WHERE id = ?')->execute([$sendCampaignId]);

    // Envoyer par lots
    $batchRecipients = [];
    foreach ($pendingRecipients as $r) {
        $batchRecipients[] = ['email' => $r['email'], 'name' => $r['name'] ?? ''];
    }

    $result = $brevoService->sendCampaign([
        'recipients' => $batchRecipients,
        'subject' => $sendCampaign['subject'],
        'html' => $emailHtml,
    ]);

    // Mettre à jour les stats
    $totalSent = $result['sent'] ?? 0;
    $db->prepare('UPDATE email_campaigns SET status = "sent", total_sent = ?, sent_at = NOW() WHERE id = ?')
        ->execute([$totalSent, $sendCampaignId]);

    // Marquer les destinataires comme envoyés
    $db->prepare('UPDATE campaign_recipients SET status = "sent", sent_at = NOW() WHERE campaign_id = ? AND status = "pending"')
        ->execute([$sendCampaignId]);

    echo json_encode([
        'success' => $result['success'] ?? false,
        'sent' => $totalSent,
        'total' => count($pendingRecipients),
        'errors' => $result['errors'] ?? [],
    ]);
    exit;
}

// === AJAX: Envoyer un test ===
if (isPost() && post('action') === 'send_test') {
    header('Content-Type: application/json');
    if (!verifyCsrf(post('csrf_token', ''))) {
        echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
        exit;
    }

    $testEmail = trim(post('test_email', ''));
    $testCampaignId = (int)post('campaign_id', 0);

    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        exit;
    }

    $stmt = $db->prepare('SELECT * FROM email_campaigns WHERE id = ?');
    $stmt->execute([$testCampaignId]);
    $testCampaign = $stmt->fetch();

    if (!$testCampaign) {
        echo json_encode(['success' => false, 'error' => 'Campagne introuvable']);
        exit;
    }

    $emailHtml = buildCampaignEmail($testCampaign);

    $result = $brevoService->sendEmail([
        'to' => [$testEmail],
        'subject' => '[TEST] ' . $testCampaign['subject'],
        'html' => $emailHtml,
    ]);

    echo json_encode($result);
    exit;
}

// === Sauvegarde de la campagne ===
if (isPost() && post('action') === 'save') {
    if (verifyCsrf(post('csrf_token', ''))) {
        $data = [
            'name' => trim(post('name', '')),
            'subject' => trim(post('subject', '')),
            'preheader' => trim(post('preheader', '')),
            'content' => post('content', ''),
            'sender_name' => trim(post('sender_name', '')) ?: $brevoStats['sender_name'],
            'sender_email' => trim(post('sender_email', '')) ?: $brevoStats['sender_email'],
            'recipients_type' => post('recipients_type', 'all_clients'),
        ];

        if (empty($data['name'])) $data['name'] = 'Campagne du ' . date('d/m/Y H:i');

        if ($isEdit) {
            $fields = [];
            $values = [];
            foreach ($data as $k => $v) {
                $fields[] = "$k = ?";
                $values[] = $v;
            }
            $values[] = $campaignId;
            $db->prepare('UPDATE email_campaigns SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
        } else {
            $stmt = $db->prepare('INSERT INTO email_campaigns (name, subject, preheader, content, sender_name, sender_email, recipients_type) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(array_values($data));
            $campaignId = (int)$db->lastInsertId();
        }

        // Sauvegarder les destinataires si custom
        $selectedEmails = post('selected_recipients', '');
        if (!empty($selectedEmails)) {
            // Supprimer les anciens
            $db->prepare('DELETE FROM campaign_recipients WHERE campaign_id = ?')->execute([$campaignId]);

            $emails = array_filter(array_map('trim', explode(',', $selectedEmails)));
            $insertStmt = $db->prepare('INSERT INTO campaign_recipients (campaign_id, email, name) VALUES (?, ?, ?)');
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $insertStmt->execute([$campaignId, $email, '']);
                }
            }
            $recipientCount = count($emails);
            $db->prepare('UPDATE email_campaigns SET total_recipients = ? WHERE id = ?')->execute([$recipientCount, $campaignId]);
        } elseif ($data['recipients_type'] === 'all_clients') {
            $db->prepare('DELETE FROM campaign_recipients WHERE campaign_id = ?')->execute([$campaignId]);
            $allClients = $db->query("SELECT email FROM users WHERE role = 'client'")->fetchAll(PDO::FETCH_COLUMN);
            $insertStmt = $db->prepare('INSERT INTO campaign_recipients (campaign_id, email) VALUES (?, ?)');
            foreach ($allClients as $email) {
                $insertStmt->execute([$campaignId, $email]);
            }
            $db->prepare('UPDATE email_campaigns SET total_recipients = ? WHERE id = ?')->execute([count($allClients), $campaignId]);
        } elseif ($data['recipients_type'] === 'newsletter') {
            $db->prepare('DELETE FROM campaign_recipients WHERE campaign_id = ?')->execute([$campaignId]);
            try {
                $allSubs = $db->query("SELECT email FROM newsletter_subscribers WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
                $insertStmt = $db->prepare('INSERT INTO campaign_recipients (campaign_id, email) VALUES (?, ?)');
                foreach ($allSubs as $email) {
                    $insertStmt->execute([$campaignId, $email]);
                }
                $db->prepare('UPDATE email_campaigns SET total_recipients = ? WHERE id = ?')->execute([count($allSubs), $campaignId]);
            } catch (Exception $e) {}
        }

        redirect('/admin/campaign-editor.php?id=' . $campaignId . '&success=Campagne sauvegardée');
    }
}

// Recharger la campagne après save
if ($campaignId && !$campaign) {
    $stmt = $db->prepare('SELECT * FROM email_campaigns WHERE id = ?');
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();
    $isEdit = (bool)$campaign;
}

function buildCampaignEmail(array $campaign): string {
    $content = $campaign['content'] ?? '';
    return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($campaign['subject']) . '</title>
<style>
body { margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.email-wrapper { background-color: #f4f4f7; padding: 40px 20px; }
.email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.email-header { background: linear-gradient(135deg, #FF69B4 0%, #FF1493 100%); padding: 30px; text-align: center; }
.email-header h1 { color: white; margin: 0; font-size: 24px; font-weight: 700; }
.email-body { padding: 32px; line-height: 1.7; color: #333; font-size: 15px; }
.email-body h1, .email-body h2, .email-body h3 { color: #1a1a2e; }
.email-body a { color: #FF1493; }
.email-body img { max-width: 100%; height: auto; border-radius: 8px; }
.email-footer { background: #f8f9fa; padding: 24px 32px; text-align: center; font-size: 12px; color: #999; }
.email-footer a { color: #FF1493; text-decoration: none; }
</style>
</head>
<body>
<div class="email-wrapper">
<div class="email-container">
<div class="email-header">
<h1>PERSONNALY</h1>
</div>
<div class="email-body">' . $content . '</div>
<div class="email-footer">
<p>&copy; ' . date('Y') . ' PERSONNALY - Tous droits réservés</p>
<p><a href="https://personnaly.fr">personnaly.fr</a></p>
</div>
</div>
</div>
</body>
</html>';
}

$pageTitle = $isEdit ? 'Modifier la campagne' : 'Nouvelle campagne';
$currentPage = 'campaigns.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <!-- TinyMCE CDN -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content" style="padding-bottom: 100px;">
            <!-- Top bar -->
            <div class="editor-topbar">
                <div class="editor-topbar-left">
                    <a href="/admin/campaigns.php" class="editor-back">&larr; Campagnes</a>
                    <div class="editor-topbar-title">
                        <input type="text" id="campaignName" class="editor-name-input"
                               value="<?= h($campaign['name'] ?? '') ?>"
                               placeholder="Nom de la campagne...">
                        <?php if ($isEdit): ?>
                            <span class="editor-status-badge" style="background:<?= ['draft'=>'#6C757D','sent'=>'#10B981','sending'=>'#F59E0B','scheduled'=>'#3B82F6','paused'=>'#8B5CF6'][$campaign['status']] ?? '#999' ?>">
                                <?= ['draft'=>'Brouillon','sent'=>'Envoyée','sending'=>'En cours','scheduled'=>'Planifiée','paused'=>'En pause'][$campaign['status']] ?? $campaign['status'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="editor-topbar-right">
                    <button class="editor-btn editor-btn-ghost" onclick="saveCampaign()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Sauvegarder
                    </button>
                    <button class="editor-btn editor-btn-outline" onclick="showTestModal()">
                        Envoyer un test
                    </button>
                    <button class="editor-btn editor-btn-outline" onclick="showPreview()">
                        Aperçu
                    </button>
                    <?php if (!$isEdit || $campaign['status'] === 'draft'): ?>
                        <button class="editor-btn editor-btn-primary" onclick="showSendModal()">
                            Envoyer la campagne
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" style="margin: 20px 0;"><?= h($success) ?></div>
            <?php endif; ?>

            <!-- Étapes de la campagne style Brevo -->
            <div class="editor-steps">
                <div class="editor-step active" data-step="setup" onclick="goToStep('setup')">
                    <span class="step-number">1</span>
                    <span class="step-label">Configuration</span>
                </div>
                <div class="step-connector"></div>
                <div class="editor-step" data-step="content" onclick="goToStep('content')">
                    <span class="step-number">2</span>
                    <span class="step-label">Contenu</span>
                </div>
                <div class="step-connector"></div>
                <div class="editor-step" data-step="recipients" onclick="goToStep('recipients')">
                    <span class="step-number">3</span>
                    <span class="step-label">Destinataires</span>
                </div>
                <div class="step-connector"></div>
                <div class="editor-step" data-step="review" onclick="goToStep('review')">
                    <span class="step-number">4</span>
                    <span class="step-label">Résumé</span>
                </div>
            </div>

            <!-- STEP 1: Configuration -->
            <div class="step-panel active" id="panel-setup">
                <div class="step-panel-inner">
                    <h2 class="step-title">Configuration de la campagne</h2>
                    <p class="step-subtitle">Définissez le sujet et les paramètres d'envoi</p>

                    <div class="form-card">
                        <div class="form-card-title">Sujet du mail</div>
                        <div class="form-group">
                            <label class="form-label">Objet *</label>
                            <input type="text" id="campaignSubject" class="form-input form-input-lg"
                                   value="<?= h($campaign['subject'] ?? '') ?>"
                                   placeholder="Ex: Découvrez nos nouvelles créations !">
                            <small class="form-hint">C'est la première chose que vos contacts verront</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Texte d'aperçu (preheader)</label>
                            <input type="text" id="campaignPreheader" class="form-input"
                                   value="<?= h($campaign['preheader'] ?? '') ?>"
                                   placeholder="Texte affiché après l'objet dans la boîte de réception">
                            <small class="form-hint">Optionnel - s'affiche après l'objet dans certains clients mail</small>
                        </div>
                    </div>

                    <div class="form-card">
                        <div class="form-card-title">Expéditeur</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nom de l'expéditeur</label>
                                <input type="text" id="campaignSenderName" class="form-input"
                                       value="<?= h($campaign['sender_name'] ?? $brevoStats['sender_name']) ?>"
                                       placeholder="PERSONNALY">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email de l'expéditeur</label>
                                <input type="email" id="campaignSenderEmail" class="form-input"
                                       value="<?= h($campaign['sender_email'] ?? $brevoStats['sender_email']) ?>"
                                       placeholder="noreply@personnaly.fr">
                                <small class="form-hint">Doit être vérifié dans Brevo</small>
                            </div>
                        </div>
                    </div>

                    <div class="step-nav">
                        <div></div>
                        <button class="editor-btn editor-btn-primary" onclick="goToStep('content')">
                            Continuer vers le contenu &rarr;
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Contenu -->
            <div class="step-panel" id="panel-content">
                <div class="step-panel-inner step-panel-wide">
                    <h2 class="step-title">Contenu de votre email</h2>
                    <p class="step-subtitle">Rédigez votre message avec l'éditeur visuel</p>

                    <div class="editor-container">
                        <textarea id="campaignContent"><?= h($campaign['content'] ?? '') ?></textarea>
                    </div>

                    <div class="step-nav">
                        <button class="editor-btn editor-btn-ghost" onclick="goToStep('setup')">
                            &larr; Configuration
                        </button>
                        <button class="editor-btn editor-btn-primary" onclick="goToStep('recipients')">
                            Continuer vers les destinataires &rarr;
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Destinataires -->
            <div class="step-panel" id="panel-recipients">
                <div class="step-panel-inner">
                    <h2 class="step-title">Choisissez vos destinataires</h2>
                    <p class="step-subtitle">Sélectionnez à qui envoyer cette campagne</p>

                    <div class="recipients-options">
                        <label class="recipient-option <?= ($campaign['recipients_type'] ?? 'all_clients') === 'all_clients' ? 'active' : '' ?>" onclick="selectRecipientsType('all_clients')">
                            <input type="radio" name="recipients_type" value="all_clients" <?= ($campaign['recipients_type'] ?? 'all_clients') === 'all_clients' ? 'checked' : '' ?>>
                            <div class="recipient-option-icon">👥</div>
                            <div class="recipient-option-info">
                                <strong>Tous les clients</strong>
                                <span><?= $clientsCount ?> contact<?= $clientsCount > 1 ? 's' : '' ?></span>
                            </div>
                            <div class="recipient-check"></div>
                        </label>

                        <label class="recipient-option <?= ($campaign['recipients_type'] ?? '') === 'newsletter' ? 'active' : '' ?>" onclick="selectRecipientsType('newsletter')">
                            <input type="radio" name="recipients_type" value="newsletter" <?= ($campaign['recipients_type'] ?? '') === 'newsletter' ? 'checked' : '' ?>>
                            <div class="recipient-option-icon">📬</div>
                            <div class="recipient-option-info">
                                <strong>Abonnés newsletter</strong>
                                <span><?= $newsletterCount ?> abonné<?= $newsletterCount > 1 ? 's' : '' ?></span>
                            </div>
                            <div class="recipient-check"></div>
                        </label>

                        <label class="recipient-option <?= ($campaign['recipients_type'] ?? '') === 'custom' ? 'active' : '' ?>" onclick="selectRecipientsType('custom')">
                            <input type="radio" name="recipients_type" value="custom" <?= ($campaign['recipients_type'] ?? '') === 'custom' ? 'checked' : '' ?>>
                            <div class="recipient-option-icon">🎯</div>
                            <div class="recipient-option-info">
                                <strong>Sélection personnalisée</strong>
                                <span>Choisissez individuellement</span>
                            </div>
                            <div class="recipient-check"></div>
                        </label>
                    </div>

                    <!-- Liste de contacts pour sélection personnalisée -->
                    <div class="custom-recipients-panel" id="customRecipientsPanel" style="<?= ($campaign['recipients_type'] ?? '') === 'custom' ? '' : 'display:none;' ?>">
                        <div class="recipients-toolbar">
                            <input type="text" class="recipients-search" id="recipientSearch"
                                   placeholder="Rechercher un email..." oninput="filterRecipients()">
                            <div class="recipients-toolbar-actions">
                                <button class="editor-btn editor-btn-ghost" onclick="selectAllRecipients()">Tout sélectionner</button>
                                <button class="editor-btn editor-btn-ghost" onclick="deselectAllRecipients()">Tout décocher</button>
                            </div>
                        </div>
                        <div class="recipients-count" id="recipientsSelectedCount">0 sélectionné(s)</div>
                        <div class="recipients-list" id="recipientsList">
                            <div class="recipients-loading">Chargement des contacts...</div>
                        </div>
                    </div>

                    <div class="step-nav">
                        <button class="editor-btn editor-btn-ghost" onclick="goToStep('content')">
                            &larr; Contenu
                        </button>
                        <button class="editor-btn editor-btn-primary" onclick="goToStep('review')">
                            Continuer vers le résumé &rarr;
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 4: Résumé -->
            <div class="step-panel" id="panel-review">
                <div class="step-panel-inner">
                    <h2 class="step-title">Résumé de la campagne</h2>
                    <p class="step-subtitle">Vérifiez tout avant d'envoyer</p>

                    <div class="review-grid">
                        <div class="review-card">
                            <div class="review-card-icon">📧</div>
                            <div class="review-card-title">Sujet</div>
                            <div class="review-card-value" id="reviewSubject">-</div>
                        </div>
                        <div class="review-card">
                            <div class="review-card-icon">👤</div>
                            <div class="review-card-title">Expéditeur</div>
                            <div class="review-card-value" id="reviewSender">-</div>
                        </div>
                        <div class="review-card">
                            <div class="review-card-icon">👥</div>
                            <div class="review-card-title">Destinataires</div>
                            <div class="review-card-value" id="reviewRecipients">-</div>
                        </div>
                        <div class="review-card">
                            <div class="review-card-icon">📊</div>
                            <div class="review-card-title">Quota Brevo</div>
                            <div class="review-card-value" id="reviewQuota"><?= $brevoStats['daily_remaining'] ?>/<?= $brevoStats['daily_limit'] ?> restants</div>
                        </div>
                    </div>

                    <div class="review-preview-section">
                        <h3>Aperçu du contenu</h3>
                        <div class="review-preview-frame" id="reviewPreviewFrame"></div>
                    </div>

                    <div class="step-nav">
                        <button class="editor-btn editor-btn-ghost" onclick="goToStep('recipients')">
                            &larr; Destinataires
                        </button>
                        <div style="display:flex;gap:10px;">
                            <button class="editor-btn editor-btn-outline" onclick="showTestModal()">
                                Envoyer un test
                            </button>
                            <button class="editor-btn editor-btn-send" onclick="showSendModal()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                                Envoyer la campagne
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal: Aperçu -->
    <div class="camp-modal-overlay" id="previewModal" onclick="if(event.target===this)closeModal('previewModal')">
        <div class="camp-modal camp-modal-lg">
            <div class="camp-modal-header">
                <h3>Aperçu de l'email</h3>
                <div class="preview-device-toggle">
                    <button class="device-btn active" onclick="setPreviewDevice('desktop', this)">Desktop</button>
                    <button class="device-btn" onclick="setPreviewDevice('mobile', this)">Mobile</button>
                </div>
                <button class="camp-modal-close" onclick="closeModal('previewModal')">&times;</button>
            </div>
            <div class="camp-modal-body" style="background:#e5e5e5;padding:20px;">
                <iframe id="previewIframe" class="preview-iframe preview-desktop"></iframe>
            </div>
        </div>
    </div>

    <!-- Modal: Test -->
    <div class="camp-modal-overlay" id="testModal" onclick="if(event.target===this)closeModal('testModal')">
        <div class="camp-modal camp-modal-sm">
            <div class="camp-modal-header">
                <h3>Envoyer un email test</h3>
                <button class="camp-modal-close" onclick="closeModal('testModal')">&times;</button>
            </div>
            <div class="camp-modal-body">
                <div class="form-group">
                    <label class="form-label">Email de test</label>
                    <input type="email" id="testEmailInput" class="form-input" placeholder="votre@email.com"
                           value="<?= h($userAuth['email'] ?? '') ?>">
                    <small class="form-hint">Un email de test sera envoyé à cette adresse</small>
                </div>
                <div class="camp-modal-actions">
                    <button class="editor-btn editor-btn-ghost" onclick="closeModal('testModal')">Annuler</button>
                    <button class="editor-btn editor-btn-primary" id="sendTestBtn" onclick="sendTest()">Envoyer le test</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Envoi -->
    <div class="camp-modal-overlay" id="sendModal" onclick="if(event.target===this)closeModal('sendModal')">
        <div class="camp-modal camp-modal-sm">
            <div class="camp-modal-header" style="background:linear-gradient(135deg,#FF69B4,#FF1493);color:white;">
                <h3>Envoyer la campagne</h3>
                <button class="camp-modal-close" onclick="closeModal('sendModal')" style="color:white;background:rgba(255,255,255,0.2);">&times;</button>
            </div>
            <div class="camp-modal-body">
                <div class="send-confirm-info" id="sendConfirmInfo"></div>
                <div class="camp-modal-actions">
                    <button class="editor-btn editor-btn-ghost" onclick="closeModal('sendModal')">Annuler</button>
                    <button class="editor-btn editor-btn-send" id="confirmSendBtn" onclick="confirmSend()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        Confirmer l'envoi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* === Editor Top Bar === */
    .editor-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 0;
        margin-bottom: 8px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .editor-topbar-left { display: flex; align-items: center; gap: 16px; flex: 1; min-width: 0; }
    .editor-back {
        color: var(--gray);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        white-space: nowrap;
    }
    .editor-back:hover { color: var(--pink-main); }
    .editor-topbar-title { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
    .editor-name-input {
        border: none;
        background: none;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--black-soft);
        width: 100%;
        padding: 4px 0;
        outline: none;
        font-family: inherit;
    }
    .editor-name-input:focus { border-bottom: 2px solid var(--pink-main); }
    .editor-name-input::placeholder { color: #ccc; }
    .editor-status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        color: white;
        white-space: nowrap;
    }
    .editor-topbar-right { display: flex; gap: 8px; flex-wrap: wrap; }

    /* Editor Buttons */
    .editor-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border: none;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-family: inherit;
        white-space: nowrap;
    }
    .editor-btn-ghost { background: transparent; color: var(--gray); }
    .editor-btn-ghost:hover { background: var(--gray-light); color: var(--black-soft); }
    .editor-btn-outline { background: white; color: var(--black-soft); border: 2px solid #e5e5e5; }
    .editor-btn-outline:hover { border-color: var(--pink-main); color: var(--pink-main); }
    .editor-btn-primary { background: var(--gradient-pink); color: white; box-shadow: 0 4px 15px rgba(255,105,180,0.3); }
    .editor-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,105,180,0.4); }
    .editor-btn-send { background: linear-gradient(135deg, #10B981, #059669); color: white; box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
    .editor-btn-send:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.4); }

    /* === Steps === */
    .editor-steps {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0;
        margin: 20px 0 32px;
        padding: 16px 24px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .editor-step {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 20px;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .editor-step:hover { background: var(--gray-light); }
    .editor-step.active { background: rgba(255,105,180,0.1); }
    .editor-step.active .step-number { background: var(--gradient-pink); color: white; }
    .editor-step.completed .step-number { background: #10B981; color: white; }
    .step-number {
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        background: var(--gray-light);
        font-size: 13px;
        font-weight: 700;
        color: var(--gray);
    }
    .step-label { font-size: 14px; font-weight: 600; color: var(--black-soft); }
    .step-connector { width: 40px; height: 2px; background: #e5e5e5; }

    /* === Step Panels === */
    .step-panel { display: none; }
    .step-panel.active { display: block; }
    .step-panel-inner { max-width: 720px; margin: 0 auto; }
    .step-panel-wide { max-width: 960px; }
    .step-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 6px; }
    .step-subtitle { color: var(--gray); margin-bottom: 24px; font-size: 15px; }
    .step-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 32px; padding-top: 24px; border-top: 1px solid #eee; }

    /* Form cards */
    .form-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .form-card-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gray); margin-bottom: 16px; }
    .form-group { margin-bottom: 16px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    .form-input { width: 100%; padding: 12px 16px; border: 2px solid #e5e5e5; border-radius: 12px; font-size: 14px; font-family: inherit; transition: border-color 0.2s; box-sizing: border-box; }
    .form-input:focus { outline: none; border-color: var(--pink-main); }
    .form-input-lg { font-size: 16px; padding: 14px 18px; }
    .form-hint { font-size: 12px; color: var(--gray); margin-top: 4px; display: block; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    /* === Editor TinyMCE Container === */
    .editor-container {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }

    /* === Recipients === */
    .recipients-options { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
    .recipient-option {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: white;
        border: 2px solid transparent;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .recipient-option:hover { border-color: var(--pink-light); }
    .recipient-option.active { border-color: var(--pink-main); background: rgba(255,105,180,0.03); }
    .recipient-option input { display: none; }
    .recipient-option-icon { font-size: 2rem; }
    .recipient-option-info { flex: 1; }
    .recipient-option-info strong { display: block; font-size: 15px; margin-bottom: 2px; }
    .recipient-option-info span { font-size: 13px; color: var(--gray); }
    .recipient-check {
        width: 24px; height: 24px;
        border: 2px solid #e5e5e5;
        border-radius: 50%;
        transition: all 0.2s;
        position: relative;
    }
    .recipient-option.active .recipient-check {
        background: var(--pink-main);
        border-color: var(--pink-main);
    }
    .recipient-option.active .recipient-check::after {
        content: '✓';
        color: white;
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        font-size: 12px;
        font-weight: 700;
    }

    .custom-recipients-panel {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .recipients-toolbar { display: flex; gap: 12px; margin-bottom: 12px; align-items: center; flex-wrap: wrap; }
    .recipients-search {
        flex: 1; min-width: 200px;
        padding: 10px 16px;
        border: 2px solid #e5e5e5;
        border-radius: 50px;
        font-size: 14px;
        font-family: inherit;
    }
    .recipients-search:focus { outline: none; border-color: var(--pink-main); }
    .recipients-toolbar-actions { display: flex; gap: 6px; }
    .recipients-count { font-size: 13px; font-weight: 600; color: var(--pink-main); margin-bottom: 12px; }
    .recipients-list { max-height: 400px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; }
    .recipients-loading { text-align: center; padding: 40px; color: var(--gray); }
    .recipient-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 10px;
        cursor: pointer;
        transition: background 0.15s;
    }
    .recipient-row:hover { background: var(--gray-light); }
    .recipient-row.selected { background: rgba(255,105,180,0.06); }
    .recipient-row input[type="checkbox"] {
        width: 18px; height: 18px;
        accent-color: var(--pink-main);
        cursor: pointer;
    }
    .recipient-email { font-size: 14px; font-weight: 500; flex: 1; }
    .recipient-source { font-size: 11px; padding: 2px 8px; border-radius: 20px; background: var(--gray-light); color: var(--gray); }

    /* === Review === */
    .review-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 32px; }
    .review-card {
        background: white;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .review-card-icon { font-size: 1.5rem; margin-bottom: 8px; }
    .review-card-title { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gray); font-weight: 600; margin-bottom: 4px; }
    .review-card-value { font-size: 15px; font-weight: 700; color: var(--black-soft); word-break: break-all; }
    .review-preview-section h3 { margin-bottom: 16px; }
    .review-preview-frame {
        background: white;
        border-radius: 14px;
        padding: 24px;
        min-height: 200px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    /* === Modals === */
    .camp-modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.5); z-index: 9999;
        align-items: center; justify-content: center;
        padding: 20px; backdrop-filter: blur(4px);
    }
    .camp-modal-overlay.active { display: flex; }
    .camp-modal {
        background: white; border-radius: 20px;
        width: 100%; overflow: hidden;
        box-shadow: 0 25px 80px rgba(0,0,0,0.25);
        animation: campModalIn 0.3s ease;
    }
    .camp-modal-sm { max-width: 480px; }
    .camp-modal-lg { max-width: 900px; max-height: 90vh; display: flex; flex-direction: column; }
    @keyframes campModalIn {
        from { opacity: 0; transform: translateY(20px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .camp-modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 18px 24px; border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    .camp-modal-header h3 { font-size: 1.1rem; font-weight: 700; margin: 0; }
    .camp-modal-close {
        width: 32px; height: 32px; border: none; background: var(--gray-light);
        border-radius: 50%; font-size: 18px; cursor: pointer; color: var(--gray);
        display: flex; align-items: center; justify-content: center;
    }
    .camp-modal-close:hover { background: #EF4444; color: white; }
    .camp-modal-body { padding: 24px; overflow-y: auto; flex: 1; }
    .camp-modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

    /* Preview */
    .preview-device-toggle { display: flex; gap: 4px; background: var(--gray-light); border-radius: 8px; padding: 3px; }
    .device-btn {
        padding: 6px 14px; border: none; background: none;
        border-radius: 6px; font-size: 13px; font-weight: 500;
        cursor: pointer; transition: all 0.2s; font-family: inherit;
    }
    .device-btn.active { background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .preview-iframe {
        border: none; width: 100%; background: white;
        border-radius: 12px; display: block;
        transition: all 0.3s;
    }
    .preview-desktop { height: 600px; max-width: 100%; margin: 0 auto; }
    .preview-mobile { height: 600px; max-width: 375px; margin: 0 auto; }

    /* Send confirm */
    .send-confirm-info { padding: 20px; background: var(--gray-light); border-radius: 12px; line-height: 1.8; }
    .send-confirm-info strong { color: var(--pink-dark); }

    .alert { padding: 14px 20px; border-radius: 12px; font-weight: 500; }
    .alert-success { background: rgba(16,185,129,0.1); color: #059669; border-left: 4px solid #10B981; }

    @media (max-width: 768px) {
        .editor-topbar { flex-direction: column; align-items: stretch; }
        .editor-topbar-right { justify-content: flex-end; }
        .editor-steps { overflow-x: auto; justify-content: flex-start; }
        .form-row { grid-template-columns: 1fr; }
        .review-grid { grid-template-columns: 1fr 1fr; }
    }
    </style>

    <script>
    const csrfToken = '<?= generateCsrf() ?>';
    const campaignId = <?= $campaignId ?: 'null' ?>;
    let currentStep = 'setup';
    let allRecipients = [];
    let selectedRecipients = new Set();

    // Charger les destinataires existants
    <?php if ($isEdit):
        $stmt = $db->prepare('SELECT email FROM campaign_recipients WHERE campaign_id = ?');
        $stmt->execute([$campaignId]);
        $existingRecipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
    ?>
    <?= json_encode($existingRecipients) ?>.forEach(e => selectedRecipients.add(e));
    <?php endif; ?>

    // === TinyMCE Init ===
    document.addEventListener('DOMContentLoaded', () => {
        tinymce.init({
            selector: '#campaignContent',
            height: 500,
            menubar: 'file edit view insert format tools table',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'
            ],
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media emoticons | removeformat code fullscreen',
            content_style: `
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.7; color: #333; padding: 16px; max-width: 600px; margin: 0 auto; }
                h1 { font-size: 28px; color: #1a1a2e; }
                h2 { font-size: 22px; color: #1a1a2e; }
                h3 { font-size: 18px; color: #1a1a2e; }
                a { color: #FF1493; }
                img { max-width: 100%; height: auto; border-radius: 8px; }
                .btn-cta { display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #FF69B4, #FF1493); color: white !important; text-decoration: none; border-radius: 50px; font-weight: 700; font-size: 16px; }
            `,
            block_formats: 'Paragraphe=p; Titre 1=h1; Titre 2=h2; Titre 3=h3; Citation=blockquote',
            font_size_formats: '10px 12px 14px 16px 18px 20px 24px 28px 32px 36px 48px',
            color_map: [
                'FF1493', 'Rose PERSONNALY',
                'FF69B4', 'Rose clair',
                '3DFFC0', 'Vert menthe',
                '1A1A2E', 'Noir doux',
                '333333', 'Texte principal',
                '6C757D', 'Gris',
                'FFFFFF', 'Blanc',
                '000000', 'Noir',
                '3B82F6', 'Bleu',
                '10B981', 'Vert',
                'F59E0B', 'Orange',
                'EF4444', 'Rouge',
            ],
            images_upload_handler: (blobInfo) => {
                return new Promise((resolve, reject) => {
                    const formData = new FormData();
                    formData.append('file', blobInfo.blob(), blobInfo.filename());
                    formData.append('csrf_token', csrfToken);
                    formData.append('action', 'upload_campaign_image');

                    fetch(window.location.pathname + (campaignId ? '?id=' + campaignId : ''), {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) resolve(data.url);
                        else reject(data.error);
                    })
                    .catch(err => reject('Upload échoué'));
                });
            },
            language: 'fr_FR',
            promotion: false,
            branding: false,
        });
    });

    // === Steps Navigation ===
    function goToStep(step) {
        // Vérification des champs
        if (step !== 'setup' && !document.getElementById('campaignSubject').value.trim()) {
            goToStep('setup');
            document.getElementById('campaignSubject').focus();
            document.getElementById('campaignSubject').style.borderColor = '#EF4444';
            setTimeout(() => document.getElementById('campaignSubject').style.borderColor = '', 2000);
            return;
        }

        currentStep = step;

        // Active step indicator
        document.querySelectorAll('.editor-step').forEach(el => {
            el.classList.remove('active');
            if (el.dataset.step === step) el.classList.add('active');
        });

        // Show panel
        document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('panel-' + step).classList.add('active');

        // Step-specific actions
        if (step === 'recipients' && allRecipients.length === 0) {
            loadRecipients();
        }
        if (step === 'review') {
            updateReview();
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // === Recipients ===
    function selectRecipientsType(type) {
        document.querySelectorAll('.recipient-option').forEach(el => el.classList.remove('active'));
        event.currentTarget.closest('.recipient-option').classList.add('active');
        document.querySelector('input[name="recipients_type"][value="' + type + '"]').checked = true;

        const panel = document.getElementById('customRecipientsPanel');
        panel.style.display = type === 'custom' ? '' : 'none';

        if (type === 'custom' && allRecipients.length === 0) {
            loadRecipients();
        }
    }

    function loadRecipients() {
        const list = document.getElementById('recipientsList');
        list.innerHTML = '<div class="recipients-loading">Chargement des contacts...</div>';

        fetch('/admin/campaign-editor.php?ajax=get_recipients&type=custom')
            .then(r => r.json())
            .then(data => {
                allRecipients = [];
                (data.clients || []).forEach(c => {
                    allRecipients.push({ email: c.email, source: 'Client', date: c.created_at });
                });
                (data.newsletter || []).forEach(n => {
                    if (!allRecipients.find(r => r.email === n.email)) {
                        allRecipients.push({ email: n.email, source: 'Newsletter', date: n.created_at });
                    }
                });
                renderRecipients();
            })
            .catch(() => {
                list.innerHTML = '<div class="recipients-loading" style="color:#EF4444;">Erreur de chargement</div>';
            });
    }

    function renderRecipients() {
        const list = document.getElementById('recipientsList');
        const search = document.getElementById('recipientSearch').value.toLowerCase();
        const filtered = allRecipients.filter(r => r.email.toLowerCase().includes(search));

        if (filtered.length === 0) {
            list.innerHTML = '<div class="recipients-loading">Aucun contact trouvé</div>';
            return;
        }

        list.innerHTML = filtered.map(r => {
            const checked = selectedRecipients.has(r.email);
            return `<label class="recipient-row ${checked ? 'selected' : ''}">
                <input type="checkbox" ${checked ? 'checked' : ''} onchange="toggleRecipient('${r.email.replace(/'/g, "\\'")}', this)">
                <span class="recipient-email">${esc(r.email)}</span>
                <span class="recipient-source">${esc(r.source)}</span>
            </label>`;
        }).join('');

        updateRecipientsCount();
    }

    function toggleRecipient(email, checkbox) {
        if (checkbox.checked) {
            selectedRecipients.add(email);
            checkbox.closest('.recipient-row').classList.add('selected');
        } else {
            selectedRecipients.delete(email);
            checkbox.closest('.recipient-row').classList.remove('selected');
        }
        updateRecipientsCount();
    }

    function selectAllRecipients() {
        allRecipients.forEach(r => selectedRecipients.add(r.email));
        renderRecipients();
    }

    function deselectAllRecipients() {
        selectedRecipients.clear();
        renderRecipients();
    }

    function filterRecipients() { renderRecipients(); }

    function updateRecipientsCount() {
        document.getElementById('recipientsSelectedCount').textContent = selectedRecipients.size + ' sélectionné(s)';
    }

    function getRecipientsCount() {
        const type = document.querySelector('input[name="recipients_type"]:checked')?.value || 'all_clients';
        if (type === 'all_clients') return <?= $clientsCount ?>;
        if (type === 'newsletter') return <?= $newsletterCount ?>;
        return selectedRecipients.size;
    }

    // === Save ===
    function saveCampaign() {
        const content = tinymce.get('campaignContent') ? tinymce.get('campaignContent').getContent() : '';
        const type = document.querySelector('input[name="recipients_type"]:checked')?.value || 'all_clients';

        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const fields = {
            'action': 'save',
            'csrf_token': csrfToken,
            'name': document.getElementById('campaignName').value,
            'subject': document.getElementById('campaignSubject').value,
            'preheader': document.getElementById('campaignPreheader').value,
            'content': content,
            'sender_name': document.getElementById('campaignSenderName').value,
            'sender_email': document.getElementById('campaignSenderEmail').value,
            'recipients_type': type,
            'selected_recipients': type === 'custom' ? Array.from(selectedRecipients).join(',') : '',
        };

        for (const [k, v] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = k;
            input.value = v;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }

    // === Preview ===
    function showPreview() {
        const content = tinymce.get('campaignContent') ? tinymce.get('campaignContent').getContent() : '';
        const subject = document.getElementById('campaignSubject').value || 'Sans sujet';

        const html = buildPreviewHtml(subject, content);
        const iframe = document.getElementById('previewIframe');
        iframe.srcdoc = html;

        document.getElementById('previewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function buildPreviewHtml(subject, content) {
        return `<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
        <style>
        body{margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}
        .ew{background:#f4f4f7;padding:40px 20px;}
        .ec{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
        .eh{background:linear-gradient(135deg,#FF69B4,#FF1493);padding:30px;text-align:center;}
        .eh h1{color:white;margin:0;font-size:24px;font-weight:700;}
        .eb{padding:32px;line-height:1.7;color:#333;font-size:15px;}
        .eb h1,.eb h2,.eb h3{color:#1a1a2e;}
        .eb a{color:#FF1493;}
        .eb img{max-width:100%;height:auto;border-radius:8px;}
        .ef{background:#f8f9fa;padding:24px 32px;text-align:center;font-size:12px;color:#999;}
        .ef a{color:#FF1493;text-decoration:none;}
        </style></head><body>
        <div class="ew"><div class="ec">
        <div class="eh"><h1>PERSONNALY</h1></div>
        <div class="eb">${content}</div>
        <div class="ef"><p>&copy; ${new Date().getFullYear()} PERSONNALY - Tous droits réservés</p><p><a href="#">personnaly.fr</a></p></div>
        </div></div></body></html>`;
    }

    function setPreviewDevice(device, btn) {
        const iframe = document.getElementById('previewIframe');
        iframe.className = 'preview-iframe preview-' + device;
        document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    // === Review ===
    function updateReview() {
        document.getElementById('reviewSubject').textContent = document.getElementById('campaignSubject').value || '-';
        const senderName = document.getElementById('campaignSenderName').value;
        const senderEmail = document.getElementById('campaignSenderEmail').value;
        document.getElementById('reviewSender').textContent = (senderName || 'PERSONNALY') + ' <' + (senderEmail || '...') + '>';

        const type = document.querySelector('input[name="recipients_type"]:checked')?.value || 'all_clients';
        const typeLabels = { all_clients: 'Tous les clients', newsletter: 'Abonnés newsletter', custom: 'Sélection personnalisée' };
        document.getElementById('reviewRecipients').textContent = (typeLabels[type] || type) + ' (' + getRecipientsCount() + ')';

        const content = tinymce.get('campaignContent') ? tinymce.get('campaignContent').getContent() : '';
        document.getElementById('reviewPreviewFrame').innerHTML = content || '<p style="color:#999;text-align:center;padding:40px;">Aucun contenu</p>';
    }

    // === Test Email ===
    function showTestModal() {
        // Auto-save first
        document.getElementById('testModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function sendTest() {
        const email = document.getElementById('testEmailInput').value.trim();
        if (!email) return;

        if (!campaignId) {
            alert('Sauvegardez d\'abord la campagne avant d\'envoyer un test.');
            closeModal('testModal');
            return;
        }

        const btn = document.getElementById('sendTestBtn');
        btn.textContent = 'Envoi en cours...';
        btn.disabled = true;

        // Save first, then send test
        const content = tinymce.get('campaignContent') ? tinymce.get('campaignContent').getContent() : '';

        const saveData = new FormData();
        saveData.append('action', 'save');
        saveData.append('csrf_token', csrfToken);
        saveData.append('name', document.getElementById('campaignName').value);
        saveData.append('subject', document.getElementById('campaignSubject').value);
        saveData.append('preheader', document.getElementById('campaignPreheader').value);
        saveData.append('content', content);
        saveData.append('sender_name', document.getElementById('campaignSenderName').value);
        saveData.append('sender_email', document.getElementById('campaignSenderEmail').value);
        saveData.append('recipients_type', document.querySelector('input[name="recipients_type"]:checked')?.value || 'all_clients');

        // Send test
        const testData = new FormData();
        testData.append('action', 'send_test');
        testData.append('csrf_token', csrfToken);
        testData.append('campaign_id', campaignId);
        testData.append('test_email', email);

        fetch(window.location.pathname + '?id=' + campaignId, { method: 'POST', body: testData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Email test envoyé avec succès à ' + email);
                } else {
                    alert('Erreur: ' + (data.error || 'Échec de l\'envoi'));
                }
            })
            .catch(() => alert('Erreur réseau'))
            .finally(() => {
                btn.textContent = 'Envoyer le test';
                btn.disabled = false;
                closeModal('testModal');
            });
    }

    // === Send Campaign ===
    function showSendModal() {
        if (!campaignId) {
            alert('Sauvegardez d\'abord la campagne.');
            return;
        }

        const count = getRecipientsCount();
        const subject = document.getElementById('campaignSubject').value || 'Sans sujet';

        document.getElementById('sendConfirmInfo').innerHTML = `
            <p>Vous êtes sur le point d'envoyer cette campagne :</p>
            <p><strong>Sujet :</strong> ${esc(subject)}</p>
            <p><strong>Destinataires :</strong> ${count} contact(s)</p>
            <p><strong>Quota restant :</strong> <?= $brevoStats['daily_remaining'] ?> emails</p>
            ${count > <?= $brevoStats['daily_remaining'] ?> ? '<p style="color:#EF4444;font-weight:700;">⚠️ Quota insuffisant pour tous les destinataires !</p>' : ''}
            <p style="margin-top:12px;font-size:13px;color:var(--gray);">L'envoi est irréversible. Vérifiez bien le contenu avant de confirmer.</p>
        `;

        document.getElementById('sendModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function confirmSend() {
        const btn = document.getElementById('confirmSendBtn');
        btn.innerHTML = '<span class="spinner-sm"></span> Envoi en cours...';
        btn.disabled = true;

        // Save first
        saveCampaignAjax().then(() => {
            const data = new FormData();
            data.append('action', 'send_campaign');
            data.append('csrf_token', csrfToken);
            data.append('campaign_id', campaignId);

            return fetch(window.location.pathname + '?id=' + campaignId, { method: 'POST', body: data });
        })
        .then(r => r.json())
        .then(result => {
            closeModal('sendModal');
            if (result.success) {
                alert('Campagne envoyée avec succès ! ' + result.sent + '/' + result.total + ' emails envoyés.');
                window.location.href = '/admin/campaigns.php?success=Campagne envoyée';
            } else {
                alert('Erreur: ' + (result.error || JSON.stringify(result.errors)));
                btn.innerHTML = 'Confirmer l\'envoi';
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Erreur réseau');
            btn.innerHTML = 'Confirmer l\'envoi';
            btn.disabled = false;
        });
    }

    function saveCampaignAjax() {
        const content = tinymce.get('campaignContent') ? tinymce.get('campaignContent').getContent() : '';
        const type = document.querySelector('input[name="recipients_type"]:checked')?.value || 'all_clients';

        const data = new FormData();
        data.append('action', 'save');
        data.append('csrf_token', csrfToken);
        data.append('name', document.getElementById('campaignName').value);
        data.append('subject', document.getElementById('campaignSubject').value);
        data.append('preheader', document.getElementById('campaignPreheader').value);
        data.append('content', content);
        data.append('sender_name', document.getElementById('campaignSenderName').value);
        data.append('sender_email', document.getElementById('campaignSenderEmail').value);
        data.append('recipients_type', type);
        data.append('selected_recipients', type === 'custom' ? Array.from(selectedRecipients).join(',') : '');

        return fetch(window.location.pathname + '?id=' + campaignId, { method: 'POST', body: data });
    }

    // === Helpers ===
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    }

    function esc(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.camp-modal-overlay.active').forEach(m => {
                m.classList.remove('active');
            });
            document.body.style.overflow = '';
        }
        // Ctrl+S pour sauvegarder
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            saveCampaign();
        }
    });
    </script>
</body>
</html>
