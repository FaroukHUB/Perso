<?php
/**
 * PERSONNALY - Admin : Formulaire Article de Blog
 * Création / Modification avec éditeur WYSIWYG
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/ImageHelper.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/BlogPost.php';

Auth::requireAdmin();

$blogModel = new BlogPost();
$statuses = $blogModel->getStatuses();

// Mode édition ou création
$editMode = false;
$post = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $post = $blogModel->findById((int)$_GET['id']);
    if ($post) {
        $editMode = true;
    }
}

// Traitement du formulaire
$success = '';
$error = '';

if (isPost()) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'excerpt' => trim($_POST['excerpt'] ?? ''),
            'content' => $_POST['content'] ?? '', // HTML autorisé
            'status' => $_POST['status'] ?? 'draft'
        ];

        // Upload image de couverture
        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../public/uploads/blog/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($ext, $allowed)) {
                $filename = 'blog_' . time() . '_' . uniqid() . '.' . $ext;
                $fullPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $fullPath)) {
                    // Générer version WebP
                    ImageHelper::convertToWebP($fullPath);
                    $data['cover_image_url'] = '/uploads/blog/' . $filename;
                }
            } else {
                $error = 'Format d\'image non autorisé. Utilisez JPG, PNG, GIF ou WebP.';
            }
        } elseif ($editMode && !empty($post['cover_image_url'])) {
            // Conserver l'image existante
            $data['cover_image_url'] = $post['cover_image_url'];
        }

        // Validation
        if (empty($data['title'])) {
            $error = 'Le titre est obligatoire.';
        } elseif (!array_key_exists($data['status'], $statuses)) {
            $error = 'Statut invalide.';
        } elseif (empty($error)) {
            try {
                if ($editMode) {
                    $blogModel->update($post['id'], $data);
                    $success = 'Article mis à jour avec succès.';
                    $post = $blogModel->findById($post['id']);
                } else {
                    $newId = $blogModel->create($data);
                    redirect('/admin/blog-form.php?id=' . $newId . '&success=1');
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
            }
        }
    }
}

// Message de succès après redirection
if (isset($_GET['success'])) {
    $success = 'Article créé avec succès.';
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editMode ? 'Modifier' : 'Créer' ?> un article - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <!-- Quill WYSIWYG Editor (gratuit, sans clé API) -->
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">
                    <?php if ($editMode): ?>
                        Modifier <span>l'article</span>
                    <?php else: ?>
                        Nouvel <span>article</span>
                    <?php endif; ?>
                </h1>
                <a href="/admin/blog.php" class="btn btn-secondary">
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

            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-grid">
                    <!-- Colonne principale -->
                    <div class="form-main">
                        <div class="data-card">
                            <div class="data-card-header">
                                <h3 class="data-card-title">Informations de l'article</h3>
                            </div>
                            <div class="data-card-body">
                                <div class="form-group">
                                    <label for="title">Titre *</label>
                                    <input type="text" id="title" name="title" required
                                           value="<?= h($post['title'] ?? '') ?>"
                                           placeholder="Titre de l'article">
                                </div>

                                <div class="form-group">
                                    <label for="excerpt">Extrait (affiché dans le slider)</label>
                                    <textarea id="excerpt" name="excerpt" rows="3"
                                              placeholder="Résumé court pour le slider blog"><?= h($post['excerpt'] ?? '') ?></textarea>
                                    <small class="form-hint">Ce texte sera affiché dans les cartes du slider blog (max ~150 caractères recommandés)</small>
                                </div>
                            </div>
                        </div>

                        <div class="data-card">
                            <div class="data-card-header">
                                <h3 class="data-card-title">Contenu de l'article</h3>
                            </div>
                            <div class="data-card-body">
                                <div class="form-group">
                                    <label>Contenu complet</label>
                                    <input type="hidden" name="content" id="contentInput">
                                    <div id="quillEditor"><?= $post['content'] ?? '' ?></div>
                                    <small class="form-hint">Utilisez l'éditeur pour ajouter des titres (H1, H2, H3), images, liens, listes, etc.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne latérale -->
                    <div class="form-sidebar">
                        <div class="data-card">
                            <div class="data-card-header">
                                <h3 class="data-card-title">Publication</h3>
                            </div>
                            <div class="data-card-body">
                                <div class="form-group">
                                    <label for="status">Statut</label>
                                    <select id="status" name="status">
                                        <?php foreach ($statuses as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= ($post['status'] ?? 'draft') === $key ? 'selected' : '' ?>>
                                                <?= h($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php if ($editMode && $post['published_at']): ?>
                                    <div class="form-group">
                                        <label>Date de publication</label>
                                        <p class="form-static"><?= date('d/m/Y à H:i', strtotime($post['published_at'])) ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($editMode && $post['slug']): ?>
                                    <div class="form-group">
                                        <label>Slug URL</label>
                                        <p class="form-static text-muted"><?= h($post['slug']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="data-card">
                            <div class="data-card-header">
                                <h3 class="data-card-title">Image de couverture</h3>
                            </div>
                            <div class="data-card-body">
                                <div class="form-group">
                                    <label for="cover_image">Uploader une image</label>
                                    <input type="file" id="cover_image" name="cover_image"
                                           accept="image/jpeg,image/png,image/gif,image/webp">
                                    <small class="form-hint">JPG, PNG, GIF ou WebP. Ratio 16:9 recommandé.</small>
                                </div>

                                <div class="image-preview" id="imagePreview">
                                    <?php if (!empty($post['cover_image_url'])): ?>
                                        <img src="/public<?= h($post['cover_image_url']) ?>" alt="Aperçu">
                                    <?php else: ?>
                                        <div class="preview-placeholder">
                                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                <polyline points="21 15 16 10 5 21"/>
                                            </svg>
                                            <span>Aperçu de l'image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($editMode && !empty($post['cover_image_url'])): ?>
                                    <p class="text-muted" style="font-size: 12px; margin-top: 8px;">
                                        Image actuelle : <?= basename($post['cover_image_url']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-block">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                    <polyline points="17 21 17 13 7 13 7 21"/>
                                    <polyline points="7 3 7 8 15 8"/>
                                </svg>
                                <?= $editMode ? 'Enregistrer' : 'Créer l\'article' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        .form-main .data-card,
        .form-sidebar .data-card {
            margin-bottom: 20px;
        }
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
        .form-group textarea { resize: vertical; font-family: inherit; }
        .form-hint {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: var(--gray);
        }
        .form-static {
            margin: 0;
            padding: 10px 0;
            font-size: 14px;
        }
        .image-preview {
            margin-top: 12px;
            border: 2px dashed var(--gray-light);
            border-radius: var(--radius-md);
            overflow: hidden;
            aspect-ratio: 16/9;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-light);
        }
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .preview-placeholder {
            text-align: center;
            color: var(--gray);
        }
        .preview-placeholder span {
            display: block;
            margin-top: 8px;
            font-size: 13px;
        }
        .form-actions { margin-top: 0; }
        .btn-block { width: 100%; justify-content: center; }
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
        /* Quill Editor Styling */
        #quillEditor {
            min-height: 400px;
            background: #fff;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .ql-container {
            font-size: 16px;
            border-bottom-left-radius: var(--radius-md);
            border-bottom-right-radius: var(--radius-md);
        }
        .ql-toolbar {
            border-top-left-radius: var(--radius-md);
            border-top-right-radius: var(--radius-md);
            background: #fafafa;
        }
        .ql-editor {
            min-height: 350px;
            line-height: 1.7;
        }
        .ql-editor h1 { font-size: 2rem; font-weight: 700; margin: 1.5em 0 0.5em; }
        .ql-editor h2 { font-size: 1.5rem; font-weight: 700; margin: 1.5em 0 0.5em; }
        .ql-editor h3 { font-size: 1.25rem; font-weight: 600; margin: 1.5em 0 0.5em; }
        .ql-editor p { margin: 0 0 1em; }
        .ql-editor a { color: #FF69B4; }
        .ql-editor img { max-width: 100%; height: auto; border-radius: 8px; }
        .ql-editor blockquote {
            border-left: 4px solid #FF69B4;
            margin: 1.5em 0;
            padding: 1em 1.5em;
            background: #f9f9f9;
        }
        .ql-snow .ql-picker.ql-header .ql-picker-label::before,
        .ql-snow .ql-picker.ql-header .ql-picker-item::before {
            content: 'Paragraphe';
        }
        .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="1"]::before,
        .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="1"]::before {
            content: 'Titre 1';
        }
        .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="2"]::before,
        .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="2"]::before {
            content: 'Titre 2';
        }
        .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="3"]::before,
        .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="3"]::before {
            content: 'Titre 3';
        }
    </style>

    <!-- Quill Editor JS -->
    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <script>
    // Configuration Quill
    const toolbarOptions = [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'align': [] }],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        [{ 'indent': '-1'}, { 'indent': '+1' }],
        ['blockquote'],
        ['link', 'image'],
        ['clean']
    ];

    // Initialisation Quill
    const quill = new Quill('#quillEditor', {
        theme: 'snow',
        modules: {
            toolbar: toolbarOptions
        },
        placeholder: 'Rédigez votre article ici...'
    });

    // Upload d'image personnalisé
    function imageHandler() {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');
        input.click();

        input.onchange = async () => {
            const file = input.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('csrf_token', '<?= $csrf ?>');

                try {
                    const response = await fetch('/admin/upload-image.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success && result.url) {
                        const range = quill.getSelection(true);
                        quill.insertEmbed(range.index, 'image', result.url);
                        quill.setSelection(range.index + 1);
                    } else {
                        alert(result.error || 'Erreur lors de l\'upload');
                    }
                } catch (error) {
                    alert('Erreur réseau lors de l\'upload');
                }
            }
        };
    }

    // Ajouter handler personnalisé pour images
    quill.getModule('toolbar').addHandler('image', imageHandler);

    // Synchroniser le contenu avec le champ hidden avant soumission
    const form = document.querySelector('form');
    const contentInput = document.getElementById('contentInput');

    form.addEventListener('submit', function(e) {
        contentInput.value = quill.root.innerHTML;
    });

    // Prévisualisation de l'image uploadée (cover)
    document.getElementById('cover_image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('imagePreview');

        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Aperçu">';
            };
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html>
