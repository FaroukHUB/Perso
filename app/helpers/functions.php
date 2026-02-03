<?php
/**
 * PERSONNALY - Fonctions utilitaires
 */

/**
 * Échappe une chaîne pour affichage HTML
 */
function h(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitise une URL pour prévenir les injections XSS
 * Bloque les protocoles dangereux (javascript:, data:, vbscript:)
 * Accepte les URLs relatives, http://, https://, mailto:, tel:
 */
function sanitizeUrl(?string $url): string
{
    if ($url === null || $url === '') {
        return '';
    }

    $url = trim($url);

    // Protocoles autorisés
    $allowedProtocols = ['http://', 'https://', 'mailto:', 'tel:', '/', '#'];

    // Vérifier si l'URL commence par un protocole autorisé ou est relative
    $isAllowed = false;
    foreach ($allowedProtocols as $protocol) {
        if (strpos($url, $protocol) === 0) {
            $isAllowed = true;
            break;
        }
    }

    // Si pas de protocole reconnu, vérifier que ce n'est pas un protocole dangereux
    if (!$isAllowed) {
        $lowercaseUrl = strtolower($url);
        $dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:'];
        foreach ($dangerousProtocols as $dangerous) {
            if (strpos($lowercaseUrl, $dangerous) === 0) {
                return '#'; // Retourner un lien vide/sûr
            }
        }
        // URL relative sans protocole - autorisé
        $isAllowed = true;
    }

    return $isAllowed ? $url : '#';
}

/**
 * Redirige vers une URL
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Retourne une réponse JSON
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Vérifie si la requête est en POST
 */
function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Récupère une valeur POST sécurisée
 */
function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/**
 * Récupère une valeur GET sécurisée
 */
function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/**
 * Génère un token CSRF
 */
function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF
 */
function verifyCsrf(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Affiche un champ hidden CSRF
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

/**
 * Alias pour csrfToken() - compatibilité
 */
function generateCsrf(): string
{
    return csrfToken();
}

/**
 * Formate un prix
 */
function formatPrice(?float $price): string
{
    if ($price === null) {
        return '0,00 €';
    }
    return number_format($price, 2, ',', ' ') . ' €';
}

/**
 * Formate une date
 */
function formatDate(string $date, string $format = 'd/m/Y H:i'): string
{
    return date($format, strtotime($date));
}

/**
 * Génère un slug à partir d'une chaîne
 */
function slugify(string $string): string
{
    $string = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
    $string = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return strtolower(trim($string, '-'));
}

/**
 * Génère une balise <picture> avec fallback WebP
 * Utilise ImageHelper pour la logique
 *
 * @param string $url URL de l'image (sans /public)
 * @param string $alt Texte alternatif
 * @param string $class Classes CSS
 * @return string HTML
 */
function picture(string $url, string $alt = '', string $class = ''): string
{
    // Charger ImageHelper si pas déjà fait
    if (!class_exists('ImageHelper')) {
        require_once __DIR__ . '/ImageHelper.php';
    }
    return ImageHelper::pictureTag($url, $alt, $class);
}
