<?php
/**
 * PERSONNALY - Model ProductPrintZone
 * Gestion des zones d'impression par produit
 */

require_once __DIR__ . '/../core/Database.php';

class ProductPrintZone
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère toutes les zones d'un produit
     */
    public function findByProduct(int $productId): array
    {
        $sql = "SELECT z.*, f.name as default_font_name, f.family as default_font_family
                FROM product_print_zones z
                LEFT JOIN fonts f ON z.default_font_id = f.id
                WHERE z.product_id = ?
                ORDER BY z.sort_order ASC, z.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les zones actives d'un produit
     */
    public function findActiveByProduct(int $productId): array
    {
        $sql = "SELECT z.*, f.name as default_font_name, f.family as default_font_family
                FROM product_print_zones z
                LEFT JOIN fonts f ON z.default_font_id = f.id
                WHERE z.product_id = ? AND z.active = 1
                ORDER BY z.sort_order ASC, z.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la zone principale d'un produit (première active)
     */
    public function findPrimaryByProduct(int $productId): ?array
    {
        $zones = $this->findActiveByProduct($productId);
        return !empty($zones) ? $zones[0] : null;
    }

    /**
     * Récupère une zone par son ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT z.*, f.name as default_font_name, f.family as default_font_family
                FROM product_print_zones z
                LEFT JOIN fonts f ON z.default_font_id = f.id
                WHERE z.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crée une nouvelle zone
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO product_print_zones
                (product_id, zone_name, zone_label, pos_x, pos_y, width, height,
                 max_chars, max_lines, default_font_id, allowed_fonts, active, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['product_id'],
            $data['zone_name'],
            $data['zone_label'],
            $data['pos_x'] ?? 15.00,
            $data['pos_y'] ?? 25.00,
            $data['width'] ?? 70.00,
            $data['height'] ?? 50.00,
            $data['max_chars'] ?? 30,
            $data['max_lines'] ?? 2,
            $data['default_font_id'] ?: null,
            $data['allowed_fonts'] ?: null,
            $data['active'] ?? 1,
            $data['sort_order'] ?? 0
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour une zone
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE product_print_zones SET
                zone_name = ?,
                zone_label = ?,
                pos_x = ?,
                pos_y = ?,
                width = ?,
                height = ?,
                max_chars = ?,
                max_lines = ?,
                default_font_id = ?,
                allowed_fonts = ?,
                active = ?,
                sort_order = ?
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['zone_name'],
            $data['zone_label'],
            $data['pos_x'],
            $data['pos_y'],
            $data['width'],
            $data['height'],
            $data['max_chars'],
            $data['max_lines'],
            $data['default_font_id'] ?: null,
            $data['allowed_fonts'] ?: null,
            $data['active'] ?? 1,
            $data['sort_order'] ?? 0,
            $id
        ]);
    }

    /**
     * Supprime une zone
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM product_print_zones WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Active/désactive une zone
     */
    public function toggleActive(int $id): bool
    {
        $sql = "UPDATE product_print_zones SET active = NOT active WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Crée une zone par défaut pour un produit
     */
    public function createDefault(int $productId): int
    {
        return $this->create([
            'product_id' => $productId,
            'zone_name' => 'front',
            'zone_label' => 'Devant',
            'pos_x' => 15.00,
            'pos_y' => 25.00,
            'width' => 70.00,
            'height' => 50.00,
            'max_chars' => 50,
            'max_lines' => 3,
            'default_font_id' => null,
            'allowed_fonts' => null,
            'active' => 1,
            'sort_order' => 0
        ]);
    }

    /**
     * Vérifie si un produit a des zones définies
     */
    public function hasZones(int $productId): bool
    {
        $sql = "SELECT COUNT(*) FROM product_print_zones WHERE product_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Retourne une zone par défaut (fallback si aucune zone définie)
     */
    public static function getDefaultZone(): array
    {
        return [
            'id' => 0,
            'zone_name' => 'default',
            'zone_label' => 'Zone d\'impression',
            'pos_x' => 15.00,
            'pos_y' => 25.00,
            'width' => 70.00,
            'height' => 50.00,
            'max_chars' => 50,
            'max_lines' => 3,
            'default_font_id' => null,
            'allowed_fonts' => null
        ];
    }

    /**
     * Parse les polices autorisées (JSON → array)
     */
    public function parseAllowedFonts(?string $allowedFonts): ?array
    {
        if (empty($allowedFonts)) {
            return null; // NULL = toutes les polices autorisées
        }
        $decoded = json_decode($allowedFonts, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Encode les polices autorisées (array → JSON)
     */
    public function encodeAllowedFonts(?array $fontIds): ?string
    {
        if (empty($fontIds)) {
            return null;
        }
        return json_encode(array_map('intval', $fontIds));
    }
}
