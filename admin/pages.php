<?php
/**
 * PERSONNALY Admin - Gestion des Pages
 * Liste, création, modification et suppression des pages
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/FontLoader.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Page.php';
require_once __DIR__ . '/../app/models/Menu.php';

Auth::requireAdmin();

$pageModel = new Page();
$menuModel = new Menu();

$success = '';
$error = '';

// Traitement des actions
if (isPost()) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                $data = [
                    'title' => trim($_POST['title'] ?? ''),
                    'slug' => trim($_POST['slug'] ?? ''),
                    'meta_title' => trim($_POST['meta_title'] ?? ''),
                    'meta_description' => trim($_POST['meta_description'] ?? ''),
                    'status' => $_POST['status'] ?? 'draft'
                ];

                if (empty($data['title'])) {
                    $error = 'Le titre de la page est obligatoire.';
                } else {
                    try {
                        $newPageId = $pageModel->create($data);
                        header('Location: /admin/page-editor.php?id=' . $newPageId);
                        exit;
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la création : ' . $e->getMessage();
                    }
                }
                break;

            case 'delete':
                $pageId = (int) ($_POST['page_id'] ?? 0);
                $page = $pageModel->findById($pageId);

                if (!$page) {
                    $error = 'Page introuvable.';
                } elseif ($page['is_system']) {
                    $error = 'Impossible de supprimer une page système.';
                } else {
                    if ($pageModel->delete($pageId)) {
                        $success = 'Page supprimée avec succès.';
                    } else {
                        $error = 'Erreur lors de la suppression.';
                    }
                }
                break;

            case 'duplicate':
                $pageId = (int) ($_POST['page_id'] ?? 0);
                $newPageId = $pageModel->duplicate($pageId);

                if ($newPageId) {
                    $success = 'Page dupliquée avec succès.';
                } else {
                    $error = 'Erreur lors de la duplication.';
                }
                break;
        }
    }
}

$pages = $pageModel->findAll();
$csrf = csrfToken();

// Onglet actif
$activeTab = $_GET['tab'] ?? 'pages';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Builder - PERSONNALY Admin</title>
    <?= FontLoader::renderHead() ?>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .page-tabs {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 30px;
            background: white;
            border-radius: 10px 10px 0 0;
            overflow: hidden;
        }
        .page-tab {
            padding: 16px 24px;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .page-tab:hover {
            color: #333;
            background: #f8f8f8;
        }
        .page-tab.active {
            color: var(--primary-color, #ff69b4);
            border-bottom-color: var(--primary-color, #ff69b4);
            background: white;
        }
        .page-tab svg {
            width: 18px;
            height: 18px;
        }

        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .page-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.2s;
            border: 1px solid #eee;
        }
        .page-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .page-card.is-system {
            border-color: var(--primary-color, #ff69b4);
        }
        .page-card-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .page-card-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-card-title .system-badge {
            font-size: 10px;
            padding: 3px 8px;
            background: var(--primary-color, #ff69b4);
            color: white;
            border-radius: 20px;
            font-weight: 500;
        }
        .page-card-slug {
            font-size: 13px;
            color: #888;
            font-family: monospace;
        }
        .page-card-meta {
            padding: 15px 20px;
            background: #fafafa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        .page-status.published {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .page-status.draft {
            background: #fff3e0;
            color: #ef6c00;
        }
        .page-sections-count {
            font-size: 12px;
            color: #666;
        }
        .page-card-actions {
            padding: 15px 20px;
            display: flex;
            gap: 10px;
            border-top: 1px solid #f0f0f0;
        }
        .page-card-actions .btn {
            flex: 1;
            padding: 10px;
            font-size: 13px;
        }

        .create-page-card {
            background: #f8f8f8;
            border: 2px dashed #ddd;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .create-page-card:hover {
            border-color: var(--primary-color, #ff69b4);
            background: #fff;
        }
        .create-page-card svg {
            width: 48px;
            height: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }
        .create-page-card:hover svg {
            color: var(--primary-color, #ff69b4);
        }
        .create-page-card span {
            font-size: 14px;
            color: #888;
            font-weight: 500;
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
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <h1>Page Builder</h1>
                <a href="/" target="_blank" class="btn btn-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                    Voir le site
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Onglets -->
            <div class="page-tabs">
                <a href="?tab=pages" class="page-tab <?= $activeTab === 'pages' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="3" y1="9" x2="21" y2="9"/>
                        <line x1="9" y1="21" x2="9" y2="9"/>
                    </svg>
                    Pages
                </a>
                <a href="?tab=menus" class="page-tab <?= $activeTab === 'menus' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                    Menus
                </a>
            </div>

            <?php if ($activeTab === 'pages'): ?>
            <!-- Onglet Pages -->
            <div class="pages-grid">
                <!-- Carte création -->
                <div class="create-page-card" onclick="openCreateModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span>Créer une nouvelle page</span>
                </div>

                <?php foreach ($pages as $page): ?>
                <div class="page-card <?= $page['is_system'] ? 'is-system' : '' ?>">
                    <div class="page-card-header">
                        <h3 class="page-card-title">
                            <?= h($page['title']) ?>
                            <?php if ($page['is_system']): ?>
                                <span class="system-badge">Système</span>
                            <?php endif; ?>
                        </h3>
                        <div class="page-card-slug">/<?= h($page['slug']) ?></div>
                    </div>
                    <div class="page-card-meta">
                        <span class="page-status <?= $page['status'] ?>">
                            <?= $page['status'] === 'published' ? 'Publié' : 'Brouillon' ?>
                        </span>
                        <span class="page-sections-count">
                            <?= $page['section_count'] ?? 0 ?> section(s)
                        </span>
                    </div>
                    <div class="page-card-actions">
                        <?php if ($page['is_system'] && $page['slug'] === 'home'): ?>
                            <a href="/admin/homepage-builder.php" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Modifier
                            </a>
                        <?php else: ?>
                            <a href="/admin/page-editor.php?id=<?= $page['id'] ?>" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Modifier
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline" onclick="duplicatePage(<?= $page['id'] ?>)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </button>
                        <?php if (!$page['is_system']): ?>
                            <button type="button" class="btn btn-outline btn-danger" onclick="deletePage(<?= $page['id'] ?>, '<?= h($page['title']) ?>')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php elseif ($activeTab === 'menus'): ?>
            <!-- Onglet Menus -->
            <?php
            $menus = $menuModel->findAll();
            ?>
            <div class="pages-grid">
                <?php foreach ($menus as $menu): ?>
                <div class="page-card">
                    <div class="page-card-header">
                        <h3 class="page-card-title"><?= h($menu['name']) ?></h3>
                        <div class="page-card-slug"><?= h($menuModel->getLocations()[$menu['location']] ?? $menu['location']) ?></div>
                    </div>
                    <div class="page-card-meta">
                        <span class="page-sections-count">
                            <?= $menu['item_count'] ?? 0 ?> élément(s)
                        </span>
                    </div>
                    <div class="page-card-actions">
                        <a href="/admin/menu-editor.php?id=<?= $menu['id'] ?>" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Gérer le menu
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal création de page -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nouvelle page</h3>
                <button type="button" class="modal-close" onclick="closeCreateModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="pageTitle">Titre de la page *</label>
                        <input type="text" id="pageTitle" name="title" required class="form-control" placeholder="Ex: À propos">
                    </div>
                    <div class="form-group">
                        <label for="pageSlug">URL (slug)</label>
                        <input type="text" id="pageSlug" name="slug" class="form-control" placeholder="a-propos (généré automatiquement si vide)">
                        <small class="form-text">L'URL sera : votresite.fr/<strong id="slugPreview">votre-slug</strong></small>
                    </div>
                    <div class="form-group">
                        <label for="pageMetaTitle">Titre SEO</label>
                        <input type="text" id="pageMetaTitle" name="meta_title" class="form-control" placeholder="Titre pour les moteurs de recherche">
                    </div>
                    <div class="form-group">
                        <label for="pageMetaDesc">Description SEO</label>
                        <textarea id="pageMetaDesc" name="meta_description" class="form-control" rows="2" placeholder="Description pour les moteurs de recherche"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeCreateModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer et modifier</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formulaires cachés pour les actions -->
    <form id="deleteForm" method="post" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="page_id" id="deletePageId">
    </form>
    <form id="duplicateForm" method="post" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="duplicate">
        <input type="hidden" name="page_id" id="duplicatePageId">
    </form>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
        }

        function deletePage(pageId, pageTitle) {
            if (confirm('Supprimer la page "' + pageTitle + '" ?\n\nCette action est irréversible.')) {
                document.getElementById('deletePageId').value = pageId;
                document.getElementById('deleteForm').submit();
            }
        }

        function duplicatePage(pageId) {
            document.getElementById('duplicatePageId').value = pageId;
            document.getElementById('duplicateForm').submit();
        }

        // Mise à jour du slug preview
        document.getElementById('pageTitle').addEventListener('input', function() {
            const title = this.value;
            const slug = title.toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
            document.getElementById('pageSlug').placeholder = slug || 'votre-slug';
            document.getElementById('slugPreview').textContent = slug || 'votre-slug';
        });

        // Fermer modal avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCreateModal();
            }
        });

        // Fermer modal en cliquant à l'extérieur
        document.getElementById('createModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });
    </script>
</body>
</html>
