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
    SELECT oc.*, p.name as product_name, p.category as product_category, p.image_front_url as product_image
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
            position: relative;
            cursor: pointer;
            overflow: hidden;
        }
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 6px;
        }
        .item-image:hover .zoom-overlay {
            opacity: 1;
        }
        .zoom-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 105, 180, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.2s;
            border-radius: var(--radius-md);
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

        /* Personnalisation détaillée */
        .item-personalization {
            margin-top: 15px;
            padding: 15px;
            background: linear-gradient(135deg, rgba(255,105,180,0.05), rgba(61,255,192,0.05));
            border-radius: var(--radius-md);
            border-left: 3px solid var(--pink-main);
        }
        .item-personalization h4 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--pink-dark);
            margin-bottom: 12px;
        }
        .personalization-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }
        .perso-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .perso-label {
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .perso-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--black-soft);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .perso-text {
            font-style: italic;
            color: var(--pink-dark);
        }
        .color-preview {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

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
        <?php include __DIR__ . '/includes/sidebar-alt.php'; ?>

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
                            <div class="item-image" onclick="openOrderLightbox(this)"
                                 data-img="<?= !empty($item['product_image']) ? '/public' . h($item['product_image']) : '' ?>"
                                 data-text="<?= h($customization['text'] ?? '') ?>"
                                 data-font="<?= h($customization['font'] ?? 'Poppins') ?>"
                                 data-text-color="<?= h($customization['text_color'] ?? '#FF1493') ?>"
                                 data-technique="<?= h($customization['technique'] ?? 'flex') ?>"
                                 data-name="<?= h($item['product_name'] ?? 'Produit') ?>">
                                <?php if (!empty($item['product_image'])): ?>
                                    <img src="/public<?= h($item['product_image']) ?>" alt="<?= h($item['product_name']) ?>">
                                <?php else: ?>
                                    👕
                                <?php endif; ?>
                                <div class="zoom-overlay">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                        <line x1="11" y1="8" x2="11" y2="14"/>
                                        <line x1="8" y1="11" x2="14" y2="11"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="item-details">
                                <h3><?= h($item['product_name'] ?? 'Produit supprimé') ?></h3>
                                <div class="item-customization">
                                    <span class="custom-tag">
                                        Taille: <strong><?= h($customization['size'] ?? 'M') ?></strong>
                                    </span>
                                    <span class="custom-tag">
                                        Couleur produit: <strong><?= ucfirst(h($customization['color'] ?? 'blanc')) ?></strong>
                                    </span>
                                    <?php if (!empty($customization['technique'])): ?>
                                        <span class="custom-tag">
                                            Technique: <strong><?= ucfirst(h($customization['technique'])) ?></strong>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($customization['view'])): ?>
                                        <span class="custom-tag">
                                            Face: <strong><?= $customization['view'] === 'front' ? 'Avant' : 'Dos' ?></strong>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($customization['text'])): ?>
                                <div class="item-personalization">
                                    <h4>Personnalisation texte</h4>
                                    <div class="personalization-grid">
                                        <div class="perso-item">
                                            <span class="perso-label">Texte</span>
                                            <span class="perso-value perso-text">"<?= h($customization['text']) ?>"</span>
                                        </div>
                                        <div class="perso-item">
                                            <span class="perso-label">Police</span>
                                            <span class="perso-value" style="font-family: '<?= h($customization['font'] ?? 'Poppins') ?>'"><?= h($customization['font'] ?? 'Poppins') ?></span>
                                        </div>
                                        <div class="perso-item">
                                            <span class="perso-label">Couleur texte</span>
                                            <span class="perso-value">
                                                <span class="color-preview" style="background: <?= h($customization['text_color'] ?? '#000') ?>"></span>
                                                <?= ucfirst(h($customization['text_color'] ?? 'noir')) ?>
                                            </span>
                                        </div>
                                        <?php if (isset($customization['position']) && is_array($customization['position'])): ?>
                                        <div class="perso-item">
                                            <span class="perso-label">Position</span>
                                            <span class="perso-value">X: <?= $customization['position']['x'] ?? 50 ?>% / Y: <?= $customization['position']['y'] ?? 50 ?>%</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
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

    <!-- Lightbox Component -->
    <script src="/public/assets/js/lightbox.js"></script>
    <script>
        function openOrderLightbox(el) {
            if (!window.PersonnalyLightbox) return;

            const imgSrc = el.dataset.img;
            if (!imgSrc) return;

            PersonnalyLightbox.open({
                imageSrc: imgSrc,
                imageAlt: el.dataset.name || 'Produit',
                text: el.dataset.text || null,
                font: (el.dataset.font || 'Poppins') + ', sans-serif',
                fontSize: '2rem',
                textX: 50,
                textY: 50,
                textColor: el.dataset.textColor || '#FF1493',
                technique: el.dataset.technique || 'flex'
            });
        }
    </script>
</body>
</html>
