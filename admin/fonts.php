<?php
/**
 * PERSONNALY - Admin : Gestion des Polices
 * P1 - Polices administrables
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Font.php';
require_once __DIR__ . '/../app/helpers/FontLoader.php';

Auth::requireAdmin();

$fontModel = new Font();
$categories = $fontModel->getCategories();

$success = '';
$error = '';

// =========================================
// ACTIONS POST
// =========================================
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {

    // Ajouter une police Google
    if (isset($_POST['add_google'])) {
        $name = trim(post('name', ''));
        $family = trim(post('family', ''));
        $weights = trim(post('google_weights', '400'));
        $category = post('category', 'sans-serif');

        if (empty($name) || empty($family)) {
            $error = 'Le nom et la famille sont obligatoires.';
        } else {
            try {
                $fontModel->create([
                    'name' => $name,
                    'family' => $family,
                    'source' => 'google',
                    'google_weights' => $weights,
                    'category' => $category,
                    'active' => 1,
                    'sort_order' => 0
                ]);
                $success = 'Police Google ajoutée avec succès.';
                FontLoader::clearCache();
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'uniq_css_key') !== false) {
                    $error = 'Cette combinaison famille/weights existe déjà.';
                } else {
                    $error = 'Erreur lors de l\'ajout : ' . $e->getMessage();
                }
            }
        }
    }

    // Ajouter une police Custom
    if (isset($_POST['add_custom'])) {
        $name = trim(post('name', ''));
        $family = trim(post('family', ''));
        $category = post('category', 'sans-serif');

        if (empty($name) || empty($family)) {
            $error = 'Le nom et la famille sont obligatoires.';
        } else {
            $woff2Url = null;
            $woffUrl = null;

            // Upload WOFF2
            if (isset($_FILES['woff2_file']) && $_FILES['woff2_file']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFontFile($_FILES['woff2_file'], 'woff2');
                if ($uploadResult['success']) {
                    $woff2Url = $uploadResult['url'];
                } else {
                    $error = $uploadResult['error'];
                }
            }

            // Upload WOFF (fallback)
            if (!$error && isset($_FILES['woff_file']) && $_FILES['woff_file']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFontFile($_FILES['woff_file'], 'woff');
                if ($uploadResult['success']) {
                    $woffUrl = $uploadResult['url'];
                } else {
                    $error = $uploadResult['error'];
                }
            }

            if (!$error && !$woff2Url) {
                $error = 'Le fichier WOFF2 est obligatoire pour les polices custom.';
            }

            if (!$error) {
                try {
                    $fontModel->create([
                        'name' => $name,
                        'family' => $family,
                        'source' => 'custom',
                        'google_weights' => '400',
                        'custom_woff2_url' => $woff2Url,
                        'custom_woff_url' => $woffUrl,
                        'category' => $category,
                        'active' => 1,
                        'sort_order' => 0
                    ]);
                    $success = 'Police custom ajoutée avec succès.';
                    FontLoader::clearCache();
                } catch (PDOException $e) {
                    $error = 'Erreur lors de l\'ajout : ' . $e->getMessage();
                }
            }
        }
    }

    // Modifier une police
    if (isset($_POST['update_font'])) {
        $id = (int) post('font_id', 0);
        $font = $fontModel->findById($id);

        if ($font) {
            $data = [
                'name' => trim(post('name', '')),
                'family' => trim(post('family', '')),
                'category' => post('category', 'sans-serif'),
                'sort_order' => (int) post('sort_order', 0)
            ];

            if ($font['source'] === 'google') {
                $data['google_weights'] = trim(post('google_weights', '400'));
            }

            try {
                $fontModel->update($id, $data);
                $success = 'Police mise à jour.';
                FontLoader::clearCache();
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour.';
            }
        }
    }
}

// =========================================
// ACTIONS GET
// =========================================

// Toggle actif
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    if (verifyCsrf($_GET['token'] ?? '')) {
        $fontModel->toggleActive((int) $_GET['toggle']);
        FontLoader::clearCache();
        redirect('/admin/fonts.php?success=Statut mis à jour');
    }
}

// Supprimer
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (verifyCsrf($_GET['token'] ?? '')) {
        $fontModel->delete((int) $_GET['delete']);
        FontLoader::clearCache();
        redirect('/admin/fonts.php?success=Police supprimée');
    }
}

// Régénérer toutes les URLs Google
if (isset($_GET['regenerate'])) {
    if (verifyCsrf($_GET['token'] ?? '')) {
        $count = $fontModel->regenerateAllGoogleUrls();
        FontLoader::clearCache();
        redirect('/admin/fonts.php?success=' . $count . ' URLs régénérées');
    }
}

// Messages flash GET
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Récupérer les polices
$fonts = $fontModel->findAll();
$counts = $fontModel->countBySource();

// Mode édition
$editFont = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editFont = $fontModel->findById((int) $_GET['edit']);
}

/**
 * Upload un fichier police
 */
