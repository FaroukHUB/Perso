<?php
/**
 * PERSONNALY - Admin : Paramètres Homepage
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
    // HERO SLIDES
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

    // TRUST BADGES
    if ($currentTab === 'badges') {
        if (isset($_POST['add_badge'])) {
            $title = trim(post('title', ''));
            if (empty($title)) {
                $error = 'Le titre est obligatoire.';
            } else {
                $badgeModel->create(['title' => $title, 'icon' => trim(post('icon', ''))]);
                $success = 'Badge ajouté.';
            }
        }
        if (isset($_POST['edit_badge'])) {
            $id = (int) post('badge_id', 0);
            $title = trim(post('title', ''));
            if ($id && !empty($title)) {
                $badgeModel->update($id, ['title' => $title, 'icon' => trim(post('icon', ''))]);
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

    // HOW IT WORKS
    if ($currentTab === 'steps') {
        if (isset($_POST['add_step'])) {
            $title = trim(post('title', ''));
            if (empty($title)) {
                $error = 'Le titre est obligatoire.';
            } else {
                $imageUrl = null;
                if (!empty($_FILES['step_image']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/../public/uploads/steps/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['step_image']['name'], PATHINFO_EXTENSION));
                    $filename = 'step_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['step_image']['tmp_name'], $uploadDir . $filename)) {
                        $imageUrl = '/uploads/steps/' . $filename;
                    }
                }
                $stepModel->create([
                    'title' => $title,
                    'description' => trim(post('description', '')),
                    'icon' => trim(post('icon', '')),
                    'image_url' => $imageUrl
                ]);
                $success = 'Étape ajoutée.';
            }
        }
        if (isset($_POST['edit_step'])) {
            $id = (int) post('step_id', 0);
            $title = trim(post('title', ''));
            if ($id && !empty($title)) {
                $data = [
                    'title' => $title,
                    'description' => trim(post('description', '')),
                    'icon' => trim(post('icon', ''))
                ];
                if (!empty($_FILES['step_image']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/../public/uploads/steps/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['step_image']['name'], PATHINFO_EXTENSION));
                    $filename = 'step_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['step_image']['tmp_name'], $uploadDir . $filename)) {
                        $data['image_url'] = '/uploads/steps/' . $filename;
                    }
                } elseif (post('remove_image') === '1') {
                    $data['image_url'] = null;
                }
                $stepModel->update($id, $data);
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

    // TESTIMONIALS
    if ($currentTab === 'testimonials') {
        if (isset($_POST['add_testimonial'])) {
            $name = trim(post('name', ''));
            $content = trim(post('content', ''));
            if (empty($name) || empty($content)) {
                $error = 'Nom et témoignage obligatoires.';
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
                $testimonialModel->create(['name' => $name, 'content' => $content, 'rating' => (int) post('rating', 5), 'photo_url' => $photoUrl]);
                $success = 'Témoignage ajouté.';
            }
        }
        if (isset($_POST['edit_testimonial'])) {
            $id = (int) post('testimonial_id', 0);
            $name = trim(post('name', ''));
            $content = trim(post('content', ''));
            if ($id && !empty($name) && !empty($content)) {
                $data = ['name' => $name, 'content' => $content, 'rating' => (int) post('rating', 5)];
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

$heroSlides = $heroModel->findAll();
$trustBadges = $badgeModel->findAll();
$howItWorksSteps = $stepModel->findAll();
$testimonials = $testimonialModel->findAll();

$tabs = [
    'hero' => ['label' => 'Hero Slider', 'icon' => '🖼', 'count' => count($heroSlides)],
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
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        .type-tabs { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .type-tab { display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: white; border-radius: var(--radius-full); text-decoration: none; color: var(--gray); font-weight: 600; font-size: 14px; transition: all 0.2s; box-shadow: var(--shadow-sm); }
        .type-tab:hover { color: var(--pink-main); transform: translateY(-2px); }
        .type-tab.active { background: var(--gradient-pink); color: white; box-shadow: var(--shadow-pink); }
        .tab-count { background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 10px; font-size: 12px; }
        .type-tab.active .tab-count { background: rgba(255,255,255,0.2); }
        .options-grid { display: grid; grid-template-columns: 1fr 400px; gap: 30px; align-items: start; }
        .options-list { background: white; border-radius: var(--radius-lg); overflow: hidden; }
        .list-header { padding: 20px 25px; border-bottom: 1px solid rgba(0,0,0,0.06); font-weight: 700; }
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; padding: 20px; }
        .card-item { background: var(--gray-light); border-radius: var(--radius-md); padding: 16px; position: relative; }
        .card-item.inactive { opacity: 0.5; }
        .card-item-img { width: 100%; height: 100px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 12px; background: #ddd; }
        .card-item-title { font-weight: 700; font-size: 15px; margin-bottom: 6px; color: var(--black-soft); }
        .card-item-desc { font-size: 13px; color: var(--gray); line-height: 1.4; }
        .card-item-meta { font-size: 12px; color: var(--gray); margin-top: 8px; }
        .card-item-actions { display: flex; gap: 6px; margin-top: 12px; }
        .action-btn-mini { width: 32px; height: 32px; border: none; border-radius: var(--radius-sm); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; background: white; transition: all 0.2s; }
        .action-btn-mini.toggle.active { background: var(--mint-main); }
        .action-btn-mini.edit:hover { background: var(--pink-light); }
        .action-btn-mini.delete { color: #dc3545; }
        .action-btn-mini.delete:hover { background: #fee; }
        .form-card { background: white; border-radius: var(--radius-lg); padding: 25px; position: sticky; top: 20px; }
        .form-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid rgba(0,0,0,0.06); }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: var(--black-soft); }
        .form-input, .form-textarea, .form-select { width: 100%; padding: 10px 14px; border: 2px solid #e5e5e5; border-radius: var(--radius-md); font-size: 14px; transition: all 0.2s; font-family: inherit; }
        .form-input:focus, .form-textarea:focus, .form-select:focus { outline: none; border-color: var(--pink-main); }
        .form-textarea { min-height: 70px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn { padding: 12px 20px; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: var(--gradient-pink); color: white; width: 100%; }
        .btn-primary:hover { box-shadow: var(--shadow-pink); }
        .alert { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: rgba(61, 255, 192, 0.15); color: var(--mint-dark); }
        .alert-error { background: rgba(255, 105, 180, 0.15); color: var(--pink-dark); }
        .empty-state { padding: 40px; text-align: center; color: var(--gray); }
        .empty-state-icon { font-size: 2.5rem; margin-bottom: 10px; opacity: 0.4; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: var(--radius-lg); padding: 25px; width: 100%; max-width: 450px; margin: 20px; }
        .modal h3 { margin-bottom: 20px; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .modal-actions .btn { flex: 1; }
        .btn-secondary { background: var(--gray-light); color: var(--black-soft); }
        @media (max-width: 900px) { .options-grid { grid-template-columns: 1fr; } .form-card { position: static; } }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1>Paramètres Homepage</h1>
            </div>

            <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

            <div class="type-tabs">
                <?php foreach ($tabs as $key => $tab): ?>
                    <a href="?tab=<?= $key ?>" class="type-tab <?= $currentTab === $key ? 'active' : '' ?>">
                        <span><?= $tab['icon'] ?></span>
                        <span><?= $tab['label'] ?></span>
                        <span class="tab-count"><?= $tab['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="options-grid">
                <div class="options-list">
                    <div class="list-header"><?= $tabs[$currentTab]['icon'] ?> <?= $tabs[$currentTab]['label'] ?></div>

                    <?php if ($currentTab === 'hero'): ?>
                        <?php if (empty($heroSlides)): ?>
                            <div class="empty-state"><div class="empty-state-icon">🖼</div><p>Aucun slide</p></div>
                        <?php else: ?>
                            <div class="cards-grid">
                                <?php foreach ($heroSlides as $s): ?>
                                    <div class="card-item <?= $s['active'] ? '' : 'inactive' ?>">
                                        <?php if ($s['image_url']): ?><img src="/public<?= h($s['image_url']) ?>" class="card-item-img"><?php endif; ?>
                                        <div class="card-item-title"><?= h($s['title']) ?></div>
                                        <div class="card-item-desc"><?= h($s['subtitle']) ?></div>
                                        <?php if ($s['cta_text']): ?><div class="card-item-meta">Bouton: <?= h($s['cta_text']) ?></div><?php endif; ?>
                                        <div class="card-item-actions">
                                            <form method="post" style="display:contents;"><?= csrfField() ?><input type="hidden" name="slide_id" value="<?= $s['id'] ?>"><button type="submit" name="toggle_slide" class="action-btn-mini toggle <?= $s['active'] ? 'active' : '' ?>"><?= $s['active'] ? '✓' : '○' ?></button></form>
                                            <button class="action-btn-mini edit" onclick="editSlide(<?= htmlspecialchars(json_encode($s)) ?>)">✏️</button>
                                            <form method="post" style="display:contents;" onsubmit="return confirm('Supprimer ?')"><?= csrfField() ?><input type="hidden" name="slide_id" value="<?= $s['id'] ?>"><button type="submit" name="delete_slide" class="action-btn-mini delete">🗑️</button></form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($currentTab === 'badges'): ?>
                        <?php if (empty($trustBadges)): ?>
                            <div class="empty-state"><div class="empty-state-icon">🏆</div><p>Aucun badge</p></div>
                        <?php else: ?>
                            <div class="cards-grid">
                                <?php foreach ($trustBadges as $b): ?>
                                    <div class="card-item <?= $b['active'] ? '' : 'inactive' ?>">
                                        <div class="card-item-title"><span style="font-size:24px;margin-right:8px;"><?= h($b['icon']) ?></span><?= h($b['title']) ?></div>
                                        <div class="card-item-actions">
                                            <form method="post" style="display:contents;"><?= csrfField() ?><input type="hidden" name="badge_id" value="<?= $b['id'] ?>"><button type="submit" name="toggle_badge" class="action-btn-mini toggle <?= $b['active'] ? 'active' : '' ?>"><?= $b['active'] ? '✓' : '○' ?></button></form>
                                            <button class="action-btn-mini edit" onclick="editBadge(<?= htmlspecialchars(json_encode($b)) ?>)">✏️</button>
                                            <form method="post" style="display:contents;" onsubmit="return confirm('Supprimer ?')"><?= csrfField() ?><input type="hidden" name="badge_id" value="<?= $b['id'] ?>"><button type="submit" name="delete_badge" class="action-btn-mini delete">🗑️</button></form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($currentTab === 'steps'): ?>
                        <?php if (empty($howItWorksSteps)): ?>
                            <div class="empty-state"><div class="empty-state-icon">📋</div><p>Aucune étape</p></div>
                        <?php else: ?>
                            <div class="cards-grid">
                                <?php foreach ($howItWorksSteps as $st): ?>
                                    <div class="card-item <?= $st['active'] ? '' : 'inactive' ?>">
                                        <?php if (!empty($st['image_url'])): ?>
                                            <img src="/public<?= h($st['image_url']) ?>" class="card-item-img" style="height:80px;width:80px;object-fit:cover;border-radius:12px;margin-bottom:12px;">
                                        <?php elseif (!empty($st['icon'])): ?>
                                            <div style="font-size:40px;margin-bottom:12px;"><?= h($st['icon']) ?></div>
                                        <?php endif; ?>
                                        <div class="card-item-title"><?= h($st['title']) ?></div>
                                        <div class="card-item-desc"><?= h($st['description']) ?></div>
                                        <div class="card-item-meta"><?= !empty($st['image_url']) ? '📷 Image' : '🎨 Icône' ?></div>
                                        <div class="card-item-actions">
                                            <form method="post" style="display:contents;"><?= csrfField() ?><input type="hidden" name="step_id" value="<?= $st['id'] ?>"><button type="submit" name="toggle_step" class="action-btn-mini toggle <?= $st['active'] ? 'active' : '' ?>"><?= $st['active'] ? '✓' : '○' ?></button></form>
                                            <button class="action-btn-mini edit" onclick="editStep(<?= htmlspecialchars(json_encode($st)) ?>)">✏️</button>
                                            <form method="post" style="display:contents;" onsubmit="return confirm('Supprimer ?')"><?= csrfField() ?><input type="hidden" name="step_id" value="<?= $st['id'] ?>"><button type="submit" name="delete_step" class="action-btn-mini delete">🗑️</button></form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($currentTab === 'testimonials'): ?>
                        <?php if (empty($testimonials)): ?>
                            <div class="empty-state"><div class="empty-state-icon">💬</div><p>Aucun témoignage</p></div>
                        <?php else: ?>
                            <div class="cards-grid">
                                <?php foreach ($testimonials as $t): ?>
                                    <div class="card-item <?= $t['active'] ? '' : 'inactive' ?>">
                                        <div class="card-item-title"><?= h($t['name']) ?> <span style="color:#FFD700;"><?= str_repeat('⭐', $t['rating']) ?></span></div>
                                        <div class="card-item-desc">"<?= h($t['content']) ?>"</div>
                                        <div class="card-item-actions">
                                            <form method="post" style="display:contents;"><?= csrfField() ?><input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>"><button type="submit" name="toggle_testimonial" class="action-btn-mini toggle <?= $t['active'] ? 'active' : '' ?>"><?= $t['active'] ? '✓' : '○' ?></button></form>
                                            <button class="action-btn-mini edit" onclick="editTestimonial(<?= htmlspecialchars(json_encode($t)) ?>)">✏️</button>
                                            <form method="post" style="display:contents;" onsubmit="return confirm('Supprimer ?')"><?= csrfField() ?><input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>"><button type="submit" name="delete_testimonial" class="action-btn-mini delete">🗑️</button></form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="form-card">
                    <?php if ($currentTab === 'hero'): ?>
                        <h3>Ajouter un slide</h3>
                        <form method="post" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <div class="form-group"><label class="form-label">Titre *</label><input type="text" name="title" class="form-input" required></div>
                            <div class="form-group"><label class="form-label">Sous-titre</label><input type="text" name="subtitle" class="form-input"></div>
                            <div class="form-row">
                                <div class="form-group"><label class="form-label">Texte bouton</label><input type="text" name="cta_text" class="form-input"></div>
                                <div class="form-group"><label class="form-label">Lien bouton</label><input type="text" name="cta_url" class="form-input"></div>
                            </div>
                            <div class="form-group"><label class="form-label">Image de fond</label><input type="file" name="image" class="form-input" accept="image/*"></div>
                            <button type="submit" name="add_slide" class="btn btn-primary">Ajouter</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($currentTab === 'badges'): ?>
                        <h3>Ajouter un badge</h3>
                        <form method="post">
                            <?= csrfField() ?>
                            <div class="form-row">
                                <div class="form-group"><label class="form-label">Icône</label><input type="text" name="icon" class="form-input" placeholder="🚚"></div>
                                <div class="form-group"><label class="form-label">Titre *</label><input type="text" name="title" class="form-input" required></div>
                            </div>
                            <button type="submit" name="add_badge" class="btn btn-primary">Ajouter</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($currentTab === 'steps'): ?>
                        <h3>Ajouter une étape</h3>
                        <form method="post" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <div class="form-group"><label class="form-label">Titre *</label><input type="text" name="title" class="form-input" required></div>
                            <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-textarea"></textarea></div>
                            <div class="form-group">
                                <label class="form-label">Visuel (icône OU image)</label>
                                <p style="font-size:12px;color:var(--gray);margin-bottom:10px;">Choisissez un émoji/icône OU uploadez une image. L'image sera prioritaire si les deux sont remplis.</p>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label class="form-label">Icône (émoji)</label><input type="text" name="icon" class="form-input" placeholder="1️⃣ 🎨 📦"></div>
                                <div class="form-group"><label class="form-label">Ou Image</label><input type="file" name="step_image" class="form-input" accept="image/*"></div>
                            </div>
                            <button type="submit" name="add_step" class="btn btn-primary">Ajouter</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($currentTab === 'testimonials'): ?>
                        <h3>Ajouter un témoignage</h3>
                        <form method="post" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <div class="form-row">
                                <div class="form-group"><label class="form-label">Nom *</label><input type="text" name="name" class="form-input" required></div>
                                <div class="form-group"><label class="form-label">Note</label><select name="rating" class="form-select"><option value="5">⭐⭐⭐⭐⭐</option><option value="4">⭐⭐⭐⭐</option><option value="3">⭐⭐⭐</option></select></div>
                            </div>
                            <div class="form-group"><label class="form-label">Témoignage *</label><textarea name="content" class="form-textarea" required></textarea></div>
                            <div class="form-group"><label class="form-label">Photo</label><input type="file" name="photo" class="form-input" accept="image/*"></div>
                            <button type="submit" name="add_testimonial" class="btn btn-primary">Ajouter</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div class="modal-overlay" id="slideModal"><div class="modal"><h3>Modifier le slide</h3><form method="post" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="slide_id" id="slideId"><div class="form-group"><label class="form-label">Titre</label><input type="text" name="title" id="slideTitle" class="form-input" required></div><div class="form-group"><label class="form-label">Sous-titre</label><input type="text" name="subtitle" id="slideSubtitle" class="form-input"></div><div class="form-row"><div class="form-group"><label class="form-label">Texte bouton</label><input type="text" name="cta_text" id="slideCtaText" class="form-input"></div><div class="form-group"><label class="form-label">Lien</label><input type="text" name="cta_url" id="slideCtaUrl" class="form-input"></div></div><div class="form-group"><label class="form-label">Nouvelle image</label><input type="file" name="image" class="form-input" accept="image/*"></div><div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeModal('slideModal')">Annuler</button><button type="submit" name="edit_slide" class="btn btn-primary">Enregistrer</button></div></form></div></div>

    <div class="modal-overlay" id="badgeModal"><div class="modal"><h3>Modifier le badge</h3><form method="post"><?= csrfField() ?><input type="hidden" name="badge_id" id="badgeId"><div class="form-row"><div class="form-group"><label class="form-label">Icône</label><input type="text" name="icon" id="badgeIcon" class="form-input"></div><div class="form-group"><label class="form-label">Titre</label><input type="text" name="title" id="badgeTitle" class="form-input" required></div></div><div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeModal('badgeModal')">Annuler</button><button type="submit" name="edit_badge" class="btn btn-primary">Enregistrer</button></div></form></div></div>

    <div class="modal-overlay" id="stepModal"><div class="modal"><h3>Modifier l'étape</h3><form method="post" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="step_id" id="stepId"><div class="form-group"><label class="form-label">Titre *</label><input type="text" name="title" id="stepTitle" class="form-input" required></div><div class="form-group"><label class="form-label">Description</label><textarea name="description" id="stepDescription" class="form-textarea"></textarea></div><div class="form-group"><label class="form-label">Icône (émoji)</label><input type="text" name="icon" id="stepIcon" class="form-input" placeholder="1️⃣ 🎨 📦"></div><div class="form-group"><label class="form-label">Nouvelle image</label><input type="file" name="step_image" class="form-input" accept="image/*"></div><div class="form-group" id="stepImagePreview" style="display:none;"><label class="form-label">Image actuelle</label><div style="display:flex;align-items:center;gap:10px;"><img id="stepImageThumb" src="" style="width:60px;height:60px;object-fit:cover;border-radius:8px;"><label style="font-size:13px;display:flex;align-items:center;gap:5px;cursor:pointer;"><input type="checkbox" name="remove_image" value="1"> Supprimer l'image</label></div></div><div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeModal('stepModal')">Annuler</button><button type="submit" name="edit_step" class="btn btn-primary">Enregistrer</button></div></form></div></div>

    <div class="modal-overlay" id="testimonialModal"><div class="modal"><h3>Modifier le témoignage</h3><form method="post" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="testimonial_id" id="testimonialId"><div class="form-row"><div class="form-group"><label class="form-label">Nom</label><input type="text" name="name" id="testimonialName" class="form-input" required></div><div class="form-group"><label class="form-label">Note</label><select name="rating" id="testimonialRating" class="form-select"><option value="5">⭐⭐⭐⭐⭐</option><option value="4">⭐⭐⭐⭐</option><option value="3">⭐⭐⭐</option></select></div></div><div class="form-group"><label class="form-label">Témoignage</label><textarea name="content" id="testimonialContent" class="form-textarea" required></textarea></div><div class="form-group"><label class="form-label">Nouvelle photo</label><input type="file" name="photo" class="form-input" accept="image/*"></div><div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeModal('testimonialModal')">Annuler</button><button type="submit" name="edit_testimonial" class="btn btn-primary">Enregistrer</button></div></form></div></div>

    <script>
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        function editSlide(s) { document.getElementById('slideId').value = s.id; document.getElementById('slideTitle').value = s.title || ''; document.getElementById('slideSubtitle').value = s.subtitle || ''; document.getElementById('slideCtaText').value = s.cta_text || ''; document.getElementById('slideCtaUrl').value = s.cta_url || ''; document.getElementById('slideModal').classList.add('active'); }
        function editBadge(b) { document.getElementById('badgeId').value = b.id; document.getElementById('badgeIcon').value = b.icon || ''; document.getElementById('badgeTitle').value = b.title || ''; document.getElementById('badgeModal').classList.add('active'); }
        function editStep(s) {
            document.getElementById('stepId').value = s.id;
            document.getElementById('stepIcon').value = s.icon || '';
            document.getElementById('stepTitle').value = s.title || '';
            document.getElementById('stepDescription').value = s.description || '';
            var preview = document.getElementById('stepImagePreview');
            var thumb = document.getElementById('stepImageThumb');
            if (s.image_url) {
                preview.style.display = 'block';
                thumb.src = '/public' + s.image_url;
            } else {
                preview.style.display = 'none';
                thumb.src = '';
            }
            document.getElementById('stepModal').classList.add('active');
        }
        function editTestimonial(t) { document.getElementById('testimonialId').value = t.id; document.getElementById('testimonialName').value = t.name || ''; document.getElementById('testimonialRating').value = t.rating || 5; document.getElementById('testimonialContent').value = t.content || ''; document.getElementById('testimonialModal').classList.add('active'); }
        document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); }));
    </script>
</body>
</html>
