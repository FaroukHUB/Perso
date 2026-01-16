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
$currentType = get('type', 'size');
if (!in_array($currentType, ['size', 'color', 'text_color', 'technique'])) {
    $currentType = 'size';
}

$typeLabels = [
    'size' => ['label' => 'Tailles', 'icon' => '📏', 'desc' => 'Tailles disponibles pour les produits'],
    'color' => ['label' => 'Couleurs Produit', 'icon' => '👕', 'desc' => 'Couleurs des vêtements'],
    'text_color' => ['label' => 'Couleurs Texte', 'icon' => '🎨', 'desc' => 'Couleurs pour la personnalisation'],
    'technique' => ['label' => 'Techniques', 'icon' => '🧵', 'desc' => 'Méthodes de personnalisation avec tarifs'],
];

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

        if (empty($value) || empty($label)) {
            $error = 'Valeur et libellé sont obligatoires.';
        } else {
            $optionModel->create([
                'type' => $currentType,
                'value' => $value,
                'label' => $label,
                'hex_code' => in_array($currentType, ['color', 'text_color']) ? $hexCode : null,
                'price' => $currentType === 'technique' && $price !== '' ? (float) $price : null,
                'description' => $currentType === 'technique' ? $description : null,
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

        if ($id && !empty($value) && !empty($label)) {
            $optionModel->update($id, [
                'value' => $value,
                'label' => $label,
                'hex_code' => in_array($currentType, ['color', 'text_color']) ? $hexCode : null,
                'price' => $currentType === 'technique' && $price !== '' ? (float) $price : null,
                'description' => $currentType === 'technique' ? $description : null,
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
}

// Récupérer les options du type actuel
$options = $optionModel->findAllByType($currentType);
$isColorType = in_array($currentType, ['color', 'text_color']);
$isTechniqueType = $currentType === 'technique';
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
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Add Form -->
                <div class="form-card">
                    <h3>Ajouter <?= $currentType === 'technique' ? 'une technique' : 'une option' ?></h3>

                    <form method="post">
                        <?= csrfField() ?>

                        <div class="form-group">
                            <label class="form-label">Valeur (code interne)</label>
                            <input type="text" name="value" class="form-input"
                                   placeholder="<?= $currentType === 'size' ? 'XXL' : ($isColorType ? 'rouge' : 'broderie') ?>"
                                   required>
                            <div class="form-hint">Identifiant unique, sans espaces ni accents</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Libellé (affiché au client)</label>
                            <input type="text" name="label" class="form-input"
                                   placeholder="<?= $currentType === 'size' ? 'XXL' : ($isColorType ? 'Rouge Passion' : 'Broderie Premium') ?>"
                                   required>
                        </div>

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
            <h3>Modifier l'option</h3>
            <form method="post" id="editForm">
                <?= csrfField() ?>
                <input type="hidden" name="option_id" id="editOptionId">

                <div class="form-group">
                    <label class="form-label">Valeur</label>
                    <input type="text" name="value" id="editValue" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Libellé</label>
                    <input type="text" name="label" id="editLabel" class="form-input" required>
                </div>

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
            document.getElementById('editLabel').value = option.label;

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

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
    </script>
</body>
</html>
