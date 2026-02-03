<?php
/**
 * PERSONNALY - Service Boxtal
 * Calcul des frais de livraison via l'API Boxtal v1 (EnvoiMoinsCher)
 */

require_once __DIR__ . '/../models/Settings.php';
require_once __DIR__ . '/../models/ShopSettings.php';

class BoxtalService
{
    private $settings;
    private $shopSettings;
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
        $this->shopSettings = new ShopSettings();
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
     * Vérifie si la livraison gratuite s'applique
     */
    private function isFreeShipping(float $cartTotal): bool
    {
        // Vérifier si la livraison gratuite est activée dans les paramètres
        if (!$this->shopSettings->isFreeShippingEnabled()) {
            return false;
        }

        // Vérifier si le total du panier atteint le seuil
        $threshold = $this->shopSettings->getFreeShippingThreshold();
        return $cartTotal >= $threshold;
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
            'colis_1.valeur' => max(1, round($cartTotal)), // Valeur déclarée en euros

            // Options
            'code_contenu' => 10120,
            'type_envoi' => 'colis',
            'collecte' => date('Y-m-d', strtotime('+1 day')),
            'delai' => 'aucun',
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

        // L'API v1 retourne UN <shipment> avec PLUSIEURS <offer> dedans
        foreach ($xml->shipment->offer as $offer) {
            $operator = $offer->operator;
            $service = $offer->service;

            $operatorCode = (string)$operator['code'];
            $serviceCode = (string)$service['code'];
            $operatorLabel = (string)$operator->label;
            $serviceLabel = (string)$service->label;

            // Prix TTC (élément enfant, pas attribut)
            $price = (float)$offer->price->{'tax-inclusive'};

            // Appliquer la livraison gratuite si activée et au-dessus du seuil
            if ($this->isFreeShipping($cartTotal)) {
                $price = 0;
            }

            // Délai de livraison
            $deliveryDate = (string)$offer->delivery->date;
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
        $isFree = $this->isFreeShipping($cartTotal);

        $rates = [];

        // Livraison standard
        $standardPrice = $isFree ? 0 : $config['standard_price'];
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

    /**
     * Récupère les points relais disponibles pour un transporteur et un code postal
     */
    public function getRelayPoints(string $carrierCode, string $postcode, string $country = 'FR'): array
    {
        if (!$this->isEnabled) {
            return [];
        }

        // Mapper les codes opérateurs vers les codes de service point relais
        $carrierMapping = [
            'MONR' => 'MONR',           // Mondial Relay
            'SOGP' => 'SOGP',           // Relais Colis (So Colissimo)
            'UPSE' => 'UPSE',           // UPS Access Point
            'CHRP' => 'CHRP',           // Chronopost Relais
            'POFR' => 'POFR',           // Colissimo Point Retrait
        ];

        // Extraire le code opérateur de l'ID (ex: MONR_RELAIS -> MONR)
        $operatorCode = explode('_', $carrierCode)[0];

        if (!isset($carrierMapping[$operatorCode])) {
            return [];
        }

        try {
            // L'API EnvoiMoinsCher v1 utilise l'endpoint 'parcelshop' pour les points relais
            // Essayer d'abord parcelshop, puis listpoints en fallback
            $endpoints = ['parcelshop', 'listpoints'];

            foreach ($endpoints as $endpoint) {
                $params = [
                    'pays' => $country,
                    'cp' => $postcode,
                    'collecte' => 'retrait',
                    'ope_code' => $operatorCode,
                ];

                $url = $this->apiUrl . $endpoint . '?' . http_build_query($params);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Basic ' . base64_encode($this->accessKey . ':' . $this->secretKey),
                        'Accept: application/xml'
                    ],
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_SSL_VERIFYPEER => true
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                // Log pour debug
                error_log("Boxtal Relay API: endpoint=$endpoint, code=$httpCode, url=$url");

                if ($httpCode === 200 && !empty($response)) {
                    libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($response);

                    if ($xml !== false) {
                        $points = $this->formatRelayPoints($xml, $operatorCode);
                        if (!empty($points)) {
                            return $points;
                        }
                    }
                }
            }

            // Aucun endpoint n'a fonctionné
            error_log("Boxtal Relay API: no points found for $operatorCode in $postcode");
            return [];

        } catch (Exception $e) {
            error_log('Boxtal Relay Points Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Formate les points relais depuis la réponse XML
     * L'API EnvoiMoinsCher utilise des noms de champs en français
     * Structure: <carriers><carrier><operator>CODE</operator><points><point>...</point></points></carrier></carriers>
     */
    private function formatRelayPoints(SimpleXMLElement $xml, string $operatorCode = ''): array
    {
        $points = [];
        $pointsList = [];

        // Debug: log XML structure
        $rootName = $xml->getName();
        error_log("formatRelayPoints: rootName=$rootName, operatorCode=$operatorCode");

        // Structure API EnvoiMoinsCher: carriers > carrier > points > point
        // Note: si le root est "carriers", on accède directement à carrier
        if (isset($xml->carrier)) {
            error_log("formatRelayPoints: found carrier elements");
            foreach ($xml->carrier as $carrier) {
                $carrierOpe = (string)($carrier->operator ?? '');
                error_log("formatRelayPoints: carrier operator=$carrierOpe");
                // Si un code opérateur est spécifié, filtrer
                if (!empty($operatorCode) && $carrierOpe !== $operatorCode) {
                    continue;
                }
                // Récupérer les points de ce carrier
                if (isset($carrier->points->point)) {
                    error_log("formatRelayPoints: found points in carrier");
                    foreach ($carrier->points->point as $point) {
                        $pointsList[] = $point;
                    }
                }
            }
        }
        // Fallback: structure directe points > point
        elseif (isset($xml->points->point)) {
            error_log("formatRelayPoints: using fallback xml->points->point");
            foreach ($xml->points->point as $point) {
                $pointsList[] = $point;
            }
        }
        // Fallback: structure directe point
        elseif (isset($xml->point)) {
            error_log("formatRelayPoints: using fallback xml->point");
            foreach ($xml->point as $point) {
                $pointsList[] = $point;
            }
        } else {
            error_log("formatRelayPoints: no matching structure found");
        }

        error_log("formatRelayPoints: pointsList count=" . count($pointsList));

        foreach ($pointsList as $point) {
            // Essayer les noms français puis anglais
            $code = (string)($point->code ?? $point->point_code ?? '');
            $name = (string)($point->name ?? $point->nom ?? $point->raison_sociale ?? '');
            $address = (string)($point->address ?? $point->adresse ?? '');
            $city = (string)($point->city ?? $point->ville ?? '');
            $postcode = (string)($point->zipcode ?? $point->code_postal ?? $point->cp ?? '');
            $country = (string)($point->country ?? $point->pays ?? 'FR');

            // Coordonnées GPS
            $lat = (float)($point->latitude ?? $point->lat ?? 0);
            $lng = (float)($point->longitude ?? $point->lng ?? $point->lon ?? 0);

            // Distance si disponible (en km)
            $distance = null;
            if (isset($point->distance)) {
                $distance = (float)$point->distance;
            }

            // Ne pas ajouter si pas de données essentielles
            if (empty($code) && empty($name)) {
                continue;
            }

            $points[] = [
                'code' => $code,
                'name' => $name ?: 'Point Relais',
                'address' => $address,
                'city' => $city,
                'postcode' => $postcode,
                'country' => $country,
                'latitude' => $lat,
                'longitude' => $lng,
                'distance' => $distance,
                'formatted_address' => trim("$address, $postcode $city")
            ];
        }

        // Limiter à 10 points relais
        return array_slice($points, 0, 10);
    }

    /**
     * Vérifie si un transporteur supporte les points relais
     */
    public function isRelayCarrier(string $carrierCode): bool
    {
        $relayCarriers = ['MONR', 'SOGP', 'UPSE', 'CHRP', 'POFR_RELAIS'];
        $operatorCode = explode('_', $carrierCode)[0];
        return in_array($operatorCode, $relayCarriers);
    }
}
