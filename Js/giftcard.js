document.addEventListener('DOMContentLoaded', function() {
    // Initialize shopping cart
    window.shoppingCart = new ShoppingCart();

    const form = document.getElementById('giftcard-form');
    const amountOptions = document.querySelectorAll('.amount-option');
    const previewAmount = document.querySelector('.preview-amount');
    const previewRecipientName = document.getElementById('preview-recipient-name');
    const previewMessage = document.getElementById('preview-message');
    const previewSenderName = document.getElementById('preview-sender-name');

    // Handle amount selection
    amountOptions.forEach(option => {
        option.addEventListener('click', function() {
            amountOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            previewAmount.textContent = `$${this.dataset.amount} MXN`;
        });
    });

    // Handle real-time preview updates
    document.getElementById('recipient-name').addEventListener('input', function() {
        previewRecipientName.textContent = this.value || 'Nombre del Destinatario';
    });

    document.getElementById('message').addEventListener('input', function() {
        previewMessage.textContent = this.value || 'Tu mensaje personal aparecerá aquí';
    });

    document.getElementById('sender-name').addEventListener('input', function() {
        previewSenderName.textContent = this.value || 'Tu Nombre';
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get selected amount
        const selectedAmount = document.querySelector('.amount-option.selected');
        if (!selectedAmount) {
            showNotification('Por favor, seleccione un monto para la tarjeta de regalo', false);
            return;
        }

        // Función para sanitizar texto
        function sanitizeText(text) {
            if (!text) return '';
            // Convertir a string primero
            const str = String(text);
            // Convertir HTML a entidades para evitar problemas con JSON
            return str.replace(/&/g, "&amp;")
                      .replace(/</g, "&lt;")
                      .replace(/>/g, "&gt;")
                      .replace(/"/g, "&quot;")
                      .replace(/'/g, "&#039;")
                      .replace(/[\u0000-\u001F\u007F-\u009F]/g, '') // Eliminar caracteres de control
                      .trim();
        }

        // Get form values and sanitize them
        const giftcardData = {
            type: 'giftcard',
            amount: selectedAmount.dataset.amount,
            recipientName: sanitizeText(document.getElementById('recipient-name').value),
            recipientEmail: document.getElementById('recipient-email').value.trim(),
            message: sanitizeText(document.getElementById('message').value),
            senderName: sanitizeText(document.getElementById('sender-name').value)
        };

        try {
            // Validar campos requeridos
            if (!giftcardData.recipientName) {
                showNotification('Por favor, ingrese el nombre del destinatario', false);
                return;
            }

            if (!giftcardData.recipientEmail) {
                showNotification('Por favor, ingrese el correo electrónico del destinatario', false);
                return;
            }

            if (!giftcardData.senderName) {
                showNotification('Por favor, ingrese su nombre', false);
                return;
            }

            // Get current path to determine image path
            const currentPath = window.location.pathname;
            const isInProductosEquipos = currentPath.includes('/Productos-equipos/');
            const imagePath = isInProductosEquipos ? '../img/LogoNav.png' : './img/LogoNav.png';

            // Create cart item
            const cartItem = {
                id: 'giftcard-' + Date.now(),
                product_id: 66, // ID fijo para gift card
                title: `Tarjeta de Regalo JersixMx $${giftcardData.amount} MXN`,
                price: 0, // Precio fijo para evitar verificaciones
                realPrice: parseFloat(giftcardData.amount), // Guardamos el precio real en otra propiedad
                quantity: 1,
                image: './img/LogoNav.png', // Siempre usar la ruta relativa base
                isGiftCard: true,
                size: "N/A",
                details: giftcardData,
                // Añadir todas las propiedades necesarias para que se procese correctamente
                _isGiftCard: true,
                _protectFromRemoval: true
            };

            console.log("Añadiendo tarjeta de regalo:", cartItem);

            // Add to cart using the ShoppingCart instance
            if (window.shoppingCart) {
                // Validación final antes de agregar al carrito
                if (!cartItem.details.recipientName.trim() || 
                    !cartItem.details.recipientEmail.trim() || 
                    !cartItem.details.senderName.trim()) {
                    showNotification('Todos los campos marcados con * son obligatorios', false);
                    return;
                }
                
                // Agregar directamente para evitar verificaciones
                const currentCart = JSON.parse(localStorage.getItem('cart') || '[]');
                currentCart.push(cartItem);
                localStorage.setItem('cart', JSON.stringify(currentCart));
                
                // Actualizar la interfaz del carrito
                if (window.shoppingCart) {
                    window.shoppingCart.cart = currentCart;
                    window.shoppingCart.updateCartIcon();
                    window.shoppingCart.updateCartModal();
                }
                
                showNotification('Tarjeta de regalo agregada al carrito', true);
                
                // Reset form and preview
                form.reset();
                previewRecipientName.textContent = 'Nombre del Destinatario';
                previewMessage.textContent = 'Tu mensaje personal aparecerá aquí';
                previewSenderName.textContent = 'Tu Nombre';
                amountOptions.forEach(opt => opt.classList.remove('selected'));
                
                // Seleccionar la primera opción por defecto
                amountOptions[0].click();
            } else {
                throw new Error('Cart not initialized');
            }
        } catch (error) {
            console.error('Error adding gift card to cart:', error);
            showNotification('Error al agregar al carrito: ' + error.message, false);
        }
    });

    // Select first amount option by default
    amountOptions[0].click();

    // Notification function
    function showNotification(message, isSuccess = true) {
        const notification = document.createElement('div');
        notification.className = `notification ${isSuccess ? 'success' : 'error'}`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
});