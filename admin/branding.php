<?php
/**
 * PERSONNALY - Admin : Branding
 * Configuration identité visuelle (global ou par client)
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/Branding.php';
require_once __DIR__ . '/../app/models/ShopSettings.php';
require_once __DIR__ . '/../app/models/Font.php';

Auth::requireAdmin();

$orderModel = new Order();
$pendingOrders = $orderModel->countNew();
$brandingModel = new Branding();
$shopSettings = new ShopSettings();
$fontModel = new Font();

$success = '';
$error = '';
$tableExists = $brandingModel->tableExists();

// Onglet actif (identity, colors, typography, topbar, appearance)
$activeTab = $_GET['tab'] ?? 'identity';

// Client ID (null = global config)
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;

// Charger la config actuelle (utilise defaults si table n'existe pas)
$config = $brandingModel->resolveBranding($clientId);

// Messages de succès via GET (après redirect POST)
$successMessages = [
    'branding' => 'Configuration sauvegardée avec succès.',
    'topbar' => 'Paramètres de la top bar enregistrés.',
    'appearance' => 'Couleurs enregistrées.',
    'typography' => 'Typographie enregistrée avec succès.',
];
$successKey = $_GET['success'] ?? '';
if (isset($successMessages[$successKey])) {
    $success = $successMessages[$successKey];
}

// Traitement du formulaire
if (isPost()) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF invalide.';
    } elseif (isset($_POST['save_branding'])) {
        // Sauvegarde branding (identité, couleurs, polices)
        if (!$tableExists) {
            $error = 'La table branding_settings n\'existe pas. Exécutez la migration SQL d\'abord.';
        } else {
            $data = [
                'font_primary' => post('font_primary', ''),
                'font_primary_url' => post('font_primary_url', ''),
                'font_secondary' => post('font_secondary', ''),
                'font_secondary_url' => post('font_secondary_url', ''),
                'color_primary' => post('color_primary', ''),
                'color_secondary' => post('color_secondary', ''),
                'color_accent' => post('color_accent', ''),
                'color_text' => post('color_text', ''),
                'color_text_light' => post('color_text_light', ''),
                'color_background' => post('color_background', ''),
                'color_surface' => post('color_surface', ''),
                'color_button' => post('color_button', ''),
                'color_button_text' => post('color_button_text', ''),
                'border_radius' => post('border_radius', 'medium'),
                'shadow_intensity' => post('shadow_intensity', 'subtle'),
                'logo_url' => post('logo_url', ''),
                'logo_light_url' => post('logo_light_url', ''),
                'favicon_url' => post('favicon_url', ''),
            ];

            // Nettoyer les valeurs vides
            foreach ($data as $key => $value) {
                if ($value === '') {
                    $data[$key] = null;
                }
            }

            try {
                if ($clientId === null) {
                    $brandingModel->upsertGlobal($data);
                } else {
                    $brandingModel->upsertForClient($clientId, $data);
                }
                $redirectUrl = '/admin/branding.php?tab=' . $activeTab . '&success=branding';
                if ($clientId) $redirectUrl .= '&client_id=' . $clientId;
                redirect($redirectUrl);
            } catch (Exception $e) {
                $error = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['save_topbar'])) {
        // Sauvegarde TopBar
        $shopSettings->setMultiple([
            'topbar_enabled' => isset($_POST['topbar_enabled']) ? '1' : '0',
            'topbar_text' => post('topbar_text', ''),
            'topbar_link' => post('topbar_link', ''),
            'topbar_bg_color' => post('topbar_bg_color', '#1a1a2e'),
            'topbar_text_color' => post('topbar_text_color', '#ffffff'),
            'topbar_font_family' => post('topbar_font_family', 'inherit'),
            'topbar_font_size' => post('topbar_font_size', '14'),
            'topbar_scroll_speed' => post('topbar_scroll_speed', '30')
        ]);
        $shopSettings->clearCache();
        redirect('/admin/branding.php?tab=topbar&success=topbar');
    } elseif (isset($_POST['save_appearance'])) {
        // Sauvegarde Apparence header/footer
        $shopSettings->setMultiple([
            'header_bg_color' => post('header_bg_color', '#1a1a2e'),
            'header_text_color' => post('header_text_color', '#ffffff'),
            'footer_bg_color' => post('footer_bg_color', '#1a1a2e'),
            'footer_text_color' => post('footer_text_color', '#ffffff')
        ]);
        $shopSettings->clearCache();
        redirect('/admin/branding.php?tab=appearance&success=appearance');
    } elseif (isset($_POST['save_typography'])) {
        // Sauvegarde Typographie
        if (!$tableExists) {
            $error = 'La table branding_settings n\'existe pas. Exécutez la migration SQL d\'abord.';
        } else {
            // Construire typography_scale JSON
            $typographyScale = [];
            $levels = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'body', 'small', 'lead'];
            foreach ($levels as $level) {
                $typographyScale[$level] = [
                    'font' => post("typo_{$level}_font", 'primary'),
                    'size' => post("typo_{$level}_size", '1rem'),
                    'weight' => post("typo_{$level}_weight", '400'),
                    'line_height' => post("typo_{$level}_line_height", '1.5')
                ];
            }

            // Construire button_styles JSON
            $buttonStyles = [];
            $buttonTypes = ['primary', 'secondary', 'danger', 'success', 'outline'];
            foreach ($buttonTypes as $type) {
                $buttonStyles[$type] = [
                    'bg_color' => post("btn_{$type}_bg", '#6366F1'),
                    'text_color' => post("btn_{$type}_text", '#FFFFFF'),
                    'hover_bg' => post("btn_{$type}_hover_bg", '#4F46E5'),
                    'hover_text' => post("btn_{$type}_hover_text", '#FFFFFF'),
                    'border_color' => post("btn_{$type}_border", 'transparent'),
                    'border_width' => post("btn_{$type}_border_width", '0px')
                ];
            }

            // Construire color_system JSON
            $colorSystem = [
                'primary' => [
                    'base' => post('color_primary_base', '#6366F1'),
                    'hover' => post('color_primary_hover', '#4F46E5'),
                    'active' => post('color_primary_active', '#4338CA'),
                    'disabled' => post('color_primary_disabled', '#A5B4FC'),
                    'text_on' => post('color_primary_text_on', '#FFFFFF')
                ],
                'secondary' => [
                    'base' => post('color_secondary_base', '#8B5CF6'),
                    'hover' => post('color_secondary_hover', '#7C3AED'),
                    'active' => post('color_secondary_active', '#6D28D9'),
                    'disabled' => post('color_secondary_disabled', '#C4B5FD'),
                    'text_on' => post('color_secondary_text_on', '#FFFFFF')
                ],
                'accent' => [
                    'base' => post('color_accent_base', '#F59E0B'),
                    'hover' => post('color_accent_hover', '#D97706'),
                    'active' => post('color_accent_active', '#B45309'),
                    'disabled' => post('color_accent_disabled', '#FCD34D'),
                    'text_on' => post('color_accent_text_on', '#FFFFFF')
                ],
                'text' => [
                    'primary' => post('color_text_primary', '#1F2937'),
                    'secondary' => post('color_text_secondary', '#6B7280'),
                    'tertiary' => post('color_text_tertiary', '#9CA3AF'),
                    'disabled' => post('color_text_disabled', '#D1D5DB'),
                    'on_dark' => post('color_text_on_dark', '#FFFFFF')
                ],
                'background' => [
                    'primary' => post('color_bg_primary', '#FFFFFF'),
                    'secondary' => post('color_bg_secondary', '#F9FAFB'),
                    'tertiary' => post('color_bg_tertiary', '#F3F4F6'),
                    'inverse' => post('color_bg_inverse', '#1F2937')
                ],
                'border' => [
                    'primary' => post('color_border_primary', '#E5E7EB'),
                    'secondary' => post('color_border_secondary', '#D1D5DB'),
                    'focus' => post('color_border_focus', '#6366F1')
                ],
                'status' => [
                    'success' => post('color_status_success', '#10B981'),
                    'success_bg' => post('color_status_success_bg', '#D1FAE5'),
                    'warning' => post('color_status_warning', '#F59E0B'),
                    'warning_bg' => post('color_status_warning_bg', '#FEF3C7'),
                    'error' => post('color_status_error', '#EF4444'),
                    'error_bg' => post('color_status_error_bg', '#FEE2E2'),
                    'info' => post('color_status_info', '#3B82F6'),
                    'info_bg' => post('color_status_info_bg', '#DBEAFE')
                ]
            ];

            $data = [
                'font_primary_id' => post('font_primary_id', null),
                'font_secondary_id' => post('font_secondary_id', null),
                'typography_scale' => json_encode($typographyScale),
                'button_styles' => json_encode($buttonStyles),
                'color_system' => json_encode($colorSystem)
            ];

            // Nettoyer les valeurs vides
            foreach ($data as $key => $value) {
                if ($value === '' || $value === 'null') {
                    $data[$key] = null;
                }
            }

            try {
                if ($clientId === null) {
                    $brandingModel->upsertGlobal($data);
                } else {
                    $brandingModel->upsertForClient($clientId, $data);
                }
                $redirectUrl = '/admin/branding.php?tab=typography&success=typography';
                if ($clientId) $redirectUrl .= '&client_id=' . $clientId;
                redirect($redirectUrl);
            } catch (Exception $e) {
                $error = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
            }
        }
    }
}

// Options pour les selects
$borderRadiusOptions = [
    'none' => 'Aucun (0px)',
    'small' => 'Petit (4px)',
    'medium' => 'Moyen (8px)',
    'large' => 'Grand (12px)',
    'full' => 'Complet (arrondi)',
];

$shadowOptions = [
    'none' => 'Aucune',
    'subtle' => 'Légère',
    'medium' => 'Moyenne',
    'strong' => 'Forte',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branding - PERSONNALY Admin</title>
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
                <h1 class="page-title">
                    <span>Branding</span>
                    <?php if ($clientId): ?>
                        <span class="badge badge-mint">Client #<?= $clientId ?></span>
                    <?php else: ?>
                        <span class="badge badge-purple">Configuration globale</span>
                    <?php endif; ?>
                </h1>
            </div>

            <!-- Navigation sous-onglets -->
            <div class="sub-tabs-wrapper">
                <div class="sub-tabs">
                    <a href="?tab=identity" class="sub-tab <?= $activeTab === 'identity' ? 'active' : '' ?>">Identité & Logos</a>
                    <a href="?tab=colors" class="sub-tab <?= $activeTab === 'colors' ? 'active' : '' ?>">Couleurs & Style</a>
                    <a href="?tab=typography" class="sub-tab <?= $activeTab === 'typography' ? 'active' : '' ?>">Typographie</a>
                    <a href="?tab=topbar" class="sub-tab <?= $activeTab === 'topbar' ? 'active' : '' ?>">Top Bar</a>
                    <a href="?tab=appearance" class="sub-tab <?= $activeTab === 'appearance' ? 'active' : '' ?>">Header & Footer</a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <?php if (!$tableExists && in_array($activeTab, ['identity', 'colors'])): ?>
                <div class="alert alert-warning">
                    <strong>Migration requise</strong><br>
                    La table <code>branding_settings</code> n'existe pas encore.
                    Exécutez le fichier <code>sql/migrate_branding.sql</code> dans phpMyAdmin pour activer cette fonctionnalité.
                    <br><small>En attendant, les valeurs par défaut sont affichées.</small>
                </div>
            <?php endif; ?>

            <!-- ========== ONGLET IDENTITÉ ========== -->
            <?php if ($activeTab === 'identity'): ?>
            <form method="post" class="branding-form">
                <?= csrfField() ?>
                <input type="hidden" name="save_branding" value="1">

                <!-- Section: Identité (Logos) -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Logos & Identité</h3>
                    </div>
                    <div class="card-body">
                        <div class="logo-grid">
                            <!-- Logo principal -->
                            <div class="logo-upload-card">
                                <div class="logo-preview-modern" id="logo_preview">
                                    <?php if (!empty($config['logo_url'])): ?>
                                        <img src="<?= h($config['logo_url']) ?>" alt="Logo">
                                    <?php else: ?>
                                        <div class="logo-placeholder">
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                <path d="M21 15l-5-5L5 21"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="logo-info">
                                    <h4>Logo principal</h4>
                                    <p>Utilisé sur fond clair (header, factures...)</p>
                                    <input type="hidden" name="logo_url" id="logo_url" value="<?= h($config['logo_url'] ?? '') ?>">
                                    <button type="button" class="btn btn-outline" onclick="uploadImage('logo_url', 'logo_preview')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="17 8 12 3 7 8"/>
                                            <line x1="12" y1="3" x2="12" y2="15"/>
                                        </svg>
                                        Changer
                                    </button>
                                </div>
                            </div>

                            <!-- Logo clair (fond sombre) -->
                            <div class="logo-upload-card dark">
                                <div class="logo-preview-modern dark" id="logo_light_preview">
                                    <?php if (!empty($config['logo_light_url'])): ?>
                                        <img src="<?= h($config['logo_light_url']) ?>" alt="Logo clair">
                                    <?php else: ?>
                                        <div class="logo-placeholder">
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                <path d="M21 15l-5-5L5 21"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="logo-info">
                                    <h4>Logo fond sombre</h4>
                                    <p>Variante pour fond sombre (footer...)</p>
                                    <input type="hidden" name="logo_light_url" id="logo_light_url" value="<?= h($config['logo_light_url'] ?? '') ?>">
                                    <button type="button" class="btn btn-outline" onclick="uploadImage('logo_light_url', 'logo_light_preview')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="17 8 12 3 7 8"/>
                                            <line x1="12" y1="3" x2="12" y2="15"/>
                                        </svg>
                                        Changer
                                    </button>
                                </div>
                            </div>

                            <!-- Favicon -->
                            <div class="logo-upload-card favicon">
                                <div class="logo-preview-modern favicon" id="favicon_preview">
                                    <?php if (!empty($config['favicon_url'])): ?>
                                        <img src="<?= h($config['favicon_url']) ?>" alt="Favicon">
                                    <?php else: ?>
                                        <div class="logo-placeholder">
                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <rect x="4" y="4" width="16" height="16" rx="2"/>
                                                <path d="M9 9h6v6H9z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="logo-info">
                                    <h4>Favicon</h4>
                                    <p>Icône onglet navigateur (32×32 ou 64×64 px)</p>
                                    <input type="hidden" name="favicon_url" id="favicon_url" value="<?= h($config['favicon_url'] ?? '') ?>">
                                    <button type="button" class="btn btn-outline" onclick="uploadImage('favicon_url', 'favicon_preview')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="17 8 12 3 7 8"/>
                                            <line x1="12" y1="3" x2="12" y2="15"/>
                                        </svg>
                                        Changer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Polices -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Polices du site</h3>
                        <span class="header-note">Branding global (pas les polices de personnalisation produit)</span>
                    </div>
                    <div class="card-body">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Police principale (titres)</label>
                                <input type="text" name="font_primary" class="form-input"
                                       value="<?= h($config['font_primary'] ?? '') ?>"
                                       placeholder="ex: Poppins">
                            </div>
                            <div class="form-group">
                                <label class="form-label">URL Google Fonts</label>
                                <input type="url" name="font_primary_url" class="form-input"
                                       value="<?= h($config['font_primary_url'] ?? '') ?>"
                                       placeholder="https://fonts.googleapis.com/css2?family=...">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Police secondaire (texte)</label>
                                <input type="text" name="font_secondary" class="form-input"
                                       value="<?= h($config['font_secondary'] ?? '') ?>"
                                       placeholder="ex: Inter">
                            </div>
                            <div class="form-group">
                                <label class="form-label">URL Google Fonts</label>
                                <input type="url" name="font_secondary_url" class="form-input"
                                       value="<?= h($config['font_secondary_url'] ?? '') ?>"
                                       placeholder="https://fonts.googleapis.com/css2?family=...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Identity -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">Sauvegarder</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- ========== ONGLET COULEURS & STYLE ========== -->
            <?php if ($activeTab === 'colors'): ?>
            <form method="post" class="branding-form">
                <?= csrfField() ?>
                <input type="hidden" name="save_branding" value="1">

                <!-- Section: Couleurs -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Couleurs</h3>
                    </div>
                    <div class="card-body">
                        <div class="color-grid">
                            <!-- Couleurs principales -->
                            <div class="color-group">
                                <h4>Couleurs principales</h4>
                                <div class="color-inputs">
                                    <div class="color-input-wrapper">
                                        <label>Primaire</label>
                                        <div class="color-input-row">
                                            <input type="color" name="color_primary" id="color_primary"
                                                   value="<?= h($config['color_primary'] ?? '#6366F1') ?>">
                                            <input type="text" class="color-hex"
                                                   value="<?= h($config['color_primary'] ?? '#6366F1') ?>"
                                                   data-target="color_primary">
                                        </div>
                                    </div>
                                    <div class="color-input-wrapper">
                                        <label>Secondaire</label>
                                        <div class="color-input-row">
                                            <input type="color" name="color_secondary" id="color_secondary"
                                                   value="<?= h($config['color_secondary'] ?? '#8B5CF6') ?>">
                                            <input type="text" class="color-hex"
                                                   value="<?= h($config['color_secondary'] ?? '#8B5CF6') ?>"
                                                   data-target="color_secondary">
                                        </div>
                                    </div>
                                    <div class="color-input-wrapper">
                                        <label>Accent</label>
                                        <div class="color-input-row">
                                            <input type="color" name="color_accent" id="color_accent"
                                                   value="<?= h($config['color_accent'] ?? '#F59E0B') ?>">
                                            <input type="text" class="color-hex"
                                                   value="<?= h($config['color_accent'] ?? '#F59E0B') ?>"
                                                   data-target="color_accent">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Couleurs texte -->
                            <div class="color-group">
                                <h4>Texte</h4>
                                <div class="color-inputs">
                                    <div class="color-input-wrapper">
                                        <label>Principal</label>
                                        <div class="color-input-row">
                                            <input type="color" name="color_text" id="color_text"
                                                   value="<?= h($config['color_text'] ?? '#1F2937') ?>">
                                            <input type="text" class="color-hex"
                                                   value="<?= h($config['color_text'] ?? '#1F2937') ?>"
                                                   data-target="color_text">
                                        </div>
                                    </div>
                                    <div class="color-input-wrapper">
                                        <label>Secondaire</label>
                                        <div class="color-input-row">
                                            <input type="color" name="color_text_light" id="color_text_light"
                                                   value="<?= h($config['color_text_light'] ?? '#6B7280') ?>">
                                            <input type="text" class="color-hex"
                                                   value="<?= h($config['color_text_light'] ?? '#6B7280') ?>"
                                                   data-target="color_text_light">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Couleurs fond -->
                            <div class="color-group">
                                <h4>Fonds</h4>
                                <div class="color-inputs">
                                    <div class="color-input-wrapper">
                                        <label>Background</label>
                                        <div class="color-input-row">
                                            <input type="color" name="color_background" id="color_background"
                                                   value="<?= h($config['color_background'] ?? '#FFFFFF') ?>">
                                            <input type="text" class="color-hex"
                                                   value="<?= h($config['color_background'] ?? '#FFFFFF') ?>"
                                                   data-target="color_background">
                                        </div>
                                    </div>
                                    <div class="color-input-wrapper">
                                        <label>Surface</label>
                                        <div class="color-input-row">
                                            <input type="color" name="color_surface" id="color_surface"
                                                   value="<?= h($config['color_surface'] ?? '#F9FAFB') ?>">
                                            <input type="text" class="color-hex"
                                                   value="<?= h($config['color_surface'] ?? '#F9FAFB') ?>"
                                                   data-target="color_surface">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Couleurs boutons -->
                            <div class="color-group">
                                <h4>Boutons</h4>
                                <div class="color-inputs">
                                    <div class="color-input-wrapper">
                                        <label>Fond bouton</label>
                                        <div class="color-input-row">
                                            <input type="color" name="color_button" id="color_button"
                                                   value="<?= h($config['color_button'] ?? '#6366F1') ?>">
                                            <input type="text" class="color-hex"
                                                   value="<?= h($config['color_button'] ?? '#6366F1') ?>"
                                                   data-target="color_button">
                                        </div>
                                    </div>
                                    <div class="color-input-wrapper">
                                        <label>Texte bouton</label>
                                        <div class="color-input-row">
                                            <input type="color" name="color_button_text" id="color_button_text"
                                                   value="<?= h($config['color_button_text'] ?? '#FFFFFF') ?>">
                                            <input type="text" class="color-hex"
                                                   value="<?= h($config['color_button_text'] ?? '#FFFFFF') ?>"
                                                   data-target="color_button_text">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Style UI -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Style UI</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Arrondi des coins</label>
                                <select name="border_radius" class="form-input">
                                    <?php foreach ($borderRadiusOptions as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($config['border_radius'] ?? 'medium') === $value ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Intensité des ombres</label>
                                <select name="shadow_intensity" class="form-input">
                                    <?php foreach ($shadowOptions as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($config['shadow_intensity'] ?? 'subtle') === $value ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Aperçu en direct</h3>
                    </div>
                    <div class="card-body">
                        <div class="branding-preview" id="brandingPreview">
                            <div class="preview-header">
                                <div class="preview-logo">LOGO</div>
                                <nav class="preview-nav">
                                    <span>Accueil</span>
                                    <span>Produits</span>
                                    <span>Contact</span>
                                </nav>
                            </div>
                            <div class="preview-hero">
                                <h2>Titre principal</h2>
                                <p>Texte secondaire avec la police secondaire</p>
                                <button class="preview-btn">Bouton d'action</button>
                            </div>
                            <div class="preview-card">
                                <h4>Carte exemple</h4>
                                <p>Surface avec ombre et border-radius</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Colors -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">Sauvegarder</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- ========== ONGLET TOP BAR ========== -->
            <?php if ($activeTab === 'topbar'): ?>
            <form method="post" class="branding-form">
                <?= csrfField() ?>
                <input type="hidden" name="save_topbar" value="1">

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
                            <small class="form-hint">URL vers laquelle le texte redirige au clic</small>
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
                                <div class="color-picker-row">
                                    <input type="color" name="topbar_bg_color" id="topbarBgColor"
                                           value="<?= h($shopSettings->get('topbar_bg_color', '#1a1a2e')) ?>">
                                    <input type="text" class="form-input color-text" id="topbarBgColorText"
                                           value="<?= h($shopSettings->get('topbar_bg_color', '#1a1a2e')) ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Couleur du texte</label>
                                <div class="color-picker-row">
                                    <input type="color" name="topbar_text_color" id="topbarTextColor"
                                           value="<?= h($shopSettings->get('topbar_text_color', '#ffffff')) ?>">
                                    <input type="text" class="form-input color-text" id="topbarTextColorText"
                                           value="<?= h($shopSettings->get('topbar_text_color', '#ffffff')) ?>">
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
                                    <option value="Verdana, sans-serif" <?= $shopSettings->get('topbar_font_family') === 'Verdana, sans-serif' ? 'selected' : '' ?>>Verdana</option>
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
                            <small class="form-hint">Durée d'un cycle complet (plus grand = plus lent)</small>
                        </div>
                    </div>
                </div>

                <!-- Preview TopBar -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Aperçu en temps réel</h3>
                    </div>
                    <div class="card-body">
                        <div class="site-preview-box">
                            <div id="topbarPreview" class="topbar-preview" style="background: <?= h($shopSettings->get('topbar_bg_color', '#1a1a2e')) ?>;">
                                <span id="topbarPreviewText" style="color: <?= h($shopSettings->get('topbar_text_color', '#ffffff')) ?>; font-family: <?= h($shopSettings->get('topbar_font_family', 'inherit')) ?>; font-size: <?= h($shopSettings->get('topbar_font_size', '14')) ?>px;">
                                    <?= h($shopSettings->get('topbar_text', 'Livraison GRATUITE dès 50€ d\'achat !')) ?>
                                </span>
                            </div>
                            <div class="header-preview-placeholder">
                                <span><?= h($shopSettings->get('site_name', 'PERSONNALY')) ?></span>
                                <div class="preview-nav-links">
                                    <span>Produits</span>
                                    <span>Panier</span>
                                </div>
                            </div>
                            <div class="content-preview-placeholder">Contenu de la page...</div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">Sauvegarder</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- ========== ONGLET HEADER & FOOTER ========== -->
            <?php if ($activeTab === 'appearance'): ?>
            <form method="post" class="branding-form">
                <?= csrfField() ?>
                <input type="hidden" name="save_appearance" value="1">

                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Couleurs du Header (Navigation)</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Couleur de fond</label>
                                <div class="color-picker-row">
                                    <input type="color" name="header_bg_color" id="headerBgColor"
                                           value="<?= h($shopSettings->get('header_bg_color', '#1a1a2e')) ?>">
                                    <input type="text" class="form-input color-text" id="headerBgColorText"
                                           value="<?= h($shopSettings->get('header_bg_color', '#1a1a2e')) ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Couleur du texte</label>
                                <div class="color-picker-row">
                                    <input type="color" name="header_text_color" id="headerTextColor"
                                           value="<?= h($shopSettings->get('header_text_color', '#ffffff')) ?>">
                                    <input type="text" class="form-input color-text" id="headerTextColorText"
                                           value="<?= h($shopSettings->get('header_text_color', '#ffffff')) ?>">
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
                                <div class="color-picker-row">
                                    <input type="color" name="footer_bg_color" id="footerBgColor"
                                           value="<?= h($shopSettings->get('footer_bg_color', '#1a1a2e')) ?>">
                                    <input type="text" class="form-input color-text" id="footerBgColorText"
                                           value="<?= h($shopSettings->get('footer_bg_color', '#1a1a2e')) ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Couleur du texte</label>
                                <div class="color-picker-row">
                                    <input type="color" name="footer_text_color" id="footerTextColor"
                                           value="<?= h($shopSettings->get('footer_text_color', '#ffffff')) ?>">
                                    <input type="text" class="form-input color-text" id="footerTextColorText"
                                           value="<?= h($shopSettings->get('footer_text_color', '#ffffff')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Header/Footer -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Aperçu en temps réel</h3>
                    </div>
                    <div class="card-body">
                        <div class="site-preview-box">
                            <div id="headerPreview" class="header-preview" style="background: <?= h($shopSettings->get('header_bg_color', '#1a1a2e')) ?>;">
                                <span id="headerPreviewBrand" style="color: <?= h($shopSettings->get('header_text_color', '#ffffff')) ?>;"><?= h($shopSettings->get('site_name', 'PERSONNALY')) ?></span>
                                <div id="headerPreviewNav" style="color: <?= h($shopSettings->get('header_text_color', '#ffffff')) ?>;">
                                    <span>Produits</span>
                                    <span>Contact</span>
                                    <span>Panier</span>
                                </div>
                            </div>
                            <div class="content-preview-placeholder" style="height: 80px;">Contenu de la page...</div>
                            <div id="footerPreview" class="footer-preview" style="background: <?= h($shopSettings->get('footer_bg_color', '#1a1a2e')) ?>;">
                                <span id="footerPreviewBrand" style="color: <?= h($shopSettings->get('footer_text_color', '#ffffff')) ?>;"><?= h($shopSettings->get('site_name', 'PERSONNALY')) ?></span>
                                <span id="footerPreviewCopy" style="color: <?= h($shopSettings->get('footer_text_color', '#ffffff')) ?>;">© <?= date('Y') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">Sauvegarder</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- ========== ONGLET TYPOGRAPHIE ========== -->
            <?php if ($activeTab === 'typography'): ?>
            <?php
            // Charger les fonts actives
            $fonts = $fontModel->findActive();

            // Récupérer les valeurs actuelles
            $typographyScale = !empty($config['typography_scale']) ? (is_string($config['typography_scale']) ? json_decode($config['typography_scale'], true) : $config['typography_scale']) : [];
            $buttonStyles = !empty($config['button_styles']) ? (is_string($config['button_styles']) ? json_decode($config['button_styles'], true) : $config['button_styles']) : [];
            $colorSystem = !empty($config['color_system']) ? (is_string($config['color_system']) ? json_decode($config['color_system'], true) : $config['color_system']) : [];

            // Valeurs par défaut si vides
            $typographyDefaults = [
                'h1' => ['font' => 'primary', 'size' => '3rem', 'weight' => '700', 'line_height' => '1.2'],
                'h2' => ['font' => 'primary', 'size' => '2.5rem', 'weight' => '600', 'line_height' => '1.3'],
                'h3' => ['font' => 'primary', 'size' => '2rem', 'weight' => '600', 'line_height' => '1.4'],
                'h4' => ['font' => 'primary', 'size' => '1.5rem', 'weight' => '500', 'line_height' => '1.4'],
                'h5' => ['font' => 'secondary', 'size' => '1.25rem', 'weight' => '500', 'line_height' => '1.5'],
                'h6' => ['font' => 'secondary', 'size' => '1rem', 'weight' => '500', 'line_height' => '1.5'],
                'body' => ['font' => 'secondary', 'size' => '1rem', 'weight' => '400', 'line_height' => '1.6'],
                'small' => ['font' => 'secondary', 'size' => '0.875rem', 'weight' => '400', 'line_height' => '1.5'],
                'lead' => ['font' => 'secondary', 'size' => '1.125rem', 'weight' => '400', 'line_height' => '1.7']
            ];

            $buttonDefaults = [
                'primary' => ['bg_color' => '#6366F1', 'text_color' => '#FFFFFF', 'hover_bg' => '#4F46E5', 'hover_text' => '#FFFFFF', 'border_color' => 'transparent', 'border_width' => '0px'],
                'secondary' => ['bg_color' => '#E5E7EB', 'text_color' => '#1F2937', 'hover_bg' => '#D1D5DB', 'hover_text' => '#111827', 'border_color' => 'transparent', 'border_width' => '0px'],
                'danger' => ['bg_color' => '#EF4444', 'text_color' => '#FFFFFF', 'hover_bg' => '#DC2626', 'hover_text' => '#FFFFFF', 'border_color' => 'transparent', 'border_width' => '0px'],
                'success' => ['bg_color' => '#10B981', 'text_color' => '#FFFFFF', 'hover_bg' => '#059669', 'hover_text' => '#FFFFFF', 'border_color' => 'transparent', 'border_width' => '0px'],
                'outline' => ['bg_color' => 'transparent', 'text_color' => '#6366F1', 'hover_bg' => '#6366F1', 'hover_text' => '#FFFFFF', 'border_color' => '#6366F1', 'border_width' => '2px']
            ];

            $colorDefaults = [
                'primary' => ['base' => '#6366F1', 'hover' => '#4F46E5', 'active' => '#4338CA', 'disabled' => '#A5B4FC', 'text_on' => '#FFFFFF'],
                'secondary' => ['base' => '#8B5CF6', 'hover' => '#7C3AED', 'active' => '#6D28D9', 'disabled' => '#C4B5FD', 'text_on' => '#FFFFFF'],
                'accent' => ['base' => '#F59E0B', 'hover' => '#D97706', 'active' => '#B45309', 'disabled' => '#FCD34D', 'text_on' => '#FFFFFF'],
                'text' => ['primary' => '#1F2937', 'secondary' => '#6B7280', 'tertiary' => '#9CA3AF', 'disabled' => '#D1D5DB', 'on_dark' => '#FFFFFF'],
                'background' => ['primary' => '#FFFFFF', 'secondary' => '#F9FAFB', 'tertiary' => '#F3F4F6', 'inverse' => '#1F2937'],
                'border' => ['primary' => '#E5E7EB', 'secondary' => '#D1D5DB', 'focus' => '#6366F1'],
                'status' => ['success' => '#10B981', 'success_bg' => '#D1FAE5', 'warning' => '#F59E0B', 'warning_bg' => '#FEF3C7', 'error' => '#EF4444', 'error_bg' => '#FEE2E2', 'info' => '#3B82F6', 'info_bg' => '#DBEAFE']
            ];
            ?>
            <form method="post" class="branding-form">
                <?= csrfField() ?>
                <input type="hidden" name="save_typography" value="1">

                <!-- Section: Polices de base -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Polices de base</h3>
                        <span class="header-note">Polices principales utilisées sur tout le site</span>
                    </div>
                    <div class="card-body">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Police principale (titres)</label>
                                <select name="font_primary_id" class="form-input">
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($fonts as $font): ?>
                                        <option value="<?= $font['id'] ?>" <?= ($config['font_primary_id'] ?? 0) == $font['id'] ? 'selected' : '' ?>>
                                            <?= h($font['name']) ?> (<?= h($font['category']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Police secondaire (texte)</label>
                                <select name="font_secondary_id" class="form-input">
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($fonts as $font): ?>
                                        <option value="<?= $font['id'] ?>" <?= ($config['font_secondary_id'] ?? 0) == $font['id'] ? 'selected' : '' ?>>
                                            <?= h($font['name']) ?> (<?= h($font['category']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Échelle typographique -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Échelle typographique</h3>
                        <span class="header-note">Configuration des styles de texte (H1-H6, body, small, lead)</span>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach ($typographyDefaults as $level => $defaults):
                            $current = $typographyScale[$level] ?? $defaults;
                        ?>
                        <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb;">
                            <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--gray-700); margin-bottom: 15px; text-transform: uppercase;"><?= strtoupper($level) ?></h4>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">Police</label>
                                    <select name="typo_<?= $level ?>_font" class="form-input">
                                        <option value="primary" <?= ($current['font'] ?? '') === 'primary' ? 'selected' : '' ?>>Principale</option>
                                        <option value="secondary" <?= ($current['font'] ?? '') === 'secondary' ? 'selected' : '' ?>>Secondaire</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Taille</label>
                                    <input type="text" name="typo_<?= $level ?>_size" class="form-input"
                                           value="<?= h($current['size'] ?? '') ?>" placeholder="ex: 1rem, 16px">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Graisse (weight)</label>
                                    <select name="typo_<?= $level ?>_weight" class="form-input">
                                        <option value="300" <?= ($current['weight'] ?? '') == '300' ? 'selected' : '' ?>>300 (Light)</option>
                                        <option value="400" <?= ($current['weight'] ?? '') == '400' ? 'selected' : '' ?>>400 (Normal)</option>
                                        <option value="500" <?= ($current['weight'] ?? '') == '500' ? 'selected' : '' ?>>500 (Medium)</option>
                                        <option value="600" <?= ($current['weight'] ?? '') == '600' ? 'selected' : '' ?>>600 (Semi-Bold)</option>
                                        <option value="700" <?= ($current['weight'] ?? '') == '700' ? 'selected' : '' ?>>700 (Bold)</option>
                                        <option value="800" <?= ($current['weight'] ?? '') == '800' ? 'selected' : '' ?>>800 (Extra-Bold)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Hauteur de ligne</label>
                                    <input type="text" name="typo_<?= $level ?>_line_height" class="form-input"
                                           value="<?= h($current['line_height'] ?? '') ?>" placeholder="ex: 1.5">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section: Styles des boutons -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Styles des boutons</h3>
                        <span class="header-note">Configuration des variantes de boutons</span>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach ($buttonDefaults as $type => $defaults):
                            $current = $buttonStyles[$type] ?? $defaults;
                        ?>
                        <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb;">
                            <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--gray-700); margin-bottom: 15px; text-transform: capitalize;"><?= ucfirst($type) ?></h4>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">Couleur fond</label>
                                    <div class="color-input-row">
                                        <input type="color" name="btn_<?= $type ?>_bg" id="btn_<?= $type ?>_bg"
                                               value="<?= h($current['bg_color'] ?? '#6366F1') ?>">
                                        <input type="text" class="color-hex"
                                               value="<?= h($current['bg_color'] ?? '#6366F1') ?>"
                                               data-target="btn_<?= $type ?>_bg">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Couleur texte</label>
                                    <div class="color-input-row">
                                        <input type="color" name="btn_<?= $type ?>_text" id="btn_<?= $type ?>_text"
                                               value="<?= h($current['text_color'] ?? '#FFFFFF') ?>">
                                        <input type="text" class="color-hex"
                                               value="<?= h($current['text_color'] ?? '#FFFFFF') ?>"
                                               data-target="btn_<?= $type ?>_text">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Fond au survol</label>
                                    <div class="color-input-row">
                                        <input type="color" name="btn_<?= $type ?>_hover_bg" id="btn_<?= $type ?>_hover_bg"
                                               value="<?= h($current['hover_bg'] ?? '#4F46E5') ?>">
                                        <input type="text" class="color-hex"
                                               value="<?= h($current['hover_bg'] ?? '#4F46E5') ?>"
                                               data-target="btn_<?= $type ?>_hover_bg">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Texte au survol</label>
                                    <div class="color-input-row">
                                        <input type="color" name="btn_<?= $type ?>_hover_text" id="btn_<?= $type ?>_hover_text"
                                               value="<?= h($current['hover_text'] ?? '#FFFFFF') ?>">
                                        <input type="text" class="color-hex"
                                               value="<?= h($current['hover_text'] ?? '#FFFFFF') ?>"
                                               data-target="btn_<?= $type ?>_hover_text">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Couleur bordure</label>
                                    <input type="text" name="btn_<?= $type ?>_border" class="form-input"
                                           value="<?= h($current['border_color'] ?? 'transparent') ?>" placeholder="transparent, #color">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Épaisseur bordure</label>
                                    <input type="text" name="btn_<?= $type ?>_border_width" class="form-input"
                                           value="<?= h($current['border_width'] ?? '0px') ?>" placeholder="0px, 1px, 2px">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section: Système de couleurs -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Système de couleurs avancé</h3>
                        <span class="header-note">Couleurs avec variantes (base, hover, active, disabled)</span>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <!-- Primary -->
                        <div style="margin-bottom: 25px;">
                            <h4 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 12px;">Primaire</h4>
                            <div class="form-grid-2" style="grid-template-columns: repeat(3, 1fr);">
                                <?php foreach (['base' => 'Base', 'hover' => 'Survol', 'active' => 'Actif', 'disabled' => 'Désactivé', 'text_on' => 'Texte sur'] as $variant => $label):
                                    $current = $colorSystem['primary'][$variant] ?? $colorDefaults['primary'][$variant];
                                ?>
                                <div class="form-group">
                                    <label class="form-label"><?= $label ?></label>
                                    <div class="color-input-row">
                                        <input type="color" name="color_primary_<?= $variant ?>" value="<?= h($current) ?>">
                                        <input type="text" class="color-hex" value="<?= h($current) ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Secondary -->
                        <div style="margin-bottom: 25px;">
                            <h4 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 12px;">Secondaire</h4>
                            <div class="form-grid-2" style="grid-template-columns: repeat(3, 1fr);">
                                <?php foreach (['base' => 'Base', 'hover' => 'Survol', 'active' => 'Actif', 'disabled' => 'Désactivé', 'text_on' => 'Texte sur'] as $variant => $label):
                                    $current = $colorSystem['secondary'][$variant] ?? $colorDefaults['secondary'][$variant];
                                ?>
                                <div class="form-group">
                                    <label class="form-label"><?= $label ?></label>
                                    <div class="color-input-row">
                                        <input type="color" name="color_secondary_<?= $variant ?>" value="<?= h($current) ?>">
                                        <input type="text" class="color-hex" value="<?= h($current) ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Accent -->
                        <div style="margin-bottom: 25px;">
                            <h4 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 12px;">Accent</h4>
                            <div class="form-grid-2" style="grid-template-columns: repeat(3, 1fr);">
                                <?php foreach (['base' => 'Base', 'hover' => 'Survol', 'active' => 'Actif', 'disabled' => 'Désactivé', 'text_on' => 'Texte sur'] as $variant => $label):
                                    $current = $colorSystem['accent'][$variant] ?? $colorDefaults['accent'][$variant];
                                ?>
                                <div class="form-group">
                                    <label class="form-label"><?= $label ?></label>
                                    <div class="color-input-row">
                                        <input type="color" name="color_accent_<?= $variant ?>" value="<?= h($current) ?>">
                                        <input type="text" class="color-hex" value="<?= h($current) ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Text Colors -->
                        <div style="margin-bottom: 25px;">
                            <h4 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 12px;">Couleurs de texte</h4>
                            <div class="form-grid-2" style="grid-template-columns: repeat(3, 1fr);">
                                <?php foreach (['primary' => 'Principal', 'secondary' => 'Secondaire', 'tertiary' => 'Tertiaire', 'disabled' => 'Désactivé', 'on_dark' => 'Sur fond sombre'] as $variant => $label):
                                    $current = $colorSystem['text'][$variant] ?? $colorDefaults['text'][$variant];
                                ?>
                                <div class="form-group">
                                    <label class="form-label"><?= $label ?></label>
                                    <div class="color-input-row">
                                        <input type="color" name="color_text_<?= $variant ?>" value="<?= h($current) ?>">
                                        <input type="text" class="color-hex" value="<?= h($current) ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Background Colors -->
                        <div style="margin-bottom: 25px;">
                            <h4 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 12px;">Couleurs de fond</h4>
                            <div class="form-grid-2" style="grid-template-columns: repeat(2, 1fr);">
                                <?php foreach (['primary' => 'Principal', 'secondary' => 'Secondaire', 'tertiary' => 'Tertiaire', 'inverse' => 'Inverse'] as $variant => $label):
                                    $current = $colorSystem['background'][$variant] ?? $colorDefaults['background'][$variant];
                                ?>
                                <div class="form-group">
                                    <label class="form-label"><?= $label ?></label>
                                    <div class="color-input-row">
                                        <input type="color" name="color_bg_<?= $variant ?>" value="<?= h($current) ?>">
                                        <input type="text" class="color-hex" value="<?= h($current) ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Border Colors -->
                        <div style="margin-bottom: 25px;">
                            <h4 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 12px;">Couleurs de bordure</h4>
                            <div class="form-grid-2" style="grid-template-columns: repeat(3, 1fr);">
                                <?php foreach (['primary' => 'Principal', 'secondary' => 'Secondaire', 'focus' => 'Focus'] as $variant => $label):
                                    $current = $colorSystem['border'][$variant] ?? $colorDefaults['border'][$variant];
                                ?>
                                <div class="form-group">
                                    <label class="form-label"><?= $label ?></label>
                                    <div class="color-input-row">
                                        <input type="color" name="color_border_<?= $variant ?>" value="<?= h($current) ?>">
                                        <input type="text" class="color-hex" value="<?= h($current) ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Status Colors -->
                        <div style="margin-bottom: 25px;">
                            <h4 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 12px;">Couleurs de statut</h4>
                            <div class="form-grid-2" style="grid-template-columns: repeat(2, 1fr);">
                                <?php foreach (['success' => 'Succès', 'success_bg' => 'Fond succès', 'warning' => 'Avertissement', 'warning_bg' => 'Fond avertissement', 'error' => 'Erreur', 'error_bg' => 'Fond erreur', 'info' => 'Info', 'info_bg' => 'Fond info'] as $variant => $label):
                                    $current = $colorSystem['status'][$variant] ?? $colorDefaults['status'][$variant];
                                ?>
                                <div class="form-group">
                                    <label class="form-label"><?= $label ?></label>
                                    <div class="color-input-row">
                                        <input type="color" name="color_status_<?= $variant ?>" value="<?= h($current) ?>">
                                        <input type="text" class="color-hex" value="<?= h($current) ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Typography -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">Sauvegarder la typographie</button>
                </div>
            </form>
            <?php endif; ?>

        </main>
    </div>

    <!-- Input file caché pour upload -->
    <input type="file" id="imageUploader" accept="image/*" style="display: none;">

    <style>
        .alert { padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); font-weight: 500; }
        .alert-success { background: rgba(61, 255, 192, 0.15); color: var(--mint-dark); border-left: 4px solid var(--mint-main); }
        .alert-error { background: rgba(255, 105, 180, 0.15); color: var(--pink-dark); border-left: 4px solid var(--pink-main); }
        .alert-warning { background: rgba(245, 158, 11, 0.15); color: #92400e; border-left: 4px solid #F59E0B; }
        .alert-warning code { background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }

        .branding-form { display: flex; flex-direction: column; gap: var(--spacing-lg); }
        .card-body { padding: var(--spacing-lg); }
        .header-note { font-size: 0.85rem; color: var(--gray-500); font-weight: 400; }

        /* Sub-tabs */
        .sub-tabs-wrapper { margin-bottom: var(--spacing-lg); }
        .sub-tabs {
            display: flex;
            gap: 4px;
            background: var(--gray-100);
            padding: 4px;
            border-radius: 12px;
            width: fit-content;
        }
        .sub-tab {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-600);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .sub-tab:hover { color: var(--gray-800); background: rgba(255,255,255,0.5); }
        .sub-tab.active {
            background: #fff;
            color: var(--purple-main);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .form-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--spacing-md); }
        .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--spacing-lg); }
        @media (max-width: 768px) {
            .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
        }

        /* Modern Logo Grid */
        .logo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }
        .logo-upload-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;
        }
        .logo-upload-card:hover {
            border-color: var(--purple-main);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.1);
            transform: translateY(-2px);
        }
        .logo-upload-card.dark {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-color: #334155;
        }
        .logo-upload-card.dark:hover {
            border-color: var(--purple-main);
        }
        .logo-upload-card.favicon {
            max-width: 200px;
        }
        .logo-preview-modern {
            width: 100%;
            height: 120px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 2px dashed var(--gray-300);
            overflow: hidden;
            transition: border-color 0.2s ease;
        }
        .logo-preview-modern:hover { border-color: var(--purple-main); }
        .logo-preview-modern.dark {
            background: #1a1a2e;
            border-color: #475569;
        }
        .logo-preview-modern.favicon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }
        .logo-preview-modern img {
            max-width: 80%;
            max-height: 80%;
            object-fit: contain;
        }
        .logo-placeholder {
            color: var(--gray-400);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .logo-upload-card.dark .logo-placeholder { color: #64748b; }
        .logo-info {
            text-align: center;
        }
        .logo-info h4 {
            margin: 0 0 4px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        .logo-upload-card.dark .logo-info h4 { color: #e2e8f0; }
        .logo-info p {
            margin: 0 0 12px;
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        .logo-upload-card.dark .logo-info p { color: #94a3b8; }
        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: transparent;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-weight: 500;
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-outline:hover {
            border-color: var(--purple-main);
            color: var(--purple-main);
            background: rgba(99, 102, 241, 0.05);
        }
        .logo-upload-card.dark .btn-outline {
            border-color: #475569;
            color: #e2e8f0;
        }
        .logo-upload-card.dark .btn-outline:hover {
            border-color: var(--purple-main);
            color: var(--purple-light);
        }

        /* Color picker row */
        .color-picker-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .color-picker-row input[type="color"] {
            width: 50px;
            height: 40px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            cursor: pointer;
            padding: 2px;
        }
        .color-picker-row .color-text { width: 100px; }

        /* Form switch */
        .form-switch {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }
        .form-switch input { display: none; }
        .switch-slider {
            width: 48px;
            height: 26px;
            background: var(--gray-300);
            border-radius: 26px;
            position: relative;
            transition: background 0.2s;
        }
        .switch-slider::after {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            background: #fff;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .form-switch input:checked + .switch-slider { background: var(--purple-main); }
        .form-switch input:checked + .switch-slider::after { transform: translateX(22px); }
        .switch-label { font-weight: 500; color: var(--gray-700); }

        /* Site preview box */
        .site-preview-box {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .topbar-preview {
            padding: 10px 20px;
            text-align: center;
            overflow: hidden;
        }
        .topbar-preview span {
            display: inline-block;
            white-space: nowrap;
            animation: marquee 15s linear infinite;
        }
        @keyframes marquee {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        .header-preview, .footer-preview {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-preview span, .footer-preview span { font-weight: 600; }
        .header-preview div, .footer-preview div { display: flex; gap: 15px; font-size: 13px; opacity: 0.9; }
        .header-preview-placeholder {
            background: #1a1a2e;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-preview-placeholder span { font-weight: 600; }
        .preview-nav-links { display: flex; gap: 15px; font-size: 13px; opacity: 0.9; }
        .content-preview-placeholder {
            height: 60px;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 12px;
        }

        /* Color inputs */
        .color-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--spacing-lg); }
        @media (max-width: 900px) { .color-grid { grid-template-columns: 1fr; } }
        .color-group h4 { margin: 0 0 12px; font-size: 0.9rem; color: var(--gray-600); }
        .color-inputs { display: flex; flex-wrap: wrap; gap: 16px; }
        .color-input-wrapper { display: flex; flex-direction: column; gap: 4px; }
        .color-input-wrapper label { font-size: 0.8rem; color: var(--gray-500); }
        .color-input-row { display: flex; align-items: center; gap: 8px; }
        .color-input-row input[type="color"] {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            padding: 0;
        }
        .color-hex {
            width: 80px;
            padding: 8px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-family: monospace;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        /* Preview */
        .branding-preview {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            overflow: hidden;
            font-family: var(--preview-font-secondary, 'Inter', sans-serif);
        }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: var(--preview-bg, #fff);
            border-bottom: 1px solid var(--gray-200);
        }
        .preview-logo {
            font-family: var(--preview-font-primary, 'Poppins', sans-serif);
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--preview-primary, #6366F1);
        }
        .preview-nav { display: flex; gap: 24px; color: var(--preview-text, #1F2937); font-size: 0.9rem; }
        .preview-hero {
            padding: 48px 24px;
            text-align: center;
            background: var(--preview-bg, #fff);
        }
        .preview-hero h2 {
            font-family: var(--preview-font-primary, 'Poppins', sans-serif);
            font-size: 2rem;
            color: var(--preview-text, #1F2937);
            margin: 0 0 8px;
        }
        .preview-hero p {
            color: var(--preview-text-light, #6B7280);
            margin: 0 0 24px;
        }
        .preview-btn {
            display: inline-block;
            padding: 12px 32px;
            background: var(--preview-button, #6366F1);
            color: var(--preview-button-text, #fff);
            border: none;
            border-radius: var(--preview-radius, 8px);
            font-weight: 600;
            cursor: pointer;
            box-shadow: var(--preview-shadow, 0 1px 3px rgba(0,0,0,0.1));
        }
        .preview-card {
            margin: 24px;
            padding: 24px;
            background: var(--preview-surface, #F9FAFB);
            border-radius: var(--preview-radius, 8px);
            box-shadow: var(--preview-shadow, 0 1px 3px rgba(0,0,0,0.1));
        }
        .preview-card h4 {
            font-family: var(--preview-font-primary, 'Poppins', sans-serif);
            color: var(--preview-text, #1F2937);
            margin: 0 0 8px;
        }
        .preview-card p { color: var(--preview-text-light, #6B7280); margin: 0; font-size: 0.9rem; }

        .form-actions { padding: var(--spacing-lg) 0; }
        .form-help { font-size: 0.8rem; color: var(--gray-500); margin-top: 4px; }
    </style>

    <script>
        // === Debounce utility (évite les freeze lors du drag des color pickers) ===
        let _rafIds = {};
        function debounceRAF(key, fn) {
            if (_rafIds[key]) cancelAnimationFrame(_rafIds[key]);
            _rafIds[key] = requestAnimationFrame(fn);
        }

        // Upload image (Identity tab)
        let currentUploadTarget = null;
        let currentPreviewTarget = null;
        const uploader = document.getElementById('imageUploader');

        function uploadImage(inputId, previewId) {
            currentUploadTarget = inputId;
            currentPreviewTarget = previewId;
            uploader.click();
        }

        uploader.addEventListener('change', async () => {
            if (!uploader.files.length) return;

            const file = uploader.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', '<?= generateCsrf() ?>');
            formData.append('type', 'branding');

            try {
                const response = await fetch('/admin/upload-branding.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    document.getElementById(currentUploadTarget).value = data.url;
                    const preview = document.getElementById(currentPreviewTarget);
                    preview.innerHTML = `<img src="${data.url}" alt="Preview">`;
                } else {
                    alert('Erreur : ' + (data.error || 'Upload échoué'));
                }
            } catch (e) {
                alert('Erreur réseau');
            }

            uploader.value = '';
        });

        // Colors tab: Sync color picker avec input text (debounced)
        document.querySelectorAll('.color-hex').forEach(input => {
            const targetId = input.dataset.target;
            const colorInput = document.getElementById(targetId);
            if (!colorInput) return;

            input.addEventListener('input', () => {
                let val = input.value.trim();
                if (!val.startsWith('#')) val = '#' + val;
                if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                    colorInput.value = val;
                    debounceRAF('colors', updateColorsPreview);
                }
            });

            colorInput.addEventListener('input', () => {
                input.value = colorInput.value.toUpperCase();
                debounceRAF('colors', updateColorsPreview);
            });
        });

        // Colors tab: Live preview
        const radiusMap = { none: '0', small: '4px', medium: '8px', large: '12px', full: '9999px' };
        const shadowMap = {
            none: 'none',
            subtle: '0 1px 3px rgba(0,0,0,0.1)',
            medium: '0 4px 6px rgba(0,0,0,0.1)',
            strong: '0 10px 25px rgba(0,0,0,0.15)'
        };

        function updateColorsPreview() {
            const preview = document.getElementById('brandingPreview');
            if (!preview) return;

            preview.style.setProperty('--preview-primary', document.getElementById('color_primary')?.value || '#6366F1');
            preview.style.setProperty('--preview-secondary', document.getElementById('color_secondary')?.value || '#8B5CF6');
            preview.style.setProperty('--preview-text', document.getElementById('color_text')?.value || '#1F2937');
            preview.style.setProperty('--preview-text-light', document.getElementById('color_text_light')?.value || '#6B7280');
            preview.style.setProperty('--preview-bg', document.getElementById('color_background')?.value || '#FFFFFF');
            preview.style.setProperty('--preview-surface', document.getElementById('color_surface')?.value || '#F9FAFB');
            preview.style.setProperty('--preview-button', document.getElementById('color_button')?.value || '#6366F1');
            preview.style.setProperty('--preview-button-text', document.getElementById('color_button_text')?.value || '#FFFFFF');

            const radiusSelect = document.querySelector('[name="border_radius"]');
            const shadowSelect = document.querySelector('[name="shadow_intensity"]');
            if (radiusSelect) preview.style.setProperty('--preview-radius', radiusMap[radiusSelect.value] || '8px');
            if (shadowSelect) preview.style.setProperty('--preview-shadow', shadowMap[shadowSelect.value] || shadowMap.subtle);

            const fontPrimary = document.querySelector('[name="font_primary"]')?.value || 'Poppins';
            const fontSecondary = document.querySelector('[name="font_secondary"]')?.value || 'Inter';
            preview.style.setProperty('--preview-font-primary', `'${fontPrimary}', sans-serif`);
            preview.style.setProperty('--preview-font-secondary', `'${fontSecondary}', sans-serif`);
        }

        // Init colors preview if on colors tab
        if (document.getElementById('brandingPreview')) {
            updateColorsPreview();
            document.querySelectorAll('input[type="color"], select, input[name="font_primary"], input[name="font_secondary"]')
                .forEach(el => el.addEventListener('change', () => debounceRAF('colors', updateColorsPreview)));
        }

        // TopBar tab: Live preview (debounced)
        function syncColorInput(colorId, textId) {
            const color = document.getElementById(colorId);
            const text = document.getElementById(textId);
            if (!color || !text) return;

            color.addEventListener('input', () => { text.value = color.value; debounceRAF('topbar', updateTopbarPreview); });
            text.addEventListener('input', () => { if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) { color.value = text.value; debounceRAF('topbar', updateTopbarPreview); } });
        }

        function updateTopbarPreview() {
            const preview = document.getElementById('topbarPreview');
            const text = document.getElementById('topbarPreviewText');
            if (!preview || !text) return;

            preview.style.background = document.getElementById('topbarBgColor')?.value || '#1a1a2e';
            text.style.color = document.getElementById('topbarTextColor')?.value || '#ffffff';
            text.style.fontFamily = document.getElementById('topbarFontFamily')?.value || 'inherit';
            text.style.fontSize = (document.getElementById('topbarFontSize')?.value || '14') + 'px';
            text.textContent = document.querySelector('input[name="topbar_text"]')?.value || '';
        }

        // Init TopBar preview
        syncColorInput('topbarBgColor', 'topbarBgColorText');
        syncColorInput('topbarTextColor', 'topbarTextColorText');
        if (document.getElementById('topbarFontFamily')) {
            document.getElementById('topbarFontFamily').addEventListener('change', () => debounceRAF('topbar', updateTopbarPreview));
        }
        if (document.getElementById('topbarFontSize')) {
            document.getElementById('topbarFontSize').addEventListener('input', () => debounceRAF('topbar', updateTopbarPreview));
        }
        const topbarTextInput = document.querySelector('input[name="topbar_text"]');
        if (topbarTextInput) topbarTextInput.addEventListener('input', () => debounceRAF('topbar', updateTopbarPreview));

        // Appearance tab: Live preview (debounced)
        function updateAppearancePreview() {
            const headerPreview = document.getElementById('headerPreview');
            const footerPreview = document.getElementById('footerPreview');
            if (!headerPreview || !footerPreview) return;

            const headerBg = document.getElementById('headerBgColor')?.value || '#1a1a2e';
            const headerText = document.getElementById('headerTextColor')?.value || '#ffffff';
            const footerBg = document.getElementById('footerBgColor')?.value || '#1a1a2e';
            const footerText = document.getElementById('footerTextColor')?.value || '#ffffff';

            headerPreview.style.background = headerBg;
            document.getElementById('headerPreviewBrand').style.color = headerText;
            document.getElementById('headerPreviewNav').style.color = headerText;

            footerPreview.style.background = footerBg;
            document.getElementById('footerPreviewBrand').style.color = footerText;
            document.getElementById('footerPreviewCopy').style.color = footerText;
        }

        function syncAppearanceColor(colorId, textId) {
            const color = document.getElementById(colorId);
            const text = document.getElementById(textId);
            if (!color || !text) return;

            color.addEventListener('input', () => { text.value = color.value; debounceRAF('appearance', updateAppearancePreview); });
            text.addEventListener('input', () => { if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) { color.value = text.value; debounceRAF('appearance', updateAppearancePreview); } });
        }

        // Init Appearance preview
        syncAppearanceColor('headerBgColor', 'headerBgColorText');
        syncAppearanceColor('headerTextColor', 'headerTextColorText');
        syncAppearanceColor('footerBgColor', 'footerBgColorText');
        syncAppearanceColor('footerTextColor', 'footerTextColorText');
    </script>
</body>
</html>
