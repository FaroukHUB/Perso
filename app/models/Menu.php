<?php
/**
 * PERSONNALY - Modèle Menu
 * Gestion des menus de navigation
 */

require_once __DIR__ . '/../core/Database.php';

class Menu
{
    private $db;

    // Emplacements de menus prédéfinis
    const LOCATIONS = [
        'header_main' => 'Menu Principal (Header)',
        'footer_main' => 'Menu Footer',
        'footer_legal' => 'Liens Légaux (Footer)'
    ];

    // Types de liens
    const LINK_TYPES = [
        'custom' => 'Lien personnalisé',
        'page' => 'Page',
        'category' => 'Catégorie',
        'home' => 'Accueil',
        'products' => 'Tous les produits',
        'packs' => 'Packs / Idées cadeaux',
        'blog' => 'Blog',
        'contact' => 'Contact'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère un menu par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM menus WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Récupère un menu par emplacement
     */
    public function findByLocation(string $location): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM menus WHERE location = ? LIMIT 1');
        $stmt->execute([$location]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Liste tous les menus
     */
    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT m.*, (SELECT COUNT(*) FROM menu_items mi WHERE mi.menu_id = m.id) as item_count FROM menus m ORDER BY m.name ASC');
        return $stmt->fetchAll();
    }

    /**
     * Crée un nouveau menu
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO menus (name, location, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([
            $data['name'],
            $data['location']
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un menu
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE menus SET name = ?, location = ? WHERE id = ?');
        return $stmt->execute([
            $data['name'],
            $data['location'],
            $id
        ]);
    }

    /**
     * Supprime un menu
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM menus WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // ========== MENU ITEMS ==========

    /**
     * Récupère les éléments d'un menu
     */
    public function getItems(int $menuId, bool $activeOnly = false): array
    {
        $sql = 'SELECT mi.*, p.title as page_title, p.slug as page_slug
                FROM menu_items mi
                LEFT JOIN pages p ON mi.page_id = p.id
                WHERE mi.menu_id = ?';
        if ($activeOnly) {
            $sql .= ' AND mi.status = "active"';
        }
        $sql .= ' ORDER BY mi.sort_order ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$menuId]);
        $items = $stmt->fetchAll();

        // Construire l'arborescence
        return $this->buildTree($items);
    }

    /**
     * Récupère les éléments d'un menu par emplacement (pour le front)
     */
    public function getItemsByLocation(string $location): array
    {
        $menu = $this->findByLocation($location);
        if (!$menu) return [];

        return $this->getItems($menu['id'], true);
    }

    /**
     * Construit l'arborescence des éléments
     */
    private function buildTree(array $items, ?int $parentId = null): array
    {
        $branch = [];

        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $item['children'] = $this->buildTree($items, $item['id']);
                $item['url'] = $this->resolveUrl($item);
                $branch[] = $item;
            }
        }

        return $branch;
    }

    /**
     * Résout l'URL finale d'un élément de menu
     */
    private function resolveUrl(array $item): string
    {
        if (!empty($item['url'])) {
            return $item['url'];
        }

        switch ($item['link_type']) {
            case 'page':
                return $item['page_slug'] ? '/' . $item['page_slug'] : '/';
            case 'home':
                return '/';
            case 'products':
                return '/produits';
            case 'packs':
                return '/packs';
            case 'blog':
                return '/blog';
            case 'contact':
                return '/contact';
            case 'category':
                return '/categorie/' . $item['link_target'];
            default:
                return '#';
        }
    }

    /**
     * Ajoute un élément au menu
     */
    public function addItem(int $menuId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO menu_items
             (menu_id, parent_id, label, url, page_id, link_type, link_target, open_new_tab, css_class, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $menuId,
            $data['parent_id'] ?? null,
            $data['label'],
            $data['url'] ?? null,
            $data['page_id'] ?? null,
            $data['link_type'] ?? 'custom',
            $data['link_target'] ?? null,
            $data['open_new_tab'] ?? 0,
            $data['css_class'] ?? null,
            $data['sort_order'] ?? $this->getNextSortOrder($menuId),
            $data['status'] ?? 'active'
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un élément de menu
     */
    public function updateItem(int $itemId, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = ['parent_id', 'label', 'url', 'page_id', 'link_type', 'link_target', 'open_new_tab', 'css_class', 'sort_order', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $values[] = $itemId;
        $sql = 'UPDATE menu_items SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Supprime un élément de menu
     */
    public function deleteItem(int $itemId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM menu_items WHERE id = ?');
        return $stmt->execute([$itemId]);
    }

    /**
     * Met à jour l'ordre des éléments
     */
    public function updateItemOrder(array $orderedIds): bool
    {
        $stmt = $this->db->prepare('UPDATE menu_items SET sort_order = ? WHERE id = ?');

        foreach ($orderedIds as $order => $id) {
            $stmt->execute([$order, $id]);
        }

        return true;
    }

    /**
     * Récupère un élément de menu par ID
     */
    public function findItemById(int $itemId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM menu_items WHERE id = ? LIMIT 1');
        $stmt->execute([$itemId]);
        return $stmt->fetch() ?: null;
    }

    // ========== HELPERS ==========

    /**
     * Récupère le prochain sort_order disponible
     */
    private function getNextSortOrder(int $menuId): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) FROM menu_items WHERE menu_id = ?');
        $stmt->execute([$menuId]);
        return ((int) $stmt->fetchColumn()) + 1;
    }

    /**
     * Récupère les emplacements disponibles
     */
    public function getLocations(): array
    {
        return self::LOCATIONS;
    }

    /**
     * Récupère les types de liens disponibles
     */
    public function getLinkTypes(): array
    {
        return self::LINK_TYPES;
    }

    /**
     * Liste les pages disponibles pour le menu
     */
    public function getAvailablePages(): array
    {
        $stmt = $this->db->query('SELECT id, title, slug FROM pages WHERE status = "published" ORDER BY title ASC');
        return $stmt->fetchAll();
    }

    /**
     * Liste les catégories disponibles pour le menu
     */
    public function getAvailableCategories(): array
    {
        $stmt = $this->db->query('SELECT id, name, slug FROM categories WHERE active = 1 ORDER BY sort_order ASC, name ASC');
        return $stmt->fetchAll();
    }
}
