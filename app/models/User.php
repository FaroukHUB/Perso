<?php
/**
 * PERSONNALY - Modèle User
 */

require_once __DIR__ . '/../core/Database.php';

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Trouve un utilisateur par email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Trouve un utilisateur par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Crée un nouvel utilisateur
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['email'],
            $data['password_hash'],
            $data['role'] ?? 'client',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Liste tous les utilisateurs
     */
    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT id, email, role, created_at FROM users ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    /**
     * Compte les utilisateurs par rôle
     */
    public function countByRole(string $role): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        $stmt->execute([$role]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Met à jour un utilisateur
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $id;

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Supprime un utilisateur
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
