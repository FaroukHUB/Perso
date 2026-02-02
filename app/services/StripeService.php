<?php
/**
 * PERSONNALY - Service Stripe
 * Gestion des paiements via Stripe
 */

require_once __DIR__ . '/../models/Settings.php';

class StripeService
{
    private $settings;
    private $publicKey;
    private $secretKey;
    private $webhookSecret;
    private $isEnabled;
    private $mode;

    public function __construct()
    {
        $this->settings = new Settings();
        $this->isEnabled = $this->settings->isStripeEnabled();

        if ($this->isEnabled) {
            $keys = $this->settings->getStripeKeys();
            $this->publicKey = $keys['public_key'];
            $this->secretKey = $keys['secret_key'];
            $this->webhookSecret = $keys['webhook_secret'];
            $this->mode = $keys['mode'];
        }
    }

    /**
     * Vérifie si Stripe est activé
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * Retourne la clé publique pour le frontend
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * Retourne le mode actuel (test/live)
     */
    public function getMode(): string
    {
        return $this->mode ?? 'test';
    }

    /**
     * Crée une session de paiement Stripe Checkout
     *
     * @param array $orderData Données de la commande
     * @return array Résultat avec session_id ou erreur
     */
    public function createCheckoutSession(array $orderData): array
    {
        if (!$this->isEnabled) {
            return ['success' => false, 'error' => 'Stripe n\'est pas configuré'];
        }

        try {
            // Construire les items pour Stripe
            $lineItems = [];
            foreach ($orderData['items'] as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => (int)($item['price'] * 100), // En centimes
                        'product_data' => [
                            'name' => $item['name'],
                            'description' => $item['description'] ?? '',
                            'images' => !empty($item['image']) ? [$item['image']] : []
                        ]
                    ],
                    'quantity' => $item['quantity']
                ];
            }

            // Ajouter les frais de livraison comme ligne séparée
            if ($orderData['shipping_cost'] > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => (int)($orderData['shipping_cost'] * 100),
                        'product_data' => [
                            'name' => 'Frais de livraison',
                            'description' => $orderData['shipping_label'] ?? 'Livraison standard'
                        ]
                    ],
                    'quantity' => 1
                ];
            }

            // Appliquer la réduction si présente
            $discounts = [];
            if (!empty($orderData['discount']) && $orderData['discount'] > 0) {
                // Créer un coupon temporaire pour la réduction
                $coupon = $this->createCoupon($orderData['discount'], $orderData['promo_code'] ?? 'DISCOUNT');
                if ($coupon) {
                    $discounts[] = ['coupon' => $coupon['id']];
                }
            }

            // Créer la session Checkout
            $sessionData = [
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $orderData['success_url'] . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $orderData['cancel_url'],
                'customer_email' => $orderData['customer_email'] ?? null,
                'metadata' => [
                    'order_id' => $orderData['order_id'] ?? '',
                    'customer_name' => $orderData['customer_name'] ?? ''
                ],
                'shipping_address_collection' => [
                    'allowed_countries' => ['FR', 'BE', 'CH', 'LU', 'MC']
                ],
                'locale' => 'fr'
            ];

            if (!empty($discounts)) {
                $sessionData['discounts'] = $discounts;
            }

            $response = $this->stripeRequest('POST', '/checkout/sessions', $sessionData);

            if (isset($response['id'])) {
                return [
                    'success' => true,
                    'session_id' => $response['id'],
                    'url' => $response['url']
                ];
            }

            return ['success' => false, 'error' => $response['error']['message'] ?? 'Erreur inconnue'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crée un PaymentIntent (alternative à Checkout Session)
     */
    public function createPaymentIntent(float $amount, array $metadata = []): array
    {
        if (!$this->isEnabled) {
            return ['success' => false, 'error' => 'Stripe n\'est pas configuré'];
        }

        try {
            $data = [
                'amount' => (int)($amount * 100), // En centimes
                'currency' => 'eur',
                'payment_method_types' => ['card'],
                'metadata' => $metadata
            ];

            $response = $this->stripeRequest('POST', '/payment_intents', $data);

            if (isset($response['client_secret'])) {
                return [
                    'success' => true,
                    'client_secret' => $response['client_secret'],
                    'payment_intent_id' => $response['id']
                ];
            }

            return ['success' => false, 'error' => $response['error']['message'] ?? 'Erreur inconnue'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Récupère les détails d'une session Checkout
     */
    public function getCheckoutSession(string $sessionId): ?array
    {
        try {
            $response = $this->stripeRequest('GET', '/checkout/sessions/' . $sessionId);
            return $response['id'] ? $response : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Récupère les détails d'un PaymentIntent
     */
    public function getPaymentIntent(string $paymentIntentId): ?array
    {
        try {
            $response = $this->stripeRequest('GET', '/payment_intents/' . $paymentIntentId);
            return $response['id'] ? $response : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Vérifie et traite un webhook Stripe
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        if (empty($this->webhookSecret)) {
            return ['success' => false, 'error' => 'Webhook secret non configuré'];
        }

        try {
            // Vérifier la signature
            $timestamp = null;
            $signatures = [];

            foreach (explode(',', $signature) as $part) {
                $pair = explode('=', $part, 2);
                if ($pair[0] === 't') {
                    $timestamp = $pair[1];
                } elseif ($pair[0] === 'v1') {
                    $signatures[] = $pair[1];
                }
            }

            if (!$timestamp || empty($signatures)) {
                return ['success' => false, 'error' => 'Signature invalide'];
            }

            // Vérifier que le timestamp n'est pas trop ancien (5 minutes)
            if (abs(time() - $timestamp) > 300) {
                return ['success' => false, 'error' => 'Timestamp trop ancien'];
            }

            // Calculer la signature attendue
            $signedPayload = $timestamp . '.' . $payload;
            $expectedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

            $valid = false;
            foreach ($signatures as $sig) {
                if (hash_equals($expectedSignature, $sig)) {
                    $valid = true;
                    break;
                }
            }

            if (!$valid) {
                return ['success' => false, 'error' => 'Signature invalide'];
            }

            $event = json_decode($payload, true);
            return [
                'success' => true,
                'event_type' => $event['type'],
                'data' => $event['data']['object']
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crée un coupon temporaire pour une réduction
     */
    private function createCoupon(float $amount, string $name): ?array
    {
        try {
            $data = [
                'amount_off' => (int)($amount * 100),
                'currency' => 'eur',
                'duration' => 'once',
                'name' => $name
            ];

            $response = $this->stripeRequest('POST', '/coupons', $data);
            return $response['id'] ? $response : null;

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Effectue une requête à l'API Stripe
     */
    private function stripeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = 'https://api.stripe.com/v1' . $endpoint;

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
                'Stripe-Version: 2023-10-16'
            ],
            CURLOPT_TIMEOUT => 30
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($this->flattenArray($data));
        } elseif ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            if (!empty($data)) {
                $options[CURLOPT_POSTFIELDS] = http_build_query($this->flattenArray($data));
            }
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true) ?? [];

        if ($httpCode >= 400) {
            error_log("Stripe API Error ($httpCode): " . $response);
        }

        return $result;
    }

    /**
     * Aplatit un tableau pour les requêtes Stripe
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
