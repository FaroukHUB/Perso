<?php
/**
 * PERSONNALY - Admin Login
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/User.php';

// Si déjà connecté, rediriger vers le dashboard
if (Auth::isAdmin()) {
    redirect('/admin/dashboard.php');
}

$error = '';

// Traitement du formulaire
if (isPost()) {
    $email = trim(post('email', ''));
    $password = post('password', '');
    $csrf = post('csrf_token', '');

    // Vérification CSRF
    if (!verifyCsrf($csrf)) {
        $error = 'Session expirée. Veuillez réessayer.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if ($user && Auth::verifyPassword($password, $user['password_hash'])) {
            if ($user['role'] === 'admin') {
                Auth::login($user);
                redirect('/admin/dashboard.php');
            } else {
                $error = 'Accès réservé aux administrateurs.';
            }
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - PERSONNALY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-header">
            <h1>PERSONNALY</h1>
            <p>Administration</p>
        </div>

        <?php if ($error): ?>
            <div class="login-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="login-form">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-input"
                       placeholder="votre@email.com" required
                       value="<?= h(post('email', '')) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Mot de passe</label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary">
                Se connecter
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>
        </form>

        <div class="login-footer">
            <a href="/">← Retour au site</a>
        </div>
    </div>
</body>
</html>
