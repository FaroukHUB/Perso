<?php
/**
 * PERSONNALY Admin - Initialisation des polices Google populaires
 * Ce script ajoute les polices populaires si elles n'existent pas déjà
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/models/Font.php';

Auth::requireAdmin();

$fontModel = new Font();

// Liste des polices Google les plus populaires
$googleFonts = [
    // Sans-serif populaires
    ['name' => 'Inter', 'family' => 'Inter', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Roboto', 'family' => 'Roboto', 'weights' => '400;500;700', 'category' => 'sans-serif'],
    ['name' => 'Open Sans', 'family' => 'Open Sans', 'weights' => '400;600;700', 'category' => 'sans-serif'],
    ['name' => 'Lato', 'family' => 'Lato', 'weights' => '400;700;900', 'category' => 'sans-serif'],
    ['name' => 'Montserrat', 'family' => 'Montserrat', 'weights' => '400;500;600;700;800', 'category' => 'sans-serif'],
    ['name' => 'Nunito', 'family' => 'Nunito', 'weights' => '400;600;700;800', 'category' => 'sans-serif'],
    ['name' => 'Nunito Sans', 'family' => 'Nunito Sans', 'weights' => '400;600;700', 'category' => 'sans-serif'],
    ['name' => 'Raleway', 'family' => 'Raleway', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Work Sans', 'family' => 'Work Sans', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Rubik', 'family' => 'Rubik', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Quicksand', 'family' => 'Quicksand', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Source Sans 3', 'family' => 'Source Sans 3', 'weights' => '400;600;700', 'category' => 'sans-serif'],
    ['name' => 'Barlow', 'family' => 'Barlow', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Manrope', 'family' => 'Manrope', 'weights' => '400;500;600;700;800', 'category' => 'sans-serif'],
    ['name' => 'DM Sans', 'family' => 'DM Sans', 'weights' => '400;500;700', 'category' => 'sans-serif'],
    ['name' => 'Space Grotesk', 'family' => 'Space Grotesk', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Karla', 'family' => 'Karla', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Mulish', 'family' => 'Mulish', 'weights' => '400;500;600;700;800', 'category' => 'sans-serif'],
    ['name' => 'Ubuntu', 'family' => 'Ubuntu', 'weights' => '400;500;700', 'category' => 'sans-serif'],
    ['name' => 'Cabin', 'family' => 'Cabin', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Archivo', 'family' => 'Archivo', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Figtree', 'family' => 'Figtree', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Plus Jakarta Sans', 'family' => 'Plus Jakarta Sans', 'weights' => '400;500;600;700;800', 'category' => 'sans-serif'],
    ['name' => 'Outfit', 'family' => 'Outfit', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],
    ['name' => 'Sora', 'family' => 'Sora', 'weights' => '400;500;600;700', 'category' => 'sans-serif'],

    // Serif populaires
    ['name' => 'Playfair Display', 'family' => 'Playfair Display', 'weights' => '400;500;600;700', 'category' => 'serif'],
    ['name' => 'Merriweather', 'family' => 'Merriweather', 'weights' => '400;700;900', 'category' => 'serif'],
    ['name' => 'Lora', 'family' => 'Lora', 'weights' => '400;500;600;700', 'category' => 'serif'],
    ['name' => 'Libre Baskerville', 'family' => 'Libre Baskerville', 'weights' => '400;700', 'category' => 'serif'],
    ['name' => 'PT Serif', 'family' => 'PT Serif', 'weights' => '400;700', 'category' => 'serif'],
    ['name' => 'Roboto Slab', 'family' => 'Roboto Slab', 'weights' => '400;500;700', 'category' => 'serif'],
    ['name' => 'Source Serif 4', 'family' => 'Source Serif 4', 'weights' => '400;600;700', 'category' => 'serif'],
    ['name' => 'Crimson Text', 'family' => 'Crimson Text', 'weights' => '400;600;700', 'category' => 'serif'],
    ['name' => 'EB Garamond', 'family' => 'EB Garamond', 'weights' => '400;500;600;700', 'category' => 'serif'],
    ['name' => 'Cormorant Garamond', 'family' => 'Cormorant Garamond', 'weights' => '400;500;600;700', 'category' => 'serif'],
    ['name' => 'Spectral', 'family' => 'Spectral', 'weights' => '400;500;600;700', 'category' => 'serif'],
    ['name' => 'DM Serif Display', 'family' => 'DM Serif Display', 'weights' => '400', 'category' => 'serif'],
    ['name' => 'Bitter', 'family' => 'Bitter', 'weights' => '400;500;600;700', 'category' => 'serif'],
    ['name' => 'Cardo', 'family' => 'Cardo', 'weights' => '400;700', 'category' => 'serif'],
    ['name' => 'Noto Serif', 'family' => 'Noto Serif', 'weights' => '400;700', 'category' => 'serif'],

    // Display / Titres
    ['name' => 'Bebas Neue', 'family' => 'Bebas Neue', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Oswald', 'family' => 'Oswald', 'weights' => '400;500;600;700', 'category' => 'display'],
    ['name' => 'Anton', 'family' => 'Anton', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Abril Fatface', 'family' => 'Abril Fatface', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Righteous', 'family' => 'Righteous', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Fredoka', 'family' => 'Fredoka', 'weights' => '400;500;600;700', 'category' => 'display'],
    ['name' => 'Staatliches', 'family' => 'Staatliches', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Bungee', 'family' => 'Bungee', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Monoton', 'family' => 'Monoton', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Titan One', 'family' => 'Titan One', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Black Ops One', 'family' => 'Black Ops One', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Permanent Marker', 'family' => 'Permanent Marker', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Russo One', 'family' => 'Russo One', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Concert One', 'family' => 'Concert One', 'weights' => '400', 'category' => 'display'],
    ['name' => 'Bangers', 'family' => 'Bangers', 'weights' => '400', 'category' => 'display'],

    // Script / Cursive
    ['name' => 'Pacifico', 'family' => 'Pacifico', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Lobster', 'family' => 'Lobster', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Great Vibes', 'family' => 'Great Vibes', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Sacramento', 'family' => 'Sacramento', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Satisfy', 'family' => 'Satisfy', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Courgette', 'family' => 'Courgette', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Kaushan Script', 'family' => 'Kaushan Script', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Parisienne', 'family' => 'Parisienne', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Tangerine', 'family' => 'Tangerine', 'weights' => '400;700', 'category' => 'script'],
    ['name' => 'Alex Brush', 'family' => 'Alex Brush', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Allura', 'family' => 'Allura', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Rouge Script', 'family' => 'Rouge Script', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Pinyon Script', 'family' => 'Pinyon Script', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Italianno', 'family' => 'Italianno', 'weights' => '400', 'category' => 'script'],
    ['name' => 'Niconne', 'family' => 'Niconne', 'weights' => '400', 'category' => 'script'],

    // Handwriting
    ['name' => 'Dancing Script', 'family' => 'Dancing Script', 'weights' => '400;500;600;700', 'category' => 'handwriting'],
    ['name' => 'Caveat', 'family' => 'Caveat', 'weights' => '400;500;600;700', 'category' => 'handwriting'],
    ['name' => 'Indie Flower', 'family' => 'Indie Flower', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Shadows Into Light', 'family' => 'Shadows Into Light', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Amatic SC', 'family' => 'Amatic SC', 'weights' => '400;700', 'category' => 'handwriting'],
    ['name' => 'Patrick Hand', 'family' => 'Patrick Hand', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Architects Daughter', 'family' => 'Architects Daughter', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Gloria Hallelujah', 'family' => 'Gloria Hallelujah', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Kalam', 'family' => 'Kalam', 'weights' => '400;700', 'category' => 'handwriting'],
    ['name' => 'Handlee', 'family' => 'Handlee', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Just Another Hand', 'family' => 'Just Another Hand', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Rock Salt', 'family' => 'Rock Salt', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Reenie Beanie', 'family' => 'Reenie Beanie', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Coming Soon', 'family' => 'Coming Soon', 'weights' => '400', 'category' => 'handwriting'],
    ['name' => 'Covered By Your Grace', 'family' => 'Covered By Your Grace', 'weights' => '400', 'category' => 'handwriting'],
];

$added = 0;
$skipped = 0;
$errors = [];

foreach ($googleFonts as $index => $font) {
    // Générer le css_key
    $cssKey = $fontModel->generateCssKey($font['family'], $font['weights']);

    // Vérifier si la police existe déjà
    if ($fontModel->cssKeyExists($cssKey)) {
        $skipped++;
        continue;
    }

    try {
        $fontModel->create([
            'name' => $font['name'],
            'family' => $font['family'],
            'source' => 'google',
            'google_weights' => $font['weights'],
            'category' => $font['category'],
            'active' => 1,
            'sort_order' => $index + 10
        ]);
        $added++;
    } catch (Exception $e) {
        $errors[] = $font['name'] . ': ' . $e->getMessage();
    }
}

// Régénérer toutes les URLs Google
$fontModel->regenerateAllGoogleUrls();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Initialisation des polices - PERSONNALY</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 40px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #1a1a2e; }
        .stat { padding: 15px; border-radius: 8px; margin-bottom: 10px; }
        .stat-success { background: #d4edda; color: #155724; }
        .stat-info { background: #d1ecf1; color: #0c5460; }
        .stat-error { background: #f8d7da; color: #721c24; }
        .back-link { display: inline-block; margin-top: 20px; padding: 12px 24px; background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; text-decoration: none; border-radius: 8px; }
        .back-link:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(255,105,180,0.4); }
        ul { margin: 10px 0; padding-left: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Initialisation des polices</h1>

        <div class="stat stat-success">
            <strong><?= $added ?></strong> polices ajoutées
        </div>

        <div class="stat stat-info">
            <strong><?= $skipped ?></strong> polices déjà présentes (ignorées)
        </div>

        <?php if (!empty($errors)): ?>
        <div class="stat stat-error">
            <strong><?= count($errors) ?></strong> erreurs :
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <p>Total des polices disponibles : <strong><?= count($fontModel->findAll()) ?></strong></p>

        <a href="/admin/homepage-builder.php" class="back-link">Retour au Page Builder</a>
    </div>
</body>
</html>
