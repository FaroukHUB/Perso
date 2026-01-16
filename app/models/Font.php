<?php
/**
 * PERSONNALY - Model Font
 * Gestion des polices administrables (Google Fonts + Custom)
 */

require_once __DIR__ . '/../core/Database.php';

class Font
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================
    // LECTURE
    // =========================================

    /**
     * Récupère toutes les polices
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM fonts ORDER BY sort_order ASC, name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les polices actives uniquement
     */
    public function findActive(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM fonts WHERE active = 1 ORDER BY sort_order ASC, name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les polices actives par catégorie
     */
    public function findActiveByCategory(string $category): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fonts WHERE active = 1 AND category = ? ORDER BY sort_order ASC'
        );
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une police par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fonts WHERE id = ?');
        $stmt->execute([$id]);
        $font = $stmt->fetch(PDO::FETCH_ASSOC);
        return $font ?: null;
    }

    /**
     * Récupère une police par css_key
     */
    public function findByCssKey(string $cssKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fonts WHERE css_key = ?');
        $stmt->execute([$cssKey]);
        $font = $stmt->fetch(PDO::FETCH_ASSOC);
        return $font ?: null;
    }

    // =========================================
    // CRÉATION / MODIFICATION
    // =========================================

    /**
     * Crée une nouvelle police
     */
    public function create(array $data): int
    {
        // Générer css_key automatiquement
        $cssKey = $this->generateCssKey($data['family'], $data['google_weights'] ?? '400');

        // Générer google_import_url si source = google
        $googleImportUrl = null;
        if (($data['source'] ?? 'google') === 'google') {
            $googleImportUrl = $this->generateGoogleUrl($data['family'], $data['google_weights'] ?? '400');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO fonts (name, family, css_key, source, google_weights, google_import_url,
             custom_woff2_url, custom_woff_url, category, active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['name'],
            $data['family'],
            $cssKey,
            $data['source'] ?? 'google',
            $data['google_weights'] ?? '400',
            $googleImportUrl,
            $data['custom_woff2_url'] ?? null,
            $data['custom_woff_url'] ?? null,
            $data['category'] ?? 'sans-serif',
            $data['active'] ?? 1,
            $data['sort_order'] ?? 0
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour une police
     */
    public function update(int $id, array $data): bool
    {
        $font = $this->findById($id);
        if (!$font) {
            return false;
        }

        // Regénérer css_key si family ou weights changent
        $family = $data['family'] ?? $font['family'];
        $weights = $data['google_weights'] ?? $font['google_weights'];
        $source = $data['source'] ?? $font['source'];

        $cssKey = $this->generateCssKey($family, $weights);

        // Regénérer google_import_url si source = google
        $googleImportUrl = null;
        if ($source === 'google') {
            $googleImportUrl = $this->generateGoogleUrl($family, $weights);
        }

        $stmt = $this->db->prepare(
            'UPDATE fonts SET
             name = ?, family = ?, css_key = ?, source = ?, google_weights = ?, google_import_url = ?,
             custom_woff2_url = ?, custom_woff_url = ?, category = ?, active = ?, sort_order = ?,
             updated_at = NOW()
             WHERE id = ?'
        );

        return $stmt->execute([
            $data['name'] ?? $font['name'],
            $family,
            $cssKey,
            $source,
            $weights,
            $googleImportUrl,
            $data['custom_woff2_url'] ?? $font['custom_woff2_url'],
            $data['custom_woff_url'] ?? $font['custom_woff_url'],
            $data['category'] ?? $font['category'],
            $data['active'] ?? $font['active'],
            $data['sort_order'] ?? $font['sort_order'],
            $id
        ]);
    }

    /**
     * Supprime une police
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM fonts WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Active/désactive une police
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE fonts SET active = NOT active, updated_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    // =========================================
    // GÉNÉRATION AUTOMATIQUE
    // =========================================

    /**
     * Génère un css_key unique à partir de family et weights
     * Ex: "Poppins" + "400;600;700" → "poppins_400_600_700"
     */
    public function generateCssKey(string $family, string $weights): string
    {
        // Nettoyer le nom de famille
        $key = strtolower($family);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');

        // Ajouter les weights
        $weightsClean = str_replace(';', '_', $weights);
        $weightsClean = preg_replace('/[^0-9_]+/', '', $weightsClean);

        return $key . '_' . $weightsClean;
    }

    /**
     * Génère l'URL d'import Google Fonts
     * Ex: "Poppins" + "400;600;700" → "https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap"
     */
    public function generateGoogleUrl(string $family, string $weights): string
    {
        // Encoder le nom de famille pour l'URL
        $familyEncoded = str_replace(' ', '+', $family);

        // Formater les weights pour Google Fonts API
        $weightsFormatted = str_replace(';', ';', $weights);

        return "https://fonts.googleapis.com/css2?family={$familyEncoded}:wght@{$weightsFormatted}&display=swap";
    }

    /**
     * Régénère toutes les google_import_url pour les polices Google
     * Utile après une migration ou correction
     */
    public function regenerateAllGoogleUrls(): int
    {
        $fonts = $this->findAll();
        $count = 0;

        foreach ($fonts as $font) {
            if ($font['source'] === 'google') {
                $newUrl = $this->generateGoogleUrl($font['family'], $font['google_weights']);
                $newCssKey = $this->generateCssKey($font['family'], $font['google_weights']);

                $stmt = $this->db->prepare(
                    'UPDATE fonts SET google_import_url = ?, css_key = ?, updated_at = NOW() WHERE id = ?'
                );
                $stmt->execute([$newUrl, $newCssKey, $font['id']]);
                $count++;
            }
        }

        return $count;
    }

    // =========================================
    // UTILITAIRES
    // =========================================

    /**
     * Vérifie si un css_key existe déjà (hors ID donné)
     */
    public function cssKeyExists(string $cssKey, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM fonts WHERE css_key = ? AND id != ?');
            $stmt->execute([$cssKey, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM fonts WHERE css_key = ?');
            $stmt->execute([$cssKey]);
        }
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère les catégories disponibles
     */
    public function getCategories(): array
    {
        return [
            'sans-serif' => 'Sans Serif',
            'serif' => 'Serif',
            'script' => 'Script',
            'display' => 'Display',
            'handwriting' => 'Manuscrite'
        ];
    }

    /**
     * Compte les polices par source
     */
    public function countBySource(): array
    {
        $stmt = $this->db->query(
            'SELECT source, COUNT(*) as count FROM fonts GROUP BY source'
        );
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = ['google' => 0, 'custom' => 0];
        foreach ($results as $row) {
            $counts[$row['source']] = (int) $row['count'];
        }
        return $counts;
    }
}
