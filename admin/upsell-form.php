<?php
/**
 * PERSONNALY Admin - Formulaire Upsell
 * Création et édition d'upsells
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Upsell.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/CustomizationOption.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$upsellModel = new Upsell();
$productModel = new Product();
$categoryModel = new Category();
$optionModel = new CustomizationOption();
$orderModel = new Order();
$pendingOrders = $orderModel->countNew();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$upsell = $id ? $upsellModel->findById($id) : null;
$isEdit = $upsell !== null;

// Données pour les selects
$products = $productModel->findAll(true); // actifs seulement
$categories = $categoryModel->findAll();
$techniques = $optionModel->getTechniques();

$errors = [];
$success = false;

// Traitement du formulaire
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'condition_type' => $_POST['condition_type'] ?? 'panier_min',
        'condition_value' => trim($_POST['condition_value'] ?? ''),
        'offer_type' => $_POST['offer_type'] ?? 'reduction',
        'offer_value' => trim($_POST['offer_value'] ?? ''),
        'offer_label' => trim($_POST['offer_label'] ?? ''),
        'discount_type' => $_POST['discount_type'] ?? 'aucun',
        'discount_value' => (float) ($_POST['discount_value'] ?? 0),
        'display_title' => trim($_POST['display_title'] ?? ''),
        'display_position' => $_POST['display_position'] ?? 'both',
        'priority' => (int) ($_POST['priority'] ?? 0),
        'max_uses' => !empty($_POST['max_uses']) ? (int) $_POST['max_uses'] : null,
        'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
        'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
        'active' => isset($_POST['active']) ? 1 : 0,
    ];

    // Validation
    if (empty($data['name'])) {
        $errors[] = 'Le nom est requis';
    }
    if (empty($data['condition_value'])) {
        $errors[] = 'La valeur de condition est requise';
    }

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $upsellModel->update($id, $data);
            } else {
                $id = $upsellModel->create($data);
            }
            redirect('/admin/upsells.php?success=1');
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
        }
    }

    // Recharger les données
    if (!$isEdit && $id) {
        $upsell = $upsellModel->findById($id);
        $isEdit = true;
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Modifier' : 'Nouvel' ?> Upsell - PERSONNALY Admin</title>
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
                <div style="display: flex; align-items: center; gap: 16px;">
                    <a href="/admin/upsells.php" class="btn-back">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <h1 class="page-title"><?= $isEdit ? 'Modifier l\'' : 'Nouvel ' ?><span>Upsell</span></h1>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="upsell-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-grid">
                    <!-- Colonne principale -->
                    <div class="form-main">
                        <!-- Informations de base -->
                        <div class="form-card">
                            <h3 class="form-card-title">Informations générales</h3>

                            <div class="form-group">
                                <label for="name">Nom interne <span class="required">*</span></label>
                                <input type="text" id="name" name="name"
                                       value="<?= h($upsell['name'] ?? '') ?>"
                                       placeholder="Ex: Promo livraison gratuite 80€"
                                       required>
                                <small class="form-help">Visible uniquement dans l'admin</small>
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="2"
                                          placeholder="Notes internes..."><?= h($upsell['description'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- Condition -->
                        <div class="form-card">
                            <h3 class="form-card-title">
                                <span class="step-badge">SI</span>
                                Condition de déclenchement
                            </h3>

                            <div class="form-row">
                                <div class="form-group" style="flex: 1;">
                                    <label for="condition_type">Type de condition</label>
                                    <select id="condition_type" name="condition_type" onchange="updateConditionUI()">
                                        <?php foreach (Upsell::CONDITION_TYPES as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= ($upsell['condition_type'] ?? 'panier_min') === $key ? 'selected' : '' ?>>
                                                <?= h($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" style="flex: 1;" id="condition-value-group">
                                    <label for="condition_value">Valeur <span class="required">*</span></label>
                                    <div class="input-with-suffix">
                                        <input type="text" id="condition_value" name="condition_value"
                                               value="<?= h($upsell['condition_value'] ?? '') ?>"
                                               placeholder="50" required>
                                        <span class="input-suffix" id="condition-suffix">€</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Select dynamique pour produit/catégorie/technique -->
                            <div class="form-group" id="condition-select-group" style="display: none;">
                                <label id="condition-select-label">Sélectionner</label>
                                <select id="condition_value_select" onchange="document.getElementById('condition_value').value = this.value">
                                    <option value="">-- Choisir --</option>
                                </select>
                            </div>
                        </div>

                        <!-- Offre -->
                        <div class="form-card">
                            <h3 class="form-card-title">
                                <span class="step-badge step-then">ALORS</span>
                                Offre proposée
                            </h3>

                            <div class="form-group">
                                <label for="offer_type">Type d'offre</label>
                                <select id="offer_type" name="offer_type" onchange="updateOfferUI()">
                                    <?php foreach (Upsell::OFFER_TYPES as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= ($upsell['offer_type'] ?? 'reduction') === $key ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="offer-value-group">
                                <label for="offer_value">Produit/Option à proposer</label>
                                <select id="offer_value" name="offer_value">
                                    <option value="">-- Aucun --</option>
                                    <?php foreach ($products as $prod): ?>
                                        <option value="<?= $prod['id'] ?>" <?= ($upsell['offer_value'] ?? '') == $prod['id'] ? 'selected' : '' ?>>
                                            <?= h($prod['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row" id="discount-group">
                                <div class="form-group" style="flex: 1;">
                                    <label for="discount_type">Type de réduction</label>
                                    <select id="discount_type" name="discount_type">
                                        <?php foreach (Upsell::DISCOUNT_TYPES as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= ($upsell['discount_type'] ?? 'aucun') === $key ? 'selected' : '' ?>>
                                                <?= h($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="discount_value">Montant</label>
                                    <div class="input-with-suffix">
                                        <input type="number" id="discount_value" name="discount_value"
                                               value="<?= h($upsell['discount_value'] ?? 0) ?>"
                                               min="0" step="0.01">
                                        <span class="input-suffix" id="discount-suffix">%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="offer_label">Message de l'offre</label>
                                <input type="text" id="offer_label" name="offer_label"
                                       value="<?= h($upsell['offer_label'] ?? '') ?>"
                                       placeholder="Ex: -15% sur votre commande !">
                                <small class="form-help">Texte court affiché au client</small>
                            </div>
                        </div>

                        <!-- Affichage -->
                        <div class="form-card">
                            <h3 class="form-card-title">Affichage client</h3>

                            <div class="form-group">
                                <label for="display_title">Titre affiché</label>
                                <input type="text" id="display_title" name="display_title"
                                       value="<?= h($upsell['display_title'] ?? '') ?>"
                                       placeholder="Ex: Offre spéciale pour vous !">
                            </div>

                            <div class="form-group">
                                <label for="display_position">Où afficher ?</label>
                                <select id="display_position" name="display_position">
                                    <?php foreach (Upsell::DISPLAY_POSITIONS as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= ($upsell['display_position'] ?? 'both') === $key ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne latérale -->
                    <div class="form-sidebar">
                        <!-- Statut et publication -->
                        <div class="form-card">
                            <h3 class="form-card-title">Publication</h3>

                            <div class="form-group">
                                <label class="toggle-label">
                                    <input type="checkbox" name="active" value="1"
                                           <?= ($upsell['active'] ?? 1) ? 'checked' : '' ?>>
                                    <span class="toggle-switch"></span>
                                    <span class="toggle-text">Actif</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label for="priority">Priorité</label>
                                <input type="number" id="priority" name="priority"
                                       value="<?= h($upsell['priority'] ?? 0) ?>"
                                       min="0" max="999">
                                <small class="form-help">Plus élevé = affiché en premier</small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                    <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                                </svg>
                                <?= $isEdit ? 'Enregistrer' : 'Créer l\'upsell' ?>
                            </button>
                        </div>

                        <!-- Période de validité -->
                        <div class="form-card">
                            <h3 class="form-card-title">Période de validité</h3>

                            <div class="form-group">
                                <label for="start_date">Date de début</label>
                                <input type="date" id="start_date" name="start_date"
                                       value="<?= h($upsell['start_date'] ?? '') ?>">
                                <small class="form-help">Laisser vide = immédiat</small>
                            </div>

                            <div class="form-group">
                                <label for="end_date">Date de fin</label>
                                <input type="date" id="end_date" name="end_date"
                                       value="<?= h($upsell['end_date'] ?? '') ?>">
                                <small class="form-help">Laisser vide = illimité</small>
                            </div>
                        </div>

                        <!-- Limite d'utilisation -->
                        <div class="form-card">
                            <h3 class="form-card-title">Limite d'utilisation</h3>

                            <div class="form-group">
                                <label for="max_uses">Nombre max d'utilisations</label>
                                <input type="number" id="max_uses" name="max_uses"
                                       value="<?= h($upsell['max_uses'] ?? '') ?>"
                                       min="0" placeholder="Illimité">
                                <small class="form-help">Laisser vide = illimité</small>
                            </div>

                            <?php if ($isEdit): ?>
                                <div class="stat-box">
                                    <span class="stat-value"><?= (int) ($upsell['current_uses'] ?? 0) ?></span>
                                    <span class="stat-label">utilisations actuelles</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
    // Données pour les selects dynamiques
    const products = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $products)) ?>;
    const categories = <?= json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $categories)) ?>;
    const techniques = <?= json_encode(array_map(fn($t) => ['value' => $t['value'], 'label' => $t['label']], $techniques)) ?>;

    function updateConditionUI() {
        const type = document.getElementById('condition_type').value;
        const valueInput = document.getElementById('condition_value');
        const suffix = document.getElementById('condition-suffix');
        const selectGroup = document.getElementById('condition-select-group');
        const selectEl = document.getElementById('condition_value_select');
        const selectLabel = document.getElementById('condition-select-label');

        // Reset
        selectGroup.style.display = 'none';
        valueInput.type = 'text';

        switch (type) {
            case 'panier_min':
                suffix.textContent = '€';
                valueInput.placeholder = '50';
                valueInput.type = 'number';
                break;
            case 'quantite_min':
                suffix.textContent = 'articles';
                valueInput.placeholder = '3';
                valueInput.type = 'number';
                break;
            case 'produit_specifique':
                suffix.textContent = 'ID';
                selectGroup.style.display = 'block';
                selectLabel.textContent = 'Ou sélectionner un produit';
                populateSelect(selectEl, products, 'id', 'name');
                break;
            case 'categorie':
                suffix.textContent = 'ID';
                selectGroup.style.display = 'block';
                selectLabel.textContent = 'Ou sélectionner une catégorie';
                populateSelect(selectEl, categories, 'id', 'name');
                break;
            case 'technique_specifique':
                suffix.textContent = '';
                selectGroup.style.display = 'block';
                selectLabel.textContent = 'Ou sélectionner une technique';
                populateSelect(selectEl, techniques, 'value', 'label');
                break;
        }
    }

    function populateSelect(selectEl, data, valueKey, labelKey) {
        selectEl.innerHTML = '<option value="">-- Choisir --</option>';
        data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[labelKey];
            selectEl.appendChild(opt);
        });
    }

    function updateOfferUI() {
        const type = document.getElementById('offer_type').value;
        const offerValueGroup = document.getElementById('offer-value-group');
        const discountGroup = document.getElementById('discount-group');
        const discountSuffix = document.getElementById('discount-suffix');

        switch (type) {
            case 'reduction':
                offerValueGroup.style.display = 'none';
                discountGroup.style.display = 'flex';
                break;
            case 'livraison_gratuite':
                offerValueGroup.style.display = 'none';
                discountGroup.style.display = 'none';
                break;
            case 'produit':
            case 'option':
                offerValueGroup.style.display = 'block';
                discountGroup.style.display = 'flex';
                break;
        }

        // Mise à jour du suffix selon le type de réduction
        document.getElementById('discount_type').addEventListener('change', function() {
            discountSuffix.textContent = this.value === 'pourcentage' ? '%' : '€';
        });
    }

    // Init
    document.addEventListener('DOMContentLoaded', function() {
        updateConditionUI();
        updateOfferUI();

        // Écouteur pour le type de réduction
        document.getElementById('discount_type').addEventListener('change', function() {
            document.getElementById('discount-suffix').textContent = this.value === 'pourcentage' ? '%' : '€';
        });
    });
    </script>

    <style>
        .btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--gray-light);
            border-radius: 12px;
            color: var(--black-soft);
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: var(--pink-light);
            color: var(--pink-dark);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .form-card-title {
            margin: 0 0 20px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--black-soft);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 10px;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
            color: white;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .step-badge.step-then {
            background: linear-gradient(135deg, var(--mint-main) 0%, var(--mint-dark) 100%);
            color: var(--black-soft);
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group:last-child {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
        }
        .required {
            color: var(--pink-main);
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--gray-light);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.1);
        }

        .form-help {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: var(--gray);
        }

        .form-row {
            display: flex;
            gap: 16px;
        }

        .input-with-suffix {
            position: relative;
            display: flex;
        }
        .input-with-suffix input {
            padding-right: 50px;
        }
        .input-suffix {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            font-weight: 600;
            color: var(--gray);
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }
        .toggle-label input {
            display: none;
        }
        .toggle-switch {
            width: 48px;
            height: 26px;
            background: var(--gray-light);
            border-radius: 13px;
            position: relative;
            transition: all 0.3s;
        }
        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        .toggle-label input:checked + .toggle-switch {
            background: linear-gradient(135deg, var(--mint-main) 0%, var(--mint-dark) 100%);
        }
        .toggle-label input:checked + .toggle-switch::after {
            left: 25px;
        }
        .toggle-text {
            font-size: 14px;
            font-weight: 500;
            color: var(--black-soft);
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        .stat-box {
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.08) 0%, rgba(61, 255, 192, 0.08) 100%);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }
        .stat-value {
            display: block;
            font-size: 32px;
            font-weight: 700;
            color: var(--pink-dark);
        }
        .stat-label {
            font-size: 12px;
            color: var(--gray);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #DC2626;
        }
    </style>
</body>
</html>
