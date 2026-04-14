<?php
/**
 * PERSONNALY - Header Navigation
 * Inclut le menu avec catégories dynamiques et hamburger mobile
 */

// S'assurer que les dépendances sont chargées
if (!class_exists('Category')) {
    require_once __DIR__ . '/../../app/models/Category.php';
}
if (!class_exists('Cart')) {
    require_once __DIR__ . '/../../app/helpers/Cart.php';
}

// Charger les catégories si pas déjà fait
if (!isset($categories)) {
    $categoryModel = new Category();
    $categories = $categoryModel->findAllActive();
}

// Compter le panier si pas déjà fait
if (!isset($cartCount)) {
    $cartCount = Cart::count();
}

// Page courante pour activer le bon lien
$currentPage = basename($_SERVER['PHP_SELF']);
$currentSlug = $_GET['slug'] ?? '';
?>
<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="container">
        <a href="/" class="navbar-brand">PERSONNALY</a>

        <!-- Hamburger Button (Mobile) -->
        <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

        <!-- Navigation Links -->
        <div class="navbar-nav" id="navbarNav">
            <a href="/" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Accueil</a>

            <!-- Categories Dropdown -->
            <div class="nav-dropdown">
                <button class="nav-dropdown-toggle">
                    Catégories
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>
                <div class="nav-dropdown-menu">
                    <?php foreach ($categories as $cat): ?>
                        <a href="/category.php?slug=<?= htmlspecialchars($cat['slug']) ?>"
                           class="<?= $currentSlug === $cat['slug'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <span class="dropdown-empty">Aucune catégorie</span>
                    <?php endif; ?>
                </div>
            </div>

            <a href="/products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">Tous les produits</a>
            <a href="/blog.php" class="<?= $currentPage === 'blog.php' ? 'active' : '' ?>">Blog</a>
            <a href="/contact.php" class="<?= $currentPage === 'contact.php' ? 'active' : '' ?>">Contact</a>

            <a href="/cart.php" class="cart-nav-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                Panier
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</nav>

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>

<style>
/* ===== NAVBAR ===== */
.navbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
    background: rgba(13, 13, 13, 0.95); backdrop-filter: blur(10px); padding: 15px 0;
}
.navbar .container { display: flex; align-items: center; justify-content: space-between; }
.navbar-brand {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; text-decoration: none;
    background: var(--gradient-hero); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.navbar-nav { display: flex; align-items: center; gap: 30px; }
.navbar-nav > a, .nav-dropdown-toggle {
    color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500;
    transition: color 0.2s; background: none; border: none; cursor: pointer;
    font-size: 1rem; font-family: inherit; display: flex; align-items: center; gap: 5px;
}
.navbar-nav > a:hover, .navbar-nav > a.active, .nav-dropdown-toggle:hover { color: var(--pink-main); }
.cart-nav-link {
    display: flex; align-items: center; gap: 8px; background: var(--gradient-mint);
    color: var(--black) !important; padding: 10px 18px; border-radius: 50px; font-weight: 600;
    transition: transform 0.2s, box-shadow 0.2s;
}
.cart-nav-link:hover { transform: scale(1.05); box-shadow: 0 4px 20px rgba(61,255,192,0.4); }
.cart-badge { background: var(--pink-main); color: white; font-size: 11px; padding: 2px 8px; border-radius: 50px; }

/* ===== CATEGORIES DROPDOWN ===== */
.nav-dropdown { position: relative; }
.nav-dropdown-menu {
    position: absolute; top: 100%; left: 50%; transform: translateX(-50%);
    background: var(--black); border: 1px solid rgba(255,255,255,0.1);
    border-radius: var(--radius-md); min-width: 200px; padding: 10px 0;
    opacity: 0; visibility: hidden; transition: opacity 0.2s, visibility 0.2s;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5); margin-top: 10px;
}
.nav-dropdown:hover .nav-dropdown-menu { opacity: 1; visibility: visible; }
.nav-dropdown-menu a {
    display: block; padding: 10px 20px; color: rgba(255,255,255,0.8);
    text-decoration: none; transition: background 0.2s, color 0.2s;
}
.nav-dropdown-menu a:hover, .nav-dropdown-menu a.active {
    background: rgba(255,105,180,0.1); color: var(--pink-main);
}
.dropdown-empty { display: block; padding: 10px 20px; color: rgba(255,255,255,0.5); font-style: italic; }

/* ===== HAMBURGER BUTTON ===== */
.hamburger {
    display: none; flex-direction: column; gap: 5px; background: none; border: none;
    cursor: pointer; padding: 10px; z-index: 1001;
}
.hamburger-line {
    width: 25px; height: 3px; background: var(--white); border-radius: 3px;
    transition: transform 0.3s, opacity 0.3s;
}
.hamburger.active .hamburger-line:nth-child(1) { transform: rotate(45deg) translate(5px, 6px); }
.hamburger.active .hamburger-line:nth-child(2) { opacity: 0; }
.hamburger.active .hamburger-line:nth-child(3) { transform: rotate(-45deg) translate(6px, -7px); }

/* ===== MOBILE MENU OVERLAY ===== */
.mobile-menu-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.8); z-index: 999; opacity: 0; transition: opacity 0.3s;
}
.mobile-menu-overlay.active { display: block; opacity: 1; }

/* ===== RESPONSIVE MOBILE ===== */
@media (max-width: 900px) {
    .hamburger { display: flex; }

    .navbar-nav {
        position: fixed; top: 0; right: -100%; width: 80%; max-width: 350px;
        height: 100vh; background: var(--black); flex-direction: column;
        align-items: flex-start; padding: 80px 30px 30px; gap: 0;
        transition: right 0.3s ease; z-index: 1000; overflow-y: auto;
    }
    .navbar-nav.active { right: 0; }

    .navbar-nav > a, .nav-dropdown-toggle {
        width: 100%; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.1);
        font-size: 1.1rem;
    }

    .nav-dropdown { width: 100%; }
    .nav-dropdown-menu {
        position: static; transform: none; opacity: 1; visibility: visible;
        background: rgba(255,255,255,0.05); margin: 0; padding: 0;
        max-height: 0; overflow: hidden; transition: max-height 0.3s;
        border: none; box-shadow: none; border-radius: 0;
    }
    .nav-dropdown.open .nav-dropdown-menu { max-height: 500px; }
    .nav-dropdown-menu a { padding: 12px 20px; font-size: 0.95rem; }

    .cart-nav-link {
        margin-top: 20px; justify-content: center; width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('hamburgerBtn');
    const nav = document.getElementById('navbarNav');
    const overlay = document.getElementById('mobileMenuOverlay');
    const dropdowns = document.querySelectorAll('.nav-dropdown');

    // Toggle mobile menu
    hamburger.addEventListener('click', function() {
        hamburger.classList.toggle('active');
        nav.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = nav.classList.contains('active') ? 'hidden' : '';
    });

    // Close menu when clicking overlay
    overlay.addEventListener('click', function() {
        hamburger.classList.remove('active');
        nav.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    });

    // Mobile dropdown toggle
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.nav-dropdown-toggle');
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth <= 900) {
                e.preventDefault();
                dropdown.classList.toggle('open');
            }
        });
    });

    // Close menu on window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 900) {
            hamburger.classList.remove('active');
            nav.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});
</script>
