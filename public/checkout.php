<?php
/**
 * PERSONNALY - Page Checkout (Commande)
 * Design: Ultra-moderne • Girly • Rose + Vert Menthe + Noir
 */

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/Cart.php';
require_once __DIR__ . '/../app/helpers/Email.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Order.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Upsell.php';

// Panier vide = retour accueil
if (Cart::isEmpty()) {
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

$success = false;
$orderId = null;
$error = '';

// Traitement de la commande
if (isPost() && isset($_POST['place_order'])) {
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

                // Adresse de livraison formatée
                $shippingAddress = json_encode([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'address' => $address,
                    'city' => $city,
                    'zipcode' => $zipcode,
                    'phone' => $phone,
                ], JSON_UNESCAPED_UNICODE);

                // Créer la commande
                $orderModel = new Order();
                $stmt = $db->prepare(
                    'INSERT INTO orders (user_id, total, status, shipping_address, notes, created_at)
                     VALUES (?, ?, "pending", ?, ?, NOW())'
                );
                $stmt->execute([$userId, $cartTotal, $shippingAddress, $notes]);
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

                // Préparer les données pour les emails
                $orderData = [
                    'id' => $orderId,
                    'total' => $cartTotal,
                    'shipping_address' => $shippingAddress,
                ];
                $customerData = [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                ];

                // Récupérer les items avec noms produits pour emails
                $emailItems = [];
                foreach ($cartItems as $item) {
                    $emailItems[] = [
                        'product_name' => $item['product']['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'data_json' => $item['customization'],
                    ];
                }

                // Envoyer emails (ne bloque pas si échec)
                try {
                    Email::sendOrderConfirmation($orderData, $customerData, $emailItems);
                    Email::sendAdminNewOrder($orderData, $customerData, $emailItems);
                } catch (Exception $emailError) {
                    // Log silencieux, ne pas bloquer la commande
                }

                // Vider le panier
                Cart::clear();
                $success = true;

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Une erreur est survenue. Veuillez réessayer.';
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
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
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

        /* Upsells in Checkout */
        .checkout-upsells {
            background: linear-gradient(135deg, rgba(255, 105, 180, 0.08) 0%, rgba(61, 255, 192, 0.08) 100%);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-top: 20px;
        }
        .checkout-upsells-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 700;
            color: var(--black-soft);
            margin-bottom: 15px;
        }
        .checkout-upsells-title svg { color: var(--pink-main); }
        .checkout-upsell-item {
            background: white;
            border-radius: var(--radius-md);
            padding: 12px 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .checkout-upsell-item:last-child { margin-bottom: 0; }
        .checkout-upsell-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-mint);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .checkout-upsell-content {
            flex: 1;
            min-width: 0;
        }
        .checkout-upsell-label {
            font-weight: 600;
            font-size: 13px;
            color: var(--black-soft);
        }
        .checkout-upsell-desc {
            font-size: 12px;
            color: var(--gray);
        }
        .checkout-upsell-badge {
            padding: 4px 10px;
            background: var(--gradient-mint);
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 700;
            color: var(--black-soft);
            white-space: nowrap;
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
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="/" class="navbar-brand">PERSONNALY</a>
        </div>
    </nav>

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

                            <!-- Shipping -->
                            <div class="checkout-form-section">
                                <h2 class="section-title">
                                    <span class="section-number">2</span>
                                    Adresse de livraison
                                </h2>

                                <div class="form-group">
                                    <label class="form-label">Adresse <span class="required">*</span></label>
                                    <input type="text" name="address" class="form-input"
                                           placeholder="123 rue de la Paix" required
                                           value="<?= h(post('address', '')) ?>">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Code postal <span class="required">*</span></label>
                                        <input type="text" name="zipcode" class="form-input"
                                               placeholder="75001" required
                                               value="<?= h(post('zipcode', '')) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Ville <span class="required">*</span></label>
                                        <input type="text" name="city" class="form-input"
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
                                <div class="summary-row">
                                    <span>Livraison</span>
                                    <span style="color: var(--mint-dark);">Gratuite</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total</span>
                                    <span><?= formatPrice($cartTotal) ?></span>
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
</body>
</html>
