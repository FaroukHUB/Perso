<?php
/**
 * PERSONNALY - Admin : Styles Sections Homepage
 * Configuration visuelle des sections de la page d'accueil
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

// Sections disponibles avec labels
$availableSections = [
    'hero' => 'Hero (Bannière principale)',
    'featured_products' => 'Produits vedettes',
    'featured_packs' => 'Packs / Idées cadeaux',
    'categories' => 'Catégories',
    'testimonials' => 'Témoignages',
    'blog_slider' => 'Articles blog',
    'newsletter' => 'Newsletter',
    'cta' => 'Call to Action',
    'features' => 'Caractéristiques',
    'partners' => 'Partenaires',
];

// Charger les styles existants
$existingStyles = $brandingModel->getSectionStyles($clientId);
$stylesMap = [];
foreach ($existingStyles as $style) {
    $stylesMap[$style['section_key']] = $style;
}

// Padding options
$paddingOptions = [
    'none' => 'Aucun',
    'small' => 'Petit (2rem)',
    'medium' => 'Moyen (4rem)',
    'large' => 'Grand (6rem)',
    'xlarge' => 'Très grand (8rem)',
];

// Traitement du formulaire
if (isPost() && isset($_POST['save_styles'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        try {
            $sections = $_POST['sections'] ?? [];

            foreach ($sections as $sectionKey => $data) {
                if (!isset($availableSections[$sectionKey])) continue;

                $styleData = [
                    'background_color' => !empty($data['background_color']) ? $data['background_color'] : null,
                    'background_image' => !empty($data['background_image']) ? $data['background_image'] : null,
                    'background_overlay' => !empty($data['background_overlay']) ? $data['background_overlay'] : null,
                    'background_overlay_opacity' => !empty($data['background_overlay_opacity']) ? (float)$data['background_overlay_opacity'] : null,
                    'text_color_override' => !empty($data['text_color_override']) ? $data['text_color_override'] : null,
                    'padding_y' => $data['padding_y'] ?? 'medium',
                    'sort_order' => (int)($data['sort_order'] ?? 0),
                    'active' => isset($data['active']) ? 1 : 0,
                ];

                $brandingModel->upsertSectionStyle($sectionKey, $styleData, $clientId);
            }

            $success = 'Styles sauvegardés avec succès.';

            // Recharger les styles
            $existingStyles = $brandingModel->getSectionStyles($clientId);
            $stylesMap = [];
            foreach ($existingStyles as $style) {
                $stylesMap[$style['section_key']] = $style;
            }
        } catch (Exception $e) {
            $error = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
        }
    } else {
        $error = 'Token CSRF invalide.';
    }
}

// Fonction helper pour obtenir une valeur de style
function getStyleValue($stylesMap, $sectionKey, $field, $default = '') {
    return $stylesMap[$sectionKey][$field] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Styles Sections - PERSONNALY Admin</title>
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
                    <span>Styles Sections Homepage</span>
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

            <form method="post" class="sections-form">
                <?= csrfField() ?>
                <input type="hidden" name="save_styles" value="1">

                <div class="sections-list">
                    <?php $order = 0; foreach ($availableSections as $key => $label): ?>
                        <?php
                        $style = $stylesMap[$key] ?? [];
                        $isActive = isset($style['active']) ? (bool)$style['active'] : true;
                        $bgColor = $style['background_color'] ?? '#FFFFFF';
                        $bgImage = $style['background_image'] ?? '';
                        $bgOverlay = $style['background_overlay'] ?? '';
                        $bgOverlayOpacity = $style['background_overlay_opacity'] ?? 0.5;
                        $textColor = $style['text_color_override'] ?? '';
                        $paddingY = $style['padding_y'] ?? 'medium';
                        $sortOrder = $style['sort_order'] ?? $order;
                        ?>
                        <div class="section-card <?= $isActive ? '' : 'inactive' ?>" data-section="<?= $key ?>">
                            <div class="section-header">
                                <div class="section-drag-handle">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="8" y1="6" x2="16" y2="6"/>
                                        <line x1="8" y1="12" x2="16" y2="12"/>
                                        <line x1="8" y1="18" x2="16" y2="18"/>
                                    </svg>
                                </div>
                                <div class="section-info">
                                    <h3><?= h($label) ?></h3>
                                    <code><?= $key ?></code>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="sections[<?= $key ?>][active]" value="1" <?= $isActive ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="section-body">
                                <input type="hidden" name="sections[<?= $key ?>][sort_order]" value="<?= $sortOrder ?>" class="sort-order-input">

                                <div class="style-grid">
                                    <!-- Background Color -->
                                    <div class="style-field">
                                        <label>Couleur de fond</label>
                                        <div class="color-input-row">
                                            <input type="color" name="sections[<?= $key ?>][background_color]"
                                                   value="<?= h($bgColor) ?>"
                                                   class="color-picker">
                                            <input type="text" class="color-hex-small"
                                                   value="<?= h($bgColor) ?>">
                                        </div>
                                    </div>

                                    <!-- Background Image -->
                                    <div class="style-field">
                                        <label>Image de fond</label>
                                        <div class="image-input-row">
                                            <input type="text" name="sections[<?= $key ?>][background_image]"
                                                   value="<?= h($bgImage) ?>"
                                                   placeholder="URL de l'image"
                                                   class="form-input form-input-sm">
                                            <button type="button" class="btn btn-secondary btn-xs"
                                                    onclick="uploadSectionImage(this, 'sections[<?= $key ?>][background_image]')">
                                                ...
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Overlay -->
                                    <div class="style-field">
                                        <label>Overlay</label>
                                        <div class="overlay-inputs">
                                            <input type="color" name="sections[<?= $key ?>][background_overlay]"
                                                   value="<?= h($bgOverlay ?: '#000000') ?>"
                                                   class="color-picker">
                                            <input type="range" name="sections[<?= $key ?>][background_overlay_opacity]"
                                                   min="0" max="1" step="0.1"
                                                   value="<?= h($bgOverlayOpacity) ?>"
                                                   class="opacity-slider">
                                            <span class="opacity-value"><?= round($bgOverlayOpacity * 100) ?>%</span>
                                        </div>
                                    </div>

                                    <!-- Text Color Override -->
                                    <div class="style-field">
                                        <label>Couleur texte (override)</label>
                                        <div class="color-input-row">
                                            <input type="color" name="sections[<?= $key ?>][text_color_override]"
                                                   value="<?= h($textColor ?: '#1F2937') ?>"
                                                   class="color-picker">
                                            <input type="text" class="color-hex-small"
                                                   value="<?= h($textColor) ?>"
                                                   placeholder="Auto">
                                            <button type="button" class="btn btn-xs btn-ghost clear-color"
                                                    title="Réinitialiser">×</button>
                                        </div>
                                    </div>

                                    <!-- Padding -->
                                    <div class="style-field">
                                        <label>Padding vertical</label>
                                        <select name="sections[<?= $key ?>][padding_y]" class="form-input form-input-sm">
                                            <?php foreach ($paddingOptions as $val => $lbl): ?>
                                                <option value="<?= $val ?>" <?= $paddingY === $val ? 'selected' : '' ?>>
                                                    <?= h($lbl) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Preview mini -->
                                <div class="section-preview"
                                     style="background-color: <?= h($bgColor) ?>;
                                            <?= $bgImage ? 'background-image: url(' . h($bgImage) . ');' : '' ?>">
                                    <?php if ($bgOverlay && $bgOverlayOpacity > 0): ?>
                                        <div class="preview-overlay"
                                             style="background-color: <?= h($bgOverlay) ?>; opacity: <?= h($bgOverlayOpacity) ?>;"></div>
                                    <?php endif; ?>
                                    <span class="preview-text" style="<?= $textColor ? 'color:' . h($textColor) : '' ?>">
                                        Aperçu section
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php $order++; endforeach; ?>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Sauvegarder les styles
                    </button>
                </div>
            </form>
        </main>
    </div>

    <!-- Input file caché pour upload -->
    <input type="file" id="sectionImageUploader" accept="image/*" style="display: none;">

    <style>
        .alert { padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); font-weight: 500; }
        .alert-success { background: rgba(61, 255, 192, 0.15); color: var(--mint-dark); border-left: 4px solid var(--mint-main); }
        .alert-error { background: rgba(255, 105, 180, 0.15); color: var(--pink-dark); border-left: 4px solid var(--pink-main); }

        .sections-form { display: flex; flex-direction: column; gap: var(--spacing-lg); }
        .sections-list { display: flex; flex-direction: column; gap: var(--spacing-md); }

        .section-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: opacity 0.2s, box-shadow 0.2s;
        }
        .section-card.inactive { opacity: 0.6; }
        .section-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

        .section-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }

        .section-drag-handle {
            cursor: grab;
            color: var(--gray-400);
            padding: 4px;
        }
        .section-drag-handle:hover { color: var(--gray-600); }

        .section-info { flex: 1; }
        .section-info h3 { margin: 0; font-size: 1rem; color: var(--gray-800); }
        .section-info code {
            font-size: 0.75rem;
            color: var(--gray-500);
            background: var(--gray-200);
            padding: 2px 6px;
            border-radius: 4px;
        }

        /* Toggle switch */
        .toggle-switch { position: relative; display: inline-block; width: 48px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: var(--gray-300);
            border-radius: 26px;
            transition: 0.3s;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background: var(--mint-main); }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(22px); }

        .section-body { padding: 20px; }

        .style-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .style-field { display: flex; flex-direction: column; gap: 6px; }
        .style-field label { font-size: 0.8rem; color: var(--gray-600); font-weight: 500; }

        .color-input-row { display: flex; align-items: center; gap: 6px; }
        .color-picker { width: 36px; height: 36px; border: none; border-radius: 6px; cursor: pointer; padding: 0; }
        .color-hex-small {
            flex: 1;
            padding: 8px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .image-input-row { display: flex; gap: 6px; }
        .image-input-row .form-input { flex: 1; }

        .overlay-inputs { display: flex; align-items: center; gap: 8px; }
        .opacity-slider { flex: 1; }
        .opacity-value { font-size: 0.8rem; color: var(--gray-500); min-width: 40px; }

        .btn-xs { padding: 4px 8px; font-size: 0.75rem; }
        .btn-ghost { background: transparent; border: 1px solid var(--gray-300); }
        .clear-color { font-size: 1.2rem; line-height: 1; }

        .form-input-sm { padding: 8px 12px; font-size: 0.85rem; }

        /* Section preview */
        .section-preview {
            position: relative;
            height: 60px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-size: cover;
            background-position: center;
            overflow: hidden;
        }
        .preview-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .preview-text {
            position: relative;
            z-index: 1;
            font-weight: 600;
            font-size: 0.9rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .form-actions { padding: var(--spacing-lg) 0; }
    </style>

    <script>
        // Sync color picker avec input text
        document.querySelectorAll('.color-input-row').forEach(row => {
            const colorPicker = row.querySelector('.color-picker');
            const hexInput = row.querySelector('.color-hex-small');

            if (colorPicker && hexInput) {
                colorPicker.addEventListener('input', () => {
                    hexInput.value = colorPicker.value.toUpperCase();
                    updateSectionPreview(row.closest('.section-card'));
                });

                hexInput.addEventListener('input', () => {
                    let val = hexInput.value.trim();
                    if (!val.startsWith('#')) val = '#' + val;
                    if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                        colorPicker.value = val;
                        updateSectionPreview(row.closest('.section-card'));
                    }
                });
            }

            // Clear button
            const clearBtn = row.querySelector('.clear-color');
            if (clearBtn && hexInput) {
                clearBtn.addEventListener('click', () => {
                    hexInput.value = '';
                    updateSectionPreview(row.closest('.section-card'));
                });
            }
        });

        // Opacity slider
        document.querySelectorAll('.opacity-slider').forEach(slider => {
            const valueSpan = slider.nextElementSibling;
            slider.addEventListener('input', () => {
                valueSpan.textContent = Math.round(slider.value * 100) + '%';
                updateSectionPreview(slider.closest('.section-card'));
            });
        });

        // Toggle active
        document.querySelectorAll('.toggle-switch input').forEach(toggle => {
            toggle.addEventListener('change', () => {
                const card = toggle.closest('.section-card');
                card.classList.toggle('inactive', !toggle.checked);
            });
        });

        // Update section preview
        function updateSectionPreview(card) {
            const preview = card.querySelector('.section-preview');
            const bgColorInput = card.querySelector('[name*="background_color"]');
            const bgImageInput = card.querySelector('[name*="background_image"]');
            const overlayColorInput = card.querySelector('[name*="background_overlay"]');
            const overlayOpacityInput = card.querySelector('[name*="background_overlay_opacity"]');
            const textColorInput = card.querySelector('[name*="text_color_override"]');

            if (bgColorInput) {
                preview.style.backgroundColor = bgColorInput.value;
            }

            if (bgImageInput && bgImageInput.value) {
                preview.style.backgroundImage = `url(${bgImageInput.value})`;
            } else {
                preview.style.backgroundImage = 'none';
            }

            let overlay = preview.querySelector('.preview-overlay');
            if (overlayColorInput && overlayOpacityInput && overlayOpacityInput.value > 0) {
                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.className = 'preview-overlay';
                    preview.insertBefore(overlay, preview.firstChild);
                }
                overlay.style.backgroundColor = overlayColorInput.value;
                overlay.style.opacity = overlayOpacityInput.value;
            } else if (overlay) {
                overlay.remove();
            }

            const textPreview = preview.querySelector('.preview-text');
            const textColorHex = card.querySelector('.color-hex-small[placeholder="Auto"]') ||
                                 card.querySelectorAll('.color-hex-small')[1];
            if (textPreview && textColorHex) {
                textPreview.style.color = textColorHex.value || '';
            }
        }

        // Upload section image
        let currentImageInput = null;
        const sectionUploader = document.getElementById('sectionImageUploader');

        function uploadSectionImage(btn, inputName) {
            currentImageInput = btn.previousElementSibling;
            sectionUploader.click();
        }

        sectionUploader.addEventListener('change', async () => {
            if (!sectionUploader.files.length || !currentImageInput) return;

            const file = sectionUploader.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', '<?= generateCsrf() ?>');

            try {
                const response = await fetch('/admin/upload-branding.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    currentImageInput.value = data.url;
                    updateSectionPreview(currentImageInput.closest('.section-card'));
                } else {
                    alert('Erreur : ' + (data.error || 'Upload échoué'));
                }
            } catch (e) {
                alert('Erreur réseau');
            }

            sectionUploader.value = '';
        });

        // Background image input change
        document.querySelectorAll('[name*="background_image"]').forEach(input => {
            input.addEventListener('change', () => {
                updateSectionPreview(input.closest('.section-card'));
            });
        });
    </script>
</body>
</html>
