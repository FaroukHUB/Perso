<?php
/**
 * PERSONNALY Admin - Page Builder Homepage
 * Interface visuelle pour construire la page d'accueil
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/ImageHelper.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/HomepageSection.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Pack.php';
require_once __DIR__ . '/../app/models/BlogPost.php';
require_once __DIR__ . '/../app/models/Category.php';

Auth::requireAdmin();

$sectionModel = new HomepageSection();
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
            if ($sectionModel->updateOrder($ids)) {
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
                'type' => $_POST['type'] ?? 'hero',
                'title' => trim($_POST['title'] ?? ''),
                'subtitle' => trim($_POST['subtitle'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'cta_text' => trim($_POST['cta_text'] ?? ''),
                'cta_url' => trim($_POST['cta_url'] ?? ''),
                'media_type' => $_POST['media_type'] ?? 'none',
                'status' => $_POST['status'] ?? 'draft',
                'config' => []
            ];

            // Conserver media existant
            if ($isEdit) {
                $existing = $sectionModel->findById($sectionId);
                if ($existing) {
                    $data['media_url'] = $existing['media_url'];
                    $data['media_type'] = $existing['media_type'];
                }
            }

            // Upload image si fournie
            $uploadDir = __DIR__ . '/../public/uploads/homepage/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (!empty($_FILES['media_file']['tmp_name']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];

                if (in_array($ext, $allowed)) {
                    $filename = 'section_' . time() . '_' . uniqid() . '.' . $ext;
                    $fullPath = $uploadDir . $filename;

                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $fullPath)) {
                        $data['media_url'] = '/uploads/homepage/' . $filename;
                        $data['media_type'] = in_array($ext, ['mp4', 'webm']) ? 'video' : 'image';
                        if ($data['media_type'] === 'image') {
                            ImageHelper::convertToWebP($fullPath);
                        }
                    }
                }
            }

            // Médias additionnels (content_block)
            if ($data['type'] === 'content_block') {
                $additionalMedia = [];
                if (!empty($_POST['existing_media_urls'])) {
                    foreach ($_POST['existing_media_urls'] as $url) {
                        if (!empty($url)) {
                            $additionalMedia[] = ['type' => 'image', 'url' => $url];
                        }
                    }
                }
                $data['config']['additional_media'] = array_slice($additionalMedia, 0, 10);
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
                $data['config']['cta2_url'] = trim($_POST['hero_cta2_url'] ?? '');
                if (!empty($_POST['hero_elements_order']) && is_array($_POST['hero_elements_order'])) {
                    $data['config']['elements_order'] = array_values($_POST['hero_elements_order']);
                }
            }

            // Style
            $data['config']['style'] = [
                'background_color' => !empty($_POST['style_bg_color']) ? $_POST['style_bg_color'] : null,
                'text_color' => !empty($_POST['style_text_color']) ? $_POST['style_text_color'] : null,
                'padding_y' => $_POST['style_padding_y'] ?? 'medium',
            ];

            try {
                if ($isEdit) {
                    $sectionModel->update($sectionId, $data);
                    echo json_encode(['success' => true, 'id' => $sectionId, 'message' => 'Section mise à jour']);
                } else {
                    $newId = $sectionModel->create($data);
                    echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Section créée']);
                }
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

$sections = $sectionModel->findAll();
$csrf = csrfToken();
$types = $sectionModel->getTypes();
$statuses = $sectionModel->getStatuses();

// Données pour les sélecteurs
$products = $productModel->findActive();
$packs = $packModel->findActive();
$categories = $categoryModel->findAllActive();
$blogPosts = $blogModel->findAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Builder - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content builder-main">
            <div class="page-header">
                <h1 class="page-title">Page <span>Builder</span></h1>
                <div class="page-header-actions">
                    <button type="button" class="btn btn-secondary" id="previewSiteBtn" onclick="window.open('/', '_blank')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <polyline points="15 3 21 3 21 9"/>
                            <line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                        Voir le site
                    </button>
                    <button type="button" class="btn btn-primary" id="addSectionBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Ajouter une section
                    </button>
                </div>
            </div>

            <div class="builder-info">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                <span>Glissez les sections pour les réordonner. Cliquez sur une section pour la modifier.</span>
            </div>

            <!-- Liste des sections avec preview -->
            <div class="builder-sections" id="builderSections">
                <?php if (empty($sections)): ?>
                    <div class="builder-empty" id="builderEmpty">
                        <div class="builder-empty-icon">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <line x1="3" y1="9" x2="21" y2="9"/>
                                <line x1="9" y1="21" x2="9" y2="9"/>
                            </svg>
                        </div>
                        <h3>Aucune section</h3>
                        <p>Commencez à construire votre page d'accueil en ajoutant une première section.</p>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('addSectionBtn').click()">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Ajouter une section
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($sections as $section): ?>
                        <div class="builder-section-card" data-id="<?= $section['id'] ?>" data-status="<?= h($section['status']) ?>">
                            <div class="builder-section-drag" title="Glisser pour réordonner">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/>
                                    <circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/>
                                    <circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>
                                </svg>
                            </div>

                            <div class="builder-section-order"><?= $section['sort_order'] + 1 ?></div>

                            <div class="builder-section-preview" onclick="editSection(<?= $section['id'] ?>)">
                                <div class="builder-section-type">
                                    <span class="type-badge type-<?= h($section['type']) ?>">
                                        <?= h($types[$section['type']] ?? $section['type']) ?>
                                    </span>
                                    <?php if ($section['status'] === 'draft'): ?>
                                        <span class="status-badge status-draft">Brouillon</span>
                                    <?php endif; ?>
                                </div>
                                <div class="builder-section-title">
                                    <?= h($section['title'] ?: '(Sans titre)') ?>
                                </div>
                                <?php if ($section['subtitle']): ?>
                                    <div class="builder-section-subtitle"><?= h(substr($section['subtitle'], 0, 80)) ?><?= strlen($section['subtitle']) > 80 ? '...' : '' ?></div>
                                <?php endif; ?>
                                <?php if ($section['media_url'] && $section['media_type'] === 'image'): ?>
                                    <div class="builder-section-thumbnail">
                                        <img src="/public<?= h($section['media_url']) ?>" alt="">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="builder-section-actions">
                                <button type="button" class="builder-action-btn" onclick="editSection(<?= $section['id'] ?>)" title="Modifier">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <button type="button" class="builder-action-btn <?= $section['status'] === 'active' ? 'active' : '' ?>" onclick="toggleSection(<?= $section['id'] ?>)" title="<?= $section['status'] === 'active' ? 'Désactiver' : 'Activer' ?>">
                                    <?php if ($section['status'] === 'active'): ?>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                        </svg>
                                    <?php endif; ?>
                                </button>
                                <button type="button" class="builder-action-btn danger" onclick="deleteSection(<?= $section['id'] ?>)" title="Supprimer">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Bouton ajouter section (flottant en bas) -->
            <div class="builder-add-section" id="builderAddSection">
                <button type="button" class="builder-add-btn" onclick="document.getElementById('addSectionBtn').click()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span>Ajouter une section ici</span>
                </button>
            </div>
        </main>
    </div>

    <!-- Modal choix type de section -->
    <div class="builder-modal" id="typeModal">
        <div class="builder-modal-backdrop" onclick="closeTypeModal()"></div>
        <div class="builder-modal-content">
            <div class="builder-modal-header">
                <h3>Choisir un type de section</h3>
                <button type="button" class="builder-modal-close" onclick="closeTypeModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="builder-modal-body">
                <div class="section-type-grid">
                    <?php
                    $typeIcons = [
                        'hero' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg>',
                        'featured_products' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
                        'featured_packs' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
                        'featured_category' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
                        'content_block' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
                        'blog_slider' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
                        'newsletter' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>'
                    ];
                    $typeDescriptions = [
                        'hero' => 'Grande bannière avec image de fond, titre et boutons',
                        'featured_products' => 'Grille de produits sélectionnés',
                        'featured_packs' => 'Sélection de packs / idées inspiration',
                        'featured_category' => 'Affiche automatiquement les produits d\'une catégorie',
                        'content_block' => 'Bloc texte + média (image/vidéo)',
                        'blog_slider' => 'Carrousel d\'articles du blog',
                        'newsletter' => 'Formulaire d\'inscription newsletter'
                    ];
                    foreach ($types as $value => $label):
                    ?>
                        <div class="section-type-option" onclick="createSection('<?= h($value) ?>')">
                            <div class="section-type-icon type-<?= h($value) ?>">
                                <?= $typeIcons[$value] ?? '' ?>
                            </div>
                            <div class="section-type-label"><?= h($label) ?></div>
                            <div class="section-type-desc"><?= h($typeDescriptions[$value] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Panneau d'édition latéral -->
    <div class="builder-panel" id="editPanel">
        <div class="builder-panel-header">
            <h3 id="panelTitle">Modifier la section</h3>
            <button type="button" class="builder-panel-close" onclick="closeEditPanel()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <form id="sectionForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="ajax_action" value="save">
            <input type="hidden" name="section_id" id="sectionId" value="0">

            <div class="builder-panel-body">
                <!-- Type (non modifiable en édition) -->
                <div class="panel-form-group" id="typeGroup">
                    <label>Type de section</label>
                    <input type="hidden" name="type" id="sectionType" value="hero">
                    <div class="panel-type-display" id="typeDisplay">
                        <span class="type-badge type-hero">Hero Banner</span>
                    </div>
                </div>

                <!-- Statut -->
                <div class="panel-form-group">
                    <label for="status">Statut</label>
                    <select name="status" id="status">
                        <option value="draft">Brouillon</option>
                        <option value="active">Actif (visible)</option>
                    </select>
                </div>

                <!-- Titre -->
                <div class="panel-form-group">
                    <label for="title">Titre</label>
                    <input type="text" name="title" id="title" placeholder="Titre de la section">
                </div>

                <!-- Sous-titre -->
                <div class="panel-form-group">
                    <label for="subtitle">Sous-titre / Description</label>
                    <textarea name="subtitle" id="subtitle" rows="2" placeholder="Description courte"></textarea>
                </div>

                <!-- Contenu (content_block) -->
                <div class="panel-form-group content-field" style="display: none;">
                    <label for="content">Contenu texte</label>
                    <textarea name="content" id="content" rows="4" placeholder="Contenu principal"></textarea>
                </div>

                <!-- CTA -->
                <div class="panel-form-group cta-fields">
                    <label>Bouton CTA</label>
                    <div class="panel-form-row">
                        <input type="text" name="cta_text" id="cta_text" placeholder="Texte du bouton">
                        <input type="text" name="cta_url" id="cta_url" placeholder="URL">
                    </div>
                </div>

                <!-- Champs Hero -->
                <div class="hero-fields" style="display: none;">
                    <div class="panel-form-group">
                        <label for="hero_badge">Badge (optionnel)</label>
                        <input type="text" name="hero_badge" id="hero_badge" placeholder="Ex: Nouveau">
                    </div>
                    <div class="panel-form-group">
                        <label for="hero_highlight">Texte mis en avant</label>
                        <input type="text" name="hero_highlight" id="hero_highlight" placeholder="Texte coloré à la fin du titre">
                    </div>
                    <div class="panel-form-group">
                        <label>Bouton secondaire (optionnel)</label>
                        <div class="panel-form-row">
                            <input type="text" name="hero_cta2_text" id="hero_cta2_text" placeholder="Texte">
                            <input type="text" name="hero_cta2_url" id="hero_cta2_url" placeholder="URL">
                        </div>
                    </div>
                    <div class="panel-form-group">
                        <label>Ordre des éléments</label>
                        <div class="hero-elements-order" id="heroElementsOrder">
                            <div class="hero-order-item" data-element="badge">
                                <span class="hero-order-label">Badge</span>
                                <input type="hidden" name="hero_elements_order[]" value="badge">
                                <div class="hero-order-arrows">
                                    <button type="button" onclick="moveHeroElement(this, -1)">▲</button>
                                    <button type="button" onclick="moveHeroElement(this, 1)">▼</button>
                                </div>
                            </div>
                            <div class="hero-order-item" data-element="title">
                                <span class="hero-order-label">Titre + Highlight</span>
                                <input type="hidden" name="hero_elements_order[]" value="title">
                                <div class="hero-order-arrows">
                                    <button type="button" onclick="moveHeroElement(this, -1)">▲</button>
                                    <button type="button" onclick="moveHeroElement(this, 1)">▼</button>
                                </div>
                            </div>
                            <div class="hero-order-item" data-element="subtitle">
                                <span class="hero-order-label">Sous-titre</span>
                                <input type="hidden" name="hero_elements_order[]" value="subtitle">
                                <div class="hero-order-arrows">
                                    <button type="button" onclick="moveHeroElement(this, -1)">▲</button>
                                    <button type="button" onclick="moveHeroElement(this, 1)">▼</button>
                                </div>
                            </div>
                            <div class="hero-order-item" data-element="buttons">
                                <span class="hero-order-label">Boutons CTA</span>
                                <input type="hidden" name="hero_elements_order[]" value="buttons">
                                <div class="hero-order-arrows">
                                    <button type="button" onclick="moveHeroElement(this, -1)">▲</button>
                                    <button type="button" onclick="moveHeroElement(this, 1)">▼</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Média -->
                <div class="panel-form-group media-field">
                    <label>Image / Vidéo</label>
                    <div class="current-media-preview" id="currentMediaPreview" style="display: none;">
                        <img src="" alt="" id="mediaPreviewImg">
                    </div>
                    <input type="file" name="media_file" id="media_file" accept="image/*,video/mp4,video/webm">
                    <small>Formats : JPG, PNG, GIF, WebP, MP4, WebM</small>
                </div>

                <!-- Sélection produits -->
                <div class="panel-form-group products-field" style="display: none;">
                    <label>Sélectionner les produits</label>
                    <div class="items-grid" id="productsGrid">
                        <?php foreach ($products as $product): ?>
                            <label class="item-checkbox">
                                <input type="checkbox" name="product_ids[]" value="<?= $product['id'] ?>">
                                <div class="item-checkbox-content">
                                    <?php if ($product['image_url']): ?>
                                        <img src="/public<?= h($product['image_url']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="item-no-image">?</div>
                                    <?php endif; ?>
                                    <span><?= h($product['name']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sélection packs -->
                <div class="panel-form-group packs-field" style="display: none;">
                    <label>Sélectionner les packs</label>
                    <div class="items-grid" id="packsGrid">
                        <?php foreach ($packs as $pack): ?>
                            <label class="item-checkbox">
                                <input type="checkbox" name="pack_ids[]" value="<?= $pack['id'] ?>">
                                <div class="item-checkbox-content">
                                    <?php if ($pack['cover_url']): ?>
                                        <img src="/public<?= h($pack['cover_url']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="item-no-image">?</div>
                                    <?php endif; ?>
                                    <span><?= h($pack['name']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sélection articles -->
                <div class="panel-form-group blog-field" style="display: none;">
                    <label>Sélectionner les articles</label>
                    <div class="items-grid" id="blogGrid">
                        <?php foreach ($blogPosts as $post): ?>
                            <label class="item-checkbox">
                                <input type="checkbox" name="blog_ids[]" value="<?= $post['id'] ?>">
                                <div class="item-checkbox-content">
                                    <?php if ($post['cover_image']): ?>
                                        <img src="/public<?= h($post['cover_image']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="item-no-image">B</div>
                                    <?php endif; ?>
                                    <span><?= h($post['title']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sélection catégorie -->
                <div class="panel-form-group category-field" style="display: none;">
                    <label for="category_id">Catégorie</label>
                    <select name="category_id" id="category_id">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top: 10px;">
                        <label for="products_limit">Nombre de produits max</label>
                        <input type="number" name="products_limit" id="products_limit" value="8" min="1" max="20">
                    </div>
                </div>

                <!-- Style -->
                <div class="panel-form-group">
                    <label>Style visuel</label>
                    <div class="style-options">
                        <div class="style-option">
                            <label for="style_bg_color">Couleur de fond</label>
                            <input type="color" name="style_bg_color" id="style_bg_color" value="#ffffff">
                        </div>
                        <div class="style-option">
                            <label for="style_text_color">Couleur du texte</label>
                            <input type="color" name="style_text_color" id="style_text_color" value="#1a1a1a">
                        </div>
                        <div class="style-option">
                            <label for="style_padding_y">Espacement vertical</label>
                            <select name="style_padding_y" id="style_padding_y">
                                <option value="none">Aucun</option>
                                <option value="small">Petit</option>
                                <option value="medium" selected>Moyen</option>
                                <option value="large">Grand</option>
                                <option value="xlarge">Très grand</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="builder-panel-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditPanel()">Annuler</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
    <div class="builder-panel-overlay" id="panelOverlay" onclick="closeEditPanel()"></div>

    <style>
    /* Builder Main Layout */
    .builder-main {
        max-width: 1000px;
        padding-bottom: 100px;
    }

    .builder-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        background: linear-gradient(135deg, rgba(255,105,180,0.08) 0%, rgba(61,255,192,0.08) 100%);
        border-radius: var(--radius-md);
        border-left: 4px solid var(--pink-main);
        margin-bottom: 24px;
        font-size: 14px;
        color: var(--gray-dark);
    }

    /* Section Cards */
    .builder-sections {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .builder-section-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: white;
        border-radius: var(--radius-lg);
        border: 2px solid var(--gray-light);
        transition: all 0.2s ease;
    }

    .builder-section-card:hover {
        border-color: var(--pink-main);
        box-shadow: 0 4px 12px rgba(255,105,180,0.15);
    }

    .builder-section-card[data-status="draft"] {
        opacity: 0.7;
        background: repeating-linear-gradient(
            45deg,
            white,
            white 10px,
            #fafafa 10px,
            #fafafa 20px
        );
    }

    .builder-section-card.dragging {
        opacity: 0.5;
        transform: scale(0.98);
    }

    .builder-section-drag {
        cursor: grab;
        padding: 8px;
        color: var(--gray);
        border-radius: var(--radius-sm);
        transition: all 0.2s;
    }

    .builder-section-drag:hover {
        background: var(--gray-light);
        color: var(--pink-main);
    }

    .builder-section-drag:active {
        cursor: grabbing;
    }

    .builder-section-order {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background: var(--gradient-pink);
        color: white;
        border-radius: 50%;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .builder-section-preview {
        flex: 1;
        cursor: pointer;
        padding: 8px;
        border-radius: var(--radius-sm);
        transition: background 0.2s;
    }

    .builder-section-preview:hover {
        background: var(--gray-light);
    }

    .builder-section-type {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }

    .builder-section-title {
        font-weight: 600;
        font-size: 16px;
        color: var(--black);
        margin-bottom: 2px;
    }

    .builder-section-subtitle {
        font-size: 13px;
        color: var(--gray);
    }

    .builder-section-thumbnail {
        margin-top: 10px;
    }

    .builder-section-thumbnail img {
        max-width: 120px;
        max-height: 60px;
        border-radius: var(--radius-sm);
        object-fit: cover;
    }

    .builder-section-actions {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .builder-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: none;
        background: var(--gray-light);
        color: var(--gray);
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: all 0.2s;
    }

    .builder-action-btn:hover {
        background: var(--pink-main);
        color: white;
    }

    .builder-action-btn.active {
        background: var(--mint-main);
        color: var(--black);
    }

    .builder-action-btn.danger:hover {
        background: #dc3545;
    }

    /* Type badges */
    .type-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .type-hero { background: var(--gradient-pink); color: white; }
    .type-featured_products { background: var(--mint-main); color: var(--black); }
    .type-featured_packs { background: #9b59b6; color: white; }
    .type-featured_category { background: #e74c3c; color: white; }
    .type-content_block { background: #3498db; color: white; }
    .type-blog_slider { background: #e67e22; color: white; }
    .type-newsletter { background: #1abc9c; color: white; }

    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-draft {
        background: rgba(0,0,0,0.1);
        color: var(--gray);
    }

    /* Empty State */
    .builder-empty {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: var(--radius-lg);
        border: 2px dashed var(--gray-light);
    }

    .builder-empty-icon {
        color: var(--gray-light);
        margin-bottom: 20px;
    }

    .builder-empty h3 {
        margin-bottom: 8px;
        color: var(--gray);
    }

    .builder-empty p {
        color: var(--gray);
        margin-bottom: 20px;
    }

    /* Add Section Button */
    .builder-add-section {
        margin-top: 20px;
    }

    .builder-add-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 20px;
        background: transparent;
        border: 2px dashed var(--gray-light);
        border-radius: var(--radius-lg);
        color: var(--gray);
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .builder-add-btn:hover {
        border-color: var(--pink-main);
        color: var(--pink-main);
        background: rgba(255,105,180,0.05);
    }

    /* Type Selection Modal */
    .builder-modal {
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

    .builder-modal.active {
        display: flex;
    }

    .builder-modal-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
    }

    .builder-modal-content {
        position: relative;
        width: 90%;
        max-width: 700px;
        max-height: 90vh;
        background: white;
        border-radius: var(--radius-xl);
        overflow: hidden;
        animation: modalIn 0.3s ease;
    }

    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }

    .builder-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        border-bottom: 1px solid var(--gray-light);
    }

    .builder-modal-header h3 {
        font-size: 18px;
        font-weight: 600;
    }

    .builder-modal-close {
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        color: var(--gray);
        transition: color 0.2s;
    }

    .builder-modal-close:hover {
        color: var(--pink-main);
    }

    .builder-modal-body {
        padding: 24px;
        overflow-y: auto;
        max-height: calc(90vh - 80px);
    }

    .section-type-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
    }

    .section-type-option {
        padding: 20px;
        background: var(--gray-light);
        border-radius: var(--radius-lg);
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
    }

    .section-type-option:hover {
        background: white;
        border-color: var(--pink-main);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255,105,180,0.2);
    }

    .section-type-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-md);
        margin-bottom: 12px;
    }

    .section-type-icon svg {
        width: 28px;
        height: 28px;
    }

    .section-type-icon.type-hero { background: var(--gradient-pink); color: white; }
    .section-type-icon.type-featured_products { background: var(--mint-main); color: var(--black); }
    .section-type-icon.type-featured_packs { background: #9b59b6; color: white; }
    .section-type-icon.type-featured_category { background: #e74c3c; color: white; }
    .section-type-icon.type-content_block { background: #3498db; color: white; }
    .section-type-icon.type-blog_slider { background: #e67e22; color: white; }
    .section-type-icon.type-newsletter { background: #1abc9c; color: white; }

    .section-type-label {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .section-type-desc {
        font-size: 12px;
        color: var(--gray);
        line-height: 1.4;
    }

    /* Edit Panel */
    .builder-panel {
        position: fixed;
        top: 0;
        right: -500px;
        width: 480px;
        max-width: 100%;
        height: 100vh;
        background: white;
        box-shadow: -4px 0 20px rgba(0,0,0,0.15);
        z-index: 1001;
        display: flex;
        flex-direction: column;
        transition: right 0.3s ease;
    }

    .builder-panel.active {
        right: 0;
    }

    .builder-panel-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.3);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s;
    }

    .builder-panel-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .builder-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        border-bottom: 1px solid var(--gray-light);
        background: var(--gray-light);
    }

    .builder-panel-header h3 {
        font-size: 18px;
        font-weight: 600;
    }

    .builder-panel-close {
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        color: var(--gray);
    }

    .builder-panel-close:hover {
        color: var(--pink-main);
    }

    .builder-panel-body {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
    }

    .builder-panel-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--gray-light);
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    /* Panel Form */
    .panel-form-group {
        margin-bottom: 20px;
    }

    .panel-form-group > label {
        display: block;
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 6px;
        color: var(--black);
    }

    .panel-form-group input[type="text"],
    .panel-form-group input[type="number"],
    .panel-form-group textarea,
    .panel-form-group select {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--gray-light);
        border-radius: var(--radius-md);
        font-size: 14px;
        transition: border-color 0.2s;
    }

    .panel-form-group input:focus,
    .panel-form-group textarea:focus,
    .panel-form-group select:focus {
        outline: none;
        border-color: var(--pink-main);
    }

    .panel-form-group small {
        display: block;
        margin-top: 4px;
        font-size: 12px;
        color: var(--gray);
    }

    .panel-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .panel-type-display {
        padding: 10px;
        background: var(--gray-light);
        border-radius: var(--radius-md);
    }

    /* Items Grid */
    .items-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        max-height: 200px;
        overflow-y: auto;
        padding: 8px;
        background: var(--gray-light);
        border-radius: var(--radius-md);
    }

    .item-checkbox {
        cursor: pointer;
    }

    .item-checkbox input {
        display: none;
    }

    .item-checkbox-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 8px;
        background: white;
        border-radius: var(--radius-sm);
        border: 2px solid transparent;
        transition: all 0.2s;
    }

    .item-checkbox input:checked + .item-checkbox-content {
        border-color: var(--pink-main);
        background: rgba(255,105,180,0.1);
    }

    .item-checkbox-content img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: var(--radius-sm);
        margin-bottom: 4px;
    }

    .item-checkbox-content span {
        font-size: 10px;
        text-align: center;
        line-height: 1.2;
        max-height: 24px;
        overflow: hidden;
    }

    .item-no-image {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--gray-light);
        border-radius: var(--radius-sm);
        margin-bottom: 4px;
        color: var(--gray);
        font-weight: 600;
    }

    /* Style Options */
    .style-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .style-option {
        padding: 12px;
        background: var(--gray-light);
        border-radius: var(--radius-md);
    }

    .style-option label {
        display: block;
        font-size: 12px;
        margin-bottom: 6px;
        color: var(--gray);
    }

    .style-option input[type="color"] {
        width: 100%;
        height: 36px;
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
    }

    .style-option select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: var(--radius-sm);
    }

    /* Hero Elements Order */
    .hero-elements-order {
        background: var(--gray-light);
        border-radius: var(--radius-md);
        padding: 8px;
    }

    .hero-order-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        background: white;
        border-radius: var(--radius-sm);
        margin-bottom: 6px;
    }

    .hero-order-item:last-child {
        margin-bottom: 0;
    }

    .hero-order-label {
        font-size: 13px;
        font-weight: 500;
    }

    .hero-order-arrows {
        display: flex;
        gap: 4px;
    }

    .hero-order-arrows button {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        background: var(--gray-light);
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: 10px;
        transition: all 0.2s;
    }

    .hero-order-arrows button:hover {
        background: var(--pink-main);
        color: white;
    }

    /* Current Media Preview */
    .current-media-preview {
        margin-bottom: 10px;
    }

    .current-media-preview img {
        max-width: 100%;
        max-height: 150px;
        border-radius: var(--radius-md);
        object-fit: cover;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .builder-panel {
            width: 100%;
            right: -100%;
        }

        .section-type-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .items-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>

    <script>
    const csrf = '<?= $csrf ?>';
    const types = <?= json_encode($types) ?>;
    let currentEditId = 0;

    // Drag & Drop
    document.addEventListener('DOMContentLoaded', function() {
        initDragAndDrop();
    });

    function initDragAndDrop() {
        const container = document.getElementById('builderSections');
        if (!container) return;

        let draggedItem = null;

        container.querySelectorAll('.builder-section-card').forEach(card => {
            card.draggable = true;

            card.addEventListener('dragstart', function(e) {
                draggedItem = card;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            card.addEventListener('dragend', function() {
                card.classList.remove('dragging');
                draggedItem = null;
                saveOrder();
            });

            card.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (!draggedItem || draggedItem === card) return;

                const rect = card.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;

                if (e.clientY < midpoint) {
                    container.insertBefore(draggedItem, card);
                } else {
                    container.insertBefore(draggedItem, card.nextSibling);
                }
            });
        });
    }

    function saveOrder() {
        const cards = document.querySelectorAll('.builder-section-card');
        const ids = [...cards].map(c => c.dataset.id);

        fetch('/admin/homepage-builder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax_action=reorder&order=${encodeURIComponent(JSON.stringify(ids))}&csrf_token=${csrf}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                cards.forEach((card, i) => {
                    const badge = card.querySelector('.builder-section-order');
                    if (badge) badge.textContent = i + 1;
                });
            }
        });
    }

    // Type Modal
    document.getElementById('addSectionBtn').addEventListener('click', function() {
        document.getElementById('typeModal').classList.add('active');
    });

    function closeTypeModal() {
        document.getElementById('typeModal').classList.remove('active');
    }

    function createSection(type) {
        closeTypeModal();
        openEditPanel(0, type);
    }

    // Edit Panel
    function editSection(id) {
        fetch('/admin/homepage-builder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax_action=get_section&section_id=${id}&csrf_token=${csrf}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                openEditPanel(id, data.section.type, data.section);
            }
        });
    }

    function openEditPanel(id, type, sectionData = null) {
        currentEditId = id;

        document.getElementById('sectionId').value = id;
        document.getElementById('sectionType').value = type;
        document.getElementById('panelTitle').textContent = id ? 'Modifier la section' : 'Nouvelle section';

        // Type display
        document.getElementById('typeDisplay').innerHTML = `<span class="type-badge type-${type}">${types[type] || type}</span>`;

        // Reset form
        const form = document.getElementById('sectionForm');
        form.reset();

        // Show/hide fields based on type
        updateFormFields(type);

        // Fill data if editing
        if (sectionData) {
            document.getElementById('status').value = sectionData.status || 'draft';
            document.getElementById('title').value = sectionData.title || '';
            document.getElementById('subtitle').value = sectionData.subtitle || '';
            document.getElementById('content').value = sectionData.content || '';
            document.getElementById('cta_text').value = sectionData.cta_text || '';
            document.getElementById('cta_url').value = sectionData.cta_url || '';

            // Hero fields
            if (type === 'hero' && sectionData.config) {
                document.getElementById('hero_badge').value = sectionData.config.badge || '';
                document.getElementById('hero_highlight').value = sectionData.config.highlight || '';
                document.getElementById('hero_cta2_text').value = sectionData.config.cta2_text || '';
                document.getElementById('hero_cta2_url').value = sectionData.config.cta2_url || '';

                if (sectionData.config.elements_order) {
                    reorderHeroElements(sectionData.config.elements_order);
                }
            }

            // Category
            if (type === 'featured_category' && sectionData.config) {
                document.getElementById('category_id').value = sectionData.config.category_id || '';
                document.getElementById('products_limit').value = sectionData.config.products_limit || 8;
            }

            // Style
            if (sectionData.config && sectionData.config.style) {
                if (sectionData.config.style.background_color) {
                    document.getElementById('style_bg_color').value = sectionData.config.style.background_color;
                }
                if (sectionData.config.style.text_color) {
                    document.getElementById('style_text_color').value = sectionData.config.style.text_color;
                }
                document.getElementById('style_padding_y').value = sectionData.config.style.padding_y || 'medium';
            }

            // Media preview
            if (sectionData.media_url && sectionData.media_type === 'image') {
                document.getElementById('currentMediaPreview').style.display = 'block';
                document.getElementById('mediaPreviewImg').src = '/public' + sectionData.media_url;
            } else {
                document.getElementById('currentMediaPreview').style.display = 'none';
            }

            // Selected items
            if (sectionData.items) {
                sectionData.items.forEach(item => {
                    let checkbox;
                    if (item.item_type === 'product') {
                        checkbox = document.querySelector(`input[name="product_ids[]"][value="${item.item_id}"]`);
                    } else if (item.item_type === 'pack') {
                        checkbox = document.querySelector(`input[name="pack_ids[]"][value="${item.item_id}"]`);
                    } else if (item.item_type === 'blog') {
                        checkbox = document.querySelector(`input[name="blog_ids[]"][value="${item.item_id}"]`);
                    }
                    if (checkbox) checkbox.checked = true;
                });
            }
        } else {
            document.getElementById('currentMediaPreview').style.display = 'none';
        }

        document.getElementById('editPanel').classList.add('active');
        document.getElementById('panelOverlay').classList.add('active');
    }

    function closeEditPanel() {
        document.getElementById('editPanel').classList.remove('active');
        document.getElementById('panelOverlay').classList.remove('active');
        currentEditId = 0;
    }

    function updateFormFields(type) {
        // Hide all conditional fields
        document.querySelectorAll('.hero-fields, .content-field, .products-field, .packs-field, .blog-field, .category-field').forEach(el => {
            el.style.display = 'none';
        });

        // Show relevant fields
        switch(type) {
            case 'hero':
                document.querySelector('.hero-fields').style.display = 'block';
                break;
            case 'content_block':
                document.querySelector('.content-field').style.display = 'block';
                break;
            case 'featured_products':
                document.querySelector('.products-field').style.display = 'block';
                break;
            case 'featured_packs':
                document.querySelector('.packs-field').style.display = 'block';
                break;
            case 'blog_slider':
                document.querySelector('.blog-field').style.display = 'block';
                break;
            case 'featured_category':
                document.querySelector('.category-field').style.display = 'block';
                break;
        }
    }

    function reorderHeroElements(order) {
        const container = document.getElementById('heroElementsOrder');
        const items = {};

        container.querySelectorAll('.hero-order-item').forEach(item => {
            items[item.dataset.element] = item;
        });

        container.innerHTML = '';
        order.forEach(element => {
            if (items[element]) {
                container.appendChild(items[element]);
            }
        });
    }

    function moveHeroElement(btn, direction) {
        const item = btn.closest('.hero-order-item');
        const container = item.parentElement;
        const items = [...container.querySelectorAll('.hero-order-item')];
        const index = items.indexOf(item);
        const newIndex = index + direction;

        if (newIndex >= 0 && newIndex < items.length) {
            if (direction === -1) {
                container.insertBefore(item, items[newIndex]);
            } else {
                container.insertBefore(item, items[newIndex].nextSibling);
            }
        }
    }

    // Save Form
    document.getElementById('sectionForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.set('section_id', currentEditId);

        const saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<svg class="spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg> Enregistrement...';

        fetch('/admin/homepage-builder.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeEditPanel();
                location.reload();
            } else {
                alert(data.error || 'Erreur');
            }
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Enregistrer';
        });
    });

    // Toggle Section
    function toggleSection(id) {
        fetch('/admin/homepage-builder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax_action=toggle&section_id=${id}&csrf_token=${csrf}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // Delete Section
    function deleteSection(id) {
        if (!confirm('Supprimer cette section ?')) return;

        fetch('/admin/homepage-builder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax_action=delete&section_id=${id}&csrf_token=${csrf}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const card = document.querySelector(`.builder-section-card[data-id="${id}"]`);
                if (card) {
                    card.remove();
                    // Update order badges
                    document.querySelectorAll('.builder-section-card').forEach((c, i) => {
                        const badge = c.querySelector('.builder-section-order');
                        if (badge) badge.textContent = i + 1;
                    });
                }
                // Show empty state if no sections left
                if (document.querySelectorAll('.builder-section-card').length === 0) {
                    location.reload();
                }
            }
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTypeModal();
            closeEditPanel();
        }
    });
    </script>
</body>
</html>
