/**
 * PERSONNALY - Ajout rapide au panier
 * Gère les boutons "Ajouter au panier" sur les cartes produits
 */

document.addEventListener('DOMContentLoaded', function() {
    // Gérer tous les boutons "Ajouter au panier"
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');

    addToCartButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            const button = this;
            const productId = button.dataset.productId;
            const productName = button.dataset.productName;
            const productPrice = button.dataset.productPrice;

            // Validation
            if (!productId) {
                alert('Erreur: ID produit manquant');
                return;
            }

            // Désactiver le bouton pendant l'ajout
            const originalHTML = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Ajout...';

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
            .then(response => {
                // Vérifier le statut HTTP
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`Erreur HTTP ${response.status}: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Animation de succès
                    button.classList.add('added');
                    button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Ajouté !';

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
                console.error('Erreur détaillée:', error);
                button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> Erreur';
                button.disabled = false;

                // Afficher l'erreur en console pour debug
                alert('Impossible d\'ajouter au panier. Vérifiez la console (F12) pour plus de détails.');

                // Réinitialiser après 3 secondes
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('added');
                }, 3000);
            });
        });
    });
});
