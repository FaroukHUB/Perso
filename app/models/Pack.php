<?php
/**
 * PERSONNALY - Modèle Pack
 * Gestion des packs / idées de personnalisation
 */

require_once __DIR__ . '/../core/Database.php';

class Pack
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Trouve un pack par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM packs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $pack = $stmt->fetch();

        if ($pack) {
            $pack['preset'] = json_decode($pack['preset_json'], true) ?? [];
            $pack['products'] = $this->getProducts($id);
        }

        return $pack ?: null;
    }

    /**
     * Trouve un pack par slug
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM packs WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $pack = $stmt->fetch();

        if ($pack) {
            $pack['preset'] = json_decode($pack['preset_json'], true) ?? [];
            $pack['products'] = $this->getProducts($pack['id']);
        }

        return $pack ?: null;
    }

    /**
     * Liste tous les packs
     */
    public function findAll(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM packs';
        if ($activeOnly) {
            $sql .= ' WHERE status = "active"';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';

        $stmt = $this->db->query($sql);
        $packs = $stmt->fetchAll();

        // Ajouter le nombre de produits pour chaque pack
        foreach ($packs as &$pack) {
            $pack['product_count'] = $this->countProducts($pack['id']);
        }

        return $packs;
    }

    /**
     * Liste les packs actifs (pour le front)
     */
    public function findActive(): array
    {
        return $this->findAll(true);
    }

    /**
     * Liste les packs par type
     */
    public function findByType(string $type): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM packs WHERE type = ? AND status = "active" ORDER BY sort_order ASC'
        );
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    /**
     * Crée un nouveau pack
     */
    public function create(array $data): int
    {
        $slug = $this->generateSlug($data['name']);

        $stmt = $this->db->prepare(
            'INSERT INTO packs (name, slug, description, type, cover_image_url, preset_json, status, sort_order, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['name'],
            $slug,
            $data['description'] ?? '',
            $data['type'] ?? 'inspiration',
            $data['cover_image_url'] ?? null,
            json_encode($data['preset'] ?? [], JSON_UNESCAPED_UNICODE),
            $data['status'] ?? 'draft',
            $data['sort_order'] ?? 0,
        ]);

        $packId = (int) $this->db->lastInsertId();

        // Ajouter les produits si fournis
        if (!empty($data['product_ids'])) {
            $this->setProducts($packId, $data['product_ids']);
        }

        return $packId;
    }

    /**
     * Met à jour un pack
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = ['name', 'description', 'type', 'cover_image_url', 'status', 'sort_order'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        // Gestion du preset JSON
        if (array_key_exists('preset', $data)) {
            $fields[] = '`preset_json` = ?';
            $values[] = json_encode($data['preset'], JSON_UNESCAPED_UNICODE);
        }

        // Régénérer le slug si le nom change
        if (array_key_exists('name', $data)) {
            $fields[] = '`slug` = ?';
            $values[] = $this->generateSlug($data['name'], $id);
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = 'UPDATE packs SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);

        // Mettre à jour les produits si fournis
        if (array_key_exists('product_ids', $data)) {
            $this->setProducts($id, $data['product_ids']);
        }

        return $result;
    }

    /**
     * Supprime un pack
     */
    public function delete(int $id): bool
    {
        // Les produits liés seront supprimés automatiquement (ON DELETE CASCADE)
        $stmt = $this->db->prepare('DELETE FROM packs WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Récupère les produits d'un pack
     */
    public function getProducts(int $packId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, pp.sort_order as pack_sort_order
             FROM products p
             INNER JOIN pack_products pp ON p.id = pp.product_id
             WHERE pp.pack_id = ?
             ORDER BY pp.sort_order ASC'
        );
        $stmt->execute([$packId]);
        return $stmt->fetchAll();
    }

    /**
     * Compte les produits d'un pack
     */
    public function countProducts(int $packId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM pack_products WHERE pack_id = ?');
        $stmt->execute([$packId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Récupère le premier produit d'un pack (pour le lien CTA)
     */
    public function getFirstProduct(int $packId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*
             FROM products p
             INNER JOIN pack_products pp ON p.id = pp.product_id
             WHERE pp.pack_id = ? AND p.active = 1
             ORDER BY pp.sort_order ASC
             LIMIT 1'
        );
        $stmt->execute([$packId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Définit les produits d'un pack (remplace tous les existants)
     */
    public function setProducts(int $packId, array $productIds): void
    {
        // Supprimer les associations existantes
        $stmt = $this->db->prepare('DELETE FROM pack_products WHERE pack_id = ?');
        $stmt->execute([$packId]);

        // Ajouter les nouvelles associations
        if (!empty($productIds)) {
            $stmt = $this->db->prepare(
                'INSERT INTO pack_products (pack_id, product_id, sort_order) VALUES (?, ?, ?)'
            );
            foreach ($productIds as $order => $productId) {
                $stmt->execute([$packId, $productId, $order]);
            }
        }
    }

    /**
     * Ajoute un produit à un pack
     */
    public function addProduct(int $packId, int $productId, int $sortOrder = 0): bool
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO pack_products (pack_id, product_id, sort_order) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$packId, $productId, $sortOrder]);
    }

    /**
     * Retire un produit d'un pack
     */
    public function removeProduct(int $packId, int $productId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM pack_products WHERE pack_id = ? AND product_id = ?');
        return $stmt->execute([$packId, $productId]);
    }

    /**
     * Génère un slug unique à partir du nom
     */
    private function generateSlug(string $name, ?int $excludeId = null): string
    {
        // Convertir en minuscules et remplacer les caractères spéciaux
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Vérifier l'unicité
        $baseSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Vérifie si un slug existe déjà
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM packs WHERE slug = ?';
        $params = [$slug];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère les types disponibles
     */
    public function getTypes(): array
    {
        return [
            'technique' => 'Technique',
            'contextuel' => 'Contextuel',
            'thematique' => 'Thématique',
            'inspiration' => 'Inspiration',
        ];
    }

    /**
     * Récupère les statuts disponibles
     */
    public function getStatuses(): array
    {
        return [
            'draft' => 'Brouillon',
            'active' => 'Actif',
        ];
    }
}
