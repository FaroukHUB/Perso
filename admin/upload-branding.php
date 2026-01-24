<?php
/**
 * PERSONNALY - Upload d'images pour Branding
 * Endpoint AJAX pour logos et favicon
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Auth.php';

header('Content-Type: application/json');

// Vérification authentification
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Vérification CSRF
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
    exit;
}

// Vérification fichier
if (empty($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu']);
    exit;
}

$file = $_FILES['file'];

// Vérification taille (max 2MB pour branding)
$maxSize = 2 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 2MB)']);
    exit;
}

// Vérification type MIME
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WEBP, SVG, ICO']);
    exit;
}

// Création du dossier d'upload
$uploadDir = __DIR__ . '/../public/uploads/branding/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Génération du nom de fichier
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext === 'ico') {
    $filename = 'favicon_' . uniqid() . '.' . $ext;
} else {
    $filename = 'logo_' . date('Ymd') . '_' . uniqid() . '.' . $ext;
}
$filepath = $uploadDir . $filename;

// Upload
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $url = '/public/uploads/branding/' . $filename;
    echo json_encode([
        'success' => true,
        'url' => $url
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'upload']);
}
