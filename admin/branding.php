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

Auth::requireAdmin();

$orderModel = new Order();
$pendingOrders = $orderModel->countNew();
$brandingModel = new Branding();

$success = '';
$error = '';

// Client ID (null = global config)
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;

// Charger la config actuelle
$config = $brandingModel->resolveBranding($clientId);

// Traitement du formulaire
if (isPost() && isset($_POST['save_branding'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
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
            $success = 'Configuration sauvegardée avec succès.';
            // Recharger la config
            $config = $brandingModel->resolveBranding($clientId);
        } catch (Exception $e) {
            $error = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
        }
    } else {
        $error = 'Token CSRF invalide.';
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

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" class="branding-form">
                <?= csrfField() ?>
                <input type="hidden" name="save_branding" value="1">

                <!-- Section: Identité -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Identité</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-grid-3">
                            <!-- Logo principal -->
                            <div class="form-group">
                                <label class="form-label">Logo principal</label>
                                <div class="image-upload-wrapper">
                                    <input type="hidden" name="logo_url" id="logo_url" value="<?= h($config['logo_url'] ?? '') ?>">
                                    <div class="image-preview" id="logo_preview">
                                        <?php if (!empty($config['logo_url'])): ?>
                                            <img src="<?= h($config['logo_url']) ?>" alt="Logo">
                                        <?php else: ?>
                                            <span class="placeholder">Aucun logo</span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="uploadImage('logo_url', 'logo_preview')">
                                        Choisir
                                    </button>
                                </div>
                                <small class="form-help">Utilisé sur fond clair</small>
                            </div>

                            <!-- Logo clair -->
                            <div class="form-group">
                                <label class="form-label">Logo (fond sombre)</label>
                                <div class="image-upload-wrapper">
                                    <input type="hidden" name="logo_light_url" id="logo_light_url" value="<?= h($config['logo_light_url'] ?? '') ?>">
                                    <div class="image-preview dark-bg" id="logo_light_preview">
                                        <?php if (!empty($config['logo_light_url'])): ?>
                                            <img src="<?= h($config['logo_light_url']) ?>" alt="Logo clair">
                                        <?php else: ?>
                                            <span class="placeholder">Aucun logo</span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="uploadImage('logo_light_url', 'logo_light_preview')">
                                        Choisir
                                    </button>
                                </div>
                                <small class="form-help">Variante pour fond sombre</small>
                            </div>

                            <!-- Favicon -->
                            <div class="form-group">
                                <label class="form-label">Favicon</label>
                                <div class="image-upload-wrapper">
                                    <input type="hidden" name="favicon_url" id="favicon_url" value="<?= h($config['favicon_url'] ?? '') ?>">
                                    <div class="image-preview favicon-preview" id="favicon_preview">
                                        <?php if (!empty($config['favicon_url'])): ?>
                                            <img src="<?= h($config['favicon_url']) ?>" alt="Favicon">
                                        <?php else: ?>
                                            <span class="placeholder">Aucun</span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="uploadImage('favicon_url', 'favicon_preview')">
                                        Choisir
                                    </button>
                                </div>
                                <small class="form-help">32x32 ou 64x64 px</small>
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

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Sauvegarder les modifications
                    </button>
                </div>
            </form>
        </main>
    </div>

    <!-- Input file caché pour upload -->
    <input type="file" id="imageUploader" accept="image/*" style="display: none;">

    <style>
        .alert { padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); font-weight: 500; }
        .alert-success { background: rgba(61, 255, 192, 0.15); color: var(--mint-dark); border-left: 4px solid var(--mint-main); }
        .alert-error { background: rgba(255, 105, 180, 0.15); color: var(--pink-dark); border-left: 4px solid var(--pink-main); }

        .branding-form { display: flex; flex-direction: column; gap: var(--spacing-lg); }
        .card-body { padding: var(--spacing-lg); }
        .header-note { font-size: 0.85rem; color: var(--gray-500); font-weight: 400; }

        .form-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--spacing-md); }
        .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--spacing-lg); }
        @media (max-width: 768px) {
            .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
        }

        /* Image upload */
        .image-upload-wrapper { display: flex; flex-direction: column; gap: 8px; }
        .image-preview {
            width: 100%;
            height: 100px;
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-50);
            overflow: hidden;
        }
        .image-preview.dark-bg { background: var(--gray-800); border-color: var(--gray-600); }
        .image-preview.favicon-preview { width: 64px; height: 64px; }
        .image-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .image-preview .placeholder { color: var(--gray-400); font-size: 0.85rem; }
        .image-preview.dark-bg .placeholder { color: var(--gray-500); }

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
        // Sync color picker avec input text
        document.querySelectorAll('.color-hex').forEach(input => {
            const targetId = input.dataset.target;
            const colorInput = document.getElementById(targetId);

            // Sync hex -> color
            input.addEventListener('input', () => {
                let val = input.value.trim();
                if (!val.startsWith('#')) val = '#' + val;
                if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                    colorInput.value = val;
                    updatePreview();
                }
            });

            // Sync color -> hex
            colorInput.addEventListener('input', () => {
                input.value = colorInput.value.toUpperCase();
                updatePreview();
            });
        });

        // Upload image
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

        // Live preview
        const radiusMap = { none: '0', small: '4px', medium: '8px', large: '12px', full: '9999px' };
        const shadowMap = {
            none: 'none',
            subtle: '0 1px 3px rgba(0,0,0,0.1)',
            medium: '0 4px 6px rgba(0,0,0,0.1)',
            strong: '0 10px 25px rgba(0,0,0,0.15)'
        };

        function updatePreview() {
            const preview = document.getElementById('brandingPreview');

            preview.style.setProperty('--preview-primary', document.getElementById('color_primary').value);
            preview.style.setProperty('--preview-secondary', document.getElementById('color_secondary').value);
            preview.style.setProperty('--preview-text', document.getElementById('color_text').value);
            preview.style.setProperty('--preview-text-light', document.getElementById('color_text_light').value);
            preview.style.setProperty('--preview-bg', document.getElementById('color_background').value);
            preview.style.setProperty('--preview-surface', document.getElementById('color_surface').value);
            preview.style.setProperty('--preview-button', document.getElementById('color_button').value);
            preview.style.setProperty('--preview-button-text', document.getElementById('color_button_text').value);

            const radius = document.querySelector('[name="border_radius"]').value;
            preview.style.setProperty('--preview-radius', radiusMap[radius] || '8px');

            const shadow = document.querySelector('[name="shadow_intensity"]').value;
            preview.style.setProperty('--preview-shadow', shadowMap[shadow] || shadowMap.subtle);

            // Fonts
            const fontPrimary = document.querySelector('[name="font_primary"]').value || 'Poppins';
            const fontSecondary = document.querySelector('[name="font_secondary"]').value || 'Inter';
            preview.style.setProperty('--preview-font-primary', `'${fontPrimary}', sans-serif`);
            preview.style.setProperty('--preview-font-secondary', `'${fontSecondary}', sans-serif`);
        }

        // Initial preview + listeners
        updatePreview();
        document.querySelectorAll('input[type="color"], select, input[name="font_primary"], input[name="font_secondary"]')
            .forEach(el => el.addEventListener('change', updatePreview));
    </script>
</body>
</html>
