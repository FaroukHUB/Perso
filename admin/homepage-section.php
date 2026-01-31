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
require_once __DIR__ . '/../app/models/Category.php';

Auth::requireAdmin();

$sectionModel = new HomepageSection();
$productModel = new Product();
$packModel = new Pack();
$blogModel = new BlogPost();
$categoryModel = new Category();

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
        } elseif ($data['type'] === 'featured_category' && !empty($_POST['category_id'])) {
            // Stocker l'ID de la catégorie dans config
            $data['config']['category_id'] = (int) $_POST['category_id'];
            $data['config']['products_limit'] = (int) ($_POST['products_limit'] ?? 8);
        }
        $data['items'] = $items;

        // Config spécifique Hero
        if ($data['type'] === 'hero') {
            $data['config']['badge'] = trim($_POST['hero_badge'] ?? '');
            $data['config']['highlight'] = trim($_POST['hero_highlight'] ?? '');
            $data['config']['cta2_text'] = trim($_POST['hero_cta2_text'] ?? '');
            $data['config']['cta2_url'] = trim($_POST['hero_cta2_url'] ?? '');
            // Ordre des éléments
            if (!empty($_POST['hero_elements_order']) && is_array($_POST['hero_elements_order'])) {
                $validElements = ['badge', 'title', 'subtitle', 'buttons'];
                $order = array_filter($_POST['hero_elements_order'], function($el) use ($validElements) {
                    return in_array($el, $validElements);
                });
                $data['config']['elements_order'] = array_values($order);
            }
        }

        // Style visuel de la section
        $data['config']['style'] = [
            'background_color' => !empty($_POST['style_bg_color']) ? $_POST['style_bg_color'] : null,
            'text_color' => !empty($_POST['style_text_color']) ? $_POST['style_text_color'] : null,
            'padding_y' => $_POST['style_padding_y'] ?? 'medium',
        ];

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

// Charger produits, packs, articles et catégories pour la sélection
$products = $productModel->findActive();
$packs = $packModel->findActive();
$categories = $categoryModel->findAllActive();
$blogPosts = $blogModel->findAll(); // Tous les articles (brouillon + publiés)

// IDs des items sélectionnés
$selectedProductIds = [];
$selectedPackIds = [];
$selectedBlogIds = [];
$selectedCategoryId = 0;
$productsLimit = 8;

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

