<?php
/**
 * PERSONNALY - Admin : Formulaire Pack / Idée (Ajout/Modification)
 * Gestion des packs avec multi-produits et preset JSON
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Pack.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Font.php';
require_once __DIR__ . '/../app/models/CustomizationOption.php';

Auth::requireAdmin();

$packModel = new Pack();
$productModel = new Product();
$fontModel = new Font();
$optionModel = new CustomizationOption();

// Mode édition ou création
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$pack = $id ? $packModel->findById($id) : null;
$isEdit = $pack !== null;

// Données pour le formulaire
$allProducts = $productModel->findActive();
$allFonts = $fontModel->findAll();
$allTechniques = $optionModel->findAllByType('technique');
$allTextColors = $optionModel->findAllByType('text_color');

// Récupérer les IDs des produits liés au pack
$packProductIds = [];
if ($isEdit && !empty($pack['products'])) {
    $packProductIds = array_column($pack['products'], 'id');
}

$error = '';
$types = $packModel->getTypes();
$statuses = $packModel->getStatuses();

// Valeurs par défaut du formulaire
$formData = [
    'name' => $pack['name'] ?? '',
    'description' => $pack['description'] ?? '',
    'type' => $pack['type'] ?? 'inspiration',
    'status' => $pack['status'] ?? 'draft',
    'sort_order' => $pack['sort_order'] ?? 0,
    'cover_image_url' => $pack['cover_image_url'] ?? '',
];

// Preset par défaut
$preset = $pack['preset'] ?? [
    'text' => '',
    'font' => '',
    'text_color' => '',
    'technique' => '',
    'position' => ['x' => 50, 'y' => 50],
    'view' => 'front',
];

// Dossier d'upload
$uploadDir = __DIR__ . '/../public/uploads/packs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

/**
 * Gère l'upload d'une image
 */
function handlePackImageUpload(array $file, string $uploadDir, ?string $oldImage = null): ?string
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
    $fileName = 'pack_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
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

    return '/uploads/packs/' . $fileName;
}

