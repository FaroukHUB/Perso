<?php
/**
 * PERSONNALY - Webhook Stripe
 * Traite les événements de paiement Stripe
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Email.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/services/StripeService.php';

// Récupérer le payload brut
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$stripeService = new StripeService();

// Vérifier la signature du webhook
$result = $stripeService->handleWebhook($payload, $sigHeader);

if (!$result['success']) {
    http_response_code(400);
    error_log('Stripe Webhook Error: ' . $result['error']);
    echo json_encode(['error' => $result['error']]);
    exit;
}

$eventType = $result['event_type'];
$data = $result['data'];

// Traiter les différents types d'événements
switch ($eventType) {
    case 'checkout.session.completed':
        handleCheckoutSessionCompleted($data);
        break;

    case 'payment_intent.succeeded':
        // Alternative si checkout.session.completed n'est pas reçu
        handlePaymentIntentSucceeded($data);
        break;

    case 'payment_intent.payment_failed':
        handlePaymentFailed($data);
        break;

    default:
        // Log l'événement pour référence
        error_log('Stripe Webhook: Unhandled event type ' . $eventType);
}

http_response_code(200);
echo json_encode(['received' => true]);

/**
 * Traite une session de checkout complétée
 */
function handleCheckoutSessionCompleted(array $session): void
{
    $orderId = $session['metadata']['order_id'] ?? null;

    if (!$orderId) {
        error_log('Stripe Webhook: No order_id in session metadata');
        return;
    }

    $db = Database::getInstance();

    // Vérifier si la commande existe et n'est pas déjà payée
    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND status IN ("pending_payment", "pending")');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        error_log('Stripe Webhook: Order not found or already processed: ' . $orderId);
        return;
    }

    // Mettre à jour le statut
    $stmt = $db->prepare('UPDATE orders SET status = "paid", stripe_session_id = ? WHERE id = ?');
    $stmt->execute([$session['id'], $orderId]);

    // Envoyer les emails de confirmation
    sendOrderConfirmationEmails($orderId, $order);

    error_log('Stripe Webhook: Order ' . $orderId . ' marked as paid');
}

/**
 * Traite un paiement réussi
 */
function handlePaymentIntentSucceeded(array $paymentIntent): void
{
    $orderId = $paymentIntent['metadata']['order_id'] ?? null;

    if (!$orderId) {
        return;
    }

    $db = Database::getInstance();

    // Vérifier si déjà traité
    $stmt = $db->prepare('SELECT status FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if ($order && $order['status'] === 'pending_payment') {
        $stmt = $db->prepare('UPDATE orders SET status = "paid" WHERE id = ?');
        $stmt->execute([$orderId]);
    }
}

/**
 * Traite un échec de paiement
 */
function handlePaymentFailed(array $paymentIntent): void
{
    $orderId = $paymentIntent['metadata']['order_id'] ?? null;

    if (!$orderId) {
        return;
    }

    $db = Database::getInstance();

    // Marquer la commande comme échouée
    $stmt = $db->prepare('UPDATE orders SET status = "cancelled", notes = CONCAT(IFNULL(notes, ""), "\n[Paiement échoué]") WHERE id = ?');
    $stmt->execute([$orderId]);

    error_log('Stripe Webhook: Payment failed for order ' . $orderId);
}

/**
 * Envoie les emails de confirmation de commande
 */
function sendOrderConfirmationEmails(int $orderId, array $order): void
{
    try {
        $db = Database::getInstance();
        $orderModel = new Order();
        $userModel = new User();

        $customer = $userModel->findById($order['user_id']);
        if (!$customer) return;

        $shippingData = json_decode($order['shipping_address'], true);
        $customerData = [
            'email' => $customer['email'],
            'first_name' => $shippingData['first_name'] ?? $customer['first_name'],
            'last_name' => $shippingData['last_name'] ?? $customer['last_name'],
            'phone' => $shippingData['phone'] ?? ''
        ];

        $items = $orderModel->getCustomizations($orderId);
        $emailItems = [];
        foreach ($items as $item) {
            $emailItems[] = [
                'product_name' => $item['product_name'] ?? 'Produit',
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'data_json' => json_decode($item['data_json'], true) ?? []
            ];
        }

        $orderData = [
            'id' => $orderId,
            'total' => $order['total'],
            'shipping_address' => $order['shipping_address']
        ];

        Email::sendOrderConfirmation($orderData, $customerData, $emailItems);
        Email::sendAdminNewOrder($orderData, $customerData, $emailItems);

    } catch (Exception $e) {
        error_log('Stripe Webhook: Email error for order ' . $orderId . ': ' . $e->getMessage());
    }
}
