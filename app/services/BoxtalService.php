<?php
/**
 * PERSONNALY - Service Boxtal (EnvoiMoinsCher)
 * Calcul des frais de livraison via l'API Boxtal
 */

require_once __DIR__ . '/../models/Settings.php';

class BoxtalService
{
    private $settings;
    private $apiUrl;
    private $user;
    private $apiKey;
    private $isEnabled;

    // URLs API Boxtal
    private const API_URL_TEST = 'https://test.envoimoinscher.com/api/v1/';
    private const API_URL_LIVE = 'https://www.envoimoinscher.com/api/v1/';

    public function __construct()
    {
        $this->settings = new Settings();
        $this->isEnabled = $this->settings->isBoxtalEnabled();

        if ($this->isEnabled) {
            $credentials = $this->settings->getBoxtalCredentials();
            $this->user = $credentials['user'];
            $this->apiKey = $credentials['api_key'];
            $this->apiUrl = $credentials['mode'] === 'live' ? self::API_URL_LIVE : self::API_URL_TEST;
        }
    }

    /**
     * Vérifie si Boxtal est activé
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * Récupère les tarifs de livraison disponibles
     *
     * @param array $recipient Adresse du destinataire
     * @param int $weight Poids en grammes
     * @param float $cartTotal Montant total du panier
     * @return array Liste des options de livraison
     */
    public function getShippingRates(array $recipient, int $weight, float $cartTotal): array
    {
        if (!$this->isEnabled) {
            return $this->getManualRates($cartTotal);
        }

        try {
            $rates = $this->callBoxtalAPI($recipient, $weight);
            return $rates;
        } catch (Exception $e) {
            // En cas d'erreur, retourner les tarifs manuels
            error_log('Boxtal API Error: ' . $e->getMessage());
            return $this->getManualRates($cartTotal);
        }
    }

