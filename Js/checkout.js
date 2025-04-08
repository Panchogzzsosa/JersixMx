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
                ${item.tipo ? `<div class="cart-item-tipo" style="border-radius: 8px;">Tipo: ${item.tipo === 'champions' ? 'Champions' : item.tipo === 'ligamx' ? 'LigaMX' : 'Liga Europea'}</div>` : ''}
                ${item.personalization ? `
                <div class="cart-item-personalization" style="margin-top: 8px; padding: 8px; background-color: #f8f9fa; border-radius: 8px;">
                    <div style="font-weight: 500; margin-bottom: 4px;">Personalización:</div>
                    ${item.personalization.name ? `<div>Nombre: ${item.personalization.name}</div>` : ''}
                    ${item.personalization.number ? `<div>Número: ${item.personalization.number}</div>` : ''}
                    ${item.personalization.patch ? `<div>Parche: Sí</div>` : ''}
                </div>
                ` : ''}
                ${item.isGiftCard && item.details ? `
                <div class="cart-item-personalization" style="margin-top: 8px; padding: 8px; background-color: #f8f9fa; border-radius: 8px;">
                    <div style="font-weight: 500; margin-bottom: 4px;">Detalles de la Gift Card:</div>
                    <div>Para: ${item.details.recipientName}</div>
                    <div>Email: ${item.details.recipientEmail}</div>
                    ${item.details.message ? `<div>Mensaje: ${item.details.message}</div>` : ''}
                    <div>De: ${item.details.senderName}</div>
                </div>
                ` : ''}
            </div>
        `;

        cartSummary.appendChild(cartItem);
        subtotal += item.price * item.quantity;
    });

    // Update totals
    const shipping = 0; // Fixed shipping cost
    const total = subtotal + shipping;
    
    // Almacenar el total original para referencia
    window.originalTotal = total;

    document.getElementById('subtotal-amount').textContent = `$${subtotal.toFixed(2)} MXN`;
    document.getElementById('shipping-amount').textContent = `$${shipping.toFixed(2)} MXN`;
    document.getElementById('total-amount').textContent = `$${total.toFixed(2)} MXN`;
    
    // Crear un elemento oculto para almacenar el total original
    const originalTotalEl = document.createElement('div');
    originalTotalEl.className = 'original-total';
    originalTotalEl.style.display = 'none';
    originalTotalEl.textContent = `$${total.toFixed(2)}`;
    document.body.appendChild(originalTotalEl);

    // Función para calcular el total final considerando GiftCard
    function calculateFinalTotal() {
        let finalTotal = window.originalTotal || total;
        let fullDiscount = false;
        
        // Verificar si hay una Gift Card aplicada (desde la variable global)
        if (window.giftcardDiscount && window.giftcardDiscount.amount > 0) {
            const discountAmount = parseFloat(window.giftcardDiscount.amount);
            
            // Verificar si el descuento cubre completamente el total
            if (discountAmount >= finalTotal) {
                fullDiscount = true;
                // Limitar el descuento para dejar al menos 0.01 para PayPal
                window.giftcardDiscount.actualAmount = finalTotal - 0.01;
                finalTotal = 0.01;
            } else {
                window.giftcardDiscount.actualAmount = discountAmount;
                finalTotal = finalTotal - discountAmount;
            }
            
            console.log('Descuento aplicado de Gift Card:', window.giftcardDiscount.actualAmount, 'Total final:', finalTotal, 'Descuento completo:', fullDiscount);
        } else {
            // Alternativamente, verificar los campos ocultos del formulario
            const giftcardAmountField = document.querySelector('input[name="giftcard_amount"]');
            if (giftcardAmountField && !isNaN(parseFloat(giftcardAmountField.value))) {
                const discountAmount = parseFloat(giftcardAmountField.value);
                
                // Verificar si el descuento cubre completamente el total
                if (discountAmount >= finalTotal) {
                    fullDiscount = true;
                    // Crear objeto global si no existe
                    window.giftcardDiscount = window.giftcardDiscount || {};
                    window.giftcardDiscount.actualAmount = finalTotal - 0.01;
                    // Actualizar el campo oculto con el valor ajustado
                    giftcardAmountField.value = window.giftcardDiscount.actualAmount.toFixed(2);
                    finalTotal = 0.01;
                } else {
                    window.giftcardDiscount = window.giftcardDiscount || {};
                    window.giftcardDiscount.actualAmount = discountAmount;
                    finalTotal = finalTotal - discountAmount;
                }
                
                console.log('Descuento aplicado desde campo oculto:', window.giftcardDiscount.actualAmount, 'Total final:', finalTotal, 'Descuento completo:', fullDiscount);
            }
        }
        
        // Almacenar si es un descuento completo para usarlo después
        window.isFullDiscount = fullDiscount;
        
        return finalTotal;
    }
    
    // Escuchar evento de Gift Card aplicada
    document.addEventListener('giftcard:applied', function(e) {
        console.log('Evento giftcard:applied recibido', e.detail);
    });

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

            // Calcular el valor final considerando Gift Card
            const finalTotal = calculateFinalTotal();
            
            console.log('Creando orden de PayPal con total:', finalTotal);

            return actions.order.create({
                purchase_units: [{
                    amount: {
                        value: finalTotal.toFixed(2)
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

                // Incluir información de Gift Card si está presente (desde variable global o campos ocultos)
                if (window.giftcardDiscount && window.giftcardDiscount.code) {
                    // Usar el monto ajustado si hubo un descuento completo
                    const amountToUse = window.isFullDiscount ? 
                        window.giftcardDiscount.actualAmount : 
                        window.giftcardDiscount.amount;
                    
                    formData.append('giftcard_code', window.giftcardDiscount.code);
                    formData.append('giftcard_amount', amountToUse.toFixed(2));
                    
                    // Si es un descuento completo, indicarlo
                    if (window.isFullDiscount) {
                        formData.append('is_full_discount', 'true');
                    }
                    
                    console.log('Aplicando Gift Card desde variable global:', window.giftcardDiscount.code, 
                                'por $' + amountToUse.toFixed(2), 
                                window.isFullDiscount ? '(Descuento completo)' : '');
                } else {
                    // Buscar en campos ocultos
                    const giftcardCode = document.querySelector('input[name="giftcard_code"]');
                    const giftcardAmount = document.querySelector('input[name="giftcard_amount"]');

                    if (giftcardCode && giftcardAmount) {
                        // El valor de giftcardAmount ya debería estar ajustado en calculateFinalTotal
                        formData.append('giftcard_code', giftcardCode.value);
                        formData.append('giftcard_amount', giftcardAmount.value);
                        
                        // Si es un descuento completo, indicarlo
                        if (window.isFullDiscount) {
                            formData.append('is_full_discount', 'true');
                        }
                        
                        console.log('Aplicando Gift Card desde campos ocultos:', giftcardCode.value, 
                                    'por $' + giftcardAmount.value, 
                                    window.isFullDiscount ? '(Descuento completo)' : '');
                    }
                }

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
                        // No mostrar alerta, solo redirigir directamente
                        
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