<?php
/**
 * PERSONNALY - Gestion du Panier (Session)
 */

class Cart
{
    private const SESSION_KEY = 'personnaly_cart';

    /**
     * Initialise la session si nécessaire
     */
    private static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    /**
     * Ajoute un article au panier
     */
    public static function add(int $productId, array $customization, float $unitPrice, int $quantity = 1): void
    {
        self::init();

        // Génère une clé unique basée sur le produit ET la personnalisation
        $itemKey = md5($productId . json_encode($customization));

        if (isset($_SESSION[self::SESSION_KEY][$itemKey])) {
            // Augmente la quantité si même produit + même personnalisation
            $_SESSION[self::SESSION_KEY][$itemKey]['quantity'] += $quantity;
        } else {
            $_SESSION[self::SESSION_KEY][$itemKey] = [
                'product_id' => $productId,
                'customization' => $customization,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'added_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Met à jour la quantité d'un article
     */
    public static function updateQuantity(string $itemKey, int $quantity): void
    {
        self::init();

        if ($quantity <= 0) {
            self::remove($itemKey);
        } elseif (isset($_SESSION[self::SESSION_KEY][$itemKey])) {
            $_SESSION[self::SESSION_KEY][$itemKey]['quantity'] = $quantity;
        }
    }

    /**
     * Supprime un article du panier
     */
    public static function remove(string $itemKey): void
    {
        self::init();
        unset($_SESSION[self::SESSION_KEY][$itemKey]);
    }

    /**
     * Récupère tous les articles du panier
     */
    public static function getItems(): array
    {
        self::init();
        return $_SESSION[self::SESSION_KEY] ?? [];
    }

    /**
     * Compte le nombre d'articles
     */
    public static function count(): int
    {
        self::init();
        $count = 0;
        foreach ($_SESSION[self::SESSION_KEY] as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }

    /**
     * Calcule le total du panier
     */
    public static function getTotal(): float
    {
        self::init();
        $total = 0;
        foreach ($_SESSION[self::SESSION_KEY] as $item) {
            $total += $item['unit_price'] * $item['quantity'];
        }
        return $total;
    }

    /**
     * Vide le panier
     */
    public static function clear(): void
    {
        self::init();
        $_SESSION[self::SESSION_KEY] = [];
    }

    /**
     * Vérifie si le panier est vide
     */
    public static function isEmpty(): bool
    {
        self::init();
        return empty($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Récupère les articles avec les infos produits enrichies
     */
    public static function getItemsWithProducts(): array
    {
        require_once __DIR__ . '/../models/Product.php';

        self::init();
        $items = [];
        $productModel = new Product();

        foreach ($_SESSION[self::SESSION_KEY] as $key => $item) {
            // Vérifier que l'item est valide
            if (!isset($item['product_id']) || !isset($item['unit_price']) || !isset($item['quantity'])) {
                continue;
            }

            $product = $productModel->findById((int) $item['product_id']);

            // Créer un produit par défaut si non trouvé (pour éviter les items invisibles)
            if (!$product) {
                $product = [
                    'id' => $item['product_id'],
                    'name' => 'Produit #' . $item['product_id'],
                    'description' => '',
                    'base_price' => $item['unit_price'],
                    'image_front_url' => null,
                    'image_back_url' => null,
                    'available_sizes' => null,
                    'active' => 0,
                    '_not_found' => true
                ];
            }

            $items[$key] = array_merge($item, [
                'product' => $product,
                'subtotal' => $item['unit_price'] * $item['quantity'],
            ]);
        }

        return $items;
    }

    /**
     * Debug: affiche le contenu du panier (pour le développement)
     */
    public static function debug(): array
    {
        self::init();
        return $_SESSION[self::SESSION_KEY] ?? [];
    }
}
