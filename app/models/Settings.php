<?php
/**
 * PERSONNALY - Modèle Settings
 * Gestion des paramètres dynamiques avec chiffrement des données sensibles
 */

require_once __DIR__ . '/../core/Database.php';

class Settings
{
    private $db;
    private static $cache = [];

    // Clé de chiffrement (à personnaliser en production)
    private const ENCRYPTION_KEY = 'PERSONNALY_SECURE_KEY_2024_CHANGE_IN_PRODUCTION';
    private const CIPHER_METHOD = 'AES-256-CBC';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère une valeur de paramètre
     */
    public function get(string $key, $default = null)
    {
        // Vérifier le cache
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $stmt = $this->db->prepare(
            'SELECT setting_value, is_encrypted FROM settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            return $default;
        }

        $value = $row['setting_value'];

        // Déchiffrer si nécessaire
        if ($row['is_encrypted'] && !empty($value)) {
            $value = $this->decrypt($value);
        }

        // Mettre en cache
        self::$cache[$key] = $value;

        return $value !== null ? $value : $default;
    }

    /**
     * Définit une valeur de paramètre
     */
    public function set(string $key, $value): bool
    {
        // Vérifier si le paramètre existe et s'il doit être chiffré
        $stmt = $this->db->prepare(
            'SELECT is_encrypted FROM settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        $isEncrypted = $row ? (bool)$row['is_encrypted'] : false;
        $storeValue = $value;

        // Chiffrer si nécessaire
        if ($isEncrypted && !empty($value)) {
            $storeValue = $this->encrypt($value);
        }

        if ($row) {
            // Mise à jour
            $stmt = $this->db->prepare(
                'UPDATE settings SET setting_value = ? WHERE setting_key = ?'
            );
            $result = $stmt->execute([$storeValue, $key]);
        } else {
            // Insertion
            $stmt = $this->db->prepare(
                'INSERT INTO settings (setting_key, setting_value, is_encrypted) VALUES (?, ?, ?)'
            );
            $result = $stmt->execute([$key, $storeValue, $isEncrypted ? 1 : 0]);
        }

        // Mettre à jour le cache
        self::$cache[$key] = $value;

        return $result;
    }

    /**
     * Récupère plusieurs paramètres par catégorie
     */
    public function getByCategory(string $category): array
    {
        $stmt = $this->db->prepare(
            'SELECT setting_key, setting_value, is_encrypted, description
             FROM settings WHERE category = ? ORDER BY setting_key'
        );
        $stmt->execute([$category]);
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'];
            if ($row['is_encrypted'] && !empty($value)) {
                $value = $this->decrypt($value);
            }
            $settings[$row['setting_key']] = [
                'value' => $value,
                'encrypted' => (bool)$row['is_encrypted'],
                'description' => $row['description']
            ];
        }

        return $settings;
    }

    /**
     * Met à jour plusieurs paramètres d'un coup
     */
    public function setMultiple(array $settings): bool
    {
        $this->db->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $this->set($key, $value);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Récupère toutes les catégories
     */
    public function getCategories(): array
    {
        $stmt = $this->db->query(
            'SELECT DISTINCT category FROM settings ORDER BY category'
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Vérifie si Stripe est configuré et activé
     */
    public function isStripeEnabled(): bool
    {
        if ($this->get('stripe_enabled') !== '1') {
            return false;
        }

        $mode = $this->get('stripe_mode', 'test');
        $publicKey = $this->get("stripe_{$mode}_public_key");
        $secretKey = $this->get("stripe_{$mode}_secret_key");

        return !empty($publicKey) && !empty($secretKey);
    }

    /**
     * Récupère les clés Stripe actuelles (selon le mode)
     */
    public function getStripeKeys(): array
    {
        $mode = $this->get('stripe_mode', 'test');
        return [
            'public_key' => $this->get("stripe_{$mode}_public_key"),
            'secret_key' => $this->get("stripe_{$mode}_secret_key"),
            'webhook_secret' => $this->get('stripe_webhook_secret'),
            'mode' => $mode
        ];
    }

    /**
     * Vérifie si Boxtal est configuré et activé
     */
    public function isBoxtalEnabled(): bool
    {
        if ($this->get('boxtal_enabled') !== '1') {
            return false;
        }

        $user = $this->get('boxtal_user');
        $apiKey = $this->get('boxtal_api_key');

        return !empty($user) && !empty($apiKey);
    }

    /**
     * Récupère les credentials Boxtal
     */
    public function getBoxtalCredentials(): array
    {
        return [
            'user' => $this->get('boxtal_user'),
            'api_key' => $this->get('boxtal_api_key'),
            'mode' => $this->get('boxtal_mode', 'test')
        ];
    }

    /**
     * Récupère les infos de l'expéditeur
     */
    public function getShipperInfo(): array
    {
        return [
            'company' => $this->get('shipper_company'),
            'address' => $this->get('shipper_address'),
            'city' => $this->get('shipper_city'),
            'postcode' => $this->get('shipper_postcode'),
            'country' => $this->get('shipper_country', 'FR'),
            'phone' => $this->get('shipper_phone'),
            'email' => $this->get('shipper_email')
        ];
    }

    /**
     * Récupère les tarifs de livraison manuels
     */
    public function getManualShippingRates(): array
    {
        return [
            'free_threshold' => (float)$this->get('shipping_free_threshold', 50),
            'standard_price' => (float)$this->get('shipping_standard_price', 4.90),
            'express_price' => (float)$this->get('shipping_express_price', 9.90)
        ];
    }

    /**
     * Chiffre une valeur
     */
    private function encrypt(string $value): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER_METHOD));
        $encrypted = openssl_encrypt($value, self::CIPHER_METHOD, self::ENCRYPTION_KEY, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Déchiffre une valeur
     */
    private function decrypt(string $value): ?string
    {
        $data = base64_decode($value);
        if ($data === false || strpos($data, '::') === false) {
            // Valeur non chiffrée ou format invalide
            return $value;
        }

        list($iv, $encrypted) = explode('::', $data, 2);
        $decrypted = openssl_decrypt($encrypted, self::CIPHER_METHOD, self::ENCRYPTION_KEY, 0, $iv);

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Vide le cache
     */
    public function clearCache(): void
    {
        self::$cache = [];
    }
}
