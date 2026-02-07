<?php
/**
 * PERSONNALY - API: Sauvegarder l'image de preview de personnalisation
 * Reçoit une image en base64 et la sauvegarde sur le serveur
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../app/helpers/functions.php';

// Vérifier la méthode
if (!isPost()) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Image manquante']);
    exit;
}

try {
    // Extraire l'image base64
    $imageData = $data['image'];

    // Vérifier le format (data:image/png;base64,...)
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/i', $imageData, $matches)) {
        throw new Exception('Format d\'image invalide');
    }

    $imageType = $matches[1];
    $base64String = $matches[2];

    // Décoder l'image
    $imageContent = base64_decode($base64String);
    if ($imageContent === false) {
        throw new Exception('Erreur de décodage de l\'image');
    }

    // Créer le dossier si nécessaire
    $uploadDir = __DIR__ . '/../../uploads/previews/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Générer un nom de fichier unique
    $filename = 'preview_' . uniqid() . '_' . time() . '.' . $imageType;
    $filepath = $uploadDir . $filename;

    // Sauvegarder l'image
    if (file_put_contents($filepath, $imageContent) === false) {
        throw new Exception('Erreur lors de la sauvegarde de l\'image');
    }

    // Retourner l'URL relative
    $relativeUrl = '/uploads/previews/' . $filename;

    echo json_encode([
        'success' => true,
        'url' => $relativeUrl,
        'filename' => $filename
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
