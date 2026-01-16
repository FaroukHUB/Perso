<?php
/**
 * PERSONNALY - Model ProductColorImage
 * Gère les variantes couleur d'un produit avec leurs images spécifiques
 *
 * Chaque produit peut avoir plusieurs variantes couleur,
 * et chaque variante peut avoir sa propre image face et dos.
 */

require_once __DIR__ . '/../core/Database.php';

class ProductColorImage
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère toutes les variantes couleur d'un produit
     */
    public function findByProduct(int $productId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM product_color_images
            WHERE product_id = ?
            ORDER BY sort_order ASC, color_name ASC
        ');
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une variante par son ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM product_color_images WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Récupère la variante par défaut d'un produit
     */
    public function findDefault(int $productId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM product_color_images
            WHERE product_id = ? AND is_default = 1
            LIMIT 1
        ');
        $stmt->execute([$productId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si pas de défaut, prendre le premier
        if (!$result) {
            $stmt = $this->db->prepare('
                SELECT * FROM product_color_images
                WHERE product_id = ?
                ORDER BY sort_order ASC
                LIMIT 1
            ');
            $stmt->execute([$productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $result ?: null;
    }

    /**
     * Vérifie si un produit a des variantes couleur avec images
     */
    public function hasColorImages(int $productId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM product_color_images
            WHERE product_id = ?
        ');
        $stmt->execute([$productId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Crée une nouvelle variante couleur
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO product_color_images
            (product_id, color_name, hex_code, image_front_url, image_back_url, is_default, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $data['product_id'],
            $data['color_name'],
            $data['hex_code'] ?? '#CCCCCC',
            $data['image_front_url'] ?? null,
            $data['image_back_url'] ?? null,
            $data['is_default'] ?? 0,
            $data['sort_order'] ?? 0
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour une variante couleur
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = ['color_name', 'hex_code', 'image_front_url', 'image_back_url', 'is_default', 'sort_order'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = 'UPDATE product_color_images SET ' . implode(', ', $fields) . ' WHERE id = ?';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Met à jour l'image face d'une variante
     */
    public function updateFrontImage(int $id, ?string $imageUrl): bool
    {
        $stmt = $this->db->prepare('
            UPDATE product_color_images SET image_front_url = ? WHERE id = ?
        ');
        return $stmt->execute([$imageUrl, $id]);
    }

    /**
     * Met à jour l'image dos d'une variante
     */
    public function updateBackImage(int $id, ?string $imageUrl): bool
    {
        $stmt = $this->db->prepare('
            UPDATE product_color_images SET image_back_url = ? WHERE id = ?
        ');
        return $stmt->execute([$imageUrl, $id]);
    }

    /**
     * Définit une variante comme défaut (et retire le défaut des autres)
     */
    public function setDefault(int $productId, int $variantId): bool
    {
        // Retirer le défaut de toutes les variantes du produit
        $stmt = $this->db->prepare('
            UPDATE product_color_images SET is_default = 0 WHERE product_id = ?
        ');
        $stmt->execute([$productId]);

        // Définir la nouvelle variante par défaut
        $stmt = $this->db->prepare('
            UPDATE product_color_images SET is_default = 1 WHERE id = ? AND product_id = ?
        ');
        return $stmt->execute([$variantId, $productId]);
    }

    /**
     * Supprime une variante couleur
     */
    public function delete(int $id): bool
    {
        // Récupérer les URLs des images pour les supprimer du disque
        $variant = $this->findById($id);

        if ($variant) {
            // Supprimer les fichiers images
            if (!empty($variant['image_front_url'])) {
                $frontPath = __DIR__ . '/../../public' . $variant['image_front_url'];
                if (file_exists($frontPath)) {
                    unlink($frontPath);
                }
            }
            if (!empty($variant['image_back_url'])) {
                $backPath = __DIR__ . '/../../public' . $variant['image_back_url'];
                if (file_exists($backPath)) {
                    unlink($backPath);
                }
            }
        }

        $stmt = $this->db->prepare('DELETE FROM product_color_images WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime toutes les variantes d'un produit
     */
    public function deleteByProduct(int $productId): bool
    {
        // Récupérer toutes les variantes pour supprimer les images
        $variants = $this->findByProduct($productId);

        foreach ($variants as $variant) {
            if (!empty($variant['image_front_url'])) {
                $frontPath = __DIR__ . '/../../public' . $variant['image_front_url'];
                if (file_exists($frontPath)) {
                    unlink($frontPath);
                }
            }
            if (!empty($variant['image_back_url'])) {
                $backPath = __DIR__ . '/../../public' . $variant['image_back_url'];
                if (file_exists($backPath)) {
                    unlink($backPath);
                }
            }
        }

        $stmt = $this->db->prepare('DELETE FROM product_color_images WHERE product_id = ?');
        return $stmt->execute([$productId]);
    }

    /**
     * Upload une image et retourne l'URL
     */
    public function uploadImage(array $file, int $productId, string $colorName, string $type = 'front'): ?string
    {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        // Valider le type de fichier
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return null;
        }

        // Créer le dossier si nécessaire
        $uploadDir = __DIR__ . '/../../public/uploads/products/colors';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Générer un nom unique
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $safeColorName = preg_replace('/[^a-z0-9]/', '-', strtolower($colorName));
        $filename = "product-{$productId}-{$safeColorName}-{$type}-" . uniqid() . '.' . $extension;
        $destination = $uploadDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return '/uploads/products/colors/' . $filename;
        }

        return null;
    }
}
