<?php
/**
 * PERSONNALY - Service WhatsApp Business
 * API Cloud de Meta (Facebook)
 * Limite gratuite: 1000 conversations/mois
 */

require_once __DIR__ . '/../models/Settings.php';

class WhatsAppService
{
    private const API_URL = 'https://graph.facebook.com/v18.0';

    private Settings $settings;
    private ?string $phoneId;
    private ?string $accessToken;
    private ?string $businessId;
    private int $monthlyLimit;

    public function __construct()
    {
        $this->settings = new Settings();
        $this->phoneId = $this->settings->get('whatsapp_phone_id');
        $this->accessToken = $this->settings->get('whatsapp_access_token');
        $this->businessId = $this->settings->get('whatsapp_business_id');
        $this->monthlyLimit = (int) ($this->settings->get('whatsapp_monthly_limit') ?: 1000);
    }

    /**
     * Vérifie si WhatsApp est activé et configuré
     */
    public function isEnabled(): bool
    {
        return $this->settings->get('whatsapp_enabled') === '1'
            && !empty($this->phoneId)
            && !empty($this->accessToken);
    }

    /**
     * Retourne le nombre de messages restants pour ce mois
     */
    public function getRemainingThisMonth(): int
    {
        $this->resetMonthlyCounterIfNeeded();
        $sent = (int) $this->settings->get('whatsapp_monthly_sent');
        return max(0, $this->monthlyLimit - $sent);
    }

    /**
     * Retourne les statistiques d'envoi
     */
    public function getStats(): array
    {
        $this->resetMonthlyCounterIfNeeded();
        return [
            'enabled' => $this->isEnabled(),
            'monthly_limit' => $this->monthlyLimit,
            'monthly_sent' => (int) $this->settings->get('whatsapp_monthly_sent'),
            'monthly_remaining' => $this->getRemainingThisMonth(),
            'phone_id' => $this->phoneId ? substr($this->phoneId, 0, 6) . '...' : null
        ];
    }

