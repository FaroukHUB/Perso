<?php
/**
 * PERSONNALY - Model ProductColor
 * Gestion des couleurs spécifiques à chaque produit
 */

require_once __DIR__ . '/../core/Database.php';

class ProductColor
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère les couleurs d'un produit
     */
    public function findByProduct(int $productId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM product_colors
                 WHERE product_id = ? AND active = 1
                 ORDER BY sort_order ASC'
            );
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table n'existe pas encore
            return [];
        }
    }

    /**
     * Récupère toutes les couleurs d'un produit (actives ou non)
     */
    public function findAllByProduct(int $productId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM product_colors
                 WHERE product_id = ?
                 ORDER BY sort_order ASC'
            );
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Récupère une couleur par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM product_colors WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Ajoute une couleur à un produit
     */
    public function create(int $productId, string $colorName, string $hexCode): int
    {
        // Récupérer le prochain sort_order
        $stmt = $this->db->prepare(
            'SELECT MAX(sort_order) as max_order FROM product_colors WHERE product_id = ?'
        );
        $stmt->execute([$productId]);
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO product_colors (product_id, color_name, hex_code, sort_order, active, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())'
        );
        $stmt->execute([$productId, $colorName, $hexCode, $maxOrder + 1]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour une couleur
     */
    public function update(int $id, string $colorName, string $hexCode): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_colors SET color_name = ?, hex_code = ? WHERE id = ?'
        );
        return $stmt->execute([$colorName, $hexCode, $id]);
    }

    /**
     * Supprime une couleur
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM product_colors WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime toutes les couleurs d'un produit
     */
    public function deleteByProduct(int $productId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM product_colors WHERE product_id = ?');
        return $stmt->execute([$productId]);
    }

    /**
     * Active/désactive une couleur
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_colors SET active = NOT active WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Synchronise les couleurs d'un produit (remplace toutes les couleurs)
     */
    public function syncColors(int $productId, array $colors): bool
    {
        // Supprimer les couleurs existantes
        $this->deleteByProduct($productId);

        // Ajouter les nouvelles
        $order = 1;
        foreach ($colors as $color) {
            if (!empty($color['name']) && !empty($color['hex'])) {
                $stmt = $this->db->prepare(
                    'INSERT INTO product_colors (product_id, color_name, hex_code, sort_order, active, created_at)
                     VALUES (?, ?, ?, ?, 1, NOW())'
                );
                $stmt->execute([$productId, $color['name'], $color['hex'], $order]);
                $order++;
            }
        }

        return true;
    }

    /**
     * Vérifie si un produit a des couleurs définies
     */
    public function hasColors(int $productId): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM product_colors WHERE product_id = ? AND active = 1'
            );
            $stmt->execute([$productId]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}
