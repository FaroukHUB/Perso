<?php
/**
 * PERSONNALY - Admin Dashboard
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
    'total_orders' => $orderModel->countByStatus('') ?: 0,
    'pending_orders' => $orderModel->countNew(),
    'total_products' => $productModel->count(),
    'total_clients' => $userModel->countByRole('client'),
    'total_sales' => $orderModel->getTotalSales(),
];

// Dernières commandes
$recentOrders = array_slice($orderModel->findAll(), 0, 5);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PERSONNALY Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #1a1a2e;
            color: white;
            padding: 20px;
        }
        .sidebar h2 { margin-bottom: 30px; }
        .sidebar nav a {
            display: block;
            color: #aaa;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar .logout {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
        }
        .sidebar .logout a {
            color: #ff6b6b;
        }

        /* Main */
        .main {
            margin-left: 250px;
            padding: 30px;
        }
        .main h1 { margin-bottom: 30px; color: #1a1a2e; }

        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #1a1a2e;
        }
        .stat-card.highlight { border-left: 4px solid #667eea; }
        .stat-card.warning { border-left: 4px solid #ffc107; }
        .stat-card.success { border-left: 4px solid #28a745; }

        /* Table */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 25px;
        }
        .card h3 { margin-bottom: 20px; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        th { color: #666; font-weight: 500; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-accepted { background: #cce5ff; color: #004085; }
        .badge-completed { background: #d4edda; color: #155724; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <h2>PERSONNALY</h2>
        <nav>
            <a href="/admin/dashboard.php" class="active">Dashboard</a>
            <a href="/admin/orders.php">Commandes</a>
            <a href="/admin/products.php">Produits</a>
            <a href="/admin/customers.php">Clients</a>
        </nav>
        <div class="logout">
            <a href="/admin/logout.php">Déconnexion</a>
        </div>
    </aside>

    <main class="main">
        <h1>Dashboard</h1>

        <div class="stats-grid">
            <div class="stat-card warning">
                <h3>Nouvelles commandes</h3>
                <div class="value"><?= $stats['pending_orders'] ?></div>
            </div>
            <div class="stat-card highlight">
                <h3>Total commandes</h3>
                <div class="value"><?= $stats['total_orders'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Produits actifs</h3>
                <div class="value"><?= $stats['total_products'] ?></div>
            </div>
            <div class="stat-card success">
                <h3>Chiffre d'affaires</h3>
                <div class="value"><?= formatPrice($stats['total_sales']) ?></div>
            </div>
        </div>

        <div class="card">
            <h3>Dernières commandes</h3>

            <?php if (empty($recentOrders)): ?>
                <div class="empty-state">
                    <p>Aucune commande pour le moment.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><?= $order['id'] ?></td>
                                <td><?= h($order['user_email'] ?? 'Invité') ?></td>
                                <td><?= formatPrice($order['total']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $order['status'] ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                                <td><?= formatDate($order['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
