<?php
/**
 * PERSONNALY - API Soumission Formulaire de Contact
 * Endpoint: POST /api/contact/submit.php
 */

require_once __DIR__ . '/../../../app/helpers/functions.php';
require_once __DIR__ . '/../../../app/core/Database.php';
require_once __DIR__ . '/../../../app/models/ContactSubmission.php';
require_once __DIR__ . '/../../../app/services/BrandingService.php';

header('Content-Type: application/json; charset=utf-8');

// Vérifier méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$sectionId = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
$pageSlug = trim($_POST['page_slug'] ?? '');

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Le nom est requis';
}

if (empty($email)) {
    $errors[] = 'L\'email est requis';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'L\'email n\'est pas valide';
}

if (empty($message)) {
    $errors[] = 'Le message est requis';
}

if (strlen($message) < 10) {
    $errors[] = 'Le message est trop court (minimum 10 caractères)';
}

if (strlen($message) > 5000) {
    $errors[] = 'Le message est trop long (maximum 5000 caractères)';
}

// Anti-spam basique : honeypot (si un champ caché "website" est rempli, c'est un bot)
if (!empty($_POST['website'])) {
    // Silencieusement ignorer les soumissions de bot
    echo json_encode(['success' => true, 'message' => 'Message envoyé avec succès']);
    exit;
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Anti-spam : limite de soumissions
$contactModel = new ContactSubmission();

if ($contactModel->hasRecentSubmissions($email, 10, 3)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Vous avez envoyé trop de messages récemment. Veuillez réessayer dans quelques minutes.'
    ]);
    exit;
}

// Enregistrer en base de données
try {
    $submissionId = $contactModel->create([
        'section_id' => $sectionId,
        'page_slug' => $pageSlug,
        'name' => $name,
        'email' => $email,
        'phone' => $phone ?: null,
        'subject' => $subject ?: null,
        'message' => $message
    ]);

    // Envoyer un email de notification (optionnel)
    try {
        $brandingService = new BrandingService();
        $siteName = $brandingService->get('site_name') ?? 'PERSONNALY';
        $adminEmail = $brandingService->get('contact_email');

        if ($adminEmail) {
            $emailSubject = "[Contact $siteName] " . ($subject ?: 'Nouveau message');
            $emailBody = "Nouveau message de contact reçu sur $siteName\n\n";
            $emailBody .= "Nom: $name\n";
            $emailBody .= "Email: $email\n";
            if ($phone) {
                $emailBody .= "Téléphone: $phone\n";
            }
            if ($subject) {
                $emailBody .= "Sujet: $subject\n";
            }
            $emailBody .= "\nMessage:\n$message\n";
            $emailBody .= "\n---\nPage source: $pageSlug";

            $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'personnaly.fr') . "\r\n";
            $headers .= "Reply-To: $email\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            @mail($adminEmail, $emailSubject, $emailBody, $headers);
        }
    } catch (Exception $e) {
        // Ignorer les erreurs d'envoi d'email
    }

    echo json_encode([
        'success' => true,
        'message' => 'Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Une erreur est survenue lors de l\'envoi du message. Veuillez réessayer.'
    ]);
}
