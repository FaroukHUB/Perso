<?php
/**
 * PERSONNALY - Admin : Gestion des Popups ULTRA-MODERNE
 * Interface redesignée avec glassmorphism + chatbot d'aide
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Popup.php';

Auth::requireAdmin();

$popupModel = new Popup();

// Messages flash
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Action: Supprimer
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (verifyCsrf($_GET['token'] ?? '')) {
        if ($popupModel->delete((int)$_GET['delete'])) {
            redirect('/admin/popups.php?success=Popup supprimée');
        }
    }
}

// Action: Toggle actif/inactif
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    if (verifyCsrf($_GET['token'] ?? '')) {
        if ($popupModel->toggleActive((int)$_GET['toggle'])) {
            redirect('/admin/popups.php?success=Statut mis à jour');
        }
    }
}

// Action: Création/Modification
$editing = false;
$editPopup = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editPopup = $popupModel->findById((int)$_GET['edit']);
    $editing = $editPopup !== null;
}

if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $data = [
        'title' => trim(post('title', '')),
        'content' => trim(post('content', '')),
        'image_url' => trim(post('image_url', '')),
        'cta_text' => trim(post('cta_text', '')),
        'cta_url' => trim(post('cta_url', '')),
        'cta_new_tab' => post('cta_new_tab', false) ? 1 : 0,
        'promo_code' => trim(post('promo_code', '')),
        'trigger_type' => post('trigger_type', 'immediate'),
        'trigger_value' => post('trigger_value') ? (int)post('trigger_value') : null,
        'frequency' => post('frequency', 'per_session'),
        'target_pages' => post('target_pages', 'all'),
        'target_urls' => post('target_urls') ? json_encode(array_filter(array_map('trim', explode("\n", post('target_urls'))))) : null,
        'target_visitors' => post('target_visitors', 'all'),
        'show_close_button' => post('show_close_button', false) ? 1 : 0,
        'click_outside_to_close' => post('click_outside_to_close', false) ? 1 : 0,
        'show_never_show_again' => post('show_never_show_again', false) ? 1 : 0,
        'auto_close_after' => post('auto_close_after') ? (int)post('auto_close_after') : null,
        'template_type' => post('template_type', 'modal'),
        'size' => post('size', 'medium'),
        'animation' => post('animation', 'fade'),
        'is_active' => post('is_active', false) ? 1 : 0,
        'priority' => post('priority') ? (int)post('priority') : 0,
    ];

    if (empty($data['title'])) {
        $error = 'Le titre est obligatoire';
    } else {
        try {
            if (isset($_POST['popup_id']) && is_numeric($_POST['popup_id'])) {
                if ($popupModel->update((int)$_POST['popup_id'], $data)) {
                    redirect('/admin/popups.php?success=Popup mise à jour');
                }
            } else {
                $popupModel->create($data);
                redirect('/admin/popups.php?success=Popup créée');
            }
        } catch (Exception $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}

$popups = $popupModel->findAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Popups - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        /* ULTRA-MODERNE DESIGN */
        :root {
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.18);
            --shadow-lg: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            --shadow-xl: 0 20px 60px 0 rgba(31, 38, 135, 0.25);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            background-attachment: fixed;
        }

        .main-content {
            background: transparent;
        }

        /* Header Ultra-Moderne */
        .modern-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
        }

        .modern-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-1);
        }

        .modern-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .modern-header p {
            color: #666;
            font-size: 1.1rem;
        }

        /* Cards Glassmorphism */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--glass-border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        /* Section Accordion Ultra-Moderne */
        .section-accordion {
            margin-bottom: 20px;
        }

        .section-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 16px;
            padding: 20px 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .section-header:hover {
            border-color: rgba(102, 126, 234, 0.3);
            transform: scale(1.01);
        }

        .section-header.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border-color: rgba(102, 126, 234, 0.5);
        }

        .section-title-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-emoji {
            font-size: 2rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        .section-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }

        .section-chevron {
            transition: transform 0.3s;
            color: #667eea;
        }

        .section-header.active .section-chevron {
            transform: rotate(180deg);
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 0 25px;
        }

        .section-content.active {
            max-height: 2000px;
            padding: 25px;
        }

        /* Modern Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background: var(--gradient-1);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        /* Modern Input Fields */
        .modern-input, .modern-textarea, .modern-select {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }

        .modern-input:focus, .modern-textarea:focus, .modern-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        /* Modern Button */
        .btn-modern {
            background: var(--gradient-1);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .btn-modern-secondary {
            background: var(--gradient-2);
            box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 8px;
        }

        /* Chatbot Ultra-Moderne */
        .chatbot-widget {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
        }

        .chatbot-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-1);
            border: none;
            cursor: pointer;
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            animation: pulse 2s infinite;
        }

        .chatbot-button:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.6);
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 8px 30px rgba(102, 126, 234, 0.5); }
            50% { box-shadow: 0 8px 40px rgba(102, 126, 234, 0.8); }
        }

        .chatbot-panel {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 380px;
            max-height: 600px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--glass-border);
            display: none;
            flex-direction: column;
            overflow: hidden;
        }

        .chatbot-panel.active {
            display: flex;
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chatbot-header {
            background: var(--gradient-1);
            padding: 20px;
            color: white;
        }

        .chatbot-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        .chatbot-header p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .chatbot-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .chatbot-question {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .chatbot-question:hover {
            border-color: rgba(102, 126, 234, 0.3);
            transform: translateX(5px);
        }

        .chatbot-answer {
            background: white;
            padding: 15px;
            border-radius: 12px;
            margin-top: 10px;
            border-left: 3px solid #667eea;
            display: none;
        }

        .chatbot-answer.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .chatbot-panel {
                width: calc(100vw - 60px);
                right: 0;
                left: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <!-- Header Ultra-Moderne -->
            <div class="modern-header">
                <h1>✨ Gestion des Popups</h1>
                <p>Créez des popups magnifiques qui convertissent vos visiteurs en clients</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" style="animation: fadeIn 0.3s;"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error" style="animation: fadeIn 0.3s;"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Liste des Popups -->
            <?php if (!empty($popups) && !$editing): ?>
                <div class="glass-card">
                    <h2 style="margin-bottom: 20px;">📊 Vos Popups</h2>

                    <?php foreach ($popups as $popup): ?>
                        <?php $stats = $popupModel->getStats($popup['id']); ?>
                        <div class="glass-card" style="margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                                <div>
                                    <h3 style="margin: 0 0 10px 0; font-size: 1.3rem;"><?= h($popup['title']) ?></h3>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <span class="badge"><?= ucfirst(str_replace('_', ' ', $popup['template_type'])) ?></span>
                                        <span class="badge"><?= ucfirst(str_replace('_', ' ', $popup['target_pages'])) ?></span>
                                        <?php if ($popup['is_active']): ?>
                                            <span class="badge badge-success">✓ Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-gray">⏸ Inactif</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="table-actions">
                                    <a href="?toggle=<?= $popup['id'] ?>&token=<?= csrfToken() ?>" class="btn btn-sm btn-secondary">
                                        <?= $popup['is_active'] ? '⏸' : '▶' ?>
                                    </a>
                                    <a href="?edit=<?= $popup['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
                                    <a href="?delete=<?= $popup['id'] ?>&token=<?= csrfToken() ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Supprimer ?')">🗑️</a>
                                </div>
                            </div>

                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-value"><?= number_format($stats['total_views']) ?></div>
                                    <div class="stat-label">Vues</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?= number_format($stats['total_clicks']) ?></div>
                                    <div class="stat-label">Clics</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?= $stats['click_rate'] ?>%</div>
                                    <div class="stat-label">Taux de clic</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?= $stats['close_rate'] ?>%</div>
                                    <div class="stat-label">Taux de fermeture</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Bouton Créer -->
            <?php if (!$editing && !empty($popups)): ?>
                <button onclick="window.location.href='?edit=new'" class="btn-modern" style="width: 100%; font-size: 1.2rem; padding: 20px;">
                    ➕ Créer une nouvelle popup
                </button>
            <?php endif; ?>

            <!-- Formulaire Ultra-Moderne -->
            <?php if ($editing || empty($popups)): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <?php if ($editing && $editPopup): ?>
                        <input type="hidden" name="popup_id" value="<?= $editPopup['id'] ?>">
                    <?php endif; ?>

                    <div class="glass-card" style="margin-bottom: 30px;">
                        <h2 style="margin-bottom: 30px;">
                            <?= $editing ? '✏️ Modifier la popup' : '✨ Créer une popup' ?>
                        </h2>

                        <!-- Section 1: Message -->
                        <div class="section-accordion">
                            <div class="section-header active" onclick="toggleSection(this)">
                                <div class="section-title-group">
                                    <span class="section-emoji">💬</span>
                                    <div>
                                        <div class="section-title">Que voulez-vous dire ?</div>
                                        <div class="section-subtitle">Le message principal de votre popup</div>
                                    </div>
                                </div>
                                <svg class="section-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="section-content active">
                                <div class="form-group">
                                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Titre principal *</label>
                                    <input type="text" name="title" class="modern-input" required
                                           value="<?= h($editPopup['title'] ?? '') ?>"
                                           placeholder="Ex: Offre spéciale -20% 🎉">
                                </div>

                                <div class="form-group">
                                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Description</label>
                                    <textarea name="content" class="modern-textarea" rows="3"
                                              placeholder="Profitez de -20% sur toute la boutique jusqu'à dimanche !"><?= h($editPopup['content'] ?? '') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Image (optionnel)</label>
                                    <input type="url" name="image_url" class="modern-input"
                                           value="<?= h($editPopup['image_url'] ?? '') ?>"
                                           placeholder="/public/uploads/promo.jpg">
                                </div>

                                <div class="form-group">
                                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Code promo à afficher</label>
                                    <input type="text" name="promo_code" class="modern-input"
                                           value="<?= h($editPopup['promo_code'] ?? '') ?>"
                                           placeholder="PROMO20">
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Bouton d'action -->
                        <div class="section-accordion">
                            <div class="section-header" onclick="toggleSection(this)">
                                <div class="section-title-group">
                                    <span class="section-emoji">🎯</span>
                                    <div>
                                        <div class="section-title">Où voulez-vous les envoyer ?</div>
                                        <div class="section-subtitle">Le bouton d'action de votre popup</div>
                                    </div>
                                </div>
                                <svg class="section-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="section-content">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Texte du bouton</label>
                                        <input type="text" name="cta_text" class="modern-input"
                                               value="<?= h($editPopup['cta_text'] ?? '') ?>"
                                               placeholder="J'en profite !">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Lien du bouton</label>
                                        <input type="url" name="cta_url" class="modern-input"
                                               value="<?= h($editPopup['cta_url'] ?? '') ?>"
                                               placeholder="/public/cart.php">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                        <input type="checkbox" name="cta_new_tab" value="1"
                                               <?= ($editPopup['cta_new_tab'] ?? false) ? 'checked' : '' ?>>
                                        Ouvrir dans un nouvel onglet
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Quand l'afficher -->
                        <div class="section-accordion">
                            <div class="section-header" onclick="toggleSection(this)">
                                <div class="section-title-group">
                                    <span class="section-emoji">⏰</span>
                                    <div>
                                        <div class="section-title">Quand l'afficher ?</div>
                                        <div class="section-subtitle">À quel moment montrer la popup</div>
                                    </div>
                                </div>
                                <svg class="section-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="section-content">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Déclencheur</label>
                                        <select name="trigger_type" id="trigger_type" class="modern-select">
                                            <option value="immediate" <?= ($editPopup['trigger_type'] ?? '') === 'immediate' ? 'selected' : '' ?>>Tout de suite</option>
                                            <option value="delay" <?= ($editPopup['trigger_type'] ?? '') === 'delay' ? 'selected' : '' ?>>Après quelques secondes</option>
                                            <option value="scroll" <?= ($editPopup['trigger_type'] ?? '') === 'scroll' ? 'selected' : '' ?>>Quand il scroll</option>
                                            <option value="exit" <?= ($editPopup['trigger_type'] ?? '') === 'exit' ? 'selected' : '' ?>>Quand il veut partir</option>
                                        </select>
                                    </div>
                                    <div class="form-group" id="trigger-value-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;" id="trigger-value-label">Combien ?</label>
                                        <input type="number" name="trigger_value" class="modern-input"
                                               value="<?= h($editPopup['trigger_value'] ?? '') ?>"
                                               placeholder="3">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">À quelle fréquence ?</label>
                                    <select name="frequency" class="modern-select">
                                        <option value="every_visit" <?= ($editPopup['frequency'] ?? '') === 'every_visit' ? 'selected' : '' ?>>À chaque fois</option>
                                        <option value="per_session" <?= ($editPopup['frequency'] ?? '') === 'per_session' ? 'selected' : '' ?>>Une fois par visite</option>
                                        <option value="daily" <?= ($editPopup['frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>Une fois par jour</option>
                                        <option value="weekly" <?= ($editPopup['frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Une fois par semaine</option>
                                        <option value="until_click" <?= ($editPopup['frequency'] ?? '') === 'until_click' ? 'selected' : '' ?>>Jusqu'à ce qu'il clique</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Pour qui -->
                        <div class="section-accordion">
                            <div class="section-header" onclick="toggleSection(this)">
                                <div class="section-title-group">
                                    <span class="section-emoji">👥</span>
                                    <div>
                                        <div class="section-title">Pour qui ?</div>
                                        <div class="section-subtitle">Qui va voir cette popup</div>
                                    </div>
                                </div>
                                <svg class="section-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="section-content">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Sur quelles pages ?</label>
                                        <select name="target_pages" id="target_pages" class="modern-select">
                                            <option value="all" <?= ($editPopup['target_pages'] ?? '') === 'all' ? 'selected' : '' ?>>Toutes les pages</option>
                                            <option value="home" <?= ($editPopup['target_pages'] ?? '') === 'home' ? 'selected' : '' ?>>Page d'accueil seulement</option>
                                            <option value="products" <?= ($editPopup['target_pages'] ?? '') === 'products' ? 'selected' : '' ?>>Pages produits</option>
                                            <option value="cart" <?= ($editPopup['target_pages'] ?? '') === 'cart' ? 'selected' : '' ?>>Panier</option>
                                            <option value="checkout" <?= ($editPopup['target_pages'] ?? '') === 'checkout' ? 'selected' : '' ?>>Page de paiement</option>
                                            <option value="specific" <?= ($editPopup['target_pages'] ?? '') === 'specific' ? 'selected' : '' ?>>Pages spécifiques</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Quel type de visiteur ?</label>
                                        <select name="target_visitors" class="modern-select">
                                            <option value="all" <?= ($editPopup['target_visitors'] ?? '') === 'all' ? 'selected' : '' ?>>Tout le monde</option>
                                            <option value="new" <?= ($editPopup['target_visitors'] ?? '') === 'new' ? 'selected' : '' ?>>Nouveaux visiteurs</option>
                                            <option value="returning" <?= ($editPopup['target_visitors'] ?? '') === 'returning' ? 'selected' : '' ?>>Visiteurs qui reviennent</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group" id="target-urls-group" style="display: none;">
                                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">URLs spécifiques (une par ligne)</label>
                                    <textarea name="target_urls" class="modern-textarea" rows="3"
                                              placeholder="/public/product.php&#10;/public/special-offer.php"><?php
                                        if (!empty($editPopup['target_urls'])) {
                                            $urls = json_decode($editPopup['target_urls'], true);
                                            echo h(is_array($urls) ? implode("\n", $urls) : '');
                                        }
                                    ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Section 5: Apparence -->
                        <div class="section-accordion">
                            <div class="section-header" onclick="toggleSection(this)">
                                <div class="section-title-group">
                                    <span class="section-emoji">🎨</span>
                                    <div>
                                        <div class="section-title">Comment ça doit apparaître ?</div>
                                        <div class="section-subtitle">Le style visuel de la popup</div>
                                    </div>
                                </div>
                                <svg class="section-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="section-content">
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Style</label>
                                        <select name="template_type" class="modern-select">
                                            <option value="modal" <?= ($editPopup['template_type'] ?? '') === 'modal' ? 'selected' : '' ?>>Au centre</option>
                                            <option value="banner_top" <?= ($editPopup['template_type'] ?? '') === 'banner_top' ? 'selected' : '' ?>>Bandeau haut</option>
                                            <option value="banner_bottom" <?= ($editPopup['template_type'] ?? '') === 'banner_bottom' ? 'selected' : '' ?>>Bandeau bas</option>
                                            <option value="corner" <?= ($editPopup['template_type'] ?? '') === 'corner' ? 'selected' : '' ?>>Coin</option>
                                            <option value="fullscreen" <?= ($editPopup['template_type'] ?? '') === 'fullscreen' ? 'selected' : '' ?>>Plein écran</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Taille</label>
                                        <select name="size" class="modern-select">
                                            <option value="small" <?= ($editPopup['size'] ?? '') === 'small' ? 'selected' : '' ?>>Petit</option>
                                            <option value="medium" <?= ($editPopup['size'] ?? '') === 'medium' ? 'selected' : '' ?>>Moyen</option>
                                            <option value="large" <?= ($editPopup['size'] ?? '') === 'large' ? 'selected' : '' ?>>Grand</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Animation</label>
                                        <select name="animation" class="modern-select">
                                            <option value="fade" <?= ($editPopup['animation'] ?? '') === 'fade' ? 'selected' : '' ?>>Fondu</option>
                                            <option value="slide_up" <?= ($editPopup['animation'] ?? '') === 'slide_up' ? 'selected' : '' ?>>Montée</option>
                                            <option value="scale" <?= ($editPopup['animation'] ?? '') === 'scale' ? 'selected' : '' ?>>Zoom</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 6: Options -->
                        <div class="section-accordion">
                            <div class="section-header" onclick="toggleSection(this)">
                                <div class="section-title-group">
                                    <span class="section-emoji">⚙️</span>
                                    <div>
                                        <div class="section-title">Options avancées</div>
                                        <div class="section-subtitle">Comportements et réglages</div>
                                    </div>
                                </div>
                                <svg class="section-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="section-content">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label style="display: flex; align-items: center; justify-content: space-between;">
                                            <span>Bouton X pour fermer</span>
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="show_close_button" value="1"
                                                       <?= ($editPopup['show_close_button'] ?? true) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label style="display: flex; align-items: center; justify-content: space-between;">
                                            <span>Fermer en cliquant à côté</span>
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="click_outside_to_close" value="1"
                                                       <?= ($editPopup['click_outside_to_close'] ?? true) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label style="display: flex; align-items: center; justify-content: space-between;">
                                            <span>Bouton "Ne plus afficher"</span>
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="show_never_show_again" value="1"
                                                       <?= ($editPopup['show_never_show_again'] ?? false) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Fermeture auto (secondes)</label>
                                        <input type="number" name="auto_close_after" class="modern-input"
                                               value="<?= h($editPopup['auto_close_after'] ?? '') ?>"
                                               placeholder="Laisser vide pour jamais">
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Priorité (0 = normal)</label>
                                        <input type="number" name="priority" class="modern-input"
                                               value="<?= h($editPopup['priority'] ?? 0) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label style="display: flex; align-items: center; justify-content: space-between;">
                                            <span style="font-weight: 600;">Popup active ?</span>
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="is_active" value="1"
                                                       <?= ($editPopup['is_active'] ?? true) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div style="display: flex; gap: 15px; margin-top: 40px;">
                            <button type="submit" class="btn-modern" style="flex: 1;">
                                💾 <?= $editing ? 'Mettre à jour' : 'Créer la popup' ?>
                            </button>
                            <?php if ($editing): ?>
                                <a href="/admin/popups.php" class="btn-modern btn-modern-secondary">
                                    ← Retour
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

        </main>
    </div>

    <!-- Chatbot Ultra-Moderne -->
    <div class="chatbot-widget">
        <button class="chatbot-button" onclick="toggleChatbot()">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </button>

        <div class="chatbot-panel" id="chatbotPanel">
            <div class="chatbot-header">
                <h3>💬 Assistant Popup</h3>
                <p>Je vous aide à créer des popups efficaces</p>
            </div>
            <div class="chatbot-messages">
                <div class="chatbot-question" onclick="showAnswer(1)">
                    ❓ C'est quoi un "déclencheur" ?
                </div>
                <div class="chatbot-answer" id="answer1">
                    <strong>Le déclencheur</strong> c'est le moment où la popup s'affiche:<br>
                    • <strong>Tout de suite</strong> = dès l'arrivée sur la page<br>
                    • <strong>Après X secondes</strong> = après un délai (ex: 5 secondes)<br>
                    • <strong>Au scroll</strong> = quand le visiteur descend à 50%<br>
                    • <strong>À la sortie</strong> = quand il veut quitter la page
                </div>

                <div class="chatbot-question" onclick="showAnswer(2)">
                    ❓ Quelle fréquence choisir ?
                </div>
                <div class="chatbot-answer" id="answer2">
                    <strong>La fréquence</strong> contrôle combien de fois montrer la popup:<br>
                    • <strong>Une fois par visite</strong> = la meilleure option (pas trop agaçant)<br>
                    • <strong>Une fois par jour</strong> = pour les visiteurs réguliers<br>
                    • <strong>Jusqu'au clic</strong> = disparaît après qu'il clique sur le bouton
                </div>

                <div class="chatbot-question" onclick="showAnswer(3)">
                    ❓ Quel style choisir ?
                </div>
                <div class="chatbot-answer" id="answer3">
                    <strong>Les styles de popup:</strong><br>
                    • <strong>Au centre</strong> = le plus classique et visible<br>
                    • <strong>Bandeau</strong> = moins intrusif, en haut ou en bas<br>
                    • <strong>Coin</strong> = discret, pour newsletter par exemple<br>
                    • <strong>Plein écran</strong> = très impactant, pour grandes annonces
                </div>

                <div class="chatbot-question" onclick="showAnswer(4)">
                    💡 Exemples de popup qui marchent
                </div>
                <div class="chatbot-answer" id="answer4">
                    <strong>Popup Promo:</strong><br>
                    Titre: "🎉 -20% aujourd'hui !"<br>
                    Déclencheur: Après 5 secondes<br>
                    Fréquence: Une fois par jour<br><br>

                    <strong>Popup Abandon:</strong><br>
                    Titre: "Attendez ! Un cadeau vous attend 🎁"<br>
                    Déclencheur: À la sortie<br>
                    Page: Panier uniquement
                </div>

                <div class="chatbot-question" onclick="showAnswer(5)">
                    🎯 Conseils pour convertir
                </div>
                <div class="chatbot-answer" id="answer5">
                    <strong>Les règles d'or:</strong><br>
                    ✅ Titre court et percutant<br>
                    ✅ Un seul bouton d'action clair<br>
                    ✅ Pas trop tôt (attendre 3-5 secondes)<br>
                    ✅ Toujours proposer de fermer facilement<br>
                    ❌ Ne pas afficher à chaque visite (agaçant)
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle Chatbot
        function toggleChatbot() {
            const panel = document.getElementById('chatbotPanel');
            panel.classList.toggle('active');
        }

        // Show Answer
        function showAnswer(id) {
            // Fermer toutes les réponses
            document.querySelectorAll('.chatbot-answer').forEach(a => a.classList.remove('active'));
            // Ouvrir la réponse cliquée
            document.getElementById('answer' + id).classList.add('active');
        }

        // Toggle Section
        function toggleSection(header) {
            const content = header.nextElementSibling;
            const isActive = header.classList.contains('active');

            // Toggle active state
            if (isActive) {
                header.classList.remove('active');
                content.classList.remove('active');
            } else {
                header.classList.add('active');
                content.classList.add('active');
            }
        }

        // Trigger Type Logic
        const triggerType = document.getElementById('trigger_type');
        const triggerValueGroup = document.getElementById('trigger-value-group');
        const triggerValueLabel = document.getElementById('trigger-value-label');

        if (triggerType) {
            triggerType.addEventListener('change', function() {
                const type = this.value;
                if (type === 'immediate' || type === 'exit') {
                    triggerValueGroup.style.display = 'none';
                } else {
                    triggerValueGroup.style.display = 'block';
                    if (type === 'delay') {
                        triggerValueLabel.textContent = 'Après combien de secondes ?';
                    } else if (type === 'scroll') {
                        triggerValueLabel.textContent = 'À quel % de scroll ?';
                    }
                }
            });
            triggerType.dispatchEvent(new Event('change'));
        }

        // Target Pages Logic
        const targetPages = document.getElementById('target_pages');
        const targetUrlsGroup = document.getElementById('target-urls-group');

        if (targetPages) {
            targetPages.addEventListener('change', function() {
                targetUrlsGroup.style.display = this.value === 'specific' ? 'block' : 'none';
            });
            targetPages.dispatchEvent(new Event('change'));
        }
    </script>
</body>
</html>
