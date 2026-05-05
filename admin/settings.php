<?php
/**
 * PERSONNALY - Admin : Paramètres
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/SiteSetting.php';

Auth::requireAdmin();

$orderModel = new Order();
$pendingOrders = $orderModel->countNew();
$user = Auth::getUser();
$settingModel = new SiteSetting();

$success = '';
$error = '';

// Sauvegarde du logo
if (isPost() && isset($_POST['save_logo'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $logoType = post('logo_type', 'text');
        $logoText = trim(post('logo_text', 'PERSONNALY'));

        $imageUrl = $settingModel->get('logo_image_url');

        // Upload nouvelle image
        if (!empty($_FILES['logo_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../public/uploads/logo/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['logo_image']['name'], PATHINFO_EXTENSION));
            $filename = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo_image']['tmp_name'], $uploadDir . $filename)) {
                $imageUrl = '/uploads/logo/' . $filename;
            }
        }

        $settingModel->setLogo($logoType, $logoText, $imageUrl);
        $success = 'Logo mis à jour avec succès.';
    }
}

// Changement de mot de passe
if (isPost() && isset($_POST['change_password'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $current = post('current_password', '');
        $new = post('new_password', '');
        $confirm = post('confirm_password', '');

        if (empty($current) || empty($new)) {
            $error = 'Veuillez remplir tous les champs.';
        } elseif ($new !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($new) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            // Vérifier l'ancien mot de passe
            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();

            if (Auth::verifyPassword($current, $userData['password_hash'])) {
                $newHash = Auth::hashPassword($new);
                $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([$newHash, $user['id']]);
                $success = 'Mot de passe mis à jour avec succès.';
            } else {
                $error = 'Mot de passe actuel incorrect.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - PERSONNALY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title"><span>Paramètres</span></h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <?php $logo = $settingModel->getLogo(); ?>
            <div class="settings-grid">
                <!-- Logo -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Logo du site</h3>
                    </div>
                    <form method="post" enctype="multipart/form-data" style="padding: var(--spacing-lg);">
                        <?= csrfField() ?>
                        <input type="hidden" name="save_logo" value="1">

                        <div class="form-group">
                            <label class="form-label">Type de logo</label>
                            <div style="display: flex; gap: 15px; margin-top: 8px;">
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="radio" name="logo_type" value="text" <?= $logo['type'] === 'text' ? 'checked' : '' ?> onchange="toggleLogoType()">
                                    Texte
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="radio" name="logo_type" value="image" <?= $logo['type'] === 'image' ? 'checked' : '' ?> onchange="toggleLogoType()">
                                    Image
                                </label>
                            </div>
                        </div>

                        <div class="form-group" id="logoTextGroup">
                            <label class="form-label">Texte du logo</label>
                            <input type="text" name="logo_text" class="form-input" value="<?= h($logo['text']) ?>" placeholder="PERSONNALY">
                        </div>

                        <div class="form-group" id="logoImageGroup">
                            <label class="form-label">Image du logo</label>
                            <?php if (!empty($logo['image_url'])): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="/public<?= h($logo['image_url']) ?>" alt="Logo actuel" style="max-height: 60px; border-radius: 8px; background: #f5f5f5; padding: 8px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="logo_image" class="form-input" accept="image/*">
                            <small style="color: var(--gray); font-size: 12px;">PNG ou SVG transparent recommandé (max 200px de haut)</small>
                        </div>

                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </form>
                </div>

                <!-- Compte -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Mon compte</h3>
                    </div>
                    <div style="padding: var(--spacing-lg);">
                        <p><strong>Email :</strong> <?= h($user['email']) ?></p>
                        <p><strong>Rôle :</strong> <span class="badge badge-pink">Admin</span></p>
                    </div>
                </div>

                <!-- Changer mot de passe -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Changer le mot de passe</h3>
                    </div>
                    <form method="post" style="padding: var(--spacing-lg);">
                        <?= csrfField() ?>
                        <input type="hidden" name="change_password" value="1">

                        <div class="form-group">
                            <label class="form-label">Mot de passe actuel</label>
                            <input type="password" name="current_password" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="form-input" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirmer le mot de passe</label>
                            <input type="password" name="confirm_password" class="form-input" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <style>
        .alert { padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); font-weight: 500; }
        .alert-success { background: rgba(61, 255, 192, 0.15); color: var(--mint-dark); border-left: 4px solid var(--mint-main); }
        .alert-error { background: rgba(255, 105, 180, 0.15); color: var(--pink-dark); border-left: 4px solid var(--pink-main); }
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: var(--spacing-lg); }
    </style>
    <script>
        function toggleLogoType() {
            const type = document.querySelector('input[name="logo_type"]:checked').value;
            document.getElementById('logoTextGroup').style.display = type === 'text' ? 'block' : 'none';
            document.getElementById('logoImageGroup').style.display = type === 'image' ? 'block' : 'none';
        }
        toggleLogoType();
    </script>
</body>
</html>
