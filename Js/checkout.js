document.addEventListener('DOMContentLoaded', function() {
    // Get cart items from localStorage
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const cartSummary = document.getElementById('cart-summary');
    let subtotal = 0;

    // Clear existing content
    cartSummary.innerHTML = '';

    // Display each cart item
    cart.forEach(item => {
        const cartItem = document.createElement('div');
        cartItem.className = 'cart-item';

        cartItem.innerHTML = `
            <div class="cart-item-image" style="width: 120px; height: 120px; overflow: hidden; border-radius: 12px;">
                <img src="${item.image}" alt="${item.title}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 12px;">
            </div>
            <div class="cart-item-details" style="border-radius: 10px;">
                <div class="cart-item-name" style="border-radius: 8px;">${item.title}</div>
    
                <div class="cart-item-quantity" style="border-radius: 8px;">Cantidad: ${item.quantity}</div>
                ${item.size ? `<div class="cart-item-size" style="border-radius: 8px;">Talla: ${item.size}</div>` : ''}
            </div>
        `;

        cartSummary.appendChild(cartItem);
        subtotal += item.price * item.quantity;
    });

    // Update totals
    const shipping = 0; // Fixed shipping cost
    const total = subtotal + shipping;

    document.getElementById('subtotal-amount').textContent = `$${subtotal.toFixed(2)} MXN`;
    document.getElementById('shipping-amount').textContent = `$${shipping.toFixed(2)} MXN`;
    document.getElementById('total-amount').textContent = `$${total.toFixed(2)} MXN`;

    // Initialize PayPal button
    paypal.Buttons({
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{
                    amount: {
                        currency_code: 'MXN',
                        value: total.toFixed(2) // Remove the division by 20 to keep original MXN price
                    }
                }]
            });
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                // Save order details
                const orderData = {
                    email: document.getElementById('email').value,
                    phone: document.getElementById('phone').value,
                    fullname: document.getElementById('fullname').value,
                    street: document.getElementById('street').value,
                    colonia: document.getElementById('colonia').value,
                    city: document.getElementById('city').value,
                    state: document.getElementById('state').value,
                    postal: document.getElementById('postal').value,
                    items: cart,
                    total: total,
                    paymentDetails: details
                };

                // Clear cart
                localStorage.removeItem('cart');

                // Redirect to success page
                window.location.href = 'success.php';
            });
        }
    }).render('#paypal-button-container');

    // Style PayPal buttons to be smaller and lower
    const paypalContainer = document.getElementById('paypal-button-container');
    if (paypalContainer) {
        paypalContainer.style.marginTop = '2rem';
        paypalContainer.style.transform = 'scale(0.8)';
        paypalContainer.style.transformOrigin = 'top center';
        paypalContainer.style.borderRadius = '12px';
        paypalContainer.style.overflow = 'hidden';
    }
});