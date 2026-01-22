<?php
/**
 * PERSONNALY - API Editor Assets
 * GET /public/api/editor/assets.php
 *
 * Retourne les designs et éléments depuis l'admin :
 * - Designs groupés par catégorie
 * - Éléments groupés par catégorie (avec info premium)
 *
 * RÈGLE : Toutes les données viennent de la base admin.
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

// Vérifier méthode GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Charger les modèles
require_once __DIR__ . '/../../../app/core/Database.php';
require_once __DIR__ . '/../../../app/models/Design.php';
require_once __DIR__ . '/../../../app/models/DesignCategory.php';
require_once __DIR__ . '/../../../app/models/Element.php';
require_once __DIR__ . '/../../../app/models/ElementCategory.php';

try {
    // =========================================
    // 1. DESIGNS
    // =========================================
    $designModel = new Design();
    $designCategoryModel = new DesignCategory();

    $designCategories = $designCategoryModel->findAllActive();
    $designs = $designModel->findAllActive();

    // Grouper les designs par catégorie
    $designsData = [];

    foreach ($designCategories as $category) {
        $categoryDesigns = array_filter($designs, function($d) use ($category) {
            return (int)$d['category_id'] === (int)$category['id'];
        });

        if (empty($categoryDesigns)) continue;

        $items = [];
        foreach ($categoryDesigns as $design) {
            $items[] = [
                'id' => (int) $design['id'],
                'name' => $design['name'],
                'image' => $design['image_path'] ? '/public/uploads/designs/' . $design['image_path'] : null,
                'type' => 'design'
            ];
        }

        $designsData[] = [
            'category_id' => (int) $category['id'],
            'category_name' => $category['name'],
            'items' => $items
        ];
    }

    // =========================================
    // 2. ÉLÉMENTS
    // =========================================
    $elementModel = new Element();
    $elementCategoryModel = new ElementCategory();

    $elementCategories = $elementCategoryModel->findAllActive();
    $elements = $elementModel->findAllActive();

    // Grouper les éléments par catégorie
    $elementsData = [];

    foreach ($elementCategories as $category) {
        $categoryElements = array_filter($elements, function($e) use ($category) {
            return (int)$e['category_id'] === (int)$category['id'];
        });

        if (empty($categoryElements)) continue;

        $items = [];
        foreach ($categoryElements as $element) {
            $items[] = [
                'id' => (int) $element['id'],
                'name' => $element['name'],
                'image' => $element['image_path'] ? '/public/uploads/elements/' . $element['image_path'] : null,
                'type' => 'element',
                'is_premium' => (bool) ($element['is_premium'] ?? false),
                'price' => $element['price'] ? (float) $element['price'] : null
            ];
        }

        $elementsData[] = [
            'category_id' => (int) $category['id'],
            'category_name' => $category['name'],
            'items' => $items
        ];
    }

    // =========================================
    // RÉPONSE JSON
    // =========================================
    echo json_encode([
        'success' => true,
        'designs' => $designsData,
        'elements' => $elementsData
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur base de données'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur'
    ]);
}
