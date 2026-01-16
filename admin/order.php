<?php
/**
 * PERSONNALY - Admin : Détail Commande
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 */

require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Product.php';

Auth::requireAdmin();

$orderId = (int) get('id', 0);
if (!$orderId) {
    redirect('/admin/orders.php');
}

$orderModel = new Order();
$order = $orderModel->findById($orderId);

if (!$order) {
    redirect('/admin/orders.php');
}

// Récupérer le client
$userModel = new User();
$customer = $order['user_id'] ? $userModel->findById($order['user_id']) : null;

// Récupérer les items de la commande
$db = Database::getInstance();
$stmt = $db->prepare('
    SELECT oc.*, p.name as product_name, p.category as product_category
    FROM order_customizations oc
    LEFT JOIN products p ON oc.product_id = p.id
    WHERE oc.order_id = ?
');
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Décoder l'adresse de livraison
$shippingAddress = $order['shipping_address']
    ? json_decode($order['shipping_address'], true)
    : null;

// Traitement changement de statut
$success = '';
$error = '';

if (isPost() && isset($_POST['update_status'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $newStatus = post('status', '');
        $validStatuses = ['pending', 'accepted', 'in_progress', 'completed', 'shipped', 'cancelled'];

        if (in_array($newStatus, $validStatuses)) {
            $orderModel->updateStatus($orderId, $newStatus);
            $order['status'] = $newStatus;
            $success = 'Statut mis à jour.';
        }
    }
}

$statusLabels = [
    'pending' => ['label' => 'En attente', 'color' => '#F59E0B'],
    'accepted' => ['label' => 'Acceptée', 'color' => '#3B82F6'],
    'in_progress' => ['label' => 'En cours', 'color' => '#8B5CF6'],
    'completed' => ['label' => 'Terminée', 'color' => '#10B981'],
    'shipped' => ['label' => 'Expédiée', 'color' => '#06B6D4'],
    'cancelled' => ['label' => 'Annulée', 'color' => '#EF4444'],
];
$currentStatus = $statusLabels[$order['status']] ?? $statusLabels['pending'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande #<?= $orderId ?> - Admin PERSONNALY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .order-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .order-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .order-title h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--black-soft);
        }
        .order-id {
            background: var(--gradient-pink);
            color: white;
            padding: 8px 16px;
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 14px;
        }
        .order-date {
            color: var(--gray);
            font-size: 14px;
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

        .order-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
            align-items: start;
        }

        /* Status Card */
        .status-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 25px;
        }
        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .current-status {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .status-label {
            font-weight: 700;
            font-size: 1.1rem;
        }
        .status-form {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .status-select {
            padding: 10px 16px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            font-size: 14px;
            min-width: 160px;
        }

        /* Items Card */
        .items-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            font-weight: 700;
            font-size: 1.1rem;
        }
        .order-item {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 20px;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            align-items: center;
        }
        .order-item:last-child { border-bottom: none; }
        .item-image {
            width: 80px;
            height: 80px;
            background: var(--gray-light);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        .item-details h3 {
            font-weight: 600;
            margin-bottom: 8px;
        }
        .item-customization {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .custom-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--gray-light);
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 12px;
            color: var(--gray);
        }
        .custom-tag strong { color: var(--black-soft); }
        .custom-text-tag {
            background: rgba(255, 105, 180, 0.1);
            color: var(--pink-dark);
        }
        .custom-text-tag strong { color: var(--pink-dark); }
        .item-price {
            text-align: right;
        }
        .item-qty {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 5px;
        }
        .item-subtotal {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--pink-dark);
        }

        /* Totals */
        .order-totals {
            padding: 20px 25px;
            background: var(--gray-light);
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 15px;
        }
        .total-row.grand-total {
            font-size: 1.3rem;
            font-weight: 700;
            padding-top: 15px;
            margin-top: 10px;
            border-top: 2px solid rgba(0,0,0,0.1);
        }
        .total-row.grand-total span:last-child {
            color: var(--pink-dark);
        }

        /* Sidebar Cards */
        .sidebar-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 20px;
        }
        .sidebar-card h3 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            margin-bottom: 15px;
        }
        .customer-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .customer-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--black-soft);
        }
        .customer-email a {
            color: var(--pink-main);
            text-decoration: none;
        }
        .customer-phone {
            color: var(--gray);
        }
        .view-customer-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            color: var(--pink-main);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .address-block {
            line-height: 1.7;
            color: var(--black-soft);
        }

        .notes-block {
            background: var(--gray-light);
            padding: 15px;
            border-radius: var(--radius-md);
            font-style: italic;
            color: var(--gray);
            line-height: 1.6;
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-dark);
        }

        @media (max-width: 968px) {
            .order-grid { grid-template-columns: 1fr; }
            .order-item { grid-template-columns: 60px 1fr; }
            .item-price { grid-column: span 2; text-align: left; margin-top: 10px; }
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
                <a href="/admin/orders.php" class="nav-item active">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    Commandes
                </a>
                <a href="/admin/customers.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    Clients
                </a>
                <a href="/admin/settings.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
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
            <div class="order-header">
                <div class="order-title">
                    <a href="/admin/orders.php" class="back-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Retour
                    </a>
                    <h1>Commande</h1>
                    <span class="order-id">#<?= $orderId ?></span>
                </div>
                <div class="order-date">
                    Passée le <?= formatDate($order['created_at'], 'd/m/Y à H:i') ?>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <!-- Status Card -->
            <div class="status-card">
                <div class="status-row">
                    <div class="current-status">
                        <span class="status-dot" style="background-color: <?= $currentStatus['color'] ?>"></span>
                        <span class="status-label"><?= $currentStatus['label'] ?></span>
                    </div>
                    <form method="post" class="status-form">
                        <?= csrfField() ?>
                        <select name="status" class="status-select">
                            <?php foreach ($statusLabels as $key => $status): ?>
                                <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>>
                                    <?= $status['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_status" value="1" class="btn btn-secondary" style="padding: 10px 20px;">
                            Mettre à jour
                        </button>
                    </form>
                </div>
            </div>

            <div class="order-grid">
                <!-- Items -->
                <div class="items-card">
                    <div class="card-header">
                        Articles commandés (<?= count($items) ?>)
                    </div>

                    <?php foreach ($items as $item):
                        $customization = is_string($item['data_json'])
                            ? json_decode($item['data_json'], true)
                            : $item['data_json'];
                    ?>
                        <div class="order-item">
                            <div class="item-image">👕</div>
                            <div class="item-details">
                                <h3><?= h($item['product_name'] ?? 'Produit supprimé') ?></h3>
                                <div class="item-customization">
                                    <span class="custom-tag">
                                        Taille: <strong><?= h($customization['size'] ?? 'M') ?></strong>
                                    </span>
                                    <span class="custom-tag">
                                        Couleur: <strong><?= ucfirst(h($customization['color'] ?? 'blanc')) ?></strong>
                                    </span>
                                    <span class="custom-tag">
                                        Position: <strong><?= ucfirst(h($customization['position'] ?? 'centre')) ?></strong>
                                    </span>
                                    <?php if (!empty($customization['text'])): ?>
                                        <span class="custom-tag custom-text-tag">
                                            Texte: <strong>"<?= h($customization['text']) ?>"</strong>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="item-price">
                                <div class="item-qty"><?= $item['quantity'] ?> × <?= formatPrice($item['unit_price']) ?></div>
                                <div class="item-subtotal"><?= formatPrice($item['quantity'] * $item['unit_price']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="order-totals">
                        <div class="total-row">
                            <span>Sous-total</span>
                            <span><?= formatPrice($order['total']) ?></span>
                        </div>
                        <div class="total-row">
                            <span>Livraison</span>
                            <span>Gratuite</span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total</span>
                            <span><?= formatPrice($order['total']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <!-- Customer -->
                    <div class="sidebar-card">
                        <h3>Client</h3>
                        <?php if ($customer): ?>
                            <div class="customer-info">
                                <div class="customer-name">
                                    <?= h(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?>
                                </div>
                                <div class="customer-email">
                                    <a href="mailto:<?= h($customer['email']) ?>"><?= h($customer['email']) ?></a>
                                </div>
                                <?php if (!empty($customer['phone'])): ?>
                                    <div class="customer-phone"><?= h($customer['phone']) ?></div>
                                <?php endif; ?>
                                <a href="/admin/customer.php?id=<?= $customer['id'] ?>" class="view-customer-btn">
                                    Voir la fiche client →
                                </a>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--gray);">Client supprimé ou anonyme</p>
                        <?php endif; ?>
                    </div>

                    <!-- Shipping Address -->
                    <div class="sidebar-card">
                        <h3>Adresse de livraison</h3>
                        <?php if ($shippingAddress): ?>
                            <div class="address-block">
                                <strong><?= h(($shippingAddress['first_name'] ?? '') . ' ' . ($shippingAddress['last_name'] ?? '')) ?></strong><br>
                                <?= h($shippingAddress['address'] ?? '') ?><br>
                                <?= h(($shippingAddress['zipcode'] ?? '') . ' ' . ($shippingAddress['city'] ?? '')) ?>
                                <?php if (!empty($shippingAddress['phone'])): ?>
                                    <br>Tél: <?= h($shippingAddress['phone']) ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--gray);">Adresse non renseignée</p>
                        <?php endif; ?>
                    </div>

                    <!-- Notes -->
                    <?php if (!empty($order['notes'])): ?>
                        <div class="sidebar-card">
                            <h3>Notes client</h3>
                            <div class="notes-block">
                                <?= nl2br(h($order['notes'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
