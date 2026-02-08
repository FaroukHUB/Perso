<?php
/**
 * PERSONNALY - Admin : Gestion des Popups
 * Interface moderne avec upload d'images
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
        /* ====================================
           POPUPS ADMIN - STYLE MODERNE
           Rose + Vert Menthe + Blanc
           ==================================== */

        /* Section Accordion */
        .popup-accordion {
            margin-bottom: 20px;
        }

        .popup-accordion-header {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }

        .popup-accordion-header:hover {
            border-color: var(--pink-main);
            box-shadow: 0 4px 12px rgba(255, 105, 180, 0.1);
        }

        .popup-accordion-header.active {
            border-color: var(--pink-main);
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.05), rgba(61, 255, 192, 0.05));
        }

        .popup-accordion-title-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .popup-accordion-emoji {
            font-size: 2rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .popup-accordion-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--black-soft);
            margin: 0;
        }

        .popup-accordion-subtitle {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 4px;
        }

        .popup-accordion-chevron {
            transition: transform 0.3s;
            color: var(--pink-main);
        }

        .popup-accordion-header.active .popup-accordion-chevron {
            transform: rotate(180deg);
        }

        .popup-accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .popup-accordion-content.active {
            max-height: 2000px;
            padding: 25px 0;
        }

        /* Modern Toggle Switch - Pink/Mint */
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
            background: linear-gradient(135deg, var(--pink-main), var(--mint-main));
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        /* Image Upload Widget */
        .image-upload-widget {
            border: 2px dashed #e5e7eb;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            background: white;
        }

        .image-upload-widget:hover {
            border-color: var(--pink-main);
            background: rgba(255, 105, 180, 0.02);
        }

        .image-upload-widget.has-image {
            border-style: solid;
            border-color: var(--mint-main);
            padding: 15px;
        }

        .image-preview-container {
            position: relative;
            max-width: 400px;
            margin: 0 auto;
        }

        .image-preview {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .image-remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--pink-main);
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(255, 105, 180, 0.4);
            transition: all 0.2s;
        }

        .image-remove-btn:hover {
            transform: scale(1.1);
            background: var(--pink-dark);
        }

        .upload-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--pink-main), var(--mint-main));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .upload-btn {
            background: linear-gradient(135deg, var(--pink-main), var(--mint-main));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 105, 180, 0.3);
        }

        /* Stats Cards - Pink/Mint */
        .popup-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .popup-stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 2px solid #f8f9fa;
            transition: all 0.3s;
        }

        .popup-stat-card:hover {
            border-color: var(--pink-main);
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(255, 105, 180, 0.15);
        }

        .popup-stat-value {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--pink-main), var(--mint-main));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .popup-stat-label {
            font-size: 0.85rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 8px;
            font-weight: 600;
        }

        /* Chatbot Widget - Pink/Mint */
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
            background: linear-gradient(135deg, var(--pink-main), var(--mint-main));
            border: none;
            cursor: pointer;
            box-shadow: 0 8px 30px rgba(255, 105, 180, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            animation: pulse 2s infinite;
        }

        .chatbot-button:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 40px rgba(255, 105, 180, 0.5);
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 8px 30px rgba(255, 105, 180, 0.4); }
            50% { box-shadow: 0 8px 40px rgba(61, 255, 192, 0.6); }
        }

        .chatbot-panel {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 380px;
            max-height: 600px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 2px solid #f8f9fa;
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
            background: linear-gradient(135deg, var(--pink-main), var(--mint-main));
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
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.08), rgba(61, 255, 192, 0.08));
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
            font-weight: 600;
            color: var(--black-soft);
        }

        .chatbot-question:hover {
            border-color: var(--pink-main);
            transform: translateX(5px);
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.12), rgba(61, 255, 192, 0.12));
        }

        .chatbot-answer {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-top: 10px;
            border-left: 4px solid var(--pink-main);
            display: none;
            color: var(--black-soft);
            line-height: 1.6;
        }

        .chatbot-answer.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Popup Item Card */
        .popup-item-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border: 2px solid #f8f9fa;
            transition: all 0.3s;
        }

        .popup-item-card:hover {
            border-color: var(--pink-main);
            box-shadow: 0 8px 24px rgba(255, 105, 180, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .popup-stats-grid {
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
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Gestion des <span>Popups</span></h1>
                <?php if (!$editing && !empty($popups)): ?>
                    <a href="?edit=new" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Créer une popup
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Liste des Popups -->
            <?php if (!empty($popups) && !$editing): ?>
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">📊 Vos Popups (<?= count($popups) ?>)</h3>
                    </div>

                    <?php foreach ($popups as $popup): ?>
                        <?php $stats = $popupModel->getStats($popup['id']); ?>
                        <div class="popup-item-card">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                                <div>
                                    <h3 style="margin: 0 0 10px 0; font-size: 1.3rem; color: var(--black-soft);"><?= h($popup['title']) ?></h3>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <span class="badge"><?= ucfirst(str_replace('_', ' ', $popup['template_type'])) ?></span>
                                        <span class="badge"><?= ucfirst(str_replace('_', ' ', $popup['target_pages'])) ?></span>
                                        <?php if ($popup['is_active']): ?>
                                            <span class="badge" style="background: var(--mint-main); color: var(--black);">✓ Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-gray">⏸ Inactif</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="table-actions">
                                    <a href="?toggle=<?= $popup['id'] ?>&token=<?= csrfToken() ?>"
                                       class="btn btn-sm btn-secondary"
                                       title="<?= $popup['is_active'] ? 'Désactiver' : 'Activer' ?>">
                                        <?= $popup['is_active'] ? '⏸' : '▶' ?>
                                    </a>
                                    <a href="?edit=<?= $popup['id'] ?>" class="btn btn-sm btn-primary" title="Modifier">✏️</a>
                                    <a href="?delete=<?= $popup['id'] ?>&token=<?= csrfToken() ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Supprimer cette popup ?')"
                                       title="Supprimer">🗑️</a>
                                </div>
                            </div>

                            <div class="popup-stats-grid">
                                <div class="popup-stat-card">
                                    <div class="popup-stat-value"><?= number_format($stats['total_views']) ?></div>
                                    <div class="popup-stat-label">Vues</div>
                                </div>
                                <div class="popup-stat-card">
                                    <div class="popup-stat-value"><?= number_format($stats['total_clicks']) ?></div>
                                    <div class="popup-stat-label">Clics</div>
                                </div>
                                <div class="popup-stat-card">
                                    <div class="popup-stat-value"><?= $stats['click_rate'] ?>%</div>
                                    <div class="popup-stat-label">Taux de clic</div>
                                </div>
                                <div class="popup-stat-card">
                                    <div class="popup-stat-value"><?= $stats['close_rate'] ?>%</div>
                                    <div class="popup-stat-label">Taux de fermeture</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <?php if ($editing || empty($popups)): ?>
                <form method="post" id="popupForm">
                    <?= csrfField() ?>
                    <?php if ($editing && $editPopup): ?>
                        <input type="hidden" name="popup_id" value="<?= $editPopup['id'] ?>">
                    <?php endif; ?>

                    <div class="data-card">
                        <div class="data-card-header">
                            <h2 style="margin: 0;">
                                <?= $editing ? '✏️ Modifier la popup' : '✨ Créer une popup' ?>
                            </h2>
                        </div>

                        <!-- Section 1: Message -->
                        <div class="popup-accordion">
                            <div class="popup-accordion-header active" onclick="togglePopupSection(this)">
                                <div class="popup-accordion-title-group">
                                    <span class="popup-accordion-emoji">💬</span>
                                    <div>
                                        <div class="popup-accordion-title">Que voulez-vous dire ?</div>
                                        <div class="popup-accordion-subtitle">Le message principal de votre popup</div>
                                    </div>
                                </div>
                                <svg class="popup-accordion-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="popup-accordion-content active">
                                <div class="form-group">
                                    <label>Titre principal *</label>
                                    <input type="text" name="title" class="form-input" required
                                           value="<?= h($editPopup['title'] ?? '') ?>"
                                           placeholder="Ex: Offre spéciale -20% 🎉">
                                </div>

                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="content" class="form-input" rows="3"
                                              placeholder="Profitez de -20% sur toute la boutique jusqu'à dimanche !"><?= h($editPopup['content'] ?? '') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Image (optionnel)</label>
                                    <div class="image-upload-widget" id="imageUploadWidget">
                                        <input type="hidden" name="image_url" id="imageUrl" value="<?= h($editPopup['image_url'] ?? '') ?>">
                                        <input type="file" id="imageInput" accept="image/*" style="display: none;">

                                        <div id="uploadArea">
                                            <div class="upload-icon">🖼️</div>
                                            <p style="color: var(--gray); margin-bottom: 15px;">Cliquez pour ajouter une image</p>
                                            <button type="button" class="upload-btn" onclick="document.getElementById('imageInput').click()">
                                                Choisir une image
                                            </button>
                                            <p style="font-size: 0.85rem; color: var(--gray-dark); margin-top: 10px;">
                                                JPG, PNG, GIF ou WEBP • Max 5 MB
                                            </p>
                                        </div>

                                        <div id="previewArea" style="display: none;">
                                            <div class="image-preview-container">
                                                <img id="imagePreview" class="image-preview" src="" alt="Aperçu">
                                                <button type="button" class="image-remove-btn" onclick="removeImage()">✕</button>
                                            </div>
                                            <p style="margin-top: 15px; color: var(--gray);">
                                                <button type="button" class="upload-btn" onclick="document.getElementById('imageInput').click()">
                                                    Changer l'image
                                                </button>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Code promo à afficher</label>
                                    <input type="text" name="promo_code" class="form-input"
                                           value="<?= h($editPopup['promo_code'] ?? '') ?>"
                                           placeholder="PROMO20">
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Bouton d'action -->
                        <div class="popup-accordion">
                            <div class="popup-accordion-header" onclick="togglePopupSection(this)">
                                <div class="popup-accordion-title-group">
                                    <span class="popup-accordion-emoji">🎯</span>
                                    <div>
                                        <div class="popup-accordion-title">Où voulez-vous les envoyer ?</div>
                                        <div class="popup-accordion-subtitle">Le bouton d'action de votre popup</div>
                                    </div>
                                </div>
                                <svg class="popup-accordion-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="popup-accordion-content">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Texte du bouton</label>
                                        <input type="text" name="cta_text" class="form-input"
                                               value="<?= h($editPopup['cta_text'] ?? '') ?>"
                                               placeholder="J'en profite !">
                                    </div>
                                    <div class="form-group">
                                        <label>Lien du bouton</label>
                                        <input type="url" name="cta_url" class="form-input"
                                               value="<?= h($editPopup['cta_url'] ?? '') ?>"
                                               placeholder="/public/cart.php">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="cta_new_tab" value="1"
                                               <?= ($editPopup['cta_new_tab'] ?? false) ? 'checked' : '' ?>>
                                        <span>Ouvrir dans un nouvel onglet</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Quand l'afficher -->
                        <div class="popup-accordion">
                            <div class="popup-accordion-header" onclick="togglePopupSection(this)">
                                <div class="popup-accordion-title-group">
                                    <span class="popup-accordion-emoji">⏰</span>
                                    <div>
                                        <div class="popup-accordion-title">Quand l'afficher ?</div>
                                        <div class="popup-accordion-subtitle">À quel moment montrer la popup</div>
                                    </div>
                                </div>
                                <svg class="popup-accordion-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="popup-accordion-content">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Déclencheur</label>
                                        <select name="trigger_type" id="trigger_type" class="form-input">
                                            <option value="immediate" <?= ($editPopup['trigger_type'] ?? '') === 'immediate' ? 'selected' : '' ?>>Tout de suite</option>
                                            <option value="delay" <?= ($editPopup['trigger_type'] ?? '') === 'delay' ? 'selected' : '' ?>>Après quelques secondes</option>
                                            <option value="scroll" <?= ($editPopup['trigger_type'] ?? '') === 'scroll' ? 'selected' : '' ?>>Quand il scroll</option>
                                            <option value="exit" <?= ($editPopup['trigger_type'] ?? '') === 'exit' ? 'selected' : '' ?>>Quand il veut partir</option>
                                        </select>
                                    </div>
                                    <div class="form-group" id="trigger-value-group">
                                        <label id="trigger-value-label">Combien ?</label>
                                        <input type="number" name="trigger_value" class="form-input"
                                               value="<?= h($editPopup['trigger_value'] ?? '') ?>"
                                               placeholder="3">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>À quelle fréquence ?</label>
                                    <select name="frequency" class="form-input">
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
                        <div class="popup-accordion">
                            <div class="popup-accordion-header" onclick="togglePopupSection(this)">
                                <div class="popup-accordion-title-group">
                                    <span class="popup-accordion-emoji">👥</span>
                                    <div>
                                        <div class="popup-accordion-title">Pour qui ?</div>
                                        <div class="popup-accordion-subtitle">Qui va voir cette popup</div>
                                    </div>
                                </div>
                                <svg class="popup-accordion-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="popup-accordion-content">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Sur quelles pages ?</label>
                                        <select name="target_pages" id="target_pages" class="form-input">
                                            <option value="all" <?= ($editPopup['target_pages'] ?? '') === 'all' ? 'selected' : '' ?>>Toutes les pages</option>
                                            <option value="home" <?= ($editPopup['target_pages'] ?? '') === 'home' ? 'selected' : '' ?>>Page d'accueil seulement</option>
                                            <option value="products" <?= ($editPopup['target_pages'] ?? '') === 'products' ? 'selected' : '' ?>>Pages produits</option>
                                            <option value="cart" <?= ($editPopup['target_pages'] ?? '') === 'cart' ? 'selected' : '' ?>>Panier</option>
                                            <option value="checkout" <?= ($editPopup['target_pages'] ?? '') === 'checkout' ? 'selected' : '' ?>>Page de paiement</option>
                                            <option value="specific" <?= ($editPopup['target_pages'] ?? '') === 'specific' ? 'selected' : '' ?>>Pages spécifiques</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Quel type de visiteur ?</label>
                                        <select name="target_visitors" class="form-input">
                                            <option value="all" <?= ($editPopup['target_visitors'] ?? '') === 'all' ? 'selected' : '' ?>>Tout le monde</option>
                                            <option value="new" <?= ($editPopup['target_visitors'] ?? '') === 'new' ? 'selected' : '' ?>>Nouveaux visiteurs</option>
                                            <option value="returning" <?= ($editPopup['target_visitors'] ?? '') === 'returning' ? 'selected' : '' ?>>Visiteurs qui reviennent</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group" id="target-urls-group" style="display: none;">
                                    <label>URLs spécifiques (une par ligne)</label>
                                    <textarea name="target_urls" class="form-input" rows="3"
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
                        <div class="popup-accordion">
                            <div class="popup-accordion-header" onclick="togglePopupSection(this)">
                                <div class="popup-accordion-title-group">
                                    <span class="popup-accordion-emoji">🎨</span>
                                    <div>
                                        <div class="popup-accordion-title">Comment ça doit apparaître ?</div>
                                        <div class="popup-accordion-subtitle">Le style visuel de la popup</div>
                                    </div>
                                </div>
                                <svg class="popup-accordion-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="popup-accordion-content">
                                <div class="form-row" style="grid-template-columns: repeat(3, 1fr);">
                                    <div class="form-group">
                                        <label>Style</label>
                                        <select name="template_type" class="form-input">
                                            <option value="modal" <?= ($editPopup['template_type'] ?? '') === 'modal' ? 'selected' : '' ?>>Au centre</option>
                                            <option value="banner_top" <?= ($editPopup['template_type'] ?? '') === 'banner_top' ? 'selected' : '' ?>>Bandeau haut</option>
                                            <option value="banner_bottom" <?= ($editPopup['template_type'] ?? '') === 'banner_bottom' ? 'selected' : '' ?>>Bandeau bas</option>
                                            <option value="corner" <?= ($editPopup['template_type'] ?? '') === 'corner' ? 'selected' : '' ?>>Coin</option>
                                            <option value="fullscreen" <?= ($editPopup['template_type'] ?? '') === 'fullscreen' ? 'selected' : '' ?>>Plein écran</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Taille</label>
                                        <select name="size" class="form-input">
                                            <option value="small" <?= ($editPopup['size'] ?? '') === 'small' ? 'selected' : '' ?>>Petit</option>
                                            <option value="medium" <?= ($editPopup['size'] ?? '') === 'medium' ? 'selected' : '' ?>>Moyen</option>
                                            <option value="large" <?= ($editPopup['size'] ?? '') === 'large' ? 'selected' : '' ?>>Grand</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Animation</label>
                                        <select name="animation" class="form-input">
                                            <option value="fade" <?= ($editPopup['animation'] ?? '') === 'fade' ? 'selected' : '' ?>>Fondu</option>
                                            <option value="slide_up" <?= ($editPopup['animation'] ?? '') === 'slide_up' ? 'selected' : '' ?>>Montée</option>
                                            <option value="scale" <?= ($editPopup['animation'] ?? '') === 'scale' ? 'selected' : '' ?>>Zoom</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 6: Options -->
                        <div class="popup-accordion">
                            <div class="popup-accordion-header" onclick="togglePopupSection(this)">
                                <div class="popup-accordion-title-group">
                                    <span class="popup-accordion-emoji">⚙️</span>
                                    <div>
                                        <div class="popup-accordion-title">Options avancées</div>
                                        <div class="popup-accordion-subtitle">Comportements et réglages</div>
                                    </div>
                                </div>
                                <svg class="popup-accordion-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="popup-accordion-content">
                                <div class="form-row">
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
                                </div>
                                <div class="form-row">
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
                                        <label>Fermeture auto (secondes)</label>
                                        <input type="number" name="auto_close_after" class="form-input"
                                               value="<?= h($editPopup['auto_close_after'] ?? '') ?>"
                                               placeholder="Laisser vide pour jamais">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Priorité (0 = normal)</label>
                                        <input type="number" name="priority" class="form-input"
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
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                💾 <?= $editing ? 'Mettre à jour' : 'Créer la popup' ?>
                            </button>
                            <?php if ($editing): ?>
                                <a href="/admin/popups.php" class="btn btn-secondary">
                                    ← Retour
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Empty State -->
            <?php if (empty($popups) && !$editing): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✨</div>
                    <h4>Aucune popup pour le moment</h4>
                    <p class="text-muted">Créez votre première popup pour commencer à convertir vos visiteurs</p>
                    <a href="?edit=new" class="btn btn-primary mt-lg">Créer ma première popup</a>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Chatbot Assistant -->
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
            document.querySelectorAll('.chatbot-answer').forEach(a => a.classList.remove('active'));
            document.getElementById('answer' + id).classList.add('active');
        }

        // Toggle Section
        function togglePopupSection(header) {
            const content = header.nextElementSibling;
            const isActive = header.classList.contains('active');

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

        // ==================================
        // IMAGE UPLOAD FUNCTIONALITY
        // ==================================

        const imageInput = document.getElementById('imageInput');
        const imageUrl = document.getElementById('imageUrl');
        const uploadArea = document.getElementById('uploadArea');
        const previewArea = document.getElementById('previewArea');
        const imagePreview = document.getElementById('imagePreview');
        const imageUploadWidget = document.getElementById('imageUploadWidget');

        // Show existing image if any
        if (imageUrl.value) {
            showImagePreview(imageUrl.value);
        }

        // Handle file selection
        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WEBP.');
                return;
            }

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Fichier trop volumineux. Taille maximale: 5 MB');
                return;
            }

            // Upload file
            uploadImage(file);
        });

        function uploadImage(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', '<?= csrfToken() ?>');

            // Show loading state
            uploadArea.innerHTML = '<p style="color: var(--pink-main); font-weight: 600;">Upload en cours...</p>';

            fetch('/admin/upload-popup-image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    imageUrl.value = data.url;
                    showImagePreview(data.url);
                } else {
                    alert('Erreur: ' + (data.error || 'Upload impossible'));
                    resetUploadArea();
                }
            })
            .catch(error => {
                alert('Erreur réseau: ' + error);
                resetUploadArea();
            });
        }

        function showImagePreview(url) {
            imagePreview.src = url;
            uploadArea.style.display = 'none';
            previewArea.style.display = 'block';
            imageUploadWidget.classList.add('has-image');
        }

        function removeImage() {
            imageUrl.value = '';
            imageInput.value = '';
            resetUploadArea();
        }

        function resetUploadArea() {
            uploadArea.style.display = 'block';
            previewArea.style.display = 'none';
            imageUploadWidget.classList.remove('has-image');
            uploadArea.innerHTML = `
                <div class="upload-icon">🖼️</div>
                <p style="color: var(--gray); margin-bottom: 15px;">Cliquez pour ajouter une image</p>
                <button type="button" class="upload-btn" onclick="document.getElementById('imageInput').click()">
                    Choisir une image
                </button>
                <p style="font-size: 0.85rem; color: var(--gray-dark); margin-top: 10px;">
                    JPG, PNG, GIF ou WEBP • Max 5 MB
                </p>
            `;
        }
    </script>
</body>
</html>
