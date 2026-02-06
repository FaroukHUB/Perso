<?php
/**
 * PERSONNALY - Admin : Formulaire Catégorie
 * Création / Modification de catégories produits
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/ImageHelper.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$categoryModel = new Category();
$orderModel = new Order();
$pendingOrders = $orderModel->countNew();
$statuses = $categoryModel->getStatuses();
$availableSizeGroups = $categoryModel->getAvailableSizeGroups();

// Mode édition ou création
$editMode = false;
$category = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $category = $categoryModel->findById((int)$_GET['id']);
    if ($category) {
        $editMode = true;
    }
}

// Traitement du formulaire
$success = '';
$error = '';

if (isPost()) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'status' => $_POST['status'] ?? 'active',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'allowed_size_groups' => $_POST['allowed_size_groups'] ?? []
        ];

        // Générer ou utiliser le slug personnalisé
        $customSlug = trim($_POST['slug'] ?? '');
        if (!empty($customSlug)) {
            $data['slug'] = $categoryModel->generateSlug($customSlug, $editMode ? $category['id'] : null);
        } else {
            $data['slug'] = $categoryModel->generateSlug($data['name'], $editMode ? $category['id'] : null);
        }

        // Upload image
        if (!empty($_FILES['image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../public/uploads/categories/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($ext, $allowed)) {
                $filename = 'cat_' . time() . '_' . uniqid() . '.' . $ext;
                $fullPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $fullPath)) {
                    // Générer version WebP
                    ImageHelper::convertToWebP($fullPath);
                    $data['image_url'] = '/uploads/categories/' . $filename;

                    // Supprimer l'ancienne image si elle existe
                    if ($editMode && !empty($category['image_url'])) {
                        $oldFile = __DIR__ . '/../public' . $category['image_url'];
                        if (file_exists($oldFile)) {
                            @unlink($oldFile);
                            $oldWebp = preg_replace('/\.[^.]+$/', '.webp', $oldFile);
                            if (file_exists($oldWebp)) {
                                @unlink($oldWebp);
                            }
                        }
                    }
                }
            } else {
                $error = 'Format d\'image non autorisé. Utilisez JPG, PNG, GIF ou WebP.';
            }
        } elseif ($editMode && !empty($category['image_url'])) {
            // Conserver l'image existante
            $data['image_url'] = $category['image_url'];
        }

        // Validation
        if (empty($data['name'])) {
            $error = 'Le nom est obligatoire.';
        } elseif (!array_key_exists($data['status'], $statuses)) {
            $error = 'Statut invalide.';
        } elseif (empty($error)) {
            try {
                if ($editMode) {
                    $categoryModel->update($category['id'], $data);
                    $success = 'Catégorie mise à jour avec succès.';
                    $category = $categoryModel->findById($category['id']);
                } else {
                    $newId = $categoryModel->create($data);
                    redirect('/admin/category-form.php?id=' . $newId . '&success=1');
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
            }
        }
    }
}

// Message de succès après redirection
if (isset($_GET['success'])) {
    $success = 'Catégorie créée avec succès.';
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editMode ? 'Modifier' : 'Créer' ?> une catégorie - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        .data-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .data-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .data-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--black);
            margin: 0;
        }
        .data-card-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group:last-child {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--black);
            font-size: 14px;
        }
        .form-group .required {
            color: var(--pink-main);
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            font-size: 15px;
            font-family: inherit;
            background: var(--white);
            transition: all 0.2s ease;
            color: var(--black);
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.1);
        }
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: var(--gray);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 48px;
        }
        .form-text {
            display: block;
            margin-top: 8px;
            font-size: 13px;
            color: var(--gray);
        }
        .form-text strong {
            color: var(--pink-main);
        }

        /* File input moderne */
        .form-group input[type="file"] {
            padding: 12px 16px;
            border: 2px dashed var(--gray-light);
            border-radius: var(--radius-md);
            background: var(--gray-light);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .form-group input[type="file"]:hover {
            border-color: var(--pink-main);
            background: rgba(255, 105, 180, 0.05);
        }
        .form-group input[type="file"]::file-selector-button {
            padding: 8px 16px;
            margin-right: 12px;
            border: none;
            border-radius: var(--radius-sm);
            background: var(--gradient-pink);
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .form-group input[type="file"]::file-selector-button:hover {
            transform: scale(1.02);
        }

        /* Image preview */
        .current-image {
            background: var(--gray-light);
            padding: 15px;
            border-radius: var(--radius-md);
            text-align: center;
        }
        .current-image img {
            max-height: 200px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }

        /* Bouton principal */
        .btn-block {
            width: 100%;
            justify-content: center;
            padding: 16px 24px;
            font-size: 15px;
            margin-top: 10px;
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success {
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-dark);
            border-left: 4px solid var(--mint-main);
        }
        .alert-error {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
            border-left: 4px solid var(--pink-main);
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">
                    <?php if ($editMode): ?>
                        Modifier <span>la catégorie</span>
                    <?php else: ?>
                        Nouvelle <span>catégorie</span>
                    <?php endif; ?>
                </h1>
                <a href="/admin/categories.php" class="btn btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Retour à la liste
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-grid">
                    <!-- Colonne principale -->
                    <div class="form-main">
                        <div class="data-card">
                            <div class="data-card-header">
                                <h3 class="data-card-title">Informations</h3>
                            </div>
                            <div class="data-card-body">
                                <div class="form-group">
                                    <label for="name">Nom de la catégorie <span class="required">*</span></label>
                                    <input type="text" id="name" name="name" required
                                           value="<?= h($category['name'] ?? '') ?>"
                                           placeholder="Ex: Homme, Femme, Enfant...">
                                </div>

                                <div class="form-group">
                                    <label for="slug">Slug (URL)</label>
                                    <input type="text" id="slug" name="slug"
                                           value="<?= h($category['slug'] ?? '') ?>"
                                           placeholder="Laissez vide pour générer automatiquement">
                                    <small class="form-text">Utilisé dans l'URL: /categorie/<strong>slug</strong></small>
                                </div>

                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="4"
                                              placeholder="Description optionnelle de la catégorie..."><?= h($category['description'] ?? '') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>📏 Groupes de tailles autorisés</label>
                                    <small class="form-text" style="margin-bottom: 10px; display: block;">
                                        Sélectionnez les groupes de tailles compatibles avec cette catégorie.
                                        <br><strong>Exemple:</strong> Catégorie "Bébé" → Uniquement "Enfants"
                                    </small>
                                    <?php
                                    $selectedGroups = [];
                                    if ($editMode && !empty($category['allowed_size_groups'])) {
                                        $selectedGroups = json_decode($category['allowed_size_groups'], true) ?: [];
                                    }
                                    ?>
                                    <div class="categories-checkboxes">
                                        <?php foreach ($availableSizeGroups as $groupKey => $groupLabel): ?>
                                            <label class="checkbox-label">
                                                <input type="checkbox"
                                                       name="allowed_size_groups[]"
                                                       value="<?= h($groupKey) ?>"
                                                       <?= in_array($groupKey, $selectedGroups) ? 'checked' : '' ?>>
                                                <span class="checkbox-custom"></span>
                                                <span class="checkbox-text"><?= h($groupLabel) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="form-text">
                                        Si aucun groupe n'est sélectionné, toutes les tailles seront disponibles.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne latérale -->
                    <div class="form-sidebar">
                        <div class="data-card">
                            <div class="data-card-header">
                                <h3 class="data-card-title">Publication</h3>
                            </div>
                            <div class="data-card-body">
                                <div class="form-group">
                                    <label for="status">Statut</label>
                                    <select id="status" name="status">
                                        <?php foreach ($statuses as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= ($category['status'] ?? 'active') === $key ? 'selected' : '' ?>>
                                                <?= h($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="sort_order">Ordre d'affichage</label>
                                    <input type="number" id="sort_order" name="sort_order" min="0"
                                           value="<?= (int)($category['sort_order'] ?? 0) ?>">
                                    <small class="form-text">Plus petit = affiché en premier</small>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                        <polyline points="17 21 17 13 7 13 7 21"/>
                                        <polyline points="7 3 7 8 15 8"/>
                                    </svg>
                                    <?= $editMode ? 'Mettre à jour' : 'Créer la catégorie' ?>
                                </button>
                            </div>
                        </div>

                        <div class="data-card">
                            <div class="data-card-header">
                                <h3 class="data-card-title">Image</h3>
                            </div>
                            <div class="data-card-body">
                                <?php if ($editMode && !empty($category['image_url'])): ?>
                                    <div class="current-image" style="margin-bottom: 15px;">
                                        <img src="/public<?= h($category['image_url']) ?>" alt="Image actuelle"
                                             style="max-width: 100%; height: auto; border-radius: 8px;">
                                    </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label for="image"><?= $editMode && !empty($category['image_url']) ? 'Changer l\'image' : 'Ajouter une image' ?></label>
                                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                                    <small class="form-text">Formats acceptés: JPG, PNG, GIF, WebP. Max 5 Mo.</small>
                                </div>

                                <div id="imagePreview" style="display: none; margin-top: 10px;">
                                    <img src="" alt="Aperçu" style="max-width: 100%; height: auto; border-radius: 8px;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
    // Preview image avant upload
    document.getElementById('image').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        const file = e.target.files[0];

        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.querySelector('img').src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    });

    // Auto-génération du slug depuis le nom
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    let slugManuallyEdited = <?= $editMode ? 'true' : 'false' ?>;

    slugInput.addEventListener('input', function() {
        slugManuallyEdited = this.value.length > 0;
    });

    nameInput.addEventListener('input', function() {
        if (!slugManuallyEdited) {
            // Générer un slug simple côté client (le serveur fera la vraie génération)
            slugInput.value = this.value
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Enlever accents
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/[\s-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    });
    </script>
</body>
</html>
