/**
 * Ajout rapide au panier - Version simple et stable
 */
(function() {
    'use strict';

    function initCartButtons() {
        // Trouver tous les boutons
        const buttons = document.querySelectorAll('.add-to-cart-btn');

        if (buttons.length === 0) return;

        buttons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                const btn = this;
                const productId = btn.dataset.productId;

                if (!productId) return;

                // Désactiver
                btn.disabled = true;
                btn.textContent = 'Ajout...';

                // Appel API
                fetch('/public/api/cart-add.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        product_id: productId,
                        product_name: btn.dataset.productName || '',
                        product_price: btn.dataset.productPrice || 0,
                        quantity: 1,
                        direct_purchase: true
                    })
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        btn.textContent = 'Ajouté !';
                        setTimeout(function() {
                            window.location.href = '/public/cart.php';
                        }, 800);
                    } else {
                        throw new Error(data.message || 'Erreur');
                    }
                })
                .catch(function(error) {
                    console.error('Erreur cart:', error);
                    btn.textContent = 'Erreur';
                    btn.disabled = false;
                    setTimeout(function() {
                        btn.textContent = 'Ajouter au panier';
                    }, 2000);
                });
            });
        });
    }

    // Init au chargement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCartButtons);
    } else {
        initCartButtons();
    }
})();
