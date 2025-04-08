// Cart state management
class ShoppingCart {
    constructor() {
        this.cart = JSON.parse(localStorage.getItem('cart')) || [];
        this.modal = document.querySelector('.cart-modal');
        this.overlay = document.querySelector('.cart-overlay');
        if (!this.modal || !this.overlay) {
            this.createCartElements();
        }
        this.bindEvents();
        this.verifyPrices();
        this.updateCartIcon();
        this.updateCartModal();

        // Escuchar el evento addToCart
        document.addEventListener('addToCart', (event) => {
            if (event.detail.isCustomEvent) {
                this.addToCartWithCustomization(event.detail);
            } else {
                this.addToCart(event);
            }
        });
    }

    async verifyPrices() {
        const updatedCart = [];
        const removedItems = [];

        for (const item of this.cart) {
            // No verificar precios para tarjetas de regalo
            if (item.isGiftCard || (item.title && item.title.includes('Tarjeta de Regalo'))) {
                updatedCart.push(item);
                continue;
            }
            
            try {
                const response = await fetch(`get_product_price.php?id=${encodeURIComponent(item.title)}`, {
                    method: 'GET'
                });

                if (!response.ok) {
                    // Si hay un error en la petición, mantener el item en el carrito
                    updatedCart.push(item);
                    continue;
                }

                const data = await response.json();
                const currentPrice = parseFloat(data.price);

                // Si el precio es 0 o no es un número válido, mantener el precio original
                if (isNaN(currentPrice) || currentPrice === 0) {
                    updatedCart.push(item);
                    continue;
                }

                // Comparar precios teniendo en cuenta la personalización
                let expectedPrice = currentPrice;
                if (item.personalization) {
                    expectedPrice += 100; // Precio de personalización
                    if (item.personalization.patch) {
                        expectedPrice += 50; // Precio del parche
                    }
                }

                // Usar una comparación con tolerancia para evitar problemas con decimales
                const priceDifference = Math.abs(expectedPrice - item.price);
                if (priceDifference < 0.01) {
                    updatedCart.push(item);
                } else {
                    removedItems.push(item.title);
                }
            } catch (error) {
                console.error('Error verifying price:', error);
                // Si hay un error, mantener el item en el carrito
                updatedCart.push(item);
            }
        }

        if (removedItems.length > 0) {
            this.cart = updatedCart;
            this.saveCart();
            this.showNotification(
                `Los siguientes productos fueron removidos del carrito debido a cambios en el precio: ${removedItems.join(', ')}`,
                'error'
            );
        }
    }

