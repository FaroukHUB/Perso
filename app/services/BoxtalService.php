<?php
/**
 * PERSONNALY - Service Boxtal
 * Calcul des frais de livraison via l'API Boxtal v3
 */

require_once __DIR__ . '/../models/Settings.php';

class BoxtalService
{
    private $settings;
    private $apiUrl;
    private $accessKey;
    private $secretKey;
    private $isEnabled;

    // URLs API Boxtal v3
    private const API_URL_TEST = 'https://api.boxtal.build/v3/';
    private const API_URL_LIVE = 'https://api.boxtal.com/v3/';

    public function __construct()
    {
        $this->settings = new Settings();
        $this->isEnabled = $this->settings->isBoxtalEnabled();

        if ($this->isEnabled) {
            $credentials = $this->settings->getBoxtalCredentials();
            $this->accessKey = $credentials['user']; // Clé d'accès
            $this->secretKey = $credentials['api_key']; // Clé secrète
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
     */
    public function getShippingRates(array $recipient, int $weight, float $cartTotal): array
    {
        if (!$this->isEnabled) {
            return $this->getManualRates($cartTotal);
        }

        try {
            $rates = $this->callBoxtalAPI($recipient, $weight, $cartTotal);
            if (empty($rates)) {
                return $this->getManualRates($cartTotal);
            }
            return $rates;
        } catch (Exception $e) {
            error_log('Boxtal API Error: ' . $e->getMessage());
            return $this->getManualRates($cartTotal);
        }
    }

    /**
     * Appelle l'API Boxtal v3 pour obtenir les cotations
     */
    private function callBoxtalAPI(array $recipient, int $weight, float $cartTotal): array
    {
        $shipper = $this->settings->getShipperInfo();

        // Vérifier que les infos expéditeur sont complètes
        if (empty($shipper['company']) || empty($shipper['postcode']) || empty($shipper['city'])) {
            throw new Exception("Informations expéditeur incomplètes");
        }

        // Construction du payload pour l'API v3
        $payload = [
            'shipper' => [
                'company' => $shipper['company'],
                'street' => $shipper['address'],
                'city' => $shipper['city'],
                'zipCode' => $shipper['postcode'],
                'country' => $shipper['country'] ?: 'FR',
                'phone' => $shipper['phone'] ?: '',
                'email' => $shipper['email'] ?: ''
            ],
            'recipient' => [
                'company' => $recipient['company'] ?? '',
                'firstName' => $recipient['firstname'] ?? 'Client',
                'lastName' => $recipient['lastname'] ?? 'Test',
                'street' => $recipient['address'] ?? '1 rue de Paris',
                'city' => $recipient['city'] ?? 'Paris',
                'zipCode' => $recipient['postcode'] ?? '75001',
                'country' => $recipient['country'] ?? 'FR',
                'phone' => $recipient['phone'] ?? '',
                'email' => $recipient['email'] ?? ''
            ],
            'parcels' => [
                [
                    'weight' => round($weight / 1000, 2), // Convertir g en kg
                    'length' => 30,
                    'width' => 20,
                    'height' => 10
                ]
            ],
            'orderValue' => $cartTotal
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl . 'shipping/quote',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->accessKey . ':' . $this->secretKey),
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("cURL Error: " . $curlError);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['message'] ?? $errorData['error'] ?? "HTTP $httpCode";
            throw new Exception("Boxtal API: " . $errorMsg);
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Réponse JSON invalide");
        }

        // L'API v3 retourne directement les offres
        $offers = $data['offers'] ?? $data['quotes'] ?? $data;
        if (!is_array($offers)) {
            throw new Exception("Format de réponse inattendu");
        }

        return $this->formatBoxtalRates($offers, $cartTotal);
    }

    /**
     * Formate les tarifs Boxtal pour l'affichage
     */
    private function formatBoxtalRates(array $offers, float $cartTotal): array
    {
        $rates = [];
        $config = $this->settings->getManualShippingRates();

        foreach ($offers as $offer) {
            $carrier = $offer['carrier'] ?? $offer['operator'] ?? [];
            $carrierCode = $carrier['code'] ?? $offer['carrierCode'] ?? '';
            $serviceName = $offer['serviceName'] ?? $offer['service'] ?? $carrier['name'] ?? 'Livraison';

            $price = $offer['price'] ?? $offer['totalPrice'] ?? 0;
            if (is_array($price)) {
                $price = $price['taxInclusive'] ?? $price['amount'] ?? 0;
            }

            // Appliquer la livraison gratuite si au-dessus du seuil
            if ($cartTotal >= $config['free_threshold']) {
                $originalPrice = $price;
                $price = 0;
            }

            $delay = $offer['deliveryTime'] ?? $offer['delay'] ?? '';
            if (is_array($delay)) {
                $delay = ($delay['min'] ?? '') . '-' . ($delay['max'] ?? '') . ' jours';
            }

            $isRelay = $offer['isRelay'] ?? $offer['pickupPoint'] ?? false;
            if (is_array($isRelay)) {
                $isRelay = true;
            }

            $rates[] = [
                'id' => $carrierCode . '_' . ($offer['serviceCode'] ?? uniqid()),
                'operator' => $carrierCode,
                'service' => $offer['serviceCode'] ?? '',
                'label' => $this->formatCarrierName($carrierCode, $serviceName),
                'description' => $delay,
                'price' => (float)$price,
                'delay' => $delay,
                'is_relay' => (bool)$isRelay,
                'logo' => $this->getOperatorLogo($carrierCode)
            ];
        }

        // Trier par prix
        usort($rates, fn($a, $b) => $a['price'] <=> $b['price']);

        // Limiter à 5 options
        return array_slice($rates, 0, 5);
    }

    /**
     * Formate le nom du transporteur
     */
    private function formatCarrierName(string $code, string $serviceName): string
    {
        $carriers = [
            'COLISSIMO' => 'Colissimo',
            'POFR' => 'Colissimo',
            'CHRONOPOST' => 'Chronopost',
            'CHRP' => 'Chronopost',
            'MONDIAL_RELAY' => 'Mondial Relay',
            'MONR' => 'Mondial Relay',
            'DPD' => 'DPD',
            'DPFR' => 'DPD',
            'UPS' => 'UPS',
            'UPSE' => 'UPS',
            'GLS' => 'GLS',
            'TNT' => 'TNT',
            'FEDEX' => 'FedEx',
            'RELAIS_COLIS' => 'Relais Colis',
            'SOGP' => 'Relais Colis'
        ];

        $carrierName = $carriers[strtoupper($code)] ?? $serviceName;

        // Ajouter indication point relais si nécessaire
        if (stripos($serviceName, 'relay') !== false || stripos($serviceName, 'relais') !== false || stripos($serviceName, 'point') !== false) {
            return $carrierName . ' Point Relais';
        }

        return $carrierName;
    }

    /**
     * Retourne le logo d'un transporteur
     */
    private function getOperatorLogo(string $operator): string
    {
        $operator = strtoupper($operator);
        $logos = [
            'POFR' => '/public/assets/images/carriers/colissimo.png',
            'COLISSIMO' => '/public/assets/images/carriers/colissimo.png',
            'CHRP' => '/public/assets/images/carriers/chronopost.png',
            'CHRONOPOST' => '/public/assets/images/carriers/chronopost.png',
            'MONR' => '/public/assets/images/carriers/mondialrelay.png',
            'MONDIAL_RELAY' => '/public/assets/images/carriers/mondialrelay.png',
            'SOGP' => '/public/assets/images/carriers/relaiscolis.png',
            'RELAIS_COLIS' => '/public/assets/images/carriers/relaiscolis.png',
            'UPSE' => '/public/assets/images/carriers/ups.png',
            'UPS' => '/public/assets/images/carriers/ups.png',
            'DPFR' => '/public/assets/images/carriers/dpd.png',
            'DPD' => '/public/assets/images/carriers/dpd.png',
            'GLS' => '/public/assets/images/carriers/gls.png',
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

        return max($totalWeight, 100); // Minimum 100g
    }
}
