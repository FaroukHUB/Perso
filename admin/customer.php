<?php
/**
 * PERSONNALY - Admin : Fiche Client
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 */

require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$customerId = (int) get('id', 0);
if (!$customerId) {
    redirect('/admin/customers.php');
}

$userModel = new User();
$customer = $userModel->findById($customerId);

if (!$customer || $customer['role'] !== 'client') {
    redirect('/admin/customers.php');
}

// Récupérer les commandes du client
$db = Database::getInstance();
$stmt = $db->prepare('
    SELECT o.*,
           (SELECT COUNT(*) FROM order_customizations WHERE order_id = o.id) as item_count
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
');
$stmt->execute([$customerId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats client
$totalOrders = count($orders);
$totalSpent = array_sum(array_column($orders, 'total'));
$lastOrderDate = $totalOrders > 0 ? $orders[0]['created_at'] : null;

$statusLabels = [
    'pending' => ['label' => 'En attente', 'color' => '#F59E0B'],
    'accepted' => ['label' => 'Acceptée', 'color' => '#3B82F6'],
    'in_progress' => ['label' => 'En cours', 'color' => '#8B5CF6'],
    'completed' => ['label' => 'Terminée', 'color' => '#10B981'],
    'shipped' => ['label' => 'Expédiée', 'color' => '#06B6D4'],
    'cancelled' => ['label' => 'Annulée', 'color' => '#EF4444'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($customer['first_name'] . ' ' . $customer['last_name']) ?> - Admin PERSONNALY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .customer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .customer-title {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .back-btn:hover { color: var(--pink-main); }
        .customer-avatar {
            width: 70px;
            height: 70px;
            background: var(--gradient-pink);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
        }
        .customer-info h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 5px;
        }
        .customer-email {
            color: var(--gray);
        }
        .customer-email a {
            color: var(--pink-main);
            text-decoration: none;
        }

        .customer-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            align-items: start;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            gap: 15px;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
        }
        .stat-label {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--black-soft);
        }
        .stat-value.highlight {
            color: var(--pink-dark);
        }
        .stat-value.mint {
            color: var(--mint-dark);
        }

        /* Info Card */
        .info-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
        }
        .info-card h3 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--gray); }
        .info-value { font-weight: 500; color: var(--black-soft); }

        /* Orders Card */
        .orders-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        .orders-table th {
            text-align: left;
            padding: 15px 20px;
            background: var(--gray-light);
            font-weight: 600;
            font-size: 13px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .orders-table td {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            vertical-align: middle;
        }
        .orders-table tr:last-child td { border-bottom: none; }
        .orders-table tr:hover { background: rgba(255, 105, 180, 0.03); }
        .order-link {
            color: var(--pink-main);
            text-decoration: none;
            font-weight: 600;
        }
        .order-link:hover { text-decoration: underline; }
        .order-amount {
            font-weight: 700;
            color: var(--black-soft);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .empty-orders {
            padding: 60px 20px;
            text-align: center;
            color: var(--gray);
        }
        .empty-orders-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 968px) {
            .customer-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar-alt.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="customer-header">
                <div class="customer-title">
                    <a href="/admin/customers.php" class="back-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Retour
                    </a>
                    <div class="customer-avatar">
                        <?= strtoupper(substr($customer['first_name'] ?? 'C', 0, 1)) ?>
                    </div>
                    <div class="customer-info">
                        <h1><?= h(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?></h1>
                        <div class="customer-email">
                            <a href="mailto:<?= h($customer['email']) ?>"><?= h($customer['email']) ?></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="customer-grid">
                <!-- Sidebar Stats -->
                <div>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Total dépensé</div>
                            <div class="stat-value highlight"><?= formatPrice($totalSpent) ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Commandes</div>
                            <div class="stat-value mint"><?= $totalOrders ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Dernière commande</div>
                            <div class="stat-value" style="font-size: 1rem;">
                                <?= $lastOrderDate ? formatDate($lastOrderDate, 'd/m/Y') : 'Aucune' ?>
                            </div>
                        </div>
                    </div>

                    <div class="info-card" style="margin-top: 20px;">
                        <h3>Informations</h3>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= h($customer['email']) ?></span>
                        </div>
                        <?php if (!empty($customer['phone'])): ?>
                            <div class="info-row">
                                <span class="info-label">Téléphone</span>
                                <span class="info-value"><?= h($customer['phone']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label">Inscrit le</span>
                            <span class="info-value"><?= formatDate($customer['created_at'], 'd/m/Y') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Orders History -->
                <div class="orders-card">
                    <div class="card-header">
                        <span>Historique des commandes</span>
                        <span style="font-size: 13px; color: var(--gray); font-weight: 400;">
                            <?= $totalOrders ?> commande<?= $totalOrders > 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <?php if (empty($orders)): ?>
                        <div class="empty-orders">
                            <div class="empty-orders-icon">📦</div>
                            <p>Aucune commande pour ce client</p>
                        </div>
                    <?php else: ?>
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Commande</th>
                                    <th>Date</th>
                                    <th>Articles</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order):
                                    $status = $statusLabels[$order['status']] ?? $statusLabels['pending'];
                                ?>
                                    <tr>
                                        <td>
                                            <a href="/admin/order.php?id=<?= $order['id'] ?>" class="order-link">
                                                #<?= $order['id'] ?>
                                            </a>
                                        </td>
                                        <td><?= formatDate($order['created_at'], 'd/m/Y') ?></td>
                                        <td><?= $order['item_count'] ?> article<?= $order['item_count'] > 1 ? 's' : '' ?></td>
                                        <td class="order-amount"><?= formatPrice($order['total']) ?></td>
                                        <td>
                                            <span class="status-badge" style="background: <?= $status['color'] ?>20; color: <?= $status['color'] ?>;">
                                                <span class="status-dot" style="background: <?= $status['color'] ?>;"></span>
                                                <?= $status['label'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
