<?php
/**
 * PERSONNALY Admin - Upsells (Suggestions de produits)
 * Vrai système d'upsell : suggérer des produits complémentaires
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/ProductUpsell.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$upsellModel = new ProductUpsell();
$orderModel = new Order();
$pendingOrders = $orderModel->countNew();

// Actions
if (isset($_GET['action'])) {
    $id = (int) ($_GET['id'] ?? 0);

    switch ($_GET['action']) {
        case 'toggle':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $upsellModel->toggleActive($id);
            }
            redirect('/admin/upsells.php');
            break;

        case 'delete':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $upsellModel->delete($id);
            }
            redirect('/admin/upsells.php');
            break;

        case 'duplicate':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $newId = $upsellModel->duplicate($id);
                if ($newId) {
                    redirect('/admin/upsell-form.php?id=' . $newId);
                }
            }
            redirect('/admin/upsells.php');
            break;
    }
}

// Mise à jour des paramètres
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $upsellModel->updateSetting('enabled', isset($_POST['enabled']) ? '1' : '0');
    $upsellModel->updateSetting('title', trim($_POST['title'] ?? 'Vous aimerez aussi'));
    $upsellModel->updateSetting('max_items', max(1, min(8, (int) ($_POST['max_items'] ?? 4))));
    $upsellModel->updateSetting('show_on_cart', isset($_POST['show_on_cart']) ? '1' : '0');
    $upsellModel->updateSetting('show_on_checkout', isset($_POST['show_on_checkout']) ? '1' : '0');
    $upsellModel->updateSetting('fallback_to_category', isset($_POST['fallback_to_category']) ? '1' : '0');
    redirect('/admin/upsells.php?saved=1');
}

$upsells = $upsellModel->findAll();
$settings = $upsellModel->getSettings();
$csrf = csrfToken();

$triggerLabels = ProductUpsell::TRIGGER_TYPES;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upsells - PERSONNALY Admin</title>
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
                <h1 class="page-title">Gérer les <span>Upsells</span></h1>
                <a href="/admin/upsell-form.php" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nouvel upsell
                </a>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="alert alert-success" style="margin-bottom: 24px;">
                    Paramètres enregistrés avec succès.
                </div>
            <?php endif; ?>

            <!-- Info explicative -->
            <div class="info-card" style="margin-bottom: 24px;">
                <div class="info-card-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="7.5 4.21 12 6.81 16.5 4.21"/>
                    </svg>
                </div>
                <div class="info-card-content">
                    <h4>Suggestions de produits</h4>
                    <p>Les upsells suggèrent des produits complémentaires sur la page panier pour inciter le client à ajouter des articles.</p>
                </div>
            </div>

            <div class="admin-grid">
                <!-- Paramètres globaux -->
                <div class="settings-card">
                    <h3>Paramètres</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                        <div class="form-group">
                            <label class="toggle-label">
                                <input type="checkbox" name="enabled" value="1"
                                       <?= ($settings['enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <span class="toggle-switch"></span>
                                <span class="toggle-text">Activer les upsells</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="title">Titre de la section</label>
                            <input type="text" id="title" name="title"
                                   value="<?= h($settings['title'] ?? 'Vous aimerez aussi') ?>">
                        </div>

                        <div class="form-group">
                            <label for="max_items">Nombre de produits</label>
                            <input type="number" id="max_items" name="max_items"
                                   value="<?= h($settings['max_items'] ?? 4) ?>"
                                   min="1" max="8">
                        </div>

                        <div class="form-group">
                            <label class="toggle-label small">
                                <input type="checkbox" name="show_on_cart" value="1"
                                       <?= ($settings['show_on_cart'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <span class="toggle-switch"></span>
                                <span class="toggle-text">Afficher sur le panier</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="toggle-label small">
                                <input type="checkbox" name="show_on_checkout" value="1"
                                       <?= ($settings['show_on_checkout'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <span class="toggle-switch"></span>
                                <span class="toggle-text">Afficher au checkout</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="toggle-label small">
                                <input type="checkbox" name="fallback_to_category" value="1"
                                       <?= ($settings['fallback_to_category'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <span class="toggle-switch"></span>
                                <span class="toggle-text">Compléter avec produits similaires</span>
                            </label>
                            <small class="form-help">Si pas assez d'upsells configurés, suggère des produits de la même catégorie.</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm">
                            Enregistrer
                        </button>
                    </form>
                </div>

                <!-- Liste des upsells -->
                <div class="upsells-list">
                    <?php if (empty($upsells)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">+</div>
                            <h3>Aucun upsell configuré</h3>
                            <p class="text-muted">Créez votre premier upsell pour suggérer des produits.</p>
                            <a href="/admin/upsell-form.php" class="btn btn-primary">Créer un upsell</a>
                        </div>
                    <?php else: ?>
                        <div class="upsells-grid">
                            <?php foreach ($upsells as $upsell): ?>
                                <div class="upsell-card <?= $upsell['active'] ? '' : 'inactive' ?>">
                                    <div class="upsell-image">
                                        <?php if (!empty($upsell['product_image'])): ?>
                                            <img src="<?= h($upsell['product_image']) ?>" alt="<?= h($upsell['product_name']) ?>">
                                        <?php else: ?>
                                            <div class="no-image">?</div>
                                        <?php endif; ?>
                                        <?php if (!empty($upsell['badge_text'])): ?>
                                            <span class="badge-overlay"><?= h($upsell['badge_text']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="upsell-content">
                                        <div class="upsell-name"><?= h($upsell['name']) ?></div>
                                        <div class="upsell-product">
                                            <?= h($upsell['product_name']) ?>
                                        </div>
                                        <div class="upsell-trigger">
                                            <span class="trigger-badge <?= $upsell['trigger_type'] ?>">
                                                <?= h($triggerLabels[$upsell['trigger_type']] ?? $upsell['trigger_type']) ?>
                                            </span>
                                            <?php if (!empty($upsell['trigger_value'])): ?>
                                                <small>ID: <?= h($upsell['trigger_value']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="upsell-price">
                                            <?php if (!empty($upsell['promo_price'])): ?>
                                                <span class="old-price"><?= number_format($upsell['product_price'], 2, ',', ' ') ?>€</span>
                                                <span class="promo-price"><?= number_format($upsell['promo_price'], 2, ',', ' ') ?>€</span>
                                            <?php else: ?>
                                                <?= number_format($upsell['product_price'], 2, ',', ' ') ?>€
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="upsell-actions">
                                        <a href="/admin/upsell-form.php?id=<?= $upsell['id'] ?>" class="btn-icon" title="Modifier">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>
                                        <a href="/admin/upsells.php?action=toggle&id=<?= $upsell['id'] ?>&csrf=<?= $csrf ?>"
                                           class="btn-icon" title="<?= $upsell['active'] ? 'Désactiver' : 'Activer' ?>">
                                            <?php if ($upsell['active']): ?>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                    <circle cx="12" cy="12" r="3"/>
                                                </svg>
                                            <?php else: ?>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                                </svg>
                                            <?php endif; ?>
                                        </a>
                                        <a href="/admin/upsells.php?action=delete&id=<?= $upsell['id'] ?>&csrf=<?= $csrf ?>"
                                           class="btn-icon btn-icon-danger"
                                           onclick="return confirm('Supprimer cet upsell ?')"
                                           title="Supprimer">
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
                </div>
            </div>
        </main>
    </div>

    <style>
        .info-card {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 20px 24px;
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.08) 0%, rgba(61, 255, 192, 0.08) 100%);
            border: 1px solid rgba(255, 105, 180, 0.2);
            border-radius: 16px;
        }
        .info-card-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
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
        }
        .info-card-content p {
            margin: 0;
            font-size: 14px;
            color: var(--gray);
        }

        .admin-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .admin-grid { grid-template-columns: 1fr; }
        }

        .settings-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            height: fit-content;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .settings-card h3 {
            margin: 0 0 20px 0;
            font-size: 16px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label:not(.toggle-label) {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
        }
        .form-group input[type="text"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--pink-main);
        }
        .form-help {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: var(--gray);
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }
        .toggle-label.small { margin-bottom: 8px; }
        .toggle-label input { display: none; }
        .toggle-switch {
            width: 40px;
            height: 22px;
            background: var(--gray-light);
            border-radius: 11px;
            position: relative;
            transition: all 0.3s;
        }
        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .toggle-label input:checked + .toggle-switch {
            background: linear-gradient(135deg, var(--mint-main) 0%, var(--mint-dark) 100%);
        }
        .toggle-label input:checked + .toggle-switch::after { left: 20px; }
        .toggle-text {
            font-size: 13px;
            color: var(--black-soft);
        }

        .btn-sm { padding: 8px 16px; font-size: 13px; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
        }
        .empty-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            background: var(--gray-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--gray);
        }

        .upsells-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .upsell-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .upsell-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .upsell-card.inactive {
            opacity: 0.6;
        }

        .upsell-image {
            position: relative;
            height: 140px;
            background: var(--gray-light);
            overflow: hidden;
        }
        .upsell-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--gray);
        }
        .badge-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .upsell-content {
            padding: 16px;
        }
        .upsell-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            color: var(--black-soft);
        }
        .upsell-product {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 8px;
        }
        .upsell-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        .trigger-badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .trigger-badge.any {
            background: rgba(61, 255, 192, 0.2);
            color: var(--mint-dark);
        }
        .trigger-badge.product {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
        }
        .trigger-badge.category {
            background: rgba(99, 102, 241, 0.15);
            color: #6366F1;
        }
        .upsell-trigger small {
            font-size: 11px;
            color: var(--gray);
        }

        .upsell-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--black-soft);
        }
        .old-price {
            text-decoration: line-through;
            color: var(--gray);
            font-weight: 400;
            font-size: 13px;
            margin-right: 8px;
        }
        .promo-price {
            color: var(--pink-dark);
        }

        .upsell-actions {
            display: flex;
            gap: 8px;
            padding: 0 16px 16px;
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

        .alert-success {
            padding: 12px 16px;
            background: rgba(61, 255, 192, 0.15);
            border: 1px solid rgba(61, 255, 192, 0.3);
            border-radius: 10px;
            color: var(--mint-dark);
            font-size: 14px;
        }
    </style>
</body>
</html>
