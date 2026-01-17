<?php
/**
 * PERSONNALY Admin - Formulaire Upsell
 * Création et édition de suggestions de produits
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/ProductUpsell.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$upsellModel = new ProductUpsell();
$productModel = new Product();
$categoryModel = new Category();
$orderModel = new Order();
$pendingOrders = $orderModel->countNew();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$upsell = $id ? $upsellModel->findById($id) : null;
$isEdit = $upsell !== null;

// Données pour les selects
$products = $productModel->findAll(true);
$categories = $categoryModel->findAll();

$errors = [];

// Traitement du formulaire
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'trigger_type' => $_POST['trigger_type'] ?? 'any',
        'trigger_value' => trim($_POST['trigger_value'] ?? ''),
        'suggested_product_id' => (int) ($_POST['suggested_product_id'] ?? 0),
        'custom_title' => trim($_POST['custom_title'] ?? ''),
        'custom_description' => trim($_POST['custom_description'] ?? ''),
        'badge_text' => trim($_POST['badge_text'] ?? ''),
        'promo_price' => !empty($_POST['promo_price']) ? (float) $_POST['promo_price'] : null,
        'priority' => (int) ($_POST['priority'] ?? 0),
        'active' => isset($_POST['active']) ? 1 : 0,
    ];

    if (empty($data['name'])) {
        $errors[] = 'Le nom est requis';
    }
    if (empty($data['suggested_product_id'])) {
        $errors[] = 'Veuillez sélectionner un produit à suggérer';
    }
    if ($data['trigger_type'] !== 'any' && empty($data['trigger_value'])) {
        $errors[] = 'Veuillez spécifier la valeur du déclencheur';
    }

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $upsellModel->update($id, $data);
            } else {
                $id = $upsellModel->create($data);
            }
            redirect('/admin/upsells.php?saved=1');
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
        }
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
                                       placeholder="Ex: Casquette avec T-shirt"
                                       required>
                                <small class="form-help">Pour identifier cet upsell dans l'admin</small>
                            </div>
                        </div>

                        <!-- Produit suggéré -->
                        <div class="form-card">
                            <h3 class="form-card-title">
                                <span class="step-badge">1</span>
                                Produit à suggérer
                            </h3>

                            <div class="form-group">
                                <label for="suggested_product_id">Sélectionner le produit <span class="required">*</span></label>
                                <select id="suggested_product_id" name="suggested_product_id" required onchange="updateProductPreview()">
                                    <option value="">-- Choisir un produit --</option>
                                    <?php foreach ($products as $prod): ?>
                                        <option value="<?= $prod['id'] ?>"
                                                data-price="<?= h($prod['price']) ?>"
                                                data-image="<?= h($prod['image_front_url'] ?? '') ?>"
                                                <?= ($upsell['suggested_product_id'] ?? '') == $prod['id'] ? 'selected' : '' ?>>
                                            <?= h($prod['name']) ?> - <?= number_format($prod['price'], 2, ',', ' ') ?>€
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="product-preview" class="product-preview" style="display: none;">
                                <img id="preview-image" src="" alt="">
                                <div class="preview-info">
                                    <span id="preview-name"></span>
                                    <span id="preview-price"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Déclencheur -->
                        <div class="form-card">
                            <h3 class="form-card-title">
                                <span class="step-badge step-2">2</span>
                                Quand suggérer ce produit ?
                            </h3>

                            <div class="form-group">
                                <label for="trigger_type">Condition d'affichage</label>
                                <select id="trigger_type" name="trigger_type" onchange="updateTriggerUI()">
                                    <?php foreach (ProductUpsell::TRIGGER_TYPES as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= ($upsell['trigger_type'] ?? 'any') === $key ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="trigger-value-group" style="display: none;">
                                <label id="trigger-value-label">Valeur</label>
                                <select id="trigger_value" name="trigger_value">
                                    <option value="">-- Choisir --</option>
                                </select>
                            </div>

                            <div class="trigger-info" id="trigger-info">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 16v-4M12 8h.01"/>
                                </svg>
                                <span>Ce produit sera suggéré à tous les clients.</span>
                            </div>
                        </div>

                        <!-- Personnalisation -->
                        <div class="form-card">
                            <h3 class="form-card-title">
                                <span class="step-badge step-3">3</span>
                                Personnalisation (optionnel)
                            </h3>

                            <div class="form-group">
                                <label for="custom_title">Titre personnalisé</label>
                                <input type="text" id="custom_title" name="custom_title"
                                       value="<?= h($upsell['custom_title'] ?? '') ?>"
                                       placeholder="Laisser vide pour utiliser le nom du produit">
                            </div>

                            <div class="form-group">
                                <label for="custom_description">Description courte</label>
                                <textarea id="custom_description" name="custom_description" rows="2"
                                          placeholder="Ex: Complétez votre look avec cette casquette assortie"><?= h($upsell['custom_description'] ?? '') ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="flex: 1;">
                                    <label for="badge_text">Badge</label>
                                    <input type="text" id="badge_text" name="badge_text"
                                           value="<?= h($upsell['badge_text'] ?? '') ?>"
                                           placeholder="Ex: Bestseller, -20%, Nouveau">
                                    <small class="form-help">S'affiche en overlay sur l'image</small>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="promo_price">Prix promo</label>
                                    <div class="input-with-suffix">
                                        <input type="number" id="promo_price" name="promo_price"
                                               value="<?= h($upsell['promo_price'] ?? '') ?>"
                                               min="0" step="0.01" placeholder="Optionnel">
                                        <span class="input-suffix">€</span>
                                    </div>
                                    <small class="form-help">Prix spécial pour cet upsell</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne latérale -->
                    <div class="form-sidebar">
                        <!-- Publication -->
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

                        <!-- Aperçu -->
                        <div class="form-card">
                            <h3 class="form-card-title">Aperçu</h3>
                            <div class="preview-card" id="preview-card">
                                <div class="preview-placeholder">
                                    Sélectionnez un produit pour voir l'aperçu
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
    const products = <?= json_encode(array_map(fn($p) => [
        'id' => $p['id'],
        'name' => $p['name'],
        'price' => $p['price'],
        'image' => $p['image_front_url'] ?? ''
    ], $products)) ?>;
    const categories = <?= json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $categories)) ?>;

    const currentTriggerValue = '<?= h($upsell['trigger_value'] ?? '') ?>';

    function updateTriggerUI() {
        const type = document.getElementById('trigger_type').value;
        const valueGroup = document.getElementById('trigger-value-group');
        const valueSelect = document.getElementById('trigger_value');
        const valueLabel = document.getElementById('trigger-value-label');
        const info = document.getElementById('trigger-info').querySelector('span');

        if (type === 'any') {
            valueGroup.style.display = 'none';
            info.textContent = 'Ce produit sera suggéré à tous les clients.';
        } else if (type === 'product') {
            valueGroup.style.display = 'block';
            valueLabel.textContent = 'Si ce produit est au panier';
            populateSelect(valueSelect, products, 'id', 'name', currentTriggerValue);
            info.textContent = 'Suggéré uniquement si le produit sélectionné est au panier.';
        } else if (type === 'category') {
            valueGroup.style.display = 'block';
            valueLabel.textContent = 'Si un produit de cette catégorie est au panier';
            populateSelect(valueSelect, categories, 'id', 'name', currentTriggerValue);
            info.textContent = 'Suggéré si un produit de cette catégorie est au panier.';
        }
    }

    function populateSelect(selectEl, data, valueKey, labelKey, selectedValue) {
        selectEl.innerHTML = '<option value="">-- Choisir --</option>';
        data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[labelKey];
            if (String(item[valueKey]) === String(selectedValue)) {
                opt.selected = true;
            }
            selectEl.appendChild(opt);
        });
    }

    function updateProductPreview() {
        const select = document.getElementById('suggested_product_id');
        const option = select.options[select.selectedIndex];
        const preview = document.getElementById('product-preview');
        const previewCard = document.getElementById('preview-card');

        if (!option.value) {
            preview.style.display = 'none';
            previewCard.innerHTML = '<div class="preview-placeholder">Sélectionnez un produit pour voir l\'aperçu</div>';
            return;
        }

        const product = products.find(p => p.id == option.value);
        if (!product) return;

        // Mini preview
        preview.style.display = 'flex';
        document.getElementById('preview-image').src = product.image || '/public/assets/images/placeholder.png';
        document.getElementById('preview-name').textContent = product.name;
        document.getElementById('preview-price').textContent = parseFloat(product.price).toFixed(2).replace('.', ',') + '€';

        // Full preview card
        const customTitle = document.getElementById('custom_title').value || product.name;
        const customDesc = document.getElementById('custom_description').value || '';
        const badge = document.getElementById('badge_text').value || '';
        const promoPrice = document.getElementById('promo_price').value;

        let priceHtml = parseFloat(product.price).toFixed(2).replace('.', ',') + '€';
        if (promoPrice) {
            priceHtml = `<span class="old">${priceHtml}</span> <span class="promo">${parseFloat(promoPrice).toFixed(2).replace('.', ',')}€</span>`;
        }

        previewCard.innerHTML = `
            <div class="preview-upsell-card">
                <div class="preview-img">
                    <img src="${product.image || '/public/assets/images/placeholder.png'}" alt="">
                    ${badge ? `<span class="preview-badge">${badge}</span>` : ''}
                </div>
                <div class="preview-content">
                    <div class="preview-title">${customTitle}</div>
                    ${customDesc ? `<div class="preview-desc">${customDesc}</div>` : ''}
                    <div class="preview-price">${priceHtml}</div>
                    <button type="button" class="preview-btn">Ajouter</button>
                </div>
            </div>
        `;
    }

    // Mettre à jour l'aperçu quand les champs changent
    ['custom_title', 'custom_description', 'badge_text', 'promo_price'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateProductPreview);
    });

    document.addEventListener('DOMContentLoaded', function() {
        updateTriggerUI();
        updateProductPreview();
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
            .form-grid { grid-template-columns: 1fr; }
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
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
            color: white;
            border-radius: 50%;
            font-size: 12px;
            font-weight: 700;
        }
        .step-badge.step-2 {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
        }
        .step-badge.step-3 {
            background: linear-gradient(135deg, var(--mint-main) 0%, var(--mint-dark) 100%);
            color: var(--black-soft);
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label:not(.toggle-label) {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
        }
        .required { color: var(--pink-main); }

        .form-group input[type="text"],
        .form-group input[type="number"],
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
        .form-row { display: flex; gap: 16px; }

        .input-with-suffix {
            position: relative;
            display: flex;
        }
        .input-with-suffix input { padding-right: 40px; }
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
        .toggle-label input { display: none; }
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
        .toggle-label input:checked + .toggle-switch::after { left: 25px; }

        .btn-block { width: 100%; justify-content: center; }

        .product-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--gray-light);
            border-radius: 10px;
            margin-top: 12px;
        }
        .product-preview img {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 8px;
        }
        .preview-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        #preview-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
        }
        #preview-price {
            font-size: 14px;
            font-weight: 700;
            color: var(--pink-dark);
        }

        .trigger-info {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 12px;
            background: rgba(99, 102, 241, 0.08);
            border-radius: 10px;
            margin-top: 12px;
            font-size: 13px;
            color: #6366F1;
        }
        .trigger-info svg { flex-shrink: 0; margin-top: 1px; }

        .preview-placeholder {
            padding: 40px 20px;
            text-align: center;
            color: var(--gray);
            font-size: 13px;
        }

        .preview-upsell-card {
            border: 1px solid var(--gray-light);
            border-radius: 12px;
            overflow: hidden;
        }
        .preview-img {
            position: relative;
            height: 120px;
            background: var(--gray-light);
        }
        .preview-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .preview-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .preview-content {
            padding: 12px;
        }
        .preview-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
        }
        .preview-desc {
            font-size: 11px;
            color: var(--gray);
            margin-bottom: 8px;
        }
        .preview-price {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .preview-price .old {
            text-decoration: line-through;
            color: var(--gray);
            font-weight: 400;
            font-size: 12px;
        }
        .preview-price .promo {
            color: var(--pink-dark);
        }
        .preview-btn {
            width: 100%;
            padding: 8px;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .alert-danger {
            padding: 16px 20px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 12px;
            color: #DC2626;
        }
    </style>
</body>
</html>
