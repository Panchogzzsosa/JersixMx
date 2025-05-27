document.addEventListener('DOMContentLoaded', function() {
    // Inicializar variables globales relacionadas con giftcard y códigos promocionales
    window.giftcardDiscount = null;
    window.promoCodeDiscount = null;
    window.isFullDiscount = false;
    
    // Asegurarnos de que cualquier campo oculto previo se elimine
    setTimeout(() => {
        ['giftcard_code', 'giftcard_amount', 'is_full_discount',
         'promo_code', 'promo_discount', 'promo_type'].forEach(name => {
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
    
    // Verificar si hay tarjetas de regalo en el carrito
    const hasGiftCard = cart.some(item => 
        item.isGiftCard || 
        (item.title && (item.title.includes('Tarjeta de Regalo') || item.title.includes('Gift Card')))
    );
    
    // Desactivar sección de códigos promocionales si hay tarjetas de regalo
    if (hasGiftCard) {
        // Desactivar sección de códigos promocionales
        const promoSection = document.querySelector('.promo-code-section');
        const promoInput = document.getElementById('promo-code');
        const promoButton = document.getElementById('apply-promo-btn');
        const promoMessage = document.getElementById('promo-message');
        
        if (promoSection) {
            if (promoInput) promoInput.disabled = true;
            if (promoButton) promoButton.disabled = true;
            if (promoMessage) {
                promoMessage.textContent = 'No se pueden usar códigos promocionales en compras de tarjetas de regalo';
                promoMessage.className = 'promo-message error';
            }
        }
        
        // Desactivar sección de tarjetas de regalo
        const giftcardContainer = document.querySelector('.giftcard-container');
        const giftcardInput = document.getElementById('giftcard-code');
        const giftcardButton = document.getElementById('apply-giftcard-btn');
        const giftcardMessage = document.getElementById('giftcard-message');
        
        if (giftcardContainer) {
            if (giftcardInput) giftcardInput.disabled = true;
            if (giftcardButton) giftcardButton.disabled = true;
            if (giftcardMessage) {
                giftcardMessage.textContent = 'No se pueden usar tarjetas de regalo en compras de tarjetas de regalo';
                giftcardMessage.className = 'giftcard-message error';
            }
        }
    }

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
                ${(!item.isGiftCard && item.size) ? `<div class="cart-item-size" style="border-radius: 8px;">Talla: ${item.size}</div>` : ''}
                ${item.personalization ? `
                    <div class="personalization-info" style="
                        margin-top: 10px;
                        padding: 12px 15px;
                        background-color: #fafafa;
                        border-left: 3px solid #2c3e50;
                        font-size: 0.9rem;
                    ">
                        <div style="
                            color: #2c3e50;
                            font-weight: 500;
                            margin-bottom: 8px;
                            letter-spacing: 0.3px;
                        ">
                            Personalización
                        </div>
                        <div style="
                            display: grid;
                            gap: 4px;
                            color: #555;
                        ">
                            ${item.personalization.name ? `
                                <div>
                                    <span style="color: #777;">Nombre:</span> ${item.personalization.name}
                                </div>
                            ` : ''}
                            ${item.personalization.number ? `
                                <div>
                                    <span style="color: #777;">Número:</span> ${item.personalization.number}
                                </div>
                            ` : ''}
                            ${item.personalization.patch ? `
                                <div style="color: #555;">• Con parche</div>
                            ` : ''}
                        </div>
                    </div>
                ` : ''}
                ${item.isGiftCard && item.details ? `
                    <div class="personalization-info" style="
                        margin-top: 10px;
                        padding: 12px 15px;
                        background-color: #fafafa;
                        border-left: 3px solid #9b59b6;
                        font-size: 0.9rem;
                    ">
                        <div style="
                            color: #2c3e50;
                            font-weight: 500;
                            margin-bottom: 8px;
                            letter-spacing: 0.3px;
                        ">
                            Detalles de la Gift Card
                        </div>
                        <div style="
                            display: grid;
                            gap: 4px;
                            color: #555;
                        ">
                            <div>
                                <span style="color: #777;">Para:</span> ${item.details.recipientName}
                            </div>
                            <div>
                                <span style="color: #777;">Email:</span> ${item.details.recipientEmail}
                            </div>
                            ${item.details.message ? `
                                <div>
                                    <span style="color: #777;">Mensaje:</span> ${item.details.message}
                                </div>
                            ` : ''}
                            <div>
                                <span style="color: #777;">De:</span> ${item.details.senderName}
                            </div>
                        </div>
                    </div>
                ` : ''}
                ${item.mysteryBoxType ? `
                    <div class="mystery-box-type" style="
                        margin-top: 10px;
                        padding: 12px 15px;
                        background-color: #fafafa;
                        border-left: 3px solid #e74c3c;
                        font-size: 0.9rem;
                    ">
                        <div style="color: #555;">
                            <span style="color: #777;">Tipo:</span> 
                            ${item.mysteryBoxType.charAt(0).toUpperCase() + item.mysteryBoxType.slice(1)}
                        </div>
                        ${item.unwantedTeam ? `
                            <div class="unwanted-team" style="
                                margin-top: 8px;
                                padding: 8px;
                                background-color: #fff3f3;
                                border-radius: 4px;
                                font-size: 0.85rem;
                                color: #555;
                            ">
                                <strong>Equipo no deseado:</strong><br>
                                ${item.unwantedTeam}
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
            </div>
        `;

        cartSummary.appendChild(cartItem);
        subtotal += displayPrice * item.quantity;
    });

    // Definir shipping ANTES de mostrar totales y descuento
    const shipping = 0; // o el valor que corresponda

    // Mostrar subtotal y envío
    document.getElementById('subtotal-amount').textContent = `$${subtotal.toFixed(2)} MXN`;
    document.getElementById('shipping-amount').textContent = `$${shipping.toFixed(2)} MXN`;

    // --- DESCUENTO AUTOMÁTICO DE 2 JERSEYS (799/899) --- //
    function aplicarDescuentoPaqueteSiActivo(cart, callback) {
        fetch('admin2/get_auto_promo_status.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.estado === 'activo') {
                    callback(true);
                } else {
                    callback(false);
                }
            })
            .catch(() => callback(false));
    }

    // Reemplaza la lógica de descuento automático por esta:
    aplicarDescuentoPaqueteSiActivo(cart, function(promoActiva) {
        let jerseyCount = 0;
        let count799 = 0;
        let count899 = 0;
        let count874 = 0;
        let count949 = 0;
        let count999 = 0;
        let count1049 = 0;
        let jerseySubtotal = 0;
        cart.forEach(item => {
            const precio = Math.round(Number(item.price));
            if (precio === 799 || precio === 899 || precio === 874 || precio === 949 || precio === 999 || precio === 1049) {
                jerseyCount += item.quantity;
                if (precio === 799) count799 += item.quantity;
                if (precio === 899) count899 += item.quantity;
                if (precio === 874) count874 += item.quantity;
                if (precio === 949) count949 += item.quantity;
                if (precio === 999) count999 += item.quantity;
                if (precio === 1049) count1049 += item.quantity;
                jerseySubtotal += Number(item.price) * item.quantity;
            }
        });
        let descuentoPaquete = 0;
        let precioPaquete = 0;
        if (promoActiva && jerseyCount === 2) {
            if (count799 === 2) {
                precioPaquete = 1000;
            } else if (count799 === 1 && count899 === 1) {
                precioPaquete = 1100;
            } else if (count899 === 2) {
                precioPaquete = 1200;
            } else if (count874 === 2) {
                precioPaquete = 1150;
            } else if (count799 === 1 && count949 === 1) {
                precioPaquete = 1150;
            } else if (count949 === 1 && count899 === 1) {
                precioPaquete = 1250;
            } else if (count949 === 2) {
                precioPaquete = 1300;
            } else if (count899 === 1 && count999 === 1) {
                precioPaquete = 1300;
            } else if (count999 === 2) {
                precioPaquete = 1400;
            } else if (count999 === 1 && count1049 === 1) {
                precioPaquete = 1450;
            } else if (count799 === 1 && count999 === 1) {
                precioPaquete = 1200;
            } else if (count799 === 1 && count1049 === 1) {
                precioPaquete = 1250;
            } else if (count949 === 1 && count1049 === 1) {
                precioPaquete = 1400;
            } else if (count899 === 1 && count1049 === 1) {
                precioPaquete = 1350;
            } else if (count949 === 1 && count999 === 1) {
                precioPaquete = 1350;
            } else if (count1049 === 2) {
                precioPaquete = 1500;
            }
            
            if (precioPaquete > 0) {
                descuentoPaquete = jerseySubtotal - precioPaquete;
            }
        }
        let descuentoRow = document.getElementById('descuento-paquete-row');
        if (descuentoPaquete > 0) {
            if (!descuentoRow) {
                descuentoRow = document.createElement('div');
                descuentoRow.id = 'descuento-paquete-row';
                descuentoRow.className = 'checkout-row';
                descuentoRow.style.display = 'flex';
                descuentoRow.style.justifyContent = 'space-between';
                descuentoRow.style.alignItems = 'center';
                descuentoRow.style.margin = '8px 0';
                descuentoRow.innerHTML = `
                    <span style="font-weight: 500; color: #2d3748;">Descuento:</span>
                    <span style="font-weight: 700; color: #e53935;">-$${descuentoPaquete.toFixed(2)} MXN</span>
                `;
                const shippingEl = document.getElementById('shipping-amount');
                let shippingRow = shippingEl ? shippingEl.closest('.checkout-row, div, tr') : null;
                if (shippingRow && shippingRow.parentNode) {
                    shippingRow.parentNode.insertBefore(descuentoRow, shippingRow.nextSibling);
                }
            } else {
                descuentoRow.innerHTML = `
                    <span style="font-weight: 500; color: #2d3748;">Descuento:</span>
                    <span style="font-weight: 700; color: #e53935;">-$${descuentoPaquete.toFixed(2)} MXN</span>
                `;
            }
        } else {
            if (descuentoRow && descuentoRow.parentNode) {
                descuentoRow.parentNode.removeChild(descuentoRow);
            }
        }

        // Calcular y mostrar el total con descuento
        const totalConDescuento = subtotal + shipping - descuentoPaquete;
        document.getElementById('total-amount').textContent = `$${totalConDescuento.toFixed(2)} MXN`;
        window.descuentoPaquete = descuentoPaquete;

        // Update totals
        const total = subtotal + shipping;
        
        // Almacenar el total original para referencia
        window.originalTotal = total;

        // Crear un elemento oculto para almacenar el total original
        const originalTotalEl = document.createElement('div');
        originalTotalEl.className = 'original-total';
        originalTotalEl.style.display = 'none';
        originalTotalEl.textContent = `$${total.toFixed(2)}`;
        document.body.appendChild(originalTotalEl);

        // Función para calcular el total final considerando GiftCard y códigos promocionales
        function calculateFinalTotal() {
            let finalTotal = window.originalTotal || total;
            let fullDiscount = false;
            let totalDiscounts = 0;
            
            // --- APLICAR DESCUENTO DE CÓDIGO PROMOCIONAL --- //
            const promoCodeHandlerActive = window.promoCodeHandler && window.promoCodeHandler.discountApplied;
            
            if (promoCodeHandlerActive && window.promoCodeDiscount && window.promoCodeDiscount.amount > 0) {
                const promoDiscountAmount = parseFloat(window.promoCodeDiscount.amount);
                totalDiscounts += promoDiscountAmount;
                
                console.log('Descuento aplicado de Código Promocional:', promoDiscountAmount);
            }
            
            // --- APLICAR DESCUENTO DE GIFTCARD --- //
            // Verificar si hay una instancia de GiftCardHandler y si tiene un descuento aplicado
            const giftCardHandlerActive = window.giftCardHandler && window.giftCardHandler.discountApplied;
            
            // Si el handler indica que no hay descuento aplicado, continuar solo con descuento promocional
            if (window.giftCardHandler && !window.giftCardHandler.discountApplied) {
                console.log('GiftCardHandler indica que no hay descuento aplicado');
            } else {
                // Verificar que giftcardDiscount exista y no haya sido eliminado
                if (window.giftcardDiscount && window.giftcardDiscount.amount > 0 && giftCardHandlerActive) {
                    const discountAmount = parseFloat(window.giftcardDiscount.amount);
                    totalDiscounts += discountAmount;
                    
                    console.log('Descuento aplicado de Gift Card:', window.giftcardDiscount.amount);
                }
                
                // Verificar explícitamente si hay giftcard activa en los campos
                const giftcardAmountField = document.querySelector('input[name="giftcard_amount"]');
                const giftcardCodeField = document.querySelector('input[name="giftcard_code"]');
                
                // Si hay campos ocultos aún en el DOM y el handler indica descuento activo
                if (giftcardAmountField && giftcardCodeField && 
                    !isNaN(parseFloat(giftcardAmountField.value)) && 
                    giftCardHandlerActive) {
                    
                    const discountAmount = parseFloat(giftcardAmountField.value);
                    // Solo sumar si no se contabilizó previamente con window.giftcardDiscount
                    if (!window.giftcardDiscount || window.giftcardDiscount.code !== giftcardCodeField.value) {
                        totalDiscounts += discountAmount;
                        
                        console.log('Descuento aplicado desde campo oculto de Gift Card:', discountAmount);
                    }
                }
            }
            
            // --- DESCUENTO AUTOMÁTICO DE 2 JERSEYS (799/899) --- //
            if (window.descuentoPaquete && window.descuentoPaquete > 0) {
                totalDiscounts += window.descuentoPaquete;
            }
            
            // --- APLICAR TOTAL DE DESCUENTOS --- //
            
            // Verificar si los descuentos cubren completamente el total
            if (totalDiscounts >= finalTotal) {
                fullDiscount = true;
                // Ajustar totalDiscounts para que no sea mayor que el total
                totalDiscounts = finalTotal;
                finalTotal = 0.00;
            } else {
                finalTotal = finalTotal - totalDiscounts;
            }
            
            // Almacenar si es un descuento completo para usarlo después
            window.isFullDiscount = fullDiscount;
            
            console.log('Total final después de descuentos:', finalTotal, 'Descuento completo:', fullDiscount);
            
            return finalTotal;
        }
        
        // Escuchar evento de código promocional aplicado
        document.addEventListener('promocode:applied', function(e) {
            console.log('Evento promocode:applied recibido', e.detail);
            
            // Almacenar información para uso posterior, asegurando que tipo siempre tenga un valor
            window.promoCodeDiscount = {
                code: e.detail.code,
                amount: e.detail.amount,
                type: e.detail.type || 'fijo' // Asegurar que siempre haya un tipo, defaulteando a 'fijo'
            };
            
            // Crear campos ocultos para asegurar que se envíen al servidor cuando se procese el pago
            ['promo_code', 'promo_discount', 'promo_type'].forEach(field => {
                // Primero eliminar si existe
                const existingField = document.querySelector(`input[name="${field}"]`);
                if (existingField) {
                    existingField.remove();
                }
                
                // Crear nuevo elemento
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = field;
                
                // Asignar valores según el campo
                if (field === 'promo_code') {
                    input.value = window.promoCodeDiscount.code;
                } else if (field === 'promo_discount') {
                    input.value = window.promoCodeDiscount.amount.toFixed(2);
                } else if (field === 'promo_type') {
                    input.value = window.promoCodeDiscount.type;
                }
                
                // Agregar al DOM
                document.body.appendChild(input);
                console.log(`Campo oculto creado: ${field} = ${input.value}`);
            });
            
            // Recalcular el total
            const finalTotal = calculateFinalTotal();
            
            // Actualizar el elemento del DOM con el nuevo total
            const totalEl = document.getElementById('total-amount');
            if (totalEl) {
                totalEl.textContent = `$${finalTotal.toFixed(2)} MXN`;
            }
            
            // Si es un descuento completo, ocultar PayPal y mostrar botón alternativo
            if (window.isFullDiscount) {
                const paypalContainer = document.getElementById('paypal-button-container');
                if (paypalContainer) paypalContainer.style.display = 'none';
            }
        });
        
        // Escuchar evento de código promocional eliminado
        document.addEventListener('promocode:removed', function(e) {
            console.log('Evento promocode:removed recibido', e?.detail || 'sin detalles');
            
            // Limpiar información almacenada
            window.promoCodeDiscount = null;
            
            // Recalcular el total (que ahora solo considerará Gift Card si está presente)
            const finalTotal = calculateFinalTotal();
            
            // Actualizar el elemento del DOM con el nuevo total
            const totalEl = document.getElementById('total-amount');
            if (totalEl) {
                totalEl.textContent = `$${finalTotal.toFixed(2)} MXN`;
            }
            
            // Si ya no hay descuento completo, mostrar PayPal
            if (!window.isFullDiscount) {
                const paypalContainer = document.getElementById('paypal-button-container');
                if (paypalContainer) paypalContainer.style.display = 'block';
            }
        });
        
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
            
            // Recalcular el total
            const finalTotal = calculateFinalTotal();
            
            // Actualizar el elemento del DOM con el nuevo total
            const totalEl = document.getElementById('total-amount');
            if (totalEl) {
                totalEl.textContent = `$${finalTotal.toFixed(2)} MXN`;
            }
            
            // Si es un descuento completo, ocultar PayPal y mostrar botón alternativo
            if (window.isFullDiscount || e.detail.isFullDiscount) {
                const paypalContainer = document.getElementById('paypal-button-container');
                if (paypalContainer) paypalContainer.style.display = 'none';
            }
        });
        
        // Escuchar evento de Gift Card eliminada
        document.addEventListener('giftcard:removed', function(e) {
            console.log('Evento giftcard:removed recibido', e?.detail || 'sin detalles');
            
            // Limpiar información almacenada de manera más exhaustiva
            window.giftcardDiscount = null;
            
            // Solo limpiar isFullDiscount si no hay código promocional activo
            if (!window.promoCodeDiscount) {
                window.isFullDiscount = false;
            }
            
            // Eliminar cualquier elemento DOM relacionado
            ['giftcard_code', 'giftcard_amount', 'is_full_discount'].forEach(name => {
                const elements = document.querySelectorAll(`input[name="${name}"]`);
                if (elements.length > 0) {
                    console.log(`Eliminando ${elements.length} elementos ${name}`);
                    elements.forEach(el => el.remove());
                }
            });
            
            // Recalcular el total (que ahora solo considerará códigos promocionales si están presentes)
            const finalTotal = calculateFinalTotal();
            
            // Actualizar el elemento del DOM con el nuevo total
            const totalEl = document.getElementById('total-amount');
            if (totalEl) {
                totalEl.textContent = `$${finalTotal.toFixed(2)} MXN`;
            }
            
            // Si ya no hay descuento completo, mostrar PayPal
            if (!window.isFullDiscount) {
                const paypalContainer = document.getElementById('paypal-button-container');
                if (paypalContainer) {
                    console.log('Mostrando botón de PayPal');
                    paypalContainer.style.display = 'block';
                }
            }
        });

        // Configurar PayPal
        if (paypal) {
            paypal.Buttons({
                style: {
                    color:  'gold',
                    shape:  'rect',
                    label:  'pay',
                    height: 40,
                    tagline: false
                },
                onInit: function(data, actions) {
                    // Este evento se dispara cuando los botones se inicializan
                    const finalTotal = calculateFinalTotal();
                    console.log("PayPal inicializado con total:", finalTotal);
                },
                createOrder: function(data, actions) {
                    const finalTotal = calculateFinalTotal();
                    
                    // Si el total es cero, no crear orden de PayPal
                    if (finalTotal <= 0) {
                        console.log('Total es cero, no se crea orden de PayPal');
                        return null;
                    }
                    
                    // Crear orden de PayPal
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: finalTotal.toFixed(2),
                                currency_code: 'MXN'
                            }
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        // Mostrar indicador de carga
                        const loadingIndicator = document.createElement('div');
                        loadingIndicator.className = 'loading-indicator';
                        loadingIndicator.innerHTML = `
                            <div class="loading-spinner"></div>
                            <p>Procesando tu pedido, por favor espera...</p>
                        `;
                        document.body.appendChild(loadingIndicator);
                        
                        // Recolectar datos del formulario
                        const form = document.getElementById('contact-form');
                        const formData = new FormData();
                        
                        formData.append('email', form.email.value);
                        formData.append('phone', form.phone.value);
                        formData.append('fullname', form.fullname.value);
                        formData.append('street', form.street.value);
                        formData.append('colonia', form.colonia.value);
                        formData.append('city', form.city.value);
                        formData.append('state', form.state.value);
                        formData.append('postal', form.postal.value);
                        
                        // Añadir detalles del pago
                        formData.append('payment_id', details.id);
                        formData.append('payment_status', details.status);
                        formData.append('payment_method', 'PayPal');
                        
                        // Aquí está el cambio: añadir el carrito con el nombre correcto 'cart_items'
                        const cartData = localStorage.getItem('cart');
                        formData.append('cart', cartData);
                        formData.append('cart_items', cartData); // Añadir con ambos nombres para compatibilidad
                        
                        // Incluir información de descuentos (Gift Card y/o código promocional)
                        
                        // 1. Gift Card
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
                            
                            console.log('Aplicando Gift Card:', window.giftcardDiscount.code, 
                                        'por $' + amountToUse.toFixed(2));
                        }
                        
                        // Solo buscar en campos ocultos si el handler indica que hay descuento activo
                        if (giftCardActive) {
                            // Buscar en campos ocultos
                            const giftcardCode = document.querySelector('input[name="giftcard_code"]');
                            const giftcardAmount = document.querySelector('input[name="giftcard_amount"]');

                            if (giftcardCode && giftcardAmount && giftcardCode.value && 
                                parseFloat(giftcardAmount.value) > 0) {
                                // El valor de giftcardAmount ya debería estar ajustado
                                formData.append('giftcard_code', giftcardCode.value);
                                formData.append('giftcard_amount', giftcardAmount.value);
                                
                                // Si es un descuento completo, indicarlo
                                if (window.isFullDiscount) {
                                    formData.append('is_full_discount', 'true');
                                }
                                
                                console.log('Aplicando Gift Card desde campos ocultos:', giftcardCode.value, 
                                            'por $' + giftcardAmount.value);
                            }
                        }
                        
                        // 2. Código Promocional
                        const promoCodeActive = window.promoCodeHandler && window.promoCodeHandler.discountApplied;
                        
                        if (window.promoCodeDiscount && window.promoCodeDiscount.code && promoCodeActive) {
                            formData.append('promo_code', window.promoCodeDiscount.code);
                            formData.append('promo_discount', window.promoCodeDiscount.amount.toFixed(2));
                            formData.append('promo_type', window.promoCodeDiscount.type || 'fijo');
                            
                            // Crear elementos ocultos para asegurar que la información se envía correctamente
                            const hiddenFields = [
                                { name: 'promo_code', value: window.promoCodeDiscount.code },
                                { name: 'promo_discount', value: window.promoCodeDiscount.amount.toFixed(2) },
                                { name: 'promo_type', value: window.promoCodeDiscount.type || 'fijo' }
                            ];
                            
                            hiddenFields.forEach(field => {
                                const existingField = document.querySelector(`input[name="${field.name}"]`);
                                if (existingField) {
                                    existingField.value = field.value;
                                } else {
                                    const hiddenInput = document.createElement('input');
                                    hiddenInput.type = 'hidden';
                                    hiddenInput.name = field.name;
                                    hiddenInput.value = field.value;
                                    document.body.appendChild(hiddenInput);
                                }
                            });
                            
                            console.log('Aplicando Código Promocional:', window.promoCodeDiscount.code, 
                                       'por $' + window.promoCodeDiscount.amount.toFixed(2), 
                                       'tipo:', window.promoCodeDiscount.type || 'fijo');
                        }
                        
                        // Buscar en campos ocultos del código promocional
                        if (promoCodeActive) {
                            const promoCode = document.querySelector('input[name="promo_code"]');
                            const promoDiscount = document.querySelector('input[name="promo_discount"]');
                            const promoType = document.querySelector('input[name="promo_type"]');
                            
                            if (promoCode && promoDiscount && promoCode.value && 
                               parseFloat(promoDiscount.value) > 0) {
                                formData.append('promo_code', promoCode.value);
                                formData.append('promo_discount', promoDiscount.value);
                                if (promoType && promoType.value) {
                                    formData.append('promo_type', promoType.value);
                                } else {
                                    formData.append('promo_type', 'fijo'); // Valor predeterminado
                                }
                                
                                console.log('Aplicando Código Promocional desde campos ocultos:', promoCode.value, 
                                           'por $' + promoDiscount.value,
                                           'tipo:', promoType?.value || 'fijo');
                            }
                        }
                        
                        // Enviar datos al servidor
                        fetch('process_order.php', {
                            method: 'POST',
                            body: formData,
                            redirect: 'follow' // Permite seguir redirecciones
                        })
                        .then(response => {
                            // Si el servidor redirecciona a success.html, seguir la redirección
                            if (response.redirected) {
                                console.log('Redirección detectada a:', response.url);
                                window.location.href = response.url;
                                return { redirected: true };
                            }
                            
                            // Verificar si la respuesta es válida antes de intentar parsearlo como JSON
                            if (!response.ok) {
                                throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
                            }
                            
                            // Verificar si hay contenido en la respuesta
                            const contentType = response.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                throw new Error('Formato de respuesta no válido. Se esperaba JSON.');
                            }
                            
                            return response.text().then(text => {
                                try {
                                    // Intentar analizar el texto como JSON
                                    if (!text || text.trim() === '') {
                                        throw new Error('Respuesta vacía del servidor');
                                    }
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error('Error al parsear JSON:', text);
                                    throw new Error('Error en formato de respuesta: ' + e.message);
                                }
                            });
                        })
                        .then(data => {
                            // Si ya se ha redireccionado, no hacer nada más
                            if (data.redirected) {
                                return;
                            }
                            
                            if (data.success) {
                                // Guardar ID del pedido
                                localStorage.setItem('last_order_id', data.order_id);
                                
                                // Limpiar carrito
                                localStorage.removeItem('cart');
                                
                                // Redireccionar a la página de éxito
                                window.location.href = 'success.html?order_id=' + data.order_id;
                            } else {
                                // Manejar error
                                document.body.removeChild(loadingIndicator);
                                alert('Error al procesar tu pedido: ' + data.message);
                            }
                        })
                        .catch(error => {
                            document.body.removeChild(loadingIndicator);
                            console.error('Error:', error);
                            
                            // Mensaje de error más específico
                            let errorMessage = 'Ocurrió un error al procesar tu pedido: ';
                            
                            if (error.message.includes('JSON')) {
                                errorMessage += 'Error en la respuesta del servidor. Por favor, contacta a soporte.';
                            } else if (error.message.includes('HTTP')) {
                                errorMessage += 'Problema de conexión con el servidor. Intenta de nuevo más tarde.';
                            } else {
                                errorMessage += 'Por favor intenta de nuevo o contacta a soporte si el problema persiste.';
                            }
                            
                            alert(errorMessage);
                        });
                    });
                },
                onError: function(err) {
                    console.error('Error en PayPal:', err);
                    alert('Ocurrió un error con PayPal. Por favor intenta de nuevo.');
                }
            }).render('#paypal-button-container');
        } else {
            console.error('PayPal no está disponible');
        }
    });
});