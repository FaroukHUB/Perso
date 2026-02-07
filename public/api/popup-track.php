<?php
/**
 * API de tracking des popups
 * Enregistre les vues, clics et fermetures
 */

require_once __DIR__ . '/../../app/core/Database.php';
require_once __DIR__ . '/../../app/models/Popup.php';

header('Content-Type: application/json');

// Lire les données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['popup_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$popupId = (int)$data['popup_id'];
$action = $data['action'];
$sessionId = $data['session_id'] ?? null;
$pageUrl = $data['page_url'] ?? null;

try {
    $popupModel = new Popup();

    // Mettre à jour les compteurs
    switch ($action) {
        case 'view':
            $popupModel->incrementViews($popupId);
            break;
        case 'click':
            $popupModel->incrementClicks($popupId);
            break;
        case 'close':
        case 'never_show':
            $popupModel->incrementCloses($popupId);
            break;
    }

    // Enregistrer dans popup_views pour analytics détaillés
    $popupModel->trackAction($popupId, $action, [
        'session_id' => $sessionId,
        'page_url' => $pageUrl,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
