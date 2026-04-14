<?php
/**
 * PERSONNALY - Admin : Paramètres Homepage
 * Hero Slider, Trust Badges, Comment ça marche, Témoignages
 */

require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/HeroSlide.php';
require_once __DIR__ . '/../app/models/TrustBadge.php';
require_once __DIR__ . '/../app/models/HowItWorksStep.php';
require_once __DIR__ . '/../app/models/Testimonial.php';

Auth::requireAdmin();

$heroModel = new HeroSlide();
$badgeModel = new TrustBadge();
$stepModel = new HowItWorksStep();
$testimonialModel = new Testimonial();

$currentTab = get('tab', 'hero');
if (!in_array($currentTab, ['hero', 'badges', 'steps', 'testimonials'])) {
    $currentTab = 'hero';
}

$success = '';
$error = '';

// Actions POST
if (isPost() && verifyCsrf($_POST['csrf_token'] ?? '')) {

    // === HERO SLIDES ===
    if ($currentTab === 'hero') {
        if (isset($_POST['add_slide'])) {
            $title = trim(post('title', ''));
            if (empty($title)) {
                $error = 'Le titre est obligatoire.';
            } else {
                $imageUrl = null;
                if (!empty($_FILES['image']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/../public/uploads/hero/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $filename = 'hero_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                        $imageUrl = '/uploads/hero/' . $filename;
                    }
                }
                $heroModel->create([
                    'title' => $title,
                    'subtitle' => trim(post('subtitle', '')),
                    'cta_text' => trim(post('cta_text', '')),
                    'cta_url' => trim(post('cta_url', '')),
                    'image_url' => $imageUrl
                ]);
                $success = 'Slide ajouté.';
            }
        }
        if (isset($_POST['edit_slide'])) {
            $id = (int) post('slide_id', 0);
            $title = trim(post('title', ''));
            if ($id && !empty($title)) {
                $data = [
                    'title' => $title,
                    'subtitle' => trim(post('subtitle', '')),
                    'cta_text' => trim(post('cta_text', '')),
                    'cta_url' => trim(post('cta_url', ''))
                ];
                if (!empty($_FILES['image']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/../public/uploads/hero/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $filename = 'hero_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                        $data['image_url'] = '/uploads/hero/' . $filename;
                    }
                }
                $heroModel->update($id, $data);
                $success = 'Slide modifié.';
            }
        }
        if (isset($_POST['toggle_slide'])) {
            $heroModel->toggleActive((int) post('slide_id', 0));
        }
        if (isset($_POST['delete_slide'])) {
            $heroModel->delete((int) post('slide_id', 0));
            $success = 'Slide supprimé.';
        }
    }

    // === TRUST BADGES ===
    if ($currentTab === 'badges') {
        if (isset($_POST['add_badge'])) {
            $title = trim(post('title', ''));
            if (empty($title)) {
                $error = 'Le titre est obligatoire.';
            } else {
                $badgeModel->create([
                    'title' => $title,
                    'icon' => trim(post('icon', ''))
                ]);
                $success = 'Badge ajouté.';
            }
        }
        if (isset($_POST['edit_badge'])) {
            $id = (int) post('badge_id', 0);
            $title = trim(post('title', ''));
            if ($id && !empty($title)) {
                $badgeModel->update($id, [
                    'title' => $title,
                    'icon' => trim(post('icon', ''))
                ]);
                $success = 'Badge modifié.';
            }
        }
        if (isset($_POST['toggle_badge'])) {
            $badgeModel->toggleActive((int) post('badge_id', 0));
        }
        if (isset($_POST['delete_badge'])) {
            $badgeModel->delete((int) post('badge_id', 0));
            $success = 'Badge supprimé.';
        }
    }

    // === HOW IT WORKS ===
    if ($currentTab === 'steps') {
        if (isset($_POST['add_step'])) {
            $title = trim(post('title', ''));
            if (empty($title)) {
                $error = 'Le titre est obligatoire.';
            } else {
                $stepModel->create([
                    'title' => $title,
                    'description' => trim(post('description', '')),
                    'icon' => trim(post('icon', ''))
                ]);
                $success = 'Étape ajoutée.';
            }
        }
        if (isset($_POST['edit_step'])) {
            $id = (int) post('step_id', 0);
            $title = trim(post('title', ''));
            if ($id && !empty($title)) {
                $stepModel->update($id, [
                    'title' => $title,
                    'description' => trim(post('description', '')),
                    'icon' => trim(post('icon', ''))
                ]);
                $success = 'Étape modifiée.';
            }
        }
        if (isset($_POST['toggle_step'])) {
            $stepModel->toggleActive((int) post('step_id', 0));
        }
        if (isset($_POST['delete_step'])) {
            $stepModel->delete((int) post('step_id', 0));
            $success = 'Étape supprimée.';
        }
    }

    // === TESTIMONIALS ===
    if ($currentTab === 'testimonials') {
        if (isset($_POST['add_testimonial'])) {
            $name = trim(post('name', ''));
            $content = trim(post('content', ''));
            if (empty($name) || empty($content)) {
                $error = 'Nom et témoignage sont obligatoires.';
            } else {
                $photoUrl = null;
                if (!empty($_FILES['photo']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/../public/uploads/testimonials/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $filename = 'testimonial_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                        $photoUrl = '/uploads/testimonials/' . $filename;
                    }
                }
                $testimonialModel->create([
                    'name' => $name,
                    'content' => $content,
                    'rating' => (int) post('rating', 5),
                    'photo_url' => $photoUrl
                ]);
                $success = 'Témoignage ajouté.';
            }
        }
        if (isset($_POST['edit_testimonial'])) {
            $id = (int) post('testimonial_id', 0);
            $name = trim(post('name', ''));
            $content = trim(post('content', ''));
            if ($id && !empty($name) && !empty($content)) {
                $data = [
                    'name' => $name,
                    'content' => $content,
                    'rating' => (int) post('rating', 5)
                ];
                if (!empty($_FILES['photo']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/../public/uploads/testimonials/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $filename = 'testimonial_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                        $data['photo_url'] = '/uploads/testimonials/' . $filename;
                    }
                }
                $testimonialModel->update($id, $data);
                $success = 'Témoignage modifié.';
            }
        }
        if (isset($_POST['toggle_testimonial'])) {
            $testimonialModel->toggleActive((int) post('testimonial_id', 0));
        }
        if (isset($_POST['delete_testimonial'])) {
            $testimonialModel->delete((int) post('testimonial_id', 0));
            $success = 'Témoignage supprimé.';
        }
    }
}

