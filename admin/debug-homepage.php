<?php
/**
 * Debug Homepage Sections
 * Outil de diagnostic pour voir l'état des sections et items
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/HomepageSection.php';
require_once __DIR__ . '/../app/models/Product.php';

Auth::requireAdmin();

$db = Database::getInstance();
$sectionModel = new HomepageSection();

// Récupérer toutes les sections
$sections = $sectionModel->findAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Debug Homepage Sections</title>
    <style>
        body { font-family: -apple-system, sans-serif; padding: 20px; background: #f5f5f5; }
        .section-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-title { font-size: 18px; font-weight: 600; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-active { background: #d4edda; color: #155724; }
        .status-draft { background: #fff3cd; color: #856404; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .item-active { color: #28a745; }
        .item-inactive { color: #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; margin-top: 10px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 4px; margin-top: 10px; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>🔍 Debug Homepage Sections</h1>
    <p>Cet outil montre l'état de chaque section et pourquoi certains items pourraient ne pas apparaître.</p>

    <?php foreach ($sections as $section): ?>
    <div class="section-card">
        <div class="section-header">
            <div class="section-title">
                <?= h($section['title'] ?: '(Sans titre)') ?>
                <span style="color: #888; font-weight: normal;">(<?= h($section['type']) ?>)</span>
            </div>
            <span class="status-badge status-<?= $section['status'] ?>">
                <?= $section['status'] === 'active' ? '✓ Actif' : '⏸ Brouillon' ?>
            </span>
        </div>

        <?php if ($section['status'] === 'draft'): ?>
        <div class="warning">
            ⚠️ Cette section est en <strong>brouillon</strong>. Elle n'apparaîtra pas sur le site en mode normal.
        </div>
        <?php endif; ?>

        <?php
        // Charger les items avec détails
        $items = $sectionModel->getItems($section['id']);

        if (in_array($section['type'], ['featured_products', 'featured_packs', 'blog_slider'])):
        ?>

        <h4>Items liés (<?= count($items) ?>)</h4>

        <?php if (empty($items)): ?>
        <div class="warning">
            ⚠️ Aucun item sélectionné. Allez dans le Page Builder et cochez des produits/packs.
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Nom</th>
                    <th>Statut item</th>
                    <th>Affiché?</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item):
                    $willShow = $item['item_active'];
                ?>
                <tr>
                    <td><?= $item['item_id'] ?></td>
                    <td><?= h($item['item_type']) ?></td>
                    <td><?= h($item['item_name'] ?: '(Supprimé?)') ?></td>
                    <td class="<?= $item['item_active'] ? 'item-active' : 'item-inactive' ?>">
                        <?= $item['item_active'] ? '✓ Actif' : '✗ Inactif' ?>
                    </td>
                    <td class="<?= $willShow ? 'item-active' : 'item-inactive' ?>">
                        <?= $willShow ? '✓ OUI' : '✗ NON' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $activeCount = count(array_filter($items, fn($i) => $i['item_active']));
        $inactiveCount = count($items) - $activeCount;
        if ($inactiveCount > 0):
        ?>
        <div class="warning">
            ⚠️ <?= $inactiveCount ?> item(s) inactif(s) ne seront pas affichés.
            Pour les afficher, activez-les dans leur gestion respective (Produits, Packs, etc.)
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <?php elseif ($section['type'] === 'featured_category'): ?>
        <div class="info">
            Catégorie ID: <?= h($section['config']['category_id'] ?? 'Non définie') ?><br>
            Limite produits: <?= h($section['config']['products_limit'] ?? 8) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <p style="margin-top: 30px;">
        <a href="/admin/homepage-builder.php" style="color: #ff69b4;">← Retour au Page Builder</a>
    </p>
</body>
</html>
