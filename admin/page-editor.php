<?php
/**
 * PERSONNALY Admin - Éditeur de Page avec Preview Live
 * Interface visuelle pour éditer les pages personnalisées
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/ImageHelper.php';
require_once __DIR__ . '/../app/helpers/FontLoader.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Page.php';
require_once __DIR__ . '/../app/models/PageSection.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Pack.php';
require_once __DIR__ . '/../app/models/BlogPost.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/Font.php';

Auth::requireAdmin();

$pageModel = new Page();
$sectionModel = new PageSection();
$productModel = new Product();
$packModel = new Pack();
$blogModel = new BlogPost();
$categoryModel = new Category();

// Récupérer la page
$pageId = (int) ($_GET['id'] ?? 0);
$page = $pageModel->findById($pageId);

if (!$page) {
    header('Location: /admin/pages.php');
    exit;
}

// Rediriger vers homepage-builder si c'est la page d'accueil
if ($page['is_system'] && $page['slug'] === 'home') {
    header('Location: /admin/homepage-builder.php');
    exit;
}

// Actions AJAX
if (isPost() && !empty($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Session expirée']);
        exit;
    }

    $action = $_POST['ajax_action'];

    switch ($action) {
        case 'update_page':
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'slug' => trim($_POST['slug'] ?? ''),
                'meta_title' => trim($_POST['meta_title'] ?? ''),
                'meta_description' => trim($_POST['meta_description'] ?? ''),
                'status' => $_POST['status'] ?? 'draft'
            ];
            if ($pageModel->update($pageId, $data)) {
                $page = $pageModel->findById($pageId);
                echo json_encode(['success' => true, 'slug' => $page['slug']]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur de mise à jour']);
            }
            break;

        case 'reorder':
            $ids = json_decode($_POST['order'] ?? '[]', true);
            if ($sectionModel->updateOrder($pageId, $ids)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur de réordonnancement']);
            }
            break;

        case 'toggle':
            $id = (int) ($_POST['section_id'] ?? 0);
            if ($sectionModel->toggleStatus($id)) {
                $section = $sectionModel->findById($id);
                echo json_encode(['success' => true, 'status' => $section['status']]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur']);
            }
            break;

        case 'delete':
            $id = (int) ($_POST['section_id'] ?? 0);
            if ($sectionModel->delete($id)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur de suppression']);
            }
            break;

        case 'save':
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            $isEdit = $sectionId > 0;

            $data = [
                'page_id' => $pageId,
                'type' => $_POST['type'] ?? 'content_block',
                'title' => trim($_POST['title'] ?? ''),
                'subtitle' => trim($_POST['subtitle'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'cta_text' => trim($_POST['cta_text'] ?? ''),
                'cta_url' => sanitizeUrl($_POST['cta_url'] ?? ''),
                'media_type' => $_POST['media_type'] ?? 'none',
                'status' => $_POST['status'] ?? 'draft',
                'config' => []
            ];

            // Conserver media existant
            if ($isEdit) {
                $existing = $sectionModel->findById($sectionId);
                if ($existing) {
                    if (!empty($_POST['clear_media']) && $_POST['clear_media'] === '1') {
                        $data['media_url'] = null;
                        $data['media_type'] = 'none';
                    } else {
                        $data['media_url'] = $existing['media_url'];
                        $data['media_type'] = $existing['media_type'];
                    }
                }
            }

            // Upload image si fournie
            $uploadDir = __DIR__ . '/../public/uploads/pages/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (!empty($_FILES['media_file']['tmp_name']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];

                if (in_array($ext, $allowed)) {
                    $filename = 'page_' . $pageId . '_' . time() . '_' . uniqid() . '.' . $ext;
                    $fullPath = $uploadDir . $filename;

                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $fullPath)) {
                        $data['media_url'] = '/uploads/pages/' . $filename;
                        $data['media_type'] = in_array($ext, ['mp4', 'webm']) ? 'video' : 'image';
                        if ($data['media_type'] === 'image') {
                            ImageHelper::convertToWebP($fullPath);
                        }
                    }
                }
            }

            // Items (produits/packs/articles)
            $items = [];
            if ($data['type'] === 'featured_products' && !empty($_POST['product_ids'])) {
                foreach ($_POST['product_ids'] as $pid) {
                    $items[] = ['type' => 'product', 'id' => (int) $pid];
                }
            } elseif ($data['type'] === 'featured_packs' && !empty($_POST['pack_ids'])) {
                foreach ($_POST['pack_ids'] as $pid) {
                    $items[] = ['type' => 'pack', 'id' => (int) $pid];
                }
            } elseif ($data['type'] === 'blog_slider' && !empty($_POST['blog_ids'])) {
                foreach ($_POST['blog_ids'] as $bid) {
                    $items[] = ['type' => 'blog', 'id' => (int) $bid];
                }
            } elseif ($data['type'] === 'featured_category' && !empty($_POST['category_id'])) {
                $data['config']['category_id'] = (int) $_POST['category_id'];
                $data['config']['products_limit'] = (int) ($_POST['products_limit'] ?? 8);
            }
            $data['items'] = $items;

            // Style
            $data['config']['style'] = [
                'background_color' => !empty($_POST['style_bg_color']) ? $_POST['style_bg_color'] : null,
                'text_color' => !empty($_POST['style_text_color']) ? $_POST['style_text_color'] : null,
                'padding_y' => $_POST['style_padding_y'] ?? 'medium',
            ];

            try {
                if ($isEdit) {
                    $sectionModel->update($sectionId, $data);
                    $response = ['success' => true, 'id' => $sectionId];
                } else {
                    $newId = $sectionModel->create($data);
                    $response = ['success' => true, 'id' => $newId, 'isNew' => true];
                }
                echo json_encode($response);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'get_section':
            $id = (int) ($_POST['section_id'] ?? 0);
            $section = $sectionModel->findById($id);
            if ($section) {
                echo json_encode(['success' => true, 'section' => $section]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Section introuvable']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    }
    exit;
}

$sections = $sectionModel->findByPage($pageId);
$csrf = csrfToken();
$types = $sectionModel->getTypes();

// Données pour les sélecteurs
$products = $productModel->findActive();
$packs = $packModel->findActive();
$categories = $categoryModel->findAllActive();
$blogPosts = $blogModel->findAll();

// Polices
$fontModel = new Font();
$fonts = $fontModel->findActive();

$typeIcons = [
    'hero' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg>',
    'featured_products' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
    'featured_packs' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
    'featured_category' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
    'content_block' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>',
    'blog_slider' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
    'newsletter' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    'text_only' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>',
    'image_gallery' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
    'video' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
    'faq' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    'testimonials' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
    'contact_form' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page['title']) ?> - Page Builder</title>
    <?= FontLoader::renderHead() ?>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        /* Layout 3 colonnes comme homepage-builder */
        .builder-body {
            margin: 0;
            padding: 0;
            background: #1a1a2e;
            overflow: hidden;
        }
        .builder-layout {
            display: flex;
            height: 100vh;
        }

        /* Sidebar gauche */
        .builder-sidebar {
            width: 280px;
            background: #1a1a2e;
            color: white;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-back {
            color: rgba(255,255,255,0.6);
            transition: color 0.2s;
        }
        .sidebar-back:hover {
            color: white;
        }
        .sidebar-header h1 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Page Info */
        .page-info {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.02);
        }
        .page-info-slug {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            font-family: monospace;
            margin-bottom: 8px;
        }
        .page-info-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
        }
        .page-info-status.published {
            background: rgba(76, 175, 80, 0.2);
            color: #81c784;
        }
        .page-info-status.draft {
            background: rgba(255, 152, 0, 0.2);
            color: #ffb74d;
        }
        .page-edit-btn {
            margin-top: 10px;
            padding: 6px 12px;
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .page-edit-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Sections List */
        .sidebar-sections {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        .section-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .section-item:hover {
            background: rgba(255,255,255,0.1);
        }
        .section-item.active {
            background: var(--primary-color, #ff69b4);
        }
        .section-item.is-draft {
            opacity: 0.6;
        }
        .section-drag {
            color: rgba(255,255,255,0.3);
            cursor: grab;
        }
        .section-icon {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .section-info {
            flex: 1;
            min-width: 0;
        }
        .section-name {
            display: block;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .section-type-label {
            display: block;
            font-size: 11px;
            color: rgba(255,255,255,0.5);
        }
        .section-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .section-status.active { background: #4caf50; }
        .section-status.draft { background: #ff9800; }

        .add-section-btn {
            margin: 15px;
            padding: 12px;
            background: var(--primary-color, #ff69b4);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .add-section-btn:hover {
            filter: brightness(1.1);
        }

        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .preview-site-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
        }
        .preview-site-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Zone Preview centrale */
        .builder-preview {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f0f2f5;
            overflow: hidden;
        }
        .preview-toolbar {
            padding: 12px 20px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .device-toggle {
            display: flex;
            gap: 5px;
            background: #f0f0f0;
            padding: 4px;
            border-radius: 8px;
        }
        .device-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 6px;
            background: transparent;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            transition: all 0.2s;
        }
        .device-btn:hover { background: #e0e0e0; }
        .device-btn.active {
            background: white;
            color: var(--primary-color, #ff69b4);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .refresh-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            background: #f0f0f0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            transition: all 0.2s;
        }
        .refresh-btn:hover {
            background: #e0e0e0;
        }

        .preview-container {
            flex: 1;
            padding: 20px;
            display: flex;
            justify-content: center;
            overflow: auto;
        }
        .preview-frame-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: width 0.3s ease;
            width: 100%;
            max-width: 100%;
            position: relative;
        }
        .preview-frame-wrapper.tablet { width: 768px; }
        .preview-frame-wrapper.mobile { width: 375px; }

        .preview-frame-wrapper iframe {
            width: 100%;
            height: 100%;
            min-height: calc(100vh - 140px);
            border: none;
        }

        /* Panneau Propriétés à droite */
        .builder-properties {
            width: 380px;
            background: white;
            border-left: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .properties-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .properties-header h2 {
            font-size: 16px;
            margin: 0;
        }
        .properties-actions {
            display: flex;
            gap: 8px;
        }
        .prop-action-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            background: #f0f0f0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .prop-action-btn:hover { background: #e0e0e0; }
        .prop-action-btn.danger:hover {
            background: #ffebee;
            color: #f44336;
        }

        .properties-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        .properties-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #999;
            text-align: center;
            padding: 40px;
        }
        .properties-empty svg {
            margin-bottom: 20px;
            color: #ddd;
        }

        .prop-group {
            margin-bottom: 20px;
        }
        .prop-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 6px;
            color: #555;
        }
        .prop-group input[type="text"],
        .prop-group input[type="number"],
        .prop-group textarea,
        .prop-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .prop-group input:focus,
        .prop-group textarea:focus,
        .prop-group select:focus {
            outline: none;
            border-color: var(--primary-color, #ff69b4);
        }

        .prop-section {
            border-top: 1px solid #e0e0e0;
            padding-top: 20px;
            margin-top: 20px;
        }
        .prop-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .prop-footer {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            background: #fafafa;
        }
        .prop-save-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary-color, #ff69b4);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .prop-save-btn:hover {
            filter: brightness(1.1);
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
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
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
        .modal-header h3 { margin: 0; font-size: 18px; }
        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            color: #666;
        }
        .modal-body { padding: 25px; }
        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Section type grid */
        .section-types-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .section-type-card {
            padding: 15px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .section-type-card:hover {
            border-color: var(--primary-color, #ff69b4);
            background: #fff5f8;
        }
        .section-type-card.selected {
            border-color: var(--primary-color, #ff69b4);
            background: #fff5f8;
        }
        .section-type-card svg {
            width: 28px;
            height: 28px;
            margin-bottom: 8px;
            color: #666;
        }
        .section-type-card span {
            display: block;
            font-size: 10px;
            color: #666;
            line-height: 1.3;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
    </style>
</head>
<body class="builder-body">
    <div class="builder-layout">
        <!-- Sidebar gauche -->
        <aside class="builder-sidebar">
            <div class="sidebar-header">
                <a href="/admin/pages.php" class="sidebar-back" title="Retour aux pages">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                </a>
                <h1><?= h($page['title']) ?></h1>
            </div>

            <div class="page-info">
                <div class="page-info-slug">/<?= h($page['slug']) ?></div>
                <span class="page-info-status <?= $page['status'] ?>">
                    <?= $page['status'] === 'published' ? 'Publié' : 'Brouillon' ?>
                </span>
                <button type="button" class="page-edit-btn" onclick="openPageModal()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Modifier
                </button>
            </div>

            <div class="sidebar-sections" id="sectionsList">
                <?php foreach ($sections as $section): ?>
                <div class="section-item <?= $section['status'] === 'draft' ? 'is-draft' : '' ?>"
                     data-id="<?= $section['id'] ?>"
                     data-type="<?= h($section['type']) ?>"
                     draggable="true">
                    <div class="section-drag">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="9" cy="6" r="2"/><circle cx="15" cy="6" r="2"/>
                            <circle cx="9" cy="12" r="2"/><circle cx="15" cy="12" r="2"/>
                            <circle cx="9" cy="18" r="2"/><circle cx="15" cy="18" r="2"/>
                        </svg>
                    </div>
                    <div class="section-icon">
                        <?= $typeIcons[$section['type'] ?? 'content_block'] ?? '' ?>
                    </div>
                    <div class="section-info">
                        <span class="section-name"><?= h($section['title'] ?: ($types[$section['type'] ?? ''] ?? 'Section')) ?></span>
                        <span class="section-type-label"><?= h($types[$section['type'] ?? ''] ?? 'Inconnu') ?></span>
                    </div>
                    <div class="section-status <?= $section['status'] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="add-section-btn" onclick="openAddModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Ajouter une section
            </button>

            <div class="sidebar-footer">
                <a href="/<?= h($page['slug']) ?>" target="_blank" class="preview-site-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                    Voir la page
                </a>
            </div>
        </aside>

        <!-- Zone Preview centrale -->
        <main class="builder-preview">
            <div class="preview-toolbar">
                <div class="device-toggle">
                    <button type="button" class="device-btn active" data-device="desktop" title="Desktop">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                    </button>
                    <button type="button" class="device-btn" data-device="tablet" title="Tablette">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="4" y="2" width="16" height="20" rx="2"/>
                            <line x1="12" y1="18" x2="12.01" y2="18"/>
                        </svg>
                    </button>
                    <button type="button" class="device-btn" data-device="mobile" title="Mobile">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="5" y="2" width="14" height="20" rx="2"/>
                            <line x1="12" y1="18" x2="12.01" y2="18"/>
                        </svg>
                    </button>
                </div>
                <button type="button" class="refresh-btn" onclick="refreshPreview()" title="Actualiser">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                </button>
            </div>
            <div class="preview-container">
                <div class="preview-frame-wrapper" id="previewWrapper">
                    <iframe id="previewFrame" src="/<?= h($page['slug']) ?>?preview=builder&t=<?= time() ?>"></iframe>
                </div>
            </div>
        </main>

        <!-- Panneau Propriétés à droite -->
        <aside class="builder-properties">
            <div class="properties-empty" id="propertiesEmpty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                <p>Sélectionnez une section<br>pour la modifier</p>
            </div>

            <div id="propertiesContent" style="display: none;">
                <div class="properties-header">
                    <h2 id="propertiesTitle">Modifier</h2>
                    <div class="properties-actions">
                        <button type="button" class="prop-action-btn" id="toggleStatusBtn" title="Activer/Désactiver">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                        <button type="button" class="prop-action-btn danger" id="deleteBtn" title="Supprimer">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <form id="sectionForm" class="properties-content" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="ajax_action" value="save">
                    <input type="hidden" name="section_id" id="sectionId" value="0">
                    <input type="hidden" name="status" id="sectionStatus" value="draft">

                    <div class="prop-group">
                        <label>Type</label>
                        <select name="type" id="sectionType">
                            <?php foreach ($types as $typeKey => $typeLabel): ?>
                            <option value="<?= h($typeKey) ?>"><?= h($typeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="prop-group">
                        <label>Titre</label>
                        <input type="text" name="title" id="propTitle" placeholder="Titre de la section">
                    </div>

                    <div class="prop-group">
                        <label>Sous-titre</label>
                        <textarea name="subtitle" id="propSubtitle" rows="2" placeholder="Description"></textarea>
                    </div>

                    <div class="prop-group" id="contentGroup">
                        <label>Contenu</label>
                        <textarea name="content" id="propContent" rows="4" placeholder="Contenu texte"></textarea>
                    </div>

                    <!-- CTA -->
                    <div class="prop-section" id="ctaFields">
                        <div class="prop-section-title">Bouton d'action</div>
                        <div class="prop-group">
                            <label>Texte</label>
                            <input type="text" name="cta_text" id="propCtaText" placeholder="En savoir plus">
                        </div>
                        <div class="prop-group">
                            <label>URL</label>
                            <input type="text" name="cta_url" id="propCtaUrl" placeholder="/ma-page">
                        </div>
                    </div>

                    <!-- Media -->
                    <div class="prop-section" id="mediaFields">
                        <div class="prop-section-title">Média</div>
                        <div class="prop-group">
                            <label>Image / Vidéo</label>
                            <input type="file" name="media_file" id="propMediaFile" accept="image/*,video/mp4,video/webm">
                        </div>
                        <div id="currentMedia" style="display: none; margin-top: 10px;">
                            <small>Média actuel :</small>
                            <div id="mediaPreview" style="margin-top: 5px;"></div>
                            <label style="display: flex; align-items: center; gap: 5px; margin-top: 5px; font-size: 12px;">
                                <input type="checkbox" name="clear_media" value="1" id="clearMedia"> Supprimer
                            </label>
                        </div>
                    </div>

                    <!-- Produits -->
                    <div class="prop-section" id="productsFields" style="display: none;">
                        <div class="prop-section-title">Produits</div>
                        <div class="prop-group">
                            <select name="product_ids[]" id="propProductIds" multiple style="height: 120px;">
                                <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>"><?= h($product['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>Ctrl + clic pour sélectionner plusieurs</small>
                        </div>
                    </div>

                    <!-- Packs -->
                    <div class="prop-section" id="packsFields" style="display: none;">
                        <div class="prop-section-title">Packs</div>
                        <div class="prop-group">
                            <select name="pack_ids[]" id="propPackIds" multiple style="height: 120px;">
                                <?php foreach ($packs as $pack): ?>
                                <option value="<?= $pack['id'] ?>"><?= h($pack['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Catégorie -->
                    <div class="prop-section" id="categoryFields" style="display: none;">
                        <div class="prop-section-title">Catégorie</div>
                        <div class="prop-group">
                            <select name="category_id" id="propCategoryId">
                                <option value="">Sélectionner</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="prop-group">
                            <label>Nb produits</label>
                            <input type="number" name="products_limit" id="propProductsLimit" value="8" min="1" max="20">
                        </div>
                    </div>

                    <!-- Style -->
                    <div class="prop-section">
                        <div class="prop-section-title">Style</div>
                        <div class="prop-group">
                            <label>Fond</label>
                            <input type="color" name="style_bg_color" id="propBgColor" value="#ffffff" style="width: 100%; height: 40px;">
                        </div>
                        <div class="prop-group">
                            <label>Espacement</label>
                            <select name="style_padding_y" id="propPaddingY">
                                <option value="none">Aucun</option>
                                <option value="small">Petit</option>
                                <option value="medium" selected>Moyen</option>
                                <option value="large">Grand</option>
                            </select>
                        </div>
                    </div>
                </form>

                <div class="prop-footer">
                    <button type="button" class="prop-save-btn" onclick="saveSection()">
                        Enregistrer
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- Modal infos page -->
    <div class="modal" id="pageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifier la page</h3>
                <button type="button" class="modal-close" onclick="closePageModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form id="pageForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="ajax_action" value="update_page">
                <div class="modal-body">
                    <div class="prop-group">
                        <label>Titre</label>
                        <input type="text" name="title" id="pageTitle" value="<?= h($page['title']) ?>" class="form-control">
                    </div>
                    <div class="prop-group">
                        <label>URL (slug)</label>
                        <input type="text" name="slug" id="pageSlug" value="<?= h($page['slug']) ?>" class="form-control">
                    </div>
                    <div class="prop-group">
                        <label>Titre SEO</label>
                        <input type="text" name="meta_title" value="<?= h($page['meta_title'] ?? '') ?>" class="form-control">
                    </div>
                    <div class="prop-group">
                        <label>Description SEO</label>
                        <textarea name="meta_description" class="form-control" rows="2"><?= h($page['meta_description'] ?? '') ?></textarea>
                    </div>
                    <div class="prop-group">
                        <label>Statut</label>
                        <select name="status" id="pageStatus" class="form-control">
                            <option value="draft" <?= $page['status'] === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                            <option value="published" <?= $page['status'] === 'published' ? 'selected' : '' ?>>Publié</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closePageModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal ajout section -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter une section</h3>
                <button type="button" class="modal-close" onclick="closeAddModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="section-types-grid">
                    <?php foreach ($types as $typeKey => $typeLabel): ?>
                    <div class="section-type-card" data-type="<?= h($typeKey) ?>">
                        <?= $typeIcons[$typeKey] ?? '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>' ?>
                        <span><?= h($typeLabel) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeAddModal()">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirmAddBtn" disabled>Ajouter</button>
            </div>
        </div>
    </div>

    <script>
        const csrf = '<?= $csrf ?>';
        const pageId = <?= $pageId ?>;
        let pageSlug = '<?= h($page['slug']) ?>';
        let currentSectionId = null;
        let selectedType = null;

        // Device toggle
        document.querySelectorAll('.device-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const device = btn.dataset.device;
                const wrapper = document.getElementById('previewWrapper');
                wrapper.classList.remove('tablet', 'mobile');
                if (device !== 'desktop') wrapper.classList.add(device);
            });
        });

        function refreshPreview() {
            const frame = document.getElementById('previewFrame');
            frame.src = '/' + pageSlug + '?preview=builder&t=' + Date.now();
        }

        // Section selection
        document.querySelectorAll('.section-item').forEach(item => {
            item.addEventListener('click', () => selectSection(item.dataset.id));
        });

        function selectSection(id) {
            currentSectionId = id;
            document.querySelectorAll('.section-item').forEach(item => {
                item.classList.toggle('active', item.dataset.id == id);
            });

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=get_section&csrf_token=${csrf}&section_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) showProperties(data.section);
            });
        }

        function showProperties(section) {
            document.getElementById('propertiesEmpty').style.display = 'none';
            document.getElementById('propertiesContent').style.display = 'flex';
            document.getElementById('propertiesContent').style.flexDirection = 'column';

            document.getElementById('sectionId').value = section.id;
            document.getElementById('sectionType').value = section.type;
            document.getElementById('sectionStatus').value = section.status;
            document.getElementById('propTitle').value = section.title || '';
            document.getElementById('propSubtitle').value = section.subtitle || '';
            document.getElementById('propContent').value = section.content || '';
            document.getElementById('propCtaText').value = section.cta_text || '';
            document.getElementById('propCtaUrl').value = section.cta_url || '';

            // Media
            if (section.media_url) {
                document.getElementById('currentMedia').style.display = 'block';
                const preview = document.getElementById('mediaPreview');
                if (section.media_type === 'video') {
                    preview.innerHTML = `<video src="${section.media_url}" style="max-width: 100%; max-height: 80px;" controls></video>`;
                } else {
                    preview.innerHTML = `<img src="${section.media_url}" style="max-width: 100%; max-height: 80px;">`;
                }
            } else {
                document.getElementById('currentMedia').style.display = 'none';
            }
            document.getElementById('clearMedia').checked = false;

            // Style
            if (section.config && section.config.style) {
                document.getElementById('propBgColor').value = section.config.style.background_color || '#ffffff';
                document.getElementById('propPaddingY').value = section.config.style.padding_y || 'medium';
            }

            updateTypeFields(section.type);

            // Products/Packs/Category
            if (section.type === 'featured_products' && section.items) {
                const select = document.getElementById('propProductIds');
                Array.from(select.options).forEach(opt => {
                    opt.selected = section.items.some(i => i.item_type === 'product' && i.item_id == opt.value);
                });
            }
            if (section.type === 'featured_packs' && section.items) {
                const select = document.getElementById('propPackIds');
                Array.from(select.options).forEach(opt => {
                    opt.selected = section.items.some(i => i.item_type === 'pack' && i.item_id == opt.value);
                });
            }
            if (section.type === 'featured_category' && section.config) {
                document.getElementById('propCategoryId').value = section.config.category_id || '';
                document.getElementById('propProductsLimit').value = section.config.products_limit || 8;
            }
        }

        function updateTypeFields(type) {
            document.getElementById('productsFields').style.display = type === 'featured_products' ? 'block' : 'none';
            document.getElementById('packsFields').style.display = type === 'featured_packs' ? 'block' : 'none';
            document.getElementById('categoryFields').style.display = type === 'featured_category' ? 'block' : 'none';
        }

        document.getElementById('sectionType').addEventListener('change', function() {
            updateTypeFields(this.value);
        });

        function saveSection() {
            const form = document.getElementById('sectionForm');
            const formData = new FormData(form);

            fetch('', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.isNew) {
                        location.reload();
                    } else {
                        const item = document.querySelector(`.section-item[data-id="${data.id}"]`);
                        if (item) {
                            item.querySelector('.section-name').textContent = document.getElementById('propTitle').value || 'Section';
                        }
                        refreshPreview();
                    }
                } else {
                    alert('Erreur : ' + data.error);
                }
            });
        }

        // Toggle status
        document.getElementById('toggleStatusBtn').addEventListener('click', function() {
            if (!currentSectionId) return;
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=toggle&csrf_token=${csrf}&section_id=${currentSectionId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('sectionStatus').value = data.status;
                    const item = document.querySelector(`.section-item[data-id="${currentSectionId}"]`);
                    if (item) {
                        item.classList.toggle('is-draft', data.status === 'draft');
                        item.querySelector('.section-status').className = 'section-status ' + data.status;
                    }
                    refreshPreview();
                }
            });
        });

        // Delete
        document.getElementById('deleteBtn').addEventListener('click', function() {
            if (!currentSectionId || !confirm('Supprimer cette section ?')) return;
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=delete&csrf_token=${csrf}&section_id=${currentSectionId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`.section-item[data-id="${currentSectionId}"]`).remove();
                    document.getElementById('propertiesEmpty').style.display = 'flex';
                    document.getElementById('propertiesContent').style.display = 'none';
                    currentSectionId = null;
                    refreshPreview();
                }
            });
        });

        // Page modal
        function openPageModal() { document.getElementById('pageModal').classList.add('active'); }
        function closePageModal() { document.getElementById('pageModal').classList.remove('active'); }

        document.getElementById('pageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('', { method: 'POST', body: new FormData(this) })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    pageSlug = data.slug;
                    location.reload();
                } else {
                    alert('Erreur : ' + data.error);
                }
            });
        });

        // Add modal
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
            selectedType = null;
            document.getElementById('confirmAddBtn').disabled = true;
            document.querySelectorAll('.section-type-card').forEach(c => c.classList.remove('selected'));
        }
        function closeAddModal() { document.getElementById('addModal').classList.remove('active'); }

        document.querySelectorAll('.section-type-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.section-type-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                selectedType = card.dataset.type;
                document.getElementById('confirmAddBtn').disabled = false;
            });
        });

        document.getElementById('confirmAddBtn').addEventListener('click', () => {
            if (!selectedType) return;
            const formData = new FormData();
            formData.append('ajax_action', 'save');
            formData.append('csrf_token', csrf);
            formData.append('section_id', '0');
            formData.append('type', selectedType);
            formData.append('status', 'draft');

            fetch('', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Erreur : ' + data.error);
            });
        });

        // Drag & Drop
        let draggedItem = null;
        document.querySelectorAll('.section-item').forEach(item => {
            item.addEventListener('dragstart', function() {
                draggedItem = this;
                this.style.opacity = '0.5';
            });
            item.addEventListener('dragend', function() {
                this.style.opacity = '1';
            });
            item.addEventListener('dragover', e => e.preventDefault());
            item.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedItem !== this) {
                    const list = document.getElementById('sectionsList');
                    const items = Array.from(list.querySelectorAll('.section-item'));
                    const draggedIndex = items.indexOf(draggedItem);
                    const droppedIndex = items.indexOf(this);
                    if (draggedIndex < droppedIndex) {
                        this.parentNode.insertBefore(draggedItem, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedItem, this);
                    }
                    const newOrder = Array.from(list.querySelectorAll('.section-item')).map(i => i.dataset.id);
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `ajax_action=reorder&csrf_token=${csrf}&order=${JSON.stringify(newOrder)}`
                    }).then(() => refreshPreview());
                }
            });
        });

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closePageModal();
                closeAddModal();
            }
        });
    </script>
</body>
</html>
