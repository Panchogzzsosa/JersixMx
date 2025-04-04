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
                <div class="cart-item-price" style="border-radius: 8px;">$${item.price.toFixed(2)} MXN</div>
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
            // Validar el formulario antes de crear la orden
            const form = document.getElementById('contact-form');
            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }

            // Verificar si hay items en el carrito
            if (!cart || cart.length === 0) {
                alert('El carrito está vacío');
                return false;
            }

            return actions.order.create({
                purchase_units: [{
                    amount: {
                        value: total.toFixed(2)
                    }
                }]
            });
        },

        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                console.log('Pago completado', details);

                // Obtener el carrito del localStorage
                const cart = JSON.parse(localStorage.getItem('cart')) || [];
                console.log('Carrito a enviar:', cart);

                if (!cart || cart.length === 0) {
                    console.error('El carrito está vacío');
                    alert('Error: El carrito está vacío');
                    return;
                }

                // Crear FormData con los datos del formulario y carrito
                const formData = new FormData();
                const formFields = {
                    'fullname': document.getElementById('fullname').value,
                    'email': document.getElementById('email').value,
                    'phone': document.getElementById('phone').value,
                    'street': document.getElementById('street').value,
                    'colonia': document.getElementById('colonia').value,
                    'city': document.getElementById('city').value,
                    'state': document.getElementById('state').value,
                    'postal': document.getElementById('postal').value,
                    'payment_id': details.id
                };

                // Verificar que todos los campos necesarios estén presentes
                for (const [key, value] of Object.entries(formFields)) {
                    if (!value) {
                        console.error(`Campo faltante: ${key}`);
                        alert(`Error: El campo ${key} es requerido`);
                        return;
                    }
                    formData.append(key, value);
                }

                // Agregar el carrito
                formData.append('cart_items', JSON.stringify(cart));

                // Mostrar mensaje de procesamiento
                const processingMessage = document.createElement('div');
                processingMessage.className = 'processing-message';
                processingMessage.textContent = 'Procesando su orden...';
                document.body.appendChild(processingMessage);

                // Enviar datos al servidor
                return fetch('process_order.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Respuesta del servidor:', data);
                    
                    if (data.success) {
                        // Mostrar mensaje de éxito con el ID de la orden
                        const successMessage = `¡Pago completado! Su número de orden es: ${data.order_id}\n\nPuede ver los detalles de su orden en el panel de administración.`;
                        alert(successMessage);
                        
                        // Limpiar carrito
                        localStorage.removeItem('cart');
                        
                        // Redirigir a página de éxito con el ID de la orden
                        window.location.href = `success.html?order_id=${data.order_id}`;
                    } else {
                        throw new Error(data.message || 'Error al procesar la orden');
                    }
                })
                .catch(error => {
                    console.error('Error completo:', error);
                    alert('Error al procesar la orden: ' + error.message);
                })
                .finally(() => {
                    // Remover mensaje de procesamiento
                    if (processingMessage) {
                        processingMessage.remove();
                    }
                });
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