<?php
/**
 * PERSONNALY - Admin : Gestion des Popups
 * Création et gestion des popups avec règles d'affichage et analytics
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
                // Modification
                if ($popupModel->update((int)$_POST['popup_id'], $data)) {
                    redirect('/admin/popups.php?success=Popup mise à jour');
                }
            } else {
                // Création
                $popupModel->create($data);
                redirect('/admin/popups.php?success=Popup créée');
            }
        } catch (Exception $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}

// Récupération des popups
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .popup-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-label {
            font-size: 11px;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
        }
        .stat-value.success { color: #10B981; }
        .stat-value.info { color: #3B82F6; }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-section {
            background: #F9FAFB;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .form-section-title {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #E5E7EB;
        }
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #374151;
        }
        @media (max-width: 968px) {
            .form-grid { grid-template-columns: 1fr; }
            .popup-stats { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Gestion des <span>Popups</span></h1>
                <?php if (!$editing): ?>
                    <button onclick="document.getElementById('popup-form').scrollIntoView({behavior: 'smooth'})" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Créer une popup
                    </button>
                <?php endif; ?>
            </div>

            <!-- Info box -->
            <div class="info-box" style="margin-bottom: 25px;">
                <strong>💡 Créez des popups ultra-modernes avec ciblage avancé et analytics en temps réel.</strong><br>
                Les couleurs et polices sont gérées automatiquement via vos paramètres de branding.
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Liste des popups -->
            <?php if (!empty($popups)): ?>
                <div class="content-table-container">
                    <table class="content-table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Type</th>
                                <th>Ciblage</th>
                                <th>Trigger</th>
                                <th>Stats</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popups as $popup): ?>
                                <?php $stats = $popupModel->getStats($popup['id']); ?>
                                <tr>
                                    <td>
                                        <strong><?= h($popup['title']) ?></strong>
                                        <?php if ($popup['priority'] > 0): ?>
                                            <span class="badge badge-warning" style="margin-left: 8px;">Priorité <?= $popup['priority'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge"><?= ucfirst(str_replace('_', ' ', $popup['template_type'])) ?></span>
                                    </td>
                                    <td><?= ucfirst(str_replace('_', ' ', $popup['target_pages'])) ?></td>
                                    <td><?= ucfirst($popup['trigger_type']) ?><?= $popup['trigger_value'] ? ' (' . $popup['trigger_value'] . ')' : '' ?></td>
                                    <td>
                                        <div class="popup-stats">
                                            <div class="stat-box">
                                                <div class="stat-label">Vues</div>
                                                <div class="stat-value info"><?= number_format($stats['total_views']) ?></div>
                                            </div>
                                            <div class="stat-box">
                                                <div class="stat-label">Clics</div>
                                                <div class="stat-value success"><?= number_format($stats['total_clicks']) ?></div>
                                            </div>
                                            <div class="stat-box">
                                                <div class="stat-label">Taux clic</div>
                                                <div class="stat-value"><?= $stats['click_rate'] ?>%</div>
                                            </div>
                                            <div class="stat-box">
                                                <div class="stat-label">Taux ferm.</div>
                                                <div class="stat-value"><?= $stats['close_rate'] ?>%</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($popup['is_active']): ?>
                                            <span class="badge badge-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-gray">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="?toggle=<?= $popup['id'] ?>&token=<?= csrfToken() ?>"
                                               class="btn btn-sm btn-secondary"
                                               title="<?= $popup['is_active'] ? 'Désactiver' : 'Activer' ?>">
                                                <?php if ($popup['is_active']): ?>
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                        <circle cx="12" cy="12" r="3"/>
                                                        <line x1="3" y1="3" x2="21" y2="21"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                        <circle cx="12" cy="12" r="3"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </a>
                                            <a href="?edit=<?= $popup['id'] ?>" class="btn btn-sm btn-primary" title="Modifier">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            </a>
                                            <a href="?delete=<?= $popup['id'] ?>&token=<?= csrfToken() ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Supprimer cette popup ?')"
                                               title="Supprimer">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📢</div>
                    <h3>Aucune popup</h3>
                    <p>Créez votre première popup pour engager vos visiteurs</p>
                </div>
            <?php endif; ?>

            <!-- Formulaire de création/modification -->
            <div class="card" id="popup-form" style="margin-top: 40px;">
                <div class="card-header">
                    <h2><?= $editing ? 'Modifier la popup' : 'Créer une popup' ?></h2>
                    <?php if ($editing): ?>
                        <a href="/admin/popups.php" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?= csrfField() ?>
                        <?php if ($editing): ?>
                            <input type="hidden" name="popup_id" value="<?= $editPopup['id'] ?>">
                        <?php endif; ?>

                        <!-- Contenu -->
                        <div class="form-section">
                            <div class="form-section-title">📝 Contenu de la popup</div>

                            <div class="form-group">
                                <label>Titre <span class="required">*</span></label>
                                <input type="text" name="title" class="form-input" required
                                       value="<?= h($editPopup['title'] ?? '') ?>"
                                       placeholder="Ex: Offre spéciale -20%">
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="content" class="form-textarea" rows="4"
                                          placeholder="Message principal de votre popup..."><?= h($editPopup['content'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>URL de l'image</label>
                                <input type="url" name="image_url" class="form-input"
                                       value="<?= h($editPopup['image_url'] ?? '') ?>"
                                       placeholder="/public/uploads/popup-image.jpg">
                                <small class="form-help">Image affichée dans la popup (optionnelle)</small>
                            </div>

                            <div class="form-group">
                                <label>Code promo (optionnel)</label>
                                <input type="text" name="promo_code" class="form-input"
                                       value="<?= h($editPopup['promo_code'] ?? '') ?>"
                                       placeholder="Ex: PROMO20">
                                <small class="form-help">Affiché en gros dans la popup si renseigné</small>
                            </div>
                        </div>

                        <!-- Call To Action -->
                        <div class="form-section">
                            <div class="form-section-title">🎯 Bouton d'action (CTA)</div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Texte du bouton</label>
                                    <input type="text" name="cta_text" class="form-input"
                                           value="<?= h($editPopup['cta_text'] ?? '') ?>"
                                           placeholder="Ex: J'en profite">
                                </div>

                                <div class="form-group">
                                    <label>URL du bouton</label>
                                    <input type="url" name="cta_url" class="form-input"
                                           value="<?= h($editPopup['cta_url'] ?? '') ?>"
                                           placeholder="/public/cart.php">
                                </div>
                            </div>

                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="cta_new_tab" value="1"
                                           <?= ($editPopup['cta_new_tab'] ?? false) ? 'checked' : '' ?>>
                                    Ouvrir dans un nouvel onglet
                                </label>
                            </div>
                        </div>

                        <!-- Règles d'affichage -->
                        <div class="form-section">
                            <div class="form-section-title">⚡ Règles d'affichage</div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Type de déclenchement</label>
                                    <select name="trigger_type" id="trigger_type" class="form-select">
                                        <option value="immediate" <?= ($editPopup['trigger_type'] ?? '') === 'immediate' ? 'selected' : '' ?>>Immédiat (à l'ouverture)</option>
                                        <option value="delay" <?= ($editPopup['trigger_type'] ?? '') === 'delay' ? 'selected' : '' ?>>Après un délai</option>
                                        <option value="scroll" <?= ($editPopup['trigger_type'] ?? '') === 'scroll' ? 'selected' : '' ?>>Au scroll</option>
                                        <option value="exit" <?= ($editPopup['trigger_type'] ?? '') === 'exit' ? 'selected' : '' ?>>À la sortie (exit intent)</option>
                                    </select>
                                </div>

                                <div class="form-group" id="trigger_value_group">
                                    <label id="trigger_value_label">Valeur</label>
                                    <input type="number" name="trigger_value" class="form-input"
                                           value="<?= h($editPopup['trigger_value'] ?? '') ?>"
                                           placeholder="3">
                                    <small class="form-help" id="trigger_value_help">Secondes pour le délai, % pour le scroll</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Fréquence d'affichage</label>
                                <select name="frequency" class="form-select">
                                    <option value="every_visit" <?= ($editPopup['frequency'] ?? '') === 'every_visit' ? 'selected' : '' ?>>À chaque visite</option>
                                    <option value="per_session" <?= ($editPopup['frequency'] ?? '') === 'per_session' ? 'selected' : '' ?>>Une fois par session</option>
                                    <option value="daily" <?= ($editPopup['frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>Une fois par jour</option>
                                    <option value="weekly" <?= ($editPopup['frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Une fois par semaine</option>
                                    <option value="monthly" <?= ($editPopup['frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Une fois par mois</option>
                                    <option value="until_click" <?= ($editPopup['frequency'] ?? '') === 'until_click' ? 'selected' : '' ?>>Jusqu'au clic (ne plus afficher après action)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Ciblage -->
                        <div class="form-section">
                            <div class="form-section-title">🎯 Ciblage</div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Pages ciblées</label>
                                    <select name="target_pages" id="target_pages" class="form-select">
                                        <option value="all" <?= ($editPopup['target_pages'] ?? '') === 'all' ? 'selected' : '' ?>>Toutes les pages</option>
                                        <option value="home" <?= ($editPopup['target_pages'] ?? '') === 'home' ? 'selected' : '' ?>>Page d'accueil uniquement</option>
                                        <option value="products" <?= ($editPopup['target_pages'] ?? '') === 'products' ? 'selected' : '' ?>>Pages produits</option>
                                        <option value="cart" <?= ($editPopup['target_pages'] ?? '') === 'cart' ? 'selected' : '' ?>>Panier</option>
                                        <option value="checkout" <?= ($editPopup['target_pages'] ?? '') === 'checkout' ? 'selected' : '' ?>>Checkout</option>
                                        <option value="specific" <?= ($editPopup['target_pages'] ?? '') === 'specific' ? 'selected' : '' ?>>URLs spécifiques</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Type de visiteurs</label>
                                    <select name="target_visitors" class="form-select">
                                        <option value="all" <?= ($editPopup['target_visitors'] ?? '') === 'all' ? 'selected' : '' ?>>Tous les visiteurs</option>
                                        <option value="new" <?= ($editPopup['target_visitors'] ?? '') === 'new' ? 'selected' : '' ?>>Nouveaux visiteurs</option>
                                        <option value="returning" <?= ($editPopup['target_visitors'] ?? '') === 'returning' ? 'selected' : '' ?>>Visiteurs récurrents</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group" id="target_urls_group" style="display: none;">
                                <label>URLs spécifiques (une par ligne)</label>
                                <textarea name="target_urls" class="form-textarea" rows="4"
                                          placeholder="/public/product.php&#10;/public/categorie-tshirts.php"><?php
                                    if (!empty($editPopup['target_urls'])) {
                                        $urls = json_decode($editPopup['target_urls'], true);
                                        echo h(is_array($urls) ? implode("\n", $urls) : '');
                                    }
                                ?></textarea>
                            </div>
                        </div>

                        <!-- Options de fermeture -->
                        <div class="form-section">
                            <div class="form-section-title">✖️ Options de fermeture</div>

                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="show_close_button" value="1"
                                           <?= ($editPopup['show_close_button'] ?? true) ? 'checked' : '' ?>>
                                    Afficher le bouton X
                                </label>

                                <label class="checkbox-label">
                                    <input type="checkbox" name="click_outside_to_close" value="1"
                                           <?= ($editPopup['click_outside_to_close'] ?? true) ? 'checked' : '' ?>>
                                    Fermer en cliquant en dehors
                                </label>

                                <label class="checkbox-label">
                                    <input type="checkbox" name="show_never_show_again" value="1"
                                           <?= ($editPopup['show_never_show_again'] ?? false) ? 'checked' : '' ?>>
                                    Afficher "Ne plus afficher"
                                </label>
                            </div>

                            <div class="form-group">
                                <label>Auto-fermeture (secondes)</label>
                                <input type="number" name="auto_close_after" class="form-input"
                                       value="<?= h($editPopup['auto_close_after'] ?? '') ?>"
                                       placeholder="Laisser vide pour pas d'auto-fermeture">
                                <small class="form-help">Popup se ferme automatiquement après X secondes</small>
                            </div>
                        </div>

                        <!-- Apparence -->
                        <div class="form-section">
                            <div class="form-section-title">🎨 Apparence</div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Template</label>
                                    <select name="template_type" class="form-select">
                                        <option value="modal" <?= ($editPopup['template_type'] ?? '') === 'modal' ? 'selected' : '' ?>>Modal centré (défaut)</option>
                                        <option value="banner_top" <?= ($editPopup['template_type'] ?? '') === 'banner_top' ? 'selected' : '' ?>>Bandeau haut</option>
                                        <option value="banner_bottom" <?= ($editPopup['template_type'] ?? '') === 'banner_bottom' ? 'selected' : '' ?>>Bandeau bas</option>
                                        <option value="corner" <?= ($editPopup['template_type'] ?? '') === 'corner' ? 'selected' : '' ?>>Coin bas-droit (notification)</option>
                                        <option value="fullscreen" <?= ($editPopup['template_type'] ?? '') === 'fullscreen' ? 'selected' : '' ?>>Plein écran</option>
                                        <option value="side_panel" <?= ($editPopup['template_type'] ?? '') === 'side_panel' ? 'selected' : '' ?>>Panel latéral</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Taille</label>
                                    <select name="size" class="form-select">
                                        <option value="small" <?= ($editPopup['size'] ?? '') === 'small' ? 'selected' : '' ?>>Petit</option>
                                        <option value="medium" <?= ($editPopup['size'] ?? '') === 'medium' ? 'selected' : '' ?>>Moyen</option>
                                        <option value="large" <?= ($editPopup['size'] ?? '') === 'large' ? 'selected' : '' ?>>Grand</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Animation d'entrée</label>
                                <select name="animation" class="form-select">
                                    <option value="fade" <?= ($editPopup['animation'] ?? '') === 'fade' ? 'selected' : '' ?>>Fade (fondu)</option>
                                    <option value="slide_up" <?= ($editPopup['animation'] ?? '') === 'slide_up' ? 'selected' : '' ?>>Slide up (montée)</option>
                                    <option value="slide_down" <?= ($editPopup['animation'] ?? '') === 'slide_down' ? 'selected' : '' ?>>Slide down (descente)</option>
                                    <option value="scale" <?= ($editPopup['animation'] ?? '') === 'scale' ? 'selected' : '' ?>>Scale (zoom)</option>
                                    <option value="slide_right" <?= ($editPopup['animation'] ?? '') === 'slide_right' ? 'selected' : '' ?>>Slide right (glissement)</option>
                                </select>
                            </div>

                            <div class="info-box">
                                <strong>🎨 Les couleurs et polices sont automatiquement adaptées via vos paramètres de branding.</strong>
                            </div>
                        </div>

                        <!-- Paramètres -->
                        <div class="form-section">
                            <div class="form-section-title">⚙️ Paramètres</div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Priorité d'affichage</label>
                                    <input type="number" name="priority" class="form-input"
                                           value="<?= h($editPopup['priority'] ?? 0) ?>"
                                           placeholder="0">
                                    <small class="form-help">Plus le nombre est élevé, plus la popup sera prioritaire</small>
                                </div>

                                <div class="form-group">
                                    <label class="checkbox-label" style="padding-top: 10px;">
                                        <input type="checkbox" name="is_active" value="1"
                                               <?= ($editPopup['is_active'] ?? true) ? 'checked' : '' ?>>
                                        <strong>Popup active</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                    <polyline points="17 21 17 13 7 13 7 21"/>
                                    <polyline points="7 3 7 8 15 8"/>
                                </svg>
                                <?= $editing ? 'Mettre à jour' : 'Créer la popup' ?>
                            </button>
                            <?php if ($editing): ?>
                                <a href="/admin/popups.php" class="btn btn-secondary">Annuler</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Gestion dynamique du formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const triggerType = document.getElementById('trigger_type');
            const triggerValueGroup = document.getElementById('trigger_value_group');
            const triggerValueLabel = document.getElementById('trigger_value_label');
            const triggerValueHelp = document.getElementById('trigger_value_help');

            const targetPages = document.getElementById('target_pages');
            const targetUrlsGroup = document.getElementById('target_urls_group');

            // Gestion du trigger value
            function updateTriggerValue() {
                const type = triggerType.value;
                if (type === 'immediate' || type === 'exit') {
                    triggerValueGroup.style.display = 'none';
                } else {
                    triggerValueGroup.style.display = 'block';
                    if (type === 'delay') {
                        triggerValueLabel.textContent = 'Délai (secondes)';
                        triggerValueHelp.textContent = 'Nombre de secondes avant l\'affichage';
                    } else if (type === 'scroll') {
                        triggerValueLabel.textContent = 'Pourcentage de scroll';
                        triggerValueHelp.textContent = 'Afficher après X% de scroll (ex: 50)';
                    }
                }
            }

            // Gestion des URLs spécifiques
            function updateTargetUrls() {
                targetUrlsGroup.style.display = targetPages.value === 'specific' ? 'block' : 'none';
            }

            triggerType.addEventListener('change', updateTriggerValue);
            targetPages.addEventListener('change', updateTargetUrls);

            // Init
            updateTriggerValue();
            updateTargetUrls();
        });
    </script>
</body>
</html>
