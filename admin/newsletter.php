<?php
/**
 * PERSONNALY - Admin : Newsletter Subscribers
 * Gestion des abonnés newsletter avec stats, filtres et export
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$orderModel = new Order();
$pendingOrders = $orderModel->countNew();
$db = Database::getInstance();

$success = '';
$error = '';

// === ACTIONS ===

// Export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        die('CSRF token invalide');
    }

    // Récupérer tous les abonnés actifs
    $stmt = $db->query("SELECT email, source, status, created_at FROM newsletter_subscribers ORDER BY created_at DESC");
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Headers pour téléchargement
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"');

    // BOM UTF-8 pour Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // En-têtes
    fputcsv($output, ['Email', 'Source', 'Statut', 'Date d\'inscription'], ';');

    // Données
    foreach ($subscribers as $sub) {
        fputcsv($output, [
            $sub['email'],
            $sub['source'],
            $sub['status'] === 'active' ? 'Actif' : 'Désabonné',
            formatDate($sub['created_at'], 'd/m/Y H:i')
        ], ';');
    }

    fclose($output);
    exit;
}

// Désabonner
if (isPost() && post('action') === 'unsubscribe') {
    if (!verifyCsrf(post('csrf_token', ''))) {
        $error = 'Token CSRF invalide';
    } else {
        $id = (int)post('id');
        try {
            $stmt = $db->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Abonné désabonné avec succès.';
        } catch (Exception $e) {
            $error = 'Erreur lors de la désabonnement: ' . $e->getMessage();
        }
    }
}

// Supprimer
if (isPost() && post('action') === 'delete') {
    if (!verifyCsrf(post('csrf_token', ''))) {
        $error = 'Token CSRF invalide';
    } else {
        $id = (int)post('id');
        try {
            $stmt = $db->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Abonné supprimé définitivement.';
        } catch (Exception $e) {
            $error = 'Erreur lors de la suppression: ' . $e->getMessage();
        }
    }
}

// Réactiver
if (isPost() && post('action') === 'reactivate') {
    if (!verifyCsrf(post('csrf_token', ''))) {
        $error = 'Token CSRF invalide';
    } else {
        $id = (int)post('id');
        try {
            $stmt = $db->prepare("UPDATE newsletter_subscribers SET status = 'active', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Abonné réactivé avec succès.';
        } catch (Exception $e) {
            $error = 'Erreur lors de la réactivation: ' . $e->getMessage();
        }
    }
}

// === STATISTIQUES ===

// Total des abonnés
$stmt = $db->query("SELECT COUNT(*) FROM newsletter_subscribers");
$totalSubscribers = (int)$stmt->fetchColumn();

// Abonnés actifs
$stmt = $db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'");
$activeSubscribers = (int)$stmt->fetchColumn();

// Désabonnés
$unsubscribed = $totalSubscribers - $activeSubscribers;

// Nouveaux aujourd'hui
$stmt = $db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE DATE(created_at) = CURDATE()");
$newToday = (int)$stmt->fetchColumn();

// === FILTRES ===

$filterEmail = get('email', '');
$filterStatus = get('status', '');
$filterDateFrom = get('date_from', '');
$filterDateTo = get('date_to', '');
$page = max(1, (int)get('page', 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Construire la requête avec filtres
$where = [];
$params = [];

if (!empty($filterEmail)) {
    $where[] = "email LIKE ?";
    $params[] = '%' . $filterEmail . '%';
}

if (!empty($filterStatus)) {
    $where[] = "status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterDateFrom)) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $filterDateTo;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Compter le total avec filtres
$stmt = $db->prepare("SELECT COUNT(*) FROM newsletter_subscribers $whereClause");
$stmt->execute($params);
$totalFiltered = (int)$stmt->fetchColumn();

// Récupérer les abonnés
$stmt = $db->prepare("
    SELECT * FROM newsletter_subscribers
    $whereClause
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination
$totalPages = ceil($totalFiltered / $perPage);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--pink-main);
            font-family: 'Poppins', sans-serif;
        }

        .stat-card.secondary .stat-value {
            color: #667eea;
        }

        .stat-card.success .stat-value {
            color: #48bb78;
        }

        .stat-card.warning .stat-value {
            color: #ed8936;
        }

        .filters-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            border: 1px solid #f0f0f0;
        }

        .filters-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #555;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.875rem;
        }

        .filters-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-filter {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-filter.primary {
            background: var(--pink-main);
            color: white;
        }

        .btn-filter.primary:hover {
            background: var(--pink-dark);
        }

        .btn-filter.secondary {
            background: #f7fafc;
            color: #4a5568;
        }

        .btn-filter.secondary:hover {
            background: #edf2f7;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .btn-export,
        .btn-campaign {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-export {
            background: #48bb78;
            color: white;
        }

        .btn-export:hover {
            background: #38a169;
        }

        .btn-campaign {
            background: #667eea;
            color: white;
        }

        .btn-campaign:hover {
            background: #5568d3;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge.unsubscribed {
            background: #fed7d7;
            color: #742a2a;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.375rem 0.75rem;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-action.warning {
            background: #feebc8;
            color: #7c2d12;
        }

        .btn-action.warning:hover {
            background: #fbd38d;
        }

        .btn-action.danger {
            background: #fed7d7;
            color: #742a2a;
        }

        .btn-action.danger:hover {
            background: #fc8181;
        }

        .btn-action.success {
            background: #c6f6d5;
            color: #22543d;
        }

        .btn-action.success:hover {
            background: #9ae6b4;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .pagination a {
            background: #f7fafc;
            color: #4a5568;
        }

        .pagination a:hover {
            background: #edf2f7;
        }

        .pagination .current {
            background: var(--pink-main);
            color: white;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .alert.error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Abonnés <span>Newsletter</span></h1>
            </div>

            <?php if ($success): ?>
                <div class="alert success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Abonnés</div>
                    <div class="stat-value"><?= $totalSubscribers ?></div>
                </div>

                <div class="stat-card success">
                    <div class="stat-label">Actifs</div>
                    <div class="stat-value"><?= $activeSubscribers ?></div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-label">Désabonnés</div>
                    <div class="stat-value"><?= $unsubscribed ?></div>
                </div>

                <div class="stat-card secondary">
                    <div class="stat-label">Nouveaux aujourd'hui</div>
                    <div class="stat-value"><?= $newToday ?></div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="filters-card">
                <div class="filters-title">Filtres de recherche</div>
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Email</label>
                            <input type="text" name="email" placeholder="Rechercher par email..." value="<?= h($filterEmail) ?>">
                        </div>

                        <div class="filter-group">
                            <label>Statut</label>
                            <select name="status">
                                <option value="">Tous</option>
                                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Actifs</option>
                                <option value="unsubscribed" <?= $filterStatus === 'unsubscribed' ? 'selected' : '' ?>>Désabonnés</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Date début</label>
                            <input type="date" name="date_from" value="<?= h($filterDateFrom) ?>">
                        </div>

                        <div class="filter-group">
                            <label>Date fin</label>
                            <input type="date" name="date_to" value="<?= h($filterDateTo) ?>">
                        </div>
                    </div>

                    <div class="filters-actions">
                        <button type="submit" class="btn-filter primary">Appliquer les filtres</button>
                        <a href="/admin/newsletter.php" class="btn-filter secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <!-- Actions -->
            <div class="actions-bar">
                <div>
                    <a href="/admin/newsletter.php?action=export_csv&csrf_token=<?= h(csrfToken()) ?>" class="btn-export">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Exporter en CSV
                    </a>
                </div>
                <div>
                    <a href="/admin/campaign-editor.php?recipients=newsletter" class="btn-campaign">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        Envoyer une campagne
                    </a>
                </div>
            </div>

            <!-- Liste des abonnés -->
            <div class="data-card">
                <div class="data-card-header">
                    <h3 class="data-card-title">
                        <?= $totalFiltered ?> abonné(s)
                        <?php if ($filterEmail || $filterStatus || $filterDateFrom || $filterDateTo): ?>
                            <span style="color: #999; font-weight: 400; font-size: 0.875rem;">(filtrés)</span>
                        <?php endif; ?>
                    </h3>
                </div>

                <?php if (empty($subscribers)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📧</div>
                        <h4>Aucun abonné</h4>
                        <p class="text-muted">
                            <?php if ($filterEmail || $filterStatus || $filterDateFrom || $filterDateTo): ?>
                                Aucun résultat ne correspond à vos filtres
                            <?php else: ?>
                                Les abonnés à la newsletter apparaîtront ici
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Source</th>
                                <th>Statut</th>
                                <th>Inscrit le</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscribers as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($sub['email']) ?></strong>
                                    </td>
                                    <td class="text-muted"><?= h($sub['source']) ?></td>
                                    <td>
                                        <span class="status-badge <?= h($sub['status']) ?>">
                                            <?= $sub['status'] === 'active' ? 'Actif' : 'Désabonné' ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= formatDate($sub['created_at']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($sub['status'] === 'active'): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Désabonner cet utilisateur ?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="unsubscribe">
                                                    <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                                    <button type="submit" class="btn-action warning">Désabonner</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="reactivate">
                                                    <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                                    <button type="submit" class="btn-action success">Réactiver</button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer définitivement cet abonné ?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                                <button type="submit" class="btn-action danger">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $filterEmail ? '&email=' . urlencode($filterEmail) : '' ?><?= $filterStatus ? '&status=' . $filterStatus : '' ?><?= $filterDateFrom ? '&date_from=' . $filterDateFrom : '' ?><?= $filterDateTo ? '&date_to=' . $filterDateTo : '' ?>">« Précédent</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <?php if ($i === $page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $i ?><?= $filterEmail ? '&email=' . urlencode($filterEmail) : '' ?><?= $filterStatus ? '&status=' . $filterStatus : '' ?><?= $filterDateFrom ? '&date_from=' . $filterDateFrom : '' ?><?= $filterDateTo ? '&date_to=' . $filterDateTo : '' ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $filterEmail ? '&email=' . urlencode($filterEmail) : '' ?><?= $filterStatus ? '&status=' . $filterStatus : '' ?><?= $filterDateFrom ? '&date_from=' . $filterDateFrom : '' ?><?= $filterDateTo ? '&date_to=' . $filterDateTo : '' ?>">Suivant »</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
