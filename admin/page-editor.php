<?php
/**
 * PERSONNALY Admin - Visual Page Builder
 * Interface visuelle intuitive pour construire les pages personnalisées
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

// Récupérer la page
$pageModel = new Page();
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

$sectionModel = new PageSection();
$productModel = new Product();
$packModel = new Pack();
$blogModel = new BlogPost();
$categoryModel = new Category();

// Actions AJAX
if (isPost() && !empty($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Session expirée']);
        exit;
    }

    $action = $_POST['ajax_action'];

    switch ($action) {
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
                'type' => $_POST['type'] ?? 'hero',
                'title' => trim($_POST['title'] ?? ''),
                'subtitle' => trim($_POST['subtitle'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'cta_text' => trim($_POST['cta_text'] ?? ''),
                'cta_url' => sanitizeUrl($_POST['cta_url'] ?? ''),
                'media_type' => $_POST['media_type'] ?? 'none',
                'status' => $_POST['status'] ?? 'draft',
                'config' => []
            ];

            // Conserver media existant (sauf si demande de suppression)
            if ($isEdit) {
                $existing = $sectionModel->findById($sectionId);
                if ($existing) {
                    // Vérifier si l'utilisateur veut supprimer l'image
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
                if (!@mkdir($uploadDir, 0755, true)) {
                    $uploadError = 'Impossible de créer le dossier uploads/pages/';
                }
            }

            // Vérifier si le dossier est accessible en écriture
            $uploadError = null;
            if (is_dir($uploadDir) && !is_writable($uploadDir)) {
                $uploadError = 'Le dossier uploads/pages/ n\'est pas accessible en écriture. Vérifiez les permissions (chmod 755 ou 777).';
            }

            if (!empty($_FILES['media_file']['tmp_name']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];

                if (in_array($ext, $allowed)) {
                    $filename = 'section_' . time() . '_' . uniqid() . '.' . $ext;
                    $fullPath = $uploadDir . $filename;

                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $fullPath)) {
                        $data['media_url'] = '/uploads/pages/' . $filename;
                        $data['media_type'] = in_array($ext, ['mp4', 'webm']) ? 'video' : 'image';
                        if ($data['media_type'] === 'image') {
                            ImageHelper::convertToWebP($fullPath);
                        }
                    } else {
                        $uploadError = 'Échec du déplacement du fichier uploadé';
                    }
                } else {
                    $uploadError = 'Extension non autorisée: ' . $ext;
                }
            } elseif (!empty($_FILES['media_file']['error']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la limite upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la limite MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
                    UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                    UPLOAD_ERR_CANT_WRITE => 'Échec d\'écriture sur le disque',
                ];
                $uploadError = $errorMessages[$_FILES['media_file']['error']] ?? 'Erreur upload: ' . $_FILES['media_file']['error'];
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

            // Config Hero
            if ($data['type'] === 'hero') {
                $data['config']['badge'] = trim($_POST['hero_badge'] ?? '');
                $data['config']['highlight'] = trim($_POST['hero_highlight'] ?? '');
                $data['config']['cta2_text'] = trim($_POST['hero_cta2_text'] ?? '');
                $data['config']['cta2_url'] = sanitizeUrl($_POST['hero_cta2_url'] ?? '');
            }

            // Style
            $data['config']['style'] = [
                'background_color' => !empty($_POST['style_bg_color']) ? $_POST['style_bg_color'] : null,
                'text_color' => !empty($_POST['style_text_color']) ? $_POST['style_text_color'] : null,
                'padding_y' => $_POST['style_padding_y'] ?? 'medium',
            ];

            // Typography
            $data['config']['typography'] = [
                'font_family' => !empty($_POST['typo_font_family']) ? $_POST['typo_font_family'] : null,
                'subtitle_font_family' => !empty($_POST['typo_subtitle_font_family']) ? $_POST['typo_subtitle_font_family'] : null,
                'title_size' => !empty($_POST['typo_title_size']) ? $_POST['typo_title_size'] : null,
                'title_color' => !empty($_POST['typo_title_color']) && $_POST['typo_title_color'] !== '#1a1a1a' ? $_POST['typo_title_color'] : null,
                'subtitle_color' => !empty($_POST['typo_subtitle_color']) && $_POST['typo_subtitle_color'] !== '#666666' ? $_POST['typo_subtitle_color'] : null,
                'bold' => ($_POST['typo_bold'] ?? '0') === '1' ? '1' : null,
                'italic' => ($_POST['typo_italic'] ?? '0') === '1' ? '1' : null,
                'underline' => ($_POST['typo_underline'] ?? '0') === '1' ? '1' : null,
                'uppercase' => ($_POST['typo_uppercase'] ?? '0') === '1' ? '1' : null,
                'subtitle_bold' => ($_POST['typo_subtitle_bold'] ?? '0') === '1' ? '1' : null,
                'subtitle_italic' => ($_POST['typo_subtitle_italic'] ?? '0') === '1' ? '1' : null,
                'subtitle_underline' => ($_POST['typo_subtitle_underline'] ?? '0') === '1' ? '1' : null,
                'align' => !empty($_POST['typo_align']) && $_POST['typo_align'] !== 'center' ? $_POST['typo_align'] : null,
                'offset_x' => !empty($_POST['typo_offset_x']) && $_POST['typo_offset_x'] !== '0' ? (int)$_POST['typo_offset_x'] : null,
                'offset_y' => !empty($_POST['typo_offset_y']) && $_POST['typo_offset_y'] !== '0' ? (int)$_POST['typo_offset_y'] : null,
            ];
            // Nettoyer les valeurs null
            $data['config']['typography'] = array_filter($data['config']['typography'], fn($v) => $v !== null);

            try {
                if ($isEdit) {
                    $sectionModel->update($sectionId, $data);
                    $response = ['success' => true, 'id' => $sectionId];
                } else {
                    $newId = $sectionModel->create($data);
                    $response = ['success' => true, 'id' => $newId, 'isNew' => true];
                }
                // Ajouter l'erreur d'upload si présente (pour debug)
                if ($uploadError) {
                    $response['uploadWarning'] = $uploadError;
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

$sections = $sectionModel->findByPage($pageId, false);
$csrf = csrfToken();
$types = $sectionModel->getTypes();

// Données pour les sélecteurs
$products = $productModel->findActive();
$packs = $packModel->findActive();
$categories = $categoryModel->findAllActive();
$blogPosts = $blogModel->findAll();

// Polices depuis la BDD
$fontModel = new Font();
$fonts = $fontModel->findActive();

$typeIcons = [
    'hero' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg>',
    'featured_products' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
    'featured_packs' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
    'featured_category' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
    'content_block' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>',
    'blog_slider' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
    'newsletter' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>'
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
</head>
<body class="builder-body">
    <!-- Layout 3 colonnes -->
    <div class="builder-layout">
        <!-- Sidebar gauche : Arborescence -->
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

            <div class="sidebar-sections" id="sectionsList">
                <?php foreach ($sections as $index => $section): ?>
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
                    <div class="section-icon type-<?= h($section['type'] ?? 'hero') ?>">
                        <?= $typeIcons[$section['type'] ?? ''] ?? '' ?>
                    </div>
                    <div class="section-info">
                        <span class="section-name"><?= h($section['title'] ?: ($types[$section['type'] ?? ''] ?? 'Section')) ?></span>
                        <span class="section-type-label"><?= h($types[$section['type'] ?? ''] ?? 'Inconnu') ?></span>
                    </div>
                    <div class="section-status <?= $section['status'] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="add-section-btn" id="addSectionBtn">
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

        <!-- Centre : Preview Live -->
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
                    <div class="preview-overlay" id="previewOverlay"></div>
                </div>
            </div>
        </main>

        <!-- Panneau droit : Propriétés -->
        <aside class="builder-properties" id="propertiesPanel">
            <div class="properties-empty" id="propertiesEmpty">
                <div class="empty-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </div>
                <p>Sélectionnez une section<br>pour la modifier</p>
            </div>

            <div class="properties-content" id="propertiesContent" style="display: none;">
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

                <form id="sectionForm" class="properties-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="ajax_action" value="save">
                    <input type="hidden" name="section_id" id="sectionId" value="0">
                    <input type="hidden" name="status" id="sectionStatus" value="draft">

                    <!-- Type de section -->
                    <div class="prop-group">
                        <label>Type de contenu</label>
                        <select name="type" id="sectionType" class="section-type-select">
                            <?php foreach ($types as $typeKey => $typeLabel): ?>
                            <option value="<?= h($typeKey) ?>"><?= h($typeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Champs généraux -->
                    <div class="prop-group">
                        <label>Titre</label>
                        <input type="text" name="title" id="propTitle" placeholder="Titre de la section">
                    </div>

                    <div class="prop-group">
                        <label>Sous-titre</label>
                        <textarea name="subtitle" id="propSubtitle" rows="2" placeholder="Description"></textarea>
                    </div>

                    <!-- Typographie & Style du texte (juste après titre/sous-titre) -->
                    <div class="prop-section typography-fields">
                        <div class="prop-section-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="4 7 4 4 20 4 20 7"/>
                                <line x1="9" y1="20" x2="15" y2="20"/>
                                <line x1="12" y1="4" x2="12" y2="20"/>
                            </svg>
                            Style du texte
                        </div>

                        <!-- BLOC TITRE -->
                        <div class="prop-subsection">
                            <div class="prop-subsection-title">Titre</div>

                            <div class="prop-group-row">
                                <div class="prop-group">
                                    <label>Police</label>
                                    <select name="typo_font_family" id="propFontFamily">
                                        <option value="">Par défaut</option>
                                        <?php foreach ($fonts as $font): ?>
                                        <option value="<?= h($font['family']) ?>" style="font-family: '<?= h($font['family']) ?>'">
                                            <?= h($font['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="prop-group">
                                    <label>Taille</label>
                                    <select name="typo_title_size" id="propTitleSize">
                                        <option value="">Par défaut</option>
                                        <option value="small">Petit</option>
                                        <option value="medium">Moyen</option>
                                        <option value="large">Grand</option>
                                        <option value="xlarge">Très grand</option>
                                    </select>
                                </div>
                            </div>

                            <div class="prop-group">
                                <label>Couleur</label>
                                <div class="gradient-picker" id="titleColorPicker" data-field="typo_title_color">
                                    <div class="gradient-tabs">
                                        <button type="button" class="gradient-tab active" data-mode="solid">Unie</button>
                                        <button type="button" class="gradient-tab" data-mode="gradient">Dégradé</button>
                                    </div>
                                    <div class="gradient-content">
                                        <div class="gradient-solid active">
                                            <input type="color" class="color-solid" value="#1a1a1a">
                                        </div>
                                        <div class="gradient-options">
                                            <div class="gradient-colors">
                                                <div class="gradient-color-item">
                                                    <label>Début</label>
                                                    <input type="color" class="color-start" value="#ff69b4">
                                                </div>
                                                <div class="gradient-color-item">
                                                    <label>Fin</label>
                                                    <input type="color" class="color-end" value="#ff1493">
                                                </div>
                                            </div>
                                            <div class="gradient-angle">
                                                <label>Angle</label>
                                                <input type="range" class="angle-slider" min="0" max="360" value="135">
                                                <span class="angle-value">135°</span>
                                            </div>
                                            <div class="gradient-type">
                                                <select class="gradient-type-select">
                                                    <option value="linear">Linéaire</option>
                                                    <option value="radial">Radial</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="gradient-preview"></div>
                                    <input type="hidden" name="typo_title_color" id="propTitleColor" value="#1a1a1a">
                                </div>
                            </div>

                            <div class="prop-group">
                                <label>Style</label>
                                <div class="typo-toggles">
                                    <button type="button" class="typo-toggle" data-field="typo_bold" title="Gras">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/>
                                            <path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="typo-toggle" data-field="typo_italic" title="Italique">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="19" y1="4" x2="10" y2="4"/>
                                            <line x1="14" y1="20" x2="5" y2="20"/>
                                            <line x1="15" y1="4" x2="9" y2="20"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="typo-toggle" data-field="typo_underline" title="Souligné">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"/>
                                            <line x1="4" y1="21" x2="20" y2="21"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="typo-toggle" data-field="typo_uppercase" title="Majuscules">
                                        <span style="font-weight: 600; font-size: 12px;">AA</span>
                                    </button>
                                </div>
                                <input type="hidden" name="typo_bold" id="propTypoBold" value="0">
                                <input type="hidden" name="typo_italic" id="propTypoItalic" value="0">
                                <input type="hidden" name="typo_underline" id="propTypoUnderline" value="0">
                                <input type="hidden" name="typo_uppercase" id="propTypoUppercase" value="0">
                            </div>
                        </div>

                        <!-- BLOC SOUS-TITRE -->
                        <div class="prop-subsection">
                            <div class="prop-subsection-title">Sous-titre</div>

                            <div class="prop-group">
                                <label>Police</label>
                                <select name="typo_subtitle_font_family" id="propSubtitleFontFamily">
                                    <option value="">Hériter du titre</option>
                                    <?php foreach ($fonts as $font): ?>
                                    <option value="<?= h($font['family']) ?>" style="font-family: '<?= h($font['family']) ?>'">
                                        <?= h($font['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="prop-group">
                                <label>Couleur</label>
                                <div class="gradient-picker" id="subtitleColorPicker" data-field="typo_subtitle_color">
                                    <div class="gradient-tabs">
                                        <button type="button" class="gradient-tab active" data-mode="solid">Unie</button>
                                        <button type="button" class="gradient-tab" data-mode="gradient">Dégradé</button>
                                    </div>
                                    <div class="gradient-content">
                                        <div class="gradient-solid active">
                                            <input type="color" class="color-solid" value="#666666">
                                        </div>
                                        <div class="gradient-options">
                                            <div class="gradient-colors">
                                                <div class="gradient-color-item">
                                                    <label>Début</label>
                                                    <input type="color" class="color-start" value="#3dffc0">
                                                </div>
                                                <div class="gradient-color-item">
                                                    <label>Fin</label>
                                                    <input type="color" class="color-end" value="#00d9a0">
                                                </div>
                                            </div>
                                            <div class="gradient-angle">
                                                <label>Angle</label>
                                                <input type="range" class="angle-slider" min="0" max="360" value="135">
                                                <span class="angle-value">135°</span>
                                            </div>
                                            <div class="gradient-type">
                                                <select class="gradient-type-select">
                                                    <option value="linear">Linéaire</option>
                                                    <option value="radial">Radial</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="gradient-preview"></div>
                                    <input type="hidden" name="typo_subtitle_color" id="propSubtitleColor" value="#666666">
                                </div>
                            </div>

                            <div class="prop-group">
                                <label>Style</label>
                                <div class="typo-toggles">
                                    <button type="button" class="typo-toggle" data-field="typo_subtitle_bold" title="Gras">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/>
                                            <path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="typo-toggle" data-field="typo_subtitle_italic" title="Italique">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="19" y1="4" x2="10" y2="4"/>
                                            <line x1="14" y1="20" x2="5" y2="20"/>
                                            <line x1="15" y1="4" x2="9" y2="20"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="typo-toggle" data-field="typo_subtitle_underline" title="Souligné">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"/>
                                            <line x1="4" y1="21" x2="20" y2="21"/>
                                        </svg>
                                    </button>
                                </div>
                                <input type="hidden" name="typo_subtitle_bold" id="propTypoSubtitleBold" value="0">
                                <input type="hidden" name="typo_subtitle_italic" id="propTypoSubtitleItalic" value="0">
                                <input type="hidden" name="typo_subtitle_underline" id="propTypoSubtitleUnderline" value="0">
                            </div>
                        </div>

                        <!-- BLOC POSITIONNEMENT -->
                        <div class="prop-subsection">
                            <div class="prop-subsection-title">Positionnement</div>

                            <div class="prop-group">
                                <label>Alignement</label>
                                <div class="align-toggles">
                                    <button type="button" class="align-toggle" data-value="left" title="Gauche">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="3" y1="6" x2="21" y2="6"/>
                                            <line x1="3" y1="12" x2="15" y2="12"/>
                                            <line x1="3" y1="18" x2="18" y2="18"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="align-toggle active" data-value="center" title="Centre">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="3" y1="6" x2="21" y2="6"/>
                                            <line x1="6" y1="12" x2="18" y2="12"/>
                                            <line x1="4" y1="18" x2="20" y2="18"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="align-toggle" data-value="right" title="Droite">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="3" y1="6" x2="21" y2="6"/>
                                            <line x1="9" y1="12" x2="21" y2="12"/>
                                            <line x1="6" y1="18" x2="21" y2="18"/>
                                        </svg>
                                    </button>
                                </div>
                                <input type="hidden" name="typo_align" id="propTypoAlign" value="center">
                            </div>

                            <div class="prop-group-row">
                                <div class="prop-group">
                                    <label>Décalage vertical</label>
                                    <div class="offset-control">
                                        <input type="range" name="typo_offset_y" id="propOffsetY" min="-100" max="100" value="0">
                                        <span class="offset-value" id="offsetYValue">0px</span>
                                    </div>
                                </div>
                                <div class="prop-group">
                                    <label>Décalage horizontal</label>
                                    <div class="offset-control">
                                        <input type="range" name="typo_offset_x" id="propOffsetX" min="-100" max="100" value="0">
                                        <span class="offset-value" id="offsetXValue">0px</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Champs Hero -->
                    <div class="prop-section hero-fields" style="display: none;">
                        <div class="prop-group">
                            <label>Badge</label>
                            <input type="text" name="hero_badge" id="propHeroBadge" placeholder="Ex: Nouveau">
                        </div>
                        <div class="prop-group">
                            <label>Texte surligné</label>
                            <input type="text" name="hero_highlight" id="propHeroHighlight" placeholder="Texte coloré">
                        </div>
                    </div>

                    <!-- Champs CTA -->
                    <div class="prop-section cta-fields">
                        <div class="prop-group-row">
                            <div class="prop-group">
                                <label>Bouton</label>
                                <input type="text" name="cta_text" id="propCtaText" placeholder="Texte">
                            </div>
                            <div class="prop-group">
                                <label>Lien</label>
                                <input type="text" name="cta_url" id="propCtaUrl" placeholder="URL">
                            </div>
                        </div>
                    </div>

                    <!-- Hero CTA2 -->
                    <div class="prop-section hero-cta2-fields" style="display: none;">
                        <div class="prop-group-row">
                            <div class="prop-group">
                                <label>Bouton 2</label>
                                <input type="text" name="hero_cta2_text" id="propHeroCta2Text" placeholder="Texte">
                            </div>
                            <div class="prop-group">
                                <label>Lien 2</label>
                                <input type="text" name="hero_cta2_url" id="propHeroCta2Url" placeholder="URL">
                            </div>
                        </div>
                    </div>

                    <!-- Contenu (content_block) -->
                    <div class="prop-section content-fields" style="display: none;">
                        <div class="prop-group">
                            <label>Contenu</label>
                            <textarea name="content" id="propContent" rows="4" placeholder="Texte principal"></textarea>
                        </div>
                    </div>

                    <!-- Média -->
                    <div class="prop-section media-fields">
                        <div class="prop-group">
                            <label>Image / Vidéo</label>
                            <div class="media-upload-zone" id="mediaUploadZone">
                                <div class="media-preview" id="mediaPreview" style="display: none;">
                                    <img src="" alt="" id="mediaPreviewImg">
                                    <button type="button" class="media-remove" onclick="removeMedia()">×</button>
                                </div>
                                <div class="media-placeholder" id="mediaPlaceholder">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                    <span>Cliquez pour ajouter</span>
                                </div>
                                <input type="file" name="media_file" id="mediaFile" accept="image/*,video/*" style="display: none;">
                                <input type="hidden" name="clear_media" id="clearMedia" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- Sélection items -->
                    <div class="prop-section products-fields" style="display: none;">
                        <div class="prop-group">
                            <label>Produits</label>
                            <div class="items-select-grid">
                                <?php foreach ($products as $p): ?>
                                <label class="item-select-option">
                                    <input type="checkbox" name="product_ids[]" value="<?= $p['id'] ?>">
                                    <span class="item-select-thumb">
                                        <?php if (!empty($p['image_front_url'])): ?>
                                        <img src="/public<?= h($p['image_front_url']) ?>" alt="">
                                        <?php endif; ?>
                                    </span>
                                    <span class="item-select-name"><?= h($p['name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="prop-section packs-fields" style="display: none;">
                        <div class="prop-group">
                            <label>Packs</label>
                            <div class="items-select-grid">
                                <?php foreach ($packs as $p): ?>
                                <label class="item-select-option">
                                    <input type="checkbox" name="pack_ids[]" value="<?= $p['id'] ?>">
                                    <span class="item-select-thumb">
                                        <?php if (!empty($p['cover_image_url'])): ?>
                                        <img src="/public<?= h($p['cover_image_url']) ?>" alt="">
                                        <?php endif; ?>
                                    </span>
                                    <span class="item-select-name"><?= h($p['name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="prop-section blog-fields" style="display: none;">
                        <div class="prop-group">
                            <label>Articles</label>
                            <div class="items-select-grid">
                                <?php foreach ($blogPosts as $p): ?>
                                <label class="item-select-option">
                                    <input type="checkbox" name="blog_ids[]" value="<?= $p['id'] ?>">
                                    <span class="item-select-thumb">
                                        <?php if (!empty($p['cover_image_url'])): ?>
                                        <img src="/public<?= h($p['cover_image_url']) ?>" alt="">
                                        <?php endif; ?>
                                    </span>
                                    <span class="item-select-name"><?= h($p['title']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="prop-section category-fields" style="display: none;">
                        <div class="prop-group">
                            <label>Catégorie</label>
                            <select name="category_id" id="propCategoryId">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="prop-group">
                            <label>Nb produits max</label>
                            <input type="number" name="products_limit" id="propProductsLimit" value="8" min="1" max="20">
                        </div>
                    </div>

                    <!-- Style -->
                    <div class="prop-section style-fields">
                        <div class="prop-section-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="13.5" cy="6.5" r="2.5"/>
                                <circle cx="17.5" cy="10.5" r="2.5"/>
                                <circle cx="8.5" cy="7.5" r="2.5"/>
                                <circle cx="6.5" cy="12.5" r="2.5"/>
                                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12"/>
                            </svg>
                            Apparence
                        </div>

                        <div class="prop-group">
                            <label>Couleur de fond</label>
                            <div class="gradient-picker" id="bgColorPicker" data-field="style_bg_color">
                                <div class="gradient-tabs">
                                    <button type="button" class="gradient-tab active" data-mode="solid">Unie</button>
                                    <button type="button" class="gradient-tab" data-mode="gradient">Dégradé</button>
                                </div>
                                <div class="gradient-content">
                                    <div class="gradient-solid active">
                                        <input type="color" class="color-solid" value="#ffffff">
                                    </div>
                                    <div class="gradient-options">
                                        <div class="gradient-colors">
                                            <div class="gradient-color-item">
                                                <label>Début</label>
                                                <input type="color" class="color-start" value="#1a1a2e">
                                            </div>
                                            <div class="gradient-color-item">
                                                <label>Fin</label>
                                                <input type="color" class="color-end" value="#252542">
                                            </div>
                                        </div>
                                        <div class="gradient-angle">
                                            <label>Angle</label>
                                            <input type="range" class="angle-slider" min="0" max="360" value="180">
                                            <span class="angle-value">180°</span>
                                        </div>
                                        <div class="gradient-type">
                                            <select class="gradient-type-select">
                                                <option value="linear">Linéaire</option>
                                                <option value="radial">Radial</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="gradient-preview"></div>
                                <input type="hidden" name="style_bg_color" id="propBgColor" value="#ffffff">
                            </div>
                        </div>

                        <!-- Presets de dégradés populaires -->
                        <div class="prop-group">
                            <label>Dégradés prédéfinis</label>
                            <div class="gradient-presets">
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #ff69b4 0%, #ff1493 100%)" title="Pink">
                                    <span style="background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%)"></span>
                                </button>
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #3dffc0 0%, #00d9a0 100%)" title="Mint">
                                    <span style="background: linear-gradient(135deg, #3dffc0 0%, #00d9a0 100%)"></span>
                                </button>
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #667eea 0%, #764ba2 100%)" title="Purple">
                                    <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)"></span>
                                </button>
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)" title="Sunset">
                                    <span style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%)"></span>
                                </button>
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)" title="Ocean">
                                    <span style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"></span>
                                </button>
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)" title="Nature">
                                    <span style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)"></span>
                                </button>
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #fa709a 0%, #fee140 100%)" title="Warm">
                                    <span style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%)"></span>
                                </button>
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)" title="Pastel">
                                    <span style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)"></span>
                                </button>
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #0c0c0c 0%, #434343 100%)" title="Dark">
                                    <span style="background: linear-gradient(135deg, #0c0c0c 0%, #434343 100%)"></span>
                                </button>
                                <button type="button" class="gradient-preset" data-gradient="linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)" title="Light">
                                    <span style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="properties-footer">
                        <button type="submit" class="save-btn" id="saveBtn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </aside>
    </div>

    <!-- Modal ajout section -->
    <div class="add-modal" id="addModal">
        <div class="add-modal-backdrop" onclick="closeAddModal()"></div>
        <div class="add-modal-content">
            <h3>Ajouter une section</h3>
            <div class="add-section-grid">
                <?php foreach ($types as $typeKey => $typeLabel): ?>
                <button type="button" class="add-section-option" onclick="createSection('<?= h($typeKey) ?>')">
                    <div class="add-option-icon type-<?= h($typeKey) ?>">
                        <?= $typeIcons[$typeKey] ?? '' ?>
                    </div>
                    <span><?= h($typeLabel) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <button type="button" class="add-modal-close" onclick="closeAddModal()">Annuler</button>
        </div>
    </div>

    <style>
    :root {
        --builder-sidebar-width: 260px;
        --builder-properties-width: 320px;
    }

    .builder-body {
        margin: 0;
        padding: 0;
        overflow: hidden;
        background: #1a1a2e;
    }

    /* Layout principal */
    .builder-layout {
        display: flex;
        height: 100vh;
        width: 100vw;
    }

    /* Sidebar gauche */
    .builder-sidebar {
        width: var(--builder-sidebar-width);
        background: #252542;
        display: flex;
        flex-direction: column;
        border-right: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-back {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        color: #fff;
        text-decoration: none;
        transition: all 0.2s;
    }

    .sidebar-back:hover {
        background: var(--pink-main);
    }

    .sidebar-header h1 {
        font-size: 16px;
        font-weight: 600;
        color: #fff;
        margin: 0;
    }

    .sidebar-sections {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
    }

    .section-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        background: rgba(255,255,255,0.05);
        border-radius: 8px;
        margin-bottom: 6px;
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
    }

    .section-item:hover {
        background: rgba(255,255,255,0.1);
    }

    .section-item.active {
        background: rgba(255,105,180,0.2);
        border-color: var(--pink-main);
    }

    .section-item.is-draft {
        opacity: 0.6;
    }

    .section-item.dragging {
        opacity: 0.4;
        transform: scale(0.98);
    }

    .section-drag {
        color: rgba(255,255,255,0.3);
        cursor: grab;
    }

    .section-drag:active {
        cursor: grabbing;
    }

    .section-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        flex-shrink: 0;
    }

    .section-icon svg {
        width: 18px;
        height: 18px;
    }

    .section-icon.type-hero { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; }
    .section-icon.type-featured_products { background: #3dffc0; color: #1a1a2e; }
    .section-icon.type-featured_packs { background: #9b59b6; color: white; }
    .section-icon.type-featured_category { background: #e74c3c; color: white; }
    .section-icon.type-content_block { background: #3498db; color: white; }
    .section-icon.type-blog_slider { background: #e67e22; color: white; }
    .section-icon.type-newsletter { background: #1abc9c; color: white; }

    .section-info {
        flex: 1;
        min-width: 0;
    }

    .section-name {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .section-type-label {
        font-size: 11px;
        color: rgba(255,255,255,0.5);
    }

    .section-status {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .section-status.active {
        background: #2ecc71;
    }

    .section-status.draft {
        background: #95a5a6;
    }

    .add-section-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin: 12px;
        padding: 14px;
        background: linear-gradient(135deg, #ff69b4, #ff1493);
        border: none;
        border-radius: 10px;
        color: white;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .add-section-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(255,105,180,0.4);
    }

    .sidebar-footer {
        padding: 12px;
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
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        font-size: 13px;
        transition: all 0.2s;
    }

    .preview-site-btn:hover {
        background: rgba(255,255,255,0.15);
        color: #fff;
    }

    /* Preview central */
    .builder-preview {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #1a1a2e;
    }

    .preview-toolbar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        padding: 12px;
        background: #252542;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .device-toggle {
        display: flex;
        gap: 4px;
        background: rgba(0,0,0,0.3);
        padding: 4px;
        border-radius: 8px;
    }

    .device-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 36px;
        background: transparent;
        border: none;
        border-radius: 6px;
        color: rgba(255,255,255,0.5);
        cursor: pointer;
        transition: all 0.2s;
    }

    .device-btn:hover {
        color: rgba(255,255,255,0.8);
    }

    .device-btn.active {
        background: rgba(255,255,255,0.15);
        color: #fff;
    }

    .refresh-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 36px;
        background: rgba(255,255,255,0.1);
        border: none;
        border-radius: 8px;
        color: rgba(255,255,255,0.7);
        cursor: pointer;
        transition: all 0.2s;
    }

    .refresh-btn:hover {
        background: rgba(255,255,255,0.15);
        color: #fff;
    }

    .preview-container {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        overflow: auto;
    }

    .preview-frame-wrapper {
        position: relative;
        width: 100%;
        height: 100%;
        max-width: 1400px;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
    }

    .preview-frame-wrapper.tablet {
        width: 768px;
        max-width: 100%;
    }

    .preview-frame-wrapper.mobile {
        width: 375px;
        max-width: 100%;
    }

    #previewFrame {
        width: 100%;
        height: 100%;
        border: none;
        transition: opacity 0.15s ease;
    }

    .preview-frame-wrapper.refreshing #previewFrame {
        opacity: 0.4;
    }

    .preview-frame-wrapper.refreshing::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 32px;
        height: 32px;
        margin: -16px 0 0 -16px;
        border: 3px solid rgba(139, 92, 246, 0.2);
        border-top-color: var(--purple-main);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        z-index: 10;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .preview-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
    }

    /* Panneau propriétés */
    .builder-properties {
        width: var(--builder-properties-width);
        background: #fff;
        display: flex;
        flex-direction: column;
        border-left: 1px solid #e0e0e0;
    }

    .properties-empty {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        text-align: center;
    }

    .empty-icon {
        color: #ccc;
        margin-bottom: 16px;
    }

    .properties-empty p {
        color: #999;
        font-size: 14px;
        line-height: 1.5;
        margin: 0;
    }

    .properties-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .properties-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        border-bottom: 1px solid #eee;
    }

    .properties-header h2 {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
    }

    .properties-actions {
        display: flex;
        gap: 6px;
    }

    .prop-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        background: #f5f5f5;
        border: none;
        border-radius: 8px;
        color: #666;
        cursor: pointer;
        transition: all 0.2s;
    }

    .prop-action-btn:hover {
        background: #eee;
        color: #333;
    }

    .prop-action-btn.danger:hover {
        background: #fee;
        color: #e74c3c;
    }

    .properties-form {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
    }

    .section-type-select {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #eee;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 500;
        background: linear-gradient(135deg, rgba(255,105,180,0.05), rgba(255,20,147,0.02));
        color: #333;
        cursor: pointer;
        transition: all 0.2s;
    }

    .section-type-select:hover {
        border-color: var(--pink-main, #ff69b4);
    }

    .section-type-select:focus {
        outline: none;
        border-color: var(--pink-main, #ff69b4);
        box-shadow: 0 0 0 3px rgba(255,105,180,0.1);
    }

    .prop-group {
        margin-bottom: 16px;
    }

    .prop-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #666;
        margin-bottom: 6px;
    }

    .prop-group input[type="text"],
    .prop-group input[type="number"],
    .prop-group textarea,
    .prop-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s;
    }

    .prop-group input:focus,
    .prop-group textarea:focus,
    .prop-group select:focus {
        outline: none;
        border-color: var(--pink-main);
    }

    .prop-group input[type="color"] {
        width: 100%;
        height: 40px;
        padding: 4px;
        border: 1px solid #ddd;
        border-radius: 8px;
        cursor: pointer;
    }

    /* Gradient Picker */
    .gradient-picker {
        border: 1px solid #ddd;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
    }

    .gradient-tabs {
        display: flex;
        border-bottom: 1px solid #eee;
    }

    .gradient-tab {
        flex: 1;
        padding: 10px;
        border: none;
        background: #f8f8f8;
        font-size: 12px;
        font-weight: 600;
        color: #666;
        cursor: pointer;
        transition: all 0.2s;
    }

    .gradient-tab:first-child {
        border-radius: 9px 0 0 0;
    }

    .gradient-tab:last-child {
        border-radius: 0 9px 0 0;
    }

    .gradient-tab.active {
        background: #fff;
        color: var(--pink-main, #ff69b4);
    }

    .gradient-tab:hover:not(.active) {
        background: #f0f0f0;
    }

    .gradient-content {
        padding: 12px;
    }

    .gradient-solid {
        display: none;
    }

    .gradient-solid.active {
        display: block;
    }

    .gradient-solid input[type="color"] {
        height: 45px;
        border-radius: 8px;
    }

    .gradient-options {
        display: none;
    }

    .gradient-options.active {
        display: block;
    }

    .gradient-colors {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 12px;
    }

    .gradient-color-item label {
        font-size: 11px;
        color: #888;
        margin-bottom: 4px;
        display: block;
    }

    .gradient-color-item input[type="color"] {
        height: 36px;
    }

    .gradient-angle {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .gradient-angle label {
        font-size: 11px;
        color: #888;
        min-width: 40px;
        margin: 0 !important;
    }

    .gradient-angle .angle-slider {
        flex: 1;
        height: 6px;
        -webkit-appearance: none;
        background: linear-gradient(to right, #ff69b4, #3dffc0, #667eea, #ff69b4);
        border-radius: 3px;
        outline: none;
    }

    .gradient-angle .angle-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 16px;
        height: 16px;
        background: #fff;
        border: 2px solid var(--pink-main, #ff69b4);
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .gradient-angle .angle-value {
        font-size: 12px;
        font-weight: 600;
        color: #666;
        min-width: 35px;
        text-align: right;
    }

    .gradient-type {
        margin-bottom: 10px;
    }

    .gradient-type-select {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 12px;
        background: #f8f8f8;
    }

    .gradient-preview {
        height: 30px;
        border-radius: 6px;
        margin-top: 8px;
        border: 1px solid #ddd;
        background: #f0f0f0;
    }

    /* Gradient Presets */
    .gradient-presets {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 8px;
    }

    .gradient-preset {
        width: 100%;
        aspect-ratio: 1;
        border: 2px solid transparent;
        border-radius: 8px;
        padding: 3px;
        background: #fff;
        cursor: pointer;
        transition: all 0.2s;
    }

    .gradient-preset:hover {
        transform: scale(1.1);
        border-color: var(--pink-main, #ff69b4);
    }

    .gradient-preset.active {
        border-color: var(--pink-main, #ff69b4);
        box-shadow: 0 0 0 2px rgba(255, 105, 180, 0.3);
    }

    .gradient-preset span {
        display: block;
        width: 100%;
        height: 100%;
        border-radius: 5px;
    }

    /* Font select avec preview */
    #propFontFamily option {
        padding: 8px;
        font-size: 14px;
    }

    .prop-group-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .prop-section {
        padding-top: 16px;
        border-top: 1px solid #eee;
        margin-top: 16px;
    }

    .prop-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 600;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 16px;
    }

    .prop-section-title svg {
        color: var(--pink-main, #ff69b4);
    }

    /* Sous-sections (Titre, Sous-titre, Positionnement) */
    .prop-subsection {
        background: #fafafa;
        border-radius: 10px;
        padding: 14px;
        margin-bottom: 12px;
    }

    .prop-subsection-title {
        font-size: 11px;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid #eee;
    }

    /* Typography toggles */
    .typo-toggles {
        display: flex;
        gap: 6px;
    }

    .typo-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: #f5f5f5;
        border: 2px solid transparent;
        border-radius: 8px;
        color: #666;
        cursor: pointer;
        transition: all 0.2s;
    }

    .typo-toggle:hover {
        background: #eee;
        color: #333;
    }

    .typo-toggle.active {
        background: linear-gradient(135deg, rgba(255,105,180,0.15), rgba(255,20,147,0.1));
        border-color: var(--pink-main, #ff69b4);
        color: var(--pink-main, #ff69b4);
    }

    /* Alignment toggles */
    .align-toggles {
        display: flex;
        gap: 6px;
    }

    .align-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 1;
        height: 40px;
        background: #f5f5f5;
        border: 2px solid transparent;
        border-radius: 8px;
        color: #666;
        cursor: pointer;
        transition: all 0.2s;
    }

    .align-toggle:hover {
        background: #eee;
        color: #333;
    }

    .align-toggle.active {
        background: linear-gradient(135deg, rgba(255,105,180,0.15), rgba(255,20,147,0.1));
        border-color: var(--pink-main, #ff69b4);
        color: var(--pink-main, #ff69b4);
    }

    /* Offset controls */
    .offset-control {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .offset-control input[type="range"] {
        flex: 1;
        height: 6px;
        -webkit-appearance: none;
        background: #e0e0e0;
        border-radius: 3px;
        outline: none;
    }

    .offset-control input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 18px;
        height: 18px;
        background: var(--pink-main, #ff69b4);
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(255,105,180,0.3);
    }

    .offset-value {
        font-size: 12px;
        font-weight: 600;
        color: #666;
        min-width: 45px;
        text-align: right;
    }

    /* Media upload */
    .media-upload-zone {
        border: 2px dashed #ddd;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .media-upload-zone:hover {
        border-color: var(--pink-main);
        background: rgba(255,105,180,0.05);
    }

    .media-placeholder {
        color: #999;
    }

    .media-placeholder svg {
        margin-bottom: 8px;
    }

    .media-placeholder span {
        font-size: 13px;
    }

    .media-preview {
        position: relative;
    }

    .media-preview img {
        max-width: 100%;
        max-height: 150px;
        border-radius: 8px;
    }

    .media-remove {
        position: absolute;
        top: -8px;
        right: -8px;
        width: 24px;
        height: 24px;
        background: #e74c3c;
        border: none;
        border-radius: 50%;
        color: white;
        font-size: 16px;
        cursor: pointer;
    }

    /* Items selection */
    .items-select-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        max-height: 200px;
        overflow-y: auto;
        padding: 8px;
        background: #f9f9f9;
        border-radius: 8px;
    }

    .item-select-option {
        cursor: pointer;
    }

    .item-select-option input {
        display: none;
    }

    .item-select-option .item-select-thumb {
        display: block;
        width: 100%;
        aspect-ratio: 1;
        background: #eee;
        border-radius: 6px;
        overflow: hidden;
        border: 2px solid transparent;
        transition: all 0.2s;
    }

    .item-select-option .item-select-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .item-select-option input:checked + .item-select-thumb {
        border-color: var(--pink-main);
    }

    .item-select-name {
        display: block;
        font-size: 10px;
        text-align: center;
        margin-top: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Footer */
    .properties-footer {
        padding: 16px;
        border-top: 1px solid #eee;
    }

    .save-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #ff69b4, #ff1493);
        border: none;
        border-radius: 10px;
        color: white;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .save-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(255,105,180,0.4);
    }

    .save-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* Modal ajout */
    .add-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .add-modal.active {
        display: flex;
    }

    .add-modal-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.6);
    }

    .add-modal-content {
        position: relative;
        background: white;
        border-radius: 16px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        animation: modalIn 0.3s ease;
    }

    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }

    .add-modal-content h3 {
        margin: 0 0 20px;
        font-size: 18px;
        text-align: center;
    }

    .add-section-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }

    .add-section-option {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: 16px 12px;
        background: #f5f5f5;
        border: 2px solid transparent;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .add-section-option:hover {
        background: #fff;
        border-color: var(--pink-main);
        transform: translateY(-2px);
    }

    .add-option-icon {
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }

    .add-option-icon svg {
        width: 24px;
        height: 24px;
    }

    .add-section-option span {
        font-size: 12px;
        font-weight: 500;
        text-align: center;
    }

    .add-modal-close {
        display: block;
        width: 100%;
        padding: 12px;
        background: #f0f0f0;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .add-modal-close:hover {
        background: #e0e0e0;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .builder-properties {
            position: fixed;
            right: -320px;
            top: 0;
            height: 100vh;
            z-index: 100;
            transition: right 0.3s ease;
            box-shadow: -4px 0 20px rgba(0,0,0,0.15);
        }

        .builder-properties.active {
            right: 0;
        }
    }

    @media (max-width: 768px) {
        .builder-sidebar {
            width: 60px;
        }

        .sidebar-header h1,
        .section-info,
        .add-section-btn span,
        .preview-site-btn span {
            display: none;
        }

        .add-section-btn {
            padding: 12px;
        }
    }
    </style>

    <script>
    const csrf = '<?= $csrf ?>';
    const pageId = <?= $pageId ?>;
    const pageSlug = '<?= h($page['slug']) ?>';
    const types = <?= json_encode($types) ?>;
    let selectedSectionId = null;

    document.addEventListener('DOMContentLoaded', function() {
        initSectionsList();
        initDragAndDrop();
        initDeviceToggle();
        initMediaUpload();
        initForm();
        initTypographyToggles();
        initAlignmentToggles();
        initOffsetControls();
        initAutoSave();
        initGradientPickers();
        initGradientPresets();
    });

    // Liste des sections
    function initSectionsList() {
        document.querySelectorAll('.section-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.closest('.section-drag')) return;
                selectSection(this.dataset.id);
            });
        });
    }

    function selectSection(id) {
        // Highlight dans la sidebar
        document.querySelectorAll('.section-item').forEach(item => {
            item.classList.toggle('active', item.dataset.id == id);
        });

        selectedSectionId = id;

        // Charger les propriétés
        fetch('/admin/page-editor.php?id=' + pageId, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax_action=get_section&section_id=${id}&csrf_token=${csrf}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showProperties(data.section);
            }
        });

        // Highlight dans le preview (scroll to section)
        highlightInPreview(id);
    }

    // Variable pour tracker l'interval de highlight (éviter accumulation)
    let currentHighlightInterval = null;

    function highlightInPreview(id) {
        try {
            // Nettoyer l'interval précédent s'il existe
            if (currentHighlightInterval) {
                clearInterval(currentHighlightInterval);
                currentHighlightInterval = null;
            }

            const frame = document.getElementById('previewFrame');
            const doc = frame.contentDocument || frame.contentWindow.document;
            const section = doc.querySelector(`[data-section-id="${id}"]`);

            if (section) {
                // Retirer le highlight précédent
                doc.querySelectorAll('[data-section-id]').forEach(el => {
                    el.style.outline = '';
                    el.style.outlineOffset = '';
                    el.classList.remove('builder-highlight');
                });

                // Scroll vers la section avec un offset pour la navbar
                const rect = section.getBoundingClientRect();
                const scrollTop = frame.contentWindow.scrollY || doc.documentElement.scrollTop;
                const targetY = rect.top + scrollTop - 100; // 100px offset pour la navbar

                frame.contentWindow.scrollTo({
                    top: targetY,
                    behavior: 'smooth'
                });

                // Ajouter un effet de highlight animé
                section.style.outline = '3px solid #ff69b4';
                section.style.outlineOffset = '4px';
                section.style.transition = 'outline-color 0.3s, outline-offset 0.3s';

                // Animation pulse
                let pulseCount = 0;
                currentHighlightInterval = setInterval(() => {
                    pulseCount++;
                    if (pulseCount >= 4) {
                        clearInterval(currentHighlightInterval);
                        currentHighlightInterval = null;
                        section.style.outline = '2px dashed rgba(255, 105, 180, 0.5)';
                        section.style.outlineOffset = '2px';
                    } else {
                        section.style.outlineOffset = pulseCount % 2 === 0 ? '4px' : '8px';
                    }
                }, 300);
            }
        } catch(e) {
            console.log('Preview highlight error:', e);
        }
    }

    function showProperties(section) {
        document.getElementById('propertiesEmpty').style.display = 'none';
        document.getElementById('propertiesContent').style.display = 'flex';
        document.getElementById('propertiesTitle').textContent = section.title || types[section.type];

        // Remplir le formulaire
        document.getElementById('sectionId').value = section.id;
        document.getElementById('sectionType').value = section.type;
        document.getElementById('sectionStatus').value = section.status;

        // Champs généraux
        document.getElementById('propTitle').value = section.title || '';
        document.getElementById('propSubtitle').value = section.subtitle || '';
        document.getElementById('propCtaText').value = section.cta_text || '';
        document.getElementById('propCtaUrl').value = section.cta_url || '';

        // Media
        // Reset clear_media flag
        document.getElementById('clearMedia').value = '0';

        if (section.media_url) {
            document.getElementById('mediaPreview').style.display = 'block';
            document.getElementById('mediaPlaceholder').style.display = 'none';
            document.getElementById('mediaPreviewImg').src = '/public' + section.media_url;
        } else {
            document.getElementById('mediaPreview').style.display = 'none';
            document.getElementById('mediaPlaceholder').style.display = 'block';
        }

        // Afficher/masquer les champs selon le type
        showFieldsForType(section.type);

        // Champs spécifiques
        if (section.type === 'hero' && section.config) {
            document.getElementById('propHeroBadge').value = section.config.badge || '';
            document.getElementById('propHeroHighlight').value = section.config.highlight || '';
            document.getElementById('propHeroCta2Text').value = section.config.cta2_text || '';
            document.getElementById('propHeroCta2Url').value = section.config.cta2_url || '';
        }

        if (section.type === 'content_block') {
            document.getElementById('propContent').value = section.content || '';
        }

        if (section.type === 'featured_category' && section.config) {
            document.getElementById('propCategoryId').value = section.config.category_id || '';
            document.getElementById('propProductsLimit').value = section.config.products_limit || 8;
        }

        // Style - utiliser setGradientPickerValue pour mettre à jour le picker correctement
        const style = section.config?.style || {};
        const bgColorPicker = document.getElementById('bgColorPicker');
        if (bgColorPicker) {
            setGradientPickerValue(bgColorPicker, style.background_color || '#ffffff');
        }

        // Padding (si l'élément existe)
        const paddingEl = document.getElementById('propPaddingY');
        if (paddingEl) {
            paddingEl.value = style.padding_y || 'medium';
        }

        // Typography
        setTypographyValues(section.config);

        // Items sélectionnés
        document.querySelectorAll('.items-select-grid input').forEach(cb => cb.checked = false);
        if (section.items) {
            section.items.forEach(item => {
                let cb;
                if (item.item_type === 'product') {
                    cb = document.querySelector(`input[name="product_ids[]"][value="${item.item_id}"]`);
                } else if (item.item_type === 'pack') {
                    cb = document.querySelector(`input[name="pack_ids[]"][value="${item.item_id}"]`);
                } else if (item.item_type === 'blog') {
                    cb = document.querySelector(`input[name="blog_ids[]"][value="${item.item_id}"]`);
                }
                if (cb) cb.checked = true;
            });
        }

        // Activer panneau sur mobile
        document.getElementById('propertiesPanel').classList.add('active');
    }

    function showFieldsForType(type) {
        // Cacher tous les champs conditionnels
        document.querySelectorAll('.hero-fields, .hero-cta2-fields, .content-fields, .products-fields, .packs-fields, .blog-fields, .category-fields').forEach(el => {
            el.style.display = 'none';
        });

        // Afficher selon le type
        switch(type) {
            case 'hero':
                document.querySelector('.hero-fields').style.display = 'block';
                document.querySelector('.hero-cta2-fields').style.display = 'block';
                break;
            case 'content_block':
                document.querySelector('.content-fields').style.display = 'block';
                break;
            case 'featured_products':
                document.querySelector('.products-fields').style.display = 'block';
                break;
            case 'featured_packs':
                document.querySelector('.packs-fields').style.display = 'block';
                break;
            case 'blog_slider':
                document.querySelector('.blog-fields').style.display = 'block';
                break;
            case 'featured_category':
                document.querySelector('.category-fields').style.display = 'block';
                break;
        }
    }

    function getTypeColor(type) {
        const colors = {
            hero: 'linear-gradient(135deg, #ff69b4, #ff1493)',
            featured_products: '#3dffc0',
            featured_packs: '#9b59b6',
            featured_category: '#e74c3c',
            content_block: '#3498db',
            blog_slider: '#e67e22',
            newsletter: '#1abc9c'
        };
        return colors[type] || '#666';
    }

    // Drag & Drop
    function initDragAndDrop() {
        const container = document.getElementById('sectionsList');
        let draggedItem = null;

        container.querySelectorAll('.section-item').forEach(item => {
            item.addEventListener('dragstart', function(e) {
                draggedItem = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            item.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                draggedItem = null;
                saveOrder();
            });

            item.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (!draggedItem || draggedItem === this) return;

                const rect = this.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;

                if (e.clientY < midpoint) {
                    container.insertBefore(draggedItem, this);
                } else {
                    container.insertBefore(draggedItem, this.nextSibling);
                }
            });
        });
    }

    function saveOrder() {
        const items = document.querySelectorAll('.section-item');
        const ids = [...items].map(item => item.dataset.id);

        fetch('/admin/page-editor.php?id=' + pageId, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax_action=reorder&order=${encodeURIComponent(JSON.stringify(ids))}&csrf_token=${csrf}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                refreshPreview();
            }
        });
    }

    // Device toggle
    function initDeviceToggle() {
        document.querySelectorAll('.device-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const device = this.dataset.device;
                const wrapper = document.getElementById('previewWrapper');
                wrapper.classList.remove('tablet', 'mobile');
                if (device !== 'desktop') {
                    wrapper.classList.add(device);
                }
            });
        });
    }

    // Anti-flood protection pour le refresh preview
    let isRefreshing = false;
    let refreshQueued = false;
    let lastRefreshTime = 0;
    const MIN_REFRESH_INTERVAL = 500; // Minimum 500ms entre les refreshs

    function refreshPreview() {
        const now = Date.now();

        // Si un refresh est en cours, on marque qu'un refresh est demandé pour plus tard
        if (isRefreshing) {
            refreshQueued = true;
            return;
        }

        // Vérifier l'intervalle minimum entre les refreshs
        if (now - lastRefreshTime < MIN_REFRESH_INTERVAL) {
            // Programmer un refresh après l'intervalle
            if (!refreshQueued) {
                refreshQueued = true;
                setTimeout(() => {
                    refreshQueued = false;
                    refreshPreview();
                }, MIN_REFRESH_INTERVAL - (now - lastRefreshTime));
            }
            return;
        }

        isRefreshing = true;
        lastRefreshTime = now;

        const frame = document.getElementById('previewFrame');
        const wrapper = document.getElementById('previewWrapper');

        // Stocker la position de scroll actuelle
        let scrollY = 0;
        try {
            scrollY = frame.contentWindow.scrollY || 0;
        } catch(e) {}

        // Ajouter classe de transition (fade out)
        wrapper.classList.add('refreshing');

        // Après un court délai, recharger l'iframe
        setTimeout(() => {
            const newSrc = frame.src.split('?')[0] + '?preview=builder&t=' + Date.now();
            frame.src = newSrc;

            // Quand l'iframe est chargée, restaurer le scroll et fade in
            frame.onload = function() {
                try {
                    frame.contentWindow.scrollTo(0, scrollY);
                } catch(e) {}

                // Petit délai pour laisser le rendu se stabiliser
                setTimeout(() => {
                    wrapper.classList.remove('refreshing');
                    isRefreshing = false;

                    // Si un refresh était en attente, le lancer
                    if (refreshQueued) {
                        refreshQueued = false;
                        refreshPreview();
                    }
                }, 50);
            };
        }, 150);
    }

    // Media upload
    function initMediaUpload() {
        const zone = document.getElementById('mediaUploadZone');
        const input = document.getElementById('mediaFile');

        zone.addEventListener('click', () => input.click());

        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('mediaPreview').style.display = 'block';
                    document.getElementById('mediaPlaceholder').style.display = 'none';
                    document.getElementById('mediaPreviewImg').src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    function removeMedia() {
        document.getElementById('mediaPreview').style.display = 'none';
        document.getElementById('mediaPlaceholder').style.display = 'block';
        document.getElementById('mediaFile').value = '';
        document.getElementById('clearMedia').value = '1';
        // Auto-save pour appliquer la suppression
        if (selectedSectionId) {
            debouncedAutoSave();
        }
    }

    // Form
    function initForm() {
        document.getElementById('sectionForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const saveBtn = document.getElementById('saveBtn');

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<svg class="spin" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/></svg> Enregistrement...';

            fetch('/admin/page-editor.php?id=' + pageId, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Afficher un avertissement upload si présent
                    if (data.uploadWarning) {
                        console.warn('Upload warning:', data.uploadWarning);
                        alert('Attention: ' + data.uploadWarning);
                    }
                    refreshPreview();
                    if (data.isNew) {
                        location.reload();
                    } else {
                        // Mettre à jour le nom dans la sidebar
                        const item = document.querySelector(`.section-item[data-id="${selectedSectionId}"]`);
                        if (item) {
                            const name = document.getElementById('propTitle').value || types[document.getElementById('sectionType').value];
                            item.querySelector('.section-name').textContent = name;
                        }
                        // Recharger la section pour voir le nouveau média
                        selectSection(selectedSectionId);
                    }
                } else {
                    alert(data.error || 'Erreur');
                }
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Enregistrer';
            });
        });

        // Toggle status
        let isToggling = false;
        document.getElementById('toggleStatusBtn').addEventListener('click', function() {
            if (!selectedSectionId || isToggling) return;

            const saveBtn = document.getElementById('saveBtn');
            const toggleBtn = this;

            // Désactiver les boutons pendant le toggle
            isToggling = true;
            saveBtn.disabled = true;
            toggleBtn.disabled = true;

            fetch('/admin/page-editor.php?id=' + pageId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=toggle&section_id=${selectedSectionId}&csrf_token=${csrf}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`.section-item[data-id="${selectedSectionId}"]`);
                    if (item) {
                        const statusDot = item.querySelector('.section-status');
                        statusDot.className = 'section-status ' + data.status;
                        item.classList.toggle('is-draft', data.status === 'draft');
                    }
                    // Mettre à jour le champ caché du formulaire pour que Save ne l'écrase pas
                    document.getElementById('sectionStatus').value = data.status;
                    refreshPreview();
                }
            })
            .finally(() => {
                isToggling = false;
                saveBtn.disabled = false;
                toggleBtn.disabled = false;
            });
        });

        // Delete
        document.getElementById('deleteBtn').addEventListener('click', function() {
            if (!selectedSectionId) return;
            if (!confirm('Supprimer cette section ?')) return;

            fetch('/admin/page-editor.php?id=' + pageId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=delete&section_id=${selectedSectionId}&csrf_token=${csrf}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`.section-item[data-id="${selectedSectionId}"]`);
                    if (item) item.remove();

                    selectedSectionId = null;
                    document.getElementById('propertiesEmpty').style.display = 'flex';
                    document.getElementById('propertiesContent').style.display = 'none';

                    refreshPreview();
                }
            });
        });
    }

    // Modal ajout
    document.getElementById('addSectionBtn').addEventListener('click', () => {
        document.getElementById('addModal').classList.add('active');
    });

    function closeAddModal() {
        document.getElementById('addModal').classList.remove('active');
    }

    function createSection(type) {
        closeAddModal();

        // Créer une section vide
        const formData = new FormData();
        formData.append('ajax_action', 'save');
        formData.append('csrf_token', csrf);
        formData.append('section_id', '0');
        formData.append('type', type);
        formData.append('title', '');
        formData.append('status', 'draft');

        fetch('/admin/page-editor.php?id=' + pageId, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // Typography toggles
    function initTypographyToggles() {
        document.querySelectorAll('.typo-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                const field = this.dataset.field;
                const input = document.getElementById('prop' + field.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(''));

                this.classList.toggle('active');
                input.value = this.classList.contains('active') ? '1' : '0';

                // Auto-save après changement de style
                if (selectedSectionId) autoSaveSection();
            });
        });
    }

    function setTypographyValues(config) {
        const typo = config?.typography || {};

        // Font family titre
        document.getElementById('propFontFamily').value = typo.font_family || '';

        // Font family sous-titre
        document.getElementById('propSubtitleFontFamily').value = typo.subtitle_font_family || '';

        // Title size
        document.getElementById('propTitleSize').value = typo.title_size || '';

        // Title color - utiliser setGradientPickerValue
        const titleColorPicker = document.getElementById('titleColorPicker');
        if (titleColorPicker) {
            setGradientPickerValue(titleColorPicker, typo.title_color || '#1a1a1a');
        }

        // Subtitle color - utiliser setGradientPickerValue
        const subtitleColorPicker = document.getElementById('subtitleColorPicker');
        if (subtitleColorPicker) {
            setGradientPickerValue(subtitleColorPicker, typo.subtitle_color || '#666666');
        }

        // Bold, Italic, Underline, Uppercase toggles (titre)
        const toggleFields = ['bold', 'italic', 'underline', 'uppercase'];
        toggleFields.forEach(field => {
            const btn = document.querySelector(`.typo-toggle[data-field="typo_${field}"]`);
            const input = document.getElementById('propTypo' + field.charAt(0).toUpperCase() + field.slice(1));
            const value = typo[field] === '1' || typo[field] === 1 || typo[field] === true;

            if (btn) btn.classList.toggle('active', value);
            if (input) input.value = value ? '1' : '0';
        });

        // Bold, Italic, Underline toggles (sous-titre)
        const subtitleToggleFields = ['subtitle_bold', 'subtitle_italic', 'subtitle_underline'];
        subtitleToggleFields.forEach(field => {
            const btn = document.querySelector(`.typo-toggle[data-field="typo_${field}"]`);
            const inputId = 'propTypo' + field.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('');
            const input = document.getElementById(inputId);
            const value = typo[field] === '1' || typo[field] === 1 || typo[field] === true;

            if (btn) btn.classList.toggle('active', value);
            if (input) input.value = value ? '1' : '0';
        });

        // Alignment
        const align = typo.align || 'center';
        document.querySelectorAll('.align-toggle').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.value === align);
        });
        document.getElementById('propTypoAlign').value = align;

        // Offsets
        const offsetY = parseInt(typo.offset_y) || 0;
        const offsetX = parseInt(typo.offset_x) || 0;
        document.getElementById('propOffsetY').value = offsetY;
        document.getElementById('propOffsetX').value = offsetX;
        document.getElementById('offsetYValue').textContent = offsetY + 'px';
        document.getElementById('offsetXValue').textContent = offsetX + 'px';
    }

    // Alignment toggles
    function initAlignmentToggles() {
        document.querySelectorAll('.align-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.align-toggle').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('propTypoAlign').value = this.dataset.value;

                // Auto-save après changement d'alignement
                if (selectedSectionId) autoSaveSection();
            });
        });
    }

    // Offset controls
    function initOffsetControls() {
        const offsetY = document.getElementById('propOffsetY');
        const offsetX = document.getElementById('propOffsetX');

        if (offsetY) {
            offsetY.addEventListener('input', function() {
                document.getElementById('offsetYValue').textContent = this.value + 'px';
                debouncedAutoSave();
            });
        }

        if (offsetX) {
            offsetX.addEventListener('input', function() {
                document.getElementById('offsetXValue').textContent = this.value + 'px';
                debouncedAutoSave();
            });
        }
    }

    // Auto-save avec debounce pour rafraîchir la preview automatiquement
    let autoSaveTimeout = null;
    function debouncedAutoSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(() => {
            if (selectedSectionId) {
                autoSaveSection();
            }
        }, 800); // 800ms de délai
    }

    function autoSaveSection() {
        const form = document.getElementById('sectionForm');
        const formData = new FormData(form);

        // Indicateur visuel de sauvegarde
        const saveBtn = document.getElementById('saveBtn');
        const originalHtml = saveBtn.innerHTML;
        saveBtn.innerHTML = '<svg class="spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';

        fetch('/admin/page-editor.php?id=' + pageId, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                refreshPreview();
            }
        })
        .finally(() => {
            saveBtn.innerHTML = originalHtml;
        });
    }

    // Initialiser l'auto-save sur les champs de saisie
    function initAutoSave() {
        // Champs texte avec debounce
        const textFields = ['propTitle', 'propSubtitle', 'propContent', 'propCtaText', 'propCtaUrl',
                           'propHeroBadge', 'propHeroHighlight', 'propHeroCta2Text', 'propHeroCta2Url'];
        textFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', debouncedAutoSave);
            }
        });

        // Sélecteurs avec sauvegarde immédiate
        const selectFields = ['propFontFamily', 'propSubtitleFontFamily', 'propTitleSize', 'propCategoryId', 'propProductsLimit'];
        selectFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', () => {
                    if (selectedSectionId) autoSaveSection();
                });
            }
        });

        // Type de section - change les champs affichés + auto-save
        const typeSelect = document.getElementById('sectionType');
        if (typeSelect) {
            typeSelect.addEventListener('change', function() {
                showFieldsForType(this.value);
                if (selectedSectionId) autoSaveSection();
            });
        }

        // Couleurs avec debounce
        const colorFields = ['propTitleColor', 'propSubtitleColor', 'propBgColor', 'propTextColor'];
        colorFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', debouncedAutoSave);
            }
        });

        // Checkboxes items (produits, packs, blog) - auto-save quand sélection change
        document.querySelectorAll('.items-select-grid input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => {
                if (selectedSectionId) autoSaveSection();
            });
        });
    }

    // Keyboard
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddModal();
        }
    });

    // ===== GRADIENT PICKERS =====
    function initGradientPickers() {
        document.querySelectorAll('.gradient-picker').forEach(picker => {
            const tabs = picker.querySelectorAll('.gradient-tab');
            const solidSection = picker.querySelector('.gradient-solid');
            const gradientSection = picker.querySelector('.gradient-options');
            const preview = picker.querySelector('.gradient-preview');
            const hiddenInput = picker.querySelector('input[type="hidden"]');

            // Tab switching
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const mode = this.dataset.mode;
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    if (mode === 'solid') {
                        solidSection.classList.add('active');
                        gradientSection.classList.remove('active');
                    } else {
                        solidSection.classList.remove('active');
                        gradientSection.classList.add('active');
                    }
                    updateGradientValue(picker);
                });
            });

            // Color inputs change
            const colorSolid = picker.querySelector('.color-solid');
            const colorStart = picker.querySelector('.color-start');
            const colorEnd = picker.querySelector('.color-end');
            const angleSlider = picker.querySelector('.angle-slider');
            const angleValue = picker.querySelector('.angle-value');
            const typeSelect = picker.querySelector('.gradient-type-select');

            if (colorSolid) {
                colorSolid.addEventListener('input', () => updateGradientValue(picker));
            }
            if (colorStart) {
                colorStart.addEventListener('input', () => updateGradientValue(picker));
            }
            if (colorEnd) {
                colorEnd.addEventListener('input', () => updateGradientValue(picker));
            }
            if (angleSlider) {
                angleSlider.addEventListener('input', function() {
                    angleValue.textContent = this.value + '°';
                    updateGradientValue(picker);
                });
            }
            if (typeSelect) {
                typeSelect.addEventListener('change', () => updateGradientValue(picker));
            }

            // Initial preview - use hidden input value if available
            const initialValue = picker.querySelector('input[type="hidden"]').value;
            if (initialValue) {
                setGradientPickerValue(picker, initialValue);
            } else {
                updateGradientValue(picker);
            }
        });
    }

    function updateGradientValue(picker) {
        const gradientTab = picker.querySelector('.gradient-tab[data-mode="gradient"]');
        if (!gradientTab) return;

        const isGradient = gradientTab.classList.contains('active');
        const preview = picker.querySelector('.gradient-preview');
        const hiddenInput = picker.querySelector('input[type="hidden"]');
        if (!preview || !hiddenInput) return;

        let value;

        if (isGradient) {
            const colorStart = picker.querySelector('.color-start').value;
            const colorEnd = picker.querySelector('.color-end').value;
            const angle = picker.querySelector('.angle-slider').value;
            const type = picker.querySelector('.gradient-type-select').value;

            if (type === 'radial') {
                value = `radial-gradient(circle, ${colorStart} 0%, ${colorEnd} 100%)`;
            } else {
                value = `linear-gradient(${angle}deg, ${colorStart} 0%, ${colorEnd} 100%)`;
            }
        } else {
            value = picker.querySelector('.color-solid').value;
        }

        preview.style.background = value;
        hiddenInput.value = value;

        // Auto-save si section sélectionnée
        if (selectedSectionId) {
            debouncedAutoSave();
        }
    }

    function setGradientPickerValue(picker, value) {
        if (!value || !picker) return;

        const tabs = picker.querySelectorAll('.gradient-tab');
        const solidSection = picker.querySelector('.gradient-solid');
        const gradientSection = picker.querySelector('.gradient-options');
        const colorSolid = picker.querySelector('.color-solid');
        const colorStart = picker.querySelector('.color-start');
        const colorEnd = picker.querySelector('.color-end');
        const angleSlider = picker.querySelector('.angle-slider');
        const angleValue = picker.querySelector('.angle-value');
        const typeSelect = picker.querySelector('.gradient-type-select');
        const preview = picker.querySelector('.gradient-preview');
        const hiddenInput = picker.querySelector('input[type="hidden"]');

        // Protection contre les éléments manquants
        if (!solidSection || !gradientSection || !preview || !hiddenInput) {
            console.warn('Gradient picker elements missing for:', picker.id);
            return;
        }

        // Check if it's a gradient
        if (value.includes && value.includes('gradient')) {
            // Switch to gradient mode
            tabs.forEach(t => t.classList.toggle('active', t.dataset.mode === 'gradient'));
            solidSection.classList.remove('active');
            gradientSection.classList.add('active');

            // Parse gradient
            const isRadial = value.includes('radial');
            typeSelect.value = isRadial ? 'radial' : 'linear';

            // Extract colors
            const colorMatches = value.match(/#[a-fA-F0-9]{6}/g);
            if (colorMatches && colorMatches.length >= 2) {
                colorStart.value = colorMatches[0];
                colorEnd.value = colorMatches[1];
            }

            // Extract angle for linear gradient
            if (!isRadial) {
                const angleMatch = value.match(/(\d+)deg/);
                if (angleMatch) {
                    angleSlider.value = angleMatch[1];
                    angleValue.textContent = angleMatch[1] + '°';
                }
            }
        } else {
            // Solid color mode
            tabs.forEach(t => t.classList.toggle('active', t.dataset.mode === 'solid'));
            solidSection.classList.add('active');
            gradientSection.classList.remove('active');

            // Set color (handle hex colors)
            if (value.startsWith('#')) {
                colorSolid.value = value;
            }
        }

        preview.style.background = value;
        hiddenInput.value = value;
    }

    // Gradient Presets
    let activePresetTarget = null;

    function initGradientPresets() {
        document.querySelectorAll('.gradient-preset').forEach(preset => {
            preset.addEventListener('click', function() {
                const gradient = this.dataset.gradient;

                // Apply to background color picker
                const bgPicker = document.getElementById('bgColorPicker');
                if (bgPicker) {
                    setGradientPickerValue(bgPicker, gradient);
                }

                // Visual feedback
                document.querySelectorAll('.gradient-preset').forEach(p => p.classList.remove('active'));
                this.classList.add('active');

                // Auto-save
                if (selectedSectionId) {
                    debouncedAutoSave();
                }
            });
        });
    }

    // Update setTypographyValues to handle gradient values
    const originalSetTypographyValues = setTypographyValues;
    setTypographyValues = function(config) {
        // Call original function for non-gradient values
        const typo = config?.typography || {};
        const style = config?.style || {};

        // Font family titre
        document.getElementById('propFontFamily').value = typo.font_family || '';

        // Font family sous-titre
        document.getElementById('propSubtitleFontFamily').value = typo.subtitle_font_family || '';

        // Title size
        document.getElementById('propTitleSize').value = typo.title_size || '';

        // Handle gradient pickers for typography colors
        const titleColorPicker = document.getElementById('titleColorPicker');
        const subtitleColorPicker = document.getElementById('subtitleColorPicker');

        if (titleColorPicker) {
            setGradientPickerValue(titleColorPicker, typo.title_color || '#1a1a1a');
        }
        if (subtitleColorPicker) {
            setGradientPickerValue(subtitleColorPicker, typo.subtitle_color || '#666666');
        }

        // Handle gradient pickers for style colors
        const bgColorPicker = document.getElementById('bgColorPicker');
        const textColorPicker = document.getElementById('textColorPicker');

        if (bgColorPicker) {
            setGradientPickerValue(bgColorPicker, style.background_color || '#ffffff');
        }
        if (textColorPicker) {
            setGradientPickerValue(textColorPicker, style.text_color || '#1a1a1a');
        }

        // Bold, Italic, Underline, Uppercase toggles (titre)
        const toggleFields = ['bold', 'italic', 'underline', 'uppercase'];
        toggleFields.forEach(field => {
            const btn = document.querySelector(`.typo-toggle[data-field="typo_${field}"]`);
            const input = document.getElementById('propTypo' + field.charAt(0).toUpperCase() + field.slice(1));
            const value = typo[field] === '1' || typo[field] === 1 || typo[field] === true;

            if (btn) btn.classList.toggle('active', value);
            if (input) input.value = value ? '1' : '0';
        });

        // Bold, Italic, Underline toggles (sous-titre)
        const subtitleToggleFields = ['subtitle_bold', 'subtitle_italic', 'subtitle_underline'];
        subtitleToggleFields.forEach(field => {
            const btn = document.querySelector(`.typo-toggle[data-field="typo_${field}"]`);
            const inputId = 'propTypo' + field.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('');
            const input = document.getElementById(inputId);
            const value = typo[field] === '1' || typo[field] === 1 || typo[field] === true;

            if (btn) btn.classList.toggle('active', value);
            if (input) input.value = value ? '1' : '0';
        });

        // Alignment
        const align = typo.align || 'center';
        document.querySelectorAll('.align-toggle').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.value === align);
        });
        document.getElementById('propTypoAlign').value = align;

        // Offsets
        const offsetY = parseInt(typo.offset_y) || 0;
        const offsetX = parseInt(typo.offset_x) || 0;
        document.getElementById('propOffsetY').value = offsetY;
        document.getElementById('propOffsetX').value = offsetX;
        document.getElementById('offsetYValue').textContent = offsetY + 'px';
        document.getElementById('offsetXValue').textContent = offsetX + 'px';
    };
    </script>
</body>
</html>
