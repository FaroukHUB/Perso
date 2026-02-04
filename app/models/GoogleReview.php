<?php
/**
 * PERSONNALY - Modèle GoogleReview
 * Cache et récupération des avis Google
 */

require_once __DIR__ . '/../core/Database.php';

class GoogleReview
{
    private $db;
    private $apiKey;
    private $placeId;
    private $cacheHours;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadSettings();
    }

    private function loadSettings(): void
    {
        $stmt = $this->db->query(
            "SELECT setting_key, setting_value FROM site_settings
             WHERE setting_key IN ('google_maps_api_key', 'google_place_id', 'google_reviews_cache_hours')"
        );
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->apiKey = $settings['google_maps_api_key'] ?? '';
        $this->placeId = $settings['google_place_id'] ?? '';
        $this->cacheHours = (int) ($settings['google_reviews_cache_hours'] ?? 24);
    }

    /**
     * Récupère les avis (depuis le cache ou l'API)
     */
    public function getReviews(int $limit = 5, int $minRating = 1): array
    {
        // Vérifier le cache
        $cacheExpiry = date('Y-m-d H:i:s', strtotime("-{$this->cacheHours} hours"));

        $stmt = $this->db->prepare(
            'SELECT * FROM google_reviews_cache
             WHERE place_id = ? AND rating >= ? AND fetched_at > ?
             ORDER BY time DESC
             LIMIT ?'
        );
        $stmt->execute([$this->placeId, $minRating, $cacheExpiry, $limit]);
        $cached = $stmt->fetchAll();

        if (count($cached) >= $limit) {
            return $cached;
        }

        // Rafraîchir le cache si nécessaire
        if ($this->apiKey && $this->placeId) {
            $this->refreshCache();

            // Réessayer depuis le cache
            $stmt->execute([$this->placeId, $minRating, $cacheExpiry, $limit]);
            return $stmt->fetchAll();
        }

        return $cached;
    }

    /**
     * Rafraîchit le cache depuis l'API Google
     */
    public function refreshCache(): bool
    {
        if (!$this->apiKey || !$this->placeId) {
            return false;
        }

        $url = "https://maps.googleapis.com/maps/api/place/details/json?" . http_build_query([
            'place_id' => $this->placeId,
            'fields' => 'reviews',
            'key' => $this->apiKey,
            'language' => 'fr'
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);

        if (!isset($data['result']['reviews'])) {
            return false;
        }

        // Supprimer l'ancien cache pour ce place_id
        $stmt = $this->db->prepare('DELETE FROM google_reviews_cache WHERE place_id = ?');
        $stmt->execute([$this->placeId]);

        // Insérer les nouveaux avis
        $stmt = $this->db->prepare(
            'INSERT INTO google_reviews_cache
             (place_id, author_name, author_photo_url, rating, text, time, language, fetched_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        foreach ($data['result']['reviews'] as $review) {
            $stmt->execute([
                $this->placeId,
                $review['author_name'] ?? 'Anonyme',
                $review['profile_photo_url'] ?? null,
                $review['rating'] ?? 5,
                $review['text'] ?? null,
                date('Y-m-d H:i:s', $review['time'] ?? time()),
                $review['language'] ?? 'fr'
            ]);
        }

        return true;
    }

    /**
     * Ajoute un avis manuellement (si pas d'API)
     */
    public function addManual(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO google_reviews_cache
             (place_id, author_name, author_photo_url, rating, text, time, language, fetched_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['place_id'] ?? 'manual',
            $data['author_name'],
            $data['author_photo_url'] ?? null,
            $data['rating'] ?? 5,
            $data['text'] ?? null,
            $data['time'] ?? date('Y-m-d H:i:s'),
            $data['language'] ?? 'fr'
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Supprime un avis du cache
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM google_reviews_cache WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Récupère les statistiques
     */
    public function getStats(): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) as total,
                AVG(rating) as average_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_stars,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_stars,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_stars,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
             FROM google_reviews_cache
             WHERE place_id = ?'
        );
        $stmt->execute([$this->placeId ?: 'manual']);
        return $stmt->fetch() ?: [
            'total' => 0,
            'average_rating' => 0,
            'five_stars' => 0,
            'four_stars' => 0,
            'three_stars' => 0,
            'two_stars' => 0,
            'one_star' => 0
        ];
    }

    /**
     * Vérifie si l'API est configurée
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->placeId);
    }

    public function getPlaceId(): string
    {
        return $this->placeId;
    }
}
