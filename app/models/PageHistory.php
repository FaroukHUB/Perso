<?php
/**
 * PERSONNALY - Modèle PageHistory
 * Historique des modifications (undo/redo)
 */

require_once __DIR__ . '/../core/Database.php';

class PageHistory
{
    private $db;
    private const MAX_HISTORY_PER_PAGE = 50;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM page_history WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $history = $stmt->fetch();
        if ($history) {
            $history['data_before'] = json_decode($history['data_before'], true);
            $history['data_after'] = json_decode($history['data_after'], true);
        }
        return $history ?: null;
    }

    /**
     * Récupère l'historique d'une page
     */
    public function findByPage(int $pageId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM page_history
             WHERE page_id = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$pageId, $limit]);
        $histories = $stmt->fetchAll();

        foreach ($histories as &$history) {
            $history['data_before'] = json_decode($history['data_before'], true);
            $history['data_after'] = json_decode($history['data_after'], true);
        }

        return $histories;
    }

    /**
     * Enregistre une action dans l'historique
     */
    public function record(int $pageId, string $action, string $entityType, int $entityId, ?array $dataBefore = null, ?array $dataAfter = null, ?int $userId = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO page_history (page_id, user_id, action, entity_type, entity_id, data_before, data_after, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $pageId,
            $userId,
            $action,
            $entityType,
            $entityId,
            $dataBefore ? json_encode($dataBefore, JSON_UNESCAPED_UNICODE) : null,
            $dataAfter ? json_encode($dataAfter, JSON_UNESCAPED_UNICODE) : null
        ]);

        $historyId = (int) $this->db->lastInsertId();

        // Nettoyer l'ancien historique
        $this->cleanup($pageId);

        return $historyId;
    }

    /**
     * Annule une action (undo)
     */
    public function undo(int $historyId): bool
    {
        $history = $this->findById($historyId);
        if (!$history || !$history['data_before']) {
            return false;
        }

        return $this->restoreEntity($history['entity_type'], $history['entity_id'], $history['data_before'], $history['action']);
    }

    /**
     * Rétablit une action annulée (redo)
     */
    public function redo(int $historyId): bool
    {
        $history = $this->findById($historyId);
        if (!$history || !$history['data_after']) {
            return false;
        }

        return $this->restoreEntity($history['entity_type'], $history['entity_id'], $history['data_after'], $history['action']);
    }

    /**
     * Restaure une entité à un état précédent
     */
    private function restoreEntity(string $entityType, int $entityId, array $data, string $originalAction): bool
    {
        switch ($entityType) {
            case 'section':
                require_once __DIR__ . '/PageSection.php';
                $model = new PageSection();

                if ($originalAction === 'delete') {
                    // Recréer la section
                    $data['id'] = $entityId;
                    return $this->recreateSection($data);
                } elseif ($originalAction === 'create') {
                    // Supprimer la section
                    return $model->delete($entityId);
                } else {
                    // Update
                    return $model->update($entityId, $data);
                }

            case 'faq_item':
                require_once __DIR__ . '/SectionFaq.php';
                $model = new SectionFaq();
                if ($originalAction === 'delete') {
                    return (bool) $model->create($data);
                } elseif ($originalAction === 'create') {
                    return $model->delete($entityId);
                }
                return $model->update($entityId, $data);

            case 'testimonial':
                require_once __DIR__ . '/SectionTestimonial.php';
                $model = new SectionTestimonial();
                if ($originalAction === 'delete') {
                    return (bool) $model->create($data);
                } elseif ($originalAction === 'create') {
                    return $model->delete($entityId);
                }
                return $model->update($entityId, $data);

            case 'gallery_image':
                require_once __DIR__ . '/SectionGallery.php';
                $model = new SectionGallery();
                if ($originalAction === 'delete') {
                    return (bool) $model->create($data);
                } elseif ($originalAction === 'create') {
                    return $model->delete($entityId);
                }
                return $model->update($entityId, $data);

            default:
                return false;
        }
    }

    /**
     * Recrée une section supprimée
     */
    private function recreateSection(array $data): bool
    {
        // Cette fonction est complexe car elle doit recréer la section
        // avec le même ID, ce qui n'est pas toujours possible
        // Pour simplifier, on la recrée avec un nouvel ID
        require_once __DIR__ . '/PageSection.php';
        $model = new PageSection();

        unset($data['id']); // Laisser la BDD générer un nouvel ID
        $newId = $model->create($data);

        return $newId > 0;
    }

    /**
     * Nettoie l'ancien historique
     */
    public function cleanup(int $pageId): void
    {
        // Compter les entrées
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM page_history WHERE page_id = ?');
        $stmt->execute([$pageId]);
        $count = (int) $stmt->fetchColumn();

        if ($count > self::MAX_HISTORY_PER_PAGE) {
            // Supprimer les plus anciennes
            $toDelete = $count - self::MAX_HISTORY_PER_PAGE;
            $stmt = $this->db->prepare(
                'DELETE FROM page_history
                 WHERE page_id = ?
                 ORDER BY created_at ASC
                 LIMIT ?'
            );
            $stmt->execute([$pageId, $toDelete]);
        }
    }

    /**
     * Supprime tout l'historique d'une page
     */
    public function clearPageHistory(int $pageId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM page_history WHERE page_id = ?');
        return $stmt->execute([$pageId]);
    }

    /**
     * Récupère le dernier enregistrement pour undo
     */
    public function getLastUndoable(int $pageId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM page_history
             WHERE page_id = ? AND data_before IS NOT NULL
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$pageId]);
        $history = $stmt->fetch();

        if ($history) {
            $history['data_before'] = json_decode($history['data_before'], true);
            $history['data_after'] = json_decode($history['data_after'], true);
        }

        return $history ?: null;
    }
}
