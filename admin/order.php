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

// Récupérer le numéro de téléphone du client
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
        /* ==========================================
           ORDER PAGE - ALL STYLES
           ========================================== */

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
            color: var(--black-soft, #1a1a2e);
        }
        .order-id {
            background: var(--gradient-pink, linear-gradient(135deg, #FF69B4, #FF1493));
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14px;
        }
        .order-date {
            color: var(--gray, #6C757D);
            font-size: 14px;
        }
        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray, #6C757D);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .back-btn:hover { color: var(--pink-main, #FF69B4); }

        .order-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
            align-items: start;
        }

        /* Status Card */
        .status-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
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
            color: var(--black-soft, #1a1a2e);
        }
        .status-form {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .status-select {
            padding: 10px 16px;
            border: 2px solid #e5e5e5;
            border-radius: 12px;
            font-size: 14px;
            min-width: 160px;
            background: white;
            font-family: inherit;
        }
        .status-select:focus {
            outline: none;
            border-color: var(--pink-main, #FF69B4);
        }
        .status-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Items Card */
        .items-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--black-soft, #1a1a2e);
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
            background: #f5f5f5;
            border-radius: 12px;
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
            border-radius: 12px;
        }
        .item-details h3 {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--black-soft, #1a1a2e);
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
            background: #f5f5f5;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 12px;
            color: #6C757D;
        }
        .custom-tag strong { color: var(--black-soft, #1a1a2e); }
        .custom-text-tag {
            background: rgba(255, 105, 180, 0.1);
            color: #c2185b;
        }
        .custom-text-tag strong { color: #c2185b; }
        .item-price {
            text-align: right;
        }
        .item-qty {
            font-size: 13px;
            color: #6C757D;
            margin-bottom: 5px;
        }
        .item-subtotal {
            font-weight: 700;
            font-size: 1.1rem;
            color: #c2185b;
        }

        /* Totals */
        .order-totals {
            padding: 20px 25px;
            background: #f9fafb;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 15px;
            color: var(--black-soft, #1a1a2e);
        }
        .total-row.grand-total {
            font-size: 1.3rem;
            font-weight: 700;
            padding-top: 15px;
            margin-top: 10px;
            border-top: 2px solid rgba(0,0,0,0.1);
        }
        .total-row.grand-total span:last-child {
            color: #c2185b;
        }

        /* Sidebar Cards */
        .order-sidebar-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .order-sidebar-card h3 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6C757D;
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
            color: var(--black-soft, #1a1a2e);
        }
        .customer-email a {
            color: var(--pink-main, #FF69B4);
            text-decoration: none;
        }
        .customer-phone {
            color: #6C757D;
        }
        .view-customer-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            color: var(--pink-main, #FF69B4);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        .view-customer-btn:hover { text-decoration: underline; }

        .address-block {
            line-height: 1.7;
            color: var(--black-soft, #1a1a2e);
        }

        .notes-block {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 12px;
            font-style: italic;
            color: #6C757D;
            line-height: 1.6;
        }

        /* Empty state */
        .empty-items {
            padding: 40px 25px;
            text-align: center;
            color: #6C757D;
        }
        .empty-items svg { margin-bottom: 12px; opacity: 0.3; }

        /* ==========================================
           WHATSAPP BUTTON - Ultra Moderne
           ========================================== */

        .whatsapp-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 28px;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 50%, #075E54 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4), 0 0 0 0 rgba(37, 211, 102, 0.3);
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
            animation: waGlow 2s ease-in-out infinite alternate;
        }
        @keyframes waGlow {
            0% { box-shadow: 0 4px 20px rgba(37, 211, 102, 0.35), 0 0 0 0 rgba(37, 211, 102, 0); }
            100% { box-shadow: 0 6px 28px rgba(37, 211, 102, 0.5), 0 0 0 4px rgba(37, 211, 102, 0.08); }
        }
        .whatsapp-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        .whatsapp-btn:hover {
            transform: translateY(-3px) scale(1.04);
            box-shadow: 0 8px 32px rgba(37, 211, 102, 0.55), 0 0 0 4px rgba(37, 211, 102, 0.12);
        }
        .whatsapp-btn:hover::before {
            left: 100%;
        }
        .whatsapp-btn:active {
            transform: translateY(-1px) scale(0.98);
        }
        .whatsapp-btn .wa-icon {
            width: 20px;
            height: 20px;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.15));
        }

        /* ==========================================
           WHATSAPP MODAL
           ========================================== */

        .wa-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(6px);
        }
        .wa-modal-overlay.active { display: flex; }
        .wa-modal {
            background: white;
            border-radius: 24px;
            width: 100%;
            max-width: 520px;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0,0,0,0.25);
            animation: waSlideIn 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes waSlideIn {
            from { opacity: 0; transform: translateY(30px) scale(0.92); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .wa-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 28px;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
        }
        .wa-modal-header h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .wa-modal-close {
            width: 34px;
            height: 34px;
            border: none;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .wa-modal-close:hover { background: rgba(255,255,255,0.35); }
        .wa-modal-body { padding: 28px; }
        .wa-form-group {
            margin-bottom: 20px;
        }
        .wa-form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .wa-textarea {
            width: 100%;
            min-height: 130px;
            padding: 14px 18px;
            border: 2px solid #e8e8e8;
            border-radius: 14px;
            font-size: 14px;
            font-family: inherit;
            line-height: 1.6;
            resize: vertical;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .wa-textarea:focus {
            outline: none;
            border-color: #25D366;
            box-shadow: 0 0 0 4px rgba(37, 211, 102, 0.1);
        }
        .wa-file-upload {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .wa-file-btn {
            padding: 10px 18px;
            background: #f9fafb;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            font-size: 13px;
            cursor: pointer;
            color: #6C757D;
            font-weight: 600;
            transition: border-color 0.2s, background 0.2s;
        }
        .wa-file-btn:hover {
            border-color: #25D366;
            background: rgba(37, 211, 102, 0.04);
            color: #128C7E;
        }
        .wa-file-name {
            font-size: 13px;
            color: #6C757D;
        }
        .wa-hint {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 8px;
        }
        .wa-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .wa-send-btn {
            flex: 1;
            padding: 14px 24px;
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .wa-send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
        }
        .wa-cancel-btn {
            padding: 14px 24px;
            background: #f3f4f6;
            border: none;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            color: #6C757D;
            transition: background 0.2s;
        }
        .wa-cancel-btn:hover { background: #e5e7eb; }

        /* Alert */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .alert-success {
            background: rgba(61, 255, 192, 0.15);
            color: #0d7c66;
            border: 1px solid rgba(61, 255, 192, 0.3);
        }

        @media (max-width: 968px) {
            .order-grid { grid-template-columns: 1fr; }
            .order-item { grid-template-columns: 60px 1fr; }
            .item-price { grid-column: span 2; text-align: left; margin-top: 10px; }
            .status-row { flex-direction: column; align-items: flex-start; }
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
                    <div class="status-actions">
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
                        <?php if ($customerPhone): ?>
                            <button type="button" class="whatsapp-btn" onclick="sendWhatsAppStatus()" title="Envoyer un WhatsApp au client">
                                <svg class="wa-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.611.611l4.458-1.495A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.594-.768-6.398-2.07l-.446-.334-3.177 1.065 1.065-3.177-.334-.446A9.935 9.935 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                                WhatsApp
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- JavaScript loaded BEFORE items grid to ensure WhatsApp works even if items section has a PHP error -->
            <script src="/public/assets/js/lightbox.js"></script>
            <script>
                const customerPhone = '<?= $customerPhone ?>';
                const statusLabelsJs = <?= json_encode(array_map(function($s) { return $s['label']; }, $statusLabels)) ?>;

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

                function sendWhatsAppStatus() {
                    const modal = document.getElementById('waModal');
                    const select = document.getElementById('statusSelect');
                    const statusLabel = select ? (statusLabelsJs[select.value] || select.value) : '';

                    if (!modal) {
                        // Fallback: ouvrir WhatsApp directement
                        const msg = 'Bonjour ! Concernant votre commande #<?= $orderId ?>, le statut est : ' + statusLabel + '. Merci !';
                        window.open('https://wa.me/' + customerPhone + '?text=' + encodeURIComponent(msg), '_blank');
                        return;
                    }

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

                    const waUrl = 'https://wa.me/' + customerPhone + '?text=' + encodeURIComponent(finalMessage);
                    window.open(waUrl, '_blank');
                    closeWaModal();
                }

                document.addEventListener('keydown', e => {
                    if (e.key === 'Escape') closeWaModal();
                });
            </script>

            <div class="order-grid">
                <!-- Items -->
                <div class="items-card">
                    <div class="card-header">
                        Articles commandés (<?= count($items) ?>)
                    </div>

                    <?php if (empty($items)): ?>
                        <div class="empty-items">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                                <line x1="3" y1="6" x2="21" y2="6"/>
                            </svg>
                            <p>Aucun article dans cette commande</p>
                        </div>
                    <?php else: ?>
                        <?php try { foreach ($items as $item):
                            $customization = [];
                            if (!empty($item['data_json'])) {
                                $decoded = is_string($item['data_json'])
                                    ? json_decode($item['data_json'], true)
                                    : $item['data_json'];
                                if (is_array($decoded)) $customization = $decoded;
                            }
                            $qty = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                            $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
                            $productName = isset($item['product_name']) ? (string)$item['product_name'] : 'Produit supprimé';
                            $productImage = isset($item['product_image']) ? (string)$item['product_image'] : '';
                        ?>
                            <div class="order-item">
                                <div class="item-image" onclick="openOrderLightbox(this)"
                                     data-img="<?= $productImage ? '/public' . h($productImage) : '' ?>"
                                     data-text="<?= h((string)($customization['text'] ?? '')) ?>"
                                     data-font="<?= h((string)($customization['font'] ?? 'Poppins')) ?>"
                                     data-text-color="<?= h((string)($customization['text_color'] ?? '#FF1493')) ?>"
                                     data-technique="<?= h((string)($customization['technique'] ?? 'flex')) ?>"
                                     data-name="<?= h($productName) ?>">
                                    <?php if ($productImage): ?>
                                        <img src="/public<?= h($productImage) ?>" alt="<?= h($productName) ?>">
                                    <?php else: ?>
                                        <span style="font-size:2rem;">&#128085;</span>
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
                                    <h3><?= h($productName) ?></h3>
                                    <div class="item-customization">
                                        <?php if (!empty($customization['size'])): ?>
                                            <span class="custom-tag">
                                                Taille: <strong><?= h((string)$customization['size']) ?></strong>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($customization['color'])): ?>
                                            <span class="custom-tag">
                                                Couleur: <strong><?= ucfirst(h((string)$customization['color'])) ?></strong>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($customization['position'])): ?>
                                            <span class="custom-tag">
                                                Position: <strong><?= ucfirst(h((string)$customization['position'])) ?></strong>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($customization['technique'])): ?>
                                            <span class="custom-tag">
                                                Technique: <strong><?= ucfirst(h((string)$customization['technique'])) ?></strong>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($customization['text'])): ?>
                                            <span class="custom-tag custom-text-tag">
                                                Texte: <strong>"<?= h((string)$customization['text']) ?>"</strong>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($qty > 0): ?>
                                            <span class="custom-tag">
                                                Qté: <strong><?= $qty ?></strong>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="item-price">
                                    <?php if ($qty > 1): ?>
                                        <div class="item-qty"><?= $qty ?> &times; <?= formatPrice($unitPrice) ?></div>
                                    <?php endif; ?>
                                    <div class="item-subtotal"><?= formatPrice($qty * $unitPrice) ?></div>
                                </div>
                            </div>
                        <?php endforeach; } catch (\Throwable $e) { ?>
                            <div style="padding: 20px 25px; color: #EF4444; background: #FEF2F2; border-radius: 8px; margin: 10px;">
                                <strong>Erreur d'affichage :</strong> <?= h($e->getMessage()) ?>
                                <br><small><?= h($e->getFile()) ?>:<?= $e->getLine() ?></small>
                            </div>
                        <?php } ?>
                    <?php endif; ?>

                    <div class="order-totals">
                        <div class="total-row">
                            <span>Sous-total</span>
                            <span><?= formatPrice($order['total'] ?? 0) ?></span>
                        </div>
                        <div class="total-row">
                            <span>Livraison</span>
                            <span><?= !empty($order['shipping_cost']) ? formatPrice($order['shipping_cost']) : 'Gratuite' ?></span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total</span>
                            <span><?= formatPrice($order['total'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <!-- Customer -->
                    <div class="order-sidebar-card">
                        <h3>Client</h3>
                        <?php if ($customer): ?>
                            <div class="customer-info">
                                <div class="customer-name">
                                    <?= h(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: 'Client') ?>
                                </div>
                                <?php if (!empty($customer['email'])): ?>
                                    <div class="customer-email">
                                        <a href="mailto:<?= h($customer['email']) ?>"><?= h($customer['email']) ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($customer['phone'])): ?>
                                    <div class="customer-phone"><?= h($customer['phone']) ?></div>
                                <?php endif; ?>
                                <a href="/admin/customer.php?id=<?= $customer['id'] ?>" class="view-customer-btn">
                                    Voir la fiche client →
                                </a>
                            </div>
                        <?php else: ?>
                            <p style="color: #6C757D;">Client supprimé ou anonyme</p>
                        <?php endif; ?>
                    </div>

                    <!-- Shipping Address -->
                    <div class="order-sidebar-card">
                        <h3>Adresse de livraison</h3>
                        <?php if ($shippingAddress): ?>
                            <div class="address-block">
                                <strong><?= h(trim(($shippingAddress['first_name'] ?? '') . ' ' . ($shippingAddress['last_name'] ?? '')) ?: 'Destinataire') ?></strong><br>
                                <?php if (!empty($shippingAddress['address'])): ?>
                                    <?= h($shippingAddress['address']) ?><br>
                                <?php endif; ?>
                                <?= h(trim(($shippingAddress['zipcode'] ?? '') . ' ' . ($shippingAddress['city'] ?? ''))) ?>
                                <?php if (!empty($shippingAddress['phone'])): ?>
                                    <br>Tél: <?= h($shippingAddress['phone']) ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: #6C757D;">Adresse non renseignée</p>
                        <?php endif; ?>
                    </div>

                    <!-- Notes -->
                    <?php if (!empty($order['notes'])): ?>
                        <div class="order-sidebar-card">
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

    <!-- WhatsApp Message Modal -->
    <?php if ($customerPhone): ?>
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
                    <label>Message personnalisé</label>
                    <textarea class="wa-textarea" id="waMessage">Bonjour ! Concernant votre commande #<?= $orderId ?> chez PERSONNALY, le statut est maintenant : <?= $currentStatus['label'] ?>.
Merci pour votre confiance !</textarea>
                </div>
                <div class="wa-form-group">
                    <label>Joindre un fichier (optionnel)</label>
                    <div class="wa-file-upload">
                        <label class="wa-file-btn" for="waFile">
                            📎 Choisir un fichier
                        </label>
                        <input type="file" id="waFile" accept="image/*,video/*" style="display:none" onchange="updateWaFileName(this)">
                        <span class="wa-file-name" id="waFileName">Aucun fichier</span>
                    </div>
                    <p class="wa-hint">Le fichier sera uploadé et un lien sera ajouté au message. Formats: image, vidéo (max 50 Mo).</p>
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

    <!-- JS already loaded in the page body before items grid -->
</body>
</html>
