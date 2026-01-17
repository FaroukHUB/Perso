<?php
/**
 * PERSONNALY Admin - Formulaire Section Homepage
 * Création et édition de sections
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/HomepageSection.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Pack.php';

Auth::requireAdmin();

$sectionModel = new HomepageSection();
$productModel = new Product();
$packModel = new Pack();

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

        // Upload image si fournie
        if (!empty($_FILES['media_file']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../public/uploads/homepage/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];

            if (in_array($ext, $allowed)) {
                $filename = 'section_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $uploadDir . $filename)) {
                    $data['media_url'] = '/uploads/homepage/' . $filename;

                    if (in_array($ext, ['mp4', 'webm'])) {
                        $data['media_type'] = 'video';
                    } else {
                        $data['media_type'] = 'image';
                    }
                }
            }
        } elseif ($isEdit && $section['media_url']) {
            $data['media_url'] = $section['media_url'];
        }

        // Gestion des items (produits/packs)
        $items = [];
        if ($data['type'] === 'featured_products' && !empty($_POST['product_ids'])) {
            foreach ($_POST['product_ids'] as $pid) {
                $items[] = ['type' => 'product', 'id' => (int) $pid];
            }
        } elseif ($data['type'] === 'featured_packs' && !empty($_POST['pack_ids'])) {
            foreach ($_POST['pack_ids'] as $pid) {
                $items[] = ['type' => 'pack', 'id' => (int) $pid];
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

// Charger produits et packs pour la sélection
$products = $productModel->findActive();
$packs = $packModel->findActive();

// IDs des items sélectionnés
$selectedProductIds = [];
$selectedPackIds = [];
if ($section && !empty($section['items'])) {
    foreach ($section['items'] as $item) {
        if ($item['item_type'] === 'product') {
            $selectedProductIds[] = $item['item_id'];
        } elseif ($item['item_type'] === 'pack') {
            $selectedPackIds[] = $item['item_id'];
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

                            <!-- Blog info -->
                            <div id="blogInfo" style="display: none;">
                                <div class="info-box-blue">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                                    </svg>
                                    <p>Le slider blog affiche automatiquement les derniers articles publiés. <a href="/admin/blog.php">Gérer les articles</a>.</p>
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
    </style>

    <script>
    function updateFormFields() {
        const type = document.getElementById('type').value;
        const itemsCard = document.getElementById('itemsCard');
        const productsSelection = document.getElementById('productsSelection');
        const packsSelection = document.getElementById('packsSelection');
        const blogInfo = document.getElementById('blogInfo');
        const contentField = document.querySelector('.content-field');
        const ctaFields = document.querySelector('.cta-fields');
        const mediaField = document.querySelector('.media-field');
        const itemsCardTitle = document.getElementById('itemsCardTitle');

        // Reset
        itemsCard.style.display = 'none';
        productsSelection.style.display = 'none';
        packsSelection.style.display = 'none';
        blogInfo.style.display = 'none';
        contentField.style.display = 'none';

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
                break;

            case 'blog_slider':
                itemsCard.style.display = 'block';
                blogInfo.style.display = 'block';
                itemsCardTitle.textContent = 'Articles de blog';
                ctaFields.style.display = 'none';
                mediaField.style.display = 'none';
                break;
        }

        updateItemsCount();
    }

    function updateItemsCount() {
        const type = document.getElementById('type').value;
        let count = 0;

        if (type === 'featured_products') {
            count = document.querySelectorAll('#productsSelection input:checked').length;
        } else if (type === 'featured_packs') {
            count = document.querySelectorAll('#packsSelection input:checked').length;
        }

        document.getElementById('itemsCount').textContent = count + ' sélectionné(s)';
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateFormFields();

        document.querySelectorAll('.item-checkbox input').forEach(cb => {
            cb.addEventListener('change', updateItemsCount);
        });
    });
    </script>
</body>
</html>
