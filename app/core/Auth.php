<?php
/**
 * PERSONNALY - Classe Auth
 * Gestion de l'authentification admin/client
 */

class Auth
{
    /**
     * Démarre la session si pas déjà active
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Connecte un utilisateur
     */
    public static function login(array $user): void
    {
        self::startSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
    }

    /**
     * Déconnecte l'utilisateur
     */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public static function isLoggedIn(): bool
    {
        self::startSession();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Vérifie si l'utilisateur est admin
     */
    public static function isAdmin(): bool
    {
        self::startSession();
        return self::isLoggedIn() && $_SESSION['user_role'] === 'admin';
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public static function getUser(): ?array
    {
        self::startSession();
        if (!self::isLoggedIn()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
        ];
    }

    /**
     * Récupère l'ID de l'utilisateur connecté
     */
    public static function getUserId(): ?int
    {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Exige une connexion, sinon redirige
     */
    public static function requireLogin(string $redirect = '/admin/login.php'): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    /**
     * Exige un rôle admin, sinon redirige
     */
    public static function requireAdmin(string $redirect = '/admin/login.php'): void
    {
        if (!self::isAdmin()) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    /**
     * Hash un mot de passe
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Vérifie un mot de passe
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
