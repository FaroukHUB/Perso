<?php
/**
 * PERSONNALY - Model ProductUpsell
 * Gestion des suggestions de produits (vrais upsells)
 * Suggère des produits complémentaires basés sur le panier
 */

require_once __DIR__ . '/../core/Database.php';

class ProductUpsell
{
    private PDO $db;

    // Types de déclencheurs
    public const TRIGGER_TYPES = [
        'any' => 'Toujours afficher',
        'product' => 'Si produit spécifique au panier',
        'category' => 'Si catégorie au panier'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère tous les upsells
     */
    public function findAll(bool $activeOnly = false): array
    {
        try {
            $sql = 'SELECT pu.*, p.name as product_name, p.price as product_price, p.image_front_url as product_image
                    FROM product_upsells pu
                    LEFT JOIN products p ON pu.suggested_product_id = p.id';
            if ($activeOnly) {
                $sql .= ' WHERE pu.active = 1';
            }
            $sql .= ' ORDER BY pu.priority DESC, pu.created_at DESC';

            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Récupère un upsell par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT pu.*, p.name as product_name, p.price as product_price, p.image_front_url as product_image
             FROM product_upsells pu
             LEFT JOIN products p ON pu.suggested_product_id = p.id
             WHERE pu.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crée un nouvel upsell
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO product_upsells (
                name, trigger_type, trigger_value, suggested_product_id,
                custom_title, custom_description, badge_text, promo_price,
                priority, active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['name'],
            $data['trigger_type'] ?? 'any',
            $data['trigger_value'] ?: null,
            $data['suggested_product_id'],
            $data['custom_title'] ?: null,
            $data['custom_description'] ?: null,
            $data['badge_text'] ?: null,
            $data['promo_price'] ?: null,
            $data['priority'] ?? 0,
            $data['active'] ?? 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un upsell
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_upsells SET
                name = ?, trigger_type = ?, trigger_value = ?, suggested_product_id = ?,
                custom_title = ?, custom_description = ?, badge_text = ?, promo_price = ?,
                priority = ?, active = ?
            WHERE id = ?'
        );

        return $stmt->execute([
            $data['name'],
            $data['trigger_type'] ?? 'any',
            $data['trigger_value'] ?: null,
            $data['suggested_product_id'],
            $data['custom_title'] ?: null,
            $data['custom_description'] ?: null,
            $data['badge_text'] ?: null,
            $data['promo_price'] ?: null,
            $data['priority'] ?? 0,
            $data['active'] ?? 1,
            $id
        ]);
    }

    /**
     * Supprime un upsell
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM product_upsells WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Active/désactive un upsell
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_upsells SET active = NOT active WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Récupère les produits suggérés pour un panier donné
     *
     * @param array $cartProductIds IDs des produits dans le panier
     * @param array $cartCategoryIds IDs des catégories des produits du panier
     * @param int $limit Nombre max de suggestions
     * @return array Produits suggérés avec infos complètes
     */
    public function getSuggestionsForCart(array $cartProductIds, array $cartCategoryIds = [], int $limit = 4): array
    {
        try {
            $settings = $this->getSettings();
            if (!($settings['enabled'] ?? true)) {
                return [];
            }

            $limit = (int) ($settings['max_items'] ?? $limit);
            $suggestions = [];
            $addedProductIds = $cartProductIds; // Ne pas suggérer ce qui est déjà au panier

            // 1. Récupérer les upsells configurés manuellement
            $placeholdersProducts = !empty($cartProductIds) ? implode(',', array_fill(0, count($cartProductIds), '?')) : '0';
            $placeholdersCategories = !empty($cartCategoryIds) ? implode(',', array_fill(0, count($cartCategoryIds), '?')) : '0';

            $sql = "SELECT pu.*, p.id as product_id, p.name as product_name, p.price as product_price,
                           p.image_front_url as product_image, p.slug as product_slug
                    FROM product_upsells pu
                    JOIN products p ON pu.suggested_product_id = p.id AND p.active = 1
                    WHERE pu.active = 1
                    AND pu.suggested_product_id NOT IN ($placeholdersProducts)
                    AND (
                        pu.trigger_type = 'any'
                        OR (pu.trigger_type = 'product' AND pu.trigger_value IN ($placeholdersProducts))
                        OR (pu.trigger_type = 'category' AND pu.trigger_value IN ($placeholdersCategories))
                    )
                    ORDER BY pu.priority DESC
                    LIMIT ?";

            $params = array_merge(
                $cartProductIds ?: [0],
                $cartProductIds ?: [0],
                $cartCategoryIds ?: [0],
                [$limit * 2] // Récupérer plus pour filtrer les doublons
            );

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $upsells = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($upsells as $upsell) {
                if (count($suggestions) >= $limit) break;
                if (in_array($upsell['product_id'], $addedProductIds)) continue;

                $addedProductIds[] = $upsell['product_id'];
                $suggestions[] = $this->formatSuggestion($upsell);
            }

            // 2. Si pas assez et fallback activé, compléter avec produits de même catégorie
            if (count($suggestions) < $limit && ($settings['fallback_to_category'] ?? true) && !empty($cartCategoryIds)) {
                $remaining = $limit - count($suggestions);
                $excludeIds = implode(',', array_map('intval', $addedProductIds));

                $sql = "SELECT p.id as product_id, p.name as product_name, p.price as product_price,
                               p.image_front_url as product_image, p.slug as product_slug
                        FROM products p
                        WHERE p.active = 1
                        AND p.category_id IN ($placeholdersCategories)
                        AND p.id NOT IN ($excludeIds)
                        ORDER BY RAND()
                        LIMIT ?";

                $stmt = $this->db->prepare($sql);
                $stmt->execute(array_merge($cartCategoryIds, [$remaining]));
                $fallbackProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($fallbackProducts as $product) {
                    $suggestions[] = [
                        'product_id' => $product['product_id'],
                        'name' => $product['product_name'],
                        'price' => (float) $product['product_price'],
                        'image' => $product['product_image'],
                        'slug' => $product['product_slug'],
                        'promo_price' => null,
                        'badge' => null,
                        'description' => null
                    ];
                }
            }

            return $suggestions;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Formate une suggestion pour l'affichage
     */
    private function formatSuggestion(array $upsell): array
    {
        return [
            'product_id' => $upsell['product_id'],
            'name' => $upsell['custom_title'] ?: $upsell['product_name'],
            'price' => (float) $upsell['product_price'],
            'image' => $upsell['product_image'],
            'slug' => $upsell['product_slug'] ?? null,
            'promo_price' => $upsell['promo_price'] ? (float) $upsell['promo_price'] : null,
            'badge' => $upsell['badge_text'],
            'description' => $upsell['custom_description']
        ];
    }

    /**
     * Récupère les paramètres globaux des upsells
     */
    public function getSettings(): array
    {
        try {
            $stmt = $this->db->query('SELECT setting_key, setting_value FROM upsell_settings');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (PDOException $e) {
            return [
                'enabled' => '1',
                'title' => 'Vous aimerez aussi',
                'max_items' => '4',
                'show_on_cart' => '1',
                'show_on_checkout' => '0',
                'fallback_to_category' => '1'
            ];
        }
    }

    /**
     * Met à jour un paramètre
     */
    public function updateSetting(string $key, string $value): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO upsell_settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?'
            );
            return $stmt->execute([$key, $value, $value]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Duplique un upsell
     */
    public function duplicate(int $id): ?int
    {
        $original = $this->findById($id);
        if (!$original) {
            return null;
        }

        unset($original['id'], $original['created_at'], $original['updated_at']);
        unset($original['product_name'], $original['product_price'], $original['product_image']);
        $original['name'] = $original['name'] . ' (copie)';
        $original['active'] = 0;

        return $this->create($original);
    }
}
