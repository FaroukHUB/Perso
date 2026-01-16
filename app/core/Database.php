<?php
/**
 * PERSONNALY - Classe Database (Singleton PDO)
 */

class Database
{
    private static ?PDO $instance = null;

    /**
     * Empêche l'instanciation directe
     */
    private function __construct() {}

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Retourne l'instance PDO (singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {
                // En production, logger l'erreur sans exposer les détails
                die('Erreur de connexion à la base de données.');
            }
        }

        return self::$instance;
    }

    /**
     * Raccourci pour obtenir la connexion
     */
    public static function getConnection(): PDO
    {
        return self::getInstance();
    }

    /**
     * Test de connexion (retourne true/false)
     */
    public static function testConnection(): bool
    {
        try {
            $pdo = self::getInstance();
            $pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
