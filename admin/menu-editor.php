<?php
/**
 * PERSONNALY Admin - Éditeur de Menu
 * Gestion des éléments de navigation
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/FontLoader.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Menu.php';
require_once __DIR__ . '/../app/models/Page.php';
require_once __DIR__ . '/../app/models/Category.php';

Auth::requireAdmin();

$menuModel = new Menu();
$pageModel = new Page();
$categoryModel = new Category();

// Récupérer le menu
$menuId = (int) ($_GET['id'] ?? 0);
$menu = $menuModel->findById($menuId);

if (!$menu) {
    header('Location: /admin/pages.php?tab=menus');
    exit;
}

$success = '';
$error = '';

// Actions AJAX
if (isPost() && !empty($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Session expirée']);
        exit;
    }

    $action = $_POST['ajax_action'];

    switch ($action) {
        case 'add_item':
            $data = [
                'label' => trim($_POST['label'] ?? ''),
                'link_type' => $_POST['link_type'] ?? 'custom',
                'url' => trim($_POST['url'] ?? ''),
                'page_id' => !empty($_POST['page_id']) ? (int)$_POST['page_id'] : null,
                'link_target' => trim($_POST['link_target'] ?? ''),
                'open_new_tab' => isset($_POST['open_new_tab']) ? 1 : 0,
                'status' => 'active'
            ];

            if (empty($data['label'])) {
                echo json_encode(['success' => false, 'error' => 'Le libellé est requis']);
                exit;
            }

            // Résoudre l'URL selon le type
            if ($data['link_type'] === 'home') {
                $data['url'] = '/';
            } elseif ($data['link_type'] === 'products') {
                $data['url'] = '/produits';
            } elseif ($data['link_type'] === 'packs') {
                $data['url'] = '/packs';
            } elseif ($data['link_type'] === 'blog') {
                $data['url'] = '/blog';
            } elseif ($data['link_type'] === 'contact') {
                $data['url'] = '/contact';
            } elseif ($data['link_type'] === 'category' && !empty($data['link_target'])) {
                $data['url'] = '/categorie/' . $data['link_target'];
            } elseif ($data['link_type'] === 'page' && $data['page_id']) {
                $page = $pageModel->findById($data['page_id']);
                if ($page) {
                    $data['url'] = '/' . $page['slug'];
                }
            }

            $itemId = $menuModel->addItem($menuId, $data);
            if ($itemId) {
                echo json_encode(['success' => true, 'id' => $itemId]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ajout']);
            }
            break;

        case 'update_item':
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $data = [
                'label' => trim($_POST['label'] ?? ''),
                'link_type' => $_POST['link_type'] ?? 'custom',
                'url' => trim($_POST['url'] ?? ''),
                'page_id' => !empty($_POST['page_id']) ? (int)$_POST['page_id'] : null,
                'link_target' => trim($_POST['link_target'] ?? ''),
                'open_new_tab' => isset($_POST['open_new_tab']) ? 1 : 0
            ];

            // Résoudre l'URL selon le type
            if ($data['link_type'] === 'home') {
                $data['url'] = '/';
            } elseif ($data['link_type'] === 'products') {
                $data['url'] = '/produits';
            } elseif ($data['link_type'] === 'packs') {
                $data['url'] = '/packs';
            } elseif ($data['link_type'] === 'blog') {
                $data['url'] = '/blog';
            } elseif ($data['link_type'] === 'contact') {
                $data['url'] = '/contact';
            } elseif ($data['link_type'] === 'category' && !empty($data['link_target'])) {
                $data['url'] = '/categorie/' . $data['link_target'];
            } elseif ($data['link_type'] === 'page' && $data['page_id']) {
                $page = $pageModel->findById($data['page_id']);
                if ($page) {
                    $data['url'] = '/' . $page['slug'];
                }
            }

            if ($menuModel->updateItem($itemId, $data)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']);
            }
            break;

        case 'delete_item':
            $itemId = (int) ($_POST['item_id'] ?? 0);
            if ($menuModel->deleteItem($itemId)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
            }
            break;

        case 'reorder':
            $ids = json_decode($_POST['order'] ?? '[]', true);
            if ($menuModel->updateItemOrder($ids)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur de réordonnancement']);
            }
            break;

        case 'toggle_status':
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $item = $menuModel->findItemById($itemId);
            if ($item) {
                $newStatus = $item['status'] === 'active' ? 'draft' : 'active';
                $menuModel->updateItem($itemId, ['status' => $newStatus]);
                echo json_encode(['success' => true, 'status' => $newStatus]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Élément introuvable']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    }
    exit;
}

$items = $menuModel->getItems($menuId, false);
$csrf = csrfToken();

// Données pour les sélecteurs
$pages = $pageModel->findAll(true);
$categories = $categoryModel->findAllActive();
$linkTypes = $menuModel->getLinkTypes();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($menu['name']) ?> - Gestion du menu</title>
    <?= FontLoader::renderHead() ?>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .menu-editor {
            max-width: 900px;
            margin: 0 auto;
        }
        .menu-header {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .menu-header h2 {
            margin: 0;
            font-size: 20px;
        }
        .menu-header small {
            color: #888;
            display: block;
            margin-top: 5px;
        }

        .menu-items {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        .menu-items-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .menu-items-header h3 {
            margin: 0;
            font-size: 16px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        .menu-item:hover {
            background: #f8f8f8;
        }
        .menu-item.inactive {
            opacity: 0.5;
        }
        .menu-item-drag {
            color: #ccc;
            cursor: grab;
        }
        .menu-item-drag:active {
            cursor: grabbing;
        }
        .menu-item-info {
            flex: 1;
        }
        .menu-item-label {
            font-weight: 500;
            margin-bottom: 3px;
        }
        .menu-item-url {
            font-size: 12px;
            color: #888;
            font-family: monospace;
        }
        .menu-item-type {
            font-size: 11px;
            padding: 3px 8px;
            background: #f0f0f0;
            border-radius: 20px;
            color: #666;
        }
        .menu-item-actions {
            display: flex;
            gap: 8px;
        }
        .menu-item-actions button {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            background: #f0f0f0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .menu-item-actions button:hover {
            background: #e0e0e0;
        }
        .menu-item-actions button.danger:hover {
            background: #ffebee;
            color: #f44336;
        }

        .menu-items-empty {
            padding: 40px;
            text-align: center;
            color: #999;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow: auto;
        }
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            color: #666;
        }
        .modal-body {
            padding: 25px;
        }
        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color, #ff69b4);
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-check input {
            width: 18px;
            height: 18px;
        }

        /* Link type specific fields */
        .link-type-field {
            display: none;
        }
        .link-type-field.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <div>
                    <a href="/admin/pages.php?tab=menus" class="btn btn-outline btn-sm" style="margin-bottom: 10px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/>
                            <polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Retour aux menus
                    </a>
                    <h1>Gestion du menu</h1>
                </div>
            </div>

            <div class="menu-editor">
                <div class="menu-header">
                    <div>
                        <h2><?= h($menu['name']) ?></h2>
                        <small><?= h($menuModel->getLocations()[$menu['location']] ?? $menu['location']) ?></small>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="openAddModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Ajouter un élément
                    </button>
                </div>

                <div class="menu-items">
                    <div class="menu-items-header">
                        <h3>Éléments du menu</h3>
                        <small>Glissez-déposez pour réordonner</small>
                    </div>

                    <div id="itemsList">
                        <?php if (empty($items)): ?>
                        <div class="menu-items-empty">
                            <p>Aucun élément dans ce menu.<br>Cliquez sur "Ajouter un élément" pour commencer.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <div class="menu-item <?= $item['status'] !== 'active' ? 'inactive' : '' ?>"
                             data-id="<?= $item['id'] ?>"
                             draggable="true">
                            <div class="menu-item-drag">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="9" cy="6" r="2"/><circle cx="15" cy="6" r="2"/>
                                    <circle cx="9" cy="12" r="2"/><circle cx="15" cy="12" r="2"/>
                                    <circle cx="9" cy="18" r="2"/><circle cx="15" cy="18" r="2"/>
                                </svg>
                            </div>
                            <div class="menu-item-info">
                                <div class="menu-item-label"><?= h($item['label']) ?></div>
                                <div class="menu-item-url"><?= h($item['url'] ?: '(aucune URL)') ?></div>
                            </div>
                            <span class="menu-item-type"><?= h($linkTypes[$item['link_type']] ?? $item['link_type']) ?></span>
                            <div class="menu-item-actions">
                                <button type="button" onclick="editItem(<?= $item['id'] ?>)" title="Modifier">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <button type="button" onclick="toggleItem(<?= $item['id'] ?>)" title="Activer/Désactiver">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                                <button type="button" class="danger" onclick="deleteItem(<?= $item['id'] ?>)" title="Supprimer">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal ajout/édition -->
    <div class="modal" id="itemModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Ajouter un élément</h3>
                <button type="button" class="modal-close" onclick="closeModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form id="itemForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="ajax_action" id="formAction" value="add_item">
                <input type="hidden" name="item_id" id="itemId" value="">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="itemLabel">Libellé *</label>
                        <input type="text" id="itemLabel" name="label" class="form-control" required placeholder="Ex: Nos produits">
                    </div>

                    <div class="form-group">
                        <label for="itemLinkType">Type de lien</label>
                        <select id="itemLinkType" name="link_type" class="form-control">
                            <?php foreach ($linkTypes as $key => $label): ?>
                            <option value="<?= h($key) ?>"><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- URL personnalisée -->
                    <div class="form-group link-type-field" data-type="custom">
                        <label for="itemUrl">URL</label>
                        <input type="text" id="itemUrl" name="url" class="form-control" placeholder="/ma-page ou https://exemple.com">
                    </div>

                    <!-- Sélection de page -->
                    <div class="form-group link-type-field" data-type="page">
                        <label for="itemPageId">Page</label>
                        <select id="itemPageId" name="page_id" class="form-control">
                            <option value="">Sélectionner une page</option>
                            <?php foreach ($pages as $page): ?>
                            <option value="<?= $page['id'] ?>"><?= h($page['title']) ?> (/<?= h($page['slug']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sélection de catégorie -->
                    <div class="form-group link-type-field" data-type="category">
                        <label for="itemCategory">Catégorie</label>
                        <select id="itemCategory" name="link_target" class="form-control">
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= h($cat['slug']) ?>"><?= h($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="open_new_tab" id="itemNewTab" value="1">
                            Ouvrir dans un nouvel onglet
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrf = '<?= $csrf ?>';
        const menuId = <?= $menuId ?>;

        // Gestion du type de lien
        document.getElementById('itemLinkType').addEventListener('change', function() {
            updateLinkTypeFields(this.value);
        });

        function updateLinkTypeFields(type) {
            document.querySelectorAll('.link-type-field').forEach(field => {
                field.classList.remove('active');
            });

            const field = document.querySelector(`.link-type-field[data-type="${type}"]`);
            if (field) {
                field.classList.add('active');
            }
        }

        // Ouvrir modal ajout
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Ajouter un élément';
            document.getElementById('formAction').value = 'add_item';
            document.getElementById('itemId').value = '';
            document.getElementById('submitBtn').textContent = 'Ajouter';
            document.getElementById('itemForm').reset();
            updateLinkTypeFields('custom');
            document.getElementById('itemModal').classList.add('active');
        }

        // Fermer modal
        function closeModal() {
            document.getElementById('itemModal').classList.remove('active');
        }

        // Éditer un élément
        function editItem(id) {
            // Récupérer les données de l'élément depuis le DOM ou via AJAX
            const item = document.querySelector(`.menu-item[data-id="${id}"]`);
            if (!item) return;

            // Pour simplifier, on recharge la page après édition
            // En production, on ferait un appel AJAX pour récupérer les données complètes
            document.getElementById('modalTitle').textContent = 'Modifier l\'élément';
            document.getElementById('formAction').value = 'update_item';
            document.getElementById('itemId').value = id;
            document.getElementById('submitBtn').textContent = 'Enregistrer';
            document.getElementById('itemLabel').value = item.querySelector('.menu-item-label').textContent;
            updateLinkTypeFields('custom');
            document.getElementById('itemUrl').value = item.querySelector('.menu-item-url').textContent;
            document.getElementById('itemModal').classList.add('active');
        }

        // Supprimer un élément
        function deleteItem(id) {
            if (!confirm('Supprimer cet élément du menu ?')) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=delete_item&csrf_token=${csrf}&item_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`.menu-item[data-id="${id}"]`).remove();
                } else {
                    alert('Erreur : ' + data.error);
                }
            });
        }

        // Activer/désactiver un élément
        function toggleItem(id) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=toggle_status&csrf_token=${csrf}&item_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`.menu-item[data-id="${id}"]`);
                    item.classList.toggle('inactive', data.status !== 'active');
                }
            });
        }

        // Soumission du formulaire
        document.getElementById('itemForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur : ' + data.error);
                }
            });
        });

        // Drag and drop pour réordonner
        let draggedItem = null;

        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('dragstart', function(e) {
                draggedItem = this;
                this.style.opacity = '0.5';
            });

            item.addEventListener('dragend', function(e) {
                this.style.opacity = '1';
            });

            item.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            item.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedItem !== this) {
                    const list = document.getElementById('itemsList');
                    const items = Array.from(list.querySelectorAll('.menu-item'));
                    const draggedIndex = items.indexOf(draggedItem);
                    const droppedIndex = items.indexOf(this);

                    if (draggedIndex < droppedIndex) {
                        this.parentNode.insertBefore(draggedItem, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedItem, this);
                    }

                    // Sauvegarder le nouvel ordre
                    const newOrder = Array.from(list.querySelectorAll('.menu-item')).map(item => item.dataset.id);

                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `ajax_action=reorder&csrf_token=${csrf}&order=${JSON.stringify(newOrder)}`
                    });
                }
            });
        });

        // Fermer modal avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Initialiser le premier type de champ
        updateLinkTypeFields('custom');
    </script>
</body>
</html>
