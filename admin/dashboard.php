<?php
/**
 * PERSONNALY - Admin Dashboard
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Order.php';

// Protection admin
Auth::requireAdmin();

// Récupération des stats
$userModel = new User();
$productModel = new Product();
$orderModel = new Order();

$stats = [
    'pending_orders' => $orderModel->countNew(),
    'total_products' => $productModel->count(),
    'total_clients' => $userModel->countByRole('client'),
    'total_sales' => $orderModel->getTotalSales(),
];

// Dernières commandes
$recentOrders = array_slice($orderModel->findAll(), 0, 5);
$user = Auth::getUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body>
    <?php $pendingOrders = $stats['pending_orders']; ?>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Bonjour <span><?= h($user['email']) ?></span> 👋</h1>
                <a href="/admin/orders.php" class="btn btn-primary">
                    Voir les commandes
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card pink">
                    <div class="stat-label">Nouvelles commandes</div>
                    <div class="stat-value"><?= $stats['pending_orders'] ?></div>
                </div>

                <div class="stat-card mint">
                    <div class="stat-label">Chiffre d'affaires</div>
                    <div class="stat-value"><?= formatPrice($stats['total_sales']) ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Produits actifs</div>
                    <div class="stat-value"><?= $stats['total_products'] ?></div>
                </div>

                <div class="stat-card dark">
                    <div class="stat-label">Clients inscrits</div>
                    <div class="stat-value"><?= $stats['total_clients'] ?></div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="data-card">
                <div class="data-card-header">
                    <h3 class="data-card-title">Dernières commandes</h3>
                    <a href="/admin/orders.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 13px;">
                        Tout voir
                    </a>
                </div>

                <?php if (empty($recentOrders)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <h4>Aucune commande pour le moment</h4>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><strong>#<?= $order['id'] ?></strong></td>
                                    <td><?= h($order['user_email'] ?? 'Invité') ?></td>
                                    <td><strong><?= formatPrice($order['total']) ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?= $order['status'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= formatDate($order['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
