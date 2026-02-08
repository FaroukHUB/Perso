/**
 * PERSONNALY - Ajout rapide au panier - VERSION DEBUG
 */

console.log('[CART] Script chargé');

document.addEventListener('DOMContentLoaded', function() {
    console.log('[CART] DOM ready');

    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
    console.log('[CART] Boutons trouvés:', addToCartButtons.length);

    addToCartButtons.forEach((btn, index) => {
        console.log('[CART] Ajout listener sur bouton', index);

        btn.addEventListener('click', function(e) {
            console.log('[CART] CLIC détecté sur bouton', index);
            e.preventDefault();
            e.stopPropagation();

            const button = this;
            const productId = button.dataset.productId;

            console.log('[CART] Product ID:', productId);

            if (!productId) {
                console.error('[CART] Pas d\'ID produit !');
                return;
            }

            const originalHTML = button.innerHTML;
            button.disabled = true;
            button.textContent = 'Chargement...';

            console.log('[CART] Envoi requête API...');

            fetch('/public/api/cart-add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    product_name: button.dataset.productName,
                    product_price: button.dataset.productPrice,
                    quantity: 1,
                    direct_purchase: true
                })
            })
            .then(response => {
                console.log('[CART] Réponse reçue, status:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('[CART] Data:', data);
                if (data.success) {
                    button.textContent = 'Ajouté !';
                    console.log('[CART] Succès ! Redirection dans 1s...');
                    setTimeout(() => {
                        window.location.href = '/public/cart.php';
                    }, 1000);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('[CART] ERREUR:', error);
                button.textContent = 'Erreur';
                button.disabled = false;
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                }, 2000);
            });
        });
    });
});
