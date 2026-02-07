<?php
/**
 * PERSONNALY - API Editor Product
 * GET /public/api/editor/product.php?id={product_id}
 *
 * Retourne TOUTES les données nécessaires à l'éditeur V2 :
 * - Produit (id, name, base_price)
 * - Couleurs avec images (front/back) et tailles
 * - Zones d'impression actives
 * - Techniques avec prix et images macro
 * - Polices actives
 *
 * RÈGLE : Aucune logique métier ici, uniquement agrégation de données.
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
require_once __DIR__ . '/../../../app/models/Product.php';
require_once __DIR__ . '/../../../app/models/ProductColorImage.php';
require_once __DIR__ . '/../../../app/models/ProductPrintZone.php';
require_once __DIR__ . '/../../../app/models/CustomizationOption.php';
require_once __DIR__ . '/../../../app/models/Font.php';

// Récupérer l'ID produit
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId || $productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID produit requis']);
    exit;
}

try {
    // =========================================
    // 1. PRODUIT
    // =========================================
    $productModel = new Product();
    $product = $productModel->findById($productId);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Produit introuvable']);
        exit;
    }

    if (!$product['active']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Produit non disponible']);
        exit;
    }

    $productData = [
        'id' => (int) $product['id'],
        'name' => $product['name'],
        'base_price' => (float) $product['base_price'],
        'description' => $product['description'] ?? null,
        'text_positioning' => [
            'mode' => $product['text_positioning_mode'] ?? 'free',
            'fixed_x' => (float) ($product['text_fixed_position_x'] ?? 50),
            'fixed_y' => (float) ($product['text_fixed_position_y'] ?? 40)
        ]
    ];

    // =========================================
    // 2. COULEURS + IMAGES + TAILLES
    // =========================================
    $colorImageModel = new ProductColorImage();
    $colorImages = $colorImageModel->findByProduct($productId);

    $colorsData = [];

    // Récupérer les tailles du produit de base
    $productSizes = [];
    if (!empty($product['available_sizes'])) {
        $decoded = json_decode($product['available_sizes'], true);
        if (is_array($decoded)) {
            $productSizes = $decoded;
        }
    }

    // TOUJOURS ajouter le produit de base (couleur Standard)
    // Ceci garantit qu'il y a toujours au moins une couleur
    $colorsData[] = [
        'id' => 0,
        'name' => 'Standard',
        'hex' => '#FFFFFF',
        'is_default' => empty($colorImages), // Par défaut seulement si pas de variantes
        'sizes' => $productSizes,
        'images' => [
            'front' => $product['image_front_url'] ?: '/editor-v2/tshirt-front.svg',
            'back' => $product['image_back_url'] ?: '/editor-v2/tshirt-back.svg'
        ]
    ];

    // Ajouter les variantes couleur si elles existent
    if (!empty($colorImages)) {
        foreach ($colorImages as $colorImage) {
            // Récupérer les tailles disponibles
            $sizes = [];
            if (!empty($colorImage['available_sizes'])) {
                $decoded = json_decode($colorImage['available_sizes'], true);
                if (is_array($decoded)) {
                    $sizes = $decoded;
                }
            }

            $colorsData[] = [
                'id' => (int) $colorImage['id'],
                'name' => $colorImage['color_name'],
                'hex' => $colorImage['hex_code'] ?? '#FFFFFF',
                'is_default' => (bool) ($colorImage['is_default'] ?? false),
                'sizes' => $sizes,
                'images' => [
                    'front' => $colorImage['image_front_url'] ?: null,
                    'back' => $colorImage['image_back_url'] ?: null
                ]
            ];
        }
    }

    // Si aucune couleur définie, ajouter fallback
    if (empty($colorsData)) {
        $colorsData[] = [
            'id' => 0,
            'name' => 'Standard',
            'hex' => '#FFFFFF',
            'is_default' => true,
            'sizes' => $productSizes,
            'images' => [
                'front' => '/editor-v2/tshirt-front.svg',
                'back' => null
            ]
        ];
    }

    // =========================================
    // 3. ZONES D'IMPRESSION
    // =========================================
    $printZoneModel = new ProductPrintZone();
    $printZones = $printZoneModel->findActiveByProduct($productId);

    $zonesData = [];

    if (!empty($printZones)) {
        foreach ($printZones as $zone) {
            $zonesData[] = [
                'id' => (int) $zone['id'],
                'view' => $zone['zone_name'], // 'front' ou 'back'
                'label' => $zone['zone_label'] ?? ucfirst($zone['zone_name']),
                'x' => (float) $zone['pos_x'],
                'y' => (float) $zone['pos_y'],
                'width' => (float) $zone['width'],
                'height' => (float) $zone['height'],
                'max_chars' => (int) ($zone['max_chars'] ?? 100),
                'max_lines' => (int) ($zone['max_lines'] ?? 5)
            ];
        }
    } else {
        // Zone par défaut si aucune définie
        $zonesData[] = [
            'id' => 0,
            'view' => 'front',
            'label' => 'Devant',
            'x' => 22,
            'y' => 18,
            'width' => 56,
            'height' => 64,
            'max_chars' => 100,
            'max_lines' => 5
        ];
    }

    // =========================================
    // 4. TECHNIQUES D'IMPRESSION
    // =========================================
    $optionModel = new CustomizationOption();
    $techniques = $optionModel->getTechniques();

    $techniquesData = [];

    foreach ($techniques as $technique) {
        // Récupérer les images macro de cette technique
        $images = $optionModel->getImages((int) $technique['id']);

        $techniquesData[] = [
            'value' => $technique['value'],
            'label' => $technique['label'],
            'price' => (float) ($technique['price'] ?? 0),
            'description' => $technique['description'] ?? null,
            'images' => $images
        ];
    }

    // Si aucune technique définie, ajouter DTG par défaut
    if (empty($techniquesData)) {
        $techniquesData[] = [
            'value' => 'dtg',
            'label' => 'Impression numérique',
            'price' => 0,
            'description' => 'Impression directe sur textile',
            'images' => []
        ];
    }

    // =========================================
    // 5. POLICES
    // =========================================
    $fontModel = new Font();
    $fonts = $fontModel->findActive();

    $fontsData = [];

    foreach ($fonts as $font) {
        $fontsData[] = [
            'id' => (int) $font['id'],
            'family' => $font['family'],
            'label' => $font['name'],
            'category' => $font['category'] ?? 'sans-serif',
            'css_url' => $font['google_import_url'] ?? null,
            'weights' => $font['google_weights'] ?? '400'
        ];
    }

    // Si aucune police définie, ajouter des polices système
    if (empty($fontsData)) {
        $fontsData = [
            ['id' => 0, 'family' => 'Inter', 'label' => 'Inter', 'category' => 'sans-serif', 'css_url' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', 'weights' => '400;500;600;700'],
            ['id' => 0, 'family' => 'Poppins', 'label' => 'Poppins', 'category' => 'sans-serif', 'css_url' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', 'weights' => '400;500;600;700'],
            ['id' => 0, 'family' => 'Georgia', 'label' => 'Georgia', 'category' => 'serif', 'css_url' => null, 'weights' => '400'],
            ['id' => 0, 'family' => 'Arial', 'label' => 'Arial', 'category' => 'sans-serif', 'css_url' => null, 'weights' => '400;700']
        ];
    }

    // =========================================
    // RÉPONSE JSON
    // =========================================
    echo json_encode([
        'success' => true,
        'product' => $productData,
        'colors' => $colorsData,
        'print_zones' => $zonesData,
        'techniques' => $techniquesData,
        'fonts' => $fontsData
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
