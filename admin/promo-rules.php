<?php
/**
 * PERSONNALY Admin - Règles Promotionnelles
 * Gestion des offres conditionnelles (SI condition ALORS offre)
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/PromoRule.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$promoModel = new PromoRule();
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
            redirect('/admin/promo-rules.php');
            break;

        case 'delete':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $promoModel->delete($id);
            }
            redirect('/admin/promo-rules.php');
            break;

        case 'duplicate':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $newId = $promoModel->duplicate($id);
                if ($newId) {
                    redirect('/admin/promo-rule-form.php?id=' . $newId);
                }
            }
            redirect('/admin/promo-rules.php');
            break;
    }
}

$rules = $promoModel->findAll();
$csrf = csrfToken();

$conditionLabels = PromoRule::CONDITION_TYPES;
$offerLabels = PromoRule::OFFER_TYPES;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Règles Promotionnelles - PERSONNALY Admin</title>
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
                <h1 class="page-title">Règles <span>Promotionnelles</span></h1>
                <a href="/admin/promo-rule-form.php" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nouvelle règle
                </a>
            </div>

            <!-- Info explicative -->
            <div class="info-card" style="margin-bottom: 24px;">
                <div class="info-card-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="info-card-content">
                    <h4>Offres conditionnelles automatiques</h4>
                    <p>Configurez des règles : <strong>SI</strong> le client remplit une condition (panier min, produit...), <strong>ALORS</strong> une offre s'affiche (réduction, livraison gratuite...).</p>
                </div>
            </div>

            <?php if (empty($rules)): ?>
                <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">%</div>
                    <h3>Aucune règle promotionnelle</h3>
                    <p class="text-muted" style="margin-bottom: 20px;">Créez votre première règle promo.</p>
                    <a href="/admin/promo-rule-form.php" class="btn btn-primary">Créer une règle</a>
                </div>
            <?php else: ?>
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Toutes les règles</h3>
                        <span class="badge badge-mint"><?= count($rules) ?> règle(s)</span>
                    </div>
                    <div class="data-card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>SI (Condition)</th>
                                    <th>ALORS (Offre)</th>
                                    <th>Priorité</th>
                                    <th>Utilisations</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rules as $rule): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <strong><?= h($rule['name']) ?></strong>
                                                <?php if (!empty($rule['display_title'])): ?>
                                                    <small class="text-muted"><?= h($rule['display_title']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="condition-badge">
                                                <span class="condition-type"><?= h($conditionLabels[$rule['condition_type']] ?? $rule['condition_type']) ?></span>
                                                <span class="condition-value">
                                                    <?php
                                                    $val = $rule['condition_value'];
                                                    if ($rule['condition_type'] === 'panier_min') {
                                                        echo h($val) . '€';
                                                    } elseif ($rule['condition_type'] === 'quantite_min') {
                                                        echo h($val) . ' articles';
                                                    } else {
                                                        echo h($val);
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="offer-badge">
                                                <span class="offer-type <?= $rule['offer_type'] ?>">
                                                    <?= h($offerLabels[$rule['offer_type']] ?? $rule['offer_type']) ?>
                                                </span>
                                                <?php if ($rule['discount_type'] !== 'aucun' && $rule['discount_value'] > 0): ?>
                                                    <span class="offer-discount">
                                                        -<?= h($rule['discount_value']) ?><?= $rule['discount_type'] === 'pourcentage' ? '%' : '€' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="priority-badge"><?= (int) $rule['priority'] ?></span>
                                        </td>
                                        <td>
                                            <span class="uses-count">
                                                <?= (int) $rule['current_uses'] ?>
                                                <?php if (!empty($rule['max_uses'])): ?>
                                                    / <?= (int) $rule['max_uses'] ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = $rule['active'] ? 'active' : 'inactive';
                                            $statusText = $rule['active'] ? 'Actif' : 'Inactif';

                                            $now = date('Y-m-d');
                                            if (!empty($rule['start_date']) && $rule['start_date'] > $now) {
                                                $statusClass = 'scheduled';
                                                $statusText = 'Programmé';
                                            } elseif (!empty($rule['end_date']) && $rule['end_date'] < $now) {
                                                $statusClass = 'expired';
                                                $statusText = 'Expiré';
                                            }

                                            if (!empty($rule['max_uses']) && $rule['current_uses'] >= $rule['max_uses']) {
                                                $statusClass = 'exhausted';
                                                $statusText = 'Épuisé';
                                            }
                                            ?>
                                            <span class="status-badge status-<?= $statusClass ?>">
                                                <?= $statusText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="/admin/promo-rule-form.php?id=<?= $rule['id'] ?>" class="btn-icon" title="Modifier">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                    </svg>
                                                </a>
                                                <a href="/admin/promo-rules.php?action=toggle&id=<?= $rule['id'] ?>&csrf=<?= $csrf ?>"
                                                   class="btn-icon" title="<?= $rule['active'] ? 'Désactiver' : 'Activer' ?>">
                                                    <?php if ($rule['active']): ?>
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                            <circle cx="12" cy="12" r="3"/>
                                                        </svg>
                                                    <?php endif; ?>
                                                </a>
                                                <a href="/admin/promo-rules.php?action=duplicate&id=<?= $rule['id'] ?>&csrf=<?= $csrf ?>"
                                                   class="btn-icon" title="Dupliquer">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                                    </svg>
                                                </a>
                                                <a href="/admin/promo-rules.php?action=delete&id=<?= $rule['id'] ?>&csrf=<?= $csrf ?>"
                                                   class="btn-icon btn-icon-danger"
                                                   onclick="return confirm('Supprimer cette règle ?')"
                                                   title="Supprimer">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
        .info-card {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 20px 24px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(61, 255, 192, 0.08) 100%);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 16px;
        }
        .info-card-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        .info-card-content h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--black-soft);
        }
        .info-card-content p {
            margin: 0;
            font-size: 14px;
            color: var(--gray);
        }

        .condition-badge {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .condition-type {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--gray);
            font-weight: 600;
        }
        .condition-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--black-soft);
        }

        .offer-badge {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .offer-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .offer-type.reduction {
            background: rgba(61, 255, 192, 0.2);
            color: var(--mint-dark);
        }
        .offer-type.produit {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
        }
        .offer-type.option {
            background: rgba(99, 102, 241, 0.15);
            color: #6366F1;
        }
        .offer-type.livraison_gratuite {
            background: rgba(245, 158, 11, 0.15);
            color: #D97706;
        }
        .offer-discount {
            font-size: 16px;
            font-weight: 700;
            color: var(--mint-dark);
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: var(--gray-light);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--black-soft);
        }

        .uses-count {
            font-size: 13px;
            color: var(--gray);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: rgba(61, 255, 192, 0.2);
            color: var(--mint-dark);
        }
        .status-inactive {
            background: var(--gray-light);
            color: var(--gray);
        }
        .status-scheduled {
            background: rgba(99, 102, 241, 0.15);
            color: #6366F1;
        }
        .status-expired {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }
        .status-exhausted {
            background: rgba(245, 158, 11, 0.15);
            color: #D97706;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-light);
            border-radius: 8px;
            color: var(--black-soft);
            transition: all 0.2s;
        }
        .btn-icon:hover {
            background: var(--pink-light);
            color: var(--pink-dark);
        }
        .btn-icon-danger:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }
    </style>
</body>
</html>
