<?php
/**
 * PERSONNALY - Admin : Gestion des Clients
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$userModel = new User();
$orderModel = new Order();
$pendingOrders = $orderModel->countNew();

// Récupération des clients (role = client)
$db = Database::getInstance();
$stmt = $db->query("SELECT * FROM users WHERE role = 'client' ORDER BY created_at DESC");
$clients = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - PERSONNALY Admin</title>
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
                <h1 class="page-title">Gestion des <span>Clients</span></h1>
            </div>

            <div class="data-card">
                <div class="data-card-header">
                    <h3 class="data-card-title"><?= count($clients) ?> client(s)</h3>
                </div>

                <?php if (empty($clients)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">👥</div>
                        <h4>Aucun client inscrit</h4>
                        <p class="text-muted">Les clients apparaîtront ici après inscription</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Email</th>
                                <th>Inscrit le</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td>
                                        <a href="/admin/customer.php?id=<?= $client['id'] ?>" style="color: var(--pink-main); text-decoration: none; font-weight: 600;">
                                            <?= h($client['first_name'] ?? '') ?> <?= h($client['last_name'] ?? '') ?>
                                            <?php if (empty($client['first_name']) && empty($client['last_name'])): ?>
                                                <span style="color: var(--gray); font-weight: 400;">Non renseigné</span>
                                            <?php endif; ?>
                                        </a>
                                    </td>
                                    <td><?= h($client['email']) ?></td>
                                    <td class="text-muted"><?= formatDate($client['created_at']) ?></td>
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
