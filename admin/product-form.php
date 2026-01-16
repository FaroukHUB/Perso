<?php
/**
 * PERSONNALY - Admin : Formulaire Produit (Ajout/Modification)
 * Support images Face + Dos
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$productModel = new Product();
$orderModel = new Order();
$pendingOrders = $orderModel->countNew();

// Mode édition ou création
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$product = $id ? $productModel->findById($id) : null;
$isEdit = $product !== null;

$error = '';
$formData = [
    'name' => $product['name'] ?? '',
    'description' => $product['description'] ?? '',
    'base_price' => $product['base_price'] ?? '',
    'category' => $product['category'] ?? '',
    'active' => $product['active'] ?? 1,
    'image_front_url' => $product['image_front_url'] ?? '',
    'image_back_url' => $product['image_back_url'] ?? '',
];

// Catégories disponibles
$categories = ['Homme', 'Femme', 'Enfant', 'Unisexe', 'Accessoire'];

// Dossier d'upload
$uploadDir = __DIR__ . '/../public/uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

/**
 * Gère l'upload d'une image
 */
function handleImageUpload(array $file, string $uploadDir, ?string $oldImage = null): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5 MB

    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Format d\'image non supporté. Utilisez JPG, PNG, WebP ou GIF.');
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('L\'image est trop volumineuse (max 5 Mo).');
    }

    // Générer un nom unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Erreur lors de l\'upload de l\'image.');
    }

    // Supprimer l'ancienne image si elle existe
    if (!empty($oldImage)) {
        $oldFile = __DIR__ . '/../public' . $oldImage;
        if (file_exists($oldFile)) {
            @unlink($oldFile);
        }
    }

    return '/uploads/products/' . $fileName;
}

