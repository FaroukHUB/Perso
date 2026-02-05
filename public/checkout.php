<?php
/**
 * PERSONNALY - Page Checkout (Commande)
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 * Intégration Stripe pour les paiements
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/helpers/Email.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Upsell.php';
require_once __DIR__ . '/../app/services/StripeService.php';
require_once __DIR__ . '/../app/services/BoxtalService.php';

// Initialiser les services
$stripeService = new StripeService();
$boxtalService = new BoxtalService();

// Vérifier si c'est un retour de Stripe
$stripeSessionId = $_GET['session_id'] ?? null;
$paymentSuccess = false;
$orderId = null;

if ($stripeSessionId && $stripeService->isEnabled()) {
    // Retour de Stripe - vérifier le paiement
    $session = $stripeService->getCheckoutSession($stripeSessionId);

    if ($session && $session['payment_status'] === 'paid') {
        // Récupérer l'order_id depuis les metadata
        $orderId = $session['metadata']['order_id'] ?? null;

        if ($orderId) {
            // Mettre à jour le statut de la commande
            $db = Database::getInstance();
            $stmt = $db->prepare('UPDATE orders SET status = "paid", stripe_session_id = ? WHERE id = ?');
            $stmt->execute([$stripeSessionId, $orderId]);

            // Récupérer les infos pour les emails
            $orderModel = new Order();
            $order = $orderModel->findById($orderId);

            if ($order) {
                // Envoyer les emails de confirmation
                $userModel = new User();
                $customer = $userModel->findById($order['user_id']);

                if ($customer) {
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

                    try {
                        Email::sendOrderConfirmation($order, $customerData, $emailItems);
                        Email::sendAdminNewOrder($order, $customerData, $emailItems);
                    } catch (Exception $e) {
                        // Log silencieux
                    }
                }
            }

            // Vider le panier si encore présent
            Cart::clear();
            $paymentSuccess = true;
        }
    }
}

// Panier vide = retour accueil (sauf si paiement réussi)
if (Cart::isEmpty() && !$paymentSuccess) {
    redirect('/');
}

$cartItems = Cart::getItemsWithProducts();
$cartTotal = Cart::getTotal();
$cartCount = Cart::count();

// Récupérer les upsells applicables pour le checkout
$applicableUpsells = [];
$upsellModel = new Upsell();
$productModel = new Product();

$products = [];
$techniques = [];
$quantity = 0;

foreach ($cartItems as $item) {
    $products[] = $item['product_id'];
    $quantity += $item['quantity'];
    if (!empty($item['customization']['technique'])) {
        $techniques[] = $item['customization']['technique'];
    }
}

$context = [
    'cart_total' => $cartTotal,
    'products' => array_unique($products),
    'techniques' => array_unique($techniques),
    'categories' => [],
    'quantity' => $quantity
];

$upsells = $upsellModel->findApplicable($context, 'checkout');

foreach ($upsells as $upsell) {
    $data = $upsell;
    if ($upsell['offer_type'] === 'produit' && !empty($upsell['offer_value'])) {
        $product = $productModel->findById((int) $upsell['offer_value']);
        if ($product) {
            $data['product'] = $product;
            if ($upsell['discount_type'] === 'pourcentage') {
                $data['product']['discounted_price'] = $product['price'] * (1 - $upsell['discount_value'] / 100);
            } elseif ($upsell['discount_type'] === 'montant_fixe') {
                $data['product']['discounted_price'] = max(0, $product['price'] - $upsell['discount_value']);
            }
        }
    }
    $applicableUpsells[] = $data;
}

$success = $paymentSuccess;
$error = '';

// Charger les options de livraison disponibles
$defaultRecipient = ['postcode' => '75001', 'city' => 'Paris', 'country' => 'FR'];
$cartWeight = $boxtalService->calculateCartWeight($cartItems);
$shippingRates = $boxtalService->getShippingRates($defaultRecipient, $cartWeight, $cartTotal);

// Organiser par type (domicile vs point relais)
$homeDeliveryOptions = [];
$relayOptions = [];
foreach ($shippingRates as $rate) {
    if ($rate['is_relay'] ?? false) {
        $relayOptions[] = $rate;
    } else {
        $homeDeliveryOptions[] = $rate;
    }
}

// Récupérer la sélection depuis la session
$shippingMethod = $_SESSION['shipping_method'] ?? '';
$shippingCost = $_SESSION['shipping_cost'] ?? 0;
$shippingLabel = $_SESSION['shipping_label'] ?? 'Livraison';

// Si pas de méthode sélectionnée, prendre la première option disponible
if (empty($shippingMethod) && !empty($shippingRates)) {
    $shippingMethod = $shippingRates[0]['id'];
    $shippingCost = $shippingRates[0]['price'];
    $shippingLabel = $shippingRates[0]['label'];
    $_SESSION['shipping_method'] = $shippingMethod;
    $_SESSION['shipping_cost'] = $shippingCost;
    $_SESSION['shipping_label'] = $shippingLabel;
}

// Traitement AJAX de la sélection de livraison
if (isset($_POST['ajax_shipping']) && isset($_POST['shipping_method'])) {
    header('Content-Type: application/json');
    $method = $_POST['shipping_method'];
    foreach ($shippingRates as $rate) {
        if ($rate['id'] === $method) {
            $_SESSION['shipping_method'] = $method;
            $_SESSION['shipping_cost'] = $rate['price'];
            $_SESSION['shipping_label'] = $rate['label'];
            // Vérifier si c'est un transporteur point relais
            $isRelay = $rate['is_relay'] ?? false;
            echo json_encode([
                'success' => true,
                'price' => $rate['price'],
                'label' => $rate['label'],
                'total' => $cartTotal + $rate['price'],
                'is_relay' => $isRelay
            ]);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

// Traitement AJAX pour récupérer les points relais
if (isset($_POST['ajax_relay_points'])) {
    header('Content-Type: application/json');
    $carrierCode = $_POST['carrier'] ?? '';
    $postcode = $_POST['postcode'] ?? '';

    // Debug log
    error_log("checkout.php ajax_relay_points: carrier=$carrierCode, postcode=$postcode");

    if (empty($carrierCode) || empty($postcode)) {
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants', 'debug' => "carrier=$carrierCode, postcode=$postcode"]);
        exit;
    }

    $relayPoints = $boxtalService->getRelayPoints($carrierCode, $postcode);
    error_log("checkout.php ajax_relay_points: found " . count($relayPoints) . " points");

    echo json_encode([
        'success' => true,
        'points' => $relayPoints,
        'debug' => ['carrier' => $carrierCode, 'postcode' => $postcode, 'count' => count($relayPoints)]
    ]);
    exit;
}

// Traitement AJAX pour sélectionner un point relais
if (isset($_POST['ajax_select_relay'])) {
    header('Content-Type: application/json');
    $relayCode = $_POST['relay_code'] ?? '';
    $relayName = $_POST['relay_name'] ?? '';
    $relayAddress = $_POST['relay_address'] ?? '';

    $_SESSION['relay_point'] = [
        'code' => $relayCode,
        'name' => $relayName,
        'address' => $relayAddress
    ];

    echo json_encode(['success' => true]);
    exit;
}

// Traitement de la commande
if (isPost() && isset($_POST['place_order']) && !$paymentSuccess) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        // Validation des champs
        $email = trim(post('email', ''));
        $firstName = trim(post('first_name', ''));
        $lastName = trim(post('last_name', ''));
        $phone = trim(post('phone', ''));
        $address = trim(post('address', ''));
        $city = trim(post('city', ''));
        $zipcode = trim(post('zipcode', ''));
        $notes = trim(post('notes', ''));

        if (empty($email) || empty($firstName) || empty($lastName) || empty($address) || empty($city) || empty($zipcode)) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Veuillez entrer une adresse email valide.';
        } else {
            try {
                $db = Database::getInstance();
                $db->beginTransaction();

                // Créer ou récupérer le client
                $userModel = new User();
                $existingUser = $userModel->findByEmail($email);

                if ($existingUser) {
                    $userId = $existingUser['id'];
                } else {
                    // Créer un nouveau client (sans mot de passe pour l'instant)
                    $stmt = $db->prepare(
                        'INSERT INTO users (email, password_hash, role, first_name, last_name, phone, created_at)
                         VALUES (?, ?, "client", ?, ?, ?, NOW())'
                    );
                    $stmt->execute([$email, '', $firstName, $lastName, $phone]);
                    $userId = (int) $db->lastInsertId();
                }

                // Adresse de livraison formatée (avec point relais si sélectionné)
                $relayPointCode = post('relay_point_code', '');
                $relayPointName = post('relay_point_name', '');
                $relayPointAddress = post('relay_point_address', '');

                $shippingData = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'address' => $address,
                    'city' => $city,
                    'zipcode' => $zipcode,
                    'phone' => $phone,
                ];

                // Ajouter les infos point relais si sélectionné
                if (!empty($relayPointCode)) {
                    $shippingData['relay_point'] = [
                        'code' => $relayPointCode,
                        'name' => $relayPointName,
                        'address' => $relayPointAddress
                    ];
                }

                $shippingAddress = json_encode($shippingData, JSON_UNESCAPED_UNICODE);

                // Calculer le total avec livraison
                $totalWithShipping = $cartTotal + $shippingCost;

                // Créer la commande avec statut approprié
                $orderModel = new Order();
                $initialStatus = $stripeService->isEnabled() ? 'pending_payment' : 'pending';

                $stmt = $db->prepare(
                    'INSERT INTO orders (user_id, total, status, shipping_address, shipping_cost, notes, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$userId, $totalWithShipping, $initialStatus, $shippingAddress, $shippingCost, $notes]);
                $orderId = (int) $db->lastInsertId();

                // Ajouter les personnalisations
                foreach ($cartItems as $item) {
                    $orderModel->addCustomization($orderId, [
                        'product_id' => $item['product_id'],
                        'customization' => $item['customization'],
                    ]);

                    // Mettre à jour quantity et unit_price
                    $stmt = $db->prepare(
                        'UPDATE order_customizations SET quantity = ?, unit_price = ?
                         WHERE order_id = ? AND product_id = ?
                         ORDER BY id DESC LIMIT 1'
                    );
                    $stmt->execute([$item['quantity'], $item['unit_price'], $orderId, $item['product_id']]);
                }

                $db->commit();

                // Si Stripe est activé, rediriger vers le paiement
                if ($stripeService->isEnabled()) {
                    // Préparer les items pour Stripe
                    $stripeItems = [];
                    foreach ($cartItems as $item) {
                        $stripeItems[] = [
                            'name' => $item['product']['name'],
                            'description' => sprintf(
                                'Taille: %s, Couleur: %s',
                                $item['customization']['size'] ?? 'M',
                                ucfirst($item['customization']['color'] ?? 'blanc')
                            ),
                            'price' => $item['unit_price'],
                            'quantity' => $item['quantity'],
                            'image' => !empty($item['product']['image_front_url'])
                                ? (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/public' . $item['product']['image_front_url']
                                : ''
                        ];
                    }

                    // Construire les URLs
                    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

                    // Créer la session Stripe Checkout
                    $stripeResult = $stripeService->createCheckoutSession([
                        'items' => $stripeItems,
                        'shipping_cost' => $shippingCost,
                        'shipping_label' => $shippingMethod,
                        'discount' => $_SESSION['promo_code']['discount'] ?? 0,
                        'promo_code' => $_SESSION['promo_code']['code'] ?? '',
                        'success_url' => $baseUrl . '/public/checkout.php',
                        'cancel_url' => $baseUrl . '/public/cart.php',
                        'customer_email' => $email,
                        'customer_name' => $firstName . ' ' . $lastName,
                        'order_id' => $orderId
                    ]);

                    if ($stripeResult['success']) {
                        // Rediriger vers Stripe Checkout
                        header('Location: ' . $stripeResult['url']);
                        exit;
                    } else {
                        // Erreur Stripe - annuler la commande
                        $stmt = $db->prepare('UPDATE orders SET status = "cancelled" WHERE id = ?');
                        $stmt->execute([$orderId]);
                        $error = 'Erreur lors de la création du paiement: ' . $stripeResult['error'];
                    }
                } else {
                    // Pas de Stripe - procéder normalement
                    $orderData = [
                        'id' => $orderId,
                        'total' => $totalWithShipping,
                        'shipping_address' => $shippingAddress,
                    ];
                    $customerData = [
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $phone,
                    ];

                    // Récupérer les items pour emails
                    $emailItems = [];
                    foreach ($cartItems as $item) {
                        $emailItems[] = [
                            'product_name' => $item['product']['name'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'data_json' => $item['customization'],
                        ];
                    }

                    // Envoyer emails
                    try {
                        Email::sendOrderConfirmation($orderData, $customerData, $emailItems);
                        Email::sendAdminNewOrder($orderData, $customerData, $emailItems);
                    } catch (Exception $emailError) {
                        // Log silencieux
                    }

                    // Vider le panier
                    Cart::clear();
                    $success = true;
                }

            } catch (Exception $e) {
                if (isset($db)) $db->rollBack();
                $error = 'Une erreur est survenue. Veuillez réessayer.';
                error_log('Checkout error: ' . $e->getMessage());
            }
        }
    } else {
        $error = 'Session expirée. Veuillez réessayer.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $success ? 'Commande confirmée' : 'Finaliser votre commande' ?> - PERSONNALY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        body { background: var(--gray-light); min-height: 100vh; }

        /* Navbar */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar {
            background: rgba(13, 13, 13, 0.98);
            backdrop-filter: blur(10px);
            padding: 15px 0;
        }
        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .navbar-logo { height: 40px; width: auto; max-width: 150px; object-fit: contain; }
        .navbar-nav { display: flex; align-items: center; gap: 20px; }
        .navbar-nav a { color: rgba(255,255,255,0.8) !important; text-decoration: none; font-weight: 500; transition: color 0.2s; -webkit-text-fill-color: initial; background: none; }
        .navbar-nav a:hover { color: var(--pink-main) !important; }
        .cart-nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--gradient-mint) !important;
            color: var(--black) !important;
            padding: 10px 20px;
            border-radius: var(--radius-full);
            font-weight: 600;
            -webkit-text-fill-color: var(--black) !important;
        }
        .cart-nav-link svg { stroke: var(--black) !important; }
        .cart-badge {
            background: var(--pink-main);
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: var(--radius-full);
        }

        /* Checkout Page */
        .checkout-page { padding: 40px 0 80px; }
        .page-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--black-soft);
            margin-bottom: 30px;
        }
        .page-title span { color: var(--pink-main); }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 25px;
            font-weight: 500;
        }
        .alert-error {
            background: rgba(255, 105, 180, 0.15);
            color: var(--pink-dark);
            border-left: 4px solid var(--pink-main);
        }

        /* Checkout Layout */
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            align-items: start;
        }

        /* Form Section */
        .checkout-form-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 35px;
        }
        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-number {
            width: 28px;
            height: 28px;
            background: var(--gradient-pink);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
        }

        /* Form */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: var(--black-soft);
            margin-bottom: 8px;
        }
        .form-label .required { color: var(--pink-main); }
        .form-input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            font-size: 15px;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--pink-main);
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.1);
        }
        textarea.form-input {
            min-height: 100px;
            resize: vertical;
        }

        /* Order Summary */
        .order-summary {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            position: sticky;
            top: 100px;
        }
        .summary-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }

        /* Summary Items */
        .summary-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        .summary-item:last-of-type { border-bottom: none; }
        .summary-item-image {
            width: 60px;
            height: 60px;
            background: var(--gray-light);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .summary-item-details {
            flex: 1;
            min-width: 0;
        }
        .summary-item-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .summary-item-qty {
            font-size: 13px;
            color: var(--gray);
        }
        .summary-item-price {
            font-weight: 700;
            color: var(--pink-dark);
            white-space: nowrap;
        }

        /* Totals */
        .summary-totals { margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.08); }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }
        .summary-row.total {
            font-size: 1.3rem;
            font-weight: 700;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 2px solid var(--pink-light);
        }
        .summary-row.total span:last-child {
            color: var(--pink-dark);
            font-family: var(--font-display);
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            margin-top: 25px;
            padding: 18px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover { color: var(--pink-main); }

        /* Shipping Options */
        .shipping-options { display: flex; flex-direction: column; gap: 20px; }
        .shipping-group-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .shipping-group-title svg { color: var(--pink-main); }
        .shipping-option {
            display: block;
            cursor: pointer;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .shipping-option:hover { border-color: var(--pink-light); }
        .shipping-option.selected {
            border-color: var(--pink-main);
            background: rgba(255, 105, 180, 0.05);
        }
        .shipping-option input { display: none; }
        .shipping-option-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .shipping-logo {
            width: 50px;
            height: 30px;
            object-fit: contain;
        }
        .shipping-info { flex: 1; }
        .shipping-name {
            display: block;
            font-weight: 600;
            font-size: 15px;
            color: var(--black-soft);
        }
        .shipping-delay {
            display: block;
            font-size: 13px;
            color: var(--gray);
            margin-top: 2px;
        }
        .shipping-price {
            font-weight: 700;
            font-size: 15px;
            color: var(--pink-dark);
        }

        /* Relay Points */
        .relay-search { margin-bottom: 20px; }
        .relay-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 30px;
            color: var(--gray);
        }
        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid #e5e5e5;
            border-top-color: var(--pink-main);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .relay-points-list { max-height: 400px; overflow-y: auto; }
        .relay-point {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            border: 2px solid #e5e5e5;
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .relay-point:hover { border-color: var(--pink-light); background: rgba(255, 105, 180, 0.02); }
        .relay-point.selected { border-color: var(--pink-main); background: rgba(255, 105, 180, 0.05); }
        .relay-point-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-mint);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .relay-point-icon svg { stroke: var(--black); }
        .relay-point-info { flex: 1; }
        .relay-point-name { font-weight: 600; font-size: 15px; color: var(--black-soft); margin-bottom: 4px; }
        .relay-point-address { font-size: 13px; color: var(--gray); line-height: 1.4; }
        .relay-point-distance {
            font-size: 12px;
            color: var(--pink-dark);
            font-weight: 600;
            margin-top: 6px;
        }
        .relay-point-check {
            width: 24px;
            height: 24px;
            border: 2px solid #e5e5e5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .relay-point.selected .relay-point-check {
            background: var(--gradient-pink);
            border-color: var(--pink-main);
        }
        .relay-point.selected .relay-point-check svg { display: block; }
        .relay-point-check svg { display: none; stroke: white; }
        .relay-empty {
            text-align: center;
            padding: 30px;
            color: var(--gray);
        }

        /* Success Page */
        .success-page {
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
        }
        .success-box {
            background: white;
            border-radius: var(--radius-lg);
            padding: 60px 40px;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--gradient-mint);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 3rem;
        }
        .success-box h1 {
            font-size: 2rem;
            color: var(--black-soft);
            margin-bottom: 15px;
        }
        .success-box p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .order-number {
            display: inline-block;
            background: var(--gray-light);
            padding: 12px 25px;
            border-radius: var(--radius-md);
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--pink-dark);
            margin-bottom: 30px;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .checkout-layout { grid-template-columns: 1fr; }
            .order-summary { position: static; order: -1; }
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../app/templates/header.php'; ?>

    <!-- Checkout Page -->
    <section class="checkout-page">
        <div class="container">
            <?php if ($success): ?>
                <!-- Success -->
                <div class="success-page">
                    <div class="success-box">
                        <div class="success-icon">✓</div>
                        <h1>Commande confirmée !</h1>
                        <p>
                            Merci pour votre commande. Vous recevrez un email de confirmation
                            avec tous les détails de votre commande.
                        </p>
                        <div class="order-number">Commande #<?= $orderId ?></div>
                        <br>
                        <a href="/" class="btn btn-primary">Retour à l'accueil</a>
                    </div>
                </div>
            <?php else: ?>
                <h1 class="page-title">Finaliser votre <span>commande</span></h1>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="place_order" value="1">

                    <div class="checkout-layout">
                        <!-- Form -->
                        <div>
                            <!-- Contact -->
                            <div class="checkout-form-section" style="margin-bottom: 25px;">
                                <h2 class="section-title">
                                    <span class="section-number">1</span>
                                    Vos coordonnées
                                </h2>

                                <div class="form-group">
                                    <label class="form-label">Email <span class="required">*</span></label>
                                    <input type="email" name="email" class="form-input"
                                           placeholder="votre@email.com" required
                                           value="<?= h(post('email', '')) ?>">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Prénom <span class="required">*</span></label>
                                        <input type="text" name="first_name" class="form-input"
                                               placeholder="Jean" required
                                               value="<?= h(post('first_name', '')) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Nom <span class="required">*</span></label>
                                        <input type="text" name="last_name" class="form-input"
                                               placeholder="Dupont" required
                                               value="<?= h(post('last_name', '')) ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" name="phone" class="form-input"
                                           placeholder="06 12 34 56 78"
                                           value="<?= h(post('phone', '')) ?>">
                                </div>
                            </div>

                            <!-- Shipping Method Selection -->
                            <div class="checkout-form-section" style="margin-bottom: 25px;">
                                <h2 class="section-title">
                                    <span class="section-number">2</span>
                                    Mode de livraison
                                </h2>

                                <div class="shipping-options">
                                    <?php if (!empty($homeDeliveryOptions)): ?>
                                        <div class="shipping-group">
                                            <h4 class="shipping-group-title">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                                    <polyline points="9 22 9 12 15 12 15 22"/>
                                                </svg>
                                                Livraison à domicile
                                            </h4>
                                            <?php foreach ($homeDeliveryOptions as $option): ?>
                                                <label class="shipping-option <?= $shippingMethod === $option['id'] ? 'selected' : '' ?>">
                                                    <input type="radio" name="shipping_method" value="<?= h($option['id']) ?>"
                                                           <?= $shippingMethod === $option['id'] ? 'checked' : '' ?>
                                                           data-price="<?= $option['price'] ?>"
                                                           data-label="<?= h($option['label']) ?>"
                                                           data-is-relay="0">
                                                    <div class="shipping-option-content">
                                                        <?php if (!empty($option['logo'])): ?>
                                                            <img src="<?= h($option['logo']) ?>" alt="" class="shipping-logo">
                                                        <?php endif; ?>
                                                        <div class="shipping-info">
                                                            <span class="shipping-name"><?= h($option['label']) ?></span>
                                                            <?php if (!empty($option['delay'])): ?>
                                                                <span class="shipping-delay"><?= h($option['delay']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="shipping-price">
                                                            <?= $option['price'] == 0 ? 'Gratuit' : formatPrice($option['price']) ?>
                                                        </span>
                                                    </div>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($relayOptions)): ?>
                                        <div class="shipping-group">
                                            <h4 class="shipping-group-title">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                    <circle cx="12" cy="10" r="3"/>
                                                </svg>
                                                Point relais
                                            </h4>
                                            <?php foreach ($relayOptions as $option): ?>
                                                <label class="shipping-option <?= $shippingMethod === $option['id'] ? 'selected' : '' ?>">
                                                    <input type="radio" name="shipping_method" value="<?= h($option['id']) ?>"
                                                           <?= $shippingMethod === $option['id'] ? 'checked' : '' ?>
                                                           data-price="<?= $option['price'] ?>"
                                                           data-label="<?= h($option['label']) ?>"
                                                           data-is-relay="1">
                                                    <div class="shipping-option-content">
                                                        <?php if (!empty($option['logo'])): ?>
                                                            <img src="<?= h($option['logo']) ?>" alt="" class="shipping-logo">
                                                        <?php endif; ?>
                                                        <div class="shipping-info">
                                                            <span class="shipping-name"><?= h($option['label']) ?></span>
                                                            <?php if (!empty($option['delay'])): ?>
                                                                <span class="shipping-delay"><?= h($option['delay']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="shipping-price">
                                                            <?= $option['price'] == 0 ? 'Gratuit' : formatPrice($option['price']) ?>
                                                        </span>
                                                    </div>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Relay Point Selection (hidden by default) -->
                            <div class="checkout-form-section relay-point-section" id="relay-section" style="margin-bottom: 25px; display: none;">
                                <h2 class="section-title">
                                    <span class="section-number">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                            <circle cx="12" cy="10" r="3"/>
                                        </svg>
                                    </span>
                                    Choisir votre point relais
                                </h2>

                                <div class="relay-search">
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 2;">
                                            <label class="form-label">Code postal <span class="required">*</span></label>
                                            <input type="text" id="relay-postcode" class="form-input"
                                                   placeholder="75001" maxlength="5">
                                        </div>
                                        <div class="form-group" style="flex: 1; display: flex; align-items: flex-end;">
                                            <button type="button" id="search-relay-btn" class="btn btn-secondary" style="width: 100%; margin-bottom: 0;">
                                                Rechercher
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div id="relay-loading" class="relay-loading" style="display: none;">
                                    <div class="spinner"></div>
                                    <span>Recherche des points relais...</span>
                                </div>

                                <div id="relay-points-list" class="relay-points-list"></div>

                                <input type="hidden" name="relay_point_code" id="relay-point-code" value="">
                                <input type="hidden" name="relay_point_name" id="relay-point-name" value="">
                                <input type="hidden" name="relay_point_address" id="relay-point-address" value="">
                            </div>

                            <!-- Shipping Address -->
                            <div class="checkout-form-section" id="address-section">
                                <h2 class="section-title">
                                    <span class="section-number">3</span>
                                    Adresse de livraison
                                </h2>

                                <div class="form-group">
                                    <label class="form-label">Adresse <span class="required">*</span></label>
                                    <input type="text" name="address" class="form-input" id="address-input"
                                           placeholder="123 rue de la Paix" required
                                           value="<?= h(post('address', '')) ?>">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Code postal <span class="required">*</span></label>
                                        <input type="text" name="zipcode" class="form-input" id="zipcode-input"
                                               placeholder="75001" required
                                               value="<?= h(post('zipcode', '')) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Ville <span class="required">*</span></label>
                                        <input type="text" name="city" class="form-input" id="city-input"
                                               placeholder="Paris" required
                                               value="<?= h(post('city', '')) ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Instructions de livraison (optionnel)</label>
                                    <textarea name="notes" class="form-input"
                                              placeholder="Digicode, étage, etc."><?= h(post('notes', '')) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="order-summary">
                            <h2 class="summary-title">Votre commande</h2>

                            <?php foreach ($cartItems as $item): ?>
                                <div class="summary-item">
                                    <div class="summary-item-image">👕</div>
                                    <div class="summary-item-details">
                                        <div class="summary-item-name"><?= h($item['product']['name']) ?></div>
                                        <div class="summary-item-qty">
                                            <?= h($item['customization']['size'] ?? 'M') ?> •
                                            <?= ucfirst(h($item['customization']['color'] ?? 'blanc')) ?> •
                                            x<?= $item['quantity'] ?>
                                        </div>
                                    </div>
                                    <div class="summary-item-price"><?= formatPrice($item['subtotal']) ?></div>
                                </div>
                            <?php endforeach; ?>

                            <div class="summary-totals">
                                <div class="summary-row">
                                    <span>Sous-total</span>
                                    <span><?= formatPrice($cartTotal) ?></span>
                                </div>
                                <div class="summary-row" id="shipping-row">
                                    <span id="shipping-label"><?= h($shippingLabel) ?></span>
                                    <span id="shipping-price" <?= $shippingCost == 0 ? 'style="color: var(--mint-dark);"' : '' ?>>
                                        <?= $shippingCost == 0 ? 'Gratuite' : formatPrice($shippingCost) ?>
                                    </span>
                                </div>
                                <div class="summary-row relay-info" id="relay-info-row" style="display: none;">
                                    <span id="relay-info-label" style="font-size: 13px; color: var(--gray); display: flex; align-items: center; gap: 6px;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                            <circle cx="12" cy="10" r="3"/>
                                        </svg>
                                        <span id="relay-info-name"></span>
                                    </span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total</span>
                                    <span id="total-price"><?= formatPrice($cartTotal + $shippingCost) ?></span>
                                </div>
                            </div>

                            <?php if (!empty($applicableUpsells)): ?>
                                <div class="checkout-upsells">
                                    <h4 class="checkout-upsells-title">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                        </svg>
                                        Offres actives
                                    </h4>
                                    <?php foreach ($applicableUpsells as $upsell): ?>
                                        <div class="checkout-upsell-item">
                                            <div class="checkout-upsell-icon">
                                                <?php if ($upsell['offer_type'] === 'livraison_gratuite'): ?>
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="checkout-upsell-content">
                                                <div class="checkout-upsell-label"><?= h($upsell['display_title'] ?: $upsell['name']) ?></div>
                                                <?php if (!empty($upsell['offer_label'])): ?>
                                                    <div class="checkout-upsell-desc"><?= h($upsell['offer_label']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($upsell['offer_type'] === 'reduction' && $upsell['discount_value'] > 0): ?>
                                                <span class="checkout-upsell-badge">-<?= h($upsell['discount_value']) ?><?= $upsell['discount_type'] === 'pourcentage' ? '%' : '€' ?></span>
                                            <?php elseif ($upsell['offer_type'] === 'livraison_gratuite'): ?>
                                                <span class="checkout-upsell-badge">Offert</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-primary submit-btn">
                                Confirmer la commande
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>

                            <a href="/public/cart.php" class="back-link">← Modifier le panier</a>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php include __DIR__ . '/../app/templates/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cartTotal = <?= $cartTotal ?>;
        const shippingOptions = document.querySelectorAll('input[name="shipping_method"]');
        const relaySection = document.getElementById('relay-section');
        const addressSection = document.getElementById('address-section');
        const relayPostcode = document.getElementById('relay-postcode');
        const searchRelayBtn = document.getElementById('search-relay-btn');
        const relayLoading = document.getElementById('relay-loading');
        const relayPointsList = document.getElementById('relay-points-list');

        let currentCarrier = '';
        let isRelaySelected = false;

        // Shipping method selection
        shippingOptions.forEach(function(option) {
            option.addEventListener('change', function() {
                // Update selected state
                document.querySelectorAll('.shipping-option').forEach(el => el.classList.remove('selected'));
                this.closest('.shipping-option').classList.add('selected');

                const price = parseFloat(this.dataset.price);
                const label = this.dataset.label;
                const isRelay = this.dataset.isRelay === '1';
                currentCarrier = this.value;

                // Update summary
                document.getElementById('shipping-label').textContent = label;
                const priceEl = document.getElementById('shipping-price');
                if (price === 0) {
                    priceEl.textContent = 'Gratuite';
                    priceEl.style.color = 'var(--mint-dark)';
                } else {
                    priceEl.textContent = price.toFixed(2).replace('.', ',') + ' €';
                    priceEl.style.color = '';
                }

                const total = cartTotal + price;
                document.getElementById('total-price').textContent = total.toFixed(2).replace('.', ',') + ' €';

                // Show/hide relay section immediately based on data attribute
                if (isRelay) {
                    relaySection.style.display = 'block';
                    isRelaySelected = true;
                    // Clear previous selection
                    relayPointsList.innerHTML = '';
                    document.getElementById('relay-point-code').value = '';
                    document.getElementById('relay-point-name').value = '';
                    document.getElementById('relay-point-address').value = '';
                    // Hide relay info in summary (will be shown when user selects a point)
                    document.getElementById('relay-info-row').style.display = 'none';
                    document.getElementById('relay-info-name').textContent = '';
                    // Pre-fill postcode from address if available
                    const zipInput = document.getElementById('zipcode-input');
                    if (zipInput && zipInput.value) {
                        relayPostcode.value = zipInput.value;
                    }
                } else {
                    relaySection.style.display = 'none';
                    isRelaySelected = false;
                    // Hide relay info in summary
                    document.getElementById('relay-info-row').style.display = 'none';
                    document.getElementById('relay-info-name').textContent = '';
                }

                // Save to session
                fetch('/public/checkout.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'ajax_shipping=1&shipping_method=' + encodeURIComponent(this.value)
                });
            });
        });

        // Search relay points
        if (searchRelayBtn) {
            searchRelayBtn.addEventListener('click', function() {
                const postcode = relayPostcode.value.trim();
                if (!postcode || postcode.length < 5) {
                    alert('Veuillez entrer un code postal valide');
                    return;
                }

                // Get carrier from currently selected shipping option (more reliable)
                const selectedShipping = document.querySelector('input[name="shipping_method"]:checked');
                const carrier = selectedShipping ? selectedShipping.value : currentCarrier;

                console.log('Search relay: carrier=' + carrier + ', postcode=' + postcode);

                if (!carrier) {
                    alert('Veuillez sélectionner un mode de livraison');
                    return;
                }

                relayLoading.style.display = 'flex';
                relayPointsList.innerHTML = '';

                fetch('/public/checkout.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'ajax_relay_points=1&carrier=' + encodeURIComponent(carrier) + '&postcode=' + encodeURIComponent(postcode)
                })
                .then(r => r.json())
                .then(data => {
                    relayLoading.style.display = 'none';
                    console.log('Relay points response:', data);
                    if (data.success && data.points && data.points.length > 0) {
                        renderRelayPoints(data.points);
                    } else {
                        relayPointsList.innerHTML = '<div class="relay-empty">Aucun point relais trouvé pour ce code postal. Essayez un autre code postal.<br><small style="color:#999;">Debug: carrier=' + carrier + ', postcode=' + postcode + '</small></div>';
                    }
                })
                .catch(err => {
                    relayLoading.style.display = 'none';
                    relayPointsList.innerHTML = '<div class="relay-empty">Erreur lors de la recherche. Veuillez réessayer.</div>';
                });
            });
        }

        // Render relay points
        function renderRelayPoints(points) {
            relayPointsList.innerHTML = points.map(point => `
                <div class="relay-point" data-code="${point.code}" data-name="${escapeHtml(point.name)}" data-address="${escapeHtml(point.formatted_address)}">
                    <div class="relay-point-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                    </div>
                    <div class="relay-point-info">
                        <div class="relay-point-name">${escapeHtml(point.name)}</div>
                        <div class="relay-point-address">${escapeHtml(point.address)}<br>${point.postcode} ${escapeHtml(point.city)}</div>
                        ${point.distance ? `<div class="relay-point-distance">À ${point.distance.toFixed(1)} km</div>` : ''}
                    </div>
                    <div class="relay-point-check">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                </div>
            `).join('');

            // Add click handlers
            document.querySelectorAll('.relay-point').forEach(el => {
                el.addEventListener('click', function() {
                    document.querySelectorAll('.relay-point').forEach(p => p.classList.remove('selected'));
                    this.classList.add('selected');

                    const code = this.dataset.code;
                    const name = this.dataset.name;
                    const address = this.dataset.address;

                    document.getElementById('relay-point-code').value = code;
                    document.getElementById('relay-point-name').value = name;
                    document.getElementById('relay-point-address').value = address;

                    // Update summary with relay point name
                    document.getElementById('relay-info-name').textContent = name;
                    document.getElementById('relay-info-row').style.display = 'flex';

                    // Save to session
                    fetch('/public/checkout.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'ajax_select_relay=1&relay_code=' + encodeURIComponent(code) + '&relay_name=' + encodeURIComponent(name) + '&relay_address=' + encodeURIComponent(address)
                    });
                });
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Check initial state for relay
        const checkedOption = document.querySelector('input[name="shipping_method"]:checked');
        if (checkedOption) {
            currentCarrier = checkedOption.value;
            // Use data-is-relay attribute for reliable detection
            if (checkedOption.dataset.isRelay === '1') {
                relaySection.style.display = 'block';
                isRelaySelected = true;
            }
        }
    });
    </script>
</body>
</html>
