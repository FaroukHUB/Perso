<?php
/**
 * PERSONNALY - Model ProductUpsell
 * Gestion des suggestions de produits (upsells)
 * Simple : liste de produits à suggérer sur le panier
 */

require_once __DIR__ . '/../core/Database.php';

class ProductUpsell
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère tous les upsells avec infos produits
     */
    public function findAll(bool $activeOnly = false): array
    {
        try {
            $sql = 'SELECT pu.*, p.name as product_name, p.base_price as product_price,
                           p.image_front_url as product_image
                    FROM product_upsells pu
                    LEFT JOIN products p ON pu.product_id = p.id';
            if ($activeOnly) {
                $sql .= ' WHERE pu.active = 1 AND p.active = 1';
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
            'SELECT pu.*, p.name as product_name, p.base_price as product_price,
                    p.image_front_url as product_image
             FROM product_upsells pu
             LEFT JOIN products p ON pu.product_id = p.id
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
                product_id, custom_title, custom_description, badge_text,
                promo_price, priority, active
            ) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['product_id'],
            $data['custom_title'] ?? null,
            $data['custom_description'] ?? null,
            $data['badge_text'] ?? null,
            $data['promo_price'] ?? null,
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
                product_id = ?, custom_title = ?, custom_description = ?,
                badge_text = ?, promo_price = ?, priority = ?, active = ?
            WHERE id = ?'
        );

        return $stmt->execute([
            $data['product_id'],
            $data['custom_title'] ?? null,
            $data['custom_description'] ?? null,
            $data['badge_text'] ?? null,
            $data['promo_price'] ?? null,
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
     * Récupère les produits suggérés pour affichage
     * Exclut les produits déjà dans le panier
     */
    public function getSuggestions(array $excludeProductIds = [], int $limit = 4): array
    {
        try {
            $settings = $this->getSettings();
            if (($settings['enabled'] ?? '1') !== '1') {
                return [];
            }

            $limit = (int) ($settings['max_items'] ?? $limit);

            $sql = 'SELECT pu.*, p.id as product_id, p.name as product_name,
                           p.base_price as product_price, p.image_front_url as product_image
                    FROM product_upsells pu
                    JOIN products p ON pu.product_id = p.id AND p.active = 1
                    WHERE pu.active = 1';

            $params = [];
            if (!empty($excludeProductIds)) {
                $placeholders = implode(',', array_fill(0, count($excludeProductIds), '?'));
                $sql .= " AND pu.product_id NOT IN ($placeholders)";
                $params = $excludeProductIds;
            }

            $sql .= ' ORDER BY pu.priority DESC LIMIT ?';
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $upsells = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function($u) {
                return [
                    'product_id' => $u['product_id'],
                    'name' => $u['custom_title'] ?: $u['product_name'],
                    'description' => $u['custom_description'],
                    'price' => (float) $u['product_price'],
                    'promo_price' => $u['promo_price'] ? (float) $u['promo_price'] : null,
                    'image' => $u['product_image'],
                    'badge' => $u['badge_text']
                ];
            }, $upsells);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Récupère les paramètres globaux
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
                'title' => 'Complétez votre commande',
                'subtitle' => 'Ces articles pourraient vous plaire',
                'max_items' => '4',
                'show_on_cart' => '1'
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
}
