<?php
/**
 * PERSONNALY Admin - Gestion Page d'Accueil
 * Liste et ordre des sections
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/HomepageSection.php';

requireAuth();

$sectionModel = new HomepageSection();
$sections = $sectionModel->findAll();

// Actions
$success = '';
$error = '';

// Toggle status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        if ($sectionModel->toggleStatus((int) $_GET['toggle'])) {
            $success = 'Statut modifié avec succès.';
            $sections = $sectionModel->findAll();
        } else {
            $error = 'Erreur lors du changement de statut.';
        }
    }
}

// Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        if ($sectionModel->delete((int) $_GET['delete'])) {
            $success = 'Section supprimée avec succès.';
            $sections = $sectionModel->findAll();
        } else {
            $error = 'Erreur lors de la suppression.';
        }
    }
}

// Reorder (AJAX)
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    header('Content-Type: application/json');
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $ids = json_decode($_POST['order'] ?? '[]', true);
        if ($sectionModel->updateOrder($ids)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur de réordonnancement']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
    }
    exit;
}

$csrf = generateCsrf();
$types = $sectionModel->getTypes();

include __DIR__ . '/includes/header.php';
?>

<div class="admin-header">
    <div class="admin-header-left">
        <h1>Page d'accueil</h1>
        <p class="admin-subtitle">Gérez les sections affichées sur la page d'accueil</p>
    </div>
    <div class="admin-header-right">
        <a href="/admin/homepage-section.php" class="btn btn-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nouvelle section
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h2>Sections actives</h2>
        <span class="badge badge-info"><?= count($sections) ?> section(s)</span>
    </div>

    <?php if (empty($sections)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📑</div>
            <h3>Aucune section</h3>
            <p>Créez votre première section pour personnaliser la page d'accueil.</p>
            <a href="/admin/homepage-section.php" class="btn btn-primary">Créer une section</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table" id="sectionsTable">
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Ordre</th>
                        <th>Type</th>
                        <th>Titre</th>
                        <th>Éléments</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sortableSections">
                    <?php foreach ($sections as $section): ?>
                        <tr data-id="<?= $section['id'] ?>">
                            <td class="drag-handle" title="Glisser pour réordonner">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/>
                                </svg>
                            </td>
                            <td>
                                <span class="sort-order-badge"><?= $section['sort_order'] + 1 ?></span>
                            </td>
                            <td>
                                <span class="type-badge type-<?= h($section['type']) ?>">
                                    <?= h($types[$section['type']] ?? $section['type']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= h($section['title'] ?: '(Sans titre)') ?></strong>
                                <?php if ($section['subtitle']): ?>
                                    <br><small class="text-muted"><?= h(substr($section['subtitle'], 0, 50)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (in_array($section['type'], ['featured_products', 'featured_packs'])): ?>
                                    <span class="badge badge-secondary"><?= $section['item_count'] ?> élément(s)</span>
                                <?php elseif ($section['type'] === 'blog_slider'): ?>
                                    <span class="badge badge-secondary">Auto</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($section['status'] === 'active'): ?>
                                    <span class="badge badge-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Brouillon</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/admin/homepage-section.php?id=<?= $section['id'] ?>" class="btn btn-sm btn-secondary" title="Modifier">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </a>
                                    <a href="?toggle=<?= $section['id'] ?>&csrf=<?= $csrf ?>" class="btn btn-sm <?= $section['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>" title="<?= $section['status'] === 'active' ? 'Désactiver' : 'Activer' ?>">
                                        <?php if ($section['status'] === 'active'): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                                <line x1="1" y1="1" x2="23" y2="23"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        <?php endif; ?>
                                    </a>
                                    <a href="?delete=<?= $section['id'] ?>&csrf=<?= $csrf ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Supprimer cette section ?')">
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
    <?php endif; ?>
</div>

<style>
.type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}
.type-hero { background: var(--gradient-pink); color: white; }
.type-featured_products { background: var(--mint-main); color: var(--black); }
.type-featured_packs { background: #9b59b6; color: white; }
.type-content_block { background: #3498db; color: white; }
.type-blog_slider { background: #e67e22; color: white; }

.sort-order-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--gray-light);
    font-weight: 600;
    font-size: 13px;
}

.drag-handle {
    cursor: grab;
    color: var(--gray);
    text-align: center;
}
.drag-handle:active { cursor: grabbing; }

#sortableSections tr.dragging {
    opacity: 0.5;
    background: var(--pink-light);
}

.action-buttons {
    display: flex;
    gap: 6px;
}
</style>

<script>
// Drag & drop pour réordonner les sections
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('sortableSections');
    if (!tbody) return;

    let draggedRow = null;

    tbody.querySelectorAll('tr').forEach(row => {
        const handle = row.querySelector('.drag-handle');
        if (!handle) return;

        handle.addEventListener('mousedown', function(e) {
            draggedRow = row;
            row.classList.add('dragging');
        });
    });

    document.addEventListener('mouseup', function() {
        if (draggedRow) {
            draggedRow.classList.remove('dragging');
            draggedRow = null;
            saveOrder();
        }
    });

    tbody.addEventListener('dragover', function(e) {
        e.preventDefault();
        if (!draggedRow) return;

        const afterElement = getDragAfterElement(tbody, e.clientY);
        if (afterElement == null) {
            tbody.appendChild(draggedRow);
        } else {
            tbody.insertBefore(draggedRow, afterElement);
        }
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('tr:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function saveOrder() {
        const ids = [...tbody.querySelectorAll('tr')].map(row => row.dataset.id);

        fetch('/admin/homepage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=reorder&order=' + encodeURIComponent(JSON.stringify(ids)) + '&csrf_token=<?= $csrf ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour les badges d'ordre
                tbody.querySelectorAll('tr').forEach((row, i) => {
                    const badge = row.querySelector('.sort-order-badge');
                    if (badge) badge.textContent = i + 1;
                });
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