    /**
     * Appelle l'API Boxtal pour obtenir les cotations
     */
    private function callBoxtalAPI(array $recipient, int $weight): array
    {
        $shipper = $this->settings->getShipperInfo();

        // Construction de la requête
        $params = [
            // Expéditeur
            'shipper.company' => $shipper['company'],
            'shipper.address' => $shipper['address'],
            'shipper.city' => $shipper['city'],
            'shipper.zipcode' => $shipper['postcode'],
            'shipper.country' => $shipper['country'],
            'shipper.phone' => $shipper['phone'],
            'shipper.email' => $shipper['email'],
            'shipper.type' => 'company',

            // Destinataire
            'recipient.company' => $recipient['company'] ?? '',
            'recipient.firstname' => $recipient['firstname'] ?? '',
            'recipient.lastname' => $recipient['lastname'] ?? '',
            'recipient.address' => $recipient['address'],
            'recipient.city' => $recipient['city'],
            'recipient.zipcode' => $recipient['postcode'],
            'recipient.country' => $recipient['country'] ?? 'FR',
            'recipient.phone' => $recipient['phone'] ?? '',
            'recipient.email' => $recipient['email'] ?? '',
            'recipient.type' => 'individual',

            // Colis
            'parcels.1.weight' => $weight / 1000, // Convertir en kg
            'parcels.1.length' => 30, // Dimensions par défaut
            'parcels.1.width' => 20,
            'parcels.1.height' => 10,

            // Options
            'content_code' => 10100, // Vêtements
            'collection_date' => date('Y-m-d', strtotime('+1 day')),
            'colis_valeur' => 0 // Pas de valeur déclarée par défaut
        ];

        $url = $this->apiUrl . 'cotation';
        $queryString = http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . $queryString,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->user . ':' . $this->apiKey),
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Boxtal API returned HTTP $httpCode");
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['cotation'])) {
            throw new Exception("Invalid Boxtal API response");
        }

        return $this->formatBoxtalRates($data['cotation']);
    }

    /**
     * Formate les tarifs Boxtal pour l'affichage
     */
    private function formatBoxtalRates(array $cotations): array
    {
        $rates = [];

        foreach ($cotations as $cotation) {
            // Filtrer les transporteurs souhaités
            $operator = $cotation['operator']['code'] ?? '';
            $service = $cotation['service']['code'] ?? '';

            // Mapper les services vers des noms compréhensibles
            $serviceLabel = $this->getServiceLabel($operator, $service);
            if (!$serviceLabel) continue;

            $rates[] = [
                'id' => $operator . '_' . $service,
                'operator' => $operator,
                'service' => $service,
                'label' => $serviceLabel['label'],
                'description' => $serviceLabel['description'],
                'price' => (float)$cotation['price']['tax-inclusive'],
                'delay' => $cotation['delivery']['date'] ?? '',
                'is_relay' => in_array($operator, ['MONR', 'SOGP', 'UPSE']),
                'logo' => $this->getOperatorLogo($operator)
            ];
        }

        // Trier par prix
        usort($rates, fn($a, $b) => $a['price'] <=> $b['price']);

        return $rates;
    }

    /**
     * Retourne le libellé d'un service
     */
    private function getServiceLabel(string $operator, string $service): ?array
    {
        $services = [
            'POFR' => [
                'ColissimoAccess' => ['label' => 'Colissimo à domicile', 'description' => 'Livraison en 2-3 jours ouvrés'],
                'ColissimoExpert' => ['label' => 'Colissimo Expert', 'description' => 'Livraison avec signature'],
            ],
            'CHRP' => [
                'Chrono13' => ['label' => 'Chronopost 13h', 'description' => 'Livraison le lendemain avant 13h'],
                'ChronoRelais' => ['label' => 'Chronopost Relais', 'description' => 'Livraison en point relais'],
            ],
            'MONR' => [
                'CpourToi' => ['label' => 'Mondial Relay', 'description' => 'Livraison en point relais'],
            ],
            'SOGP' => [
                'RelaisColis' => ['label' => 'Relais Colis', 'description' => 'Livraison en point relais'],
            ],
            'UPSE' => [
                'AccessPoint' => ['label' => 'UPS Access Point', 'description' => 'Livraison en point UPS'],
            ],
            'DPFR' => [
                'DPDPredict' => ['label' => 'DPD Predict', 'description' => 'Livraison avec créneau horaire'],
            ],
        ];

        return $services[$operator][$service] ?? null;
    }

    /**
     * Retourne le logo d'un transporteur
     */
    private function getOperatorLogo(string $operator): string
    {
        $logos = [
            'POFR' => '/public/assets/images/carriers/colissimo.png',
            'CHRP' => '/public/assets/images/carriers/chronopost.png',
            'MONR' => '/public/assets/images/carriers/mondialrelay.png',
            'SOGP' => '/public/assets/images/carriers/relaiscolis.png',
            'UPSE' => '/public/assets/images/carriers/ups.png',
            'DPFR' => '/public/assets/images/carriers/dpd.png',
        ];

        return $logos[$operator] ?? '';
    }

    /**
     * Retourne les tarifs manuels (fallback)
     */
    public function getManualRates(float $cartTotal): array
    {
        $config = $this->settings->getManualShippingRates();

        $rates = [];

        // Livraison standard
        $standardPrice = $cartTotal >= $config['free_threshold'] ? 0 : $config['standard_price'];
        $rates[] = [
            'id' => 'standard',
            'operator' => 'MANUAL',
            'service' => 'standard',
            'label' => 'Livraison standard',
            'description' => $standardPrice == 0 ? 'Gratuite' : 'Livraison en 3-5 jours ouvrés',
            'price' => $standardPrice,
            'delay' => '3-5 jours ouvrés',
            'is_relay' => false,
            'logo' => ''
        ];

        // Livraison express
        $rates[] = [
            'id' => 'express',
            'operator' => 'MANUAL',
            'service' => 'express',
            'label' => 'Livraison express',
            'description' => 'Livraison en 24-48h',
            'price' => $config['express_price'],
            'delay' => '24-48h',
            'is_relay' => false,
            'logo' => ''
        ];

        return $rates;
    }

    /**
     * Calcule le poids total du panier
     */
    public function calculateCartWeight(array $cartItems): int
    {
        $defaultWeight = (int)$this->settings->get('boxtal_default_weight', 500);
        $totalWeight = 0;

        foreach ($cartItems as $item) {
            $itemWeight = $item['product']['weight'] ?? $defaultWeight;
            $totalWeight += $itemWeight * $item['quantity'];
        }

        return $totalWeight;
    }
}
