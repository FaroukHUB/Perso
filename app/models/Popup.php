<?php
/**
 * Modèle Popup
 * Gestion des popups administrables avec règles d'affichage et analytics
 */

require_once __DIR__ . '/../core/Database.php';

class Popup
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupérer toutes les popups
     */
    public function findAll(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM popups
            ORDER BY priority DESC, created_at DESC
        ');
        return $stmt->fetchAll();
    }

    /**
     * Récupérer les popups actives
     */
    public function findActive(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM popups
            WHERE is_active = 1
            ORDER BY priority DESC, created_at DESC
        ');
        return $stmt->fetchAll();
    }

    /**
     * Récupérer une popup par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM popups WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Récupérer la popup à afficher selon les règles
     * (priorité la plus haute + règles de ciblage)
     */
    public function getPopupToDisplay(string $currentUrl, bool $isNewVisitor = false): ?array
    {
        // Récupérer toutes les popups actives
        $popups = $this->findActive();

        // Filtrer selon le ciblage de page
        $matchingPopups = [];
        foreach ($popups as $popup) {
            if ($this->matchesPageTarget($popup, $currentUrl)) {
                if ($this->matchesVisitorTarget($popup, $isNewVisitor)) {
                    $matchingPopups[] = $popup;
                }
            }
        }

        // Retourner la popup avec la priorité la plus haute
        return !empty($matchingPopups) ? $matchingPopups[0] : null;
    }

    /**
     * Vérifier si la popup correspond à la page ciblée
     */
    private function matchesPageTarget(array $popup, string $currentUrl): bool
    {
        switch ($popup['target_pages']) {
            case 'all':
                return true;

            case 'home':
                return $currentUrl === '/' || $currentUrl === '/public/' || $currentUrl === '/public/index.php';

            case 'products':
                return strpos($currentUrl, '/product') !== false || strpos($currentUrl, '/produit') !== false;

            case 'cart':
                return strpos($currentUrl, '/cart') !== false || strpos($currentUrl, '/panier') !== false;

            case 'checkout':
                return strpos($currentUrl, '/checkout') !== false;

            case 'specific':
                if (empty($popup['target_urls'])) {
                    return false;
                }
                $targetUrls = json_decode($popup['target_urls'], true);
                if (!is_array($targetUrls)) {
                    return false;
                }
                foreach ($targetUrls as $targetUrl) {
                    if (strpos($currentUrl, $targetUrl) !== false) {
                        return true;
                    }
                }
                return false;

            default:
                return false;
        }
    }

    /**
     * Vérifier si la popup correspond au type de visiteur
     */
    private function matchesVisitorTarget(array $popup, bool $isNewVisitor): bool
    {
        switch ($popup['target_visitors']) {
            case 'all':
                return true;
            case 'new':
                return $isNewVisitor;
            case 'returning':
                return !$isNewVisitor;
            default:
                return true;
        }
    }

    /**
     * Créer une popup
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO popups (
                title, content, image_url,
                cta_text, cta_url, cta_new_tab, promo_code,
                trigger_type, trigger_value,
                frequency,
                target_pages, target_urls,
                target_visitors,
                show_close_button, click_outside_to_close, show_never_show_again, auto_close_after,
                template_type, size, animation,
                is_active, priority
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?,
                ?, ?,
                ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?
            )
        ');

        $stmt->execute([
            $data['title'] ?? '',
            $data['content'] ?? '',
            $data['image_url'] ?? null,
            $data['cta_text'] ?? null,
            $data['cta_url'] ?? null,
            $data['cta_new_tab'] ?? false,
            $data['promo_code'] ?? null,
            $data['trigger_type'] ?? 'immediate',
            $data['trigger_value'] ?? null,
            $data['frequency'] ?? 'per_session',
            $data['target_pages'] ?? 'all',
            $data['target_urls'] ?? null,
            $data['target_visitors'] ?? 'all',
            $data['show_close_button'] ?? true,
            $data['click_outside_to_close'] ?? true,
            $data['show_never_show_again'] ?? false,
            $data['auto_close_after'] ?? null,
            $data['template_type'] ?? 'modal',
            $data['size'] ?? 'medium',
            $data['animation'] ?? 'fade',
            $data['is_active'] ?? true,
            $data['priority'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Mettre à jour une popup
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE popups SET
                title = ?,
                content = ?,
                image_url = ?,
                cta_text = ?,
                cta_url = ?,
                cta_new_tab = ?,
                promo_code = ?,
                trigger_type = ?,
                trigger_value = ?,
                frequency = ?,
                target_pages = ?,
                target_urls = ?,
                target_visitors = ?,
                show_close_button = ?,
                click_outside_to_close = ?,
                show_never_show_again = ?,
                auto_close_after = ?,
                template_type = ?,
                size = ?,
                animation = ?,
                is_active = ?,
                priority = ?
            WHERE id = ?
        ');

        return $stmt->execute([
            $data['title'] ?? '',
            $data['content'] ?? '',
            $data['image_url'] ?? null,
            $data['cta_text'] ?? null,
            $data['cta_url'] ?? null,
            $data['cta_new_tab'] ?? false,
            $data['promo_code'] ?? null,
            $data['trigger_type'] ?? 'immediate',
            $data['trigger_value'] ?? null,
            $data['frequency'] ?? 'per_session',
            $data['target_pages'] ?? 'all',
            $data['target_urls'] ?? null,
            $data['target_visitors'] ?? 'all',
            $data['show_close_button'] ?? true,
            $data['click_outside_to_close'] ?? true,
            $data['show_never_show_again'] ?? false,
            $data['auto_close_after'] ?? null,
            $data['template_type'] ?? 'modal',
            $data['size'] ?? 'medium',
            $data['animation'] ?? 'fade',
            $data['is_active'] ?? true,
            $data['priority'] ?? 0,
            $id
        ]);
    }

    /**
     * Supprimer une popup
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM popups WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Activer/désactiver une popup
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('
            UPDATE popups
            SET is_active = NOT is_active
            WHERE id = ?
        ');
        return $stmt->execute([$id]);
    }

    /**
     * Incrémenter le compteur de vues
     */
    public function incrementViews(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE popups SET total_views = total_views + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Incrémenter le compteur de clics
     */
    public function incrementClicks(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE popups SET total_clicks = total_clicks + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Incrémenter le compteur de fermetures
     */
    public function incrementCloses(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE popups SET total_closes = total_closes + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Enregistrer une action dans popup_views (analytics détaillés)
     */
    public function trackAction(int $popupId, string $action, array $data = []): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO popup_views (
                popup_id, session_id, action, ip_address, user_agent, page_url
            ) VALUES (?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $popupId,
            $data['session_id'] ?? session_id(),
            $action,
            $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            $data['page_url'] ?? $_SERVER['REQUEST_URI'] ?? null,
        ]);
    }

    /**
     * Récupérer les stats d'une popup
     */
    public function getStats(int $id): array
    {
        $popup = $this->findById($id);
        if (!$popup) {
            return [];
        }

        // Calcul du taux de conversion et autres métriques
        $views = $popup['total_views'];
        $clicks = $popup['total_clicks'];
        $closes = $popup['total_closes'];

        $clickRate = $views > 0 ? ($clicks / $views) * 100 : 0;
        $closeRate = $views > 0 ? ($closes / $views) * 100 : 0;

        return [
            'total_views' => $views,
            'total_clicks' => $clicks,
            'total_closes' => $closes,
            'click_rate' => round($clickRate, 2),
            'close_rate' => round($closeRate, 2),
        ];
    }

    /**
     * Récupérer les stats détaillées par jour (7 derniers jours)
     */
    public function getDetailedStats(int $id): array
    {
        $stmt = $this->db->prepare('
            SELECT
                DATE(created_at) as date,
                action,
                COUNT(*) as count
            FROM popup_views
            WHERE popup_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at), action
            ORDER BY date DESC
        ');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }
}
