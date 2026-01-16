<?php
/**
 * PERSONNALY - Admin Zones d'impression par produit
 * Gestion des zones de personnalisation
 */

require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/ProductPrintZone.php';
require_once __DIR__ . '/../app/models/Font.php';

Auth::requireAdmin();

$productId = (int) get('product_id', 0);
if (!$productId) {
    redirect('/admin/products.php');
}

$productModel = new Product();
$product = $productModel->findById($productId);

if (!$product) {
    redirect('/admin/products.php');
}

$zoneModel = new ProductPrintZone();
$fontModel = new Font();
$fonts = $fontModel->findActive();

$success = '';
$error = '';

// Traitement des actions
if (isPost()) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée. Veuillez réessayer.';
    } else {
        $action = post('action', '');

        if ($action === 'create') {
            $data = [
                'product_id' => $productId,
                'zone_name' => trim(post('zone_name', '')),
                'zone_label' => trim(post('zone_label', '')),
                'pos_x' => (float) post('pos_x', 15),
                'pos_y' => (float) post('pos_y', 25),
                'width' => (float) post('width', 70),
                'height' => (float) post('height', 50),
                'max_chars' => (int) post('max_chars', 50),
                'max_lines' => (int) post('max_lines', 3),
                'default_font_id' => (int) post('default_font_id', 0) ?: null,
                'allowed_fonts' => null,
                'active' => 1,
                'sort_order' => (int) post('sort_order', 0)
            ];

            if (empty($data['zone_name']) || empty($data['zone_label'])) {
                $error = 'Le nom et le libellé de la zone sont requis.';
            } else {
                $zoneModel->create($data);
                $success = 'Zone créée avec succès.';
            }
        }

        if ($action === 'update') {
            $zoneId = (int) post('zone_id', 0);
            $data = [
                'zone_name' => trim(post('zone_name', '')),
                'zone_label' => trim(post('zone_label', '')),
                'pos_x' => (float) post('pos_x', 15),
                'pos_y' => (float) post('pos_y', 25),
                'width' => (float) post('width', 70),
                'height' => (float) post('height', 50),
                'max_chars' => (int) post('max_chars', 50),
                'max_lines' => (int) post('max_lines', 3),
                'default_font_id' => (int) post('default_font_id', 0) ?: null,
                'allowed_fonts' => null,
                'active' => (int) post('active', 1),
                'sort_order' => (int) post('sort_order', 0)
            ];

            if ($zoneId && !empty($data['zone_name']) && !empty($data['zone_label'])) {
                $zoneModel->update($zoneId, $data);
                $success = 'Zone mise à jour.';
            } else {
                $error = 'Données invalides.';
            }
        }

        if ($action === 'delete') {
            $zoneId = (int) post('zone_id', 0);
            if ($zoneId) {
                $zoneModel->delete($zoneId);
                $success = 'Zone supprimée.';
            }
        }

        if ($action === 'toggle') {
            $zoneId = (int) post('zone_id', 0);
            if ($zoneId) {
                $zoneModel->toggleActive($zoneId);
                $success = 'Statut modifié.';
            }
        }

        if ($action === 'create_default') {
            if (!$zoneModel->hasZones($productId)) {
                $zoneModel->createDefault($productId);
                $success = 'Zone par défaut créée.';
            } else {
                $error = 'Ce produit a déjà des zones définies.';
            }
        }
    }
}

// Récupérer les zones du produit
$zones = $zoneModel->findByProduct($productId);

