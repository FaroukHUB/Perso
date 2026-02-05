<?php
/**
 * PERSONNALY - Modèle HomepageSection
 * Wrapper rétro-compatible autour de PageSection.
 * Toutes les sections sont désormais stockées dans `page_sections`
 * avec le page_id de la page d'accueil.
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/PageSection.php';

class HomepageSection
{
    private PageSection $delegate;
    private ?int $homePageId = null;

    public function __construct()
    {
        $this->delegate = new PageSection();

        // Trouver l'ID de la page d'accueil
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM pages WHERE slug = 'home' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        $this->homePageId = $id ? (int) $id : null;
    }

    /**
     * ID de la page d'accueil dans la table pages
     */
    public function getHomePageId(): ?int
    {
        return $this->homePageId;
    }

    // ========== LECTURE ==========

    public function findById(int $id): ?array
    {
        return $this->delegate->findById($id);
    }

    public function findAll(bool $activeOnly = false): array
    {
        if (!$this->homePageId) return [];
        $sections = $this->delegate->findByPage($this->homePageId, $activeOnly);

        // Ajouter item_count pour compatibilité avec homepage-builder
        foreach ($sections as &$section) {
            $section['item_count'] = $this->delegate->countItems($section['id']);
        }

        return $sections;
    }

    public function findActive(): array
    {
        if (!$this->homePageId) return [];
        return $this->delegate->findByPage($this->homePageId, true);
    }

    public function findByType(string $type, bool $activeOnly = true): array
    {
        if (!$this->homePageId) return [];
        return $this->delegate->findByType($this->homePageId, $type, $activeOnly);
    }

    // ========== ÉCRITURE ==========

    public function create(array $data): int
    {
        $data['page_id'] = $this->homePageId;
        return $this->delegate->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->delegate->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->delegate->delete($id);
    }

    public function toggleStatus(int $id): bool
    {
        return $this->delegate->toggleStatus($id);
    }

    /**
     * updateOrder sans page_id (compat homepage-builder)
     */
    public function updateOrder(array $orderedIds): bool
    {
        if (!$this->homePageId) return false;
        return $this->delegate->updateOrder($this->homePageId, $orderedIds);
    }

    // ========== ITEMS ==========

    public function getItems(int $sectionId): array
    {
        return $this->delegate->getItems($sectionId);
    }

    public function countItems(int $sectionId): int
    {
        return $this->delegate->countItems($sectionId);
    }

    public function setItems(int $sectionId, array $items): void
    {
        $this->delegate->setItems($sectionId, $items);
    }

    public function addItem(int $sectionId, string $itemType, int $itemId, int $sortOrder = 0): bool
    {
        return $this->delegate->addItem($sectionId, $itemType, $itemId, $sortOrder);
    }

    public function removeItem(int $sectionId, string $itemType, int $itemId): bool
    {
        return $this->delegate->removeItem($sectionId, $itemType, $itemId);
    }

    // ========== HELPERS ==========

    public function getTypes(): array
    {
        return PageSection::TYPES;
    }

    public function getStatuses(): array
    {
        return PageSection::STATUSES;
    }

    public function getMediaTypes(): array
    {
        return PageSection::MEDIA_TYPES;
    }
}
