<?php
/**
 * PERSONNALY - API : Images techniques
 * Retourne les images d'une technique en JSON
 *
 * Usage: /api/technique-images.php?technique=broderie
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/CustomizationOption.php';

$technique = $_GET['technique'] ?? '';

if (empty($technique)) {
    echo json_encode(['success' => false, 'error' => 'Technique non spécifiée']);
    exit;
}

$optionModel = new CustomizationOption();
$techniques = $optionModel->findByType('technique');

// Recherche flexible : par value OU par label (insensible à la casse)
$techLower = strtolower($technique);
$found = null;

foreach ($techniques as $tech) {
    $valueLower = strtolower($tech['value']);
    $labelLower = strtolower($tech['label']);

    // Match exact sur value
    if ($valueLower === $techLower) {
        $found = $tech;
        break;
    }
    // Match si le label contient la technique recherchée
    if (strpos($labelLower, $techLower) !== false) {
        $found = $tech;
        break;
    }
    // Match si la value contient la technique recherchée
    if (strpos($valueLower, $techLower) !== false) {
        $found = $tech;
        break;
    }
    // Match inversé : si la technique recherchée contient la value
    if (strpos($techLower, $valueLower) !== false) {
        $found = $tech;
        break;
    }
}

if (!$found) {
    echo json_encode([
        'success' => true,
        'technique' => $technique,
        'label' => ucfirst($technique),
        'description' => '',
        'images' => []
    ]);
    exit;
}

$images = [];
if (!empty($found['images_json'])) {
    $images = json_decode($found['images_json'], true) ?: [];
}

echo json_encode([
    'success' => true,
    'technique' => $found['value'],
    'label' => $found['label'],
    'description' => $found['description'] ?? '',
    'images' => $images
]);
