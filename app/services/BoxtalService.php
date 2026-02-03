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

    // URLs API Boxtal v1 (EnvoiMoinsCher)
    private const API_URL_TEST = 'https://test.envoimoinscher.com/api/v1/';
    private const API_URL_LIVE = 'https://www.envoimoinscher.com/api/v1/';

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
     * Appelle l'API Boxtal v1 (EnvoiMoinsCher) pour obtenir les cotations
     */
    private function callBoxtalAPI(array $recipient, int $weight, float $cartTotal): array
    {
        $shipper = $this->settings->getShipperInfo();

        // Vérifier que les infos expéditeur sont complètes
        if (empty($shipper['company']) || empty($shipper['postcode']) || empty($shipper['city'])) {
            throw new Exception("Informations expéditeur incomplètes");
        }

        // Construction des paramètres pour l'API v1 (format query string)
        $params = [
            // Expéditeur
            'expediteur.type' => 'entreprise',
            'expediteur.pays' => $shipper['country'] ?: 'FR',
            'expediteur.code_postal' => $shipper['postcode'],
            'expediteur.ville' => $shipper['city'],
            'expediteur.adresse' => $shipper['address'],
            'expediteur.civilite' => 'M',
            'expediteur.prenom' => 'Service',
            'expediteur.nom' => 'Expedition',

            // Destinataire
            'destinataire.type' => 'particulier',
            'destinataire.pays' => $recipient['country'] ?? 'FR',
            'destinataire.code_postal' => $recipient['postcode'] ?? '75001',
            'destinataire.ville' => $recipient['city'] ?? 'Paris',
            'destinataire.adresse' => $recipient['address'] ?? '1 rue de Paris',
            'destinataire.civilite' => 'M',
            'destinataire.prenom' => $recipient['firstname'] ?? 'Client',
            'destinataire.nom' => $recipient['lastname'] ?? 'Test',

            // Colis
            'colis_1.poids' => max(0.1, round($weight / 1000, 2)),
            'colis_1.longueur' => 30,
            'colis_1.largeur' => 20,
            'colis_1.hauteur' => 10,

            // Options
            'code_contenu' => 10120,
            'collecte' => date('Y-m-d', strtotime('+1 day')),
            'delai' => 'aucun',
            'operateur' => '', // Tous les transporteurs
        ];

        $url = $this->apiUrl . 'cotation?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->accessKey . ':' . $this->secretKey),
                'Accept: application/xml'
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
            throw new Exception("Boxtal API: HTTP $httpCode - " . substr($response, 0, 200));
        }

        // L'API v1 retourne du XML
        if (empty($response)) {
            throw new Exception("Réponse vide de l'API");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            throw new Exception("Réponse XML invalide");
        }

        return $this->formatBoxtalRatesV1($xml, $cartTotal);
    }

    /**
     * Formate les tarifs Boxtal API v1 pour l'affichage (XML)
     */
    private function formatBoxtalRatesV1(SimpleXMLElement $xml, float $cartTotal): array
    {
        $rates = [];
        $config = $this->settings->getManualShippingRates();

        // L'API v1 retourne <shipment> pour chaque offre
        foreach ($xml->shipment as $shipment) {
            $offer = $shipment->offer;
            $operator = $offer->operator;
            $service = $offer->service;

            $operatorCode = (string)$operator['code'];
            $serviceCode = (string)$service['code'];
            $operatorLabel = (string)$operator->label;
            $serviceLabel = (string)$service->label;

            // Prix TTC
            $price = (float)$offer->price['tax-inclusive'];

            // Appliquer la livraison gratuite si au-dessus du seuil
            if ($cartTotal >= $config['free_threshold']) {
                $price = 0;
            }

            // Délai de livraison
            $deliveryDate = (string)$shipment->delivery->date;
            $delay = $deliveryDate ? date('d/m', strtotime($deliveryDate)) : '';

            // Point relais?
            $isRelay = in_array($operatorCode, ['MONR', 'SOGP', 'UPSE', 'POFR_RELAIS'])
                    || stripos($serviceLabel, 'relais') !== false
                    || stripos($serviceLabel, 'relay') !== false
                    || stripos($serviceLabel, 'point') !== false;

            $rates[] = [
                'id' => $operatorCode . '_' . $serviceCode,
                'operator' => $operatorCode,
                'service' => $serviceCode,
                'label' => $this->formatCarrierName($operatorCode, $operatorLabel . ' - ' . $serviceLabel),
                'description' => $serviceLabel,
                'price' => $price,
                'delay' => $delay,
                'is_relay' => $isRelay,
                'logo' => $this->getOperatorLogo($operatorCode)
            ];
        }

        // Trier par prix
        usort($rates, fn($a, $b) => $a['price'] <=> $b['price']);

        // Limiter à 5 options
        return array_slice($rates, 0, 5);
    }

    /**
     * Formate les tarifs Boxtal pour l'affichage (API v3)
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
