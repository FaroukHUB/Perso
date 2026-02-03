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

            // Utiliser INSERT ... ON DUPLICATE KEY UPDATE pour créer ou mettre à jour
            $stmt = $this->db->prepare("
                INSERT INTO shop_settings (setting_key, setting_value, setting_group, setting_type, created_at, updated_at)
                VALUES (?, ?, 'general', 'text', NOW(), NOW())
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
            ");
            $result = $stmt->execute([$key, $value, $value]);

            // Mettre à jour le cache
            if ($result && self::$cacheLoaded) {
                self::$cache[$key] = $value;
            }

            return $result;
        } catch (PDOException $e) {
            error_log("ShopSettings::set error: " . $e->getMessage());
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
        // Default à false pour ne pas activer la livraison gratuite par défaut
        return (bool) $this->get('free_shipping_enabled', false);
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

    /**
     * Récupère toutes les informations légales
     */
    public function getLegalInfo(): array
    {
        return [
            'company_name' => $this->get('legal_company_name', ''),
            'company_type' => $this->get('legal_company_type', 'auto-entrepreneur'),
            'siret' => $this->get('legal_siret', ''),
            'siren' => substr($this->get('legal_siret', ''), 0, 9),
            'tva_number' => $this->get('legal_tva_number', ''),
            'rcs' => $this->get('legal_rcs', ''),
            'capital' => $this->get('legal_capital', ''),
            'address' => $this->get('legal_address', ''),
            'postcode' => $this->get('legal_postcode', ''),
            'city' => $this->get('legal_city', ''),
            'country' => $this->get('legal_country', 'France'),
            'full_address' => trim($this->get('legal_address', '') . ', ' . $this->get('legal_postcode', '') . ' ' . $this->get('legal_city', '') . ', ' . $this->get('legal_country', 'France'), ', '),
            'phone' => $this->get('legal_phone', ''),
            'email' => $this->get('legal_email', ''),
            'director_name' => $this->get('legal_director_name', ''),
            'director_title' => $this->get('legal_director_title', 'Gérant'),
            'host_name' => $this->get('legal_host_name', ''),
            'host_address' => $this->get('legal_host_address', ''),
            'host_phone' => $this->get('legal_host_phone', ''),
            'dpo_name' => $this->get('legal_dpo_name', ''),
            'dpo_email' => $this->get('legal_dpo_email', ''),
            'data_collected' => $this->get('legal_data_collected', ''),
            'data_purpose' => $this->get('legal_data_purpose', ''),
            'data_retention' => $this->get('legal_data_retention', ''),
            'cookies_used' => $this->get('legal_cookies_used', ''),
            'pages_generated' => (bool) $this->get('legal_pages_generated', false),
            'site_name' => $this->getSiteName(),
            'site_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'personnaly.fr'),
            'returns_days' => $this->getReturnDays(),
            'returns_free' => (bool) $this->get('returns_free', true),
            'free_shipping_threshold' => $this->getFreeShippingThreshold()
        ];
    }

    /**
     * Vérifie si les pages légales sont configurées
     */
    public function areLegalPagesConfigured(): bool
    {
        return !empty($this->get('legal_company_name')) && !empty($this->get('legal_siret'));
    }

    /**
     * Récupère tous les paramètres CGV
     */
    public function getCgvSettings(): array
    {
        return [
            // Commandes
            'order_confirmation' => $this->get('cgv_order_confirmation', 'Un email de confirmation vous est envoyé dès validation de votre commande.'),
            'order_modification' => $this->get('cgv_order_modification', 'Toute modification de commande doit être demandée dans les 2 heures suivant la commande, avant mise en production.'),
            'order_cancellation' => $this->get('cgv_order_cancellation', 'L\'annulation est possible uniquement avant la mise en production du produit personnalisé.'),
            'production_time' => $this->get('cgv_production_time', '3 à 5 jours ouvrés'),
            // Livraison
            'delivery_zones' => $this->get('cgv_delivery_zones', 'France métropolitaine, DOM-TOM, Belgique, Suisse, Luxembourg'),
            'delivery_standard_time' => $this->get('cgv_delivery_standard_time', '3 à 5 jours ouvrés'),
            'delivery_express_time' => $this->get('cgv_delivery_express_time', '24 à 48 heures'),
            'delivery_carriers' => $this->get('cgv_delivery_carriers', 'Colissimo, Mondial Relay, Chronopost'),
            'delivery_tracking' => $this->get('cgv_delivery_tracking', 'Un numéro de suivi vous est communiqué par email dès l\'expédition de votre colis.'),
            'delivery_signature' => (bool) $this->get('cgv_delivery_signature', false),
            'delivery_insurance' => (bool) $this->get('cgv_delivery_insurance', true),
            // Paiement
            'payment_methods' => $this->get('cgv_payment_methods', 'Carte bancaire (Visa, Mastercard, CB, American Express) via Stripe'),
            'payment_security' => $this->get('cgv_payment_security', 'Tous les paiements sont sécurisés par Stripe. Vos données bancaires ne transitent jamais par nos serveurs et sont chiffrées en SSL/TLS.'),
            'payment_debit_time' => $this->get('cgv_payment_debit_time', 'Le débit est effectué immédiatement à la validation de la commande.'),
            'payment_installments' => (bool) $this->get('cgv_payment_installments', false),
            'payment_installments_info' => $this->get('cgv_payment_installments_info', ''),
            // Garanties
            'legal_warranty' => $this->get('cgv_legal_warranty', '2 ans'),
            'commercial_warranty' => (bool) $this->get('cgv_commercial_warranty', false),
            'commercial_warranty_duration' => $this->get('cgv_commercial_warranty_duration', ''),
            'commercial_warranty_coverage' => $this->get('cgv_commercial_warranty_coverage', ''),
            // Produits personnalisés
            'custom_products_policy' => $this->get('cgv_custom_products_policy', 'Les produits personnalisés sont fabriqués selon vos spécifications. Nous ne pouvons être tenus responsables des erreurs dues aux informations fournies par le client.'),
            'custom_products_ip' => $this->get('cgv_custom_products_ip', 'Vous garantissez détenir les droits sur les contenus que vous nous transmettez pour personnalisation.'),
            'prohibited_content' => $this->get('cgv_prohibited_content', 'Contenu à caractère pornographique, violent, raciste, discriminatoire, incitant à la haine, ou portant atteinte aux droits de propriété intellectuelle de tiers.'),
            // Litiges
            'mediator_name' => $this->get('cgv_mediator_name', ''),
            'mediator_address' => $this->get('cgv_mediator_address', ''),
            'mediator_website' => $this->get('cgv_mediator_website', ''),
            'applicable_law' => $this->get('cgv_applicable_law', 'droit français'),
            'competent_court' => $this->get('cgv_competent_court', 'les tribunaux du ressort de notre siège social')
        ];
    }

    /**
     * Récupère tous les paramètres politique de retour
     */
    public function getReturnPolicySettings(): array
    {
        return [
            // Conditions générales
            'standard_products' => (bool) $this->get('return_standard_products', true),
            'custom_products' => (bool) $this->get('return_custom_products', false),
            'custom_exception' => $this->get('return_custom_exception', 'Conformément à l\'article L.221-28 du Code de la consommation, les produits personnalisés ne peuvent faire l\'objet d\'un retour.'),
            // Conditions produit
            'product_condition' => $this->get('return_product_condition', 'non porté, non lavé, avec étiquettes d\'origine attachées'),
            'original_packaging' => (bool) $this->get('return_original_packaging', true),
            'complete_product' => $this->get('return_complete_product', 'Le produit doit être retourné complet avec tous ses accessoires.'),
            // Processus
            'request_method' => $this->get('return_request_method', 'email'),
            'request_info' => $this->get('return_request_info', 'Envoyez un email avec votre numéro de commande et les articles à retourner.'),
            'label_provided' => (bool) $this->get('return_label_provided', true),
            'drop_points' => $this->get('return_drop_points', 'Bureau de poste, points relais'),
            'address' => $this->get('return_address', ''),
            // Remboursement
            'refund_delay' => $this->get('return_refund_delay', '14 jours'),
            'refund_method' => $this->get('return_refund_method', 'Le remboursement est effectué sur le même moyen de paiement utilisé lors de la commande.'),
            'shipping_refund' => $this->get('return_shipping_refund', 'Les frais de livraison initiaux sont remboursés uniquement en cas de retour de la totalité de la commande.'),
            'partial_refund' => $this->get('return_partial_refund', 'En cas de produit retourné incomplet ou endommagé, un remboursement partiel pourra être appliqué.'),
            // Échange
            'exchange_available' => (bool) $this->get('return_exchange_available', true),
            'exchange_info' => $this->get('return_exchange_info', 'L\'échange est possible sous réserve de disponibilité.'),
            'size_exchange' => $this->get('return_size_exchange', 'Pour un échange de taille, retournez l\'article et passez une nouvelle commande.'),
            // Défectueux
            'defective_policy' => $this->get('return_defective_policy', 'Si vous recevez un produit défectueux, contactez-nous dans les 48h avec des photos.'),
            'defective_evidence' => $this->get('return_defective_evidence', 'photos du produit et du défaut'),
            'defective_resolution' => $this->get('return_defective_resolution', 'Remplacement du produit ou remboursement intégral à votre choix.'),
            'defective_delay' => $this->get('return_defective_delay', '48 heures'),
            'wrong_item_policy' => $this->get('return_wrong_item_policy', 'Si vous recevez un mauvais article, contactez-nous immédiatement.')
        ];
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
