/**
 * Módulo de redención de Códigos Promocionales para JerSix
 * Este script maneja la verificación y aplicación de códigos promocionales
 */

class PromoCodeHandler {
    constructor() {
        this.promoCode = null;
        this.promoData = null;
        this.discountAmount = 0;
        this.discountType = null; // 'porcentaje' o 'fijo'
        this.discountApplied = false;
        
        // Inicializar eventos cuando el DOM está listo
        document.addEventListener('DOMContentLoaded', () => this.init());
    }
    
    /**
     * Inicializar el manejador de Códigos Promocionales
     */
    init() {
        // Establecer eventos en los elementos existentes en el HTML
        const applyBtn = document.getElementById('apply-promo-btn');
        if (applyBtn) {
            applyBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.validatePromoCode();
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
     * Verificar si hay una tarjeta de regalo en el carrito
     */
    hasGiftCardInCart() {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        return cart.some(item => item.isGiftCard || (item.title && (item.title.includes('Tarjeta de Regalo') || item.title.includes('Gift Card'))));
    }
    
    /**
     * Validar un código promocional
     */
    validatePromoCode() {
        // Si ya hay un código aplicado, no permitir aplicar otro
        if (this.discountApplied) {
            this.showMessage('Ya tiene un código aplicado', 'error');
            return;
        }
        
        // Verificar si hay una tarjeta de regalo en el carrito
        if (this.hasGiftCardInCart()) {
            this.showMessage('No se pueden usar códigos promocionales en compras de tarjetas de regalo', 'error');
            return;
        }
        
        const codeInput = document.getElementById('promo-code');
        const messageEl = document.getElementById('promo-message');
        
        if (!codeInput || !messageEl) return;
        
        const code = codeInput.value.trim().toUpperCase();
        if (!code) {
            this.showMessage('Ingrese un código', 'error');
            return;
        }
        
        this.showMessage('Verificando...', 'info');
        
        // Enviar solicitud al servidor
        fetch('validate_promocode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ code })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Si el código es válido, limpiar cualquier mensaje de error
                if (messageEl) {
                    messageEl.textContent = '';
                    messageEl.className = 'promo-message';
                }
                
                this.promoCode = code;
                this.promoData = data.data;
                this.discountType = data.data.tipo_descuento;
                this.showPromoCodeSuccess();
                this.applyDiscount();
                
                // Deshabilitar pero NO ocultar el input y el botón
                const applyBtn = document.getElementById('apply-promo-btn');
                if (codeInput) {
                    codeInput.disabled = true;
                    codeInput.style.backgroundColor = '#f8f8f8';
                    codeInput.style.color = '#666';
                }
                if (applyBtn) {
                    applyBtn.disabled = true;
                    applyBtn.style.backgroundColor = '#b0c4de';
                }
            } else {
                // Simplificar mensajes de error
                let errorMsg = 'Código no válido';
                if (data.message) {
                    // Simplificar mensajes comunes
                    if (data.message.includes('expirado')) errorMsg = 'Código expirado';
                    else if (data.message.includes('límite')) errorMsg = 'Código agotado';
                    else if (data.message.includes('no encontrado')) errorMsg = 'Código no existe';
                    else errorMsg = data.message;
                }
                this.showMessage(errorMsg, 'error');
                
                // Asegurarse de que el mensaje de éxito no se muestre
                const detailsEl = document.getElementById('promo-details');
                if (detailsEl) {
                    detailsEl.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showMessage('Error de verificación', 'error');
            
            // Asegurarse de que el mensaje de éxito no se muestre
            const detailsEl = document.getElementById('promo-details');
            if (detailsEl) {
                detailsEl.style.display = 'none';
            }
        });
    }
    
    /**
     * Mostrar mensaje de éxito del código promocional
     */
    showPromoCodeSuccess() {
        const detailsEl = document.getElementById('promo-details');
        const messageEl = document.getElementById('promo-message');
        
        // Limpiar cualquier mensaje de error anterior
        if (messageEl) {
            messageEl.textContent = '';
            messageEl.className = 'promo-message';
        }
        
        if (detailsEl) {
            detailsEl.style.display = 'block';
        }
    }
    
    /**
     * Aplicar descuento del código promocional
     */
    applyDiscount() {
        if (!this.promoData) return;
        
        // Obtener el total actual del carrito (sin envío)
        const subtotalElement = document.getElementById('subtotal-amount');
        let subtotalText = subtotalElement ? subtotalElement.textContent : '0';
        const subtotal = this.extractNumber(subtotalText);
        
        // Calcular el descuento según el tipo
        if (this.discountType === 'porcentaje') {
            // Aplicar porcentaje
            this.discountAmount = (subtotal * this.promoData.descuento) / 100;
        } else {
            // Descuento fijo
            this.discountAmount = Math.min(this.promoData.descuento, subtotal);
        }
        
        // Actualizar el total del carrito
        this.updateCartTotal();
        
        // Indicar que hay un descuento aplicado
        this.discountApplied = true;
        
        // Añadir campos ocultos al formulario
        this.addPromoCodeToForm();
        
        // Mostrar la fila de descuento en el resumen
        const discountContainer = document.getElementById('promo-discount-container');
        if (discountContainer) {
            discountContainer.style.display = 'flex';
            discountContainer.style.justifyContent = 'space-between';
            
            const discountAmount = document.getElementById('promo-discount-amount');
            if (discountAmount) {
                discountAmount.textContent = `-$${this.discountAmount.toFixed(2)} MXN`;
            }
        }
        
        // Disparar evento de aplicación de Código Promocional
        document.dispatchEvent(new CustomEvent('promocode:applied', { 
            detail: { 
                code: this.promoCode,
                amount: this.discountAmount,
                type: this.discountType
            }
        }));
    }
    
    /**
     * Actualizar el total del carrito
     */
    updateCartTotal() {
        const totalEl = document.getElementById('total-amount');
        if (!totalEl) return;
        
        // Obtener el total actual
        const currentTotal = this.getOriginalTotal();
        
        // Calcular el nuevo total con el descuento
        const newTotal = Math.max(currentTotal - this.discountAmount, 0);
        
        // Actualizar el elemento del DOM
        totalEl.textContent = `$${newTotal.toFixed(2)} MXN`;
        
        console.log('Total actualizado con descuento de código promocional:', newTotal);
    }
    
    /**
     * Actualizar el descuento si cambia el total
     */
    updateDiscount() {
        if (!this.discountApplied || !this.promoData) return;
        
        // Si es descuento porcentual, recalcular
        if (this.discountType === 'porcentaje') {
            const subtotalElement = document.getElementById('subtotal-amount');
            let subtotalText = subtotalElement ? subtotalElement.textContent : '0';
            const subtotal = this.extractNumber(subtotalText);
            
            this.discountAmount = (subtotal * this.promoData.descuento) / 100;
        }
        
        // Actualizar el total del carrito
        this.updateCartTotal();
        
        // Actualizar el monto del descuento mostrado
        const discountAmount = document.getElementById('promo-discount-amount');
        if (discountAmount) {
            discountAmount.textContent = `-$${this.discountAmount.toFixed(2)} MXN`;
        }
        
        // Actualizar los campos ocultos
        this.addPromoCodeToForm();
    }
    
    /**
     * Agregar información del código promocional al formulario
     */
    addPromoCodeToForm() {
        const form = document.querySelector('form.contact-info') || document.querySelector('form#contact-form');
        if (!form || !this.promoCode || this.discountAmount <= 0) return;
        
        // Eliminar campos existentes para evitar duplicados
        this.removePromoCodeFromForm();
        
        // Crear campos ocultos
        const codeField = document.createElement('input');
        codeField.type = 'hidden';
        codeField.name = 'promo_code';
        codeField.value = this.promoCode;
        
        const amountField = document.createElement('input');
        amountField.type = 'hidden';
        amountField.name = 'promo_discount';
        amountField.value = this.discountAmount.toFixed(2);
        
        const typeField = document.createElement('input');
        typeField.type = 'hidden';
        typeField.name = 'promo_type';
        typeField.value = this.discountType;
        
        // Añadir al formulario
        form.appendChild(codeField);
        form.appendChild(amountField);
        form.appendChild(typeField);
        
        // Guardar en una variable global para que el botón de PayPal pueda acceder a ella
        window.promoCodeDiscount = {
            code: this.promoCode,
            amount: this.discountAmount,
            type: this.discountType
        };
        
        console.log('Código promocional aplicado:', this.promoCode, 'Descuento:', this.discountAmount, 'Tipo:', this.discountType);
    }
    
    /**
     * Eliminar campos del código promocional del formulario
     */
    removePromoCodeFromForm() {
        const form = document.querySelector('form.contact-info') || document.querySelector('form#contact-form');
        if (!form) return;
        
        // Eliminar campos existentes
        const fieldNames = ['promo_code', 'promo_discount', 'promo_type'];
        fieldNames.forEach(name => {
            const existingFields = form.querySelectorAll(`input[name="${name}"]`);
            existingFields.forEach(field => field.remove());
        });
        
        // Limpiar variable global
        window.promoCodeDiscount = null;
    }
    
    /**
     * Mostrar un mensaje en la interfaz
     */
    showMessage(message, type = 'info') {
        const messageEl = document.getElementById('promo-message');
        if (messageEl) {
            messageEl.textContent = message;
            messageEl.className = `promo-message ${type}`;
        }
    }
    
    /**
     * Extraer número de una cadena de texto
     */
    extractNumber(str) {
        if (!str) return 0;
        const matches = str.match(/[\d.]+/);
        return matches ? parseFloat(matches[0]) : 0;
    }
    
    /**
     * Obtener el total original del carrito
     */
    getOriginalTotal() {
        // Intentar obtener desde la variable global
        if (window.originalTotal) {
            return window.originalTotal;
        }
        
        // Alternativa: calcular sumando subtotal y envío
        const subtotalEl = document.getElementById('subtotal-amount');
        const shippingEl = document.getElementById('shipping-amount');
        
        let subtotal = 0;
        let shipping = 0;
        
        if (subtotalEl) {
            subtotal = this.extractNumber(subtotalEl.textContent);
        }
        
        if (shippingEl) {
            shipping = this.extractNumber(shippingEl.textContent);
        }
        
        return subtotal + shipping;
    }
}

// Inicializar el manejador de códigos promocionales
window.promoCodeHandler = new PromoCodeHandler(); 