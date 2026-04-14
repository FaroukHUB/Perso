<?php
/**
 * PERSONNALY - Model TrustBadge
 * Gestion des badges de confiance
 */

require_once __DIR__ . '/../core/Database.php';

class TrustBadge
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAllActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM trust_badges WHERE active = 1 ORDER BY sort_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM trust_badges ORDER BY sort_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM trust_badges WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('SELECT MAX(sort_order) as max_order FROM trust_badges');
        $stmt->execute();
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $this->db->prepare(
            'INSERT INTO trust_badges (title, icon, sort_order, active) VALUES (?, ?, ?, 1)'
        );
        $stmt->execute([
            $data['title'],
            $data['icon'] ?? null,
            $maxOrder + 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE trust_badges SET title = ?, icon = ? WHERE id = ?'
        );
        return $stmt->execute([$data['title'], $data['icon'] ?? null, $id]);
    }

    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE trust_badges SET active = NOT active WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM trust_badges WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function reorder(array $ids): bool
    {
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $this->db->prepare('UPDATE trust_badges SET sort_order = ? WHERE id = ?');
            $stmt->execute([$order, (int)$id]);
            $order++;
        }
        return true;
    }
}
