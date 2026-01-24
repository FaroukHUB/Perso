<?php
/**
 * PERSONNALY - API Editor Text Colors
 * GET /public/api/editor/colors.php
 *
 * Retourne les couleurs de texte disponibles depuis l'admin
 * (type = 'text_color' dans customization_options)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verifier methode GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode non autorisee']);
    exit;
}

// Charger le modele
require_once __DIR__ . '/../../../app/core/Database.php';
require_once __DIR__ . '/../../../app/models/CustomizationOption.php';

try {
    $optionModel = new CustomizationOption();
    $textColors = $optionModel->getTextColors();

    $colorsData = [];

    foreach ($textColors as $color) {
        $colorsData[] = [
            'id' => (int) $color['id'],
            'value' => $color['value'],
            'label' => $color['label'],
            'hex' => $color['hex_code'] ?? '#000000'
        ];
    }

    // Si aucune couleur definie, retourner des couleurs par defaut
    if (empty($colorsData)) {
        $colorsData = [
            ['id' => 0, 'value' => 'black', 'label' => 'Noir', 'hex' => '#000000'],
            ['id' => 0, 'value' => 'white', 'label' => 'Blanc', 'hex' => '#FFFFFF'],
            ['id' => 0, 'value' => 'red', 'label' => 'Rouge', 'hex' => '#E53935'],
            ['id' => 0, 'value' => 'blue', 'label' => 'Bleu', 'hex' => '#1E88E5'],
            ['id' => 0, 'value' => 'green', 'label' => 'Vert', 'hex' => '#43A047'],
            ['id' => 0, 'value' => 'yellow', 'label' => 'Jaune', 'hex' => '#FDD835'],
            ['id' => 0, 'value' => 'orange', 'label' => 'Orange', 'hex' => '#FB8C00'],
            ['id' => 0, 'value' => 'purple', 'label' => 'Violet', 'hex' => '#8E24AA'],
            ['id' => 0, 'value' => 'pink', 'label' => 'Rose', 'hex' => '#EC407A'],
            ['id' => 0, 'value' => 'gray', 'label' => 'Gris', 'hex' => '#757575']
        ];
    }

    echo json_encode([
        'success' => true,
        'colors' => $colorsData
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur base de donnees'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur'
    ]);
}
