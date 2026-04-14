<?php
/**
 * PERSONNALY - Model HeroSlide
 * Gestion des slides du carrousel hero
 */

require_once __DIR__ . '/../core/Database.php';

class HeroSlide
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAllActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM hero_slides WHERE active = 1 ORDER BY sort_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM hero_slides ORDER BY sort_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM hero_slides WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) as max_order FROM hero_slides');
        $stmt->execute();
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO hero_slides (title, subtitle, cta_text, cta_url, image_url, sort_order, active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $data['title'],
            $data['subtitle'] ?? null,
            $data['cta_text'] ?? null,
            $data['cta_url'] ?? null,
            $data['image_url'] ?? null,
            $maxOrder + 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach (['title', 'subtitle', 'cta_text', 'cta_url', 'image_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $values[] = $id;
        $sql = 'UPDATE hero_slides SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE hero_slides SET active = NOT active WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM hero_slides WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare('UPDATE hero_slides SET sort_order = ? WHERE id = ?');
            $stmt->execute([$order, (int)$id]);
            $order++;
        }
        return true;
    }
}