// Zone en édition
$editZone = null;
$editId = (int) get('edit', 0);
if ($editId) {
    $editZone = $zoneModel->findById($editId);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zones d'impression - <?= h($product['name']) ?> - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .zone-preview-container {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 30px;
        }
        .zone-preview {
            width: 100%;
            max-width: 400px;
            height: 400px;
            margin: 0 auto;
            background: linear-gradient(135deg, #f8f8f8 0%, #e8e8e8 100%);
            border-radius: var(--radius-lg);
            position: relative;
            overflow: hidden;
        }
        .zone-preview img {
            max-width: 80%;
            max-height: 80%;
            object-fit: contain;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .zone-preview .zone-overlay {
            position: absolute;
            border: 2px dashed var(--pink-main);
            background: rgba(255, 105, 180, 0.1);
            border-radius: 8px;
        }
        .zone-preview .zone-label {
            position: absolute;
            bottom: 100%;
            left: 0;
            font-size: 10px;
            background: var(--pink-main);
            color: white;
            padding: 2px 6px;
            border-radius: 4px 4px 0 0;
        }

        .zones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .zone-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            border: 1px solid #eee;
        }
        .zone-card.inactive {
            opacity: 0.6;
        }
        .zone-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .zone-card-title {
            font-weight: 700;
            font-size: 1.1rem;
        }
        .zone-card-name {
            font-size: 12px;
            color: var(--gray);
            font-family: monospace;
        }
        .zone-card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 14px;
        }
        .zone-stat {
            display: flex;
            flex-direction: column;
        }
        .zone-stat-label {
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
        }
        .zone-stat-value {
            font-weight: 600;
        }
        .zone-card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .zone-form {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
        }
        .zone-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .zone-form-grid .full-width {
            grid-column: 1 / -1;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .back-link:hover {
            color: var(--pink-main);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--radius-lg);
        }
        .empty-state h3 {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <div>
                    <a href="/admin/product-form.php?id=<?= $productId ?>" class="back-link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Retour au produit
                    </a>
                    <h1>Zones d'impression</h1>
                    <p class="admin-subtitle"><?= h($product['name']) ?></p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Preview du produit avec zones -->
            <div class="zone-preview-container">
                <h3 style="margin-bottom: 15px;">Aperçu des zones</h3>
                <div class="zone-preview" id="zonePreview">
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="/public<?= h($product['image_url']) ?>" alt="<?= h($product['name']) ?>">
                    <?php else: ?>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 4rem; opacity: 0.3;">👕</div>
                    <?php endif; ?>

                    <?php foreach ($zones as $z): ?>
                        <?php if ($z['active']): ?>
                            <div class="zone-overlay"
                                 style="left: <?= $z['pos_x'] ?>%; top: <?= $z['pos_y'] ?>%; width: <?= $z['width'] ?>%; height: <?= $z['height'] ?>%;">
                                <span class="zone-label"><?= h($z['zone_label']) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if (empty($zones)): ?>
                        <div class="zone-overlay" style="left: 15%; top: 25%; width: 70%; height: 50%; border-style: dotted; opacity: 0.5;">
                            <span class="zone-label">Zone par défaut (non enregistrée)</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulaire ajout/édition -->
            <div class="zone-form">
                <h3 style="margin-bottom: 20px;">
                    <?= $editZone ? 'Modifier la zone' : 'Ajouter une zone' ?>
                </h3>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editZone ? 'update' : 'create' ?>">
                    <?php if ($editZone): ?>
                        <input type="hidden" name="zone_id" value="<?= $editZone['id'] ?>">
                    <?php endif; ?>

                    <div class="zone-form-grid">
                        <div class="form-group">
                            <label class="form-label">Nom technique</label>
                            <input type="text" name="zone_name" class="form-input"
                                   value="<?= h($editZone['zone_name'] ?? 'front') ?>"
                                   placeholder="front, back, sleeve..." required>
                            <small style="color: var(--gray);">Identifiant unique (sans espaces)</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Libellé affiché</label>
                            <input type="text" name="zone_label" class="form-input"
                                   value="<?= h($editZone['zone_label'] ?? 'Devant') ?>"
                                   placeholder="Devant, Dos, Manche..." required>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Position et dimensions (en % de l'image)</label>
                            <div class="form-row">
                                <div>
                                    <label style="font-size: 12px; color: var(--gray);">X (gauche)</label>
                                    <input type="number" name="pos_x" class="form-input"
                                           value="<?= $editZone['pos_x'] ?? 15 ?>"
                                           min="0" max="100" step="0.1">
                                </div>
                                <div>
                                    <label style="font-size: 12px; color: var(--gray);">Y (haut)</label>
                                    <input type="number" name="pos_y" class="form-input"
                                           value="<?= $editZone['pos_y'] ?? 25 ?>"
                                           min="0" max="100" step="0.1">
                                </div>
                                <div>
                                    <label style="font-size: 12px; color: var(--gray);">Largeur</label>
                                    <input type="number" name="width" class="form-input"
                                           value="<?= $editZone['width'] ?? 70 ?>"
                                           min="1" max="100" step="0.1">
                                </div>
                                <div>
                                    <label style="font-size: 12px; color: var(--gray);">Hauteur</label>
                                    <input type="number" name="height" class="form-input"
                                           value="<?= $editZone['height'] ?? 50 ?>"
                                           min="1" max="100" step="0.1">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Max caractères</label>
                            <input type="number" name="max_chars" class="form-input"
                                   value="<?= $editZone['max_chars'] ?? 50 ?>"
                                   min="1" max="200">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Max lignes</label>
                            <input type="number" name="max_lines" class="form-input"
                                   value="<?= $editZone['max_lines'] ?? 3 ?>"
                                   min="1" max="10">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Police par défaut</label>
                            <select name="default_font_id" class="form-input">
                                <option value="">-- Aucune (première dispo) --</option>
                                <?php foreach ($fonts as $font): ?>
                                    <option value="<?= $font['id'] ?>"
                                        <?= ($editZone['default_font_id'] ?? '') == $font['id'] ? 'selected' : '' ?>>
                                        <?= h($font['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Ordre d'affichage</label>
                            <input type="number" name="sort_order" class="form-input"
                                   value="<?= $editZone['sort_order'] ?? 0 ?>"
                                   min="0">
                        </div>

                        <?php if ($editZone): ?>
                            <div class="form-group">
                                <label class="form-label">Statut</label>
                                <select name="active" class="form-input">
                                    <option value="1" <?= $editZone['active'] ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= !$editZone['active'] ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 25px; display: flex; gap: 15px;">
                        <button type="submit" class="btn btn-primary">
                            <?= $editZone ? 'Mettre à jour' : 'Ajouter la zone' ?>
                        </button>
                        <?php if ($editZone): ?>
                            <a href="/admin/product-zones.php?product_id=<?= $productId ?>" class="btn btn-outline">
                                Annuler
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Liste des zones -->
            <?php if (!empty($zones)): ?>
                <h3 style="margin-bottom: 20px;">Zones configurées (<?= count($zones) ?>)</h3>
                <div class="zones-grid">
                    <?php foreach ($zones as $zone): ?>
                        <div class="zone-card <?= !$zone['active'] ? 'inactive' : '' ?>">
                            <div class="zone-card-header">
                                <div>
                                    <div class="zone-card-title"><?= h($zone['zone_label']) ?></div>
                                    <div class="zone-card-name"><?= h($zone['zone_name']) ?></div>
                                </div>
                                <span class="badge <?= $zone['active'] ? 'badge-mint' : 'badge-gray' ?>">
                                    <?= $zone['active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            <div class="zone-card-body">
                                <div class="zone-stat">
                                    <span class="zone-stat-label">Position</span>
                                    <span class="zone-stat-value"><?= $zone['pos_x'] ?>% × <?= $zone['pos_y'] ?>%</span>
                                </div>
                                <div class="zone-stat">
                                    <span class="zone-stat-label">Dimensions</span>
                                    <span class="zone-stat-value"><?= $zone['width'] ?>% × <?= $zone['height'] ?>%</span>
                                </div>
                                <div class="zone-stat">
                                    <span class="zone-stat-label">Max caractères</span>
                                    <span class="zone-stat-value"><?= $zone['max_chars'] ?></span>
                                </div>
                                <div class="zone-stat">
                                    <span class="zone-stat-label">Max lignes</span>
                                    <span class="zone-stat-value"><?= $zone['max_lines'] ?></span>
                                </div>
                            </div>
                            <div class="zone-card-actions">
                                <a href="?product_id=<?= $productId ?>&edit=<?= $zone['id'] ?>" class="btn btn-sm btn-outline">
                                    Modifier
                                </a>
                                <form method="post" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="zone_id" value="<?= $zone['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline">
                                        <?= $zone['active'] ? 'Désactiver' : 'Activer' ?>
                                    </button>
                                </form>
                                <form method="post" style="display: inline;"
                                      onsubmit="return confirm('Supprimer cette zone ?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="zone_id" value="<?= $zone['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>Aucune zone définie</h3>
                    <p style="color: var(--gray); margin-bottom: 20px;">
                        Ce produit utilise la zone par défaut (15% × 25%, 70% × 50%).<br>
                        Créez une zone personnalisée ou utilisez la zone par défaut.
                    </p>
                    <form method="post" style="display: inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create_default">
                        <button type="submit" class="btn btn-outline">
                            Créer la zone par défaut
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
