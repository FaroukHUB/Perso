<?php
/**
 * PERSONNALY - Layout des pages légales
 * Template partagé pour CGV, Mentions légales, etc.
 */

// Variables attendues: $pageTitle, $pageContent, $legal, $siteName
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .legal-page {
            min-height: 100vh;
            background: linear-gradient(180deg, #fafafa 0%, #fff 100%);
        }
        .legal-header {
            background: linear-gradient(135deg, var(--pink-main) 0%, var(--mint-main) 100%);
            padding: 60px 0 80px;
            text-align: center;
            color: white;
        }
        .legal-header h1 {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
        }
        .legal-header .breadcrumb {
            margin-top: 15px;
            font-size: 14px;
            opacity: 0.9;
        }
        .legal-header .breadcrumb a {
            color: white;
            text-decoration: none;
        }
        .legal-header .breadcrumb a:hover {
            text-decoration: underline;
        }
        .legal-container {
            max-width: 800px;
            margin: -40px auto 60px;
            padding: 0 20px;
        }
        .legal-content {
            background: white;
            border-radius: 16px;
            padding: 50px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .legal-content h2 {
            font-family: var(--font-display);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--black-soft);
            margin: 40px 0 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--pink-light);
        }
        .legal-content h2:first-child {
            margin-top: 0;
        }
        .legal-content h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--black-soft);
            margin: 25px 0 12px;
        }
        .legal-content p {
            color: #555;
            line-height: 1.8;
            margin-bottom: 16px;
        }
        .legal-content ul, .legal-content ol {
            color: #555;
            line-height: 1.8;
            margin: 16px 0;
            padding-left: 24px;
        }
        .legal-content li {
            margin-bottom: 8px;
        }
        .legal-content strong {
            color: var(--black-soft);
        }
        .legal-content a {
            color: var(--pink-main);
            text-decoration: none;
        }
        .legal-content a:hover {
            text-decoration: underline;
        }
        .legal-info-box {
            background: linear-gradient(135deg, rgba(255,105,180,0.08) 0%, rgba(61,255,192,0.08) 100%);
            border-left: 4px solid var(--pink-main);
            padding: 20px 24px;
            border-radius: 8px;
            margin: 24px 0;
        }
        .legal-info-box p {
            margin: 0;
        }
        .legal-meta {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #888;
        }
        @media (max-width: 768px) {
            .legal-header {
                padding: 40px 20px 60px;
            }
            .legal-header h1 {
                font-size: 1.8rem;
            }
            .legal-content {
                padding: 30px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="legal-page">
        <!-- Header -->
        <header class="legal-header">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <div class="breadcrumb">
                <a href="/">Accueil</a> / <?= htmlspecialchars($pageTitle) ?>
            </div>
        </header>

        <!-- Content -->
        <div class="legal-container">
            <div class="legal-content">
                <?= $pageContent ?>

                <div class="legal-meta">
                    Dernière mise à jour : <?= date('d/m/Y') ?>
                </div>
            </div>
        </div>

        <!-- Footer simple -->
        <footer style="text-align: center; padding: 30px; color: #888; font-size: 13px;">
            <a href="/" style="color: var(--pink-main); text-decoration: none; font-weight: 600;">
                <?= htmlspecialchars($siteName) ?>
            </a>
            <span style="margin: 0 10px;">|</span>
            <a href="/mentions-legales" style="color: #888; text-decoration: none;">Mentions légales</a>
            <span style="margin: 0 10px;">|</span>
            <a href="/cgv" style="color: #888; text-decoration: none;">CGV</a>
            <span style="margin: 0 10px;">|</span>
            <a href="/politique-confidentialite" style="color: #888; text-decoration: none;">Confidentialité</a>
        </footer>
    </div>
</body>
</html>
