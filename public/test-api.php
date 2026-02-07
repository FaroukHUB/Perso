<?php
/**
 * TEST API - Vérifier que l'API retourne bien les couleurs et tailles
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔬 TEST API PRODUIT</h1>";

// Charger l'API
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/ProductColorImage.php';

$productId = $_GET['id'] ?? 1;

echo "<h2>Produit ID: {$productId}</h2>";

try {
    $productModel = new Product();
    $product = $productModel->findById($productId);

    if (!$product) {
        echo "<p style='color: red;'>❌ Produit non trouvé !</p>";
        exit;
    }

    echo "<h3>📦 Données brutes du produit :</h3>";
    echo "<pre>";
    print_r([
        'id' => $product['id'],
        'name' => $product['name'],
        'available_sizes (raw)' => $product['available_sizes'] ?? 'NULL',
        'available_sizes (decoded)' => json_decode($product['available_sizes'] ?? '[]', true)
    ]);
    echo "</pre>";

    // Tester l'extraction des tailles
    $productSizes = [];
    if (!empty($product['available_sizes'])) {
        $decoded = json_decode($product['available_sizes'], true);
        if (is_array($decoded)) {
            $productSizes = $decoded;
        }
    }

    echo "<h3>🎯 Tailles extraites :</h3>";
    if (empty($productSizes)) {
        echo "<p style='color: red; font-weight: bold;'>❌ AUCUNE TAILLE TROUVÉE !</p>";
        echo "<p>👉 Exécutez cette requête SQL :</p>";
        echo "<code style='background: #f0f0f0; padding: 10px; display: block;'>";
        echo "UPDATE products SET available_sizes = '[\"XS\",\"S\",\"M\",\"L\",\"XL\",\"XXL\"]' WHERE id = {$productId};";
        echo "</code>";
    } else {
        echo "<p style='color: green; font-weight: bold;'>✅ Tailles trouvées : " . implode(', ', $productSizes) . "</p>";
    }

    // Tester les couleurs
    $colorImageModel = new ProductColorImage();
    $colorImages = $colorImageModel->findByProduct($productId);

    echo "<h3>🎨 Couleurs du produit :</h3>";
    if (empty($colorImages)) {
        echo "<p>ℹ️ Pas de variantes couleur - utilise la couleur Standard</p>";
    } else {
        echo "<pre>";
        print_r($colorImages);
        echo "</pre>";
    }

    // Simuler la réponse API
    echo "<h3>📡 Simulation réponse API :</h3>";
    $colorsData = [];

    // Couleur Standard
    if (!empty($product['image_front_url']) || !empty($product['image_back_url'])) {
        $colorsData[] = [
            'id' => 0,
            'name' => 'Standard',
            'hex' => '#FFFFFF',
            'is_default' => empty($colorImages),
            'sizes' => $productSizes,
            'images' => [
                'front' => $product['image_front_url'] ?: '/editor-v2/tshirt-front.svg',
                'back' => $product['image_back_url'] ?: null
            ]
        ];
    }

    echo "<pre>";
    echo json_encode(['colors' => $colorsData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";

    if (empty($productSizes)) {
        echo "<div style='background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0;'>";
        echo "<h3>⚠️ ACTION REQUISE</h3>";
        echo "<p>Le produit n'a PAS de tailles définies. Exécutez ces commandes :</p>";
        echo "<ol>";
        echo "<li>Dans phpMyAdmin, exécutez :<br><code>UPDATE products SET available_sizes = '[\"XS\",\"S\",\"M\",\"L\",\"XL\",\"XXL\"]' WHERE id = {$productId};</code></li>";
        echo "<li>Rechargez cette page</li>";
        echo "<li>Testez l'éditeur : <a href='/public/editor-v2.php?id={$productId}'>Ouvrir l'éditeur</a></li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; border: 2px solid #28a745; padding: 20px; margin: 20px 0;'>";
        echo "<h3>✅ TOUT EST OK !</h3>";
        echo "<p>Le produit a bien des tailles. Testez l'éditeur :</p>";
        echo "<a href='/public/editor-v2.php?id={$productId}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🎨 Ouvrir l'éditeur</a>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>
