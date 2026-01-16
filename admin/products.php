<?php
/**
 * PERSONNALY - Admin : Gestion des Produits
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$productModel = new Product();
$orderModel = new Order();

// Messages flash
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Action: Supprimer
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (verifyCsrf($_GET['token'] ?? '')) {
        $productModel->delete((int)$_GET['delete']);
        redirect('/admin/products.php?success=Produit supprimé');
    }
}

// Action: Toggle actif/inactif
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    if (verifyCsrf($_GET['token'] ?? '')) {
        $product = $productModel->findById((int)$_GET['toggle']);
        if ($product) {
            $productModel->setActive((int)$_GET['toggle'], !$product['active']);
            redirect('/admin/products.php?success=Statut mis à jour');
        }
    }
}

// Récupération des produits
$products = $productModel->findAll();
$pendingOrders = $orderModel->countNew();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - PERSONNALY Admin</title>
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
                <h1 class="page-title">Gestion des <span>Produits</span></h1>
                <a href="/admin/product-form.php" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Ajouter un produit
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Products Table -->
            <div class="data-card">
                <div class="data-card-header">
                    <h3 class="data-card-title"><?= count($products) ?> produit(s)</h3>
                </div>

                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">👕</div>
                        <h4>Aucun produit</h4>
                        <p class="text-muted">Commencez par ajouter votre premier produit</p>
                        <a href="/admin/product-form.php" class="btn btn-primary mt-lg">Ajouter un produit</a>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Catégorie</th>
                                <th>Prix</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 50px; height: 50px; background: var(--gray-light); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                👕
                                            </div>
                                            <div>
                                                <strong><?= h($product['name']) ?></strong>
                                                <div class="text-muted" style="font-size: 13px;">
                                                    <?= h(substr($product['description'] ?? '', 0, 50)) ?>...
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-pink"><?= h($product['category'] ?? 'Non classé') ?></span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--pink-dark);"><?= formatPrice($product['base_price']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($product['active']): ?>
                                            <span class="status-badge status-accepted">Actif</span>
                                        <?php else: ?>
                                            <span class="status-badge status-cancelled">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="/admin/product-form.php?id=<?= $product['id'] ?>"
                                               class="btn-icon" title="Modifier">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            </a>
                                            <a href="/admin/products.php?toggle=<?= $product['id'] ?>&token=<?= csrfToken() ?>"
                                               class="btn-icon" title="<?= $product['active'] ? 'Désactiver' : 'Activer' ?>">
                                                <?php if ($product['active']): ?>
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                        <circle cx="12" cy="12" r="3"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </a>
                                            <a href="/admin/products.php?delete=<?= $product['id'] ?>&token=<?= csrfToken() ?>"
                                               class="btn-icon btn-icon-danger"
                                               title="Supprimer"
                                               onclick="return confirm('Supprimer ce produit ?')">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
        .alert-success {
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-dark);
            border-left: 4px solid var(--mint-main);
        }
        .alert-error {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
            border-left: 4px solid var(--pink-main);
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            background: var(--gray-light);
            color: var(--gray);
            transition: all var(--transition-fast);
        }
        .btn-icon:hover {
            background: var(--pink-main);
            color: white;
        }
        .btn-icon-danger:hover {
            background: #dc3545;
        }
    </style>
</body>
</html>
