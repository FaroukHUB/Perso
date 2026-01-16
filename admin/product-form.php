<?php
/**
 * PERSONNALY - Admin : Formulaire Produit (Ajout/Modification)
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
];

// Catégories disponibles
$categories = ['Homme', 'Femme', 'Enfant', 'Unisexe', 'Accessoire'];

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
        ];

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
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <h2>PERSONNALY</h2>
                <span>Administration</span>
            </div>

            <nav class="sidebar-nav">
                <a href="/admin/dashboard.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="/admin/orders.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <span>Commandes</span>
                    <?php if ($pendingOrders > 0): ?>
                        <span class="nav-badge"><?= $pendingOrders ?></span>
                    <?php endif; ?>
                </a>

                <a href="/admin/products.php" class="nav-item active">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                        <line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>
                    <span>Produits</span>
                </a>

                <a href="/admin/customers.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                    </svg>
                    <span>Clients</span>
                </a>

                <a href="/admin/settings.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <span>Paramètres</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="/admin/logout.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Déconnexion
                </a>
            </div>
        </aside>

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
                <form method="post" class="product-form">
                    <?= csrfField() ?>

                    <div class="form-grid">
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
                        </div>

                        <div class="form-section">
                            <h3>Paramètres</h3>

                            <div class="form-group">
                                <label class="switch-label">
                                    <input type="checkbox" name="active" class="switch-input"
                                           <?= $formData['active'] ? 'checked' : '' ?>>
                                    <span class="switch-slider"></span>
                                    <span class="switch-text">Produit actif (visible sur le site)</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <div class="preview-box">
                                    <div class="preview-icon">👕</div>
                                    <p>Aperçu de l'image<br><small class="text-muted">(Upload à venir)</small></p>
                                </div>
                            </div>
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
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-xl);
        }

        @media (max-width: 900px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        .form-section h3 {
            font-size: 1rem;
            color: var(--black-soft);
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-sm);
            border-bottom: 2px solid var(--gray-light);
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

        .switch-input {
            display: none;
        }

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

        /* Preview box */
        .preview-box {
            background: var(--gray-light);
            border: 2px dashed rgba(0,0,0,0.1);
            border-radius: var(--radius-md);
            padding: var(--spacing-xl);
            text-align: center;
        }

        .preview-icon {
            font-size: 4rem;
            margin-bottom: var(--spacing-md);
        }

        .preview-box p {
            color: var(--gray);
            font-size: 14px;
        }
    </style>
</body>
</html>
