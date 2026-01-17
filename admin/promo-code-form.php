<?php
/**
 * PERSONNALY Admin - Formulaire Code Promo
 * Création et modification de codes promotionnels
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/PromoCode.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$promoModel = new PromoCode();
$orderModel = new Order();
$pendingOrders = $orderModel->countNew();

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$promo = null;
$errors = [];

if ($isEdit) {
    $promo = $promoModel->findById($id);
    if (!$promo) {
        redirect('/admin/promo-codes.php');
    }
}

// Traitement du formulaire
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $data = [
        'code' => trim($_POST['code'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'discount_type' => $_POST['discount_type'] ?? 'percentage',
        'discount_value' => floatval($_POST['discount_value'] ?? 0),
        'min_order_amount' => !empty($_POST['min_order_amount']) ? floatval($_POST['min_order_amount']) : null,
        'max_discount' => !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null,
        'max_uses' => !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null,
        'max_uses_per_customer' => intval($_POST['max_uses_per_customer'] ?? 1),
        'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
        'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
        'active' => isset($_POST['active']) ? 1 : 0
    ];

    // Validation
    if (empty($data['code'])) {
        $errors[] = 'Le code est obligatoire';
    } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $data['code'])) {
        $errors[] = 'Le code ne peut contenir que des lettres, chiffres, tirets et underscores';
    } else {
        // Vérifier unicité du code
        $existing = $promoModel->findByCode($data['code']);
        if ($existing && (!$isEdit || $existing['id'] != $id)) {
            $errors[] = 'Ce code existe déjà';
        }
    }

    if (empty($data['name'])) {
        $errors[] = 'Le nom est obligatoire';
    }

    if ($data['discount_type'] !== 'free_shipping' && $data['discount_value'] <= 0) {
        $errors[] = 'La valeur de réduction doit être supérieure à 0';
    }

    if ($data['discount_type'] === 'percentage' && $data['discount_value'] > 100) {
        $errors[] = 'Le pourcentage ne peut pas dépasser 100%';
    }

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $promoModel->update($id, $data);
            } else {
                $promoModel->create($data);
            }
            redirect('/admin/promo-codes.php?saved=1');
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de l\'enregistrement';
        }
    }

    // Conserver les données saisies
    $promo = $data;
    $promo['id'] = $id;
}

$csrf = csrfToken();
$discountTypes = PromoCode::DISCOUNT_TYPES;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Modifier' : 'Nouveau' ?> Code Promo - PERSONNALY Admin</title>
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
                <div>
                    <a href="/admin/promo-codes.php" class="back-link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Retour aux codes promo
                    </a>
                    <h1 class="page-title"><?= $isEdit ? 'Modifier le' : 'Nouveau' ?> <span>Code Promo</span></h1>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="promo-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-layout">
                    <!-- Colonne principale -->
                    <div class="form-main">
                        <!-- Section Code -->
                        <div class="form-card">
                            <div class="card-header">
                                <h3>Code & Identification</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group code-input-group">
                                        <label>Code promo <span class="required">*</span></label>
                                        <div class="code-input-wrapper">
                                            <input type="text" name="code"
                                                   value="<?= h($promo['code'] ?? '') ?>"
                                                   placeholder="BIENVENUE20"
                                                   pattern="[A-Za-z0-9_-]+"
                                                   style="text-transform: uppercase;"
                                                   required>
                                            <button type="button" class="generate-btn" onclick="generateCode()">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/>
                                                </svg>
                                                Générer
                                            </button>
                                        </div>
                                        <p class="help-text">Ce que le client saisira sur la page panier</p>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Nom <span class="required">*</span></label>
                                    <input type="text" name="name"
                                           value="<?= h($promo['name'] ?? '') ?>"
                                           placeholder="Ex: Code bienvenue -20%"
                                           required>
                                    <p class="help-text">Nom pour identifier le code (visible admin seulement)</p>
                                </div>

                                <div class="form-group">
                                    <label>Description (optionnelle)</label>
                                    <textarea name="description" rows="2"
                                              placeholder="Notes internes sur ce code..."><?= h($promo['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Section Réduction -->
                        <div class="form-card">
                            <div class="card-header">
                                <h3>Type de réduction</h3>
                            </div>
                            <div class="card-body">
                                <div class="discount-types">
                                    <?php foreach ($discountTypes as $type => $label): ?>
                                        <label class="discount-type-option <?= ($promo['discount_type'] ?? 'percentage') === $type ? 'active' : '' ?>">
                                            <input type="radio" name="discount_type" value="<?= $type ?>"
                                                   <?= ($promo['discount_type'] ?? 'percentage') === $type ? 'checked' : '' ?>
                                                   onchange="toggleDiscountType()">
                                            <div class="option-content">
                                                <div class="option-icon">
                                                    <?php if ($type === 'percentage'): ?>
                                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <line x1="19" y1="5" x2="5" y2="19"/>
                                                            <circle cx="6.5" cy="6.5" r="2.5"/>
                                                            <circle cx="17.5" cy="17.5" r="2.5"/>
                                                        </svg>
                                                    <?php elseif ($type === 'fixed_amount'): ?>
                                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <rect x="1" y="3" width="15" height="13"/>
                                                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                                            <circle cx="5.5" cy="18.5" r="2.5"/>
                                                            <circle cx="18.5" cy="18.5" r="2.5"/>
                                                        </svg>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="option-label"><?= $label ?></span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div id="discount-value-section" class="form-row" style="margin-top: 24px; <?= ($promo['discount_type'] ?? '') === 'free_shipping' ? 'display:none;' : '' ?>">
                                    <div class="form-group">
                                        <label id="discount-value-label">
                                            <?= ($promo['discount_type'] ?? 'percentage') === 'percentage' ? 'Pourcentage de réduction' : 'Montant de réduction' ?>
                                            <span class="required">*</span>
                                        </label>
                                        <div class="input-with-suffix">
                                            <input type="number" name="discount_value" id="discount_value"
                                                   value="<?= h($promo['discount_value'] ?? '') ?>"
                                                   min="0" step="0.01" placeholder="20">
                                            <span class="suffix" id="discount-suffix">
                                                <?= ($promo['discount_type'] ?? 'percentage') === 'percentage' ? '%' : '€' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="form-group" id="max-discount-group" style="<?= ($promo['discount_type'] ?? 'percentage') !== 'percentage' ? 'display:none;' : '' ?>">
                                        <label>Réduction max (optionnel)</label>
                                        <div class="input-with-suffix">
                                            <input type="number" name="max_discount"
                                                   value="<?= h($promo['max_discount'] ?? '') ?>"
                                                   min="0" step="0.01" placeholder="50">
                                            <span class="suffix">€</span>
                                        </div>
                                        <p class="help-text">Plafond pour les grosses commandes</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section Conditions -->
                        <div class="form-card">
                            <div class="card-header">
                                <h3>Conditions d'utilisation</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Commande minimum</label>
                                        <div class="input-with-suffix">
                                            <input type="number" name="min_order_amount"
                                                   value="<?= h($promo['min_order_amount'] ?? '') ?>"
                                                   min="0" step="0.01" placeholder="30">
                                            <span class="suffix">€</span>
                                        </div>
                                        <p class="help-text">Montant minimum du panier</p>
                                    </div>

                                    <div class="form-group">
                                        <label>Limite d'utilisations totales</label>
                                        <input type="number" name="max_uses"
                                               value="<?= h($promo['max_uses'] ?? '') ?>"
                                               min="1" placeholder="Illimité">
                                        <p class="help-text">Laisser vide = illimité</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Utilisations max par client</label>
                                        <input type="number" name="max_uses_per_customer"
                                               value="<?= h($promo['max_uses_per_customer'] ?? 1) ?>"
                                               min="1" placeholder="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne latérale -->
                    <div class="form-sidebar">
                        <!-- Statut -->
                        <div class="form-card">
                            <div class="card-header">
                                <h3>Statut</h3>
                            </div>
                            <div class="card-body">
                                <label class="toggle-row">
                                    <span>Code actif</span>
                                    <input type="checkbox" name="active" value="1"
                                           <?= ($promo['active'] ?? 1) ? 'checked' : '' ?>>
                                    <span class="toggle"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Période de validité -->
                        <div class="form-card">
                            <div class="card-header">
                                <h3>Période de validité</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Date de début</label>
                                    <input type="date" name="start_date"
                                           value="<?= h($promo['start_date'] ?? '') ?>">
                                    <p class="help-text">Laisser vide = immédiat</p>
                                </div>

                                <div class="form-group">
                                    <label>Date de fin</label>
                                    <input type="date" name="end_date"
                                           value="<?= h($promo['end_date'] ?? '') ?>">
                                    <p class="help-text">Laisser vide = sans limite</p>
                                </div>
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="form-card preview-card">
                            <div class="card-header">
                                <h3>Aperçu client</h3>
                            </div>
                            <div class="card-body">
                                <div class="promo-preview">
                                    <div class="preview-code" id="preview-code">
                                        <?= h(strtoupper($promo['code'] ?? 'PROMO')) ?>
                                    </div>
                                    <div class="preview-value" id="preview-value">
                                        <?php
                                        if (!empty($promo)) {
                                            echo $promoModel->getDiscountLabel($promo);
                                        } else {
                                            echo '-20%';
                                        }
                                        ?>
                                    </div>
                                    <p class="preview-name" id="preview-name">
                                        <?= h($promo['name'] ?? 'Nom du code promo') ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-full">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                    <polyline points="17 21 17 13 7 13 7 21"/>
                                    <polyline points="7 3 7 8 15 8"/>
                                </svg>
                                <?= $isEdit ? 'Enregistrer les modifications' : 'Créer le code promo' ?>
                            </button>
                            <a href="/admin/promo-codes.php" class="btn btn-secondary btn-full">Annuler</a>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
    function generateCode() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = '';
        for (let i = 0; i < 8; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.querySelector('input[name="code"]').value = code;
        updatePreview();
    }

    function toggleDiscountType() {
        const type = document.querySelector('input[name="discount_type"]:checked').value;
        const valueSection = document.getElementById('discount-value-section');
        const maxDiscountGroup = document.getElementById('max-discount-group');
        const label = document.getElementById('discount-value-label');
        const suffix = document.getElementById('discount-suffix');

        // Mettre à jour les classes actives
        document.querySelectorAll('.discount-type-option').forEach(opt => {
            opt.classList.remove('active');
        });
        document.querySelector('input[name="discount_type"]:checked').closest('.discount-type-option').classList.add('active');

        if (type === 'free_shipping') {
            valueSection.style.display = 'none';
        } else {
            valueSection.style.display = 'grid';
            if (type === 'percentage') {
                label.innerHTML = 'Pourcentage de réduction <span class="required">*</span>';
                suffix.textContent = '%';
                maxDiscountGroup.style.display = 'block';
            } else {
                label.innerHTML = 'Montant de réduction <span class="required">*</span>';
                suffix.textContent = '€';
                maxDiscountGroup.style.display = 'none';
            }
        }
        updatePreview();
    }

    function updatePreview() {
        const code = document.querySelector('input[name="code"]').value || 'PROMO';
        const name = document.querySelector('input[name="name"]').value || 'Nom du code promo';
        const type = document.querySelector('input[name="discount_type"]:checked').value;
        const value = document.getElementById('discount_value').value || 0;

        document.getElementById('preview-code').textContent = code.toUpperCase();
        document.getElementById('preview-name').textContent = name;

        let valueText = '';
        if (type === 'percentage') {
            valueText = '-' + parseInt(value) + '%';
        } else if (type === 'fixed_amount') {
            valueText = '-' + parseFloat(value).toFixed(2).replace('.', ',') + ' €';
        } else {
            valueText = 'Livraison gratuite';
        }
        document.getElementById('preview-value').textContent = valueText;
    }

    // Event listeners
    document.querySelector('input[name="code"]').addEventListener('input', updatePreview);
    document.querySelector('input[name="name"]').addEventListener('input', updatePreview);
    document.getElementById('discount_value').addEventListener('input', updatePreview);
    </script>

    <style>
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 8px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--pink-main); }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #DC2626;
        }
        .alert ul {
            margin: 0;
            padding-left: 20px;
        }
        .alert li { margin: 4px 0; }

        .form-layout {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 1100px) {
            .form-layout { grid-template-columns: 1fr; }
        }

        .form-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            margin-bottom: 24px;
        }
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-light);
        }
        .card-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--black-soft);
        }
        .card-body {
            padding: 24px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
            margin-bottom: 8px;
        }
        .required { color: var(--pink-main); }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.1);
        }

        .help-text {
            margin: 8px 0 0;
            font-size: 12px;
            color: var(--gray);
        }

        .code-input-wrapper {
            display: flex;
            gap: 10px;
        }
        .code-input-wrapper input {
            flex: 1;
            font-family: 'SF Mono', 'Fira Code', monospace;
            letter-spacing: 1px;
        }
        .generate-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 16px;
            background: var(--gray-light);
            border: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .generate-btn:hover {
            background: var(--pink-light);
            color: var(--pink-dark);
        }

        .input-with-suffix {
            position: relative;
        }
        .input-with-suffix input {
            padding-right: 50px;
        }
        .input-with-suffix .suffix {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 600;
            color: var(--gray);
        }

        .discount-types {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        @media (max-width: 600px) {
            .discount-types { grid-template-columns: 1fr; }
        }

        .discount-type-option {
            cursor: pointer;
        }
        .discount-type-option input { display: none; }
        .discount-type-option .option-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            background: var(--gray-light);
            border: 2px solid transparent;
            border-radius: 16px;
            transition: all 0.2s;
            text-align: center;
        }
        .discount-type-option:hover .option-content {
            background: var(--pink-light);
        }
        .discount-type-option.active .option-content {
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.15) 0%, rgba(61, 255, 192, 0.15) 100%);
            border-color: var(--pink-main);
        }
        .option-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 12px;
            color: var(--pink-main);
            margin-bottom: 12px;
        }
        .option-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
        }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }
        .toggle-row span:first-child {
            font-size: 14px;
            font-weight: 500;
        }
        .toggle-row input { display: none; }
        .toggle {
            width: 48px;
            height: 26px;
            background: var(--gray-light);
            border-radius: 13px;
            position: relative;
            transition: all 0.3s;
        }
        .toggle::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .toggle-row input:checked + .toggle {
            background: linear-gradient(135deg, var(--mint-main) 0%, var(--mint-dark) 100%);
        }
        .toggle-row input:checked + .toggle::after { left: 25px; }

        .preview-card {
            border: 2px dashed var(--pink-main);
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.03) 0%, rgba(61, 255, 192, 0.03) 100%);
        }
        .promo-preview {
            text-align: center;
            padding: 20px 0;
        }
        .preview-code {
            display: inline-block;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 18px;
            font-weight: 700;
            color: var(--black-soft);
            background: white;
            padding: 10px 20px;
            border-radius: 12px;
            border: 2px dashed var(--pink-main);
            letter-spacing: 2px;
            margin-bottom: 16px;
        }
        .preview-value {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        .preview-name {
            font-size: 14px;
            color: var(--gray);
            margin: 0;
        }

        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn-full { width: 100%; justify-content: center; }
        .btn-secondary {
            background: var(--gray-light);
            color: var(--black-soft);
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
    </style>
</body>
</html>
