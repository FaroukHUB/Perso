<?php
/**
 * PERSONNALY - Admin : Formulaire Produit (Ajout/Modification)
 * Support images Face + Dos + Variantes couleur avec images
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/ImageHelper.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/ProductColor.php';
require_once __DIR__ . '/../app/models/ProductColorImage.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/CustomizationOption.php';

Auth::requireAdmin();

$productModel = new Product();
$orderModel = new Order();
$productColorModel = new ProductColor();
$productColorImageModel = new ProductColorImage();
$categoryModel = new Category();
$optionModel = new CustomizationOption();
$pendingOrders = $orderModel->countNew();

// Récupérer les tailles personnalisées de la base de données
$customSizesFromDb = $optionModel->getSizes();
$customSizes = !empty($customSizesFromDb) ? array_column($customSizesFromDb, 'value') : [];

// Récupérer toutes les catégories disponibles
$allCategories = $categoryModel->findAllActive();

// Mode édition ou création
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$product = $id ? $productModel->findById($id) : null;
$isEdit = $product !== null;

// Récupérer les couleurs du produit (ancien système)
$productColors = $isEdit ? $productColorModel->findAllByProduct($id) : [];

// Récupérer les variantes couleur avec images (nouveau système)
$colorVariants = $isEdit ? $productColorImageModel->findByProduct($id) : [];

// Récupérer les catégories du produit si édition
$productCategoryIds = $isEdit ? $categoryModel->getCategoryIdsByProduct($id) : [];

$error = '';
$formData = [
    'name' => $product['name'] ?? '',
    'description' => $product['description'] ?? '',
    'base_price' => $product['base_price'] ?? '',
    'category' => $product['category'] ?? '',
    'active' => $product['active'] ?? 1,
    'image_front_url' => $product['image_front_url'] ?? '',
    'image_back_url' => $product['image_back_url'] ?? '',
    'available_sizes' => $product['available_sizes'] ?? null,
];

// Décoder les tailles disponibles (JSON -> array)
$productSizes = [];
if (!empty($formData['available_sizes'])) {
    $productSizes = json_decode($formData['available_sizes'], true) ?: [];
}
// Default sizes if empty
if (empty($productSizes)) {
    $productSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
}

// Les catégories sont maintenant récupérées de la base de données ($allCategories)

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

    // Générer version WebP pour performance
    ImageHelper::convertToWebP($filePath);

    // Supprimer l'ancienne image si elle existe
    if (!empty($oldImage)) {
        $oldFile = __DIR__ . '/../public' . $oldImage;
        if (file_exists($oldFile)) {
            @unlink($oldFile);
            // Supprimer aussi la version WebP
            $oldWebp = preg_replace('/\.[^.]+$/', '.webp', $oldFile);
            if (file_exists($oldWebp)) {
                @unlink($oldWebp);
            }
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
        // Récupérer les tailles sélectionnées
        $selectedSizes = post('available_sizes', []);
        $availableSizesJson = !empty($selectedSizes) ? json_encode($selectedSizes) : null;

        $formData = [
            'name' => trim(post('name', '')),
            'description' => trim(post('description', '')),
            'base_price' => (float)post('base_price', 0),
            'category' => trim(post('category', '')),
            'active' => post('active') ? 1 : 0,
            'image_front_url' => $formData['image_front_url'],
            'image_back_url' => $formData['image_back_url'],
            'available_sizes' => $availableSizesJson,
        ];

        try {
            // Créer le dossier d'upload s'il n'existe pas
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Impossible de créer le dossier d\'upload.');
                }
            }

            // Upload image FACE
            if (isset($_FILES['image_front']) && $_FILES['image_front']['error'] === UPLOAD_ERR_OK) {
                $newFrontUrl = handleImageUpload(
                    $_FILES['image_front'],
                    $uploadDir,
                    $formData['image_front_url']
                );
                if ($newFrontUrl) {
                    $formData['image_front_url'] = $newFrontUrl;
                }
            }

            // Upload image DOS
            if (isset($_FILES['image_back']) && $_FILES['image_back']['error'] === UPLOAD_ERR_OK) {
                $newBackUrl = handleImageUpload(
                    $_FILES['image_back'],
                    $uploadDir,
                    $formData['image_back_url']
                );
                if ($newBackUrl) {
                    $formData['image_back_url'] = $newBackUrl;
                }
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
                    $productId = $id;
                } else {
                    $productId = $productModel->create($formData);
                }

                // Sauvegarder les catégories du produit
                $selectedCategories = post('product_categories', []);
                $categoryModel->setProductCategories($productId, array_map('intval', $selectedCategories));

                // === Traitement des variantes produit (couleur + taille + images) ===
                $variantIds = post('variant_ids', []);
                $variantNames = post('variant_names', []);
                $variantHexes = post('variant_hexes', []);
                $variantSizes = post('variant_sizes', []);
                $variantDefaults = post('variant_default', '');

                // Variantes à supprimer
                $deleteVariants = post('delete_variants', []);
                foreach ($deleteVariants as $delId) {
                    $productColorImageModel->delete((int)$delId);
                }

                // Mettre à jour ou créer les variantes
                foreach ($variantNames as $i => $vName) {
                    $vName = trim($vName);
                    if (empty($vName)) continue;

                    $vHex = trim($variantHexes[$i] ?? '#CCCCCC');
                    $vSize = trim($variantSizes[$i] ?? '');
                    $vId = isset($variantIds[$i]) ? (int)$variantIds[$i] : 0;
                    $isDefault = ($variantDefaults == $i) ? 1 : 0;

                    // Upload image face variante
                    $frontUrl = null;
                    if (isset($_FILES['variant_front_' . $i]) && $_FILES['variant_front_' . $i]['error'] === UPLOAD_ERR_OK) {
                        $frontUrl = $productColorImageModel->uploadImage(
                            $_FILES['variant_front_' . $i],
                            $productId,
                            $vName,
                            'front'
                        );
                    }

                    // Upload image dos variante
                    $backUrl = null;
                    if (isset($_FILES['variant_back_' . $i]) && $_FILES['variant_back_' . $i]['error'] === UPLOAD_ERR_OK) {
                        $backUrl = $productColorImageModel->uploadImage(
                            $_FILES['variant_back_' . $i],
                            $productId,
                            $vName,
                            'back'
                        );
                    }

                    if ($vId > 0) {
                        // Mise à jour variante existante
                        $updateData = [
                            'color_name' => $vName,
                            'hex_code' => $vHex,
                            'size' => $vSize ?: null,
                            'is_default' => $isDefault,
                            'sort_order' => $i
                        ];
                        if ($frontUrl) $updateData['image_front_url'] = $frontUrl;
                        if ($backUrl) $updateData['image_back_url'] = $backUrl;
                        $productColorImageModel->update($vId, $updateData);
                    } else {
                        // Nouvelle variante
                        $productColorImageModel->create([
                            'product_id' => $productId,
                            'color_name' => $vName,
                            'hex_code' => $vHex,
                            'size' => $vSize ?: null,
                            'image_front_url' => $frontUrl,
                            'image_back_url' => $backUrl,
                            'is_default' => $isDefault,
                            'sort_order' => $i
                        ]);
                    }
                }

                redirect('/admin/products.php?success=' . ($isEdit ? 'Produit mis à jour' : 'Produit créé'));
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

        /* Categories Checkboxes */
        .categories-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: var(--gray-light);
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            font-weight: 500;
        }
        .checkbox-label:hover {
            background: var(--pink-light);
        }
        .checkbox-label input {
            display: none;
        }
        .checkbox-custom {
            width: 18px;
            height: 18px;
            border: 2px solid #ccc;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .checkbox-label input:checked + .checkbox-custom {
            background: var(--gradient-mint);
            border-color: var(--mint-main);
        }
        .checkbox-label input:checked + .checkbox-custom::after {
            content: '✓';
            color: var(--black);
            font-size: 12px;
            font-weight: 700;
        }
        .checkbox-label input:checked ~ .checkbox-text {
            color: var(--mint-dark);
        }
        .checkbox-text {
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

        /* === Tailles disponibles === */
        .sizes-section {
            margin-top: 30px;
            padding: 25px;
            background: linear-gradient(135deg, rgba(61, 255, 192, 0.05), rgba(255, 105, 180, 0.05));
            border-radius: var(--radius-lg);
            border: 1px solid rgba(0,0,0,0.06);
        }
        .sizes-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--black-soft);
            margin: 0;
        }
        .sizes-subtitle {
            font-size: 13px;
            color: var(--gray);
            margin: 5px 0 20px 0;
        }
        .size-group {
            margin-bottom: 16px;
        }
        .size-group:last-child {
            margin-bottom: 0;
        }
        .size-group-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .size-toggles {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .size-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            padding: 10px 16px;
            background: white;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        .size-toggle input {
            display: none;
        }
        .size-toggle:hover {
            border-color: var(--mint-light);
            background: rgba(61, 255, 192, 0.05);
        }
        .size-toggle.active,
        .size-toggle:has(input:checked) {
            background: var(--gradient-mint);
            border-color: var(--mint-main);
            color: var(--black);
            box-shadow: 0 2px 8px rgba(61, 255, 192, 0.3);
        }

        /* === Variantes produit (couleur + taille + images) === */
        .variants-section {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid var(--pink-light);
        }
        .variants-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .variants-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--black-soft);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .variants-header h3::before {
            content: '';
            width: 4px;
            height: 24px;
            background: var(--gradient-pink);
            border-radius: 2px;
        }
        .add-variant-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: var(--gradient-pink);
            color: white;
            border: none;
            border-radius: var(--radius-full);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .add-variant-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-pink);
        }
        .variant-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .variant-item {
            background: white;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-lg);
            padding: 20px;
            position: relative;
            transition: border-color 0.2s;
        }
        .variant-item:hover {
            border-color: var(--pink-light);
        }
        .variant-item.is-default {
            border-color: var(--mint-main);
            background: rgba(61, 255, 192, 0.03);
        }
        .variant-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .variant-color-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .variant-color-preview {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .variant-color-inputs {
            display: flex;
            gap: 10px;
        }
        .variant-color-inputs input[type="text"] {
            width: 150px;
            padding: 10px 14px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 500;
        }
        .variant-color-inputs input[type="color"] {
            width: 50px;
            height: 42px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
        }
        .variant-size-select {
            width: 100px !important;
            padding: 10px 12px;
            background: rgba(61, 255, 192, 0.1);
            border: 2px solid var(--mint-light) !important;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2300D9A0' stroke-width='3'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }
        .variant-size-select:focus {
            border-color: var(--mint-main) !important;
            box-shadow: 0 0 0 3px rgba(61, 255, 192, 0.2);
            outline: none;
        }
        .variant-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .variant-default-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: rgba(61, 255, 192, 0.1);
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
            color: var(--mint-dark);
            cursor: pointer;
            transition: all 0.2s;
        }
        .variant-default-label:hover {
            background: rgba(61, 255, 192, 0.2);
        }
        .variant-default-label input {
            width: 16px;
            height: 16px;
        }
        .variant-delete-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
        }
        .variant-delete-btn:hover {
            background: #EF4444;
            color: white;
        }
        .variant-images {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .variant-image-box {
            background: var(--gray-light);
            border: 2px dashed rgba(0,0,0,0.1);
            border-radius: var(--radius-md);
            padding: 15px;
            text-align: center;
            transition: all 0.2s;
        }
        .variant-image-box:hover {
            border-color: var(--pink-main);
        }
        .variant-image-box.has-image {
            border-style: solid;
            border-color: var(--mint-main);
        }
        .variant-image-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .variant-image-preview {
            background: white;
            border-radius: var(--radius-md);
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .variant-image-preview img {
            max-width: 100%;
            max-height: 120px;
            object-fit: contain;
        }
        .variant-image-preview .placeholder {
            font-size: 2.5rem;
            opacity: 0.3;
        }
        .variant-upload-btn {
            width: 100%;
            padding: 8px;
            font-size: 12px;
        }
        .variants-hint {
            margin-top: 20px;
            padding: 15px 20px;
            background: linear-gradient(135deg, rgba(255,105,180,0.08) 0%, rgba(61,255,192,0.08) 100%);
            border-radius: var(--radius-md);
            font-size: 13px;
            color: var(--gray);
            line-height: 1.6;
        }
        .variants-hint strong {
            color: var(--pink-dark);
        }
        .variants-hint ul {
            margin: 10px 0 0 20px;
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
                                    <label class="form-label">Catégories</label>
                                    <?php if (empty($allCategories)): ?>
                                        <p class="text-muted" style="font-size: 13px;">
                                            Aucune catégorie disponible.
                                            <a href="/admin/category-form.php">Créer une catégorie</a>
                                        </p>
                                    <?php else: ?>
                                        <div class="categories-checkboxes">
                                            <?php foreach ($allCategories as $cat): ?>
                                                <label class="checkbox-label">
                                                    <input type="checkbox"
                                                           name="product_categories[]"
                                                           value="<?= $cat['id'] ?>"
                                                           <?= in_array($cat['id'], $productCategoryIds) ? 'checked' : '' ?>>
                                                    <span class="checkbox-custom"></span>
                                                    <span class="checkbox-text"><?= h($cat['name']) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
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

                    <!-- Tailles disponibles pour ce produit -->
                    <?php
                    // Toutes les tailles disponibles
                    $allSizes = [
                        'Lettres' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'],
                        'Chiffres' => ['36', '38', '40', '42', '44', '46', '48'],
                        'Enfants' => ['2 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans'],
                    ];
                    ?>
                    <div class="sizes-section">
                        <div class="sizes-header">
                            <h3>📏 Tailles disponibles</h3>
                            <p class="sizes-subtitle">Cliquez sur les tailles pour les activer/désactiver</p>
                        </div>

                        <?php foreach ($allSizes as $groupName => $sizes): ?>
                        <div class="size-group">
                            <span class="size-group-label"><?= $groupName ?></span>
                            <div class="size-toggles">
                                <?php foreach ($sizes as $size): ?>
                                <label class="size-toggle <?= in_array($size, $productSizes) ? 'active' : '' ?>">
                                    <input type="checkbox" name="available_sizes[]" value="<?= h($size) ?>" <?= in_array($size, $productSizes) ? 'checked' : '' ?>>
                                    <span><?= h($size) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Variantes produit (couleur + taille + images) -->
                    <div class="variants-section">
                        <div class="variants-header">
                            <h3>Variantes produit (couleur + taille)</h3>
                            <button type="button" class="add-variant-btn" onclick="addVariant()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                Ajouter une variante
                            </button>
                        </div>

                        <div class="variant-list" id="variantList">
                            <?php foreach ($colorVariants as $i => $variant): ?>
                            <div class="variant-item <?= $variant['is_default'] ? 'is-default' : '' ?>" data-index="<?= $i ?>">
                                <input type="hidden" name="variant_ids[]" value="<?= (int)$variant['id'] ?>">
                                <div class="variant-header">
                                    <div class="variant-color-info">
                                        <div class="variant-color-preview" style="background-color: <?= h($variant['hex_code']) ?>"></div>
                                        <div class="variant-color-inputs">
                                            <input type="text" name="variant_names[]" value="<?= h($variant['color_name']) ?>" placeholder="Couleur (ex: Noir)">
                                            <input type="color" name="variant_hexes[]" value="<?= h($variant['hex_code']) ?>" onchange="updateVariantPreview(this)">
                                            <select name="variant_sizes[]" class="variant-size-select">
                                                <option value="">Taille</option>
                                                <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'] as $s): ?>
                                                <option value="<?= $s ?>" <?= ($variant['size'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                                <?php endforeach; ?>
                                                <?php foreach (['36', '38', '40', '42', '44', '46', '48'] as $s): ?>
                                                <option value="<?= $s ?>" <?= ($variant['size'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="variant-actions">
                                        <label class="variant-default-label">
                                            <input type="radio" name="variant_default" value="<?= $i ?>" <?= $variant['is_default'] ? 'checked' : '' ?>>
                                            Par défaut
                                        </label>
                                        <button type="button" class="variant-delete-btn" onclick="deleteVariant(this, <?= (int)$variant['id'] ?>)">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="6" x2="6" y2="18"/>
                                                <line x1="6" y1="6" x2="18" y2="18"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="variant-images">
                                    <div class="variant-image-box <?= !empty($variant['image_front_url']) ? 'has-image' : '' ?>">
                                        <div class="variant-image-label">📷 Image Face</div>
                                        <div class="variant-image-preview" onclick="document.getElementById('variantFront<?= $i ?>').click()">
                                            <?php if (!empty($variant['image_front_url'])): ?>
                                                <img src="/public<?= h($variant['image_front_url']) ?>" alt="Face">
                                            <?php else: ?>
                                                <span class="placeholder">👕</span>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="variant_front_<?= $i ?>" id="variantFront<?= $i ?>" accept="image/*" style="display:none" onchange="previewVariantImage(this)">
                                        <button type="button" class="btn btn-secondary variant-upload-btn" onclick="document.getElementById('variantFront<?= $i ?>').click()">
                                            <?= !empty($variant['image_front_url']) ? 'Changer' : 'Ajouter' ?>
                                        </button>
                                    </div>
                                    <div class="variant-image-box <?= !empty($variant['image_back_url']) ? 'has-image' : '' ?>">
                                        <div class="variant-image-label">📷 Image Dos</div>
                                        <div class="variant-image-preview" onclick="document.getElementById('variantBack<?= $i ?>').click()">
                                            <?php if (!empty($variant['image_back_url'])): ?>
                                                <img src="/public<?= h($variant['image_back_url']) ?>" alt="Dos">
                                            <?php else: ?>
                                                <span class="placeholder">👕</span>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="variant_back_<?= $i ?>" id="variantBack<?= $i ?>" accept="image/*" style="display:none" onchange="previewVariantImage(this)">
                                        <button type="button" class="btn btn-secondary variant-upload-btn" onclick="document.getElementById('variantBack<?= $i ?>').click()">
                                            <?= !empty($variant['image_back_url']) ? 'Changer' : 'Ajouter' ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="variants-hint">
                            <strong>Variantes produit</strong> — Une variante = Couleur + Taille + Images :
                            <ul>
                                <li><strong>Couleur :</strong> Nom et code couleur du produit</li>
                                <li><strong>Taille :</strong> Ex: S, M, L, XL, 38, 40, etc.</li>
                                <li><strong>Images :</strong> Photo Face et Dos pour cette variante</li>
                                <li>Marquez une variante "Par défaut" pour l'afficher en premier</li>
                            </ul>
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
        // Toggle des tailles
        document.querySelectorAll('.size-toggle').forEach(label => {
            label.addEventListener('click', function(e) {
                // Toggle la checkbox
                const checkbox = this.querySelector('input[type="checkbox"]');
                // Le navigateur gère le toggle automatiquement, on met juste à jour la classe
                setTimeout(() => {
                    this.classList.toggle('active', checkbox.checked);
                }, 0);
            });
        });

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

        // === Gestion des variantes produit (couleur + taille + images) ===
        let variantIndex = <?= count($colorVariants) ?>;
        let deletedVariants = [];

        function addVariant() {
            const list = document.getElementById('variantList');
            const idx = variantIndex++;

            const item = document.createElement('div');
            item.className = 'variant-item';
            item.dataset.index = idx;
            item.innerHTML = `
                <input type="hidden" name="variant_ids[]" value="0">
                <div class="variant-header">
                    <div class="variant-color-info">
                        <div class="variant-color-preview" style="background-color: #FFFFFF"></div>
                        <div class="variant-color-inputs">
                            <input type="text" name="variant_names[]" placeholder="Couleur (ex: Noir)" required>
                            <input type="color" name="variant_hexes[]" value="#FFFFFF" onchange="updateVariantPreview(this)">
                            <select name="variant_sizes[]" class="variant-size-select">
                                <option value="">Taille</option>
                                <option value="XS">XS</option>
                                <option value="S">S</option>
                                <option value="M">M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                                <option value="XXL">XXL</option>
                                <option value="3XL">3XL</option>
                                <option value="36">36</option>
                                <option value="38">38</option>
                                <option value="40">40</option>
                                <option value="42">42</option>
                                <option value="44">44</option>
                                <option value="46">46</option>
                                <option value="48">48</option>
                            </select>
                        </div>
                    </div>
                    <div class="variant-actions">
                        <label class="variant-default-label">
                            <input type="radio" name="variant_default" value="${idx}">
                            Par défaut
                        </label>
                        <button type="button" class="variant-delete-btn" onclick="deleteVariant(this, 0)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="variant-images">
                    <div class="variant-image-box">
                        <div class="variant-image-label">📷 Image Face</div>
                        <div class="variant-image-preview" onclick="document.getElementById('variantFront${idx}').click()">
                            <span class="placeholder">👕</span>
                        </div>
                        <input type="file" name="variant_front_${idx}" id="variantFront${idx}" accept="image/*" style="display:none" onchange="previewVariantImage(this)">
                        <button type="button" class="btn btn-secondary variant-upload-btn" onclick="document.getElementById('variantFront${idx}').click()">
                            Ajouter
                        </button>
                    </div>
                    <div class="variant-image-box">
                        <div class="variant-image-label">📷 Image Dos</div>
                        <div class="variant-image-preview" onclick="document.getElementById('variantBack${idx}').click()">
                            <span class="placeholder">👕</span>
                        </div>
                        <input type="file" name="variant_back_${idx}" id="variantBack${idx}" accept="image/*" style="display:none" onchange="previewVariantImage(this)">
                        <button type="button" class="btn btn-secondary variant-upload-btn" onclick="document.getElementById('variantBack${idx}').click()">
                            Ajouter
                        </button>
                    </div>
                </div>
            `;
            list.appendChild(item);
        }

        function deleteVariant(btn, variantId) {
            if (!confirm('Supprimer cette variante couleur ?')) return;

            const item = btn.closest('.variant-item');

            // Si c'est une variante existante (id > 0), marquer pour suppression
            if (variantId > 0) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_variants[]';
                input.value = variantId;
                document.querySelector('form').appendChild(input);
            }

            item.remove();
        }

        function updateVariantPreview(input) {
            const preview = input.closest('.variant-item').querySelector('.variant-color-preview');
            preview.style.backgroundColor = input.value;
        }

        function previewVariantImage(input) {
            const file = input.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const container = input.closest('.variant-image-box').querySelector('.variant-image-preview');
                container.innerHTML = '<img src="' + e.target.result + '" alt="Aperçu">';
                input.closest('.variant-image-box').classList.add('has-image');
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
