<?php
/**
 * PERSONNALY - Modèle BlogPost
 * Gestion des articles de blog (affichage slider uniquement)
 */

require_once __DIR__ . '/../core/Database.php';

class BlogPost
{
    private $db;

    const STATUSES = [
        'draft' => 'Brouillon',
        'published' => 'Publié'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère un article par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM blog_posts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Récupère un article par slug
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM blog_posts WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Liste tous les articles
     */
    public function findAll(bool $publishedOnly = false): array
    {
        $sql = 'SELECT * FROM blog_posts';
        if ($publishedOnly) {
            $sql .= ' WHERE status = "published"';
        }
        $sql .= ' ORDER BY published_at DESC, created_at DESC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Liste les articles publiés (pour le front)
     */
    public function findPublished(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM blog_posts
             WHERE status = "published"
             ORDER BY published_at DESC, created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Crée un nouvel article
     */
    public function create(array $data): int
    {
        $slug = $this->generateSlug($data['title']);

        $publishedAt = null;
        if (($data['status'] ?? 'draft') === 'published') {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO blog_posts (title, slug, excerpt, content, cover_image_url, status, published_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['title'],
            $slug,
            $data['excerpt'] ?? null,
            $data['content'] ?? null,
            $data['cover_image_url'] ?? null,
            $data['status'] ?? 'draft',
            $publishedAt
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un article
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = ['title', 'excerpt', 'content', 'cover_image_url', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        // Régénérer le slug si le titre change
        if (array_key_exists('title', $data)) {
            $fields[] = '`slug` = ?';
            $values[] = $this->generateSlug($data['title'], $id);
        }

        // Gérer la date de publication
        if (array_key_exists('status', $data)) {
            $current = $this->findById($id);
            if ($data['status'] === 'published' && $current['status'] !== 'published') {
                $fields[] = '`published_at` = ?';
                $values[] = date('Y-m-d H:i:s');
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = 'UPDATE blog_posts SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Supprime un article
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM blog_posts WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Change le statut d'un article
     */
    public function toggleStatus(int $id): bool
    {
        $post = $this->findById($id);
        if (!$post) return false;

        $newStatus = $post['status'] === 'published' ? 'draft' : 'published';
        return $this->update($id, ['status' => $newStatus]);
    }

    /**
     * Compte les articles publiés
     */
    public function countPublished(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM blog_posts WHERE status = "published"');
        return (int) $stmt->fetchColumn();
    }

    /**
     * Génère un slug unique à partir du titre
     */
    private function generateSlug(string $title, ?int $excludeId = null): string
    {
        // Convertir en minuscules et remplacer les caractères spéciaux
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[àáâãäå]/u', 'a', $slug);
        $slug = preg_replace('/[èéêë]/u', 'e', $slug);
        $slug = preg_replace('/[ìíîï]/u', 'i', $slug);
        $slug = preg_replace('/[òóôõö]/u', 'o', $slug);
        $slug = preg_replace('/[ùúûü]/u', 'u', $slug);
        $slug = preg_replace('/[ç]/u', 'c', $slug);
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
        $sql = 'SELECT COUNT(*) FROM blog_posts WHERE slug = ?';
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
     * Récupère les statuts disponibles
     */
    public function getStatuses(): array
    {
        return self::STATUSES;
    }
}
