<?php
/**
 * PERSONNALY Admin - Liste des Catégories
 * Gestion des catégories de produits
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/Order.php';

Auth::requireAdmin();

$categoryModel = new Category();
$orderModel = new Order();
$pendingOrders = $orderModel->countNew();

// Actions
if (isset($_GET['action'])) {
    $id = (int) ($_GET['id'] ?? 0);

    switch ($_GET['action']) {
        case 'toggle':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $categoryModel->toggleStatus($id);
            }
            redirect('/admin/categories.php');
            break;

        case 'delete':
            if ($id && verifyCsrf($_GET['csrf'] ?? '')) {
                $categoryModel->delete($id);
            }
            redirect('/admin/categories.php');
            break;
    }
}

// Réordonnancement AJAX
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
        exit;
    }
    $order = $_POST['order'] ?? [];
    $success = $categoryModel->updateOrder($order);
    echo json_encode(['success' => $success]);
    exit;
}

$categories = $categoryModel->findAll();
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories - PERSONNALY Admin</title>
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
                <h1 class="page-title">Gérer les <span>catégories</span></h1>
                <a href="/admin/category-form.php" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nouvelle catégorie
                </a>
            </div>

            <?php if (empty($categories)): ?>
                <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📁</div>
                    <h3>Aucune catégorie</h3>
                    <p class="text-muted" style="margin-bottom: 20px;">Créez votre première catégorie pour organiser vos produits.</p>
                    <a href="/admin/category-form.php" class="btn btn-primary">Créer une catégorie</a>
                </div>
            <?php else: ?>
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Toutes les catégories</h3>
                        <span class="badge badge-mint"><?= count($categories) ?> catégorie(s)</span>
                    </div>
                    <div class="data-card-body" style="padding: 0;">
                        <p class="text-muted" style="padding: 15px 20px; margin: 0; font-size: 13px; border-bottom: 1px solid var(--gray-light);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                <circle cx="9" cy="6" r="2"/><circle cx="15" cy="6" r="2"/>
                                <circle cx="9" cy="12" r="2"/><circle cx="15" cy="12" r="2"/>
                                <circle cx="9" cy="18" r="2"/><circle cx="15" cy="18" r="2"/>
                            </svg>
                            Glissez-déposez pour réordonner les catégories
                        </p>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Catégorie</th>
                                    <th>Slug</th>
                                    <th>Produits</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="categoriesList">
                                <?php foreach ($categories as $cat): ?>
                                    <tr data-id="<?= $cat['id'] ?>" class="draggable-row">
                                        <td class="drag-handle">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="opacity: 0.4; cursor: grab;">
                                                <circle cx="9" cy="6" r="2"/><circle cx="15" cy="6" r="2"/>
                                                <circle cx="9" cy="12" r="2"/><circle cx="15" cy="12" r="2"/>
                                                <circle cx="9" cy="18" r="2"/><circle cx="15" cy="18" r="2"/>
                                            </svg>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <?php if (!empty($cat['image_url'])): ?>
                                                    <img src="/public<?= h($cat['image_url']) ?>" alt=""
                                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px;">
                                                <?php else: ?>
                                                    <div style="width: 40px; height: 40px; background: var(--gray-light); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                        📁
                                                    </div>
                                                <?php endif; ?>
                                                <strong><?= h($cat['name']) ?></strong>
                                            </div>
                                        </td>
                                        <td><code style="font-size: 12px; background: var(--gray-light); padding: 2px 6px; border-radius: 4px;"><?= h($cat['slug']) ?></code></td>
                                        <td>
                                            <span class="badge badge-mint"><?= $cat['product_count'] ?? 0 ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $cat['status'] ?>">
                                                <?= $cat['status'] === 'active' ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="/admin/category-form.php?id=<?= $cat['id'] ?>" class="btn-icon" title="Modifier">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                    </svg>
                                                </a>
                                                <a href="/admin/categories.php?action=toggle&id=<?= $cat['id'] ?>&csrf=<?= $csrf ?>"
                                                   class="btn-icon" title="<?= $cat['status'] === 'active' ? 'Désactiver' : 'Activer' ?>">
                                                    <?php if ($cat['status'] === 'active'): ?>
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
                                                <?php if (($cat['product_count'] ?? 0) == 0): ?>
                                                    <a href="/admin/categories.php?action=delete&id=<?= $cat['id'] ?>&csrf=<?= $csrf ?>"
                                                       class="btn-icon btn-icon-danger"
                                                       onclick="return confirm('Supprimer cette catégorie ?')"
                                                       title="Supprimer">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                        </svg>
                                                    </a>
                                                <?php endif; ?>
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
        .draggable-row {
            transition: background-color 0.2s ease;
        }
        .draggable-row.dragging {
            opacity: 0.5;
            background: var(--pink-light);
        }
        .draggable-row.drag-over {
            border-top: 2px solid var(--pink-main);
        }
        .drag-handle {
            cursor: grab;
        }
        .drag-handle:active {
            cursor: grabbing;
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tbody = document.getElementById('categoriesList');
        if (!tbody) return;

        let draggedRow = null;

        tbody.querySelectorAll('.draggable-row').forEach(row => {
            row.draggable = true;

            row.addEventListener('dragstart', function(e) {
                draggedRow = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            row.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                tbody.querySelectorAll('.draggable-row').forEach(r => r.classList.remove('drag-over'));
                saveOrder();
            });

            row.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (this !== draggedRow) {
                    tbody.querySelectorAll('.draggable-row').forEach(r => r.classList.remove('drag-over'));
                    this.classList.add('drag-over');
                }
            });

            row.addEventListener('drop', function(e) {
                e.preventDefault();
                if (this !== draggedRow) {
                    const rows = Array.from(tbody.querySelectorAll('.draggable-row'));
                    const draggedIdx = rows.indexOf(draggedRow);
                    const targetIdx = rows.indexOf(this);

                    if (draggedIdx < targetIdx) {
                        this.after(draggedRow);
                    } else {
                        this.before(draggedRow);
                    }
                }
            });
        });

        function saveOrder() {
            const rows = tbody.querySelectorAll('.draggable-row');
            const order = Array.from(rows).map(row => row.dataset.id);

            fetch('/admin/categories.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=reorder&csrf_token=<?= $csrf ?>&' + order.map((id, i) => `order[${i}]=${id}`).join('&')
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert('Erreur lors de la sauvegarde de l\'ordre');
                }
            });
        }
    });
    </script>
</body>
</html>
