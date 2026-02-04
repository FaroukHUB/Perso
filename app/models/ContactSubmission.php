<?php
/**
 * PERSONNALY - Modèle ContactSubmission
 * Gestion des soumissions de formulaire de contact
 */

require_once __DIR__ . '/../core/Database.php';

class ContactSubmission
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère une soumission par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contact_form_submissions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Liste toutes les soumissions
     */
    public function findAll(bool $excludeSpam = true, ?int $limit = null): array
    {
        $sql = 'SELECT * FROM contact_form_submissions';
        if ($excludeSpam) {
            $sql .= ' WHERE is_spam = 0';
        }
        $sql .= ' ORDER BY created_at DESC';
        if ($limit) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Liste les soumissions non lues
     */
    public function findUnread(): array
    {
        $stmt = $this->db->query('SELECT * FROM contact_form_submissions WHERE is_read = 0 AND is_spam = 0 ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    /**
     * Compte les soumissions non lues
     */
    public function countUnread(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM contact_form_submissions WHERE is_read = 0 AND is_spam = 0');
        return (int) $stmt->fetchColumn();
    }

    /**
     * Crée une nouvelle soumission
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO contact_form_submissions
             (section_id, page_slug, name, email, phone, subject, message, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['section_id'] ?? null,
            $data['page_slug'] ?? null,
            $data['name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['subject'] ?? null,
            $data['message'],
            $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Marque une soumission comme lue
     */
    public function markAsRead(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE contact_form_submissions SET is_read = 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Marque plusieurs soumissions comme lues
     */
    public function markAllAsRead(): bool
    {
        $stmt = $this->db->prepare('UPDATE contact_form_submissions SET is_read = 1 WHERE is_read = 0');
        return $stmt->execute();
    }

    /**
     * Marque une soumission comme spam
     */
    public function markAsSpam(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE contact_form_submissions SET is_spam = 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime une soumission
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM contact_form_submissions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Supprime les soumissions spam plus anciennes que X jours
     */
    public function cleanupSpam(int $daysOld = 30): int
    {
        $stmt = $this->db->prepare('DELETE FROM contact_form_submissions WHERE is_spam = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    }

    /**
     * Vérifie si un email a trop de soumissions récentes (anti-spam basique)
     */
    public function hasRecentSubmissions(string $email, int $minutes = 10, int $maxCount = 3): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM contact_form_submissions
             WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$email, $minutes]);
        return (int) $stmt->fetchColumn() >= $maxCount;
    }
}