// Traitement du formulaire
if (isPost()) {
    $csrf = post('csrf_token', '');

    if (!verifyCsrf($csrf)) {
        $error = 'Session expirée. Veuillez réessayer.';
    } else {
        // Récupérer les données du formulaire
        $formData['name'] = trim(post('name', ''));
        $formData['description'] = trim(post('description', ''));
        $formData['type'] = post('type', 'inspiration');
        $formData['status'] = post('status', 'draft');
        $formData['sort_order'] = (int) post('sort_order', 0);

        // Récupérer les produits sélectionnés
        $selectedProducts = $_POST['products'] ?? [];
        $packProductIds = array_map('intval', $selectedProducts);

        // Récupérer le preset
        $preset = [
            'text' => trim(post('preset_text', '')),
            'font' => post('preset_font', ''),
            'text_color' => post('preset_text_color', ''),
            'technique' => post('preset_technique', ''),
            'position' => [
                'x' => (float) post('preset_position_x', 50),
                'y' => (float) post('preset_position_y', 50),
            ],
            'view' => post('preset_view', 'front'),
        ];

        // Supprimer les valeurs vides du preset
        $preset = array_filter($preset, function($value) {
            if (is_array($value)) return true; // Garder position même vide
            return $value !== '' && $value !== null;
        });

        // Validation
        if (empty($formData['name'])) {
            $error = 'Le nom du pack est requis.';
        } elseif (empty($packProductIds)) {
            $error = 'Sélectionnez au moins un produit.';
        } else {
            try {
                // Gestion de l'image
                $coverImage = $formData['cover_image_url'];
                if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                    $coverImage = handlePackImageUpload(
                        $_FILES['cover_image'],
                        $uploadDir,
                        $isEdit ? $pack['cover_image_url'] : null
                    );
                }

                $data = [
                    'name' => $formData['name'],
                    'description' => $formData['description'],
                    'type' => $formData['type'],
                    'status' => $formData['status'],
                    'sort_order' => $formData['sort_order'],
                    'cover_image_url' => $coverImage,
                    'preset' => $preset,
                    'product_ids' => $packProductIds,
                ];

                if ($isEdit) {
                    $packModel->update($id, $data);
                    $successMsg = 'Pack mis à jour avec succès';
                } else {
                    $id = $packModel->create($data);
                    $successMsg = 'Pack créé avec succès';
                }

                redirect('/admin/packs.php?success=' . urlencode($successMsg));

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Modifier' : 'Créer' ?> un Pack - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title"><?= $isEdit ? 'Modifier' : 'Créer' ?> un <span>Pack</span></h1>
                <a href="/admin/packs.php" class="btn btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Retour
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="pack-form">
                <?= csrfField() ?>

                <div class="form-layout">
                    <!-- Colonne gauche : Informations générales -->
                    <div class="form-section">
                        <h3 class="section-title">Informations générales</h3>

                        <div class="form-group">
                            <label class="form-label">Nom du pack <span class="required">*</span></label>
                            <input type="text" name="name" class="form-input"
                                   value="<?= h($formData['name']) ?>"
                                   placeholder="Ex: Idée Cadeau Élégante" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-input" rows="3"
                                      placeholder="Description courte pour le client"><?= h($formData['description']) ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-input">
                                    <?php foreach ($types as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $formData['type'] === $value ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Statut</label>
                                <select name="status" class="form-input">
                                    <?php foreach ($statuses as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $formData['status'] === $value ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Ordre d'affichage</label>
                            <input type="number" name="sort_order" class="form-input"
                                   value="<?= h($formData['sort_order']) ?>" min="0">
                            <small class="form-hint">Plus petit = affiché en premier</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Image de couverture</label>
                            <?php if (!empty($formData['cover_image_url'])): ?>
                                <div class="current-image">
                                    <img src="/public<?= h($formData['cover_image_url']) ?>" alt="Couverture actuelle">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="cover_image" class="form-input-file" accept="image/*">
                            <small class="form-hint">JPG, PNG, WebP ou GIF (max 5 Mo)</small>
                        </div>
                    </div>

                    <!-- Colonne droite : Produits et Preset -->
                    <div class="form-section">
                        <h3 class="section-title">Produits liés <span class="required">*</span></h3>
                        <p class="section-hint">Sélectionnez les produits qui partageront ce design</p>

                        <div class="products-grid">
                            <?php foreach ($allProducts as $product): ?>
                                <label class="product-checkbox <?= in_array($product['id'], $packProductIds) ? 'selected' : '' ?>">
                                    <input type="checkbox" name="products[]" value="<?= $product['id'] ?>"
                                           <?= in_array($product['id'], $packProductIds) ? 'checked' : '' ?>>
                                    <div class="product-thumb">
                                        <?php if (!empty($product['image_front_url'])): ?>
                                            <img src="/public<?= h($product['image_front_url']) ?>" alt="">
                                        <?php else: ?>
                                            <span>👕</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="product-name"><?= h($product['name']) ?></span>
                                    <span class="product-price"><?= formatPrice($product['base_price']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <h3 class="section-title" style="margin-top: 30px;">Préconfiguration (Preset)</h3>
                        <p class="section-hint">Définissez les valeurs par défaut. Tout reste modifiable par le client.</p>

                        <div class="form-group">
                            <label class="form-label">Texte par défaut</label>
                            <input type="text" name="preset_text" class="form-input"
                                   value="<?= h($preset['text'] ?? '') ?>"
                                   placeholder="Ex: Team Family">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Police</label>
                                <select name="preset_font" class="form-input">
                                    <option value="">-- Libre --</option>
                                    <?php foreach ($allFonts as $font): ?>
                                        <option value="<?= h($font['name']) ?>"
                                                <?= ($preset['font'] ?? '') === $font['name'] ? 'selected' : '' ?>>
                                            <?= h($font['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Technique</label>
                                <select name="preset_technique" class="form-input">
                                    <option value="">-- Libre --</option>
                                    <?php foreach ($allTechniques as $tech): ?>
                                        <option value="<?= h($tech['value']) ?>"
                                                <?= ($preset['technique'] ?? '') === $tech['value'] ? 'selected' : '' ?>>
                                            <?= h($tech['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Couleur du texte</label>
                                <select name="preset_text_color" class="form-input">
                                    <option value="">-- Libre --</option>
                                    <?php foreach ($allTextColors as $color): ?>
                                        <option value="<?= h($color['value']) ?>"
                                                <?= ($preset['text_color'] ?? '') === $color['value'] ? 'selected' : '' ?>
                                                style="background-color: <?= h($color['value']) ?>;">
                                            <?= h($color['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Vue</label>
                                <select name="preset_view" class="form-input">
                                    <option value="front" <?= ($preset['view'] ?? 'front') === 'front' ? 'selected' : '' ?>>Face</option>
                                    <option value="back" <?= ($preset['view'] ?? '') === 'back' ? 'selected' : '' ?>>Dos</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Position X (%)</label>
                                <input type="number" name="preset_position_x" class="form-input"
                                       value="<?= h($preset['position']['x'] ?? 50) ?>"
                                       min="0" max="100" step="0.1">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Position Y (%)</label>
                                <input type="number" name="preset_position_y" class="form-input"
                                       value="<?= h($preset['position']['y'] ?? 50) ?>"
                                       min="0" max="100" step="0.1">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="/admin/packs.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        <?= $isEdit ? 'Enregistrer' : 'Créer le pack' ?>
                    </button>
                </div>
            </form>
        </main>
    </div>

    <style>
        .form-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        @media (max-width: 1024px) {
            .form-layout { grid-template-columns: 1fr; }
        }
        .form-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 10px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .section-hint {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 20px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
            color: var(--black-soft);
        }
        .form-label .required { color: var(--pink-main); }
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            font-size: 15px;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.1);
        }
        .form-hint {
            display: block;
            font-size: 12px;
            color: var(--gray);
            margin-top: 6px;
        }
        .current-image {
            margin-bottom: 15px;
        }
        .current-image img {
            max-width: 150px;
            border-radius: var(--radius-md);
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
            max-height: 300px;
            overflow-y: auto;
            padding: 5px;
        }
        .product-checkbox {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .product-checkbox:hover {
            border-color: var(--pink-light);
        }
        .product-checkbox.selected,
        .product-checkbox:has(input:checked) {
            border-color: var(--pink-main);
            background: linear-gradient(135deg, rgba(255,105,180,0.08) 0%, rgba(61,255,192,0.08) 100%);
        }
        .product-checkbox input {
            position: absolute;
            opacity: 0;
        }
        .product-thumb {
            width: 60px;
            height: 60px;
            background: var(--gray-light);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            overflow: hidden;
        }
        .product-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-thumb span {
            font-size: 1.5rem;
        }
        .product-name {
            font-size: 12px;
            font-weight: 600;
            color: var(--black-soft);
            margin-bottom: 4px;
        }
        .product-price {
            font-size: 11px;
            color: var(--mint-dark);
            font-weight: 700;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(0,0,0,0.06);
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 25px;
            font-weight: 500;
        }
        .alert-error {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
            border-left: 4px solid var(--pink-main);
        }
    </style>

    <script>
        // Toggle visual selection on product checkboxes
        document.querySelectorAll('.product-checkbox input').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                this.closest('.product-checkbox').classList.toggle('selected', this.checked);
            });
        });
    </script>
</body>
</html>
