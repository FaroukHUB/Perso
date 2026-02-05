<?php
/**
 * PERSONNALY - Admin : Liste des campagnes email
 * Inspiré de Brevo - Tableau de bord campagnes
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/Settings.php';
require_once __DIR__ . '/../app/services/BrevoService.php';

Auth::requireAdmin();

$orderModel = new Order();
$pendingOrders = $orderModel->countNew();
$user = Auth::getUser();
$db = Database::getInstance();
$brevoService = new BrevoService();
$brevoStats = $brevoService->getStats();

// Suppression d'une campagne
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (verifyCsrf($_GET['token'] ?? '')) {
        $stmt = $db->prepare('DELETE FROM email_campaigns WHERE id = ? AND status = "draft"');
        $stmt->execute([(int)$_GET['delete']]);
        redirect('/admin/campaigns.php?success=Campagne supprimée');
    }
}

// Dupliquer une campagne
if (isset($_GET['duplicate']) && is_numeric($_GET['duplicate'])) {
    if (verifyCsrf($_GET['token'] ?? '')) {
        $srcId = (int)$_GET['duplicate'];
        $stmt = $db->prepare('SELECT * FROM email_campaigns WHERE id = ?');
        $stmt->execute([$srcId]);
        $src = $stmt->fetch();
        if ($src) {
            $stmt = $db->prepare('INSERT INTO email_campaigns (name, subject, preheader, content, sender_name, sender_email, status, recipients_type, recipients_data) VALUES (?, ?, ?, ?, ?, ?, "draft", ?, ?)');
            $stmt->execute([
                $src['name'] . ' (copie)',
                $src['subject'],
                $src['preheader'],
                $src['content'],
                $src['sender_name'],
                $src['sender_email'],
                $src['recipients_type'],
                $src['recipients_data'],
            ]);
            $newId = $db->lastInsertId();
            redirect('/admin/campaign-editor.php?id=' . $newId . '&success=Campagne dupliquée');
        }
    }
}

$success = $_GET['success'] ?? '';

// Filtrer par statut
$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT * FROM email_campaigns';
$params = [];
if ($statusFilter) {
    $sql .= ' WHERE status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$campaigns = $stmt->fetchAll();

// Compteurs
$stmtCounts = $db->query("SELECT status, COUNT(*) as cnt FROM email_campaigns GROUP BY status");
$counts = [];
foreach ($stmtCounts->fetchAll() as $row) {
    $counts[$row['status']] = $row['cnt'];
}
$totalCampaigns = array_sum($counts);

$statuses = [
    'draft' => ['label' => 'Brouillon', 'icon' => '✏️', 'color' => '#6C757D'],
    'scheduled' => ['label' => 'Planifiée', 'icon' => '🕐', 'color' => '#3B82F6'],
    'sending' => ['label' => 'En cours', 'icon' => '🚀', 'color' => '#F59E0B'],
    'sent' => ['label' => 'Envoyée', 'icon' => '✅', 'color' => '#10B981'],
    'paused' => ['label' => 'En pause', 'icon' => '⏸️', 'color' => '#8B5CF6'],
];

$pageTitle = 'Campagnes Email';
$currentPage = 'campaigns.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campagnes - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Campagnes Email</h1>
                    <p class="text-muted"><?= $totalCampaigns ?> campagne<?= $totalCampaigns > 1 ? 's' : '' ?></p>
                </div>
                <div style="display:flex;gap:12px;align-items:center;">
                    <!-- Quota Brevo -->
                    <div class="quota-badge <?= $brevoStats['enabled'] ? 'quota-active' : 'quota-inactive' ?>">
                        <span class="quota-dot"></span>
                        <?php if ($brevoStats['enabled']): ?>
                            <?= $brevoStats['daily_remaining'] ?>/<?= $brevoStats['daily_limit'] ?> emails restants
                        <?php else: ?>
                            Brevo non configuré
                        <?php endif; ?>
                    </div>
                    <a href="/admin/campaign-editor.php" class="btn btn-primary">
                        + Nouvelle campagne
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <!-- Filtres -->
            <div class="campaign-filters">
                <a href="/admin/campaigns.php" class="filter-chip <?= !$statusFilter ? 'active' : '' ?>">
                    Toutes <span class="filter-count"><?= $totalCampaigns ?></span>
                </a>
                <?php foreach ($statuses as $key => $status): ?>
                    <a href="/admin/campaigns.php?status=<?= $key ?>" class="filter-chip <?= $statusFilter === $key ? 'active' : '' ?>">
                        <?= $status['icon'] ?> <?= $status['label'] ?>
                        <?php if (!empty($counts[$key])): ?>
                            <span class="filter-count"><?= $counts[$key] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($campaigns)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📧</div>
                    <h3>Aucune campagne <?= $statusFilter ? 'avec ce statut' : '' ?></h3>
                    <p>Créez votre première campagne email pour communiquer avec vos clients.</p>
                    <a href="/admin/campaign-editor.php" class="btn btn-primary" style="margin-top:16px;">
                        + Créer une campagne
                    </a>
                </div>
            <?php else: ?>
                <div class="campaigns-list">
                    <?php foreach ($campaigns as $campaign):
                        $st = $statuses[$campaign['status']] ?? ['label' => $campaign['status'], 'icon' => '?', 'color' => '#999'];
                        $openRate = $campaign['total_sent'] > 0 ? round(($campaign['total_opened'] / $campaign['total_sent']) * 100, 1) : 0;
                        $clickRate = $campaign['total_sent'] > 0 ? round(($campaign['total_clicked'] / $campaign['total_sent']) * 100, 1) : 0;
                    ?>
                        <div class="campaign-card">
                            <div class="campaign-card-left">
                                <div class="campaign-status-dot" style="background:<?= $st['color'] ?>;"></div>
                                <div class="campaign-card-info">
                                    <a href="/admin/campaign-editor.php?id=<?= $campaign['id'] ?>" class="campaign-name">
                                        <?= h($campaign['name'] ?: 'Sans titre') ?>
                                    </a>
                                    <div class="campaign-subject"><?= h($campaign['subject'] ?: 'Pas de sujet') ?></div>
                                    <div class="campaign-meta">
                                        <span class="campaign-badge" style="background:<?= $st['color'] ?>15;color:<?= $st['color'] ?>;">
                                            <?= $st['icon'] ?> <?= $st['label'] ?>
                                        </span>
                                        <span class="campaign-date"><?= formatDate($campaign['created_at']) ?></span>
                                        <?php if ($campaign['sent_at']): ?>
                                            <span class="campaign-date">Envoyée le <?= formatDate($campaign['sent_at']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="campaign-card-stats">
                                <?php if ($campaign['total_sent'] > 0): ?>
                                    <div class="campaign-stat">
                                        <span class="campaign-stat-value"><?= $campaign['total_sent'] ?></span>
                                        <span class="campaign-stat-label">Envoyés</span>
                                    </div>
                                    <div class="campaign-stat">
                                        <span class="campaign-stat-value"><?= $openRate ?>%</span>
                                        <span class="campaign-stat-label">Ouvertures</span>
                                    </div>
                                    <div class="campaign-stat">
                                        <span class="campaign-stat-value"><?= $clickRate ?>%</span>
                                        <span class="campaign-stat-label">Clics</span>
                                    </div>
                                <?php else: ?>
                                    <div class="campaign-stat">
                                        <span class="campaign-stat-value"><?= $campaign['total_recipients'] ?></span>
                                        <span class="campaign-stat-label">Destinataires</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="campaign-card-actions">
                                <a href="/admin/campaign-editor.php?id=<?= $campaign['id'] ?>" class="action-btn" title="Modifier">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                                <a href="/admin/campaigns.php?duplicate=<?= $campaign['id'] ?>&token=<?= generateCsrf() ?>" class="action-btn" title="Dupliquer">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                </a>
                                <?php if ($campaign['status'] === 'draft'): ?>
                                    <a href="/admin/campaigns.php?delete=<?= $campaign['id'] ?>&token=<?= generateCsrf() ?>" class="action-btn action-btn-danger" title="Supprimer" onclick="return confirm('Supprimer cette campagne ?')">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
        .quota-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
        }
        .quota-active { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .quota-inactive { background: rgba(239, 68, 68, 0.1); color: #EF4444; }
        .quota-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        .campaign-filters {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: white;
            border: 2px solid transparent;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray);
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .filter-chip:hover { border-color: var(--pink-light); color: var(--pink-main); }
        .filter-chip.active {
            background: var(--gradient-pink);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 15px rgba(255,105,180,0.3);
        }
        .filter-chip.active .filter-count { background: rgba(255,255,255,0.3); color: white; }
        .filter-count {
            background: var(--gray-light);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
        }
        .empty-icon { font-size: 4rem; margin-bottom: 16px; }
        .empty-state h3 { font-size: 1.3rem; margin-bottom: 8px; }
        .empty-state p { color: var(--gray); max-width: 400px; margin: 0 auto; }

        .campaigns-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .campaign-card {
            display: flex;
            align-items: center;
            gap: 20px;
            background: white;
            padding: 20px 24px;
            border-radius: 14px;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .campaign-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        .campaign-card-left {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            flex: 1;
            min-width: 0;
        }
        .campaign-status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 6px;
        }
        .campaign-card-info { flex: 1; min-width: 0; }
        .campaign-name {
            font-weight: 700;
            font-size: 15px;
            color: var(--black-soft);
            text-decoration: none;
            display: block;
            margin-bottom: 4px;
        }
        .campaign-name:hover { color: var(--pink-main); }
        .campaign-subject {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .campaign-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .campaign-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .campaign-date {
            font-size: 12px;
            color: var(--gray);
        }
        .campaign-card-stats {
            display: flex;
            gap: 24px;
            flex-shrink: 0;
        }
        .campaign-stat {
            text-align: center;
        }
        .campaign-stat-value {
            display: block;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--black-soft);
        }
        .campaign-stat-label {
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .campaign-card-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }
        .action-btn {
            width: 36px; height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--gray-light);
            color: var(--gray);
            transition: all 0.2s;
            text-decoration: none;
        }
        .action-btn:hover { background: var(--pink-light); color: var(--pink-dark); }
        .action-btn-danger:hover { background: #FEE2E2; color: #EF4444; }

        @media (max-width: 768px) {
            .campaign-card { flex-direction: column; align-items: stretch; }
            .campaign-card-stats { justify-content: space-around; padding-top: 12px; border-top: 1px solid var(--gray-light); }
            .campaign-card-actions { justify-content: flex-end; }
        }
    </style>
</body>
</html>
