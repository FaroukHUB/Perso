<?php
/**
 * PERSONNALY - Model CustomizationOption
 * Gestion des options de personnalisation (tailles, couleurs texte, techniques)
 */

require_once __DIR__ . '/../core/Database.php';

class CustomizationOption
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère toutes les options actives d'un type
     */
    public function findByType(string $type): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM customization_options
                 WHERE type = ? AND active = 1
                 ORDER BY sort_order ASC'
            );
            $stmt->execute([$type]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table n'existe pas encore - retourner tableau vide
            return [];
        }
    }

    /**
     * Récupère toutes les options d'un type (actives ou non)
     */
    public function findAllByType(string $type): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM customization_options
                 WHERE type = ?
                 ORDER BY sort_order ASC'
            );
            $stmt->execute([$type]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Récupère une option par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customization_options WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crée une nouvelle option
     */
    public function create(array $data): int
    {
        // Récupérer le prochain sort_order
        $stmt = $this->db->prepare(
            'SELECT MAX(sort_order) as max_order FROM customization_options WHERE type = ?'
        );
        $stmt->execute([$data['type']]);
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO customization_options (type, value, label, hex_code, price, description, sort_order, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())'
        );
        $stmt->execute([
            $data['type'],
            $data['value'],
            $data['label'],
            $data['hex_code'] ?? null,
            $data['price'] ?? null,
            $data['description'] ?? null,
            $maxOrder + 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour une option
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE customization_options
             SET value = ?, label = ?, hex_code = ?, price = ?, description = ?
             WHERE id = ?'
        );
        return $stmt->execute([
            $data['value'],
            $data['label'],
            $data['hex_code'] ?? null,
            $data['price'] ?? null,
            $data['description'] ?? null,
            $id,
        ]);
    }

    /**
     * Active/désactive une option
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE customization_options SET active = NOT active WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Supprime une option
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM customization_options WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Réordonne les options
     */
    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare(
                'UPDATE customization_options SET sort_order = ? WHERE id = ?'
            );
            $stmt->execute([$order, $id]);
            $order++;
        }
        return true;
    }

    /**
     * Récupère les tailles actives
     */
    public function getSizes(): array
    {
        return $this->findByType('size');
    }

    /**
     * Récupère les couleurs produit actives (couleurs du vêtement)
     */
    public function getColors(): array
    {
        return $this->findByType('color');
    }

    /**
     * Récupère les couleurs de texte actives (pour personnalisation)
     */
    public function getTextColors(): array
    {
        return $this->findByType('text_color');
    }

    /**
     * Récupère les techniques de personnalisation actives
     */
    public function getTechniques(): array
    {
        return $this->findByType('technique');
    }
}
