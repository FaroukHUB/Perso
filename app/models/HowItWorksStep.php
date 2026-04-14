<?php
/**
 * PERSONNALY - Model HowItWorksStep
 * Gestion des étapes "Comment ça marche"
 */

require_once __DIR__ . '/../core/Database.php';

class HowItWorksStep
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAllActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM how_it_works_steps WHERE active = 1 ORDER BY sort_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM how_it_works_steps ORDER BY sort_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM how_it_works_steps WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) as max_order FROM how_it_works_steps');
        $stmt->execute();
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO how_it_works_steps (title, description, icon, image_url, sort_order, active) VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['icon'] ?? null,
            $data['image_url'] ?? null,
            $maxOrder + 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE how_it_works_steps SET title = ?, description = ?, icon = ?, image_url = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['icon'] ?? null,
            $data['image_url'] ?? null,
            $id
        ]);
    }

    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE how_it_works_steps SET active = NOT active WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM how_it_works_steps WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare('UPDATE how_it_works_steps SET sort_order = ? WHERE id = ?');
            $stmt->execute([$order, (int)$id]);
            $order++;
        }
        return true;
    }
}
