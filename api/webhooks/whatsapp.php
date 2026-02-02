<?php
/**
 * PERSONNALY - WhatsApp Webhook
 * Reçoit les notifications de Meta (messages, statuts)
 */

require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/services/WhatsAppService.php';

header('Content-Type: application/json');

$whatsapp = new WhatsAppService();

// GET : Vérification du webhook par Meta
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    $result = $whatsapp->verifyWebhook($mode, $token, $challenge);

    if ($result !== null) {
        http_response_code(200);
        echo $result;
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Verification failed']);
    }
    exit;
}

// POST : Réception des événements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $payload = json_decode($input, true);

    if (!$payload) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    // Traiter le webhook
    $result = $whatsapp->handleWebhook($payload);

    // Log pour debug (optionnel)
    if (!empty($result['messages'])) {
        // Vous pouvez ajouter ici la logique pour traiter les messages reçus
        // Par exemple : sauvegarder en BDD, notifier l'admin, réponse automatique, etc.
        error_log('WhatsApp incoming: ' . json_encode($result['messages']));
    }

    // Toujours répondre 200 OK à Meta
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

// Autre méthode
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
