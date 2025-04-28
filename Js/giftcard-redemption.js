/**
 * Módulo de redención de Gift Cards para JerSix
 * Este script maneja la verificación y aplicación de códigos de Gift Card
 */

class GiftCardHandler {
    constructor() {
        this.giftcardCode = null;
        this.giftcardData = null;
        this.appliedAmount = 0;
        this.discountApplied = false;
        
        // Inicializar eventos cuando el DOM está listo
        document.addEventListener('DOMContentLoaded', () => this.init());
    }
    
    /**
     * Inicializar el manejador de Gift Cards
     */
    init() {
        // Crear y añadir el contenedor de Gift Card al DOM
        this.createGiftCardUI();
        
        // Establecer eventos
        const applyBtn = document.getElementById('apply-giftcard-btn');
        if (applyBtn) {
            applyBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.validateGiftCard();
            });
        }
        
        // Escuchar cambios en el total
        document.addEventListener('cart:updated', () => {
            if (this.discountApplied) {
                this.updateDiscount();
            }
        });
    }
    
    /**
     * Crear la interfaz de usuario para la Gift Card
     */
    createGiftCardUI() {
        // Verificar si estamos en la página de checkout
        const checkoutForm = document.querySelector('form.contact-info');
        if (!checkoutForm) return;
        
        // Crear el contenedor de Gift Card
        const giftcardContainer = document.createElement('div');
        giftcardContainer.className = 'giftcard-container';
        giftcardContainer.innerHTML = `
            <h3>¿Tienes una tarjeta de regalo? <span class="giftcard-instruction"></span></h3>
            <div class="giftcard-input-group">
                <input type="text" id="giftcard-code" placeholder="Ingresa el código de tu tarjeta de regalo">
                <button id="apply-giftcard-btn" class="giftcard-btn">Aplicar</button>
            </div>
            <div id="giftcard-message" class="giftcard-message"></div>
            <div id="giftcard-details" class="giftcard-details" style="display: none;">
                <div class="giftcard-info">
                    <span>Saldo disponible:</span>
                    <span id="giftcard-balance" class="giftcard-balance">$0.00</span>
                </div>
                <div class="giftcard-info">
                    <span>Descuento aplicado:</span>
                    <span id="giftcard-discount" class="giftcard-discount">$0.00</span>
                </div>
                <button id="remove-giftcard-btn" class="remove-giftcard-btn">Quitar</button>
            </div>
        `;
        
        // Añadir estilos
        const styles = document.createElement('style');
        styles.textContent = `
            .giftcard-container {
                margin: 20px 0;
                padding: 15px;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                background-color: #f9f9f9;
            }
            .giftcard-container h3 {
                margin-top: 0;
                margin-bottom: 10px;
                font-size: 16px;
                color: #333;
            }
            .giftcard-container p {
                margin-top: 0;
                margin-bottom: 12px;
                font-size: 12px;
                color: #555;
                font-weight: 500;
            }
            .giftcard-instruction {
                color: #007bff;
                font-weight: 400;
                font-size: 12px;
                display: inline-block;
                margin-left: 5px;
            }
            .giftcard-input-group {
                display: flex;
                margin-bottom: 10px;
            }
            .giftcard-input-group input {
                flex: 1;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px 0 0 4px;
                font-size: 14px;
            }
            .giftcard-btn {
                padding: 10px 15px;
                border: none;
                border-radius: 0 4px 4px 0;
                background-color: #007bff;
                color: white;
                cursor: pointer;
                font-size: 14px;
                transition: background-color 0.3s;
            }
            .giftcard-btn:hover {
                background-color: #0056b3;
            }
            .giftcard-message {
                margin-top: 10px;
                font-size: 14px;
            }
            .giftcard-message.error {
                color: #dc3545;
            }
            .giftcard-message.success {
                color: #28a745;
            }
            .giftcard-details {
                margin-top: 15px;
                padding: 10px;
                background-color: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .giftcard-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                font-size: 14px;
            }
            .giftcard-balance, .giftcard-discount {
                font-weight: bold;
            }
            .remove-giftcard-btn {
                margin-top: 10px;
                padding: 6px 12px;
                background-color: #f8f9fa;
                border: 1px solid #ddd;
                border-radius: 4px;
                color: #dc3545;
                cursor: pointer;
                font-size: 13px;
                width: 100%;
                transition: all 0.3s;
            }
            .remove-giftcard-btn:hover {
                background-color: #dc3545;
                color: white;
                border-color: #dc3545;
            }
        `;
        
        // Insertar antes del botón de PayPal
        const paypalContainer = document.getElementById('paypal-button-container');
        if (paypalContainer) {
            // Insertar antes del contenedor de PayPal
            checkoutForm.insertBefore(giftcardContainer, paypalContainer);
            document.head.appendChild(styles);
            
            // Añadir evento para remover Gift Card
            const removeBtn = document.getElementById('remove-giftcard-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.removeGiftCard();
                });
            }
        } else {
            // Alternativa: insertar al final del formulario
            checkoutForm.appendChild(giftcardContainer);
            document.head.appendChild(styles);
            
            // Añadir evento para remover Gift Card
            const removeBtn = document.getElementById('remove-giftcard-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.removeGiftCard();
                });
            }
        }
    }
    
    /**
     * Verificar si hay una tarjeta de regalo en el carrito
     */
    hasGiftCardInCart() {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        return cart.some(item => item.isGiftCard || (item.title && (item.title.includes('Tarjeta de Regalo') || item.title.includes('Gift Card'))));
    }
    
    /**
     * Validar un código de Gift Card
     */
    validateGiftCard() {
        const codeInput = document.getElementById('giftcard-code');
        const messageEl = document.getElementById('giftcard-message');
        
        if (!codeInput || !messageEl) return;
        
        // Verificar si hay una tarjeta de regalo en el carrito
        if (this.hasGiftCardInCart()) {
            this.showMessage('No se pueden usar tarjetas de regalo en compras de tarjetas de regalo', 'error');
            return;
        }
        
        const code = codeInput.value.trim();
        if (!code) {
            this.showMessage('Por favor ingresa un código de tarjeta de regalo', 'error');
            return;
        }
        
        this.showMessage('Verificando código...', 'info');
        
        // Enviar solicitud al servidor
        fetch('validate_giftcard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ code })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.giftcardCode = code;
                this.giftcardData = data.data;
                this.showMessage('Tarjeta de regalo válida', 'success');
                this.showGiftCardDetails();
                this.applyDiscount();
            } else {
                this.showMessage(data.message || 'Código no válido', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showMessage('Error al verificar el código', 'error');
        });
    }
    
    /**
     * Mostrar detalles de la Gift Card
     */
    showGiftCardDetails() {
        const detailsEl = document.getElementById('giftcard-details');
        const balanceEl = document.getElementById('giftcard-balance');
        
        if (detailsEl && balanceEl && this.giftcardData) {
            detailsEl.style.display = 'block';
            balanceEl.textContent = `$${parseFloat(this.giftcardData.balance).toFixed(2)}`;
        }
    }
    
    /**
     * Aplicar descuento de la Gift Card
     */
    applyDiscount() {
        if (!this.giftcardData) return;
        
        // Obtener el total actual del carrito
        const total = this.getOriginalTotal();
        
        // Obtener el saldo disponible de la tarjeta
        const availableBalance = parseFloat(this.giftcardData.balance);
        
        // Determinar si es un descuento completo
        const isFullDiscount = availableBalance >= total;
        
        // Si es un descuento completo, usar todo el total
        // Si es parcial, usar el saldo disponible completo
        if (isFullDiscount) {
            this.appliedAmount = total; // Aplicar el total completo
        } else {
            this.appliedAmount = Math.min(availableBalance, total);
        }
        
        // Actualizar la visualización
        this.updateDiscountDisplay();
        
        // Actualizar el total del carrito
        this.updateCartTotal();
        
        // Indicar que hay un descuento aplicado
        this.discountApplied = true;
        
        // Ocultar el botón de PayPal y mostrar el botón de completar orden si es un descuento completo
        // En caso contrario, asegurarse de que PayPal esté visible
        if (isFullDiscount) {
            this.togglePayPalButton(false);
            this.createCompleteOrderButton();
        } else {
            this.togglePayPalButton(true);
            this.removeCompleteOrderButton();
        }
        
        // Añadir campos ocultos al formulario
        this.addGiftCardToForm();
        
        // Disparar evento de aplicación de Gift Card
        document.dispatchEvent(new CustomEvent('giftcard:applied', { 
            detail: { 
                code: this.giftcardCode,
                amount: this.appliedAmount,
                isFullDiscount: isFullDiscount
            }
        }));
    }
    
    /**
     * Actualizar la visualización del descuento aplicado
     */
    updateDiscountDisplay() {
        const discountEl = document.getElementById('giftcard-discount');
        if (discountEl) {
            discountEl.textContent = `$${this.appliedAmount.toFixed(2)}`;
        }
        
        // Añadir un mensaje si es descuento completo
        const messageEl = document.getElementById('giftcard-message');
        if (messageEl) {
            if (this.appliedAmount > 0) {
                const isFullDiscount = this.appliedAmount >= (this.getOriginalTotal() - 0.01);
                if (isFullDiscount) {
                    messageEl.textContent = '¡Tarjeta de regalo válida! Cubre completamente el total de tu compra.';
                } else {
                    messageEl.textContent = '¡Tarjeta de regalo válida! Se ha aplicado un descuento parcial.';
                }
                messageEl.className = 'giftcard-message success';
            } else {
                messageEl.textContent = '';
                messageEl.className = 'giftcard-message';
            }
        }
    }
    
    /**
     * Actualizar el total del carrito
     * @param {boolean} reset - Si es true, restablece al total original
     */
    updateCartTotal(reset = false) {
        const totalEl = document.querySelector('#total-amount');
        if (!totalEl) return;
        
        const originalTotal = this.getOriginalTotal();
        
        // Guardar el total original si aún no está guardado
        if (!window.originalTotal) {
            window.originalTotal = originalTotal;
            console.log('Total original guardado:', window.originalTotal);
        }
        
        if (reset || !this.discountApplied || this.appliedAmount <= 0) {
            // Mostrar el total original
            totalEl.textContent = `$${originalTotal.toFixed(2)} MXN`;
            console.log('Restaurando total original:', originalTotal);
        } else {
            // Calcular el nuevo total con descuento
            const isFullDiscount = this.appliedAmount >= originalTotal;
            const newTotal = isFullDiscount ? 0.00 : (originalTotal - this.appliedAmount);
            totalEl.textContent = `$${newTotal.toFixed(2)} MXN`;
            console.log('Aplicando descuento:', this.appliedAmount, 'Nuevo total:', newTotal);
        }
    }
    
    /**
     * Actualizar el descuento cuando cambia el total
     */
    updateDiscount() {
        if (this.discountApplied && this.giftcardData) {
            this.applyDiscount();
        }
    }
    
    /**
     * Remover Gift Card y su descuento
     */
    removeGiftCard() {
        console.log('Iniciando remoción de Gift Card');
        
        const detailsEl = document.getElementById('giftcard-details');
        const discountEl = document.getElementById('giftcard-discount');
        const messageEl = document.getElementById('giftcard-message');
        const codeInput = document.getElementById('giftcard-code');
        
        // Resetear la interfaz
        if (detailsEl) detailsEl.style.display = 'none';
        if (discountEl) discountEl.textContent = '$0.00';
        if (messageEl) messageEl.textContent = '';
        if (codeInput) codeInput.value = '';
        
        // Almacenar el total original antes de limpiarlo
        const originalTotal = window.originalTotal || this.getOriginalTotal();
        
        // Eliminar campos ocultos de manera más exhaustiva
        const formFields = document.querySelectorAll('input[name^="giftcard_"]');
        console.log('Eliminando campos ocultos:', formFields.length, 'campos encontrados');
        formFields.forEach(field => field.remove());
        
        // Buscar nuevamente para asegurarse de que se eliminaron
        const remainingFields = document.querySelectorAll('input[name^="giftcard_"]');
        if (remainingFields.length > 0) {
            console.log('ADVERTENCIA: Aún quedan campos de giftcard:', remainingFields.length);
            remainingFields.forEach(field => field.remove());
        }
        
        // Guardar referencias para el evento
        const oldCode = this.giftcardCode;
        const oldAmount = this.appliedAmount;
        
        // Restablecer variables
        this.giftcardCode = null;
        this.giftcardData = null;
        this.appliedAmount = 0;
        this.discountApplied = false;
        
        // Restablecer el total original de manera forzada
        this.updateCartTotal(true);
        
        // Restablecer botones
        this.togglePayPalButton(true);
        this.removeCompleteOrderButton();
        
        // Limpiar la variable global de manera explícita y completa
        window.giftcardDiscount = null;
        window.isFullDiscount = false;
        
        // Actualizar el total mostrado directamente
        const totalEl = document.querySelector('#total-amount');
        if (totalEl) {
            totalEl.textContent = `$${originalTotal.toFixed(2)} MXN`;
            console.log('Total restaurado manualmente a:', originalTotal);
        }
        
        // Mostrar mensaje
        this.showMessage('La tarjeta de regalo ha sido removida', 'info');
        
        // Lanzar evento con información completa
        document.dispatchEvent(new CustomEvent('giftcard:removed', { 
            detail: { 
                code: oldCode,
                amount: oldAmount,
                originalTotal: originalTotal
            }
        }));
        
        console.log('Finalizada remoción de Gift Card - Variables actuales:', {
            discountApplied: this.discountApplied,
            giftcardCode: this.giftcardCode,
            appliedAmount: this.appliedAmount,
            windowGiftcardDiscount: window.giftcardDiscount,
            windowIsFullDiscount: window.isFullDiscount
        });
        
        // Forzar refresco del total
        setTimeout(() => {
            if (totalEl) {
                totalEl.textContent = `$${originalTotal.toFixed(2)} MXN`;
            }
        }, 100);
    }
    
    /**
     * Agregar información de Gift Card al formulario
     */
    addGiftCardToForm() {
        const form = document.querySelector('form.contact-info') || document.querySelector('form#contact-form');
        if (!form || !this.giftcardCode || this.appliedAmount <= 0) return;
        
        // Eliminar campos existentes para evitar duplicados
        const existingFields = form.querySelectorAll('input[name^="giftcard_"]');
        existingFields.forEach(field => field.remove());
        
        // Crear campos ocultos
        const codeField = document.createElement('input');
        codeField.type = 'hidden';
        codeField.name = 'giftcard_code';
        codeField.value = this.giftcardCode;
        
        const amountField = document.createElement('input');
        amountField.type = 'hidden';
        amountField.name = 'giftcard_amount';
        amountField.value = this.appliedAmount.toFixed(2);
        
        // Añadir al formulario
        form.appendChild(codeField);
        form.appendChild(amountField);

        // Guardar en una variable global para que el botón de PayPal pueda acceder a ella
        window.giftcardDiscount = {
            code: this.giftcardCode,
            amount: this.appliedAmount
        };

        // Disparar un evento personalizado para notificar que se ha aplicado un descuento
        const discountEvent = new CustomEvent('giftcard:applied', { 
            detail: { 
                code: this.giftcardCode, 
                amount: this.appliedAmount 
            } 
        });
        document.dispatchEvent(discountEvent);
        
        console.log('Gift Card aplicada:', this.giftcardCode, 'Monto:', this.appliedAmount);
    }
    
    /**
     * Mostrar mensaje de estado
     */
    showMessage(message, type = 'info') {
        const messageEl = document.getElementById('giftcard-message');
        if (messageEl) {
            messageEl.textContent = message;
            messageEl.className = 'giftcard-message ' + type;
        }
    }
    
    /**
     * Extraer número de un string (ej. "$123.45" -> 123.45)
     */
    extractNumber(str) {
        const match = str.match(/[\d,]+\.?\d*/);
        if (match) {
            return parseFloat(match[0].replace(/,/g, ''));
        }
        return 0;
    }
    
    /**
     * Obtener el total original del carrito
     */
    getOriginalTotal() {
        // Esta función debería obtener el total original desde el carrito
        // Puedes implementarla según la estructura de tu aplicación
        const cartItems = window.cartData || [];
        let total = 0;
        
        if (cartItems.length > 0) {
            total = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        } else {
            // Alternativa: buscar un elemento con el total original
            const originalTotalEl = document.querySelector('.original-total');
            if (originalTotalEl) {
                total = this.extractNumber(originalTotalEl.textContent);
            }
        }
        
        return total;
    }
    
    /**
     * Ocultar o mostrar el botón de PayPal
     */
    togglePayPalButton(show) {
        const paypalContainer = document.getElementById('paypal-button-container');
        if (paypalContainer) {
            paypalContainer.style.display = show ? 'block' : 'none';
        }
    }
    
    /**
     * Crear botón para completar la orden directamente (sin PayPal)
     */
    createCompleteOrderButton() {
        // Remover botón existente si hay uno
        this.removeCompleteOrderButton();
        
        const paypalContainer = document.getElementById('paypal-button-container');
        if (!paypalContainer) return;
        
        const completeOrderBtn = document.createElement('button');
        completeOrderBtn.id = 'complete-order-btn';
        completeOrderBtn.className = 'complete-order-btn';
        completeOrderBtn.textContent = 'Completar Pedido';
        completeOrderBtn.style.cssText = `
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: background-color 0.3s;
        `;
        
        completeOrderBtn.addEventListener('mouseover', function() {
            this.style.backgroundColor = '#218838';
        });
        
        completeOrderBtn.addEventListener('mouseout', function() {
            this.style.backgroundColor = '#28a745';
        });
        
        completeOrderBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.processOrderWithGiftCard();
        });
        
        // Insertar después del contenedor de PayPal
        paypalContainer.parentNode.insertBefore(completeOrderBtn, paypalContainer.nextSibling);
    }
    
    /**
     * Remover el botón de completar orden
     */
    removeCompleteOrderButton() {
        const completeOrderBtn = document.getElementById('complete-order-btn');
        if (completeOrderBtn) {
            completeOrderBtn.remove();
        }
    }
    
    /**
     * Procesar la orden directamente con Gift Card (sin PayPal)
     */
    processOrderWithGiftCard() {
        // Validar el formulario antes de enviar
        const form = document.querySelector('form.contact-info') || document.querySelector('form#contact-form');
        if (!form || !form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Obtener el carrito del localStorage
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        if (!cart || cart.length === 0) {
            alert('El carrito está vacío');
            return;
        }
        
        // Mostrar mensaje de procesamiento
        const processingMessage = document.createElement('div');
        processingMessage.className = 'processing-message';
        processingMessage.textContent = 'Procesando su orden...';
        processingMessage.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            z-index: 9999;
        `;
        document.body.appendChild(processingMessage);
        
        // Crear FormData con los datos del formulario y carrito
        const formData = new FormData();
        
        // Añadir campos del formulario
        const fields = [
            'fullname', 'email', 'phone', 'street', 'colonia', 
            'city', 'state', 'postal'
        ];
        
        for (const field of fields) {
            const input = document.getElementById(field);
            if (input) {
                formData.append(field, input.value);
            } else {
                console.error(`Campo no encontrado: ${field}`);
                processingMessage.remove();
                alert(`Error: Campo faltante: ${field}`);
                return;
            }
        }
        
        // Usar un ID de pago especial para indicar que es pago con Gift Card
        formData.append('payment_id', 'GIFTCARD-FULL-PAYMENT-' + new Date().getTime());
        formData.append('payment_method', 'giftcard');
        
        // Agregar el carrito
        formData.append('cart_items', JSON.stringify(cart));
        
        // Incluir información de Gift Card
        formData.append('giftcard_code', this.giftcardCode);
        formData.append('giftcard_amount', this.appliedAmount.toFixed(2));
        formData.append('is_full_discount', 'true');
        
        // Para depuración
        console.log('Enviando orden con gift card:', {
            code: this.giftcardCode,
            amount: this.appliedAmount.toFixed(2),
            paymentMethod: 'giftcard'
        });
        
        // Enviar datos al servidor
        fetch('process_order.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Verificar si el servidor está redirigiendo (código 302 o similar)
            console.log('Response status:', response.status, response.redirected);
            
            if (response.redirected) {
                // Si hay una redirección, seguirla directamente
                window.location.href = response.url;
                return;
            }
            
            if (response.url.includes('success.html')) {
                // Si la URL ya contiene success.html, redirigir directamente
                window.location.href = response.url;
                return;
            }

            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Error en la respuesta:', text);
                    throw new Error('Error en la respuesta del servidor');
                });
            }

            // Intentar parsear como JSON solo si no hay redirección
            return response.text().then(text => {
                try {
                    // Intentar parsear como JSON
                    return JSON.parse(text);
                } catch (e) {
                    console.log('Respuesta no es JSON:', text);
                    
                    // Verificar si la respuesta contiene HTML de la página de éxito
                    if (text.includes('success.html') || text.includes('Compra Exitosa')) {
                        // Limpiar carrito y redirigir a success.html
                        localStorage.removeItem('cart');
                        window.location.href = 'success.html';
                        return null;
                    }
                    
                    // Si no es JSON ni contiene referencias a success, es un error
                    throw new Error('Formato de respuesta inesperado');
                }
            });
        })
        .then(data => {
            // Si data es null, significa que ya estamos redirigiendo
            if (data === null) return;
            
            console.log('Respuesta del servidor:', data);
            
            if (data && data.success) {
                // Limpiar carrito
                localStorage.removeItem('cart');
                localStorage.removeItem('giftcard_applied');
                
                // Redirigir a página de éxito con el ID de la orden
                window.location.href = `success.html?order_id=${data.order_id}`;
            } else if (data) {
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
    }
}

// Inicializar el manejador de Gift Cards y hacerlo accesible globalmente
const giftCardHandler = new GiftCardHandler();
// Exponerlo globalmente para que pueda ser usado por checkout.js
window.giftCardHandler = giftCardHandler; 