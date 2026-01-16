<?php
/**
 * PERSONNALY - Admin : Gestion Options de Personnalisation
 * Tailles, Couleurs, Positions
 */

require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/CustomizationOption.php';

Auth::requireAdmin();

$optionModel = new CustomizationOption();

// Type d'option actuel (size, color, position)
$currentType = get('type', 'size');
if (!in_array($currentType, ['size', 'color', 'position'])) {
    $currentType = 'size';
}

$typeLabels = [
    'size' => ['label' => 'Tailles', 'icon' => '📏'],
    'color' => ['label' => 'Couleurs', 'icon' => '🎨'],
    'position' => ['label' => 'Positions', 'icon' => '📍'],
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

        if (empty($value) || empty($label)) {
            $error = 'Valeur et libellé sont obligatoires.';
        } else {
            $optionModel->create([
                'type' => $currentType,
                'value' => $value,
                'label' => $label,
                'hex_code' => $currentType === 'color' ? $hexCode : null,
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

        if ($id && !empty($value) && !empty($label)) {
            $optionModel->update($id, [
                'value' => $value,
                'label' => $label,
                'hex_code' => $currentType === 'color' ? $hexCode : null,
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
        }
        .type-tab {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: white;
            border-radius: var(--radius-full);
            text-decoration: none;
            color: var(--gray);
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: var(--shadow-sm);
        }
        .type-tab:hover {
            color: var(--pink-main);
        }
        .type-tab.active {
            background: var(--gradient-pink);
            color: white;
            box-shadow: var(--shadow-pink);
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
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
        .option-info {
            flex: 1;
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
            max-width: 450px;
            margin: 20px;
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
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="/admin/dashboard.php" class="admin-logo">PERSONNALY</a>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/dashboard.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Dashboard
                </a>
                <a href="/admin/products.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    Produits
                </a>
                <a href="/admin/orders.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    Commandes
                </a>
                <a href="/admin/customers.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    Clients
                </a>
                <a href="/admin/options.php" class="nav-item active">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Options
                </a>
                <a href="/admin/settings.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    Paramètres
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="/admin/logout.php" class="nav-item logout">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Déconnexion
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <h1 class="page-title">Options de <span class="text-gradient">Personnalisation</span></h1>

            <!-- Type Tabs -->
            <div class="type-tabs">
                <?php foreach ($typeLabels as $type => $info): ?>
                    <a href="?type=<?= $type ?>" class="type-tab <?= $currentType === $type ? 'active' : '' ?>">
                        <?= $info['icon'] ?> <?= $info['label'] ?>
                    </a>
                <?php endforeach; ?>
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
                                <?php if ($currentType === 'color' && $option['hex_code']): ?>
                                    <div class="color-preview" style="background-color: <?= h($option['hex_code']) ?>;"></div>
                                <?php endif; ?>
                                <div class="option-info">
                                    <div class="option-value"><?= h($option['value']) ?></div>
                                    <div class="option-label">
                                        <?= h($option['label']) ?>
                                        <?php if ($currentType === 'color' && $option['hex_code']): ?>
                                            <code style="font-size: 11px; color: var(--gray);"><?= h($option['hex_code']) ?></code>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
                    <h3>Ajouter une <?= strtolower($typeLabels[$currentType]['label']) ?></h3>

                    <form method="post">
                        <?= csrfField() ?>

                        <div class="form-group">
                            <label class="form-label">Valeur (code interne)</label>
                            <input type="text" name="value" class="form-input"
                                   placeholder="<?= $currentType === 'size' ? 'XXL' : ($currentType === 'color' ? 'rouge' : 'haut') ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Libellé (affiché au client)</label>
                            <input type="text" name="label" class="form-input"
                                   placeholder="<?= $currentType === 'size' ? 'XXL' : ($currentType === 'color' ? 'Rouge Passion' : 'En haut') ?>"
                                   required>
                        </div>

                        <?php if ($currentType === 'color'): ?>
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

                <?php if ($currentType === 'color'): ?>
                    <div class="form-group">
                        <label class="form-label">Couleur</label>
                        <div class="color-input-group">
                            <input type="color" id="editColorPicker" value="#FF69B4"
                                   onchange="document.getElementById('editHexCode').value = this.value">
                            <input type="text" name="hex_code" id="editHexCode" class="form-input" placeholder="#FF69B4">
                        </div>
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

            const hexCode = document.getElementById('editHexCode');
            const colorPicker = document.getElementById('editColorPicker');
            if (hexCode && option.hex_code) {
                hexCode.value = option.hex_code;
                if (colorPicker) colorPicker.value = option.hex_code;
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