// Traitement du formulaire
if (isPost()) {
    $csrf = post('csrf_token', '');

    if (!verifyCsrf($csrf)) {
        $error = 'Session expirée. Veuillez réessayer.';
    } else {
        $formData = [
            'name' => trim(post('name', '')),
            'description' => trim(post('description', '')),
            'base_price' => (float)post('base_price', 0),
            'category' => trim(post('category', '')),
            'active' => post('active') ? 1 : 0,
            'image_front_url' => $formData['image_front_url'],
            'image_back_url' => $formData['image_back_url'],
        ];

        try {
            // Upload image FACE
            if (isset($_FILES['image_front']) && $_FILES['image_front']['error'] === UPLOAD_ERR_OK) {
                $formData['image_front_url'] = handleImageUpload(
                    $_FILES['image_front'],
                    $uploadDir,
                    $formData['image_front_url']
                );
            }

            // Upload image DOS
            if (isset($_FILES['image_back']) && $_FILES['image_back']['error'] === UPLOAD_ERR_OK) {
                $formData['image_back_url'] = handleImageUpload(
                    $_FILES['image_back'],
                    $uploadDir,
                    $formData['image_back_url']
                );
            }

            // Validation
            if (empty($formData['name'])) {
                $error = 'Le nom du produit est requis.';
            } elseif ($formData['base_price'] <= 0) {
                $error = 'Le prix doit être supérieur à 0.';
            } else {
                // Sauvegarde
                if ($isEdit) {
                    $productModel->update($id, $formData);
                    redirect('/admin/products.php?success=Produit mis à jour');
                } else {
                    $productModel->create($formData);
                    redirect('/admin/products.php?success=Produit créé');
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Modifier' : 'Ajouter' ?> un produit - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            font-weight: 500;
        }
        .alert-error {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
            border-left: 4px solid var(--pink-main);
        }

        .form-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-xl);
        }

        @media (max-width: 1100px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        .form-section h3 {
            font-size: 1rem;
            color: var(--black-soft);
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-sm);
            border-bottom: 2px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-md);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: var(--spacing-md);
            margin-top: var(--spacing-xl);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--gray-light);
        }

        /* Switch toggle */
        .switch-label {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            cursor: pointer;
        }
        .switch-input { display: none; }
        .switch-slider {
            width: 50px;
            height: 28px;
            background: var(--gray-light);
            border-radius: var(--radius-full);
            position: relative;
            transition: background var(--transition-fast);
        }
        .switch-slider::before {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            background: white;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: transform var(--transition-fast);
            box-shadow: var(--shadow-sm);
        }
        .switch-input:checked + .switch-slider {
            background: var(--gradient-mint);
        }
        .switch-input:checked + .switch-slider::before {
            transform: translateX(22px);
        }
        .switch-text {
            font-weight: 500;
            color: var(--black-soft);
        }

        /* Images Grid */
        .images-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .image-upload-card {
            background: var(--gray-light);
            border: 2px dashed rgba(0,0,0,0.1);
            border-radius: var(--radius-lg);
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
        }
        .image-upload-card:hover {
            border-color: var(--pink-main);
            background: rgba(255, 105, 180, 0.05);
        }
        .image-upload-card.has-image {
            border-style: solid;
            border-color: var(--mint-main);
        }

        .image-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 15px;
            color: var(--black-soft);
        }
        .image-label .badge {
            font-size: 11px;
            padding: 4px 10px;
        }

        .preview-box {
            background: white;
            border-radius: var(--radius-md);
            min-height: 180px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-bottom: 12px;
            overflow: hidden;
        }
        .preview-box img {
            max-width: 100%;
            max-height: 180px;
            border-radius: var(--radius-md);
            object-fit: contain;
        }
        .preview-icon {
            font-size: 3rem;
            opacity: 0.4;
            margin-bottom: 8px;
        }
        .preview-box p {
            color: var(--gray);
            font-size: 13px;
        }

        .upload-btn {
            width: 100%;
            padding: 10px;
            font-size: 13px;
        }
        .upload-hint {
            font-size: 11px;
            color: var(--gray);
            margin-top: 8px;
        }

        /* Zone link */
        .zone-link-section {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">
                    <?= $isEdit ? 'Modifier le' : 'Nouveau' ?> <span>Produit</span>
                </h1>
                <a href="/admin/products.php" class="btn btn-dark">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Retour
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="form-card">
                <form method="post" class="product-form" enctype="multipart/form-data">
                    <?= csrfField() ?>

                    <div class="form-grid">
                        <!-- Colonne gauche : Infos -->
                        <div class="form-section">
                            <h3>Informations générales</h3>

                            <div class="form-group">
                                <label class="form-label" for="name">Nom du produit *</label>
                                <input type="text" id="name" name="name" class="form-input"
                                       placeholder="Ex: T-Shirt Homme Premium"
                                       value="<?= h($formData['name']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="description">Description</label>
                                <textarea id="description" name="description" class="form-input form-textarea"
                                          placeholder="Description du produit..."
                                          rows="4"><?= h($formData['description']) ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="base_price">Prix de base (€) *</label>
                                    <input type="number" id="base_price" name="base_price" class="form-input"
                                           step="0.01" min="0"
                                           placeholder="19.90"
                                           value="<?= h($formData['base_price']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="category">Catégorie</label>
                                    <select id="category" name="category" class="form-input">
                                        <option value="">Sélectionner...</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= h($cat) ?>" <?= $formData['category'] === $cat ? 'selected' : '' ?>>
                                                <?= h($cat) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="switch-label">
                                    <input type="checkbox" name="active" class="switch-input"
                                           <?= $formData['active'] ? 'checked' : '' ?>>
                                    <span class="switch-slider"></span>
                                    <span class="switch-text">Produit actif (visible sur le site)</span>
                                </label>
                            </div>

                            <?php if ($isEdit): ?>
                            <div class="zone-link-section">
                                <label class="form-label">Zones d'impression</label>
                                <a href="/admin/product-zones.php?product_id=<?= $id ?>" class="btn btn-outline" style="width: 100%;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="9" y1="3" x2="9" y2="21"/>
                                        <line x1="15" y1="3" x2="15" y2="21"/>
                                    </svg>
                                    Gérer les zones d'impression
                                </a>
                                <p class="upload-hint">Définissez les zones Face et Dos où le texte peut être placé</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Colonne droite : Images -->
                        <div class="form-section">
                            <h3>Images du produit</h3>

                            <div class="images-grid">
                                <!-- Image FACE -->
                                <div class="image-upload-card <?= !empty($formData['image_front_url']) ? 'has-image' : '' ?>">
                                    <div class="image-label">
                                        <span class="badge badge-pink">FACE</span>
                                        Avant du produit
                                    </div>
                                    <div class="preview-box" onclick="document.getElementById('imageFrontInput').click()">
                                        <?php if (!empty($formData['image_front_url'])): ?>
                                            <img src="/public<?= h($formData['image_front_url']) ?>" alt="Face" id="previewFront">
                                        <?php else: ?>
                                            <div class="preview-icon">👕</div>
                                            <p>Cliquez pour ajouter</p>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" name="image_front" id="imageFrontInput" accept="image/*" style="display: none;">
                                    <button type="button" class="btn btn-secondary upload-btn"
                                            onclick="document.getElementById('imageFrontInput').click()">
                                        <?= !empty($formData['image_front_url']) ? 'Changer' : 'Ajouter' ?>
                                    </button>
                                </div>

                                <!-- Image DOS -->
                                <div class="image-upload-card <?= !empty($formData['image_back_url']) ? 'has-image' : '' ?>">
                                    <div class="image-label">
                                        <span class="badge badge-mint">DOS</span>
                                        Arrière du produit
                                    </div>
                                    <div class="preview-box" onclick="document.getElementById('imageBackInput').click()">
                                        <?php if (!empty($formData['image_back_url'])): ?>
                                            <img src="/public<?= h($formData['image_back_url']) ?>" alt="Dos" id="previewBack">
                                        <?php else: ?>
                                            <div class="preview-icon">👕</div>
                                            <p>Cliquez pour ajouter</p>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" name="image_back" id="imageBackInput" accept="image/*" style="display: none;">
                                    <button type="button" class="btn btn-secondary upload-btn"
                                            onclick="document.getElementById('imageBackInput').click()">
                                        <?= !empty($formData['image_back_url']) ? 'Changer' : 'Ajouter' ?>
                                    </button>
                                </div>
                            </div>

                            <p class="upload-hint" style="text-align: center; margin-top: 15px;">
                                Formats: JPG, PNG, WebP, GIF (max 5 Mo par image)<br>
                                <strong>L'image Dos est optionnelle</strong>
                            </p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="/admin/products.php" class="btn btn-dark">Annuler</a>
                        <button type="submit" class="btn btn-primary">
                            <?= $isEdit ? 'Enregistrer les modifications' : 'Créer le produit' ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Preview images on select
        function setupImagePreview(inputId) {
            const input = document.getElementById(inputId);
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const container = input.closest('.image-upload-card').querySelector('.preview-box');
                        container.innerHTML = '<img src="' + e.target.result + '" alt="Aperçu">';
                        input.closest('.image-upload-card').classList.add('has-image');
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        setupImagePreview('imageFrontInput');
        setupImagePreview('imageBackInput');
    </script>
</body>
</html>
