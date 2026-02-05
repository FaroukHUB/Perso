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

// AJAX: Récupérer le détail d'une commande
if (isset($_GET['ajax']) && $_GET['ajax'] === 'order_detail' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $orderId = (int)$_GET['id'];
    $order = $orderModel->findById($orderId);
    if (!$order) {
        echo json_encode(['error' => 'Commande introuvable']);
        exit;
    }

    // Client
    require_once __DIR__ . '/../app/models/User.php';
    $userModel = new User();
    $customer = $order['user_id'] ? $userModel->findById($order['user_id']) : null;

    // Items
    $db = Database::getInstance();
    $stmt = $db->prepare('
        SELECT oc.*, p.name as product_name, p.category as product_category, p.image_front_url as product_image
        FROM order_customizations oc
        LEFT JOIN products p ON oc.product_id = p.id
        WHERE oc.order_id = ?
    ');
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Décoder l'adresse
    $shippingAddress = $order['shipping_address']
        ? json_decode($order['shipping_address'], true)
        : null;

    // Décoder les customizations
    foreach ($items as &$item) {
        $item['customization'] = is_string($item['data_json'])
            ? json_decode($item['data_json'], true)
            : ($item['data_json'] ?? []);
    }
    unset($item);

    echo json_encode([
        'order' => $order,
        'customer' => $customer,
        'items' => $items,
        'shipping_address' => $shippingAddress,
    ]);
    exit;
}

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
                                <tr class="order-row" data-order-id="<?= $order['id'] ?>" onclick="openOrderModal(<?= $order['id'] ?>)" style="cursor: pointer;">
                                    <td><span style="color: var(--pink-main); font-weight: 700;">#<?= $order['id'] ?></span></td>
                                    <td><?= h($order['user_email'] ?? 'Invité') ?></td>
                                    <td><strong style="color: var(--pink-dark);"><?= formatPrice($order['total']) ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?= $order['status'] ?>">
                                            <?= $statuses[$order['status']]['icon'] ?? '' ?>
                                            <?= $statuses[$order['status']]['label'] ?? ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= formatDate($order['created_at']) ?></td>
                                    <td onclick="event.stopPropagation()">
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

    <!-- Order Detail Modal -->
    <div class="order-modal-overlay" id="orderModal" onclick="closeOrderModal(event)">
        <div class="order-modal" onclick="event.stopPropagation()">
            <div class="order-modal-header">
                <div class="order-modal-title">
                    <h2>Commande <span id="modalOrderId"></span></h2>
                    <span class="order-modal-date" id="modalOrderDate"></span>
                </div>
                <button class="order-modal-close" onclick="closeOrderModal()">&times;</button>
            </div>

            <div class="order-modal-body" id="modalBody">
                <div class="order-modal-loading">
                    <div class="spinner"></div>
                    <p>Chargement...</p>
                </div>
            </div>
        </div>
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

        .order-row:hover {
            background: rgba(255, 105, 180, 0.04);
        }

        /* === Order Detail Modal === */
        .order-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(4px);
        }
        .order-modal-overlay.active {
            display: flex;
        }
        .order-modal {
            background: #f8f8fa;
            border-radius: 20px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 80px rgba(0,0,0,0.25);
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .order-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 30px;
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .order-modal-title h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--black-soft);
            margin: 0;
        }
        .order-modal-title h2 span {
            color: var(--pink-main);
        }
        .order-modal-date {
            font-size: 13px;
            color: var(--gray);
            margin-top: 4px;
            display: block;
        }
        .order-modal-close {
            width: 40px;
            height: 40px;
            border: none;
            background: var(--gray-light);
            border-radius: 50%;
            font-size: 22px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            transition: all 0.2s;
        }
        .order-modal-close:hover {
            background: #EF4444;
            color: white;
        }
        .order-modal-body {
            overflow-y: auto;
            padding: 24px 30px;
            flex: 1;
        }
        .order-modal-loading {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--gray-light);
            border-top-color: var(--pink-main);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 15px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Modal content sections */
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 24px;
        }
        .modal-section {
            background: white;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
        }
        .modal-section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .modal-status-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .modal-status-current {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .modal-status-label {
            font-weight: 700;
            font-size: 15px;
        }
        .modal-item {
            display: flex;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            align-items: center;
        }
        .modal-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .modal-item-img {
            width: 60px;
            height: 60px;
            background: var(--gray-light);
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-item-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
        }
        .modal-item-details {
            flex: 1;
            min-width: 0;
        }
        .modal-item-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 6px;
        }
        .modal-item-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .modal-tag {
            display: inline-block;
            padding: 2px 8px;
            background: var(--gray-light);
            border-radius: 20px;
            font-size: 11px;
            color: var(--gray);
        }
        .modal-tag-text {
            background: rgba(255,105,180,0.1);
            color: var(--pink-dark);
        }
        .modal-item-price {
            text-align: right;
            flex-shrink: 0;
        }
        .modal-item-qty {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 3px;
        }
        .modal-item-subtotal {
            font-weight: 700;
            color: var(--pink-dark);
        }
        .modal-totals {
            background: var(--gray-light);
            border-radius: 10px;
            padding: 16px;
            margin-top: 12px;
        }
        .modal-total-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .modal-total-row:last-child {
            margin-bottom: 0;
            font-size: 1.15rem;
            font-weight: 700;
            padding-top: 10px;
            border-top: 2px solid rgba(0,0,0,0.08);
        }
        .modal-total-row:last-child span:last-child {
            color: var(--pink-dark);
        }
        .modal-customer-name {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 8px;
        }
        .modal-customer-email {
            color: var(--pink-main);
            text-decoration: none;
            font-size: 14px;
        }
        .modal-customer-phone {
            color: var(--gray);
            font-size: 14px;
            margin-top: 6px;
        }
        .modal-address {
            line-height: 1.8;
            font-size: 14px;
        }
        .modal-notes {
            background: var(--gray-light);
            padding: 12px 16px;
            border-radius: 10px;
            font-style: italic;
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
        }
        .modal-link-full {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 16px;
            padding: 10px 20px;
            background: var(--gradient-pink);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .modal-link-full:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-pink);
        }

        /* WhatsApp button in modal */
        .modal-whatsapp-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #25D366;
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            margin-top: 10px;
        }
        .modal-whatsapp-btn:hover {
            background: #1da851;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
        }
        .modal-whatsapp-btn svg {
            width: 18px;
            height: 18px;
        }

        @media (max-width: 768px) {
            .order-modal {
                max-height: 95vh;
                border-radius: 16px;
            }
            .modal-grid {
                grid-template-columns: 1fr;
            }
            .order-modal-header {
                padding: 18px 20px;
            }
            .order-modal-body {
                padding: 18px 20px;
            }
        }
    </style>

    <script>
    const statusConfig = <?= json_encode($statuses) ?>;
    const statusColors = {
        'pending': '#F59E0B',
        'accepted': '#3B82F6',
        'in_progress': '#8B5CF6',
        'completed': '#10B981',
        'shipped': '#06B6D4',
        'cancelled': '#EF4444'
    };

    function openOrderModal(orderId) {
        const modal = document.getElementById('orderModal');
        const body = document.getElementById('modalBody');

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        body.innerHTML = '<div class="order-modal-loading"><div class="spinner"></div><p>Chargement...</p></div>';
        document.getElementById('modalOrderId').textContent = '#' + orderId;
        document.getElementById('modalOrderDate').textContent = '';

        fetch('/admin/orders.php?ajax=order_detail&id=' + orderId)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    body.innerHTML = '<p style="text-align:center;color:var(--gray);padding:40px;">Commande introuvable</p>';
                    return;
                }
                renderOrderModal(data, orderId);
            })
            .catch(() => {
                body.innerHTML = '<p style="text-align:center;color:#EF4444;padding:40px;">Erreur de chargement</p>';
            });
    }

    function closeOrderModal(event) {
        if (event && event.target !== event.currentTarget) return;
        const modal = document.getElementById('orderModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeOrderModal();
    });

    function esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatPriceJs(amount) {
        return parseFloat(amount).toFixed(2).replace('.', ',') + ' \u20AC';
    }

    function renderOrderModal(data, orderId) {
        const { order, customer, items, shipping_address } = data;
        const body = document.getElementById('modalBody');

        // Date
        const dateStr = order.created_at || '';
        document.getElementById('modalOrderDate').textContent = dateStr ? 'Passée le ' + dateStr : '';

        const statusInfo = statusConfig[order.status] || { label: order.status, icon: '' };
        const statusColor = statusColors[order.status] || '#999';

        // Customer phone for WhatsApp
        let customerPhone = '';
        if (customer && customer.phone) {
            customerPhone = customer.phone.replace(/\s+/g, '').replace(/^0/, '33');
        } else if (shipping_address && shipping_address.phone) {
            customerPhone = shipping_address.phone.replace(/\s+/g, '').replace(/^0/, '33');
        }

        let html = '';

        // Status bar
        html += `<div class="modal-status-bar">
            <div class="modal-status-current">
                <span class="modal-status-dot" style="background:${statusColor}"></span>
                <span class="modal-status-label">${statusInfo.icon || ''} ${esc(statusInfo.label)}</span>
            </div>
            <a href="/admin/order.php?id=${orderId}" class="modal-link-full">
                Voir la fiche complète &rarr;
            </a>
        </div>`;

        html += '<div class="modal-grid">';

        // Left column: Items
        html += '<div>';
        html += '<div class="modal-section">';
        html += `<div class="modal-section-title">Articles (${items.length})</div>`;

        items.forEach(item => {
            const c = item.customization || {};
            const imgSrc = item.product_image ? '/public' + esc(item.product_image) : '';

            html += `<div class="modal-item">
                <div class="modal-item-img">
                    ${imgSrc ? '<img src="' + imgSrc + '" alt="">' : '<span style="font-size:1.5rem">👕</span>'}
                </div>
                <div class="modal-item-details">
                    <div class="modal-item-name">${esc(item.product_name || 'Produit supprimé')}</div>
                    <div class="modal-item-tags">
                        ${c.size ? '<span class="modal-tag">Taille: ' + esc(c.size) + '</span>' : ''}
                        ${c.color ? '<span class="modal-tag">Couleur: ' + esc(c.color) + '</span>' : ''}
                        ${c.technique ? '<span class="modal-tag">Technique: ' + esc(c.technique) + '</span>' : ''}
                        ${c.text ? '<span class="modal-tag modal-tag-text">&laquo; ' + esc(c.text) + ' &raquo;</span>' : ''}
                    </div>
                </div>
                <div class="modal-item-price">
                    <div class="modal-item-qty">${item.quantity} × ${formatPriceJs(item.unit_price)}</div>
                    <div class="modal-item-subtotal">${formatPriceJs(item.quantity * item.unit_price)}</div>
                </div>
            </div>`;
        });

        // Totals
        html += `<div class="modal-totals">
            <div class="modal-total-row">
                <span>Sous-total</span>
                <span>${formatPriceJs(order.total)}</span>
            </div>
            <div class="modal-total-row">
                <span>Livraison</span>
                <span>Gratuite</span>
            </div>
            <div class="modal-total-row">
                <span>Total</span>
                <span>${formatPriceJs(order.total)}</span>
            </div>
        </div>`;

        html += '</div>'; // modal-section
        html += '</div>'; // left column

        // Right column: Customer + Address
        html += '<div>';

        // Customer
        html += '<div class="modal-section">';
        html += '<div class="modal-section-title">Client</div>';
        if (customer) {
            const fullName = ((customer.first_name || '') + ' ' + (customer.last_name || '')).trim();
            html += `<div class="modal-customer-name">${esc(fullName) || 'Client'}</div>`;
            if (customer.email) {
                html += `<div><a href="mailto:${esc(customer.email)}" class="modal-customer-email">${esc(customer.email)}</a></div>`;
            }
            if (customer.phone) {
                html += `<div class="modal-customer-phone">${esc(customer.phone)}</div>`;
            }
        } else {
            html += '<p style="color:var(--gray);font-size:14px;">Client anonyme</p>';
        }

        // WhatsApp button
        if (customerPhone) {
            const whatsappMsg = encodeURIComponent(
                'Bonjour ! Concernant votre commande #' + orderId + ' chez PERSONNALY, ' +
                'le statut est maintenant : ' + (statusInfo.label || order.status) + '.\n' +
                'Merci pour votre confiance !'
            );
            html += `<a href="https://wa.me/${customerPhone}?text=${whatsappMsg}" target="_blank" rel="noopener" class="modal-whatsapp-btn">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.611.611l4.458-1.495A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.594-.768-6.398-2.07l-.446-.334-3.177 1.065 1.065-3.177-.334-.446A9.935 9.935 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                WhatsApp client
            </a>`;
        }
        html += '</div>';

        // Shipping address
        html += '<div class="modal-section">';
        html += '<div class="modal-section-title">Adresse de livraison</div>';
        if (shipping_address) {
            const addrName = ((shipping_address.first_name || '') + ' ' + (shipping_address.last_name || '')).trim();
            html += `<div class="modal-address">
                ${addrName ? '<strong>' + esc(addrName) + '</strong><br>' : ''}
                ${esc(shipping_address.address || '')}<br>
                ${esc((shipping_address.zipcode || '') + ' ' + (shipping_address.city || ''))}
                ${shipping_address.phone ? '<br>Tél: ' + esc(shipping_address.phone) : ''}
            </div>`;
        } else {
            html += '<p style="color:var(--gray);font-size:14px;">Adresse non renseignée</p>';
        }
        html += '</div>';

        // Notes
        if (order.notes) {
            html += '<div class="modal-section">';
            html += '<div class="modal-section-title">Notes</div>';
            html += `<div class="modal-notes">${esc(order.notes).replace(/\n/g, '<br>')}</div>`;
            html += '</div>';
        }

        html += '</div>'; // right column
        html += '</div>'; // modal-grid

        body.innerHTML = html;
    }
    </script>
</body>
</html>
