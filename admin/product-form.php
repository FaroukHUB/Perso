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

// Récupérer les tailles depuis la base de données (groupées)
$sizesGrouped = $optionModel->getSizesGrouped();
// Fallback si pas de tailles en BDD
if (empty($sizesGrouped)) {
    $sizesGrouped = [
        'Lettres' => [
            ['value' => 'XS', 'label' => 'XS'],
            ['value' => 'S', 'label' => 'S'],
            ['value' => 'M', 'label' => 'M'],
            ['value' => 'L', 'label' => 'L'],
            ['value' => 'XL', 'label' => 'XL'],
            ['value' => 'XXL', 'label' => 'XXL'],
            ['value' => '3XL', 'label' => '3XL'],
        ],
        'Chiffres' => [
            ['value' => '36', 'label' => '36'],
            ['value' => '38', 'label' => '38'],
            ['value' => '40', 'label' => '40'],
            ['value' => '42', 'label' => '42'],
            ['value' => '44', 'label' => '44'],
            ['value' => '46', 'label' => '46'],
            ['value' => '48', 'label' => '48'],
        ],
        'Enfants' => [
            ['value' => '2 ans', 'label' => '2 ans'],
            ['value' => '4 ans', 'label' => '4 ans'],
            ['value' => '6 ans', 'label' => '6 ans'],
            ['value' => '8 ans', 'label' => '8 ans'],
            ['value' => '10 ans', 'label' => '10 ans'],
            ['value' => '12 ans', 'label' => '12 ans'],
        ],
    ];
}
// Convertir en JSON pour JavaScript
$sizesJson = json_encode($sizesGrouped);

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
    'sale_price' => $product['sale_price'] ?? '',
    'badge' => $product['badge'] ?? '',
    'badge_color' => $product['badge_color'] ?? '#FF1493',
    'weight' => $product['weight'] ?? '',
    'category' => $product['category'] ?? '',
    'active' => $product['active'] ?? 1,
    'image_front_url' => $product['image_front_url'] ?? '',
    'image_back_url' => $product['image_back_url'] ?? '',
    'available_sizes' => $product['available_sizes'] ?? null,
    'text_positioning_mode' => $product['text_positioning_mode'] ?? 'free',
    'text_fixed_position_x' => $product['text_fixed_position_x'] ?? 50,
    'text_fixed_position_y' => $product['text_fixed_position_y'] ?? 40,
    'text_preset_zones' => $product['text_preset_zones'] ?? null,
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

        // Récupérer le positionnement du texte
        $textPositioningMode = trim(post('text_positioning_mode', 'free'));
        $textFixedX = (int)post('text_fixed_position_x', 50);
        $textFixedY = (int)post('text_fixed_position_y', 40);

        // Récupérer les zones prédéfinies personnalisées
        $textPresetZones = null;
        if ($textPositioningMode === 'preset') {
            $zoneIds = post('preset_zone_id', []);
            $zoneLabels = post('preset_zone_label', []);
            $zoneXs = post('preset_zone_x', []);
            $zoneYs = post('preset_zone_y', []);

            $zones = [];
            for ($i = 0; $i < count($zoneIds); $i++) {
                if (!empty($zoneIds[$i]) && !empty($zoneLabels[$i])) {
                    $zones[] = [
                        'id' => $zoneIds[$i],
                        'label' => $zoneLabels[$i],
                        'x' => (int)($zoneXs[$i] ?? 50),
                        'y' => (int)($zoneYs[$i] ?? 50)
                    ];
                }
            }

            // Si aucune zone définie, utiliser les zones par défaut
            if (empty($zones)) {
                $zones = [
                    ['id' => 'center', 'label' => 'Centré', 'x' => 50, 'y' => 50],
                    ['id' => 'top_left', 'label' => 'Haut gauche', 'x' => 15, 'y' => 15],
                    ['id' => 'top_right', 'label' => 'Haut droite', 'x' => 85, 'y' => 15],
                    ['id' => 'bottom_left', 'label' => 'Bas gauche', 'x' => 15, 'y' => 85],
                    ['id' => 'bottom_right', 'label' => 'Bas droite', 'x' => 85, 'y' => 85]
                ];
            }

            $textPresetZones = json_encode($zones);
        }

        $salePriceRaw = post('sale_price', '');
        $formData = [
            'name' => trim(post('name', '')),
            'description' => trim(post('description', '')),
            'base_price' => (float)post('base_price', 0),
            'sale_price' => $salePriceRaw !== '' ? (float)$salePriceRaw : null,
            'badge' => trim(post('badge', '')),
            'badge_color' => trim(post('badge_color', '#FF1493')),
            'weight' => (int)post('weight', 0) ?: null,
            'category' => trim(post('category', '')),
            'active' => post('active') ? 1 : 0,
            'image_front_url' => $formData['image_front_url'],
            'image_back_url' => $formData['image_back_url'],
            'available_sizes' => $availableSizesJson,
            'text_positioning_mode' => $textPositioningMode,
            'text_fixed_position_x' => $textFixedX,
            'text_fixed_position_y' => $textFixedY,
            'text_preset_zones' => $textPresetZones,
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

                // === Traitement des variantes produit (couleur + tailles + images) ===
                $variantIds = post('variant_ids', []);
                $variantNames = post('variant_names', []);
                $variantHexes = post('variant_hexes', []);
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
                    // Récupérer les tailles sélectionnées pour cette variante (tableau)
                    $vSizes = post('variant_sizes_' . $i, []);
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
                            'available_sizes' => !empty($vSizes) ? $vSizes : null,
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
                            'available_sizes' => !empty($vSizes) ? $vSizes : null,
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
        .sizes-header {
            margin-bottom: 20px;
        }
        .sizes-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--black-soft);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sizes-subtitle {
            font-size: 13px;
            color: var(--gray);
            margin: 5px 0 0 0;
        }
        .sizes-groups {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .size-group {
            background: white;
            padding: 15px 20px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(0,0,0,0.06);
        }
        .size-group-header-inline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .size-group-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--pink-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .size-group-toggle-all {
            font-size: 11px;
            padding: 4px 10px;
            background: var(--gray-light);
            border: none;
            border-radius: var(--radius-full);
            cursor: pointer;
            color: var(--gray);
            font-weight: 500;
            transition: all 0.2s;
        }
        .size-group-toggle-all:hover {
            background: var(--mint-light);
            color: var(--mint-dark);
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
            background: var(--gray-light);
            border: 2px solid transparent;
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
            background: rgba(61, 255, 192, 0.2);
        }
        .size-toggle.active,
        .size-toggle:has(input:checked) {
            background: var(--gradient-mint);
            border-color: var(--mint-main);
            color: var(--black);
            box-shadow: 0 2px 8px rgba(61, 255, 192, 0.3);
        }
        .sizes-quick-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(0,0,0,0.06);
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        .btn-mint {
            background: var(--gradient-mint);
            color: var(--black);
            border: none;
        }
        .btn-mint:hover {
            box-shadow: var(--shadow-mint);
        }

        /* === Variantes produit (couleur + tailles + images) === */
        .variants-section {
            margin-top: 40px;
        }
        .variants-subtitle {
            font-size: 13px;
            color: var(--gray);
            margin: 0;
        }
        /* Simplified variant sizes */
        .variant-sizes-simple {
            padding: 15px;
            background: rgba(61, 255, 192, 0.05);
            border-radius: var(--radius-md);
            margin: 10px 0;
        }
        .variant-sizes-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
            margin-bottom: 10px;
        }
        .variant-sizes-options {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
        }
        .variant-size-radio {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            color: var(--gray);
        }
        .variant-size-radio input {
            display: none;
        }
        .variant-size-radio .radio-btn {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .variant-size-radio input:checked + .radio-btn {
            border-color: var(--mint-main);
            background: var(--mint-main);
        }
        .variant-size-radio input:checked + .radio-btn::after {
            content: '';
            width: 6px;
            height: 6px;
            background: white;
            border-radius: 50%;
        }
        .variant-size-radio input:checked ~ span:last-child {
            color: var(--black-soft);
            font-weight: 500;
        }
        .variant-sizes-limited {
            padding: 12px;
            background: white;
            border-radius: var(--radius-md);
            margin-top: 10px;
        }
        .variant-size-toggles {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .size-toggle-mini {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            padding: 6px 12px;
            background: var(--gray-light);
            border: 2px solid transparent;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        .size-toggle-mini input {
            display: none;
        }
        .size-toggle-mini:hover {
            background: rgba(61, 255, 192, 0.2);
        }
        .size-toggle-mini.active,
        .size-toggle-mini:has(input:checked) {
            background: var(--gradient-mint);
            border-color: var(--mint-main);
            color: var(--black);
            box-shadow: 0 2px 6px rgba(61, 255, 192, 0.3);
        }
        .variants-section .variants-section {
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

        /* === Badge Selector === */
        .badge-selector {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .badge-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .badge-option {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            background: var(--gray-light);
            border: 2px solid transparent;
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            font-weight: 500;
        }
        .badge-option:hover {
            border-color: var(--pink-light);
        }
        .badge-option.active {
            border-color: var(--pink-main);
            background: rgba(255, 105, 180, 0.08);
        }
        .badge-option input {
            display: none;
        }
        .badge-preview {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .badge-custom-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .badge-color-picker {
            width: 42px;
            height: 42px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            padding: 2px;
        }

        /* Positioning mode cards */
        .positioning-mode-card:hover {
            border-color: rgba(139, 92, 246, 0.3) !important;
            background: rgba(139, 92, 246, 0.02) !important;
        }
        .positioning-mode-card:has(input:checked) {
            border-color: #8B5CF6 !important;
            background: rgba(139, 92, 246, 0.08) !important;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
        }

        @media (max-width: 1024px) {
            .positioning-modes {
                grid-template-columns: 1fr !important;
            }
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
                                    <label class="form-label" for="sale_price">Prix soldé (€)</label>
                                    <input type="number" id="sale_price" name="sale_price" class="form-input"
                                           step="0.01" min="0"
                                           placeholder="Laisser vide si pas de solde"
                                           value="<?= h($formData['sale_price']) ?>">
                                    <small class="form-hint">Si renseigné, le prix de base apparaîtra barré</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Badge produit</label>
                                    <div class="badge-selector">
                                        <div class="badge-options">
                                            <label class="badge-option <?= empty($formData['badge']) ? 'active' : '' ?>">
                                                <input type="radio" name="badge" value="" <?= empty($formData['badge']) ? 'checked' : '' ?>>
                                                <span>Aucun</span>
                                            </label>
                                            <label class="badge-option <?= $formData['badge'] === 'Soldé' ? 'active' : '' ?>">
                                                <input type="radio" name="badge" value="Soldé" <?= $formData['badge'] === 'Soldé' ? 'checked' : '' ?>>
                                                <span class="badge-preview" style="background:#FF1493;color:white">Soldé</span>
                                            </label>
                                            <label class="badge-option <?= $formData['badge'] === 'Nouveau' ? 'active' : '' ?>">
                                                <input type="radio" name="badge" value="Nouveau" <?= $formData['badge'] === 'Nouveau' ? 'checked' : '' ?>>
                                                <span class="badge-preview" style="background:#3DFFC0;color:#1a1a2e">Nouveau</span>
                                            </label>
                                            <label class="badge-option <?= $formData['badge'] === 'Populaire' ? 'active' : '' ?>">
                                                <input type="radio" name="badge" value="Populaire" <?= $formData['badge'] === 'Populaire' ? 'checked' : '' ?>>
                                                <span class="badge-preview" style="background:#8B5CF6;color:white">Populaire</span>
                                            </label>
                                            <label class="badge-option <?= $formData['badge'] === 'Limité' ? 'active' : '' ?>">
                                                <input type="radio" name="badge" value="Limité" <?= $formData['badge'] === 'Limité' ? 'checked' : '' ?>>
                                                <span class="badge-preview" style="background:#F59E0B;color:white">Limité</span>
                                            </label>
                                            <label class="badge-option <?= (!empty($formData['badge']) && !in_array($formData['badge'], ['Soldé','Nouveau','Populaire','Limité'])) ? 'active' : '' ?>">
                                                <input type="radio" name="badge" value="custom" <?= (!empty($formData['badge']) && !in_array($formData['badge'], ['Soldé','Nouveau','Populaire','Limité'])) ? 'checked' : '' ?>>
                                                <span>Personnalisé</span>
                                            </label>
                                        </div>
                                        <div class="badge-custom-row" id="badgeCustomRow" style="<?= (!empty($formData['badge']) && !in_array($formData['badge'], ['Soldé','Nouveau','Populaire','Limité'])) ? '' : 'display:none;' ?>">
                                            <input type="text" id="badge_custom" class="form-input" placeholder="Texte du badge"
                                                   value="<?= (!empty($formData['badge']) && !in_array($formData['badge'], ['Soldé','Nouveau','Populaire','Limité'])) ? h($formData['badge']) : '' ?>"
                                                   style="flex:1;">
                                            <input type="color" name="badge_color" value="<?= h($formData['badge_color']) ?>" class="badge-color-picker">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="weight">Poids (grammes)</label>
                                    <input type="number" id="weight" name="weight" class="form-input"
                                           min="0" step="1"
                                           placeholder="500"
                                           value="<?= h($formData['weight']) ?>">
                                    <small class="form-hint">Pour le calcul des frais de livraison</small>
                                </div>
                            </div>

                            <div class="form-row">
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

                    <!-- Section Tailles du produit -->
                    <div class="sizes-section">
                        <div class="sizes-header">
                            <h3>📏 Tailles disponibles</h3>
                            <p class="sizes-subtitle">Sélectionnez les tailles proposées pour ce produit</p>
                        </div>

                        <div class="sizes-groups">
                            <?php foreach ($sizesGrouped as $groupName => $sizes): ?>
                                <div class="size-group">
                                    <div class="size-group-header-inline">
                                        <span class="size-group-label"><?= h($groupName) ?></span>
                                        <button type="button" class="size-group-toggle-all" data-group="<?= h($groupName) ?>">
                                            Tout sélectionner
                                        </button>
                                    </div>
                                    <div class="size-toggles" data-group="<?= h($groupName) ?>">
                                        <?php foreach ($sizes as $size): ?>
                                            <label class="size-toggle <?= in_array($size['value'], $productSizes) ? 'active' : '' ?>">
                                                <input type="checkbox"
                                                       name="available_sizes[]"
                                                       value="<?= h($size['value']) ?>"
                                                       <?= in_array($size['value'], $productSizes) ? 'checked' : '' ?>>
                                                <span><?= h($size['label']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="sizes-quick-actions">
                            <button type="button" class="btn btn-sm btn-mint" onclick="selectAllSizes()">✓ Tout sélectionner</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAllSizes()">✗ Tout désélectionner</button>
                        </div>
                    </div>

                    <!-- Variantes produit (couleur + images) -->
                    <div class="variants-section">
                        <div class="variants-header">
                            <h3>🎨 Variantes couleur</h3>
                            <p class="variants-subtitle">Ajoutez des couleurs avec leurs images (les tailles héritent du produit)</p>
                            <button type="button" class="add-variant-btn" onclick="addVariant()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                Ajouter une variante
                            </button>
                        </div>

                        <div class="variant-list" id="variantList">
                            <?php foreach ($colorVariants as $i => $variant):
                                // Décoder les tailles disponibles de cette variante
                                $variantSizes = [];
                                if (!empty($variant['available_sizes'])) {
                                    $variantSizes = json_decode($variant['available_sizes'], true) ?: [];
                                }
                            ?>
                            <div class="variant-item <?= $variant['is_default'] ? 'is-default' : '' ?>" data-index="<?= $i ?>">
                                <input type="hidden" name="variant_ids[]" value="<?= (int)$variant['id'] ?>">
                                <div class="variant-header">
                                    <div class="variant-color-info">
                                        <div class="variant-color-preview" style="background-color: <?= h($variant['hex_code']) ?>"></div>
                                        <div class="variant-color-inputs">
                                            <input type="text" name="variant_names[]" value="<?= h($variant['color_name']) ?>" placeholder="Couleur (ex: Noir)">
                                            <input type="color" name="variant_hexes[]" value="<?= h($variant['hex_code']) ?>" onchange="updateVariantPreview(this)">
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
                                <div class="variant-sizes-simple">
                                    <span class="variant-sizes-label">📏 Tailles :</span>
                                    <div class="variant-sizes-options">
                                        <?php
                                        // Déterminer si toutes les tailles ou limitées
                                        $allProductSizes = $productSizes;
                                        $isAllSizes = empty($variantSizes) || count(array_diff($allProductSizes, $variantSizes)) === 0;
                                        ?>
                                        <label class="variant-size-radio">
                                            <input type="radio" name="variant_size_mode_<?= $i ?>" value="all" <?= $isAllSizes ? 'checked' : '' ?> onchange="toggleVariantSizes(<?= $i ?>, 'all')">
                                            <span class="radio-btn"></span>
                                            <span>Toutes les tailles du produit</span>
                                        </label>
                                        <label class="variant-size-radio">
                                            <input type="radio" name="variant_size_mode_<?= $i ?>" value="limited" <?= !$isAllSizes ? 'checked' : '' ?> onchange="toggleVariantSizes(<?= $i ?>, 'limited')">
                                            <span class="radio-btn"></span>
                                            <span>Limiter les tailles</span>
                                        </label>
                                    </div>
                                    <div class="variant-sizes-limited" id="variantSizesLimited_<?= $i ?>" style="<?= $isAllSizes ? 'display:none;' : '' ?>">
                                        <div class="variant-size-toggles">
                                            <?php foreach ($sizesGrouped as $groupName => $sizes): ?>
                                                <?php foreach ($sizes as $size): ?>
                                                <label class="size-toggle-mini <?= in_array($size['value'], $variantSizes) ? 'active' : '' ?>">
                                                    <input type="checkbox" name="variant_sizes_<?= $i ?>[]" value="<?= h($size['value']) ?>" <?= in_array($size['value'], $variantSizes) ? 'checked' : '' ?>>
                                                    <span><?= h($size['label']) ?></span>
                                                </label>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </div>
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
                            <strong>💡 Fonctionnement</strong>
                            <ul>
                                <li><strong>Tailles du produit :</strong> Définies ci-dessus, appliquées à toutes les couleurs</li>
                                <li><strong>Variantes :</strong> Chaque couleur peut limiter les tailles disponibles</li>
                                <li><strong>Par défaut :</strong> La variante marquée s'affiche en premier sur le site</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section Positionnement du texte -->
                    <div class="positioning-section" style="margin-top: 40px; padding: 30px; background: linear-gradient(135deg, rgba(139, 92, 246, 0.05), rgba(99, 102, 241, 0.05)); border-radius: var(--radius-lg); border: 1px solid rgba(0,0,0,0.06);">
                        <div class="positioning-header" style="margin-bottom: 25px;">
                            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--black-soft); display: flex; align-items: center; gap: 10px; margin: 0;">
                                <span style="width: 4px; height: 24px; background: linear-gradient(135deg, #8B5CF6, #6366F1); border-radius: 2px;"></span>
                                ✏️ Positionnement de la personnalisation
                            </h3>
                            <p style="margin: 8px 0 0 0; font-size: 13px; color: var(--gray);">
                                Contrôlez comment le client peut placer le texte sur ce produit
                            </p>
                        </div>

                        <div class="positioning-modes" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <!-- Mode FREE -->
                            <label class="positioning-mode-card" style="background: white; padding: 20px; border-radius: var(--radius-md); border: 2px solid transparent; cursor: pointer; transition: all 0.2s;">
                                <input type="radio" name="text_positioning_mode" value="free" <?= $formData['text_positioning_mode'] === 'free' ? 'checked' : '' ?> style="display: none;" onchange="togglePositioningOptions()">
                                <div style="font-size: 2rem; margin-bottom: 10px;">🆓</div>
                                <div style="font-weight: 700; font-size: 15px; color: var(--black-soft); margin-bottom: 6px;">Liberté totale</div>
                                <div style="font-size: 13px; color: var(--gray); line-height: 1.4;">Le client peut déplacer le texte où il veut sur le produit</div>
                            </label>

                            <!-- Mode PRESET -->
                            <label class="positioning-mode-card" style="background: white; padding: 20px; border-radius: var(--radius-md); border: 2px solid transparent; cursor: pointer; transition: all 0.2s;">
                                <input type="radio" name="text_positioning_mode" value="preset" <?= $formData['text_positioning_mode'] === 'preset' ? 'checked' : '' ?> style="display: none;" onchange="togglePositioningOptions()">
                                <div style="font-size: 2rem; margin-bottom: 10px;">📍</div>
                                <div style="font-weight: 700; font-size: 15px; color: var(--black-soft); margin-bottom: 6px;">Emplacements prédéfinis</div>
                                <div style="font-size: 13px; color: var(--gray); line-height: 1.4;">Le client choisit parmi 5 zones prédéfinies</div>
                            </label>

                            <!-- Mode FIXED -->
                            <label class="positioning-mode-card" style="background: white; padding: 20px; border-radius: var(--radius-md); border: 2px solid transparent; cursor: pointer; transition: all 0.2s;">
                                <input type="radio" name="text_positioning_mode" value="fixed" <?= $formData['text_positioning_mode'] === 'fixed' ? 'checked' : '' ?> style="display: none;" onchange="togglePositioningOptions()">
                                <div style="font-size: 2rem; margin-bottom: 10px;">🔒</div>
                                <div style="font-weight: 700; font-size: 15px; color: var(--black-soft); margin-bottom: 6px;">Position fixe</div>
                                <div style="font-size: 13px; color: var(--gray); line-height: 1.4;">Emplacement imposé, le client ne peut pas choisir</div>
                            </label>
                        </div>

                        <!-- Options pour mode PRESET -->
                        <div id="presetPositionOptions" style="<?= $formData['text_positioning_mode'] === 'preset' ? '' : 'display:none;' ?> margin-top: 20px; padding: 20px; background: rgba(99, 102, 241, 0.08); border-radius: var(--radius-md);">
                            <div style="font-weight: 600; font-size: 14px; color: var(--black-soft); margin-bottom: 10px;">📍 Définir les 5 zones prédéfinies</div>
                            <small style="display: block; color: var(--gray); font-size: 12px; margin-bottom: 15px;">
                                Le client pourra choisir parmi ces 5 emplacements dans le configurateur
                            </small>

                            <?php
                            $defaultZones = [
                                ['id' => 'center', 'label' => 'Centré', 'x' => 50, 'y' => 50],
                                ['id' => 'top_left', 'label' => 'Haut gauche', 'x' => 15, 'y' => 15],
                                ['id' => 'top_right', 'label' => 'Haut droite', 'x' => 85, 'y' => 15],
                                ['id' => 'bottom_left', 'label' => 'Bas gauche', 'x' => 15, 'y' => 85],
                                ['id' => 'bottom_right', 'label' => 'Bas droite', 'x' => 85, 'y' => 85]
                            ];
                            $presetZones = !empty($formData['text_preset_zones']) ? json_decode($formData['text_preset_zones'], true) : $defaultZones;
                            if (!is_array($presetZones)) $presetZones = $defaultZones;
                            ?>

                            <div style="display: grid; gap: 12px;">
                                <?php foreach ($presetZones as $idx => $zone): ?>
                                <div style="display: grid; grid-template-columns: 140px 1fr 80px 80px; gap: 10px; align-items: center; padding: 12px; background: white; border-radius: 8px; border: 1px solid rgba(0,0,0,0.08);">
                                    <input type="text" name="preset_zone_label[]" value="<?= h($zone['label']) ?>" placeholder="Label" style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;">
                                    <input type="hidden" name="preset_zone_id[]" value="<?= h($zone['id']) ?>">
                                    <div style="flex: 1; color: var(--gray); font-size: 12px;">Position:</div>
                                    <input type="number" name="preset_zone_x[]" value="<?= h($zone['x']) ?>" min="0" max="100" placeholder="X%" style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;">
                                    <input type="number" name="preset_zone_y[]" value="<?= h($zone['y']) ?>" min="0" max="100" placeholder="Y%" style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <small style="display: block; color: var(--gray); font-size: 12px; margin-top: 10px;">
                                💡 Coordonnées en % : X (0=gauche, 100=droite), Y (0=haut, 100=bas)
                            </small>
                        </div>

                        <!-- Options pour mode FIXED -->
                        <div id="fixedPositionOptions" style="<?= $formData['text_positioning_mode'] === 'fixed' ? '' : 'display:none;' ?> margin-top: 20px; padding: 25px; background: rgba(139, 92, 246, 0.08); border-radius: var(--radius-md);">
                            <div style="font-weight: 600; font-size: 14px; color: var(--black-soft); margin-bottom: 10px;">📍 Où voulez-vous placer le texte ?</div>
                            <small style="display: block; color: var(--gray); font-size: 12px; margin-bottom: 20px;">
                                Choisissez l'emplacement où le texte apparaîtra sur le produit
                            </small>

                            <?php
                            // Positions prédéfinies humaines
                            $fixedPositions = [
                                ['id' => 'top_left', 'label' => '↖️ Haut gauche', 'x' => 15, 'y' => 15],
                                ['id' => 'top_center', 'label' => '⬆️ Haut centre', 'x' => 50, 'y' => 15],
                                ['id' => 'top_right', 'label' => '↗️ Haut droite', 'x' => 85, 'y' => 15],
                                ['id' => 'center_left', 'label' => '⬅️ Centre gauche', 'x' => 15, 'y' => 50],
                                ['id' => 'center', 'label' => '🎯 Centré', 'x' => 50, 'y' => 50],
                                ['id' => 'center_right', 'label' => '➡️ Centre droite', 'x' => 85, 'y' => 50],
                                ['id' => 'bottom_left', 'label' => '↙️ Bas gauche', 'x' => 15, 'y' => 85],
                                ['id' => 'bottom_center', 'label' => '⬇️ Bas centre', 'x' => 50, 'y' => 85],
                                ['id' => 'bottom_right', 'label' => '↘️ Bas droite', 'x' => 85, 'y' => 85]
                            ];

                            // Déterminer quelle position est actuellement sélectionnée
                            $currentX = (int)$formData['text_fixed_position_x'];
                            $currentY = (int)$formData['text_fixed_position_y'];
                            $selectedPosition = 'center'; // défaut
                            foreach ($fixedPositions as $pos) {
                                if (abs($pos['x'] - $currentX) < 10 && abs($pos['y'] - $currentY) < 10) {
                                    $selectedPosition = $pos['id'];
                                    break;
                                }
                            }
                            ?>

                            <!-- Champs cachés pour stocker X et Y -->
                            <input type="hidden" id="text_fixed_position_x" name="text_fixed_position_x" value="<?= h($formData['text_fixed_position_x']) ?>">
                            <input type="hidden" id="text_fixed_position_y" name="text_fixed_position_y" value="<?= h($formData['text_fixed_position_y']) ?>">

                            <!-- Grille visuelle 3x3 -->
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; max-width: 500px; margin: 0 auto;">
                                <?php foreach ($fixedPositions as $pos): ?>
                                <button type="button"
                                        class="fixed-position-btn <?= $selectedPosition === $pos['id'] ? 'active' : '' ?>"
                                        data-position="<?= h($pos['id']) ?>"
                                        data-x="<?= h($pos['x']) ?>"
                                        data-y="<?= h($pos['y']) ?>"
                                        style="padding: 18px 12px; background: white; border: 2px solid #e5e7eb; border-radius: 10px; cursor: pointer; transition: all 0.2s; font-size: 13px; font-weight: 600; color: #374151; text-align: center;">
                                    <?= $pos['label'] ?>
                                </button>
                                <?php endforeach; ?>
                            </div>

                            <div style="margin-top: 15px; padding: 12px 16px; background: rgba(99, 102, 241, 0.1); border-radius: 8px; text-align: center;">
                                <span style="font-size: 12px; color: #6366F1; font-weight: 500;">
                                    💡 Le client ne pourra pas déplacer le texte, il sera toujours à cet emplacement
                                </span>
                            </div>

                            <style>
                                .fixed-position-btn:hover {
                                    background: rgba(139, 92, 246, 0.1);
                                    border-color: #8B5CF6;
                                    transform: scale(1.03);
                                }
                                .fixed-position-btn.active {
                                    background: linear-gradient(135deg, #8B5CF6, #6366F1);
                                    border-color: #8B5CF6;
                                    color: white;
                                    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
                                }
                            </style>
                        </div>

                        <div class="positioning-hint" style="margin-top: 20px; padding: 15px 20px; background: white; border-left: 3px solid #8B5CF6; border-radius: var(--radius-md); font-size: 13px; color: var(--gray); line-height: 1.6;">
                            <strong style="color: #8B5CF6;">💡 Conseil :</strong>
                            <strong>Liberté totale</strong> offre la meilleure expérience client mais peut compliquer la production.
                            <strong>Position fixe</strong> simplifie votre travail mais limite la créativité du client.
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
        // Toggle des tailles - update active class
        function updateSizeToggleClasses() {
            document.querySelectorAll('.size-toggle, .size-toggle-mini').forEach(label => {
                const checkbox = label.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    label.classList.toggle('active', checkbox.checked);
                }
            });
        }

        document.querySelectorAll('.size-toggle, .size-toggle-mini').forEach(label => {
            label.addEventListener('click', function(e) {
                setTimeout(updateSizeToggleClasses, 0);
            });
        });

        // Select/Deselect all sizes
        function selectAllSizes() {
            document.querySelectorAll('.sizes-section .size-toggle input[type="checkbox"]').forEach(cb => {
                cb.checked = true;
            });
            updateSizeToggleClasses();
        }

        function deselectAllSizes() {
            document.querySelectorAll('.sizes-section .size-toggle input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            updateSizeToggleClasses();
        }

        // Toggle all sizes in a group
        document.querySelectorAll('.size-group-toggle-all').forEach(btn => {
            btn.addEventListener('click', function() {
                const groupName = this.dataset.group;
                const container = document.querySelector(`.size-toggles[data-group="${groupName}"]`);
                if (!container) return;

                const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                const allChecked = [...checkboxes].every(cb => cb.checked);

                checkboxes.forEach(cb => {
                    cb.checked = !allChecked;
                });

                this.textContent = allChecked ? 'Tout sélectionner' : 'Tout désélectionner';
                updateSizeToggleClasses();
            });
        });

        // Toggle variant sizes (all / limited)
        function toggleVariantSizes(idx, mode) {
            const limitedContainer = document.getElementById('variantSizesLimited_' + idx);
            if (limitedContainer) {
                limitedContainer.style.display = mode === 'limited' ? 'block' : 'none';
            }
        }

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

        // === Gestion des variantes produit (couleur + tailles + images) ===
        let variantIndex = <?= count($colorVariants) ?>;
        let deletedVariants = [];
        const sizesGrouped = <?= $sizesJson ?>;

        // Configuration pour le filtrage des tailles par catégorie
        const categoryAllowedGroups = <?= json_encode(array_map(function($cat) use ($categoryModel) {
            return [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'groups' => !empty($cat['allowed_size_groups']) ? json_decode($cat['allowed_size_groups'], true) : []
            ];
        }, $allCategories)) ?>;

        console.log('🔍 [FILTRAGE TAILLES] Catégories disponibles:', categoryAllowedGroups);

        function generateSizeToggles(idx) {
            let html = '';
            for (const [groupName, sizes] of Object.entries(sizesGrouped)) {
                sizes.forEach(size => {
                    const value = size.value || size;
                    const label = size.label || size;
                    html += `<label class="size-toggle-mini">
                        <input type="checkbox" name="variant_sizes_${idx}[]" value="${value}" checked>
                        <span>${label}</span>
                    </label>`;
                });
            }
            return html;
        }

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
                <div class="variant-sizes-simple">
                    <span class="variant-sizes-label">📏 Tailles :</span>
                    <div class="variant-sizes-options">
                        <label class="variant-size-radio">
                            <input type="radio" name="variant_size_mode_${idx}" value="all" checked onchange="toggleVariantSizes(${idx}, 'all')">
                            <span class="radio-btn"></span>
                            <span>Toutes les tailles du produit</span>
                        </label>
                        <label class="variant-size-radio">
                            <input type="radio" name="variant_size_mode_${idx}" value="limited" onchange="toggleVariantSizes(${idx}, 'limited')">
                            <span class="radio-btn"></span>
                            <span>Limiter les tailles</span>
                        </label>
                    </div>
                    <div class="variant-sizes-limited" id="variantSizesLimited_${idx}" style="display:none;">
                        <div class="variant-size-toggles">
                            ${generateSizeToggles(idx)}
                        </div>
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

            // Add click listeners for new size toggles
            item.querySelectorAll('.size-toggle-mini').forEach(label => {
                label.addEventListener('click', function() {
                    setTimeout(updateSizeToggleClasses, 0);
                });
            });
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

        // === Badge Selection ===
        document.querySelectorAll('.badge-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Update active state
                document.querySelectorAll('.badge-option').forEach(opt => opt.classList.remove('active'));
                this.closest('.badge-option').classList.add('active');

                // Show/hide custom input
                const customRow = document.getElementById('badgeCustomRow');
                if (this.value === 'custom') {
                    customRow.style.display = 'flex';
                    document.getElementById('badge_custom').focus();
                } else {
                    customRow.style.display = 'none';
                }
            });
        });

        // Before form submission, set badge value from custom input if "custom" is selected
        document.querySelector('.product-form').addEventListener('submit', function(e) {
            const checkedBadge = document.querySelector('input[name="badge"]:checked');
            if (checkedBadge && checkedBadge.value === 'custom') {
                const customVal = document.getElementById('badge_custom').value.trim();
                if (customVal) {
                    checkedBadge.value = customVal;
                } else {
                    checkedBadge.value = '';
                }
            }
        });

        // === Positionnement du texte ===
        function togglePositioningOptions() {
            const selectedMode = document.querySelector('input[name="text_positioning_mode"]:checked').value;
            const presetOptions = document.getElementById('presetPositionOptions');
            const fixedOptions = document.getElementById('fixedPositionOptions');

            // Cacher toutes les options
            presetOptions.style.display = 'none';
            fixedOptions.style.display = 'none';

            // Afficher les options du mode sélectionné
            if (selectedMode === 'preset') {
                presetOptions.style.display = 'block';
            } else if (selectedMode === 'fixed') {
                fixedOptions.style.display = 'block';
            }
        }

        // Gérer les clics sur les boutons de position fixe
        document.querySelectorAll('.fixed-position-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Retirer la classe active de tous les boutons
                document.querySelectorAll('.fixed-position-btn').forEach(b => b.classList.remove('active'));

                // Activer le bouton cliqué
                this.classList.add('active');

                // Mettre à jour les champs cachés
                document.getElementById('text_fixed_position_x').value = this.dataset.x;
                document.getElementById('text_fixed_position_y').value = this.dataset.y;

                console.log('[Position fixe] Sélectionné:', this.dataset.position, `(${this.dataset.x}%, ${this.dataset.y}%)`);
            });
        });

        // === Filtrage intelligent des tailles selon catégories ===
        function filterSizesByCategories() {
            const selectedCats = Array.from(document.querySelectorAll('input[name="product_categories[]"]:checked'))
                .map(cb => parseInt(cb.value));

            console.log('✅ [FILTRAGE TAILLES] Catégories sélectionnées (IDs):', selectedCats);

            // Trouver les TAILLES (values) autorisées pour les catégories sélectionnées
            let allowedSizes = [];
            selectedCats.forEach(catId => {
                const cat = categoryAllowedGroups.find(c => c.id === catId);
                console.log(`   → Catégorie ID ${catId}:`, cat);
                if (cat && cat.groups && cat.groups.length > 0) {
                    console.log(`   ✓ Tailles autorisées pour "${cat.name}":`, cat.groups);
                    allowedSizes = allowedSizes.concat(cat.groups);
                } else {
                    console.log(`   ✗ Aucune taille définie pour "${cat ? cat.name : 'inconnue'}"`);
                }
            });

            console.log('📏 [FILTRAGE TAILLES] Tailles autorisées au total:', allowedSizes);

            // Si aucune catégorie ou aucune restriction, tout afficher
            if (selectedCats.length === 0 || allowedSizes.length === 0) {
                document.querySelectorAll('.size-toggle').forEach(toggle => {
                    toggle.style.display = 'inline-flex';
                });
                document.querySelectorAll('.size-group').forEach(group => {
                    group.style.display = 'block';
                });
                return;
            }

            // Sinon, filtrer les tailles individuelles
            allowedSizes = [...new Set(allowedSizes)]; // unique

            // Parcourir chaque groupe
            document.querySelectorAll('.size-group').forEach(group => {
                const toggles = group.querySelectorAll('.size-toggle');
                let visibleCount = 0;

                // Filtrer chaque taille individuelle
                toggles.forEach(toggle => {
                    const checkbox = toggle.querySelector('input[type="checkbox"]');
                    const sizeValue = checkbox ? checkbox.value : '';

                    if (allowedSizes.includes(sizeValue)) {
                        toggle.style.display = 'inline-flex';
                        visibleCount++;
                    } else {
                        toggle.style.display = 'none';
                        // Décocher si masqué
                        if (checkbox) checkbox.checked = false;
                    }
                });

                // Si aucune taille visible dans ce groupe, cacher le groupe entier
                if (visibleCount === 0) {
                    group.style.display = 'none';
                } else {
                    group.style.display = 'block';
                }
            });

            updateSizeToggleClasses();

            console.log('✨ [FILTRAGE TAILLES] Filtrage terminé - Tailles visibles:', allowedSizes.length);
        }

        // Écouter les changements de catégories
        document.querySelectorAll('input[name="product_categories[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                console.log('🔄 [FILTRAGE TAILLES] Changement de catégorie détecté');
                filterSizesByCategories();
            });
        });

        // Appliquer le filtre au chargement (toujours, pas seulement en mode édition)
        console.log('🚀 [FILTRAGE TAILLES] Application du filtre au chargement...');
        filterSizesByCategories();
    </script>
</body>
</html>
