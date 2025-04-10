document.addEventListener('DOMContentLoaded', function() {
    // Inicializar variables globales relacionadas con giftcard
    window.giftcardDiscount = null;
    window.isFullDiscount = false;
    
    // Asegurarnos de que cualquier campo oculto previo de giftcard se elimine
    setTimeout(() => {
        ['giftcard_code', 'giftcard_amount', 'is_full_discount'].forEach(name => {
            const elements = document.querySelectorAll(`input[name="${name}"]`);
            if (elements.length > 0) {
                console.log(`Limpieza inicial: Eliminando ${elements.length} elementos ${name}`);
                elements.forEach(el => el.remove());
            }
        });
    }, 500);
    
    // Get cart items from localStorage
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const cartSummary = document.getElementById('cart-summary');
    let subtotal = 0;

    // Clear existing content
    cartSummary.innerHTML = '';

    // Display each cart item
    cart.forEach(item => {
        // Determinar el precio real para mostrar y calcular el subtotal
        let displayPrice = item.price;
        
        // Si es una gift card, usar realPrice si está disponible
        if (item.isGiftCard) {
            if (item.realPrice && item.realPrice > 0) {
                displayPrice = item.realPrice;
            } else if (item.price === 0 && item.title) {
                // Intentar extraer el precio del título (ej: "Tarjeta de Regalo JerSix $1000 MXN")
                const priceMatch = item.title.match(/\$(\d+)/);
                if (priceMatch && priceMatch[1]) {
                    displayPrice = parseFloat(priceMatch[1]);
                }
            }
        }
        
        const cartItem = document.createElement('div');
        cartItem.className = 'cart-item';

        cartItem.innerHTML = `
            <div class="cart-item-image" style="width: 120px; height: 120px; overflow: hidden; border-radius: 12px;">
                <img src="${item.image}" alt="${item.title}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 12px;">
            </div>
            <div class="cart-item-details" style="border-radius: 10px;">
                <div class="cart-item-name" style="border-radius: 8px;">${item.title}</div>
                <div class="cart-item-price" style="border-radius: 8px;">$${displayPrice.toFixed(2)} MXN</div>
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
        subtotal += displayPrice * item.quantity;
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
        
        // Verificar si hay una instancia de GiftCardHandler y si tiene un descuento aplicado
        const giftCardHandlerActive = window.giftCardHandler && window.giftCardHandler.discountApplied;
        
        // Si el handler indica que no hay descuento aplicado, devolver el total original
        if (window.giftCardHandler && !window.giftCardHandler.discountApplied) {
            console.log('GiftCardHandler indica que no hay descuento aplicado');
            return finalTotal;
        }
        
        // Verificar que giftcardDiscount exista y no haya sido eliminado
        if (window.giftcardDiscount && window.giftcardDiscount.amount > 0 && giftCardHandlerActive) {
            const discountAmount = parseFloat(window.giftcardDiscount.amount);
            
            // Verificar si el descuento cubre completamente el total
            if (discountAmount >= finalTotal) {
                fullDiscount = true;
                window.giftcardDiscount.actualAmount = finalTotal;
                finalTotal = 0.00;
            } else {
                window.giftcardDiscount.actualAmount = discountAmount;
                finalTotal = finalTotal - discountAmount;
            }
            
            console.log('Descuento aplicado de Gift Card:', window.giftcardDiscount.actualAmount, 'Total final:', finalTotal, 'Descuento completo:', fullDiscount);
        }
        
        // Verificar explícitamente si no hay giftcard activa
        const giftcardAmountField = document.querySelector('input[name="giftcard_amount"]');
        const giftcardCodeField = document.querySelector('input[name="giftcard_code"]');
        
        // Si el handler indica que no hay descuento o faltan los campos, devolver el total original
        if (!giftCardHandlerActive || !giftcardAmountField || !giftcardCodeField) {
            console.log('No hay giftcard activa según verificación DOM, usando total original:', finalTotal);
            window.isFullDiscount = false;
            return finalTotal;
        }
        
        // Si hay campos ocultos aún en el DOM y el handler indica descuento activo
        if (giftcardAmountField && giftcardCodeField && 
            !isNaN(parseFloat(giftcardAmountField.value)) && 
            giftCardHandlerActive) {
            
            const discountAmount = parseFloat(giftcardAmountField.value);
            
            // Verificar si el descuento cubre completamente el total
            if (discountAmount >= finalTotal) {
                fullDiscount = true;
                window.giftcardDiscount = window.giftcardDiscount || {};
                window.giftcardDiscount.code = giftcardCodeField.value;
                window.giftcardDiscount.amount = discountAmount;
                window.giftcardDiscount.actualAmount = finalTotal;
                
                // Actualizar el campo oculto con el valor ajustado
                giftcardAmountField.value = window.giftcardDiscount.actualAmount.toFixed(2);
                finalTotal = 0.00;
            } else {
                window.giftcardDiscount = window.giftcardDiscount || {};
                window.giftcardDiscount.code = giftcardCodeField.value;
                window.giftcardDiscount.amount = discountAmount;
                window.giftcardDiscount.actualAmount = discountAmount;
                finalTotal = finalTotal - discountAmount;
            }
            
            console.log('Descuento aplicado desde campo oculto:', window.giftcardDiscount.actualAmount, 'Total final:', finalTotal, 'Descuento completo:', fullDiscount);
        }
        
        // Almacenar si es un descuento completo para usarlo después
        window.isFullDiscount = fullDiscount;
        
        return finalTotal;
    }
    
    // Escuchar evento de Gift Card aplicada
    document.addEventListener('giftcard:applied', function(e) {
        console.log('Evento giftcard:applied recibido', e.detail);
        
        // Almacenar información para uso posterior
        window.giftcardDiscount = {
            code: e.detail.code,
            amount: e.detail.amount,
            actualAmount: e.detail.amount,
            isFullDiscount: e.detail.isFullDiscount
        };
        
        // Si es un descuento completo, ocultar PayPal y mostrar botón alternativo
        if (e.detail.isFullDiscount) {
            const paypalContainer = document.getElementById('paypal-button-container');
            if (paypalContainer) paypalContainer.style.display = 'none';
        }
    });
    
    // Escuchar evento de Gift Card eliminada
    document.addEventListener('giftcard:removed', function(e) {
        console.log('Evento giftcard:removed recibido', e?.detail || 'sin detalles');
        
        // Limpiar información almacenada de manera más exhaustiva
        window.giftcardDiscount = null;
        window.isFullDiscount = false;
        
        // Eliminar cualquier elemento DOM relacionado
        ['giftcard_code', 'giftcard_amount', 'is_full_discount'].forEach(name => {
            const elements = document.querySelectorAll(`input[name="${name}"]`);
            if (elements.length > 0) {
                console.log(`Eliminando ${elements.length} elementos ${name}`);
                elements.forEach(el => el.remove());
            }
        });
        
        // Asegurarse de que se muestre el botón de PayPal
        const paypalContainer = document.getElementById('paypal-button-container');
        if (paypalContainer) {
            console.log('Mostrando botón de PayPal');
            paypalContainer.style.display = 'block';
        }
        
        // Restaurar el total original con prioridad
        let originalTotal;
        
        // 1. Intentar usar el valor proporcionado en el evento
        if (e && e.detail && e.detail.originalTotal) {
            originalTotal = e.detail.originalTotal;
            console.log('Usando total original del evento:', originalTotal);
        } 
        // 2. Intentar usar la variable global
        else if (window.originalTotal) {
            originalTotal = window.originalTotal;
            console.log('Usando total original de variable global:', originalTotal);
        } 
        // 3. Recalcular desde el carrito
        else {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            originalTotal = cart.reduce((sum, item) => {
                const price = item.isGiftCard && item.realPrice ? item.realPrice : item.price;
                return sum + (price * item.quantity);
            }, 0);
            console.log('Recalculando total original del carrito:', originalTotal);
        }
        
        // Actualizar el total mostrado
        const totalAmountEl = document.getElementById('total-amount');
        if (totalAmountEl) {
            totalAmountEl.textContent = `$${originalTotal.toFixed(2)} MXN`;
            console.log('Total mostrado actualizado a:', originalTotal);
        }
        
        // Almacenar el total original para futuras referencias
        window.originalTotal = originalTotal;
        
        // Forzar la actualización del total en PayPal también
        if (typeof calculateFinalTotal === 'function') {
            const finalTotal = calculateFinalTotal();
            console.log('Recalculando total final para PayPal:', finalTotal);
        }
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
                // Verificar si hay un giftcard handler activo
                const giftCardActive = window.giftCardHandler && window.giftCardHandler.discountApplied;
                
                if (window.giftcardDiscount && window.giftcardDiscount.code && giftCardActive) {
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
                    // Solo buscar en campos ocultos si el handler indica que hay descuento activo
                    if (giftCardActive) {
                        // Buscar en campos ocultos
                        const giftcardCode = document.querySelector('input[name="giftcard_code"]');
                        const giftcardAmount = document.querySelector('input[name="giftcard_amount"]');

                        if (giftcardCode && giftcardAmount && giftcardCode.value && 
                            parseFloat(giftcardAmount.value) > 0) {
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
                    } else {
                        console.log('No hay Gift Card activa según el handler. No se aplicará ningún descuento.');
                        
                        // Eliminar cualquier campo oculto que pueda existir
                        ['giftcard_code', 'giftcard_amount', 'is_full_discount'].forEach(name => {
                            const elements = document.querySelectorAll(`input[name="${name}"]`);
                            elements.forEach(el => el.remove());
                        });
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
                        // Mejorar el manejo de errores HTTP para depuración
                        const statusText = response.statusText || 'Error desconocido';
                        console.error(`Error HTTP: ${response.status} ${statusText}`);
                        
                        // Intentar obtener más información sobre el error
                        return response.text().then(text => {
                            let errorDetail = text;
                            try {
                                // Intentar parsear como JSON
                                const jsonResponse = JSON.parse(text);
                                if (jsonResponse && jsonResponse.message) {
                                    errorDetail = jsonResponse.message;
                                }
                            } catch (e) {
                                // Si no es JSON, usar el texto como está
                                console.log('Respuesta no es JSON válido:', text);
                            }
                            
                            throw new Error(`Error del servidor (${response.status}): ${errorDetail}`);
                        });
                    }
                    
                    // Manejar posibles respuestas no-JSON
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.warn('Respuesta no es JSON válido, pero el código HTTP es exitoso:', text);
                            // Si el HTTP status es 200 pero no es JSON, considerarlo exitoso de todos modos
                            if (text.includes('success') || text.includes('exitoso') || text.includes('order_id')) {
                                return { success: true, order_id: 'unknown' };
                            }
                            return { success: false, message: 'Formato de respuesta inválido' };
                        }
                    });
                })
                .then(data => {
                    console.log('Respuesta del servidor:', data);
                    
                    // Si la solicitud HTTP fue exitosa (200) pero no hay datos claros, considerarla exitosa
                    if (!data && response && response.ok) {
                        console.log('No hay datos en la respuesta, pero el status HTTP es exitoso');
                        // Limpiar carrito
                        localStorage.removeItem('cart');
                        
                        // Redirigir a página de éxito sin ID de orden
                        window.location.href = `success.html`;
                        return;
                    }
                    
                    if (data.success) {
                        // No mostrar alerta, solo redirigir directamente
                        
                        // Limpiar carrito
                        localStorage.removeItem('cart');
                        
                        // Redirigir a página de éxito con el ID de la orden si está disponible
                        try {
                            if (data.order_id) {
                                window.location.href = `success.html?order_id=${data.order_id}`;
                            } else {
                                // Si no hay order_id, simplemente redirigir a success
                                window.location.href = `success.html`;
                            }
                            
                            // Si la redirección falla (por ejemplo, si success.html no existe),
                            // manejarlo en el catch abajo
                        } catch (e) {
                            console.error('Error al redirigir a success.html, intentando con success-backup.php', e);
                            
                            // Plan B: intentar con la página de respaldo PHP
                            if (data.order_id) {
                                window.location.href = `success-backup.php?order_id=${data.order_id}`;
                            } else {
                                window.location.href = `success-backup.php`;
                            }
                        }
                    } else {
                        throw new Error(data.message || 'Error al procesar la orden');
                    }
                })
                .catch(error => {
                    console.error('Error completo:', error);
                    // Mostrar detalles completos del error para depuración
                    const errorMessage = error.message || 'Error desconocido';
                    const errorStack = error.stack || '';
                    
                    // Para ayudar en la depuración, añadir información sobre la URL de process_order.php
                    const currentUrl = window.location.href;
                    const processOrderUrl = new URL('process_order.php', currentUrl).href;
                    
                    console.error('Detalles del error:', {
                        message: errorMessage,
                        stack: errorStack,
                        currentUrl: currentUrl,
                        processOrderUrl: processOrderUrl
                    });
                    
                    // Si estamos en un entorno de producción, mostrar instrucciones más útiles
                    if (currentUrl.includes('jersix.mx') || !currentUrl.includes('localhost')) {
                        alert('Error al procesar la orden. Por favor, contacta a soporte y proporciona el siguiente código de error: ' + 
                              new Date().toISOString().slice(0,16).replace(/[-:T]/g,'') + '-' + Math.random().toString(36).substring(2, 8));
                    } else {
                        alert('Error al procesar la orden: ' + errorMessage);
                    }
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