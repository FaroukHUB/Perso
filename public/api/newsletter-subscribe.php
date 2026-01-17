<?php
/**
 * PERSONNALY - API Newsletter Subscription
 * Endpoint AJAX pour l'inscription à la newsletter
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

require_once __DIR__ . '/../../app/core/Database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$source = preg_replace('/[^a-zA-Z0-9_-]/', '', $input['source'] ?? 'homepage');

// Validate email
if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Adresse email invalide']);
    exit;
}

try {
    $db = Database::getInstance();

    // Check if email already exists
    $stmt = $db->prepare('SELECT id, status FROM newsletter_subscribers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'active') {
            echo json_encode([
                'success' => true,
                'message' => 'Vous êtes déjà inscrit à notre newsletter !'
            ]);
        } else {
            // Reactivate subscription
            $stmt = $db->prepare('UPDATE newsletter_subscribers SET status = "active", updated_at = NOW() WHERE id = ?');
            $stmt->execute([$existing['id']]);
            echo json_encode([
                'success' => true,
                'message' => 'Bienvenue de retour ! Votre inscription a été réactivée.'
            ]);
        }
    } else {
        // Insert new subscriber
        $stmt = $db->prepare('INSERT INTO newsletter_subscribers (email, source, status, created_at) VALUES (?, ?, "active", NOW())');
        $stmt->execute([$email, $source]);

        echo json_encode([
            'success' => true,
            'message' => 'Merci pour votre inscription ! Vous recevrez bientôt nos meilleures offres.'
        ]);
    }

} catch (PDOException $e) {
    // Handle duplicate key error (race condition)
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success' => true,
            'message' => 'Vous êtes déjà inscrit à notre newsletter !'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Une erreur est survenue. Veuillez réessayer plus tard.'
        ]);
        // Log error in production
        error_log('Newsletter subscription error: ' . $e->getMessage());
    }
}
