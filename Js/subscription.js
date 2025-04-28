// Subscription handler
document.addEventListener('DOMContentLoaded', () => {
    // Initialize cart - this will automatically create modal and overlay
    const cart = new ShoppingCart();

    // Handle subscription button clicks
    document.querySelectorAll('.subscribe-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            const planCard = e.target.closest('.plan-card');
            const planTitle = planCard.querySelector('h2').textContent;
            const planDuration = planCard.querySelector('.plan-duration').textContent;
            const planPrice = parseFloat(planCard.querySelector('.plan-price').textContent
                .replace('$', '')
                .replace(',', '')
                .replace('MXN/mes', '')
                .trim());

            // Create subscription item
            const subscriptionItem = {
                id: Date.now().toString(),
                title: `${planTitle} - ${planDuration}`,
                price: planPrice,
                quantity: 1,
                image: 'img/MysteryBox.webp',
                tipo: 'subscription'
            };

            // Add to cart
            cart.cart.push(subscriptionItem);
            cart.saveCart();
            cart.updateCartIcon();
            cart.updateCartModal();
            cart.showNotification('Suscripción agregada al carrito', 'success');
            cart.openCart(); // Open cart modal after adding item
        });
    });
});