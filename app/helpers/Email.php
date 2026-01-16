<?php
/**
 * PERSONNALY - Helper Email
 * Envoi d'emails via mail() PHP (compatible o2switch)
 */

class Email
{
    private static string $fromEmail = 'noreply@personnaly.fr';
    private static string $fromName = 'PERSONNALY';
    private static string $adminEmail = 'admin@personnaly.fr';

    /**
     * Configure l'email admin (pour notifications)
     */
    public static function setAdminEmail(string $email): void
    {
        self::$adminEmail = $email;
    }

    /**
     * Envoie un email HTML
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::$fromName . ' <' . self::$fromEmail . '>',
            'Reply-To: ' . self::$adminEmail,
            'X-Mailer: PHP/' . phpversion(),
        ];

        return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }

    /**
     * Email de confirmation de commande au client
     */
    public static function sendOrderConfirmation(array $order, array $customer, array $items): bool
    {
        $subject = 'Confirmation de votre commande #' . $order['id'] . ' - PERSONNALY';

        $itemsHtml = '';
        foreach ($items as $item) {
            $customization = is_string($item['data_json'])
                ? json_decode($item['data_json'], true)
                : $item['data_json'];

            $itemsHtml .= '
            <tr>
                <td style="padding: 15px; border-bottom: 1px solid #eee;">
                    <strong>' . htmlspecialchars($item['product_name'] ?? 'Produit') . '</strong><br>
                    <small style="color: #666;">
                        Taille: ' . htmlspecialchars($customization['size'] ?? 'M') . ' •
                        Couleur: ' . ucfirst(htmlspecialchars($customization['color'] ?? 'blanc')) . '
                        ' . (!empty($customization['text']) ? '• Texte: "' . htmlspecialchars($customization['text']) . '"' : '') . '
                    </small>
                </td>
                <td style="padding: 15px; border-bottom: 1px solid #eee; text-align: center;">' . $item['quantity'] . '</td>
                <td style="padding: 15px; border-bottom: 1px solid #eee; text-align: right;">' . number_format($item['unit_price'] * $item['quantity'], 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $shippingAddress = is_string($order['shipping_address'])
            ? json_decode($order['shipping_address'], true)
            : $order['shipping_address'];

        $body = self::getEmailTemplate('
            <h1 style="color: #1A1A2E; margin-bottom: 10px;">Merci pour votre commande !</h1>
            <p style="color: #666; font-size: 16px;">
                Bonjour ' . htmlspecialchars($customer['first_name'] ?? '') . ',<br>
                Votre commande a bien été enregistrée. Voici le récapitulatif :
            </p>

            <div style="background: linear-gradient(135deg, #FF69B4 0%, #FF1493 100%); color: white; padding: 20px; border-radius: 10px; margin: 25px 0; text-align: center;">
                <span style="font-size: 14px; opacity: 0.9;">Numéro de commande</span><br>
                <span style="font-size: 28px; font-weight: bold;">#' . $order['id'] . '</span>
            </div>

            <h2 style="color: #1A1A2E; font-size: 18px; margin-top: 30px;">Articles commandés</h2>
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr style="background: #f8f8f8;">
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Produit</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600;">Qté</th>
                        <th style="padding: 12px; text-align: right; font-weight: 600;">Prix</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $itemsHtml . '
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="padding: 15px; text-align: right; font-weight: bold;">Total</td>
                        <td style="padding: 15px; text-align: right; font-weight: bold; color: #FF1493; font-size: 18px;">
                            ' . number_format($order['total'], 2, ',', ' ') . ' €
                        </td>
                    </tr>
                </tfoot>
            </table>

            <h2 style="color: #1A1A2E; font-size: 18px; margin-top: 30px;">Adresse de livraison</h2>
            <div style="background: #f8f8f8; padding: 20px; border-radius: 10px; margin-top: 15px;">
                <strong>' . htmlspecialchars(($shippingAddress['first_name'] ?? '') . ' ' . ($shippingAddress['last_name'] ?? '')) . '</strong><br>
                ' . htmlspecialchars($shippingAddress['address'] ?? '') . '<br>
                ' . htmlspecialchars(($shippingAddress['zipcode'] ?? '') . ' ' . ($shippingAddress['city'] ?? '')) . '
                ' . (!empty($shippingAddress['phone']) ? '<br>Tél: ' . htmlspecialchars($shippingAddress['phone']) : '') . '
            </div>

            <p style="color: #666; font-size: 14px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                Nous vous tiendrons informé(e) de l\'avancement de votre commande.<br>
                Pour toute question, contactez-nous à <a href="mailto:contact@personnaly.fr" style="color: #FF69B4;">contact@personnaly.fr</a>
            </p>
        ');

        return self::send($customer['email'], $subject, $body);
    }

    /**
     * Notification admin : nouvelle commande
     */
    public static function sendAdminNewOrder(array $order, array $customer, array $items): bool
    {
        $subject = '🛒 Nouvelle commande #' . $order['id'] . ' - ' . number_format($order['total'], 2, ',', ' ') . ' €';

        $itemsHtml = '';
        foreach ($items as $item) {
            $customization = is_string($item['data_json'])
                ? json_decode($item['data_json'], true)
                : $item['data_json'];

            $itemsHtml .= '<li style="margin-bottom: 10px;">
                <strong>' . htmlspecialchars($item['product_name'] ?? 'Produit') . '</strong> x' . $item['quantity'] . '<br>
                <small style="color: #666;">
                    ' . htmlspecialchars($customization['size'] ?? 'M') . ' / ' . ucfirst(htmlspecialchars($customization['color'] ?? 'blanc')) . '
                    ' . (!empty($customization['text']) ? ' / "' . htmlspecialchars($customization['text']) . '"' : '') . '
                </small>
            </li>';
        }

        $shippingAddress = is_string($order['shipping_address'])
            ? json_decode($order['shipping_address'], true)
            : $order['shipping_address'];

        $body = self::getEmailTemplate('
            <h1 style="color: #1A1A2E; margin-bottom: 10px;">🎉 Nouvelle commande !</h1>

            <div style="background: linear-gradient(135deg, #3DFFC0 0%, #00D9A0 100%); color: #1A1A2E; padding: 20px; border-radius: 10px; margin: 25px 0;">
                <table style="width: 100%;">
                    <tr>
                        <td>
                            <span style="font-size: 14px; opacity: 0.8;">Commande</span><br>
                            <span style="font-size: 24px; font-weight: bold;">#' . $order['id'] . '</span>
                        </td>
                        <td style="text-align: right;">
                            <span style="font-size: 14px; opacity: 0.8;">Montant</span><br>
                            <span style="font-size: 24px; font-weight: bold;">' . number_format($order['total'], 2, ',', ' ') . ' €</span>
                        </td>
                    </tr>
                </table>
            </div>

            <h2 style="color: #1A1A2E; font-size: 16px;">Client</h2>
            <p style="background: #f8f8f8; padding: 15px; border-radius: 8px;">
                <strong>' . htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) . '</strong><br>
                <a href="mailto:' . htmlspecialchars($customer['email'] ?? '') . '" style="color: #FF69B4;">' . htmlspecialchars($customer['email'] ?? '') . '</a>
                ' . (!empty($customer['phone']) ? '<br>' . htmlspecialchars($customer['phone']) : '') . '
            </p>

            <h2 style="color: #1A1A2E; font-size: 16px;">Articles</h2>
            <ul style="background: #f8f8f8; padding: 20px 20px 20px 35px; border-radius: 8px; margin: 0;">
                ' . $itemsHtml . '
            </ul>

            <h2 style="color: #1A1A2E; font-size: 16px; margin-top: 20px;">Livraison</h2>
            <p style="background: #f8f8f8; padding: 15px; border-radius: 8px;">
                ' . htmlspecialchars($shippingAddress['address'] ?? '') . '<br>
                ' . htmlspecialchars(($shippingAddress['zipcode'] ?? '') . ' ' . ($shippingAddress['city'] ?? '')) . '
            </p>

            <div style="text-align: center; margin-top: 30px;">
                <a href="https://personnaly.fr/admin/order.php?id=' . $order['id'] . '"
                   style="display: inline-block; background: linear-gradient(135deg, #FF69B4 0%, #FF1493 100%); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; font-weight: bold;">
                    Voir la commande →
                </a>
            </div>
        ');

        return self::send(self::$adminEmail, $subject, $body);
    }

    /**
     * Template email de base
     */
    private static function getEmailTemplate(string $content): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%); padding: 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 800;">
                                <span style="background: linear-gradient(135deg, #FF69B4 0%, #3DFFC0 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">PERSONNALY</span>
                            </h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            ' . $content . '
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background: #f8f8f8; padding: 25px 30px; text-align: center;">
                            <p style="margin: 0; color: #999; font-size: 13px;">
                                PERSONNALY - Personnalisation textile pour toute la famille<br>
                                <a href="https://personnaly.fr" style="color: #FF69B4; text-decoration: none;">personnaly.fr</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}
