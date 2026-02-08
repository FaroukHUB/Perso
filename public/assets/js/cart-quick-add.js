/**
 * PERSONNALY - Ajout rapide au panier
 * Gère les boutons "Ajouter au panier" sur les cartes produits
 */

document.addEventListener('DOMContentLoaded', function() {
    // Gérer tous les boutons "Ajouter au panier"
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');

    addToCartButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const productPrice = this.dataset.productPrice;

            // Désactiver le bouton pendant l'ajout
            const originalHTML = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Ajout...';

            // Requête AJAX pour ajouter au panier
            fetch('/public/api/cart-add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    product_name: productName,
                    product_price: productPrice,
                    quantity: 1,
                    direct_purchase: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animation de succès
                    this.classList.add('added');
                    this.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Ajouté !';

                    // Mettre à jour le compteur du panier
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        cartBadge.textContent = data.cart_count || (parseInt(cartBadge.textContent || 0) + 1);
                        cartBadge.style.display = 'flex';
                    }

                    // Rediriger vers le panier après 1 seconde
                    setTimeout(() => {
                        window.location.href = '/public/cart.php';
                    }, 1000);
                } else {
                    throw new Error(data.message || 'Erreur lors de l\'ajout au panier');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                this.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> Erreur';
                this.disabled = false;

                // Réinitialiser après 2 secondes
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.classList.remove('added');
                }, 2000);
            });
        });
    });
});
