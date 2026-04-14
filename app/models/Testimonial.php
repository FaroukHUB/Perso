<?php
/**
 * PERSONNALY - Model Testimonial
 * Gestion des témoignages clients
 */

require_once __DIR__ . '/../core/Database.php';

class Testimonial
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAllActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM testimonials WHERE active = 1 ORDER BY sort_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM testimonials ORDER BY sort_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM testimonials WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) as max_order FROM testimonials');
        $stmt->execute();
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO testimonials (name, photo_url, rating, content, sort_order, active) VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $data['name'],
            $data['photo_url'] ?? null,
            $data['rating'] ?? 5,
            $data['content'],
            $maxOrder + 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE testimonials SET name = ?, photo_url = ?, rating = ?, content = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['name'],
            $data['photo_url'] ?? null,
            $data['rating'] ?? 5,
            $data['content'],
            $id
        ]);
    }

    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE testimonials SET active = NOT active WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM testimonials WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare('UPDATE testimonials SET sort_order = ? WHERE id = ?');
            $stmt->execute([$order, (int)$id]);
            $order++;
        }
        return true;
    }
}