    createCartElements() {
        // Create cart modal if it doesn't exist
        if (!this.modal) {
            this.modal = document.createElement('div');
            this.modal.className = 'cart-modal';
            document.body.appendChild(this.modal);
        }
    
        // Create overlay if it doesn't exist
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.className = 'cart-overlay';
            document.body.appendChild(this.overlay);
        }
    }

    bindEvents() {
        // Cart icon click event
        const cartIcon = document.querySelector('.cart-icon');
        if (cartIcon) {
            cartIcon.addEventListener('click', () => this.toggleCart());
        }

        // Close cart when clicking overlay
        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.closeCart());
        }

        // Close cart when pressing ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeCart();
        });
    }

    addToCart(event) {
        // Solo procesar si no es un evento personalizado
        if (event.detail && event.detail.isCustomEvent) {
            return;
        }

        // Check if it's a direct call with item data (for gift cards)
        if (typeof event === 'object' && !event.target) {
            const item = event;
            // Asegurar que las gift cards tengan un valor para size
            if (item.isGiftCard && !item.size) {
                item.size = "N/A";
            }
            this.cart.push(item);
            this.saveCart();
            this.updateCartIcon();
            this.updateCartModal();
            return;
        }

        const productContainer = event.target.closest('.product-detail') || event.target.closest('.product-card') || event.target.closest('.giftcard-container');
        if (!productContainer) return;

        const titleElement = productContainer.querySelector('h1.product-title') || productContainer.querySelector('h3.product-title') || productContainer.querySelector('.product-title');
        const title = titleElement ? titleElement.textContent.trim() : null;
        const price = parseFloat(productContainer.querySelector('.product-price')?.textContent.replace('$', '').trim());
        const currentPath = window.location.pathname;
        const isInProductosEquipos = currentPath.includes('/Productos-equipos/');
        const image = productContainer.querySelector('.product-image')?.src || 
            (productContainer.classList.contains('giftcard-container') ? 
                (isInProductosEquipos ? '../img/LogoNav.png' : './img/LogoNav.png') : 
                (isInProductosEquipos ? '../img/Jerseys/' : './img/Jerseys/') + productContainer.querySelector('.product-image')?.getAttribute('data-image-name'));
        const size = productContainer.querySelector('.size-option.selected')?.textContent;
        const quantity = parseInt(productContainer.querySelector('.quantity-input')?.value || '1');
        const isGiftCard = productContainer.classList.contains('giftcard-container');

        if (!title || !price) {
            console.error('Missing required product information');
            return;
        }

        // Skip size validation for gift cards
        if (!isGiftCard && !size) {
            this.showNotification('Por favor selecciona una talla', 'error');
            return;
        }

        const cartItem = {
            id: Date.now().toString(),
            title,
            price,
            size: isGiftCard ? null : size,
            quantity,
            image,
            isGiftCard,
            personalization: null
        };

        this.cart.push(cartItem);
        this.saveCart();
        this.updateCartIcon();
        this.updateCartModal();
        this.showNotification('Producto agregado al carrito', 'success');
    }

    updateItemQuantity(itemId, change) {
        const itemIndex = this.cart.findIndex(item => item.id === itemId);
        if (itemIndex !== -1) {
            const newQuantity = this.cart[itemIndex].quantity + change;
            if (newQuantity > 0) {
                this.cart[itemIndex].quantity = newQuantity;
                this.saveCart();
                this.updateCartModal();
                this.updateCartIcon();
            } else if (newQuantity === 0) {
                this.removeFromCart(itemId);
            }
        }
    }

    removeFromCart(itemId) {
        this.cart = this.cart.filter(item => item.id !== itemId);
        this.saveCart();
        this.updateCartModal();
        this.updateCartIcon();
    }

    toggleCart() {
        if (this.modal.classList.contains('open')) {
            this.closeCart();
        } else {
            this.openCart();
        }
    }

    openCart() {
        // Verificar si hay gift cards en el carrito
        const hasGiftCards = this.cart.some(item => 
            item.isGiftCard || 
            (item.title && (item.title.includes('Tarjeta de Regalo') || item.title.includes('Gift Card')))
        );
        
        // Si hay gift cards, abrir el carrito sin verificar precios
        if (hasGiftCards) {
            this.modal.classList.add('open');
            this.overlay.classList.add('show');
            this.updateCartModal();
            document.body.style.overflow = 'hidden';
            return;
        }
        
        // Si no hay gift cards, proceder normalmente
        this.verifyPrices().then(() => {
            this.modal.classList.add('open');
            this.overlay.classList.add('show');
            this.updateCartModal();
            document.body.style.overflow = 'hidden';
        });
    }

    closeCart() {
        this.modal.classList.remove('open');
        this.overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    updateCartModal() {
        if (!this.modal) return;

        const cartContent = document.createElement('div');
        cartContent.className = 'cart-content';

        if (this.cart.length === 0) {
            cartContent.innerHTML = `
                <div class="cart-empty">
                    <p>Tu carrito está vacío</p>
                </div>
            `;
        } else {
            const total = this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            
            cartContent.innerHTML = `
                <div class="cart-items">
                    ${this.cart.map(item => {
                        let personalizationInfo = '';
                        if (item.personalization) {
                            personalizationInfo = `
                                <div class="personalization-info">
                                    <p>Nombre: ${item.personalization.name}</p>
                                    <p>Número: ${item.personalization.number}</p>
                                </div>
                            `;
                        }
                        return this.generateCartItemHTML(item, personalizationInfo);
                    }).join('')}
                </div>
                <div class="cart-footer">
                    <div class="cart-total">
                        Total: $${total.toFixed(2)}
                    </div>
                    <button class="checkout-button">Proceder al pago</button>
                </div>
            `;


            // Add event listeners for quantity adjustment buttons
            cartContent.querySelectorAll('.quantity-controls button').forEach(button => {
                button.addEventListener('click', (e) => {
                    const itemId = e.target.closest('.cart-item').dataset.id;
                    if (e.target.classList.contains('decrease')) {
                        this.updateItemQuantity(itemId, -1);
                    } else if (e.target.classList.contains('increase')) {
                        this.updateItemQuantity(itemId, 1);
                    }
                });
            });

            // Add event listeners for remove buttons
            cartContent.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const itemId = e.target.closest('.cart-item').dataset.id;
                    if (itemId) {
                        this.removeFromCart(itemId);
                    }
                });
            });
        }

        // Clear and update modal content
        this.modal.innerHTML = `
            <button class="close-cart">×</button>
            <h2>Tu Carrito</h2>
            ${cartContent.outerHTML}
        `;

        // Add close button event listener
        const closeButton = this.modal.querySelector('.close-cart');
        if (closeButton) {
            closeButton.addEventListener('click', () => this.closeCart());
        }

        // Re-attach event listeners after updating modal content
        if (this.cart.length > 0) {
            this.modal.querySelectorAll('.quantity-controls button').forEach(button => {
                button.addEventListener('click', (e) => {
                    const itemId = e.target.closest('.cart-item').dataset.id;
                    if (e.target.classList.contains('decrease')) {
                        this.updateItemQuantity(itemId, -1);
                    } else if (e.target.classList.contains('increase')) {
                        this.updateItemQuantity(itemId, 1);
                    }
                });
            });

            this.modal.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const itemId = e.target.closest('.cart-item').dataset.id;
                    if (itemId) {
                        this.removeFromCart(itemId);
                    }
                });
            });

            // Add event listener for checkout button
            const checkoutButton = this.modal.querySelector('.checkout-button');
            if (checkoutButton) {
                checkoutButton.addEventListener('click', () => {
                    const currentPath = window.location.pathname;
const isInProductosEquipos = currentPath.includes('/Productos-equipos/');
window.location.href = isInProductosEquipos ? '../checkout.html' : 'checkout.html';
                });
            }
        }
    }

    generateCartItemHTML(item) {
        let personalizationHtml = '';
        if (item.personalization) {
            personalizationHtml = `
                <div class="personalization-info" style="margin: 10px 0; padding: 10px; background: #f8f8f8; border-radius: 5px;">
                    <p style="margin: 0; font-size: 14px; color: #333;">
                        <strong>Personalización:</strong><br>
                        Nombre: ${item.personalization.name}<br>
                        Número: ${item.personalization.number}
                        ${item.personalization.patch ? '<br>✓ Con parche' : ''}
                    </p>
                </div>
            `;
        }
        
        if (item.isGiftCard && item.details) {
            // Sanitizar textos para evitar problemas de XSS y JSON
            const sanitizeText = (text) => {
                if (!text) return '';
                return String(text)
                       .replace(/[<>]/g, '') // Eliminar < y >
                       .replace(/[\u0000-\u001F\u007F-\u009F]/g, '') // Eliminar caracteres de control
                       .trim();
            };
            
            const recipientName = sanitizeText(item.details.recipientName || '');
            const recipientEmail = sanitizeText(item.details.recipientEmail || '');
            const message = sanitizeText(item.details.message || '');
            const senderName = sanitizeText(item.details.senderName || '');
            
            personalizationHtml = `
                <div class="personalization-info" style="margin: 10px 0; padding: 10px; background: #f8f8f8; border-radius: 5px;">
                    <p style="margin: 0; font-size: 14px; color: #333;">
                        <strong>Detalles de la Gift Card:</strong><br>
                        Para: ${recipientName}<br>
                        Email: ${recipientEmail}<br>
                        ${message ? `Mensaje: ${message}<br>` : ''}
                        De: ${senderName}
                    </p>
                </div>
            `;
        }

        return `
            <div class="cart-item" data-id="${item.id}">
                <img src="${item.image}" alt="${item.title}" ${item.isGiftCard ? 'class="giftcard-image"' : ''}>
                <div class="cart-item-details">
                    <h4>${item.title}</h4>
                    ${!item.isGiftCard && item.size ? `<p class="item-size">Talla: ${item.size}</p>` : ''}
                    ${personalizationHtml}
                    <p class="item-price">Precio: $${item.price.toFixed(2)}</p>
                    <div class="quantity-controls">
                        <button class="decrease">-</button>
                        <span>${item.quantity}</span>
                        <button class="increase">+</button>
                    </div>
                </div>
                <button class="remove-item">×</button>
            </div>
        `;
    }

    saveCart() {
        localStorage.setItem('cart', JSON.stringify(this.cart));
    }

    updateCartIcon() {
        const cartCount = this.cart.reduce((total, item) => total + item.quantity, 0);
        const cartIcon = document.querySelector('.cart-icon');
        if (cartIcon) {
            const countBadge = cartIcon.querySelector('.cart-count') || document.createElement('span');
            countBadge.className = 'cart-count';
            countBadge.textContent = cartCount;
            if (!cartIcon.querySelector('.cart-count')) {
                cartIcon.appendChild(countBadge);
            }
        }
    }

    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 2000);
        }, 100);
    }

    addToCartWithCustomization(productData) {
        if (!productData) {
            console.error('No se proporcionaron datos del producto');
            return;
        }

        const cartItem = {
            id: Date.now().toString(),
            title: productData.title,
            price: productData.price,
            size: productData.size,
            quantity: productData.quantity,
            image: productData.image,
            personalization: productData.personalization ? {
                name: productData.personalization.name,
                number: productData.personalization.number,
                patch: productData.personalization.patch
            } : null
        };

        console.log('Agregando item al carrito con personalización:', cartItem);

        this.cart.push(cartItem);
        this.saveCart();
        this.updateCartIcon();
        this.updateCartModal();
        this.showNotification('Producto agregado al carrito', 'success');
    }
}

// Initialize shopping cart
const shoppingCart = new ShoppingCart();