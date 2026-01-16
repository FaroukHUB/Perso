<?php
/**
 * PERSONNALY - Admin : Gestion des Commandes
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$orderModel = new Order();

// Messages flash
$success = $_GET['success'] ?? '';

// Filtre par statut
$statusFilter = $_GET['status'] ?? '';

// Action: Changer le statut
if (isset($_POST['update_status']) && isset($_POST['order_id'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $orderModel->updateStatus((int)$_POST['order_id'], $_POST['new_status']);
        redirect('/admin/orders.php?success=Statut mis à jour');
    }
}

// Récupération des commandes
$orders = $orderModel->findAll($statusFilter ?: null);
$pendingOrders = $orderModel->countNew();

// Statuts disponibles
$statuses = [
    'pending' => ['label' => 'En attente', 'icon' => '⏳'],
    'accepted' => ['label' => 'Acceptée', 'icon' => '✅'],
    'in_progress' => ['label' => 'En production', 'icon' => '🔨'],
    'completed' => ['label' => 'Terminée', 'icon' => '📦'],
    'shipped' => ['label' => 'Expédiée', 'icon' => '🚚'],
    'cancelled' => ['label' => 'Annulée', 'icon' => '❌'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes - PERSONNALY Admin</title>
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
                <h1 class="page-title">Gestion des <span>Commandes</span></h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <!-- Filtres -->
            <div class="filters-bar">
                <a href="/admin/orders.php" class="filter-btn <?= !$statusFilter ? 'active' : '' ?>">
                    Toutes
                </a>
                <?php foreach ($statuses as $key => $status): ?>
                    <a href="/admin/orders.php?status=<?= $key ?>"
                       class="filter-btn <?= $statusFilter === $key ? 'active' : '' ?>">
                        <?= $status['icon'] ?> <?= $status['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Orders Table -->
            <div class="data-card">
                <div class="data-card-header">
                    <h3 class="data-card-title"><?= count($orders) ?> commande(s)</h3>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <h4>Aucune commande</h4>
                        <p class="text-muted">Les commandes apparaîtront ici</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Client</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><a href="/admin/order.php?id=<?= $order['id'] ?>" style="color: var(--pink-main); text-decoration: none; font-weight: 700;">#<?= $order['id'] ?></a></td>
                                    <td><?= h($order['user_email'] ?? 'Invité') ?></td>
                                    <td><strong style="color: var(--pink-dark);"><?= formatPrice($order['total']) ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?= $order['status'] ?>">
                                            <?= $statuses[$order['status']]['icon'] ?? '' ?>
                                            <?= $statuses[$order['status']]['label'] ?? ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= formatDate($order['created_at']) ?></td>
                                    <td>
                                        <form method="post" class="status-form">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="new_status" class="status-select" onchange="this.form.submit()">
                                                <?php foreach ($statuses as $key => $status): ?>
                                                    <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>>
                                                        <?= $status['icon'] ?> <?= $status['label'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
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

        .filters-bar {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 18px;
            background: var(--white);
            border-radius: var(--radius-full);
            text-decoration: none;
            color: var(--gray);
            font-weight: 500;
            font-size: 14px;
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-sm);
        }

        .filter-btn:hover {
            color: var(--pink-main);
        }

        .filter-btn.active {
            background: var(--gradient-pink);
            color: white;
            box-shadow: var(--shadow-pink);
        }

        .status-form {
            display: inline-block;
        }

        .status-select {
            padding: 8px 12px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 500;
            background: white;
            cursor: pointer;
            transition: border-color var(--transition-fast);
        }

        .status-select:hover {
            border-color: var(--pink-main);
        }

        .status-select:focus {
            outline: none;
            border-color: var(--pink-main);
        }
    </style>
</body>
</html>
