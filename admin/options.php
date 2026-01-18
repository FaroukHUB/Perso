<?php
/**
 * PERSONNALY - Admin : Gestion Options de Personnalisation
 * Tailles, Couleurs produit, Couleurs texte, Techniques
 */

require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/CustomizationOption.php';

Auth::requireAdmin();

$optionModel = new CustomizationOption();

// Type d'option actuel
$currentType = get('type', 'technique');
if (!in_array($currentType, ['size', 'technique'])) {
    $currentType = 'technique';
}

$typeLabels = [
    'technique' => ['label' => 'Techniques', 'icon' => '🧵', 'desc' => 'Méthodes de personnalisation (Broderie, Flex, Flock) avec tarifs'],
    'size' => ['label' => 'Tailles', 'icon' => '📏', 'desc' => 'Créez vos tailles par groupe (Lettres, Chiffres, Enfants, Personnalisé)'],
];

// Groupes de tailles disponibles
$sizeGroups = ['Lettres', 'Chiffres', 'Enfants', 'Personnalisé'];

$success = '';
$error = '';

// Actions POST
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {
    // Ajouter une option
    if (isset($_POST['add_option'])) {
        $value = trim(post('value', ''));
        $label = trim(post('label', ''));
        $hexCode = trim(post('hex_code', ''));
        $price = post('price', '');
        $description = trim(post('description', ''));
        $sizeGroup = trim(post('size_group', ''));

        if (empty($value) || empty($label)) {
            $error = 'Valeur et libellé sont obligatoires.';
        } elseif ($currentType === 'size' && empty($sizeGroup)) {
            $error = 'Veuillez sélectionner un groupe pour cette taille.';
        } else {
            $optionModel->create([
                'type' => $currentType,
                'value' => $value,
                'label' => $label,
                'hex_code' => in_array($currentType, ['color', 'text_color']) ? $hexCode : null,
                'price' => $currentType === 'technique' && $price !== '' ? (float) $price : null,
                'description' => $currentType === 'technique' ? $description : null,
                'size_group' => $currentType === 'size' ? $sizeGroup : null,
            ]);
            $success = 'Option ajoutée avec succès.';
        }
    }

    // Modifier une option
    if (isset($_POST['edit_option'])) {
        $id = (int) post('option_id', 0);
        $value = trim(post('value', ''));
        $label = trim(post('label', ''));
        $hexCode = trim(post('hex_code', ''));
        $price = post('price', '');
        $description = trim(post('description', ''));
        $sizeGroup = trim(post('size_group', ''));

        if ($id && !empty($value) && !empty($label)) {
            $optionModel->update($id, [
                'value' => $value,
                'label' => $label,
                'hex_code' => in_array($currentType, ['color', 'text_color']) ? $hexCode : null,
                'price' => $currentType === 'technique' && $price !== '' ? (float) $price : null,
                'description' => $currentType === 'technique' ? $description : null,
                'size_group' => $currentType === 'size' ? $sizeGroup : null,
            ]);
            $success = 'Option modifiée avec succès.';
        }
    }

    // Toggle actif
    if (isset($_POST['toggle_option'])) {
        $id = (int) post('option_id', 0);
        if ($id) {
            $optionModel->toggleActive($id);
            $success = 'Statut modifié.';
        }
    }

    // Supprimer
    if (isset($_POST['delete_option'])) {
        $id = (int) post('option_id', 0);
        if ($id) {
            $optionModel->delete($id);
            $success = 'Option supprimée.';
        }
    }

    // Upload image technique
    if (isset($_POST['upload_technique_image'])) {
        $id = (int) post('technique_id', 0);
        if ($id && !empty($_FILES['technique_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../public/uploads/techniques/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['technique_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed)) {
                $option = $optionModel->findById($id);
                $filename = $option['value'] . '_' . time() . '_' . uniqid() . '.' . $ext;
                $fullPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['technique_image']['tmp_name'], $fullPath)) {
                    require_once __DIR__ . '/../app/helpers/ImageHelper.php';
                    ImageHelper::convertToWebP($fullPath);

                    if ($optionModel->addImage($id, '/uploads/techniques/' . $filename)) {
                        $success = 'Image ajoutée avec succès.';
                    } else {
                        $error = 'Maximum 3 images par technique.';
                        @unlink($fullPath);
                    }
                } else {
                    $error = 'Erreur lors de l\'upload.';
                }
            } else {
                $error = 'Format non autorisé. Utilisez JPG, PNG ou WebP.';
            }
        }
    }

    // Supprimer image technique
    if (isset($_POST['delete_technique_image'])) {
        $id = (int) post('technique_id', 0);
        $imageIndex = (int) post('image_index', 0);
        if ($id >= 0) {
            $images = $optionModel->getImages($id);
            if (isset($images[$imageIndex])) {
                $imagePath = __DIR__ . '/../public' . $images[$imageIndex];
                if (file_exists($imagePath)) {
                    @unlink($imagePath);
                    $webpPath = preg_replace('/\.[^.]+$/', '.webp', $imagePath);
                    if (file_exists($webpPath)) @unlink($webpPath);
                }
            }
            $optionModel->removeImage($id, $imageIndex);
            $success = 'Image supprimée.';
        }
    }
}

