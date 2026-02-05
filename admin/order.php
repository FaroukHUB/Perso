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

// AJAX: Upload de fichier WhatsApp
if (isPost() && post('action') === 'upload_wa_file') {
    header('Content-Type: application/json');
    if (!verifyCsrf(post('csrf_token', ''))) {
        echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
        exit;
    }
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../public/uploads/whatsapp/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp','mp4','mov','avi','webm'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Format non supporté']);
            exit;
        }
        if ($_FILES['file']['size'] > 50 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 50 Mo)']);
            exit;
        }
        $filename = 'wa_' . $orderId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename)) {
            echo json_encode(['success' => true, 'url' => '/public/uploads/whatsapp/' . $filename]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur upload']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Aucun fichier']);
    }
    exit;
}

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
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <form method="post" class="status-form" id="statusForm">
                            <?= csrfField() ?>
                            <select name="status" class="status-select" id="statusSelect">
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
                        <?php
                            $customerPhone = '';
                            if ($customer && !empty($customer['phone'])) {
                                $customerPhone = preg_replace('/\s+/', '', $customer['phone']);
                                if (strpos($customerPhone, '0') === 0) {
                                    $customerPhone = '33' . substr($customerPhone, 1);
                                }
                            } elseif ($shippingAddress && !empty($shippingAddress['phone'])) {
                                $customerPhone = preg_replace('/\s+/', '', $shippingAddress['phone']);
                                if (strpos($customerPhone, '0') === 0) {
                                    $customerPhone = '33' . substr($customerPhone, 1);
                                }
                            }
                        ?>
                        <?php if ($customerPhone): ?>
                            <button type="button" class="whatsapp-status-btn" onclick="sendWhatsAppStatus()" title="Envoyer un WhatsApp au client">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.611.611l4.458-1.495A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.594-.768-6.398-2.07l-.446-.334-3.177 1.065 1.065-3.177-.334-.446A9.935 9.935 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                                WhatsApp
                            </button>
                        <?php endif; ?>
                    </div>
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
                                        Couleur: <strong><?= ucfirst(h($customization['color'] ?? 'blanc')) ?></strong>
                                    </span>
                                    <span class="custom-tag">
                                        Position: <strong><?= ucfirst(h($customization['position'] ?? 'centre')) ?></strong>
                                    </span>
                                    <?php if (!empty($customization['technique'])): ?>
                                        <span class="custom-tag">
                                            Technique: <strong><?= ucfirst(h($customization['technique'])) ?></strong>
                                        </span>
                                    <?php endif; ?>
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

    <style>
        .whatsapp-status-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
            letter-spacing: 0.3px;
        }
        .whatsapp-status-btn:hover {
            background: linear-gradient(135deg, #1ebe5d 0%, #0e7a6b 100%);
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.5);
        }
        .whatsapp-status-btn:active {
            transform: translateY(-1px) scale(0.98);
        }

        /* WhatsApp Modal */
        .wa-modal-overlay {
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
        .wa-modal-overlay.active { display: flex; }
        .wa-modal {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 520px;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0,0,0,0.25);
            animation: waSlideIn 0.3s ease;
        }
        @keyframes waSlideIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .wa-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            background: #25D366;
            color: white;
        }
        .wa-modal-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .wa-modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wa-modal-close:hover { background: rgba(255,255,255,0.3); }
        .wa-modal-body { padding: 24px; }
        .wa-form-group {
            margin-bottom: 16px;
        }
        .wa-form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
            margin-bottom: 6px;
        }
        .wa-textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px 16px;
            border: 2px solid #e5e5e5;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            line-height: 1.6;
            resize: vertical;
            transition: border-color 0.2s;
        }
        .wa-textarea:focus {
            outline: none;
            border-color: #25D366;
        }
        .wa-file-upload {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .wa-file-btn {
            padding: 8px 16px;
            background: var(--gray-light);
            border: 2px dashed rgba(0,0,0,0.1);
            border-radius: 10px;
            font-size: 13px;
            cursor: pointer;
            color: var(--gray);
            font-weight: 500;
            transition: all 0.2s;
        }
        .wa-file-btn:hover {
            border-color: #25D366;
            background: rgba(37, 211, 102, 0.05);
            color: #1da851;
        }
        .wa-file-name {
            font-size: 13px;
            color: var(--gray);
        }
        .wa-hint {
            font-size: 12px;
            color: var(--gray);
            margin-top: 6px;
        }
        .wa-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .wa-send-btn {
            flex: 1;
            padding: 12px 20px;
            background: #25D366;
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .wa-send-btn:hover {
            background: #1da851;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
        }
        .wa-cancel-btn {
            padding: 12px 20px;
            background: var(--gray-light);
            border: none;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            color: var(--gray);
            transition: all 0.2s;
        }
        .wa-cancel-btn:hover { background: #e5e5e5; }
    </style>

    <!-- WhatsApp Message Modal -->
    <?php if (!empty($customerPhone)): ?>
    <div class="wa-modal-overlay" id="waModal" onclick="if(event.target===this)closeWaModal()">
        <div class="wa-modal">
            <div class="wa-modal-header">
                <h3>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.611.611l4.458-1.495A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.594-.768-6.398-2.07l-.446-.334-3.177 1.065 1.065-3.177-.334-.446A9.935 9.935 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                    Envoyer un WhatsApp
                </h3>
                <button class="wa-modal-close" onclick="closeWaModal()">&times;</button>
            </div>
            <div class="wa-modal-body">
                <div class="wa-form-group">
                    <label>Message</label>
                    <textarea class="wa-textarea" id="waMessage">Bonjour ! Concernant votre commande #<?= $orderId ?> chez PERSONNALY, le statut est maintenant : <?= $currentStatus['label'] ?>.
Merci pour votre confiance !</textarea>
                </div>
                <div class="wa-form-group">
                    <label>Joindre un fichier (optionnel)</label>
                    <div class="wa-file-upload">
                        <label class="wa-file-btn" for="waFile">
                            Choisir un fichier
                        </label>
                        <input type="file" id="waFile" accept="image/*,video/*" style="display:none" onchange="updateWaFileName(this)">
                        <span class="wa-file-name" id="waFileName">Aucun fichier</span>
                    </div>
                    <p class="wa-hint">Le fichier sera uploadé et un lien sera ajouté au message. Formats: image, vidéo.</p>
                </div>
                <div class="wa-actions">
                    <button class="wa-cancel-btn" onclick="closeWaModal()">Annuler</button>
                    <button class="wa-send-btn" onclick="submitWhatsApp()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        Envoyer via WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

        // === WhatsApp Functions ===
        const customerPhone = '<?= $customerPhone ?? '' ?>';
        const statusLabelsJs = <?= json_encode(array_map(function($s) { return $s['label']; }, $statusLabels)) ?>;

        function sendWhatsAppStatus() {
            const modal = document.getElementById('waModal');
            if (!modal) return;

            // Update message with current status selection
            const select = document.getElementById('statusSelect');
            const selectedStatus = select.value;
            const statusLabel = statusLabelsJs[selectedStatus] || selectedStatus;

            document.getElementById('waMessage').value =
                'Bonjour ! Concernant votre commande #<?= $orderId ?> chez PERSONNALY, ' +
                'le statut est maintenant : ' + statusLabel + '.\n' +
                'Merci pour votre confiance !';

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeWaModal() {
            const modal = document.getElementById('waModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        function updateWaFileName(input) {
            const nameEl = document.getElementById('waFileName');
            nameEl.textContent = input.files[0] ? input.files[0].name : 'Aucun fichier';
        }

        async function submitWhatsApp() {
            const message = document.getElementById('waMessage').value;
            const fileInput = document.getElementById('waFile');
            let finalMessage = message;

            // If a file is selected, upload it first
            if (fileInput && fileInput.files[0]) {
                const formData = new FormData();
                formData.append('file', fileInput.files[0]);
                formData.append('csrf_token', '<?= generateCsrf() ?>');
                formData.append('action', 'upload_wa_file');

                try {
                    const resp = await fetch('/admin/order.php?id=<?= $orderId ?>', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await resp.json();
                    if (result.success && result.url) {
                        finalMessage += '\n\nFichier joint : ' + window.location.origin + result.url;
                    }
                } catch (e) {
                    // Continue without file
                }
            }

            // Open WhatsApp
            const waUrl = 'https://wa.me/' + customerPhone + '?text=' + encodeURIComponent(finalMessage);
            window.open(waUrl, '_blank');
            closeWaModal();
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeWaModal();
        });
    </script>
</body>
</html>
