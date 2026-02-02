<?php
/**
 * PERSONNALY - Service Brevo (ex-Sendinblue)
 * Gestion des emails marketing
 * Limite gratuite: 300 emails/jour
 */

require_once __DIR__ . '/../models/Settings.php';

class BrevoService
{
    private const API_URL = 'https://api.brevo.com/v3';

    private Settings $settings;
    private ?string $apiKey;
    private string $senderName;
    private string $senderEmail;
    private int $dailyLimit;

    public function __construct()
    {
        $this->settings = new Settings();
        $this->apiKey = $this->settings->get('brevo_api_key');
        $this->senderName = $this->settings->get('brevo_sender_name') ?: 'PERSONNALY';
        $this->senderEmail = $this->settings->get('brevo_sender_email') ?: '';
        $this->dailyLimit = (int) ($this->settings->get('brevo_daily_limit') ?: 300);
    }

    /**
     * Vérifie si Brevo est activé et configuré
     */
    public function isEnabled(): bool
    {
        return $this->settings->get('brevo_enabled') === '1'
            && !empty($this->apiKey)
            && !empty($this->senderEmail);
    }

    /**
     * Retourne le nombre d'emails restants pour aujourd'hui
     */
    public function getRemainingToday(): int
    {
        $this->resetDailyCounterIfNeeded();
        $sent = (int) $this->settings->get('brevo_daily_sent');
        return max(0, $this->dailyLimit - $sent);
    }

    /**
     * Retourne les statistiques d'envoi
     */
    public function getStats(): array
    {
        $this->resetDailyCounterIfNeeded();
        return [
            'enabled' => $this->isEnabled(),
            'daily_limit' => $this->dailyLimit,
            'daily_sent' => (int) $this->settings->get('brevo_daily_sent'),
            'daily_remaining' => $this->getRemainingToday(),
            'sender_email' => $this->senderEmail,
            'sender_name' => $this->senderName
        ];
    }

    /**
     * Envoie un email simple
     */
    public function sendEmail(array $params): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'Brevo n\'est pas activé'];
        }

        if ($this->getRemainingToday() <= 0) {
            return ['success' => false, 'error' => 'Limite quotidienne atteinte (300 emails/jour)'];
        }

        $to = $params['to'] ?? [];
        $subject = $params['subject'] ?? '';
        $htmlContent = $params['html'] ?? '';
        $textContent = $params['text'] ?? strip_tags($htmlContent);

        if (empty($to) || empty($subject)) {
            return ['success' => false, 'error' => 'Destinataire et sujet requis'];
        }

        // Formater les destinataires
        $recipients = [];
        foreach ((array) $to as $email) {
            if (is_array($email)) {
                $recipients[] = $email;
            } else {
                $recipients[] = ['email' => $email];
            }
        }

        $data = [
            'sender' => [
                'name' => $this->senderName,
                'email' => $this->senderEmail
            ],
            'to' => $recipients,
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'textContent' => $textContent
        ];

        // Ajouter reply-to si fourni
        if (!empty($params['reply_to'])) {
            $data['replyTo'] = ['email' => $params['reply_to']];
        }

        $response = $this->apiRequest('POST', '/smtp/email', $data);

        if ($response['success']) {
            $this->incrementDailyCounter(count($recipients));
        }

        return $response;
    }

    /**
     * Envoie un email de campagne à plusieurs destinataires
     */
    public function sendCampaign(array $params): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'Brevo n\'est pas activé'];
        }

        $recipients = $params['recipients'] ?? [];
        $subject = $params['subject'] ?? '';
        $htmlContent = $params['html'] ?? '';

        if (empty($recipients)) {
            return ['success' => false, 'error' => 'Aucun destinataire'];
        }

        $remaining = $this->getRemainingToday();
        if (count($recipients) > $remaining) {
            return [
                'success' => false,
                'error' => "Limite quotidienne insuffisante. Restant: {$remaining}, Demandé: " . count($recipients)
            ];
        }

        // Envoyer par lots de 50 pour éviter les timeouts
        $batchSize = 50;
        $batches = array_chunk($recipients, $batchSize);
        $totalSent = 0;
        $errors = [];

        foreach ($batches as $batch) {
            $result = $this->sendEmail([
                'to' => $batch,
                'subject' => $subject,
                'html' => $htmlContent,
                'text' => $params['text'] ?? null
            ]);

            if ($result['success']) {
                $totalSent += count($batch);
            } else {
                $errors[] = $result['error'];
            }

            // Petite pause entre les lots
            usleep(100000); // 100ms
        }

        return [
            'success' => empty($errors),
            'sent' => $totalSent,
            'total' => count($recipients),
            'errors' => $errors
        ];
    }

    /**
     * Crée ou met à jour un contact dans Brevo
     */
    public function createContact(string $email, array $attributes = [], array $listIds = []): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'Brevo n\'est pas activé'];
        }

        $data = [
            'email' => $email,
            'updateEnabled' => true
        ];

        if (!empty($attributes)) {
            $data['attributes'] = $attributes;
        }

        if (!empty($listIds)) {
            $data['listIds'] = $listIds;
        }

        return $this->apiRequest('POST', '/contacts', $data);
    }

    /**
     * Récupère les listes de contacts
     */
    public function getLists(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'Brevo n\'est pas activé'];
        }

        return $this->apiRequest('GET', '/contacts/lists?limit=50&offset=0');
    }

    /**
     * Envoie un email transactionnel (confirmation commande, etc.)
     */
    public function sendTransactional(string $templateId, string $to, array $params = []): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'Brevo n\'est pas activé'];
        }

        $data = [
            'to' => [['email' => $to]],
            'templateId' => (int) $templateId,
            'params' => $params
        ];

        $response = $this->apiRequest('POST', '/smtp/email', $data);

        if ($response['success']) {
            $this->incrementDailyCounter(1);
        }

        return $response;
    }

    /**
     * Effectue une requête à l'API Brevo
     */
    private function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = self::API_URL . $endpoint;

        $headers = [
            'accept: application/json',
            'api-key: ' . $this->apiKey,
            'content-type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
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

        $errorMessage = $result['message'] ?? $result['error'] ?? 'Erreur API Brevo';
        return ['success' => false, 'error' => $errorMessage, 'code' => $httpCode];
    }

    /**
     * Incrémente le compteur quotidien
     */
    private function incrementDailyCounter(int $count = 1): void
    {
        $this->resetDailyCounterIfNeeded();
        $current = (int) $this->settings->get('brevo_daily_sent');
        $this->settings->set('brevo_daily_sent', (string) ($current + $count));
    }

    /**
     * Réinitialise le compteur si on est un nouveau jour
     */
    private function resetDailyCounterIfNeeded(): void
    {
        $lastReset = $this->settings->get('brevo_daily_reset');
        $today = date('Y-m-d');

        if ($lastReset !== $today) {
            $this->settings->set('brevo_daily_sent', '0');
            $this->settings->set('brevo_daily_reset', $today);
        }
    }
}