    /**
     * Envoie un message texte simple
     */
    public function sendTextMessage(string $to, string $message): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'WhatsApp n\'est pas activé'];
        }

        if ($this->getRemainingThisMonth() <= 0) {
            return ['success' => false, 'error' => 'Limite mensuelle atteinte (1000 messages/mois)'];
        }

        // Formater le numéro (enlever le + et les espaces)
        $to = preg_replace('/[^0-9]/', '', $to);

        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message
            ]
        ];

        $response = $this->apiRequest('POST', "/{$this->phoneId}/messages", $data);

        if ($response['success']) {
            $this->incrementMonthlyCounter();
        }

        return $response;
    }

    /**
     * Envoie un message template (pré-approuvé par Meta)
     * Les templates sont obligatoires pour initier une conversation
     */
    public function sendTemplateMessage(string $to, string $templateName, string $language = 'fr', array $components = []): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'WhatsApp n\'est pas activé'];
        }

        if ($this->getRemainingThisMonth() <= 0) {
            return ['success' => false, 'error' => 'Limite mensuelle atteinte (1000 messages/mois)'];
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        $template = [
            'name' => $templateName,
            'language' => ['code' => $language]
        ];

        if (!empty($components)) {
            $template['components'] = $components;
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $template
        ];

        $response = $this->apiRequest('POST', "/{$this->phoneId}/messages", $data);

        if ($response['success']) {
            $this->incrementMonthlyCounter();
        }

        return $response;
    }

    /**
     * Envoie un message avec média (image, document, etc.)
     */
    public function sendMediaMessage(string $to, string $type, string $mediaUrl, string $caption = ''): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'WhatsApp n\'est pas activé'];
        }

        if ($this->getRemainingThisMonth() <= 0) {
            return ['success' => false, 'error' => 'Limite mensuelle atteinte'];
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        $mediaData = ['link' => $mediaUrl];
        if (!empty($caption) && in_array($type, ['image', 'video', 'document'])) {
            $mediaData['caption'] = $caption;
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
            $type => $mediaData
        ];

        $response = $this->apiRequest('POST', "/{$this->phoneId}/messages", $data);

        if ($response['success']) {
            $this->incrementMonthlyCounter();
        }

        return $response;
    }

    /**
     * Envoie une campagne de messages à plusieurs destinataires
     * Utilise les templates car c'est obligatoire pour initier des conversations
     */
    public function sendBroadcast(array $recipients, string $templateName, string $language = 'fr', array $components = []): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'WhatsApp n\'est pas activé'];
        }

        $remaining = $this->getRemainingThisMonth();
        if (count($recipients) > $remaining) {
            return [
                'success' => false,
                'error' => "Limite mensuelle insuffisante. Restant: {$remaining}, Demandé: " . count($recipients)
            ];
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($recipients as $recipient) {
            $phone = is_array($recipient) ? ($recipient['phone'] ?? $recipient['tel'] ?? '') : $recipient;

            if (empty($phone)) {
                $failed++;
                continue;
            }

            $result = $this->sendTemplateMessage($phone, $templateName, $language, $components);

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
                $errors[] = $phone . ': ' . ($result['error'] ?? 'Erreur inconnue');
            }

            // Rate limiting: 80 messages/seconde max, on reste prudent
            usleep(50000); // 50ms entre chaque message
        }

        return [
            'success' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($recipients),
            'errors' => array_slice($errors, 0, 10) // Max 10 erreurs affichées
        ];
    }

    /**
     * Récupère les templates disponibles
     */
    public function getTemplates(): array
    {
        if (!$this->isEnabled() || empty($this->businessId)) {
            return ['success' => false, 'error' => 'WhatsApp n\'est pas configuré'];
        }

        return $this->apiRequest('GET', "/{$this->businessId}/message_templates?limit=100");
    }

    /**
     * Marque un message comme lu
     */
    public function markAsRead(string $messageId): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'WhatsApp n\'est pas activé'];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];

        return $this->apiRequest('POST', "/{$this->phoneId}/messages", $data);
    }

    /**
     * Vérifie la validité du webhook (pour la configuration initiale)
     */
    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        $verifyToken = $this->settings->get('whatsapp_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $challenge;
        }

        return null;
    }

    /**
     * Traite un webhook entrant (messages reçus, statuts, etc.)
     */
    public function handleWebhook(array $payload): array
    {
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return ['success' => false, 'error' => 'Payload invalide'];
        }

        $changes = $entry['changes'][0] ?? null;
        if (!$changes || ($changes['field'] ?? '') !== 'messages') {
            return ['success' => true, 'type' => 'ignored'];
        }

        $value = $changes['value'] ?? [];
        $messages = $value['messages'] ?? [];
        $statuses = $value['statuses'] ?? [];

        $result = ['success' => true, 'messages' => [], 'statuses' => []];

        // Traiter les messages reçus
        foreach ($messages as $message) {
            $result['messages'][] = [
                'id' => $message['id'],
                'from' => $message['from'],
                'timestamp' => $message['timestamp'],
                'type' => $message['type'],
                'text' => $message['text']['body'] ?? null
            ];
        }

        // Traiter les statuts (envoyé, délivré, lu)
        foreach ($statuses as $status) {
            $result['statuses'][] = [
                'id' => $status['id'],
                'status' => $status['status'],
                'timestamp' => $status['timestamp'],
                'recipient_id' => $status['recipient_id']
            ];
        }

        return $result;
    }

    /**
     * Effectue une requête à l'API Meta
     */
    private function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = self::API_URL . $endpoint;

        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Erreur cURL: ' . $error];
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $result];
        }

        $errorMessage = $result['error']['message'] ?? 'Erreur API WhatsApp';
        return ['success' => false, 'error' => $errorMessage, 'code' => $httpCode];
    }

    /**
     * Incrémente le compteur mensuel
     */
    private function incrementMonthlyCounter(): void
    {
        $this->resetMonthlyCounterIfNeeded();
        $current = (int) $this->settings->get('whatsapp_monthly_sent');
        $this->settings->set('whatsapp_monthly_sent', (string) ($current + 1));
    }

    /**
     * Réinitialise le compteur si on est un nouveau mois
     */
    private function resetMonthlyCounterIfNeeded(): void
    {
        $lastReset = $this->settings->get('whatsapp_monthly_reset');
        $currentMonth = date('Y-m');

        if (substr($lastReset, 0, 7) !== $currentMonth) {
            $this->settings->set('whatsapp_monthly_sent', '0');
            $this->settings->set('whatsapp_monthly_reset', date('Y-m-d'));
        }
    }
}
