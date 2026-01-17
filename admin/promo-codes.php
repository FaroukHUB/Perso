<?php
/**
 * PERSONNALY Admin - Codes Promo
 * Gestion des codes promotionnels avec saisie client
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/PromoCode.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$promoModel = new PromoCode();
$orderModel = new Order();
$pendingOrders = $orderModel->countNew();

// Actions
if (isset($_GET['action'])) {
    $id = (int) ($_GET['id'] ?? 0);

    switch ($_GET['action']) {
        case 'toggle':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $promoModel->toggleActive($id);
            }
            redirect('/admin/promo-codes.php');
            break;

        case 'delete':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $promoModel->delete($id);
            }
            redirect('/admin/promo-codes.php?deleted=1');
            break;

        case 'duplicate':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $newId = $promoModel->duplicate($id);
                if ($newId) {
                    redirect('/admin/promo-code-form.php?id=' . $newId);
                }
            }
            redirect('/admin/promo-codes.php');
            break;
    }
}

$promoCodes = $promoModel->findAll();
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Codes Promo - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Codes <span>Promo</span></h1>
                    <p class="page-subtitle">Les clients saisissent ces codes sur la page panier</p>
                </div>
                <a href="/admin/promo-code-form.php" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nouveau code
                </a>
            </div>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">Code promo supprimé.</div>
            <?php endif; ?>
            <?php if (isset($_GET['saved'])): ?>
                <div class="alert alert-success">Code promo enregistré.</div>
            <?php endif; ?>

            <?php if (empty($promoCodes)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                            <line x1="7" y1="7" x2="7.01" y2="7"/>
                        </svg>
                    </div>
                    <h3>Aucun code promo</h3>
                    <p>Créez votre premier code promo pour offrir des réductions à vos clients.</p>
                    <a href="/admin/promo-code-form.php" class="btn btn-primary" style="margin-top: 20px;">
                        Créer un code promo
                    </a>
                </div>
            <?php else: ?>
                <div class="promo-grid">
                    <?php foreach ($promoCodes as $promo): ?>
                        <?php
                        $isExpired = !empty($promo['end_date']) && $promo['end_date'] < date('Y-m-d');
                        $isNotStarted = !empty($promo['start_date']) && $promo['start_date'] > date('Y-m-d');
                        $isLimitReached = !empty($promo['max_uses']) && $promo['current_uses'] >= $promo['max_uses'];
                        ?>
                        <div class="promo-card <?= $promo['active'] ? '' : 'inactive' ?> <?= $isExpired ? 'expired' : '' ?>">
                            <div class="promo-header">
                                <div class="promo-code-badge">
                                    <span class="code"><?= h($promo['code']) ?></span>
                                    <button class="copy-btn" onclick="copyCode('<?= h($promo['code']) ?>')" title="Copier le code">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="promo-status">
                                    <?php if (!$promo['active']): ?>
                                        <span class="status-tag status-inactive">Inactif</span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="status-tag status-expired">Expiré</span>
                                    <?php elseif ($isLimitReached): ?>
                                        <span class="status-tag status-expired">Épuisé</span>
                                    <?php elseif ($isNotStarted): ?>
                                        <span class="status-tag status-pending">Programmé</span>
                                    <?php else: ?>
                                        <span class="status-tag status-active">Actif</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="promo-body">
                                <h4><?= h($promo['name']) ?></h4>

                                <div class="promo-value">
                                    <?= $promoModel->getDiscountLabel($promo) ?>
                                </div>

                                <div class="promo-details">
                                    <?php if (!empty($promo['min_order_amount'])): ?>
                                        <div class="detail-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <path d="M12 6v6l4 2"/>
                                            </svg>
                                            Min. <?= formatPrice($promo['min_order_amount']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($promo['max_discount']) && $promo['discount_type'] === 'percentage'): ?>
                                        <div class="detail-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                            </svg>
                                            Max. <?= formatPrice($promo['max_discount']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($promo['max_uses'])): ?>
                                        <div class="detail-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                            </svg>
                                            <?= $promo['current_uses'] ?>/<?= $promo['max_uses'] ?> utilisé<?= $promo['current_uses'] > 1 ? 's' : '' ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="detail-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="8" y1="12" x2="16" y2="12"/>
                                            </svg>
                                            <?= $promo['current_uses'] ?> utilisation<?= $promo['current_uses'] > 1 ? 's' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($promo['start_date']) || !empty($promo['end_date'])): ?>
                                    <div class="promo-dates">
                                        <?php if (!empty($promo['start_date']) && !empty($promo['end_date'])): ?>
                                            Du <?= date('d/m/Y', strtotime($promo['start_date'])) ?> au <?= date('d/m/Y', strtotime($promo['end_date'])) ?>
                                        <?php elseif (!empty($promo['start_date'])): ?>
                                            À partir du <?= date('d/m/Y', strtotime($promo['start_date'])) ?>
                                        <?php else: ?>
                                            Jusqu'au <?= date('d/m/Y', strtotime($promo['end_date'])) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="promo-actions">
                                <a href="/admin/promo-code-form.php?id=<?= $promo['id'] ?>" class="btn-action" title="Modifier">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </a>
                                <a href="/admin/promo-codes.php?action=duplicate&id=<?= $promo['id'] ?>&csrf=<?= $csrf ?>"
                                   class="btn-action" title="Dupliquer">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                </a>
                                <a href="/admin/promo-codes.php?action=toggle&id=<?= $promo['id'] ?>&csrf=<?= $csrf ?>"
                                   class="btn-action" title="<?= $promo['active'] ? 'Désactiver' : 'Activer' ?>">
                                    <?php if ($promo['active']): ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                        </svg>
                                    <?php endif; ?>
                                </a>
                                <a href="/admin/promo-codes.php?action=delete&id=<?= $promo['id'] ?>&csrf=<?= $csrf ?>"
                                   class="btn-action btn-danger" title="Supprimer"
                                   onclick="return confirm('Supprimer ce code promo ?')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Info box -->
            <div class="info-box">
                <div class="info-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 16v-4"/>
                        <path d="M12 8h.01"/>
                    </svg>
                </div>
                <div class="info-content">
                    <h4>Comment ça marche ?</h4>
                    <p>Les clients saisissent le code promo sur la page panier. Si le code est valide, la réduction s'applique automatiquement. Vous pouvez partager ces codes par email, WhatsApp ou sur vos réseaux sociaux.</p>
                </div>
            </div>
        </main>
    </div>

    <script>
    function copyCode(code) {
        navigator.clipboard.writeText(code).then(() => {
            const btn = event.target.closest('.copy-btn');
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
            setTimeout(() => {
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
            }, 2000);
        });
    }
    </script>

    <style>
        .page-subtitle {
            color: var(--gray);
            font-size: 14px;
            margin-top: 4px;
        }

        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
        }
        .alert-success {
            background: linear-gradient(135deg, rgba(61, 255, 192, 0.15) 0%, rgba(61, 255, 192, 0.05) 100%);
            border: 1px solid rgba(61, 255, 192, 0.3);
            color: var(--mint-dark);
        }

        .empty-state {
            background: white;
            border-radius: 24px;
            padding: 80px 40px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .empty-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.15) 0%, rgba(61, 255, 192, 0.15) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: var(--pink-main);
        }
        .empty-state h3 {
            margin: 0 0 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--black-soft);
        }
        .empty-state p {
            margin: 0;
            color: var(--gray);
            font-size: 15px;
            max-width: 320px;
            margin: 0 auto;
        }

        .promo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .promo-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 16px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .promo-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.1);
            border-color: rgba(255, 105, 180, 0.2);
        }
        .promo-card.inactive {
            opacity: 0.7;
        }
        .promo-card.expired {
            background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
        }

        .promo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.08) 0%, rgba(61, 255, 192, 0.08) 100%);
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }

        .promo-code-badge {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .promo-code-badge .code {
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 16px;
            font-weight: 700;
            color: var(--black-soft);
            background: white;
            padding: 8px 14px;
            border-radius: 10px;
            border: 2px dashed var(--pink-main);
            letter-spacing: 1px;
        }
        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray);
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .copy-btn:hover {
            background: var(--pink-light);
            color: var(--pink-dark);
        }

        .status-tag {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-active {
            background: linear-gradient(135deg, rgba(61, 255, 192, 0.2) 0%, rgba(61, 255, 192, 0.1) 100%);
            color: var(--mint-dark);
        }
        .status-inactive {
            background: rgba(0,0,0,0.08);
            color: var(--gray);
        }
        .status-expired {
            background: rgba(239, 68, 68, 0.1);
            color: #DC2626;
        }
        .status-pending {
            background: rgba(245, 158, 11, 0.15);
            color: #D97706;
        }

        .promo-body {
            padding: 24px;
        }
        .promo-body h4 {
            margin: 0 0 16px;
            font-size: 16px;
            font-weight: 600;
            color: var(--black-soft);
        }

        .promo-value {
            display: inline-block;
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }

        .promo-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--gray);
            background: var(--gray-light);
            padding: 6px 12px;
            border-radius: 8px;
        }
        .detail-item svg {
            color: var(--pink-main);
        }

        .promo-dates {
            font-size: 12px;
            color: var(--gray);
            padding-top: 12px;
            border-top: 1px solid var(--gray-light);
        }

        .promo-actions {
            display: flex;
            gap: 8px;
            padding: 16px 24px;
            background: var(--gray-light);
            border-top: 1px solid rgba(0,0,0,0.04);
        }
        .btn-action {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 10px;
            color: var(--black-soft);
            transition: all 0.2s;
        }
        .btn-action:hover {
            background: var(--pink-light);
            color: var(--pink-dark);
        }
        .btn-action.btn-danger:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }

        .info-box {
            display: flex;
            gap: 16px;
            padding: 24px;
            background: linear-gradient(135deg, rgba(61, 255, 192, 0.08) 0%, rgba(255, 105, 180, 0.08) 100%);
            border-radius: 16px;
            border: 1px solid rgba(61, 255, 192, 0.2);
        }
        .info-icon {
            width: 44px;
            height: 44px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--mint-dark);
            flex-shrink: 0;
        }
        .info-content h4 {
            margin: 0 0 6px;
            font-size: 14px;
            font-weight: 700;
            color: var(--black-soft);
        }
        .info-content p {
            margin: 0;
            font-size: 13px;
            color: var(--gray);
            line-height: 1.6;
        }
    </style>
</body>
</html>
