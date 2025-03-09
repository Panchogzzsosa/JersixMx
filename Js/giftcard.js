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

        // Get form values
        const giftcardData = {
            type: 'giftcard',
            amount: selectedAmount.dataset.amount,
            recipientName: document.getElementById('recipient-name').value,
            recipientEmail: document.getElementById('recipient-email').value,
            message: document.getElementById('message').value,
            senderName: document.getElementById('sender-name').value
        };

        try {
            // Get current path to determine image path
            const currentPath = window.location.pathname;
            const isInProductosEquipos = currentPath.includes('/Productos-equipos/');
            const imagePath = isInProductosEquipos ? '../img/LogoNav.png' : './img/LogoNav.png';

            // Create cart item
            const cartItem = {
                id: 'giftcard-' + Date.now(),
                title: `Tarjeta de Regalo JerSix $${giftcardData.amount} MXN`,
                price: parseFloat(giftcardData.amount),
                quantity: 1,
                image: imagePath,
                isGiftCard: true,
                details: giftcardData
            };

            // Add to cart using the ShoppingCart instance
            if (window.shoppingCart) {
                window.shoppingCart.cart.push(cartItem);
                window.shoppingCart.saveCart();
                window.shoppingCart.updateCartIcon();
                window.shoppingCart.updateCartModal();
                showNotification('Tarjeta de regalo agregada al carrito', true);
                
                // Reset form and preview
                form.reset();
                previewRecipientName.textContent = 'Nombre del Destinatario';
                previewMessage.textContent = 'Tu mensaje personal aparecerá aquí';
                previewSenderName.textContent = 'Tu Nombre';
                amountOptions.forEach(opt => opt.classList.remove('selected'));
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