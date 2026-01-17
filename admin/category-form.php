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
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
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