// Charger les données
$heroSlides = $heroModel->findAll();
$trustBadges = $badgeModel->findAll();
$howItWorksSteps = $stepModel->findAll();
$testimonials = $testimonialModel->findAll();

$tabs = [
    'hero' => ['label' => 'Hero Slider', 'icon' => '🖼️', 'count' => count($heroSlides)],
    'badges' => ['label' => 'Trust Badges', 'icon' => '🏆', 'count' => count($trustBadges)],
    'steps' => ['label' => 'Comment ça marche', 'icon' => '📋', 'count' => count($howItWorksSteps)],
    'testimonials' => ['label' => 'Témoignages', 'icon' => '💬', 'count' => count($testimonials)]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres Homepage - Admin PERSONNALY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .tabs-nav { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .tab-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 12px 20px; border-radius: 8px;
            background: #f5f5f5; border: none; cursor: pointer;
            font-size: 14px; font-weight: 500; color: #666;
            transition: all 0.2s;
        }
        .tab-btn:hover { background: #eee; }
        .tab-btn.active { background: var(--pink); color: white; }
        .tab-btn .count { background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 10px; font-size: 12px; }
        .tab-btn.active .count { background: rgba(255,255,255,0.2); }

        .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
        .item-card {
            background: white; border-radius: 12px; padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #eee;
        }
        .item-card.inactive { opacity: 0.5; }
        .item-card-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; }
        .item-card-title { font-weight: 600; font-size: 15px; }
        .item-card-actions { display: flex; gap: 6px; }
        .item-card-content { color: #666; font-size: 13px; line-height: 1.5; }
        .item-card-meta { margin-top: 10px; font-size: 12px; color: #999; }
        .item-card-image { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 12px; background: #f5f5f5; }

        .btn-mini { padding: 6px 10px; font-size: 12px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-toggle { background: #e0e0e0; }
        .btn-toggle.active { background: #4CAF50; color: white; }
        .btn-edit { background: #2196F3; color: white; }
        .btn-delete { background: #f44336; color: white; }

        .add-form { background: white; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 2px dashed #ddd; }
        .add-form h3 { margin-bottom: 16px; font-size: 16px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #444; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .btn-add { background: var(--pink); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-add:hover { opacity: 0.9; }

        .rating-stars { display: flex; gap: 4px; }
        .rating-stars span { font-size: 18px; color: #ddd; cursor: pointer; }
        .rating-stars span.active { color: #FFD700; }

        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 24px; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }

        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state-icon { font-size: 48px; margin-bottom: 12px; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <h1>Paramètres Homepage</h1>
            </div>

            <div class="admin-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= h($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= h($error) ?></div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tabs-nav">
                    <?php foreach ($tabs as $key => $tab): ?>
                        <a href="?tab=<?= $key ?>" class="tab-btn <?= $currentTab === $key ? 'active' : '' ?>">
                            <span><?= $tab['icon'] ?></span>
                            <span><?= $tab['label'] ?></span>
                            <span class="count"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- ========== HERO SLIDES ========== -->
                <?php if ($currentTab === 'hero'): ?>
                    <div class="add-form">
                        <h3>Ajouter un slide</h3>
                        <form method="post" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Titre *</label>
                                    <input type="text" name="title" required placeholder="Ex: Créez des vêtements uniques">
                                </div>
                                <div class="form-group">
                                    <label>Sous-titre</label>
                                    <input type="text" name="subtitle" placeholder="Ex: Personnalisation de qualité">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Texte bouton</label>
                                    <input type="text" name="cta_text" placeholder="Ex: Découvrir">
                                </div>
                                <div class="form-group">
                                    <label>Lien bouton</label>
                                    <input type="text" name="cta_url" placeholder="Ex: #produits">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Image de fond</label>
                                <input type="file" name="image" accept="image/*">
                            </div>
                            <button type="submit" name="add_slide" class="btn-add">Ajouter le slide</button>
                        </form>
                    </div>

                    <?php if (empty($heroSlides)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🎠</div>
                            <p>Aucun slide. Ajoutez-en un !</p>
                        </div>
                    <?php else: ?>
                        <div class="items-grid">
                            <?php foreach ($heroSlides as $slide): ?>
                                <div class="item-card <?= $slide['active'] ? '' : 'inactive' ?>">
                                    <?php if (!empty($slide['image_url'])): ?>
                                        <img src="/public<?= h($slide['image_url']) ?>" alt="" class="item-card-image">
                                    <?php endif; ?>
                                    <div class="item-card-header">
                                        <div class="item-card-title"><?= h($slide['title']) ?></div>
                                        <div class="item-card-actions">
                                            <form method="post" style="display:inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="slide_id" value="<?= $slide['id'] ?>">
                                                <button type="submit" name="toggle_slide" class="btn-mini btn-toggle <?= $slide['active'] ? 'active' : '' ?>"><?= $slide['active'] ? '✓' : '○' ?></button>
                                            </form>
                                            <button type="button" class="btn-mini btn-edit" onclick="editSlide(<?= htmlspecialchars(json_encode($slide)) ?>)">✏️</button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="slide_id" value="<?= $slide['id'] ?>">
                                                <button type="submit" name="delete_slide" class="btn-mini btn-delete">🗑️</button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php if (!empty($slide['subtitle'])): ?>
                                        <div class="item-card-content"><?= h($slide['subtitle']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($slide['cta_text'])): ?>
                                        <div class="item-card-meta">Bouton: <?= h($slide['cta_text']) ?> → <?= h($slide['cta_url']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- ========== TRUST BADGES ========== -->
                <?php if ($currentTab === 'badges'): ?>
                    <div class="add-form">
                        <h3>Ajouter un badge</h3>
                        <form method="post">
                            <?= csrfField() ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Icône (emoji)</label>
                                    <input type="text" name="icon" placeholder="Ex: 🚚" style="width: 80px;">
                                </div>
                                <div class="form-group" style="flex: 2;">
                                    <label>Titre *</label>
                                    <input type="text" name="title" required placeholder="Ex: Livraison rapide">
                                </div>
                            </div>
                            <button type="submit" name="add_badge" class="btn-add">Ajouter le badge</button>
                        </form>
                    </div>

                    <?php if (empty($trustBadges)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🏆</div>
                            <p>Aucun badge. Ajoutez-en un !</p>
                        </div>
                    <?php else: ?>
                        <div class="items-grid">
                            <?php foreach ($trustBadges as $badge): ?>
                                <div class="item-card <?= $badge['active'] ? '' : 'inactive' ?>">
                                    <div class="item-card-header">
                                        <div class="item-card-title">
                                            <span style="font-size: 24px; margin-right: 8px;"><?= h($badge['icon']) ?></span>
                                            <?= h($badge['title']) ?>
                                        </div>
                                        <div class="item-card-actions">
                                            <form method="post" style="display:inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="badge_id" value="<?= $badge['id'] ?>">
                                                <button type="submit" name="toggle_badge" class="btn-mini btn-toggle <?= $badge['active'] ? 'active' : '' ?>"><?= $badge['active'] ? '✓' : '○' ?></button>
                                            </form>
                                            <button type="button" class="btn-mini btn-edit" onclick="editBadge(<?= htmlspecialchars(json_encode($badge)) ?>)">✏️</button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="badge_id" value="<?= $badge['id'] ?>">
                                                <button type="submit" name="delete_badge" class="btn-mini btn-delete">🗑️</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- ========== HOW IT WORKS ========== -->
                <?php if ($currentTab === 'steps'): ?>
                    <div class="add-form">
                        <h3>Ajouter une étape</h3>
                        <form method="post">
                            <?= csrfField() ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Icône (emoji)</label>
                                    <input type="text" name="icon" placeholder="Ex: 1️⃣" style="width: 80px;">
                                </div>
                                <div class="form-group" style="flex: 2;">
                                    <label>Titre *</label>
                                    <input type="text" name="title" required placeholder="Ex: Choisissez">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" placeholder="Ex: Sélectionnez le produit qui vous plaît..."></textarea>
                            </div>
                            <button type="submit" name="add_step" class="btn-add">Ajouter l'étape</button>
                        </form>
                    </div>

                    <?php if (empty($howItWorksSteps)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <p>Aucune étape. Ajoutez-en une !</p>
                        </div>
                    <?php else: ?>
                        <div class="items-grid">
                            <?php foreach ($howItWorksSteps as $step): ?>
                                <div class="item-card <?= $step['active'] ? '' : 'inactive' ?>">
                                    <div class="item-card-header">
                                        <div class="item-card-title">
                                            <span style="font-size: 24px; margin-right: 8px;"><?= h($step['icon']) ?></span>
                                            <?= h($step['title']) ?>
                                        </div>
                                        <div class="item-card-actions">
                                            <form method="post" style="display:inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="step_id" value="<?= $step['id'] ?>">
                                                <button type="submit" name="toggle_step" class="btn-mini btn-toggle <?= $step['active'] ? 'active' : '' ?>"><?= $step['active'] ? '✓' : '○' ?></button>
                                            </form>
                                            <button type="button" class="btn-mini btn-edit" onclick="editStep(<?= htmlspecialchars(json_encode($step)) ?>)">✏️</button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="step_id" value="<?= $step['id'] ?>">
                                                <button type="submit" name="delete_step" class="btn-mini btn-delete">🗑️</button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php if (!empty($step['description'])): ?>
                                        <div class="item-card-content"><?= h($step['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- ========== TESTIMONIALS ========== -->
                <?php if ($currentTab === 'testimonials'): ?>
                    <div class="add-form">
                        <h3>Ajouter un témoignage</h3>
                        <form method="post" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nom *</label>
                                    <input type="text" name="name" required placeholder="Ex: Marie L.">
                                </div>
                                <div class="form-group">
                                    <label>Note (1-5)</label>
                                    <select name="rating">
                                        <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                                        <option value="4">⭐⭐⭐⭐ (4)</option>
                                        <option value="3">⭐⭐⭐ (3)</option>
                                        <option value="2">⭐⭐ (2)</option>
                                        <option value="1">⭐ (1)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Témoignage *</label>
                                <textarea name="content" required placeholder="Ex: Super qualité ! Je recommande."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Photo (optionnel)</label>
                                <input type="file" name="photo" accept="image/*">
                            </div>
                            <button type="submit" name="add_testimonial" class="btn-add">Ajouter le témoignage</button>
                        </form>
                    </div>

                    <?php if (empty($testimonials)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">💬</div>
                            <p>Aucun témoignage. Ajoutez-en un !</p>
                        </div>
                    <?php else: ?>
                        <div class="items-grid">
                            <?php foreach ($testimonials as $testimonial): ?>
                                <div class="item-card <?= $testimonial['active'] ? '' : 'inactive' ?>">
                                    <div class="item-card-header">
                                        <div class="item-card-title">
                                            <?php if (!empty($testimonial['photo_url'])): ?>
                                                <img src="/public<?= h($testimonial['photo_url']) ?>" alt="" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px; vertical-align: middle;">
                                            <?php endif; ?>
                                            <?= h($testimonial['name']) ?>
                                            <span style="color: #FFD700; margin-left: 8px;">
                                                <?= str_repeat('⭐', $testimonial['rating']) ?>
                                            </span>
                                        </div>
                                        <div class="item-card-actions">
                                            <form method="post" style="display:inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="testimonial_id" value="<?= $testimonial['id'] ?>">
                                                <button type="submit" name="toggle_testimonial" class="btn-mini btn-toggle <?= $testimonial['active'] ? 'active' : '' ?>"><?= $testimonial['active'] ? '✓' : '○' ?></button>
                                            </form>
                                            <button type="button" class="btn-mini btn-edit" onclick="editTestimonial(<?= htmlspecialchars(json_encode($testimonial)) ?>)">✏️</button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="testimonial_id" value="<?= $testimonial['id'] ?>">
                                                <button type="submit" name="delete_testimonial" class="btn-mini btn-delete">🗑️</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="item-card-content"><?= h($testimonial['content']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- Modals d'édition -->
    <div class="modal" id="slideModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifier le slide</h3>
                <button class="modal-close" onclick="closeModal('slideModal')">&times;</button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="slide_id" id="slideId">
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" id="slideTitle" required>
                </div>
                <div class="form-group">
                    <label>Sous-titre</label>
                    <input type="text" name="subtitle" id="slideSubtitle">
                </div>
                <div class="form-group">
                    <label>Texte bouton</label>
                    <input type="text" name="cta_text" id="slideCtaText">
                </div>
                <div class="form-group">
                    <label>Lien bouton</label>
                    <input type="text" name="cta_url" id="slideCtaUrl">
                </div>
                <div class="form-group">
                    <label>Nouvelle image (optionnel)</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <button type="submit" name="edit_slide" class="btn-add">Enregistrer</button>
            </form>
        </div>
    </div>

    <div class="modal" id="badgeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifier le badge</h3>
                <button class="modal-close" onclick="closeModal('badgeModal')">&times;</button>
            </div>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="badge_id" id="badgeId">
                <div class="form-group">
                    <label>Icône (emoji)</label>
                    <input type="text" name="icon" id="badgeIcon" style="width: 80px;">
                </div>
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" id="badgeTitle" required>
                </div>
                <button type="submit" name="edit_badge" class="btn-add">Enregistrer</button>
            </form>
        </div>
    </div>

    <div class="modal" id="stepModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifier l'étape</h3>
                <button class="modal-close" onclick="closeModal('stepModal')">&times;</button>
            </div>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="step_id" id="stepId">
                <div class="form-group">
                    <label>Icône (emoji)</label>
                    <input type="text" name="icon" id="stepIcon" style="width: 80px;">
                </div>
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" id="stepTitle" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="stepDescription"></textarea>
                </div>
                <button type="submit" name="edit_step" class="btn-add">Enregistrer</button>
            </form>
        </div>
    </div>

    <div class="modal" id="testimonialModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifier le témoignage</h3>
                <button class="modal-close" onclick="closeModal('testimonialModal')">&times;</button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="testimonial_id" id="testimonialId">
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="name" id="testimonialName" required>
                </div>
                <div class="form-group">
                    <label>Note</label>
                    <select name="rating" id="testimonialRating">
                        <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                        <option value="4">⭐⭐⭐⭐ (4)</option>
                        <option value="3">⭐⭐⭐ (3)</option>
                        <option value="2">⭐⭐ (2)</option>
                        <option value="1">⭐ (1)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Témoignage *</label>
                    <textarea name="content" id="testimonialContent" required></textarea>
                </div>
                <div class="form-group">
                    <label>Nouvelle photo (optionnel)</label>
                    <input type="file" name="photo" accept="image/*">
                </div>
                <button type="submit" name="edit_testimonial" class="btn-add">Enregistrer</button>
            </form>
        </div>
    </div>

    <script>
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function editSlide(s) {
            document.getElementById('slideId').value = s.id;
            document.getElementById('slideTitle').value = s.title || '';
            document.getElementById('slideSubtitle').value = s.subtitle || '';
            document.getElementById('slideCtaText').value = s.cta_text || '';
            document.getElementById('slideCtaUrl').value = s.cta_url || '';
            document.getElementById('slideModal').classList.add('active');
        }

        function editBadge(b) {
            document.getElementById('badgeId').value = b.id;
            document.getElementById('badgeIcon').value = b.icon || '';
            document.getElementById('badgeTitle').value = b.title || '';
            document.getElementById('badgeModal').classList.add('active');
        }

        function editStep(s) {
            document.getElementById('stepId').value = s.id;
            document.getElementById('stepIcon').value = s.icon || '';
            document.getElementById('stepTitle').value = s.title || '';
            document.getElementById('stepDescription').value = s.description || '';
            document.getElementById('stepModal').classList.add('active');
        }

        function editTestimonial(t) {
            document.getElementById('testimonialId').value = t.id;
            document.getElementById('testimonialName').value = t.name || '';
            document.getElementById('testimonialRating').value = t.rating || 5;
            document.getElementById('testimonialContent').value = t.content || '';
            document.getElementById('testimonialModal').classList.add('active');
        }

        // Fermer modal au clic extérieur
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeModal(this.id);
            });
        });
    </script>
</body>
</html>