// Récupérer les options du type actuel
$options = $optionModel->findAllByType($currentType);
$isColorType = in_array($currentType, ['color', 'text_color']);
$isTechniqueType = $currentType === 'technique';
$isSizeType = $currentType === 'size';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Options de Personnalisation - Admin PERSONNALY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .type-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .type-tab {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: white;
            border-radius: var(--radius-full);
            text-decoration: none;
            color: var(--gray);
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            box-shadow: var(--shadow-sm);
        }
        .type-tab:hover {
            color: var(--pink-main);
            transform: translateY(-2px);
        }
        .type-tab.active {
            background: var(--gradient-pink);
            color: white;
            box-shadow: var(--shadow-pink);
        }
        .type-tab .tab-icon {
            font-size: 1.1em;
        }

        .page-desc {
            background: white;
            padding: 15px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 25px;
            font-size: 14px;
            color: var(--gray);
            border-left: 4px solid var(--pink-main);
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 30px;
            align-items: start;
        }

        .options-list {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .list-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            transition: background 0.2s;
        }
        .option-item:hover { background: rgba(255, 105, 180, 0.03); }
        .option-item:last-child { border-bottom: none; }
        .option-item.inactive { opacity: 0.5; }

        .color-preview {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid #eee;
            flex-shrink: 0;
        }
        .technique-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: var(--gradient-mint);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .option-info {
            flex: 1;
            min-width: 0;
        }
        .option-value {
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 2px;
        }
        .option-label {
            font-size: 13px;
            color: var(--gray);
        }
        .option-desc {
            font-size: 12px;
            color: var(--gray);
            margin-top: 4px;
            line-height: 1.4;
        }
        .option-price {
            background: var(--gradient-mint);
            padding: 6px 14px;
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 14px;
            white-space: nowrap;
        }
        .option-actions {
            display: flex;
            gap: 8px;
        }
        .action-btn {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 14px;
        }
        .action-btn.toggle {
            background: var(--gray-light);
        }
        .action-btn.toggle:hover { background: var(--mint-light); }
        .action-btn.toggle.active {
            background: var(--mint-main);
        }
        .action-btn.edit {
            background: var(--gray-light);
        }
        .action-btn.edit:hover { background: var(--pink-light); }
        .action-btn.delete {
            background: var(--gray-light);
            color: #dc3545;
        }
        .action-btn.delete:hover { background: #fee; }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            position: sticky;
            top: 20px;
        }
        .form-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
            color: var(--black-soft);
        }
        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            font-size: 15px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.1);
        }
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        .color-input-group {
            display: flex;
            gap: 10px;
        }
        .color-input-group input[type="color"] {
            width: 50px;
            height: 44px;
            padding: 2px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            cursor: pointer;
        }
        .color-input-group input[type="text"] {
            flex: 1;
        }
        .price-input-group {
            position: relative;
        }
        .price-input-group input {
            padding-right: 40px;
        }
        .price-input-group .currency {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-weight: 600;
        }
        .form-hint {
            font-size: 12px;
            color: var(--gray);
            margin-top: 6px;
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-dark);
        }
        .alert-error {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
            color: var(--gray);
        }
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.4;
        }

        /* Technique Images */
        .technique-images-section {
            background: var(--gray-light);
            padding: 15px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        .technique-images-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .technique-images-header h4 {
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
            margin: 0;
        }
        .technique-images-header span {
            font-size: 11px;
            color: var(--gray);
        }
        .technique-images-grid {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .technique-image-item {
            position: relative;
            width: 80px;
            height: 80px;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .technique-image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .technique-image-delete {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(255, 105, 180, 0.9);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .technique-image-item:hover .technique-image-delete {
            opacity: 1;
        }
        .technique-image-add {
            width: 80px;
            height: 80px;
            border: 2px dashed var(--gray);
            border-radius: var(--radius-md);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: white;
            transition: all 0.2s;
            color: var(--gray);
            font-size: 11px;
            gap: 4px;
        }
        .technique-image-add:hover {
            border-color: var(--pink-main);
            color: var(--pink-main);
            background: rgba(255, 105, 180, 0.05);
        }
        .technique-image-add svg {
            width: 20px;
            height: 20px;
        }
        .technique-images-empty {
            font-size: 12px;
            color: var(--gray);
            font-style: italic;
        }
        .technique-images-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            padding: 10px 14px;
            background: rgba(61, 255, 192, 0.1);
            border-radius: var(--radius-md);
            font-size: 12px;
            color: var(--mint-dark);
        }
        .technique-images-info svg {
            flex-shrink: 0;
            color: var(--mint-main);
        }
        .technique-image-add.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        .technique-image-add.loading svg {
            animation: spin 1s linear infinite;
        }
        .technique-image-add.loading span {
            display: none;
        }
        .technique-image-add.loading::after {
            content: 'Envoi...';
            font-size: 11px;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Edit Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            width: 100%;
            max-width: 500px;
            margin: 20px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal h3 {
            margin-bottom: 25px;
            font-size: 1.2rem;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        .modal-actions button {
            flex: 1;
        }

        /* Size Groups Display */
        .size-group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 25px;
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.08), rgba(61, 255, 192, 0.08));
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .size-group-name {
            font-weight: 700;
            font-size: 14px;
            color: var(--pink-dark);
        }
        .size-group-count {
            font-size: 12px;
            color: var(--gray);
        }
        .size-group-items {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 15px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        .size-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: white;
            border: 2px solid rgba(0,0,0,0.08);
            border-radius: var(--radius-full);
            transition: all 0.2s;
        }
        .size-item:hover {
            border-color: var(--pink-light);
            box-shadow: var(--shadow-sm);
        }
        .size-item.inactive {
            opacity: 0.5;
            background: var(--gray-light);
        }
        .size-value {
            font-weight: 600;
            font-size: 14px;
            color: var(--black-soft);
        }
        .size-actions {
            display: flex;
            gap: 4px;
        }
        .action-btn-mini {
            width: 26px;
            height: 26px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 11px;
            background: var(--gray-light);
        }
        .action-btn-mini.toggle.active {
            background: var(--mint-main);
        }
        .action-btn-mini.edit:hover {
            background: var(--pink-light);
        }
        .action-btn-mini.delete {
            color: #dc3545;
        }
        .action-btn-mini.delete:hover {
            background: #fee;
        }

        @media (max-width: 968px) {
            .options-grid { grid-template-columns: 1fr; }
            .form-card { position: static; order: -1; }
            .type-tabs { gap: 8px; }
            .type-tab { padding: 10px 14px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar-alt.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <h1 class="page-title">Options de <span class="text-gradient">Personnalisation</span></h1>

            <!-- Type Tabs -->
            <div class="type-tabs">
                <?php foreach ($typeLabels as $type => $info): ?>
                    <a href="?type=<?= $type ?>" class="type-tab <?= $currentType === $type ? 'active' : '' ?>">
                        <span class="tab-icon"><?= $info['icon'] ?></span>
                        <?= $info['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="page-desc">
                <?= $typeLabels[$currentType]['icon'] ?> <?= $typeLabels[$currentType]['desc'] ?>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="options-grid">
                <!-- Options List -->
                <div class="options-list">
                    <div class="list-header">
                        <span><?= $typeLabels[$currentType]['icon'] ?> <?= $typeLabels[$currentType]['label'] ?></span>
                        <span style="font-size: 13px; color: var(--gray); font-weight: 400;">
                            <?= count($options) ?> option<?= count($options) > 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <?php if (empty($options)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= $typeLabels[$currentType]['icon'] ?></div>
                            <p>Aucune option. Ajoutez-en une !</p>
                        </div>
                    <?php elseif ($isSizeType): ?>
                        <?php
                        // Grouper les tailles par size_group
                        $groupedSizes = [];
                        foreach ($options as $opt) {
                            $group = $opt['size_group'] ?? 'Non classé';
                            if (!isset($groupedSizes[$group])) {
                                $groupedSizes[$group] = [];
                            }
                            $groupedSizes[$group][] = $opt;
                        }
                        ?>
                        <?php foreach ($groupedSizes as $groupName => $groupOptions): ?>
                            <div class="size-group-header">
                                <span class="size-group-name"><?= h($groupName) ?></span>
                                <span class="size-group-count"><?= count($groupOptions) ?> taille<?= count($groupOptions) > 1 ? 's' : '' ?></span>
                            </div>
                            <div class="size-group-items">
                                <?php foreach ($groupOptions as $option): ?>
                                    <div class="size-item <?= $option['active'] ? '' : 'inactive' ?>">
                                        <span class="size-value"><?= h($option['label']) ?></span>
                                        <div class="size-actions">
                                            <form method="post" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="option_id" value="<?= $option['id'] ?>">
                                                <button type="submit" name="toggle_option" value="1"
                                                        class="action-btn-mini toggle <?= $option['active'] ? 'active' : '' ?>"
                                                        title="<?= $option['active'] ? 'Désactiver' : 'Activer' ?>">
                                                    <?= $option['active'] ? '✓' : '○' ?>
                                                </button>
                                            </form>
                                            <button type="button" class="action-btn-mini edit" title="Modifier"
                                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($option)) ?>)">
                                                ✏️
                                            </button>
                                            <form method="post" style="display: inline;"
                                                  onsubmit="return confirm('Supprimer cette taille ?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="option_id" value="<?= $option['id'] ?>">
                                                <button type="submit" name="delete_option" value="1"
                                                        class="action-btn-mini delete" title="Supprimer">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($options as $option): ?>
                            <div class="option-item <?= $option['active'] ? '' : 'inactive' ?>">
                                <?php if ($isColorType && $option['hex_code']): ?>
                                    <div class="color-preview" style="background-color: <?= h($option['hex_code']) ?>;"></div>
                                <?php elseif ($isTechniqueType): ?>
                                    <div class="technique-icon">🧵</div>
                                <?php endif; ?>

                                <div class="option-info">
                                    <div class="option-value"><?= h($option['label']) ?></div>
                                    <div class="option-label">
                                        <code style="font-size: 11px;"><?= h($option['value']) ?></code>
                                        <?php if ($isColorType && $option['hex_code']): ?>
                                            <span style="margin-left: 8px;"><?= h($option['hex_code']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isTechniqueType && !empty($option['description'])): ?>
                                        <div class="option-desc"><?= h($option['description']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isTechniqueType && $option['price'] !== null): ?>
                                    <div class="option-price">+<?= number_format($option['price'], 2, ',', ' ') ?> €</div>
                                <?php endif; ?>

                                <div class="option-actions">
                                    <form method="post" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="option_id" value="<?= $option['id'] ?>">
                                        <button type="submit" name="toggle_option" value="1"
                                                class="action-btn toggle <?= $option['active'] ? 'active' : '' ?>"
                                                title="<?= $option['active'] ? 'Désactiver' : 'Activer' ?>">
                                            <?= $option['active'] ? '✓' : '○' ?>
                                        </button>
                                    </form>
                                    <button type="button" class="action-btn edit" title="Modifier"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($option)) ?>)">
                                        ✏️
                                    </button>
                                    <form method="post" style="display: inline;"
                                          onsubmit="return confirm('Supprimer cette option ?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="option_id" value="<?= $option['id'] ?>">
                                        <button type="submit" name="delete_option" value="1"
                                                class="action-btn delete" title="Supprimer">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <?php if ($isTechniqueType):
                                $techniqueImages = $optionModel->getImages($option['id']);
                            ?>
                            <div class="technique-images-section">
                                <div class="technique-images-header">
                                    <h4>📸 Photos de rendu réel</h4>
                                    <span><?= count($techniqueImages) ?>/3 images</span>
                                </div>
                                <div class="technique-images-grid">
                                    <?php foreach ($techniqueImages as $idx => $imgUrl): ?>
                                        <div class="technique-image-item">
                                            <img src="/public<?= h($imgUrl) ?>" alt="Rendu <?= $option['label'] ?>">
                                            <form method="post" style="display: contents;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="technique_id" value="<?= $option['id'] ?>">
                                                <input type="hidden" name="image_index" value="<?= $idx ?>">
                                                <button type="submit" name="delete_technique_image" value="1"
                                                        class="technique-image-delete" title="Supprimer">×</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (count($techniqueImages) < 3): ?>
                                        <label class="technique-image-add" id="addBtn_<?= $option['id'] ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                            </svg>
                                            <span>Ajouter</span>
                                            <form method="post" enctype="multipart/form-data" id="uploadForm_<?= $option['id'] ?>" style="display: none;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="technique_id" value="<?= $option['id'] ?>">
                                                <input type="file" name="technique_image" accept="image/jpeg,image/png,image/webp"
                                                       onchange="showUploadLoading(<?= $option['id'] ?>); this.form.submit();">
                                                <input type="hidden" name="upload_technique_image" value="1">
                                            </form>
                                            <input type="file" style="display: none;" accept="image/jpeg,image/png,image/webp"
                                                   onchange="document.getElementById('uploadForm_<?= $option['id'] ?>').querySelector('input[type=file]').files = this.files; showUploadLoading(<?= $option['id'] ?>); document.getElementById('uploadForm_<?= $option['id'] ?>').submit();">
                                        </label>
                                    <?php endif; ?>

                                    <?php if (empty($techniqueImages)): ?>
                                        <span class="technique-images-empty">Ajoutez des photos macro du rendu réel de cette technique</span>
                                    <?php endif; ?>
                                </div>
                                <div class="technique-images-info">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                                    </svg>
                                    <span>Les images sont enregistrées automatiquement après sélection</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Add Form -->
                <div class="form-card">
                    <h3>Ajouter <?= $isTechniqueType ? 'une technique' : ($isSizeType ? 'une taille' : 'une option') ?></h3>

                    <form method="post">
                        <?= csrfField() ?>

                        <?php if ($isSizeType): ?>
                            <div class="form-group">
                                <label class="form-label">Groupe de taille *</label>
                                <select name="size_group" class="form-input" required>
                                    <option value="">-- Sélectionner un groupe --</option>
                                    <?php foreach ($sizeGroups as $group): ?>
                                        <option value="<?= h($group) ?>"><?= h($group) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-hint">Lettres (S,M,L...), Chiffres (36,38...), Enfants (2ans...), Personnalisé</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Taille *</label>
                                <input type="text" name="value" class="form-input"
                                       placeholder="Ex: 3XL, 50, 14 ans..."
                                       required>
                                <input type="hidden" name="label" id="sizeLabelHidden">
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label class="form-label">Valeur (code interne)</label>
                                <input type="text" name="value" class="form-input"
                                       placeholder="<?= $isColorType ? 'rouge' : 'broderie' ?>"
                                       required>
                                <div class="form-hint">Identifiant unique, sans espaces ni accents</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Libellé (affiché au client)</label>
                                <input type="text" name="label" class="form-input"
                                       placeholder="<?= $isColorType ? 'Rouge Passion' : 'Broderie Premium' ?>"
                                       required>
                            </div>
                        <?php endif; ?>

                        <?php if ($isColorType): ?>
                            <div class="form-group">
                                <label class="form-label">Couleur</label>
                                <div class="color-input-group">
                                    <input type="color" name="hex_color_picker" value="#FF69B4"
                                           onchange="document.querySelector('[name=hex_code]').value = this.value">
                                    <input type="text" name="hex_code" class="form-input"
                                           placeholder="#FF69B4" pattern="^#[0-9A-Fa-f]{6}$">
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($isTechniqueType): ?>
                            <div class="form-group">
                                <label class="form-label">Prix additionnel</label>
                                <div class="price-input-group">
                                    <input type="number" name="price" class="form-input"
                                           placeholder="5.00" step="0.01" min="0">
                                    <span class="currency">€</span>
                                </div>
                                <div class="form-hint">Prix ajouté au produit de base</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-textarea"
                                          placeholder="Description de la technique, avantages, conseils d'utilisation..."></textarea>
                            </div>
                        <?php endif; ?>

                        <button type="submit" name="add_option" value="1" class="btn btn-primary" style="width: 100%;">
                            Ajouter
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <h3>Modifier <?= $isSizeType ? 'la taille' : 'l\'option' ?></h3>
            <form method="post" id="editForm">
                <?= csrfField() ?>
                <input type="hidden" name="option_id" id="editOptionId">

                <?php if ($isSizeType): ?>
                    <div class="form-group">
                        <label class="form-label">Groupe</label>
                        <select name="size_group" id="editSizeGroup" class="form-input" required>
                            <?php foreach ($sizeGroups as $group): ?>
                                <option value="<?= h($group) ?>"><?= h($group) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Taille</label>
                        <input type="text" name="value" id="editValue" class="form-input" required>
                        <input type="hidden" name="label" id="editLabel">
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">Valeur</label>
                        <input type="text" name="value" id="editValue" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Libellé</label>
                        <input type="text" name="label" id="editLabel" class="form-input" required>
                    </div>
                <?php endif; ?>

                <?php if ($isColorType): ?>
                    <div class="form-group">
                        <label class="form-label">Couleur</label>
                        <div class="color-input-group">
                            <input type="color" id="editColorPicker" value="#FF69B4"
                                   onchange="document.getElementById('editHexCode').value = this.value">
                            <input type="text" name="hex_code" id="editHexCode" class="form-input" placeholder="#FF69B4">
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($isTechniqueType): ?>
                    <div class="form-group">
                        <label class="form-label">Prix additionnel</label>
                        <div class="price-input-group">
                            <input type="number" name="price" id="editPrice" class="form-input"
                                   placeholder="5.00" step="0.01" min="0">
                            <span class="currency">€</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDescription" class="form-textarea"></textarea>
                    </div>
                <?php endif; ?>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Annuler</button>
                    <button type="submit" name="edit_option" value="1" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(option) {
            document.getElementById('editOptionId').value = option.id;
            document.getElementById('editValue').value = option.value;

            const editLabel = document.getElementById('editLabel');
            if (editLabel) {
                editLabel.value = option.label;
            }

            // Size group field
            const sizeGroupField = document.getElementById('editSizeGroup');
            if (sizeGroupField && option.size_group) {
                sizeGroupField.value = option.size_group;
            }

            // Color fields
            const hexCode = document.getElementById('editHexCode');
            const colorPicker = document.getElementById('editColorPicker');
            if (hexCode && option.hex_code) {
                hexCode.value = option.hex_code;
                if (colorPicker) colorPicker.value = option.hex_code;
            }

            // Technique fields
            const priceField = document.getElementById('editPrice');
            const descField = document.getElementById('editDescription');
            if (priceField) {
                priceField.value = option.price || '';
            }
            if (descField) {
                descField.value = option.description || '';
            }

            document.getElementById('editModal').classList.add('active');
        }

        // For sizes in edit modal: sync label with value
        <?php if ($isSizeType): ?>
        document.getElementById('editValue')?.addEventListener('input', function() {
            const editLabel = document.getElementById('editLabel');
            if (editLabel) {
                editLabel.value = this.value;
            }
        });
        <?php endif; ?>

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });

        // Loading state pour upload images techniques
        function showUploadLoading(optionId) {
            const btn = document.getElementById('addBtn_' + optionId);
            if (btn) {
                btn.classList.add('loading');
            }
        }

        // Auto-fill label for sizes (label = value for sizes)
        <?php if ($isSizeType): ?>
        document.querySelector('input[name="value"]')?.addEventListener('input', function() {
            const hiddenLabel = document.getElementById('sizeLabelHidden');
            if (hiddenLabel) {
                hiddenLabel.value = this.value;
            }
        });

        // Before submit, ensure label is set
        document.querySelector('.form-card form')?.addEventListener('submit', function(e) {
            const valueInput = this.querySelector('input[name="value"]');
            const hiddenLabel = document.getElementById('sizeLabelHidden');
            if (hiddenLabel && valueInput) {
                hiddenLabel.value = valueInput.value;
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
