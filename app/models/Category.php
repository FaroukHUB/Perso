<?php
/**
 * PERSONNALY - Modèle Category
 * Gestion des catégories de produits
 */

class Category
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère toutes les catégories actives triées
     */
    public function findAllActive(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM categories
            WHERE status = 'active'
            ORDER BY sort_order ASC, name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Récupère toutes les catégories (actives et inactives)
     */
    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT c.*,
                   (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = c.id) as product_count
            FROM categories c
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Récupère une catégorie par son ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Récupère une catégorie par son slug
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Crée une nouvelle catégorie
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO categories (name, slug, description, image_url, sort_order, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['image_url'] ?? null,
            $data['sort_order'] ?? 0,
            $data['status'] ?? 'active'
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour une catégorie
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE categories
            SET name = ?, slug = ?, description = ?, image_url = ?, sort_order = ?, status = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['image_url'] ?? null,
            $data['sort_order'] ?? 0,
            $data['status'] ?? 'active',
            $id
        ]);
    }

    /**
     * Supprime une catégorie
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Change le statut d'une catégorie
     */
    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE categories
            SET status = IF(status = 'active', 'inactive', 'active')
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Met à jour l'ordre des catégories
     */
    public function updateOrder(array $order): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
            foreach ($order as $position => $id) {
                $stmt->execute([$position, $id]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Récupère les catégories d'un produit
     */
    public function findByProduct(int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.* FROM categories c
            INNER JOIN product_categories pc ON c.id = pc.category_id
            WHERE pc.product_id = ?
            ORDER BY c.sort_order ASC
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère les IDs des catégories d'un produit
     */
    public function getCategoryIdsByProduct(int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT category_id FROM product_categories WHERE product_id = ?
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Récupère les noms des catégories d'un produit
     */
    public function getCategoryNamesByProduct(int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.name
            FROM categories c
            INNER JOIN product_categories pc ON c.id = pc.category_id
            WHERE pc.product_id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Définit les catégories d'un produit
     */
    public function setProductCategories(int $productId, array $categoryIds): bool
    {
        $this->db->beginTransaction();
        try {
            // Supprimer les anciennes associations
            $stmt = $this->db->prepare("DELETE FROM product_categories WHERE product_id = ?");
            $stmt->execute([$productId]);

            // Ajouter les nouvelles
            if (!empty($categoryIds)) {
                $stmt = $this->db->prepare("
                    INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)
                ");
                foreach ($categoryIds as $catId) {
                    $stmt->execute([$productId, $catId]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Récupère les produits d'une catégorie
     */
    public function getProducts(int $categoryId, int $limit = 0): array
    {
        $sql = "
            SELECT p.* FROM products p
            INNER JOIN product_categories pc ON p.id = pc.product_id
            WHERE pc.category_id = ? AND p.active = 1
            ORDER BY p.name ASC
        ";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    }

    /**
     * Génère un slug unique
     */
    public function generateSlug(string $name, ?int $excludeId = null): string
    {
        $slug = $this->slugify($name);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugify(string $string): string
    {
        $string = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
        $string = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
        $string = preg_replace('/[\s-]+/', '-', $string);
        return strtolower(trim($string, '-'));
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM categories WHERE slug = ?";
        $params = [$slug];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Statuts disponibles
     */
    public function getStatuses(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive'
        ];
    }
}
