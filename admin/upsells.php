<?php
/**
 * PERSONNALY Admin - Upsells (Suggestions de produits)
 * Interface ultra-moderne pour configurer les produits suggérés
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/ProductUpsell.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$upsellModel = new ProductUpsell();
$productModel = new Product();
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
    }
}

// Ajout rapide d'un produit
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['add_product'])) {
        $productId = (int) $_POST['product_id'];
        if ($productId) {
            $upsellModel->create([
                'product_id' => $productId,
                'priority' => 0,
                'active' => 1
            ]);
        }
        redirect('/admin/upsells.php?added=1');
    }

    // Mise à jour paramètres
    if (isset($_POST['save_settings'])) {
        $upsellModel->updateSetting('enabled', isset($_POST['enabled']) ? '1' : '0');
        $upsellModel->updateSetting('title', trim($_POST['title'] ?? 'Complétez votre commande'));
        $upsellModel->updateSetting('subtitle', trim($_POST['subtitle'] ?? ''));
        $upsellModel->updateSetting('max_items', max(1, min(8, (int) ($_POST['max_items'] ?? 4))));
        redirect('/admin/upsells.php?saved=1');
    }
}

$upsells = $upsellModel->findAll();
$settings = $upsellModel->getSettings();
$products = $productModel->findAll(true); // Produits actifs seulement
$csrf = csrfToken();

// Exclure les produits déjà ajoutés
$addedProductIds = array_column($upsells, 'product_id');
$availableProducts = array_filter($products, fn($p) => !in_array($p['id'], $addedProductIds));
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
                <div>
                    <h1 class="page-title">Suggestions <span>Produits</span></h1>
                    <p class="page-subtitle">Produits suggérés sur la page panier</p>
                </div>
            </div>

            <?php if (isset($_GET['saved']) || isset($_GET['added'])): ?>
                <div class="alert alert-success">
                    <?= isset($_GET['added']) ? 'Produit ajouté aux suggestions.' : 'Paramètres enregistrés.' ?>
                </div>
            <?php endif; ?>

            <div class="upsells-layout">
                <!-- Paramètres -->
                <div class="settings-panel">
                    <div class="panel-header">
                        <h3>Paramètres</h3>
                    </div>
                    <form method="POST" class="panel-body">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                        <label class="toggle-row">
                            <span>Activer les suggestions</span>
                            <input type="checkbox" name="enabled" value="1"
                                   <?= ($settings['enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <span class="toggle"></span>
                        </label>

                        <div class="form-group">
                            <label>Titre de la section</label>
                            <input type="text" name="title" value="<?= h($settings['title'] ?? 'Complétez votre commande') ?>">
                        </div>

                        <div class="form-group">
                            <label>Sous-titre (optionnel)</label>
                            <input type="text" name="subtitle" value="<?= h($settings['subtitle'] ?? '') ?>"
                                   placeholder="Ex: Ces articles pourraient vous plaire">
                        </div>

                        <div class="form-group">
                            <label>Nombre de produits</label>
                            <select name="max_items">
                                <?php for ($i = 2; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($settings['max_items'] ?? '4') == $i ? 'selected' : '' ?>><?= $i ?> produits</option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <button type="submit" name="save_settings" class="btn btn-primary btn-full">
                            Enregistrer
                        </button>
                    </form>

                    <!-- Ajouter un produit -->
                    <div class="panel-header" style="margin-top: 24px; border-top: 1px solid var(--gray-light); padding-top: 24px;">
                        <h3>Ajouter un produit</h3>
                    </div>
                    <form method="POST" class="panel-body">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                        <div class="form-group">
                            <select name="product_id" required>
                                <option value="">-- Choisir un produit --</option>
                                <?php foreach ($availableProducts as $product): ?>
                                    <option value="<?= $product['id'] ?>">
                                        <?= h($product['name']) ?> - <?= formatPrice($product['price']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" name="add_product" class="btn btn-secondary btn-full">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Ajouter aux suggestions
                        </button>
                    </form>
                </div>

                <!-- Liste des produits suggérés -->
                <div class="upsells-list">
                    <?php if (empty($upsells)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                </svg>
                            </div>
                            <h3>Aucun produit suggéré</h3>
                            <p>Ajoutez des produits pour les suggérer aux clients sur la page panier.</p>
                        </div>
                    <?php else: ?>
                        <div class="upsells-grid">
                            <?php foreach ($upsells as $upsell): ?>
                                <div class="upsell-card <?= $upsell['active'] ? '' : 'inactive' ?>">
                                    <div class="upsell-image">
                                        <?php if (!empty($upsell['product_image'])): ?>
                                            <img src="<?= h($upsell['product_image']) ?>" alt="<?= h($upsell['product_name']) ?>">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($upsell['badge_text'])): ?>
                                            <span class="upsell-badge"><?= h($upsell['badge_text']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!$upsell['active']): ?>
                                            <span class="status-badge">Inactif</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="upsell-info">
                                        <h4><?= h($upsell['custom_title'] ?: $upsell['product_name']) ?></h4>
                                        <?php if (!empty($upsell['custom_description'])): ?>
                                            <p class="description"><?= h($upsell['custom_description']) ?></p>
                                        <?php endif; ?>
                                        <div class="price-row">
                                            <?php if (!empty($upsell['promo_price'])): ?>
                                                <span class="old-price"><?= formatPrice($upsell['product_price']) ?></span>
                                                <span class="promo-price"><?= formatPrice($upsell['promo_price']) ?></span>
                                            <?php else: ?>
                                                <span class="price"><?= formatPrice($upsell['product_price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="upsell-actions">
                                        <a href="/admin/upsell-form.php?id=<?= $upsell['id'] ?>" class="btn-icon" title="Modifier">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>
                                        <a href="/admin/upsells.php?action=toggle&id=<?= $upsell['id'] ?>&csrf=<?= $csrf ?>"
                                           class="btn-icon" title="<?= $upsell['active'] ? 'Désactiver' : 'Activer' ?>">
                                            <?php if ($upsell['active']): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                                </svg>
                                            <?php else: ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                                </svg>
                                            <?php endif; ?>
                                        </a>
                                        <a href="/admin/upsells.php?action=delete&id=<?= $upsell['id'] ?>&csrf=<?= $csrf ?>"
                                           class="btn-icon btn-danger" title="Supprimer"
                                           onclick="return confirm('Retirer ce produit des suggestions ?')">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

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
                    <p>Les produits suggérés s'affichent automatiquement sur la page panier. Les clients peuvent les ajouter en un clic. Vous pouvez personnaliser le titre, la description et même proposer un prix promotionnel spécial pour inciter à l'achat.</p>
                </div>
            </div>
        </main>
    </div>

    <style>
        /* ===== Page Header ===== */
        .page-subtitle {
            color: var(--gray);
            font-size: 14px;
            margin-top: 4px;
        }

        /* ===== Alerts ===== */
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

        /* ===== Layout Grid ===== */
        .upsells-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 28px;
            align-items: start;
            margin-bottom: 32px;
        }
        @media (max-width: 1024px) {
            .upsells-layout { grid-template-columns: 1fr; }
        }

        /* ===== Settings Panel (Ultra-Moderne) ===== */
        .settings-panel {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .settings-panel:hover {
            border-color: rgba(255, 105, 180, 0.15);
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }

        .panel-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.08) 0%, rgba(61, 255, 192, 0.08) 100%);
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        .panel-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--black-soft);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .panel-header h3::before {
            content: '';
            width: 4px;
            height: 18px;
            background: linear-gradient(180deg, var(--pink-main) 0%, var(--mint-main) 100%);
            border-radius: 2px;
        }

        .panel-body {
            padding: 24px;
        }

        /* ===== Toggle Switch (Ultra-Moderne) ===== */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            cursor: pointer;
            margin-bottom: 20px;
            background: linear-gradient(135deg, rgba(61, 255, 192, 0.06) 0%, rgba(255, 105, 180, 0.06) 100%);
            border-radius: 14px;
            border: 1px solid rgba(61, 255, 192, 0.15);
            transition: all 0.2s;
        }
        .toggle-row:hover {
            border-color: rgba(61, 255, 192, 0.3);
            background: linear-gradient(135deg, rgba(61, 255, 192, 0.1) 0%, rgba(255, 105, 180, 0.1) 100%);
        }
        .toggle-row span:first-child {
            font-size: 14px;
            font-weight: 600;
            color: var(--black-soft);
        }
        .toggle-row input { display: none; }
        .toggle {
            width: 52px;
            height: 28px;
            background: #e0e0e0;
            border-radius: 14px;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        .toggle::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .toggle-row input:checked + .toggle {
            background: linear-gradient(135deg, var(--mint-main) 0%, #2dd4bf 100%);
            box-shadow: 0 4px 12px rgba(61, 255, 192, 0.4);
        }
        .toggle-row input:checked + .toggle::after {
            left: 27px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        /* ===== Form Groups ===== */
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--black-soft);
            margin-bottom: 8px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
            background: #fafafa;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--pink-main);
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.1);
        }
        .form-group input::placeholder {
            color: #aaa;
        }

        /* ===== Buttons ===== */
        .btn-full {
            width: 100%;
            justify-content: center;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #f0f0f0 0%, #e8e8e8 100%);
            color: var(--black-soft);
            border: none;
            gap: 8px;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--pink-light) 0%, rgba(255, 105, 180, 0.2) 100%);
            color: var(--pink-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 105, 180, 0.2);
        }

        /* ===== Empty State ===== */
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

        /* ===== Upsells Grid ===== */
        .upsells-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        /* ===== Upsell Cards (Ultra-Moderne) ===== */
        .upsell-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 16px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .upsell-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.12);
            border-color: rgba(255, 105, 180, 0.2);
        }
        .upsell-card.inactive {
            opacity: 0.65;
            filter: grayscale(30%);
        }
        .upsell-card.inactive:hover {
            opacity: 0.8;
            filter: grayscale(0%);
        }

        /* ===== Card Image ===== */
        .upsell-image {
            position: relative;
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            overflow: hidden;
        }
        .upsell-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .upsell-card:hover .upsell-image img {
            transform: scale(1.05);
        }
        .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.05) 0%, rgba(61, 255, 192, 0.05) 100%);
        }

        /* ===== Badges ===== */
        .upsell-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
            color: white;
            padding: 8px 14px;
            border-radius: 24px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(255, 105, 180, 0.4);
        }
        .status-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ===== Card Info ===== */
        .upsell-info {
            padding: 20px 24px;
            background: linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(248,249,250,0.5) 100%);
        }
        .upsell-info h4 {
            margin: 0 0 10px;
            font-size: 17px;
            font-weight: 700;
            color: var(--black-soft);
            line-height: 1.35;
        }
        .upsell-info .description {
            margin: 0 0 14px;
            font-size: 13px;
            color: var(--gray);
            line-height: 1.55;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ===== Price Row ===== */
        .price-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .price {
            font-size: 22px;
            font-weight: 800;
            color: var(--black-soft);
        }
        .old-price {
            font-size: 15px;
            color: var(--gray);
            text-decoration: line-through;
        }
        .promo-price {
            font-size: 22px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--pink-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ===== Card Actions ===== */
        .upsell-actions {
            display: flex;
            gap: 10px;
            padding: 16px 24px 24px;
            background: var(--gray-light);
            border-top: 1px solid rgba(0,0,0,0.04);
        }
        .btn-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 12px;
            color: var(--black-soft);
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .btn-icon:hover {
            background: var(--pink-light);
            color: var(--pink-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 105, 180, 0.2);
        }
        .btn-icon.btn-danger:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }

        /* ===== Info Box ===== */
        .info-box {
            display: flex;
            gap: 16px;
            padding: 24px;
            background: linear-gradient(135deg, rgba(61, 255, 192, 0.08) 0%, rgba(255, 105, 180, 0.08) 100%);
            border-radius: 16px;
            border: 1px solid rgba(61, 255, 192, 0.2);
            margin-top: 8px;
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
            box-shadow: 0 2px 8px rgba(61, 255, 192, 0.2);
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

        /* ===== Responsive Mobile ===== */
        @media (max-width: 768px) {
            .upsells-grid {
                grid-template-columns: 1fr;
            }
            .upsell-image {
                height: 180px;
            }
            .info-box {
                flex-direction: column;
                text-align: center;
            }
            .info-icon {
                margin: 0 auto;
            }
        }
    </style>
</body>
</html>
