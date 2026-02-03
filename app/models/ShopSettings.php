<?php
/**
 * PERSONNALY - Shop Settings Model
 * Gestion centralisée de tous les paramètres du site
 */

require_once __DIR__ . '/../core/Database.php';

class ShopSettings
{
    private $db;
    private static $cache = [];
    private static $cacheLoaded = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Charge tous les paramètres en cache
     */
    private function loadCache(): void
    {
        if (self::$cacheLoaded) {
            return;
        }

        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value, setting_type FROM shop_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                self::$cache[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type']);
            }
            self::$cacheLoaded = true;
        } catch (PDOException $e) {
            // Table might not exist yet
            self::$cacheLoaded = true;
        }
    }

    /**
     * Convertit la valeur selon son type
     */
    private function castValue($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return $value === '1' || $value === 'true' || $value === true;
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
            case 'json':
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            default:
                return $value;
        }
    }

    /**
     * Récupère une valeur de paramètre
     */
    public function get(string $key, $default = null)
    {
        $this->loadCache();
        return self::$cache[$key] ?? $default;
    }

    /**
     * Récupère plusieurs valeurs de paramètres
     */
    public function getMultiple(array $keys): array
    {
        $this->loadCache();
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::$cache[$key] ?? null;
        }
        return $result;
    }

    /**
     * Récupère tous les paramètres d'un groupe
     */
    public function getGroup(string $group): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT setting_key, setting_value, setting_type, setting_label, setting_description, sort_order
                FROM shop_settings
                WHERE setting_group = ?
                ORDER BY sort_order ASC
            ");
            $stmt->execute([$group]);

            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = [
                    'value' => $this->castValue($row['setting_value'], $row['setting_type']),
                    'raw_value' => $row['setting_value'],
                    'type' => $row['setting_type'],
                    'label' => $row['setting_label'],
                    'description' => $row['setting_description']
                ];
            }
            return $settings;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Récupère tous les groupes disponibles
     */
    public function getGroups(): array
    {
        try {
            $stmt = $this->db->query("SELECT DISTINCT setting_group FROM shop_settings ORDER BY setting_group");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Met à jour une valeur de paramètre
     */
    public function set(string $key, $value): bool
    {
        try {
            // Convertir les valeurs JSON
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            // Convertir les booléens
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $stmt = $this->db->prepare("
                UPDATE shop_settings
                SET setting_value = ?, updated_at = NOW()
                WHERE setting_key = ?
            ");
            $result = $stmt->execute([$value, $key]);

            // Mettre à jour le cache
            if ($result && self::$cacheLoaded) {
                self::$cache[$key] = $value;
            }

            return $result;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Met à jour plusieurs paramètres
     */
    public function setMultiple(array $settings): bool
    {
        try {
            $this->db->beginTransaction();

            foreach ($settings as $key => $value) {
                if (!$this->set($key, $value)) {
                    $this->db->rollBack();
                    return false;
                }
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Invalide le cache (à appeler après modification)
     */
    public function clearCache(): void
    {
        self::$cache = [];
        self::$cacheLoaded = false;
    }

    // =========================================
    // Méthodes utilitaires pour accès rapide
    // =========================================

    /**
     * Récupère le nom du site
     */
    public function getSiteName(): string
    {
        return $this->get('site_name', 'PERSONNALY');
    }

    /**
     * Récupère l'email de contact
     */
    public function getContactEmail(): string
    {
        return $this->get('contact_email', 'contact@personnaly.fr');
    }

    /**
     * Récupère le seuil de livraison gratuite
     */
    public function getFreeShippingThreshold(): float
    {
        return (float) $this->get('free_shipping_threshold', 50);
    }

    /**
     * Vérifie si la livraison gratuite est activée
     */
    public function isFreeShippingEnabled(): bool
    {
        return (bool) $this->get('free_shipping_enabled', true);
    }

    /**
     * Récupère le délai de retour en jours
     */
    public function getReturnDays(): int
    {
        return (int) $this->get('returns_days', 14);
    }

    /**
     * Récupère les moyens de paiement activés
     */
    public function getEnabledPaymentMethods(): array
    {
        $methods = [];
        $available = ['visa', 'mastercard', 'amex', 'cb', 'paypal', 'apple_pay', 'google_pay'];

        foreach ($available as $method) {
            if ($this->get("payment_{$method}_enabled", false)) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * Récupère les badges de confiance activés
     */
    public function getTrustBadges(): array
    {
        $badges = [];

        for ($i = 1; $i <= 4; $i++) {
            if ($this->get("trust_badge_{$i}_enabled", false)) {
                $badges[] = [
                    'icon' => $this->get("trust_badge_{$i}_icon", 'check'),
                    'text' => $this->get("trust_badge_{$i}_text", '')
                ];
            }
        }

        return $badges;
    }

    /**
     * Récupère les options de livraison fallback
     */
    public function getShippingFallback(): array
    {
        return [
            'standard' => [
                'label' => $this->get('shipping_fallback_standard_label', 'Livraison standard'),
                'price' => (float) $this->get('shipping_fallback_standard_price', 4.90),
                'delay' => $this->get('shipping_fallback_standard_delay', '3-5 jours ouvrés')
            ],
            'express' => [
                'label' => $this->get('shipping_fallback_express_label', 'Livraison express'),
                'price' => (float) $this->get('shipping_fallback_express_price', 9.90),
                'delay' => $this->get('shipping_fallback_express_delay', '24-48h')
            ]
        ];
    }

    /**
     * Récupère l'adresse par défaut pour estimation
     */
    public function getDefaultAddress(): array
    {
        return [
            'address' => '',
            'city' => $this->get('shipping_default_city', 'Paris'),
            'postcode' => $this->get('shipping_default_postcode', '75001'),
            'country' => $this->get('shipping_default_country', 'FR')
        ];
    }

    /**
     * Récupère les textes du panier
     */
    public function getCartTexts(): array
    {
        return [
            'title' => $this->get('cart_title', 'Votre Panier'),
            'empty_title' => $this->get('cart_empty_title', 'Votre panier est vide'),
            'empty_description' => $this->get('cart_empty_description', 'Découvrez nos produits personnalisables et créez quelque chose d\'unique !'),
            'empty_button' => $this->get('cart_empty_button', 'Découvrir nos produits'),
            'items_title' => $this->get('cart_items_title', 'Vos articles'),
            'clear_button' => $this->get('cart_clear_button', 'Vider'),
            'summary_title' => $this->get('cart_summary_title', 'Récapitulatif'),
            'promo_label' => $this->get('cart_promo_label', 'Code promo'),
            'promo_placeholder' => $this->get('cart_promo_placeholder', 'Entrez votre code'),
            'promo_button' => $this->get('cart_promo_button', 'Appliquer'),
            'shipping_label' => $this->get('cart_shipping_label', 'Livraison'),
            'subtotal_label' => $this->get('cart_subtotal_label', 'Sous-total'),
            'discount_label' => $this->get('cart_discount_label', 'Réduction'),
            'total_label' => $this->get('cart_total_label', 'Total'),
            'checkout_button' => $this->get('cart_checkout_button', 'Passer commande'),
            'continue_link' => $this->get('cart_continue_link', '← Continuer mes achats')
        ];
    }

    /**
     * Récupère les messages système
     */
    public function getMessages(): array
    {
        return [
            'quantity_updated' => $this->get('msg_quantity_updated', 'Quantité mise à jour.'),
            'item_removed' => $this->get('msg_item_removed', 'Article supprimé du panier.'),
            'cart_cleared' => $this->get('msg_cart_cleared', 'Panier vidé.'),
            'session_expired' => $this->get('msg_session_expired', 'Session expirée. Veuillez réessayer.'),
            'promo_applied' => $this->get('msg_promo_applied', 'Code promo appliqué !'),
            'free_shipping_applied' => $this->get('msg_free_shipping_applied', 'Livraison gratuite appliquée !'),
            'connection_error' => $this->get('msg_connection_error', 'Erreur de connexion. Réessayez.'),
            'enter_promo' => $this->get('msg_enter_promo', 'Veuillez entrer un code promo'),
            'discount_applied' => $this->get('msg_discount_applied', 'de réduction !')
        ];
    }

    /**
     * Récupère les éléments du footer
     */
    public function getFooterContent(): array
    {
        return [
            'site_name' => $this->getSiteName(),
            'description' => $this->get('site_description', ''),
            'contact_email' => $this->getContactEmail(),
            'copyright' => $this->get('copyright_text', '© ' . date('Y') . ' PERSONNALY'),
            'reassurance_1' => $this->get('footer_reassurance_1', ''),
            'reassurance_2' => $this->get('footer_reassurance_2', ''),
            'reassurance_3' => $this->get('footer_reassurance_3', ''),
            'col1_title' => $this->get('footer_col1_title', 'Navigation'),
            'col2_title' => $this->get('footer_col2_title', 'Informations'),
            'col3_title' => $this->get('footer_col3_title', 'Contact'),
            'links' => $this->get('footer_links', [])
        ];
    }

    /**
     * Récupère les liens de navigation
     */
    public function getNavbarLinks(): array
    {
        return $this->get('navbar_links', [
            ['label' => 'Accueil', 'url' => '/'],
            ['label' => 'Nos Produits', 'url' => '/#produits']
        ]);
    }
}

// =========================================
// Fonction helper globale pour accès rapide
// =========================================

/**
 * Récupère un paramètre de configuration
 * @param string $key Clé du paramètre
 * @param mixed $default Valeur par défaut
 * @return mixed
 */
function setting(string $key, $default = null)
{
    static $settings = null;
    if ($settings === null) {
        $settings = new ShopSettings();
    }
    return $settings->get($key, $default);
}

/**
 * Récupère l'instance du modèle ShopSettings
 * @return ShopSettings
 */
function shopSettings(): ShopSettings
{
    static $instance = null;
    if ($instance === null) {
        $instance = new ShopSettings();
    }
    return $instance;
}
