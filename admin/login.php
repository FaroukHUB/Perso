<?php
/**
 * PERSONNALY - Admin Login
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #1a1a2e;
        }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 14px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover { background: #5a6fd6; }
        .error {
            background: #ffe6e6;
            color: #c00;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a { color: #667eea; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>PERSONNALY<br><small style="font-size: 14px; color: #666;">Administration</small></h1>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrfField() ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?= h(post('email', '')) ?>">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit">Se connecter</button>
        </form>

        <div class="back-link">
            <a href="/">← Retour au site</a>
        </div>
    </div>
</body>
</html>