function uploadFontFile(array $file, string $type): array
{
    $allowedTypes = [
        'woff2' => ['font/woff2', 'application/font-woff2', 'application/octet-stream'],
        'woff' => ['font/woff', 'application/font-woff', 'application/octet-stream']
    ];
    $allowedExts = [
        'woff2' => ['woff2'],
        'woff' => ['woff']
    ];
    $maxSize = 2 * 1024 * 1024; // 2 MB

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts[$type])) {
        return ['success' => false, 'error' => "Le fichier doit être au format .{$type}"];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Le fichier ne doit pas dépasser 2 Mo.'];
    }

    $uploadDir = __DIR__ . '/../public/uploads/fonts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('font_') . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'url' => '/uploads/fonts/' . $filename];
    }

    return ['success' => false, 'error' => 'Erreur lors de l\'upload.'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polices - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <?= FontLoader::getGoogleFontsLinks() ?>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .fonts-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            align-items: start;
        }

        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-pill {
            background: white;
            padding: 12px 20px;
            border-radius: var(--radius-full);
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-pill.google { border-left: 4px solid var(--pink-main); }
        .stat-pill.custom { border-left: 4px solid var(--mint-main); }

        .font-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.2s;
        }
        .font-card:hover {
            box-shadow: var(--shadow-md);
        }
        .font-card.inactive {
            opacity: 0.5;
        }

        .font-preview {
            width: 80px;
            height: 80px;
            background: var(--gray-light);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .font-info {
            flex: 1;
            min-width: 0;
        }
        .font-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }
        .font-family {
            color: var(--gray);
            font-size: 13px;
            margin-bottom: 8px;
        }
        .font-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .font-tag {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-weight: 600;
        }
        .font-tag.google {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
        }
        .font-tag.custom {
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-dark);
        }
        .font-tag.category {
            background: var(--gray-light);
            color: var(--gray);
        }

        .font-actions {
            display: flex;
            gap: 8px;
        }
        .font-actions a, .font-actions button {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--gray-light);
            color: var(--gray);
        }
        .font-actions a:hover, .font-actions button:hover {
            background: var(--pink-main);
            color: white;
        }
        .font-actions .delete:hover {
            background: #dc3545;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            position: sticky;
            top: 20px;
        }
        .form-card h3 {
            margin-bottom: 20px;
            font-size: 1.1rem;
        }

        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .form-tab {
            flex: 1;
            padding: 12px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            background: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
        }
        .form-tab:hover {
            border-color: var(--pink-light);
        }
        .form-tab.active {
            background: var(--gradient-pink);
            border-color: var(--pink-main);
            color: white;
        }

        .form-section {
            display: none;
        }
        .form-section.active {
            display: block;
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--pink-main);
        }
        .form-hint {
            font-size: 12px;
            color: var(--gray);
            margin-top: 4px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--gradient-mint);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-mint);
        }

        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-dark);
            border-left: 4px solid var(--mint-main);
        }
        .alert-error {
            background: rgba(255, 105, 180, 0.1);
            color: var(--pink-dark);
            border-left: 4px solid var(--pink-main);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 15px;
            opacity: 0.4;
        }

        @media (max-width: 1024px) {
            .fonts-grid {
                grid-template-columns: 1fr;
            }
            .form-card {
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Gestion des <span>Polices</span></h1>
                <a href="?regenerate=1&token=<?= generateCsrf() ?>" class="btn btn-dark" style="font-size: 13px;">
                    Régénérer URLs
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="stats-bar">
                <div class="stat-pill google">
                    <span>Google Fonts</span>
                    <strong><?= $counts['google'] ?></strong>
                </div>
                <div class="stat-pill custom">
                    <span>Custom</span>
                    <strong><?= $counts['custom'] ?></strong>
                </div>
            </div>

            <div class="fonts-grid">
                <!-- Liste des polices -->
                <div class="fonts-list">
                    <?php if (empty($fonts)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🔤</div>
                            <h4>Aucune police</h4>
                            <p>Ajoutez votre première police Google ou custom.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($fonts as $font): ?>
                            <div class="font-card <?= $font['active'] ? '' : 'inactive' ?>">
                                <div class="font-preview" style="font-family: '<?= h($font['family']) ?>', <?= h($font['category']) ?>;">
                                    Aa
                                </div>
                                <div class="font-info">
                                    <div class="font-name"><?= h($font['name']) ?></div>
                                    <div class="font-family"><?= h($font['family']) ?> • <?= h($font['css_key']) ?></div>
                                    <div class="font-meta">
                                        <span class="font-tag <?= $font['source'] ?>"><?= $font['source'] === 'google' ? 'Google' : 'Custom' ?></span>
                                        <span class="font-tag category"><?= h($categories[$font['category']] ?? $font['category']) ?></span>
                                        <?php if ($font['source'] === 'google'): ?>
                                            <span class="font-tag category"><?= h($font['google_weights']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="font-actions">
                                    <a href="?edit=<?= $font['id'] ?>" title="Modifier">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </a>
                                    <a href="?toggle=<?= $font['id'] ?>&token=<?= generateCsrf() ?>" title="<?= $font['active'] ? 'Désactiver' : 'Activer' ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <?php if ($font['active']): ?>
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            <?php else: ?>
                                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                                <line x1="1" y1="1" x2="23" y2="23"/>
                                            <?php endif; ?>
                                        </svg>
                                    </a>
                                    <a href="?delete=<?= $font['id'] ?>&token=<?= generateCsrf() ?>" class="delete" title="Supprimer" onclick="return confirm('Supprimer cette police ?')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Formulaire -->
                <div class="form-card">
                    <?php if ($editFont): ?>
                        <!-- Mode édition -->
                        <h3>Modifier la police</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrf() ?>">
                            <input type="hidden" name="font_id" value="<?= $editFont['id'] ?>">

                            <div class="form-group">
                                <label>Nom affiché</label>
                                <input type="text" name="name" value="<?= h($editFont['name']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Famille CSS</label>
                                <input type="text" name="family" value="<?= h($editFont['family']) ?>" required>
                            </div>

                            <?php if ($editFont['source'] === 'google'): ?>
                                <div class="form-group">
                                    <label>Weights</label>
                                    <input type="text" name="google_weights" value="<?= h($editFont['google_weights']) ?>">
                                    <div class="form-hint">Séparés par ; (ex: 400;600;700)</div>
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Catégorie</label>
                                <select name="category">
                                    <?php foreach ($categories as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $editFont['category'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Ordre d'affichage</label>
                                <input type="number" name="sort_order" value="<?= $editFont['sort_order'] ?>" min="0">
                            </div>

                            <button type="submit" name="update_font" class="btn-submit">Enregistrer</button>
                            <a href="/admin/fonts.php" style="display: block; text-align: center; margin-top: 12px; color: var(--gray);">Annuler</a>
                        </form>
                    <?php else: ?>
                        <!-- Mode ajout -->
                        <h3>Ajouter une police</h3>

                        <div class="form-tabs">
                            <button type="button" class="form-tab active" data-tab="google">Google Fonts</button>
                            <button type="button" class="form-tab" data-tab="custom">Custom (.woff2)</button>
                        </div>

                        <!-- Formulaire Google -->
                        <div class="form-section active" id="form-google">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrf() ?>">

                                <div class="form-group">
                                    <label>Nom affiché</label>
                                    <input type="text" name="name" placeholder="Poppins Bold" required>
                                </div>

                                <div class="form-group">
                                    <label>Famille (Google Fonts)</label>
                                    <input type="text" name="family" placeholder="Poppins" required>
                                    <div class="form-hint">Nom exact de la police sur Google Fonts</div>
                                </div>

                                <div class="form-group">
                                    <label>Weights</label>
                                    <input type="text" name="google_weights" value="400;700" placeholder="400;700">
                                    <div class="form-hint">Séparés par ; (ex: 400;600;700)</div>
                                </div>

                                <div class="form-group">
                                    <label>Catégorie</label>
                                    <select name="category">
                                        <?php foreach ($categories as $key => $label): ?>
                                            <option value="<?= $key ?>"><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" name="add_google" class="btn-submit">Ajouter Google Font</button>
                            </form>
                        </div>

                        <!-- Formulaire Custom -->
                        <div class="form-section" id="form-custom">
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrf() ?>">

                                <div class="form-group">
                                    <label>Nom affiché</label>
                                    <input type="text" name="name" placeholder="Ma Police Custom" required>
                                </div>

                                <div class="form-group">
                                    <label>Famille CSS</label>
                                    <input type="text" name="family" placeholder="MaPolice" required>
                                    <div class="form-hint">Nom utilisé dans font-family CSS</div>
                                </div>

                                <div class="form-group">
                                    <label>Fichier WOFF2 (obligatoire)</label>
                                    <input type="file" name="woff2_file" accept=".woff2" required>
                                </div>

                                <div class="form-group">
                                    <label>Fichier WOFF (fallback, optionnel)</label>
                                    <input type="file" name="woff_file" accept=".woff">
                                </div>

                                <div class="form-group">
                                    <label>Catégorie</label>
                                    <select name="category">
                                        <?php foreach ($categories as $key => $label): ?>
                                            <option value="<?= $key ?>"><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" name="add_custom" class="btn-submit">Uploader la police</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tabs switching
        document.querySelectorAll('.form-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));

                this.classList.add('active');
                document.getElementById('form-' + this.dataset.tab).classList.add('active');
            });
        });
    </script>
</body>
</html>