// Récupérer la catégorie sélectionnée (pour featured_category)
if ($section && !empty($section['config']['category_id'])) {
    $selectedCategoryId = (int) $section['config']['category_id'];
}
if ($section && !empty($section['config']['products_limit'])) {
    $productsLimit = (int) $section['config']['products_limit'];
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

        <main class="main-content main-content-with-preview">
            <div class="page-header">
                <h1 class="page-title">
                    <?php if ($isEdit): ?>
                        Modifier la <span>section</span>
                    <?php else: ?>
                        Nouvelle <span>section</span>
                    <?php endif; ?>
                </h1>
                <div class="page-header-actions">
                    <button type="button" class="btn btn-secondary btn-toggle-preview" id="togglePreviewBtn" title="Afficher/Masquer l'aperçu">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Aperçu
                    </button>
                    <a href="/admin/homepage.php" class="btn btn-secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/>
                            <polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Retour
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Split-screen wrapper -->
            <div class="split-screen-wrapper" id="splitScreenWrapper">
                <!-- Panneau Formulaire -->
                <div class="split-form-panel" id="formPanel">
            <form method="post" enctype="multipart/form-data" id="sectionForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="media_url" id="currentMediaUrl" value="<?= h($section['media_url'] ?? '') ?>">

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

                            <!-- Champs spécifiques Hero -->
                            <div class="hero-fields" style="display: none;">
                                <div class="form-group">
                                    <label for="hero_badge">Badge (optionnel)</label>
                                    <input type="text" name="hero_badge" id="hero_badge"
                                           value="<?= h($section['config']['badge'] ?? '') ?>" placeholder="✨ Nouveau — Personnalisation en ligne">
                                    <small class="form-hint">Petit texte au-dessus du titre</small>
                                </div>
                                <div class="form-group">
                                    <label for="hero_highlight">Texte mis en avant (optionnel)</label>
                                    <input type="text" name="hero_highlight" id="hero_highlight"
                                           value="<?= h($section['config']['highlight'] ?? '') ?>" placeholder="pour toute la famille">
                                    <small class="form-hint">Texte coloré à la fin du titre</small>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="hero_cta2_text">Bouton secondaire (optionnel)</label>
                                        <input type="text" name="hero_cta2_text" id="hero_cta2_text"
                                               value="<?= h($section['config']['cta2_text'] ?? '') ?>" placeholder="Voir les catégories">
                                    </div>
                                    <div class="form-group">
                                        <label for="hero_cta2_url">URL bouton secondaire</label>
                                        <input type="text" name="hero_cta2_url" id="hero_cta2_url"
                                               value="<?= h($section['config']['cta2_url'] ?? '') ?>" placeholder="#categories">
                                    </div>
                                </div>

                                <!-- Ordre des éléments du Hero -->
                                <div class="form-group">
                                    <label>Ordre des éléments</label>
                                    <small class="form-hint" style="margin-bottom: 10px; display: block;">Utilisez les flèches pour réorganiser l'affichage des éléments</small>
                                    <?php
                                    $defaultOrder = ['badge', 'title', 'subtitle', 'buttons'];
                                    $heroOrder = $section['config']['elements_order'] ?? $defaultOrder;
                                    // S'assurer que tous les éléments sont présents
                                    foreach ($defaultOrder as $el) {
                                        if (!in_array($el, $heroOrder)) {
                                            $heroOrder[] = $el;
                                        }
                                    }
                                    $elementLabels = [
                                        'badge' => 'Badge',
                                        'title' => 'Titre + Highlight',
                                        'subtitle' => 'Sous-titre',
                                        'buttons' => 'Boutons CTA'
                                    ];
                                    ?>
                                    <div class="hero-elements-order" id="heroElementsOrder">
                                        <?php foreach ($heroOrder as $idx => $element): ?>
                                            <div class="hero-order-item" data-element="<?= h($element) ?>">
                                                <span class="hero-order-position"><?= $idx + 1 ?></span>
                                                <span class="hero-order-label"><?= h($elementLabels[$element] ?? $element) ?></span>
                                                <input type="hidden" name="hero_elements_order[]" value="<?= h($element) ?>">
                                                <div class="hero-order-arrows">
                                                    <button type="button" class="hero-arrow-btn" onclick="moveHeroElement(this, -1)" title="Monter">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="18 15 12 9 6 15"/>
                                                        </svg>
                                                    </button>
                                                    <button type="button" class="hero-arrow-btn" onclick="moveHeroElement(this, 1)" title="Descendre">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="6 9 12 15 18 9"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
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

                    <!-- Style visuel de la section -->
                    <?php
                    $sectionStyle = $section['config']['style'] ?? [];
                    $styleBgColor = $sectionStyle['background_color'] ?? '#FFFFFF';
                    $styleTextColor = $sectionStyle['text_color'] ?? '';
                    $stylePaddingY = $sectionStyle['padding_y'] ?? 'medium';
                    ?>
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">Style visuel</h3>
                        </div>
                        <div class="data-card-body">
                            <div class="style-fields-row">
                                <div class="form-group">
                                    <label for="style_bg_color">Couleur de fond</label>
                                    <div class="color-input-row">
                                        <input type="color" name="style_bg_color" id="style_bg_color"
                                               value="<?= h($styleBgColor) ?>">
                                        <input type="text" class="color-hex" id="style_bg_color_hex"
                                               value="<?= h($styleBgColor) ?>" placeholder="#FFFFFF">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="style_text_color">Couleur du texte (optionnel)</label>
                                    <div class="color-input-row">
                                        <input type="color" name="style_text_color" id="style_text_color"
                                               value="<?= h($styleTextColor ?: '#1F2937') ?>">
                                        <input type="text" class="color-hex" id="style_text_color_hex"
                                               value="<?= h($styleTextColor) ?>" placeholder="Auto">
                                        <button type="button" class="btn-clear-color" onclick="clearTextColor()" title="Réinitialiser">×</button>
                                    </div>
                                    <small class="form-hint">Laissez vide pour utiliser la couleur par défaut</small>
                                </div>
                                <div class="form-group">
                                    <label for="style_padding_y">Espacement vertical</label>
                                    <select name="style_padding_y" id="style_padding_y">
                                        <option value="none" <?= $stylePaddingY === 'none' ? 'selected' : '' ?>>Aucun</option>
                                        <option value="small" <?= $stylePaddingY === 'small' ? 'selected' : '' ?>>Petit (2rem)</option>
                                        <option value="medium" <?= $stylePaddingY === 'medium' ? 'selected' : '' ?>>Moyen (4rem)</option>
                                        <option value="large" <?= $stylePaddingY === 'large' ? 'selected' : '' ?>>Grand (6rem)</option>
                                        <option value="xlarge" <?= $stylePaddingY === 'xlarge' ? 'selected' : '' ?>>Très grand (8rem)</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Mini preview -->
                            <div class="section-style-preview" id="sectionStylePreview"
                                 style="background-color: <?= h($styleBgColor) ?>;">
                                <span style="<?= $styleTextColor ? 'color:' . h($styleTextColor) : '' ?>">
                                    Aperçu du style de la section
                                </span>
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

                            <!-- Sélection catégorie -->
                            <div id="categorySelection" style="display: none;">
                                <p class="text-muted" style="margin-bottom: 15px;">
                                    Sélectionnez une catégorie pour afficher ses produits.
                                    <a href="/admin/categories.php" style="color: var(--pink-main);">Gérer les catégories</a>
                                </p>
                                <?php if (empty($categories)): ?>
                                    <div class="info-box-blue">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                                        </svg>
                                        <p>Aucune catégorie. <a href="/admin/category-form.php">Créer une catégorie</a>.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="form-group">
                                        <label for="category_id">Catégorie *</label>
                                        <select name="category_id" id="category_id" class="form-select">
                                            <option value="">-- Sélectionner une catégorie --</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>" <?= $selectedCategoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                                    <?= h($cat['name']) ?>
                                                    <?php if (!empty($cat['description'])): ?>
                                                        (<?= h(substr($cat['description'], 0, 30)) ?>...)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="products_limit">Nombre de produits à afficher</label>
                                        <select name="products_limit" id="products_limit" class="form-select">
                                            <?php foreach ([4, 6, 8, 10, 12] as $limit): ?>
                                                <option value="<?= $limit ?>" <?= $productsLimit === $limit ? 'selected' : '' ?>>
                                                    <?= $limit ?> produits
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="info-box-mint" style="margin-top: 15px;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        <div>
                                            <p><strong>Catégorie à la une</strong></p>
                                            <p style="font-size: 13px;">Les produits de cette catégorie seront affichés automatiquement. Vous pouvez personnaliser le titre et le sous-titre de la section.</p>
                                        </div>
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
                </div><!-- /split-form-panel -->

                <!-- Panneau Prévisualisation -->
                <div class="split-preview-panel" id="previewPanel">
                    <div class="preview-header">
                        <div class="preview-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            Aperçu en direct
                        </div>
                        <div class="preview-controls">
                            <button type="button" class="preview-device-btn active" data-device="desktop" title="Desktop">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                    <line x1="8" y1="21" x2="16" y2="21"/>
                                    <line x1="12" y1="17" x2="12" y2="21"/>
                                </svg>
                            </button>
                            <button type="button" class="preview-device-btn" data-device="mobile" title="Mobile">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                                    <line x1="12" y1="18" x2="12.01" y2="18"/>
                                </svg>
                            </button>
                            <button type="button" class="preview-refresh-btn" id="refreshPreviewBtn" title="Rafraîchir">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 4 23 10 17 10"/>
                                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="preview-status" id="previewStatus">
                        <span class="preview-status-text">Prêt</span>
                    </div>
                    <div class="preview-container" id="previewContainer">
                        <div class="preview-frame" id="previewFrame">
                            <div class="preview-loading" id="previewLoading">
                                <div class="preview-spinner"></div>
                                <span>Chargement de l'aperçu...</span>
                            </div>
                            <div class="preview-content" id="previewContent">
                                <!-- Le contenu de la preview sera injecté ici -->
                            </div>
                        </div>
                    </div>
                </div><!-- /split-preview-panel -->
            </div><!-- /split-screen-wrapper -->
        </main>
    </div>

    <style>
        /* ===== SPLIT-SCREEN LAYOUT ===== */
        .main-content-with-preview {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .main-content-with-preview .page-header {
            flex-shrink: 0;
        }
        .main-content-with-preview .alert {
            flex-shrink: 0;
        }
        .page-header-actions {
            display: flex;
            gap: 10px;
        }
        .btn-toggle-preview {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-toggle-preview.active {
            background: var(--pink-main);
            color: white;
            border-color: var(--pink-main);
        }
        .split-screen-wrapper {
            display: flex;
            flex: 1;
            gap: 0;
            overflow: hidden;
            min-height: 0;
        }
        .split-form-panel {
            flex: 1;
            overflow-y: auto;
            padding-right: 20px;
            padding-bottom: 40px;
        }
        .split-preview-panel {
            width: 50%;
            max-width: 700px;
            min-width: 400px;
            background: #1a1a2e;
            border-radius: var(--radius-lg) 0 0 var(--radius-lg);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: -10px 0 40px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .split-preview-panel.hidden {
            transform: translateX(100%);
            opacity: 0;
            width: 0;
            min-width: 0;
            pointer-events: none;
        }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .preview-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        .preview-title svg {
            color: var(--mint-main);
        }
        .preview-controls {
            display: flex;
            gap: 6px;
        }
        .preview-device-btn,
        .preview-refresh-btn {
            width: 36px;
            height: 36px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-sm);
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .preview-device-btn:hover,
        .preview-refresh-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .preview-device-btn.active {
            background: var(--pink-main);
            border-color: var(--pink-main);
            color: white;
        }
        .preview-refresh-btn:active svg {
            animation: spin 0.5s linear;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .preview-status {
            padding: 8px 20px;
            background: rgba(0, 0, 0, 0.2);
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .preview-status.loading {
            color: var(--mint-main);
        }
        .preview-status.loading::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--mint-main);
            animation: pulse-dot 1s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        .preview-container {
            flex: 1;
            overflow: hidden;
            display: flex;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #0d0d0d 0%, #1a1a2e 100%);
        }
        .preview-frame {
            width: 100%;
            height: 100%;
            overflow-y: auto;
            border-radius: var(--radius-md);
            position: relative;
            transition: max-width 0.3s ease;
        }
        .preview-frame.mobile {
            max-width: 375px;
            border: 8px solid #333;
            border-radius: 24px;
            background: white;
        }
        .preview-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            display: none;
        }
        .preview-loading.show {
            display: flex;
        }
        .preview-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--pink-main);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .preview-content {
            min-height: 100%;
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        .preview-content:empty::after {
            content: 'Modifiez le formulaire pour voir l\\'aperçu';
            display: flex;
            align-items: center;
            justify-content: center;
            height: 300px;
            color: #999;
            font-size: 14px;
            text-align: center;
            padding: 20px;
        }
        /* Responsive: cacher le preview sur petit écran */
        @media (max-width: 1200px) {
            .split-preview-panel {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                width: 50%;
                max-width: none;
                min-width: 0;
                border-radius: var(--radius-lg) 0 0 var(--radius-lg);
                z-index: 1000;
            }
            .split-preview-panel.hidden {
                transform: translateX(100%);
            }
            .split-form-panel {
                width: 100%;
                padding-right: 0;
            }
        }
        @media (max-width: 768px) {
            .split-preview-panel {
                width: 100%;
            }
        }

        /* ===== FORM STYLES ===== */
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
        .info-box-mint {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: rgba(61, 255, 192, 0.1);
            border-radius: 10px;
            color: var(--mint-dark);
            align-items: flex-start;
        }
        .info-box-mint svg { flex-shrink: 0; color: var(--mint-main); }
        .info-box-mint p { margin: 0 0 5px; }
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
        /* Style visuel section */
        .style-fields-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 900px) {
            .style-fields-row { grid-template-columns: 1fr; }
        }
        .color-input-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .color-input-row input[type="color"] {
            width: 44px;
            height: 44px;
            padding: 0;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-md);
            cursor: pointer;
        }
        .color-input-row .color-hex {
            flex: 1;
            width: auto;
            font-family: monospace;
            text-transform: uppercase;
        }
        .btn-clear-color {
            width: 36px;
            height: 36px;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-md);
            background: white;
            cursor: pointer;
            font-size: 1.2rem;
            color: var(--gray);
        }
        .btn-clear-color:hover {
            background: var(--gray-light);
            color: var(--pink-main);
        }
        .section-style-preview {
            margin-top: 20px;
            padding: 30px;
            border-radius: var(--radius-md);
            text-align: center;
            font-weight: 500;
            border: 1px solid var(--gray-light);
        }
        /* Hero elements order */
        .hero-elements-order {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .hero-order-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: var(--gray-light);
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
        }
        .hero-order-item:hover {
            background: rgba(255, 105, 180, 0.08);
        }
        .hero-order-position {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--pink-main);
            color: white;
            font-size: 12px;
            font-weight: 700;
            border-radius: 50%;
        }
        .hero-order-label {
            flex: 1;
            font-weight: 500;
            color: var(--black-soft);
        }
        .hero-order-arrows {
            display: flex;
            gap: 4px;
        }
        .hero-arrow-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-sm);
            cursor: pointer;
            color: var(--gray);
            transition: all 0.15s ease;
        }
        .hero-arrow-btn:hover {
            border-color: var(--pink-main);
            color: var(--pink-main);
            background: rgba(255, 105, 180, 0.05);
        }
        .hero-arrow-btn:active {
            transform: scale(0.95);
        }
        .hero-order-item.moving {
            background: rgba(255, 105, 180, 0.15);
            transform: scale(1.02);
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
        const heroFields = document.querySelector('.hero-fields');
        const itemsCardTitle = document.getElementById('itemsCardTitle');

        // Reset
        itemsCard.style.display = 'none';
        productsSelection.style.display = 'none';
        packsSelection.style.display = 'none';
        blogSelection.style.display = 'none';
        contentField.style.display = 'none';
        multiMediaField.style.display = 'none';
        heroFields.style.display = 'none';
        document.getElementById('newsletterInfo').style.display = 'none';
        document.getElementById('categorySelection').style.display = 'none';

        switch (type) {
            case 'hero':
                ctaFields.style.display = 'grid';
                mediaField.style.display = 'block';
                heroFields.style.display = 'block';
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

            case 'featured_category':
                itemsCard.style.display = 'block';
                document.getElementById('categorySelection').style.display = 'block';
                itemsCardTitle.textContent = 'Catégorie';
                ctaFields.style.display = 'grid';
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

    // === Style visuel section ===
    // Sync color pickers avec hex inputs
    document.getElementById('style_bg_color').addEventListener('input', function() {
        document.getElementById('style_bg_color_hex').value = this.value.toUpperCase();
        updateStylePreview();
    });
    document.getElementById('style_bg_color_hex').addEventListener('input', function() {
        let val = this.value.trim();
        if (!val.startsWith('#')) val = '#' + val;
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            document.getElementById('style_bg_color').value = val;
            updateStylePreview();
        }
    });
    document.getElementById('style_text_color').addEventListener('input', function() {
        document.getElementById('style_text_color_hex').value = this.value.toUpperCase();
        updateStylePreview();
    });
    document.getElementById('style_text_color_hex').addEventListener('input', function() {
        let val = this.value.trim();
        if (!val.startsWith('#')) val = '#' + val;
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            document.getElementById('style_text_color').value = val;
            updateStylePreview();
        }
    });

    function clearTextColor() {
        document.getElementById('style_text_color_hex').value = '';
        updateStylePreview();
    }

    function updateStylePreview() {
        const preview = document.getElementById('sectionStylePreview');
        const bgColor = document.getElementById('style_bg_color').value;
        const textColorHex = document.getElementById('style_text_color_hex').value;

        preview.style.backgroundColor = bgColor;
        const textSpan = preview.querySelector('span');
        textSpan.style.color = textColorHex || '';
    }

    // Hero elements order
    function moveHeroElement(btn, direction) {
        const item = btn.closest('.hero-order-item');
        const container = document.getElementById('heroElementsOrder');
        const items = Array.from(container.querySelectorAll('.hero-order-item'));
        const currentIndex = items.indexOf(item);
        const newIndex = currentIndex + direction;

        // Check bounds
        if (newIndex < 0 || newIndex >= items.length) return;

        // Add animation
        item.classList.add('moving');
        setTimeout(() => item.classList.remove('moving'), 200);

        // Move the element
        if (direction === -1) {
            // Move up
            container.insertBefore(item, items[newIndex]);
        } else {
            // Move down
            const nextItem = items[newIndex + 1];
            if (nextItem) {
                container.insertBefore(item, nextItem.nextSibling);
            } else {
                container.appendChild(item);
            }
        }

        // Update position numbers
        updateHeroOrderPositions();
    }

    function updateHeroOrderPositions() {
        const container = document.getElementById('heroElementsOrder');
        if (!container) return;
        const items = container.querySelectorAll('.hero-order-item');
        items.forEach((item, idx) => {
            const posSpan = item.querySelector('.hero-order-position');
            if (posSpan) posSpan.textContent = idx + 1;
        });
    }

    // ========== PRÉVISUALISATION EN DIRECT ==========

    const LivePreview = {
        debounceTimer: null,
        debounceDelay: 500,
        isLoading: false,
        previewEnabled: true,
        tempMediaUrl: null,

        init() {
            this.bindEvents();
            this.loadInitialPreview();
            this.setupToggleButton();
        },

        bindEvents() {
            const form = document.getElementById('sectionForm');
            if (!form) return;

            // Écouter tous les changements de champs
            const watchedInputs = form.querySelectorAll('input, select, textarea');
            watchedInputs.forEach(input => {
                const eventType = input.type === 'checkbox' || input.type === 'radio' ? 'change' : 'input';
                input.addEventListener(eventType, () => this.scheduleUpdate());
            });

            // Écouter les changements de type de section
            document.getElementById('type').addEventListener('change', () => {
                this.scheduleUpdate(100); // Mise à jour rapide pour le changement de type
            });

            // Écouter le réordonnancement des éléments hero
            const heroContainer = document.getElementById('heroElementsOrder');
            if (heroContainer) {
                const observer = new MutationObserver(() => this.scheduleUpdate(100));
                observer.observe(heroContainer, { childList: true, subtree: true });
            }

            // Bouton refresh manuel
            document.getElementById('refreshPreviewBtn').addEventListener('click', () => {
                this.updatePreview();
            });

            // Device toggle
            document.querySelectorAll('.preview-device-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.preview-device-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    const device = btn.dataset.device;
                    const frame = document.getElementById('previewFrame');
                    frame.classList.toggle('mobile', device === 'mobile');
                });
            });

            // Upload de fichier média - aperçu local immédiat
            document.getElementById('media_file').addEventListener('change', (e) => {
                this.handleMediaUpload(e);
            });
        },

        setupToggleButton() {
            const btn = document.getElementById('togglePreviewBtn');
            const panel = document.getElementById('previewPanel');

            // Charger la préférence sauvegardée
            const savedState = localStorage.getItem('previewPanelVisible');
            if (savedState === 'false') {
                panel.classList.add('hidden');
            } else {
                btn.classList.add('active');
            }

            btn.addEventListener('click', () => {
                panel.classList.toggle('hidden');
                btn.classList.toggle('active');
                localStorage.setItem('previewPanelVisible', !panel.classList.contains('hidden'));
            });
        },

        scheduleUpdate(delay = this.debounceDelay) {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.updatePreview(), delay);
        },

        async handleMediaUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Créer un aperçu local avec FileReader
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    // On ne peut pas utiliser directement le data URL dans le backend
                    // Donc on affiche juste un message et on garde l'ancienne image pour le preview
                    this.setStatus('Nouvelle image sélectionnée - Enregistrez pour voir le changement', 'loading');
                };
                reader.readAsDataURL(file);
            }
        },

        async updatePreview() {
            if (this.isLoading || !this.previewEnabled) return;

            const panel = document.getElementById('previewPanel');
            if (panel.classList.contains('hidden')) return;

            this.isLoading = true;
            this.showLoading(true);
            this.setStatus('Mise à jour...', 'loading');

            try {
                const formData = this.collectFormData();
                const response = await fetch('/admin/api/homepage-section/preview.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Erreur réseau');

                const data = await response.json();

                if (data.success) {
                    this.renderPreview(data.html);
                    this.setStatus('Aperçu mis à jour');
                } else {
                    throw new Error(data.error || 'Erreur inconnue');
                }
            } catch (error) {
                console.error('Preview error:', error);
                this.setStatus('Erreur de chargement', 'error');
            } finally {
                this.isLoading = false;
                this.showLoading(false);
            }
        },

        collectFormData() {
            const form = document.getElementById('sectionForm');
            const formData = new FormData();

            // Champs texte simples
            const textFields = ['type', 'title', 'subtitle', 'content', 'cta_text', 'cta_url',
                               'hero_badge', 'hero_highlight', 'hero_cta2_text', 'hero_cta2_url',
                               'style_bg_color', 'style_text_color', 'style_padding_y',
                               'category_id', 'products_limit'];

            textFields.forEach(name => {
                const input = form.querySelector(`[name="${name}"]`);
                if (input) {
                    formData.append(name, input.value);
                }
            });

            // Media URL existant
            const currentMediaUrl = document.getElementById('currentMediaUrl');
            if (currentMediaUrl && currentMediaUrl.value) {
                formData.append('media_url', currentMediaUrl.value);
                formData.append('media_type', 'image'); // On assume image pour le preview
            }

            // Ordre des éléments hero
            const heroOrder = form.querySelectorAll('[name="hero_elements_order[]"]');
            heroOrder.forEach(input => {
                formData.append('hero_elements_order[]', input.value);
            });

            // IDs des produits sélectionnés
            const productIds = form.querySelectorAll('#productsSelection input:checked');
            productIds.forEach(input => {
                formData.append('product_ids[]', input.value);
            });

            // IDs des packs sélectionnés
            const packIds = form.querySelectorAll('#packsSelection input:checked');
            packIds.forEach(input => {
                formData.append('pack_ids[]', input.value);
            });

            // IDs des articles blog sélectionnés
            const blogIds = form.querySelectorAll('#blogSelection input:checked');
            blogIds.forEach(input => {
                formData.append('blog_ids[]', input.value);
            });

            // Médias additionnels (content_block)
            const existingMediaUrls = form.querySelectorAll('[name="existing_media_urls[]"]');
            existingMediaUrls.forEach(input => {
                formData.append('existing_media_urls[]', input.value);
            });

            return formData;
        },

        renderPreview(html) {
            const container = document.getElementById('previewContent');
            container.innerHTML = html;

            // Injecter les styles nécessaires
            this.injectPreviewStyles();
        },

        injectPreviewStyles() {
            const container = document.getElementById('previewContent');
            const existingStyle = container.querySelector('#previewInlineStyles');
            if (existingStyle) existingStyle.remove();

            const style = document.createElement('style');
            style.id = 'previewInlineStyles';
            style.textContent = `
                /* Reset pour le preview */
                .preview-content * { box-sizing: border-box; }
                .preview-content { font-family: 'Inter', sans-serif; }

                /* Variables CSS */
                .preview-content {
                    --pink-main: #FF69B4;
                    --pink-dark: #DB2777;
                    --mint-main: #3DFFC0;
                    --mint-dark: #059669;
                    --black: #0D0D0D;
                    --black-soft: #1E1E1E;
                    --white: #FFFFFF;
                    --gray: #6B7280;
                    --gray-light: #F3F4F6;
                    --gradient-hero: linear-gradient(135deg, #FF69B4 0%, #3DFFC0 100%);
                    --gradient-pink: linear-gradient(135deg, #FF69B4 0%, #DB2777 100%);
                    --gradient-mint: linear-gradient(135deg, #3DFFC0 0%, #10B981 100%);
                    --gradient-dark: linear-gradient(135deg, #0D0D0D 0%, #1E1E1E 100%);
                    --radius-sm: 6px;
                    --radius-md: 12px;
                    --radius-lg: 20px;
                    --radius-full: 9999px;
                }

                /* Hero */
                .preview-content .hero {
                    min-height: 400px;
                    display: flex;
                    align-items: center;
                    background: var(--gradient-dark);
                    position: relative;
                    padding: 60px 20px;
                }
                .preview-content .hero-with-bg {
                    background-size: cover;
                    background-position: center;
                }
                .preview-content .hero-with-bg::after {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(135deg, rgba(13,13,13,0.85), rgba(13,13,13,0.7));
                }
                .preview-content .hero .container {
                    position: relative;
                    z-index: 1;
                    width: 100%;
                    max-width: 800px;
                    margin: 0 auto;
                }
                .preview-content .hero-content { text-align: center; }
                .preview-content .hero-badge {
                    display: inline-flex;
                    background: rgba(255,255,255,0.1);
                    padding: 8px 20px;
                    border-radius: 9999px;
                    margin-bottom: 20px;
                    color: var(--mint-main);
                    font-size: 14px;
                    font-weight: 600;
                }
                .preview-content .hero h1 {
                    font-size: 2.5rem;
                    font-weight: 800;
                    color: white;
                    margin: 0 0 20px;
                    line-height: 1.1;
                }
                .preview-content .hero h1 span {
                    background: var(--gradient-hero);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                }
                .preview-content .hero[style*="color"] h1,
                .preview-content .hero[style*="color"] p,
                .preview-content .hero[style*="color"] .hero-badge {
                    color: inherit !important;
                }
                .preview-content .hero[style*="color"] h1 span {
                    background: none !important;
                    -webkit-text-fill-color: inherit !important;
                }
                .preview-content .hero p {
                    font-size: 1.1rem;
                    color: rgba(255,255,255,0.7);
                    margin: 0 0 30px;
                }
                .preview-content .hero-buttons {
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .preview-content .btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 14px 28px;
                    font-weight: 600;
                    border-radius: 9999px;
                    text-decoration: none;
                    font-size: 15px;
                }
                .preview-content .btn-primary {
                    background: var(--gradient-pink);
                    color: white;
                }
                .preview-content .btn-dark {
                    background: rgba(255,255,255,0.1);
                    color: white;
                    border: 1px solid rgba(255,255,255,0.2);
                }

                /* Products section */
                .preview-content .products-section {
                    padding: 60px 20px;
                    background: var(--gray-light);
                }
                .preview-content .section-header {
                    text-align: center;
                    margin-bottom: 40px;
                }
                .preview-content .section-header h2 {
                    font-size: 2rem;
                    margin: 0 0 15px;
                }
                .preview-content .section-header p {
                    color: var(--gray);
                    font-size: 1rem;
                    margin: 0;
                }
                .preview-content .products-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                    gap: 20px;
                    max-width: 1200px;
                    margin: 0 auto;
                }
                .preview-content .product-card {
                    background: white;
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                }
                .preview-content .product-image {
                    aspect-ratio: 4/3;
                    background: linear-gradient(145deg, #fafafa, #f0f0f0);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                }
                .preview-content .product-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: contain;
                    padding: 15px;
                }
                .preview-content .product-category {
                    position: absolute;
                    top: 10px;
                    left: 10px;
                }
                .preview-content .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    font-size: 11px;
                    font-weight: 600;
                    border-radius: 9999px;
                }
                .preview-content .badge-mint {
                    background: var(--gradient-mint);
                    color: var(--black);
                }
                .preview-content .product-info {
                    padding: 15px;
                }
                .preview-content .product-info h3 {
                    font-size: 1rem;
                    margin: 0 0 8px;
                }
                .preview-content .product-info p {
                    font-size: 13px;
                    color: var(--gray);
                    margin: 0 0 12px;
                }
                .preview-content .product-footer {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .preview-content .product-price {
                    font-weight: 700;
                    font-size: 1.2rem;
                    color: var(--pink-dark);
                }
                .preview-content .product-btn {
                    background: var(--gradient-mint);
                    color: var(--black);
                    padding: 8px 16px;
                    border-radius: 9999px;
                    font-size: 13px;
                    font-weight: 600;
                }

                /* Packs/Inspirations */
                .preview-content .inspirations-section {
                    padding: 60px 20px;
                    background: var(--black-soft);
                }
                .preview-content .inspirations-section .section-header h2,
                .preview-content .inspirations-section .section-header p {
                    color: white;
                }
                .preview-content .inspirations-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                    gap: 20px;
                    max-width: 1200px;
                    margin: 0 auto;
                }
                .preview-content .inspiration-card {
                    background: rgba(255,255,255,0.05);
                    border: 1px solid rgba(255,255,255,0.1);
                    border-radius: 16px;
                    overflow: hidden;
                }
                .preview-content .inspiration-image {
                    aspect-ratio: 16/10;
                    background: linear-gradient(135deg, rgba(255,105,180,0.2), rgba(61,255,192,0.1));
                    position: relative;
                }
                .preview-content .inspiration-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .preview-content .inspiration-type {
                    position: absolute;
                    top: 10px;
                    left: 10px;
                    font-size: 10px;
                    font-weight: 600;
                    text-transform: uppercase;
                    padding: 5px 10px;
                    border-radius: 9999px;
                    background: rgba(0,0,0,0.6);
                    color: white;
                }
                .preview-content .inspiration-type.type-technique { background: var(--pink-main); }
                .preview-content .inspiration-type.type-contextuel { background: var(--mint-dark); color: var(--black); }
                .preview-content .inspiration-info {
                    padding: 15px;
                }
                .preview-content .inspiration-info h3 {
                    color: white;
                    font-size: 1rem;
                    margin: 0 0 8px;
                }
                .preview-content .inspiration-info p {
                    color: rgba(255,255,255,0.6);
                    font-size: 13px;
                    margin: 0 0 12px;
                }
                .preview-content .inspiration-cta {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: var(--gradient-pink);
                    color: white;
                    padding: 8px 16px;
                    border-radius: 9999px;
                    font-size: 12px;
                    font-weight: 600;
                }

                /* Content block */
                .preview-content .content-block-section {
                    padding: 60px 20px;
                }
                .preview-content .content-block-inner {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 40px;
                    align-items: center;
                    max-width: 1000px;
                    margin: 0 auto;
                }
                .preview-content .content-block-inner.gallery-mode {
                    grid-template-columns: 1fr;
                }
                .preview-content .content-block-text h2 {
                    font-size: 1.8rem;
                    margin: 0 0 15px;
                }
                .preview-content .content-block-text p {
                    color: var(--gray);
                    margin: 0 0 20px;
                    line-height: 1.7;
                }
                .preview-content .content-block-media img,
                .preview-content .content-block-media video {
                    width: 100%;
                    border-radius: 16px;
                }
                .preview-content .content-block-gallery {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                    gap: 15px;
                }
                .preview-content .gallery-card {
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                }
                .preview-content .gallery-card img {
                    width: 100%;
                    aspect-ratio: 4/3;
                    object-fit: cover;
                }

                /* Blog */
                .preview-content .blog-section {
                    padding: 60px 20px;
                    background: var(--gray-light);
                }
                .preview-content .blog-slider {
                    display: flex;
                    gap: 20px;
                    overflow-x: auto;
                    padding-bottom: 10px;
                }
                .preview-content .blog-card {
                    min-width: 220px;
                    max-width: 220px;
                    background: white;
                    border-radius: 16px;
                    overflow: hidden;
                }
                .preview-content .blog-card-image {
                    aspect-ratio: 16/10;
                    background: linear-gradient(135deg, rgba(255,105,180,0.15), rgba(61,255,192,0.15));
                }
                .preview-content .blog-card-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .preview-content .blog-card-content {
                    padding: 15px;
                }
                .preview-content .blog-card-date {
                    font-size: 11px;
                    color: var(--pink-main);
                    font-weight: 600;
                    margin-bottom: 6px;
                }
                .preview-content .blog-card-content h3 {
                    font-size: 0.9rem;
                    margin: 0 0 6px;
                }
                .preview-content .blog-card-content p {
                    font-size: 12px;
                    color: var(--gray);
                    margin: 0;
                }

                /* Newsletter */
                .preview-content .newsletter-section {
                    position: relative;
                    padding: 60px 20px;
                    background-size: cover;
                    background-position: center;
                    background-color: var(--black-soft);
                }
                .preview-content .newsletter-overlay {
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(135deg, rgba(13,13,13,0.9), rgba(30,30,30,0.85));
                }
                .preview-content .newsletter-section .container {
                    position: relative;
                    z-index: 1;
                }
                .preview-content .newsletter-content {
                    max-width: 500px;
                    margin: 0 auto;
                    text-align: center;
                }
                .preview-content .newsletter-content h2 {
                    font-size: 2rem;
                    color: white;
                    margin: 0 0 15px;
                }
                .preview-content .newsletter-subtitle {
                    color: rgba(255,255,255,0.7);
                    margin: 0 0 30px;
                }
                .preview-content .newsletter-input-group {
                    display: flex;
                    gap: 10px;
                }
                .preview-content .newsletter-input {
                    flex: 1;
                    padding: 14px 20px;
                    border: 2px solid rgba(255,255,255,0.15);
                    border-radius: 9999px;
                    background: rgba(255,255,255,0.08);
                    color: white;
                    font-size: 14px;
                }
                .preview-content .newsletter-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 14px 24px;
                    background: var(--gradient-pink);
                    color: white;
                    border: none;
                    border-radius: 9999px;
                    font-weight: 600;
                    font-size: 14px;
                }
                .preview-content .newsletter-privacy {
                    margin-top: 20px;
                    font-size: 12px;
                    color: rgba(255,255,255,0.4);
                }

                /* Empty states */
                .preview-content .empty-products {
                    text-align: center;
                    padding: 40px 20px;
                }
                .preview-content .empty-products-icon {
                    font-size: 3rem;
                    margin-bottom: 15px;
                }
                .preview-content .empty-products h3 {
                    margin: 0 0 8px;
                }
                .preview-content .empty-products p {
                    color: var(--gray);
                    margin: 0;
                }
            `;
            container.prepend(style);
        },

        showLoading(show) {
            const loader = document.getElementById('previewLoading');
            loader.classList.toggle('show', show);
        },

        setStatus(text, type = '') {
            const status = document.getElementById('previewStatus');
            status.className = 'preview-status' + (type ? ' ' + type : '');
            status.querySelector('.preview-status-text').textContent = text;
        },

        loadInitialPreview() {
            // Charger l'aperçu initial après un court délai
            setTimeout(() => this.updatePreview(), 300);
        }
    };

    // Initialiser le preview au chargement
    document.addEventListener('DOMContentLoaded', function() {
        LivePreview.init();
    });
    </script>
</body>
</html>
