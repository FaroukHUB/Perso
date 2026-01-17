<?php
/**
 * PERSONNALY Admin - Formulaire Section Homepage
 * Création et édition de sections
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/ImageHelper.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/HomepageSection.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Pack.php';
require_once __DIR__ . '/../app/models/BlogPost.php';

Auth::requireAdmin();

$sectionModel = new HomepageSection();
$productModel = new Product();
$packModel = new Pack();
$blogModel = new BlogPost();

$sectionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$section = null;
$isEdit = false;

if ($sectionId > 0) {
    $section = $sectionModel->findById($sectionId);
    if (!$section) {
        redirect('/admin/homepage.php');
    }
    $isEdit = true;
}

$success = '';
$error = '';

// Traitement du formulaire
if (isPost()) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
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

        // Upload image principale si fournie
        $uploadDir = __DIR__ . '/../public/uploads/homepage/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $error = 'Impossible de créer le dossier uploads. Vérifiez les permissions.';
            }
        }

        if (!$error && !empty($_FILES['media_file']['tmp_name'])) {
            // Vérifier les erreurs d'upload PHP
            if ($_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite PHP)',
                    UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire)',
                    UPLOAD_ERR_PARTIAL => 'Fichier partiellement uploadé',
                    UPLOAD_ERR_NO_FILE => 'Aucun fichier uploadé',
                    UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                    UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier',
                    UPLOAD_ERR_EXTENSION => 'Extension PHP a bloqué l\'upload'
                ];
                $error = $uploadErrors[$_FILES['media_file']['error']] ?? 'Erreur upload inconnue';
            } else {
                $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];

                if (!in_array($ext, $allowed)) {
                    $error = 'Format de fichier non autorisé. Utilisez : ' . implode(', ', $allowed);
                } else {
                    $filename = 'section_' . time() . '_' . uniqid() . '.' . $ext;
                    $fullPath = $uploadDir . $filename;

                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $fullPath)) {
                        $data['media_url'] = '/uploads/homepage/' . $filename;

                        if (in_array($ext, ['mp4', 'webm'])) {
                            $data['media_type'] = 'video';
                        } else {
                            $data['media_type'] = 'image';
                            // Générer version WebP pour les images
                            ImageHelper::convertToWebP($fullPath);
                        }
                    } else {
                        $error = 'Échec de l\'upload. Vérifiez les permissions du dossier uploads.';
                    }
                }
            }
        } elseif ($isEdit && !empty($section['media_url'])) {
            $data['media_url'] = $section['media_url'];
            $data['media_type'] = $section['media_type'];
        }

        // Gestion des médias additionnels (content_block uniquement)
        if (!$error && $data['type'] === 'content_block') {
            $additionalMedia = [];

            // 1. Conserver les médias existants (dans l'ordre du formulaire)
            if (!empty($_POST['existing_media_urls'])) {
                foreach ($_POST['existing_media_urls'] as $url) {
                    if (!empty($url)) {
                        $additionalMedia[] = [
                            'type' => 'image',
                            'url' => $url
                        ];
                    }
                }
            }

            // 2. Ajouter les nouveaux médias uploadés
            if (!empty($_FILES['additional_media']['tmp_name'])) {
                $allowedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $files = $_FILES['additional_media'];

                // Gérer input simple ou multiple
                $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
                $names = is_array($files['name']) ? $files['name'] : [$files['name']];
                $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

                foreach ($tmpNames as $idx => $tmpName) {
                    if (!empty($tmpName) && $errors[$idx] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($names[$idx], PATHINFO_EXTENSION));
                        if (in_array($ext, $allowedImages)) {
                            $filename = 'content_' . time() . '_' . uniqid() . '_' . $idx . '.' . $ext;
                            $fullPath = $uploadDir . $filename;
                            if (move_uploaded_file($tmpName, $fullPath)) {
                                // Générer version WebP
                                ImageHelper::convertToWebP($fullPath);
                                $additionalMedia[] = [
                                    'type' => 'image',
                                    'url' => '/uploads/homepage/' . $filename
                                ];
                            }
                        }
                    }
                }
            }

            // Limiter à 10 images maximum
            $additionalMedia = array_slice($additionalMedia, 0, 10);

            $data['config']['additional_media'] = $additionalMedia;
        }

        // Gestion des items (produits/packs/articles)
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
        }
        $data['items'] = $items;

        try {
            if ($isEdit) {
                $sectionModel->update($sectionId, $data);
                $success = 'Section mise à jour avec succès.';
                $section = $sectionModel->findById($sectionId);
            } else {
                $newId = $sectionModel->create($data);
                redirect('/admin/homepage-section.php?id=' . $newId . '&created=1');
            }
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    } else {
        $error = 'Session expirée. Veuillez réessayer.';
    }
}

if (isset($_GET['created'])) {
    $success = 'Section créée avec succès.';
}

$csrf = csrfToken();
$types = $sectionModel->getTypes();
$statuses = $sectionModel->getStatuses();
$mediaTypes = $sectionModel->getMediaTypes();

// Charger produits, packs et articles pour la sélection
$products = $productModel->findActive();
$packs = $packModel->findActive();
$blogPosts = $blogModel->findAll(); // Tous les articles (brouillon + publiés)

// IDs des items sélectionnés
$selectedProductIds = [];
$selectedPackIds = [];
$selectedBlogIds = [];
if ($section && !empty($section['items'])) {
    foreach ($section['items'] as $item) {
        if ($item['item_type'] === 'product') {
            $selectedProductIds[] = $item['item_id'];
        } elseif ($item['item_type'] === 'pack') {
            $selectedPackIds[] = $item['item_id'];
        } elseif ($item['item_type'] === 'blog') {
            $selectedBlogIds[] = $item['item_id'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Modifier' : 'Nouvelle' ?> section - PERSONNALY Admin</title>
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
                <h1 class="page-title">
                    <?php if ($isEdit): ?>
                        Modifier la <span>section</span>
                    <?php else: ?>
                        Nouvelle <span>section</span>
                    <?php endif; ?>
                </h1>
                <a href="/admin/homepage.php" class="btn btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Retour à la liste
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="sectionForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-grid">
                    <!-- Colonne gauche : Infos générales -->
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Informations générales</h3>
                        </div>
                        <div class="data-card-body">
                            <div class="form-group">
                                <label for="type">Type de section *</label>
                                <select name="type" id="type" required onchange="updateFormFields()">
                                    <?php foreach ($types as $value => $label): ?>
                                        <option value="<?= h($value) ?>" <?= ($section['type'] ?? '') === $value ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="title">Titre</label>
                                <input type="text" name="title" id="title"
                                       value="<?= h($section['title'] ?? '') ?>" placeholder="Titre de la section">
                            </div>

                            <div class="form-group">
                                <label for="subtitle">Sous-titre / Description</label>
                                <textarea name="subtitle" id="subtitle" rows="2"
                                          placeholder="Description courte"><?= h($section['subtitle'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group content-field" style="display: none;">
                                <label for="content">Contenu texte</label>
                                <textarea name="content" id="content" rows="5"
                                          placeholder="Contenu principal du bloc"><?= h($section['content'] ?? '') ?></textarea>
                            </div>

                            <div class="form-row cta-fields">
                                <div class="form-group">
                                    <label for="cta_text">Texte du bouton CTA</label>
                                    <input type="text" name="cta_text" id="cta_text"
                                           value="<?= h($section['cta_text'] ?? '') ?>" placeholder="Découvrir">
                                </div>
                                <div class="form-group">
                                    <label for="cta_url">URL du CTA</label>
                                    <input type="text" name="cta_url" id="cta_url"
                                           value="<?= h($section['cta_url'] ?? '') ?>" placeholder="#produits ou /page">
                                </div>
                            </div>

                            <div class="form-group media-field">
                                <label for="media_file">Image / Vidéo</label>
                                <?php if ($section && !empty($section['media_url'])): ?>
                                    <div class="current-media">
                                        <?php if ($section['media_type'] === 'video'): ?>
                                            <video src="/public<?= h($section['media_url']) ?>" width="200" controls></video>
                                        <?php else: ?>
                                            <img src="/public<?= h($section['media_url']) ?>" alt="Media actuel" style="max-width: 200px; border-radius: 8px;">
                                        <?php endif; ?>
                                        <small class="text-muted">Média actuel</small>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="media_file" id="media_file"
                                       accept="image/*,video/mp4,video/webm">
                                <small class="form-hint">Formats : JPG, PNG, GIF, WebP, MP4, WebM</small>
                            </div>

                            <!-- Multi-médias pour content_block -->
                            <div class="form-group multi-media-field" style="display: none;">
                                <label>Médias additionnels</label>
                                <?php
                                $additionalMedia = [];
                                if ($section && !empty($section['config']['additional_media'])) {
                                    $additionalMedia = $section['config']['additional_media'];
                                }
                                ?>
                                <div class="media-gallery-container" id="mediaGalleryContainer">
                                    <div class="media-gallery-grid" id="mediaGalleryGrid">
                                        <?php foreach ($additionalMedia as $idx => $media): ?>
                                            <div class="media-gallery-item" draggable="true" data-index="<?= $idx ?>">
                                                <img src="/public<?= h($media['url']) ?>" alt="Media <?= $idx + 1 ?>">
                                                <input type="hidden" name="existing_media_urls[]" value="<?= h($media['url']) ?>">
                                                <button type="button" class="media-delete-btn" onclick="removeMediaItem(this)" title="Supprimer">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                                    </svg>
                                                </button>
                                                <div class="media-drag-handle" title="Glisser pour réordonner">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                                        <circle cx="9" cy="6" r="2"/><circle cx="15" cy="6" r="2"/>
                                                        <circle cx="9" cy="12" r="2"/><circle cx="15" cy="12" r="2"/>
                                                        <circle cx="9" cy="18" r="2"/><circle cx="15" cy="18" r="2"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <!-- Zone d'ajout -->
                                        <div class="media-add-zone" id="mediaAddZone">
                                            <input type="file" name="additional_media[]" id="additionalMediaInput" accept="image/*" style="display: none;">
                                            <button type="button" class="media-add-btn" onclick="document.getElementById('additionalMediaInput').click()">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                                </svg>
                                                <span>Ajouter</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="media-pending-uploads" id="mediaPendingUploads"></div>
                                </div>
                                <small class="form-hint">Cliquez sur + pour ajouter une image. Glissez-déposez pour réordonner. Maximum 10 images.</small>
                            </div>

                            <div class="form-group">
                                <label for="status">Statut</label>
                                <select name="status" id="status">
                                    <?php foreach ($statuses as $value => $label): ?>
                                        <option value="<?= h($value) ?>" <?= ($section['status'] ?? 'draft') === $value ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne droite : Sélection items -->
                    <div class="data-card items-card" id="itemsCard" style="display: none;">
                        <div class="data-card-header">
                            <h3 class="data-card-title" id="itemsCardTitle">Sélection</h3>
                            <span class="badge badge-pink" id="itemsCount">0 sélectionné(s)</span>
                        </div>
                        <div class="data-card-body">
                            <!-- Produits -->
                            <div id="productsSelection" style="display: none;">
                                <p class="text-muted" style="margin-bottom: 15px;">Sélectionnez les produits à afficher (max 8 recommandé)</p>
                                <div class="items-grid">
                                    <?php foreach ($products as $product): ?>
                                        <label class="item-checkbox">
                                            <input type="checkbox" name="product_ids[]" value="<?= $product['id'] ?>"
                                                   <?= in_array($product['id'], $selectedProductIds) ? 'checked' : '' ?>>
                                            <div class="item-card">
                                                <?php if (!empty($product['image_front_url'])): ?>
                                                    <img src="/public<?= h($product['image_front_url']) ?>" alt="">
                                                <?php else: ?>
                                                    <div class="item-placeholder">👕</div>
                                                <?php endif; ?>
                                                <span class="item-name"><?= h($product['name']) ?></span>
                                                <span class="item-price"><?= formatPrice($product['base_price']) ?></span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Packs -->
                            <div id="packsSelection" style="display: none;">
                                <p class="text-muted" style="margin-bottom: 15px;">Sélectionnez les packs à afficher (max 6 recommandé)</p>
                                <div class="items-grid">
                                    <?php foreach ($packs as $pack): ?>
                                        <label class="item-checkbox">
                                            <input type="checkbox" name="pack_ids[]" value="<?= $pack['id'] ?>"
                                                   <?= in_array($pack['id'], $selectedPackIds) ? 'checked' : '' ?>>
                                            <div class="item-card">
                                                <?php if (!empty($pack['cover_image_url'])): ?>
                                                    <img src="/public<?= h($pack['cover_image_url']) ?>" alt="">
                                                <?php else: ?>
                                                    <div class="item-placeholder">✨</div>
                                                <?php endif; ?>
                                                <span class="item-name"><?= h($pack['name']) ?></span>
                                                <span class="item-type"><?= h($pack['type']) ?></span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Articles de blog -->
                            <div id="blogSelection" style="display: none;">
                                <p class="text-muted" style="margin-bottom: 15px;">
                                    Sélectionnez les articles à afficher. <a href="/admin/blog.php" style="color: var(--pink-main);">Gérer les articles</a>
                                </p>
                                <?php if (empty($blogPosts)): ?>
                                    <div class="info-box-blue">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                                        </svg>
                                        <p>Aucun article de blog. <a href="/admin/blog-form.php">Créer un article</a>.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="items-grid">
                                        <?php foreach ($blogPosts as $post): ?>
                                            <label class="item-checkbox">
                                                <input type="checkbox" name="blog_ids[]" value="<?= $post['id'] ?>"
                                                       <?= in_array($post['id'], $selectedBlogIds) ? 'checked' : '' ?>>
                                                <div class="item-card <?= $post['status'] === 'draft' ? 'item-draft' : '' ?>">
                                                    <?php if (!empty($post['cover_image_url'])): ?>
                                                        <img src="/public<?= h($post['cover_image_url']) ?>" alt="">
                                                    <?php else: ?>
                                                        <div class="item-placeholder">📝</div>
                                                    <?php endif; ?>
                                                    <span class="item-name"><?= h($post['title']) ?></span>
                                                    <span class="item-type <?= $post['status'] === 'published' ? 'status-published' : 'status-draft' ?>">
                                                        <?= $post['status'] === 'published' ? '✓ Publié' : '⏳ Brouillon' ?>
                                                    </span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Newsletter info -->
                            <div id="newsletterInfo" style="display: none;">
                                <div class="info-box-pink">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                        <polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                    <div>
                                        <p><strong>Section Newsletter</strong></p>
                                        <p>Les visiteurs pourront s'inscrire à votre newsletter. Les emails sont stockés dans la base de données.</p>
                                        <ul style="margin: 10px 0 0; padding-left: 20px; font-size: 13px;">
                                            <li><strong>Titre</strong> : phrase principale</li>
                                            <li><strong>Sous-titre</strong> : phrase secondaire (optionnel)</li>
                                            <li><strong>Texte bouton</strong> : ex. "S'inscrire"</li>
                                            <li><strong>Image</strong> : fond de la section</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="/admin/homepage.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        <?= $isEdit ? 'Enregistrer' : 'Créer la section' ?>
                    </button>
                </div>
            </form>
        </main>
    </div>

    <style>
        .data-card-body { padding: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--black);
        }
        .form-group input[type="text"],
        .form-group input[type="file"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            font-size: 15px;
            transition: border-color var(--transition-fast);
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--pink-main);
        }
        .form-group textarea {
            resize: vertical;
            font-family: inherit;
        }
        .form-hint {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: var(--gray);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
            margin-bottom: 24px;
        }
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            max-height: 400px;
            overflow-y: auto;
            padding: 5px;
        }
        .item-checkbox { cursor: pointer; }
        .item-checkbox input { display: none; }
        .item-card {
            border: 2px solid var(--gray-light);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            transition: all 0.2s;
            background: white;
        }
        .item-checkbox input:checked + .item-card {
            border-color: var(--pink-main);
            background: rgba(255, 105, 180, 0.05);
            box-shadow: 0 0 0 3px rgba(255, 105, 180, 0.2);
        }
        .item-card img {
            width: 100%;
            height: 70px;
            object-fit: contain;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .item-placeholder {
            width: 100%;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            background: var(--gray-light);
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .item-name {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--black-soft);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .item-price, .item-type {
            display: block;
            font-size: 10px;
            color: var(--gray);
        }
        .current-media {
            margin-bottom: 15px;
            padding: 15px;
            background: var(--gray-light);
            border-radius: 8px;
        }
        .info-box-blue {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 10px;
            color: #2980b9;
            align-items: flex-start;
        }
        .info-box-blue svg { flex-shrink: 0; }
        .info-box-blue a { color: var(--pink-main); font-weight: 600; }
        .info-box-pink {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: rgba(255, 105, 180, 0.1);
            border-radius: 10px;
            color: var(--pink-dark);
            align-items: flex-start;
        }
        .info-box-pink svg { flex-shrink: 0; color: var(--pink-main); }
        .info-box-pink p { margin: 0 0 5px; }
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            font-weight: 500;
        }
        .alert-success {
            background: rgba(61, 255, 192, 0.15);
            color: var(--mint-dark);
            border-left: 4px solid var(--mint-main);
        }
        .alert-error {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
            border-left: 4px solid var(--pink-main);
        }
        /* Blog article status */
        .item-draft {
            opacity: 0.7;
            border-style: dashed;
        }
        .status-published {
            color: var(--mint-dark);
            font-weight: 600;
        }
        .status-draft {
            color: var(--gray);
        }
        /* Multi-media gallery - Nouvelle UI */
        .media-gallery-container {
            margin-top: 10px;
        }
        .media-gallery-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 15px;
            background: var(--gray-light);
            border-radius: var(--radius-md);
            min-height: 110px;
        }
        .media-gallery-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            cursor: grab;
            background: white;
        }
        .media-gallery-item:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .media-gallery-item.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }
        .media-gallery-item.drag-over {
            border: 2px dashed var(--pink-main);
        }
        .media-gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-delete-btn {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(255, 105, 180, 0.9);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: all 0.2s ease;
            z-index: 2;
        }
        .media-gallery-item:hover .media-delete-btn {
            opacity: 1;
        }
        .media-delete-btn:hover {
            background: var(--pink-dark);
            transform: scale(1.1);
        }
        .media-drag-handle {
            position: absolute;
            bottom: 4px;
            left: 4px;
            width: 20px;
            height: 20px;
            background: rgba(255,255,255,0.9);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 2;
        }
        .media-gallery-item:hover .media-drag-handle {
            opacity: 1;
        }
        .media-add-zone {
            width: 100px;
            height: 100px;
        }
        .media-add-btn {
            width: 100%;
            height: 100%;
            border: 2px dashed var(--gray);
            border-radius: 10px;
            background: white;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            color: var(--gray);
            transition: all 0.2s ease;
        }
        .media-add-btn:hover {
            border-color: var(--pink-main);
            color: var(--pink-main);
            background: rgba(255, 105, 180, 0.05);
        }
        .media-add-btn span {
            font-size: 11px;
            font-weight: 600;
        }
        .media-pending-uploads {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .media-pending-item {
            position: relative;
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid var(--mint-main);
        }
        .media-pending-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-pending-item .pending-badge {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--mint-main);
            color: var(--black);
            font-size: 9px;
            font-weight: 600;
            text-align: center;
            padding: 2px;
        }
        .media-pending-item .pending-remove {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: rgba(255, 105, 180, 0.9);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            line-height: 1;
        }
    </style>

    <script>
    function updateFormFields() {
        const type = document.getElementById('type').value;
        const itemsCard = document.getElementById('itemsCard');
        const productsSelection = document.getElementById('productsSelection');
        const packsSelection = document.getElementById('packsSelection');
        const blogSelection = document.getElementById('blogSelection');
        const contentField = document.querySelector('.content-field');
        const ctaFields = document.querySelector('.cta-fields');
        const mediaField = document.querySelector('.media-field');
        const multiMediaField = document.querySelector('.multi-media-field');
        const itemsCardTitle = document.getElementById('itemsCardTitle');

        // Reset
        itemsCard.style.display = 'none';
        productsSelection.style.display = 'none';
        packsSelection.style.display = 'none';
        blogSelection.style.display = 'none';
        contentField.style.display = 'none';
        multiMediaField.style.display = 'none';
        document.getElementById('newsletterInfo').style.display = 'none';

        switch (type) {
            case 'hero':
                ctaFields.style.display = 'grid';
                mediaField.style.display = 'block';
                break;

            case 'featured_products':
                itemsCard.style.display = 'block';
                productsSelection.style.display = 'block';
                itemsCardTitle.textContent = 'Sélection des produits';
                ctaFields.style.display = 'none';
                mediaField.style.display = 'none';
                break;

            case 'featured_packs':
                itemsCard.style.display = 'block';
                packsSelection.style.display = 'block';
                itemsCardTitle.textContent = 'Sélection des packs';
                ctaFields.style.display = 'none';
                mediaField.style.display = 'none';
                break;

            case 'content_block':
                contentField.style.display = 'block';
                ctaFields.style.display = 'grid';
                mediaField.style.display = 'block';
                multiMediaField.style.display = 'block';
                break;

            case 'blog_slider':
                itemsCard.style.display = 'block';
                blogSelection.style.display = 'block';
                itemsCardTitle.textContent = 'Articles de blog';
                ctaFields.style.display = 'none';
                mediaField.style.display = 'none';
                break;

            case 'newsletter':
                // Newsletter: titre + sous-titre + bouton CTA + image de fond
                ctaFields.style.display = 'grid';
                mediaField.style.display = 'block';
                // Afficher info newsletter
                itemsCard.style.display = 'block';
                itemsCardTitle.textContent = 'Informations';
                document.getElementById('newsletterInfo').style.display = 'block';
                break;
        }

        updateItemsCount();
    }

    function updateItemsCount() {
        const type = document.getElementById('type').value;
        const itemsCountBadge = document.getElementById('itemsCount');
        let count = 0;

        if (type === 'featured_products') {
            count = document.querySelectorAll('#productsSelection input:checked').length;
            itemsCountBadge.style.display = 'inline-flex';
            itemsCountBadge.textContent = count + ' sélectionné(s)';
        } else if (type === 'featured_packs') {
            count = document.querySelectorAll('#packsSelection input:checked').length;
            itemsCountBadge.style.display = 'inline-flex';
            itemsCountBadge.textContent = count + ' sélectionné(s)';
        } else if (type === 'blog_slider') {
            count = document.querySelectorAll('#blogSelection input:checked').length;
            itemsCountBadge.style.display = 'inline-flex';
            itemsCountBadge.textContent = count + ' sélectionné(s)';
        } else {
            // Masquer le badge pour newsletter
            itemsCountBadge.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateFormFields();

        document.querySelectorAll('.item-checkbox input').forEach(cb => {
            cb.addEventListener('change', updateItemsCount);
        });

        // Initialiser la galerie de médias
        initMediaGallery();
    });

    // ========== GESTION GALERIE MÉDIAS ==========

    let pendingFiles = [];
    let draggedItem = null;

    function initMediaGallery() {
        const fileInput = document.getElementById('additionalMediaInput');
        const grid = document.getElementById('mediaGalleryGrid');

        if (!fileInput || !grid) return;

        // Écouter les ajouts de fichiers
        fileInput.addEventListener('change', handleFileSelect);

        // Drag & drop pour réordonner
        initDragAndDrop();
    }

    function handleFileSelect(e) {
        const files = Array.from(e.target.files);
        const pendingContainer = document.getElementById('mediaPendingUploads');
        const grid = document.getElementById('mediaGalleryGrid');
        const existingCount = grid.querySelectorAll('.media-gallery-item').length;
        const maxImages = 10;

        files.forEach((file, idx) => {
            if (existingCount + pendingFiles.length + idx >= maxImages) {
                alert('Maximum ' + maxImages + ' images autorisées.');
                return;
            }

            if (!file.type.startsWith('image/')) {
                alert('Seules les images sont autorisées.');
                return;
            }

            // Créer preview
            const reader = new FileReader();
            reader.onload = function(ev) {
                const pendingItem = document.createElement('div');
                pendingItem.className = 'media-pending-item';
                pendingItem.innerHTML = `
                    <img src="${ev.target.result}" alt="Preview">
                    <div class="pending-badge">En attente</div>
                    <button type="button" class="pending-remove" onclick="removePendingFile(this, ${pendingFiles.length})">×</button>
                `;
                pendingContainer.appendChild(pendingItem);
            };
            reader.readAsDataURL(file);

            pendingFiles.push(file);
        });

        // Recréer l'input file pour permettre plusieurs sélections successives
        updateFileInput();

        // Réinitialiser l'input
        e.target.value = '';
    }

    function removePendingFile(btn, index) {
        // Supprimer visuellement
        btn.closest('.media-pending-item').remove();
        // Marquer comme null dans le tableau (on ne peut pas changer l'ordre facilement)
        pendingFiles[index] = null;
        updateFileInput();
    }

    function updateFileInput() {
        // Recréer un DataTransfer avec les fichiers valides
        const validFiles = pendingFiles.filter(f => f !== null);

        // Créer un nouvel input file pour remplacer
        const oldInput = document.getElementById('additionalMediaInput');
        const newInput = oldInput.cloneNode(true);
        newInput.addEventListener('change', handleFileSelect);

        // Pour soumettre les fichiers, on utilise des inputs cachés
        const pendingInputsContainer = document.getElementById('mediaPendingUploads');
        pendingInputsContainer.querySelectorAll('input[type="file"]').forEach(i => i.remove());

        // Créer un nouvel input avec les fichiers
        if (validFiles.length > 0) {
            const dt = new DataTransfer();
            validFiles.forEach(f => dt.items.add(f));
            newInput.files = dt.files;
        }

        oldInput.parentNode.replaceChild(newInput, oldInput);
    }

    function removeMediaItem(btn) {
        const item = btn.closest('.media-gallery-item');
        item.style.transform = 'scale(0.8)';
        item.style.opacity = '0';
        setTimeout(() => {
            item.remove();
            updateMediaOrder();
        }, 200);
    }

    // ========== DRAG & DROP ==========

    function initDragAndDrop() {
        const grid = document.getElementById('mediaGalleryGrid');
        if (!grid) return;

        grid.addEventListener('dragstart', handleDragStart);
        grid.addEventListener('dragend', handleDragEnd);
        grid.addEventListener('dragover', handleDragOver);
        grid.addEventListener('drop', handleDrop);
        grid.addEventListener('dragleave', handleDragLeave);
    }

    function handleDragStart(e) {
        if (!e.target.classList.contains('media-gallery-item')) return;
        draggedItem = e.target;
        e.target.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    }

    function handleDragEnd(e) {
        if (draggedItem) {
            draggedItem.classList.remove('dragging');
            draggedItem = null;
        }
        document.querySelectorAll('.media-gallery-item').forEach(item => {
            item.classList.remove('drag-over');
        });
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const target = e.target.closest('.media-gallery-item');
        if (target && target !== draggedItem && !target.classList.contains('media-add-zone')) {
            document.querySelectorAll('.media-gallery-item').forEach(item => {
                item.classList.remove('drag-over');
            });
            target.classList.add('drag-over');
        }
    }

    function handleDragLeave(e) {
        const target = e.target.closest('.media-gallery-item');
        if (target) {
            target.classList.remove('drag-over');
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        const target = e.target.closest('.media-gallery-item');

        if (target && draggedItem && target !== draggedItem) {
            const grid = document.getElementById('mediaGalleryGrid');
            const items = Array.from(grid.querySelectorAll('.media-gallery-item'));
            const draggedIndex = items.indexOf(draggedItem);
            const targetIndex = items.indexOf(target);

            if (draggedIndex < targetIndex) {
                target.after(draggedItem);
            } else {
                target.before(draggedItem);
            }

            updateMediaOrder();
        }

        document.querySelectorAll('.media-gallery-item').forEach(item => {
            item.classList.remove('drag-over');
        });
    }

    function updateMediaOrder() {
        const grid = document.getElementById('mediaGalleryGrid');
        const items = grid.querySelectorAll('.media-gallery-item');

        items.forEach((item, idx) => {
            item.dataset.index = idx;
            const input = item.querySelector('input[name="existing_media_urls[]"]');
            if (input) {
                // L'ordre des inputs détermine l'ordre final
            }
        });
    }
    </script>
</body>
</html>
