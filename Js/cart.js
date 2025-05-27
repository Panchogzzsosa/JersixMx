//
// Fix de emergencia para Gift Cards - Versión para hosting
// Añadir este código al inicio del archivo
//
(function() {
    // Guardar la implementación original de JSON.parse
    const originalJSONParse = JSON.parse;
    
    // Reemplazar JSON.parse con nuestra versión personalizada
    JSON.parse = function(text, reviver) {
        // Primero llamar a la versión original
        let result;
        try {
            result = originalJSONParse(text, reviver);
        } catch (e) {
            console.error('Error al analizar JSON:', e);
            return [];
        }
        
        // Si es un array (probablemente el carrito), proteger las gift cards
        if (Array.isArray(result)) {
            // Filtrar para conservar las gift cards
            return result.filter(item => {
                // Gift cards siempre deben mantenerse
                if (item && (
                    item.isGiftCard === true || 
                    (item.title && (
                        item.title.toLowerCase().includes('tarjeta') || 
                        item.title.toLowerCase().includes('gift')
                    )) ||
                    (item.product_id === 66)
                )) {
                    console.log('Manteniendo gift card en carrito:', item.title);
                    return true;
                }
                return true; // Mantener todos los demás elementos también
            });
        }
        
        return result;
    };
})();

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
        if (typeof event === 'object' && !event.target && event.detail) {
            const item = event.detail;
            if (item.isGiftCard) {
                item.quantity = 1; // Forzar cantidad a 1 solo para gift cards
            }
            this.cart.push(item);
            this.saveCart();
            this.updateCartIcon();
            this.updateCartModal();
            this.showNotification('Producto agregado al carrito', 'success');
            return;
        }

        const productContainer = event.target.closest('.product-detail') || event.target.closest('.product-card') || event.target.closest('.giftcard-container');
        if (!productContainer) return;

        const titleElement = productContainer.querySelector('h1.product-title') || productContainer.querySelector('h3.product-title') || productContainer.querySelector('.product-title');
        const title = titleElement ? titleElement.textContent.trim() : null;
        const price = parseFloat(productContainer.querySelector('.product-price')?.textContent.replace('$', '').replace(' MXN', '').replace(',', '').trim());
        const currentPath = window.location.pathname;
        const isInProductosEquipos = currentPath.includes('/Productos-equipos/');
        const image = productContainer.querySelector('.product-image')?.src || 
            (productContainer.classList.contains('giftcard-container') ? 
                (isInProductosEquipos ? '../img/LogoNav.png' : './img/LogoNav.png') : 
                (isInProductosEquipos ? '../img/Jerseys/' : './img/Jerseys/') + productContainer.querySelector('.product-image')?.getAttribute('data-image-name'));
        const size = productContainer.querySelector('.size-option.selected')?.textContent;
        const isGiftCard = productContainer.classList.contains('giftcard-container');
        const quantity = isGiftCard ? 1 : parseInt(productContainer.querySelector('.quantity-input')?.value || '1');
        const isMysteryBox = title?.toLowerCase().includes('mystery box');

        // Obtener el product_id
        let product_id = null;
        
        if (isMysteryBox) {
            product_id = 65;
        } else {
            // Intentar obtener el product_id de diferentes fuentes
            if (productContainer.dataset.productId) {
                product_id = parseInt(productContainer.dataset.productId);
            } else if (productContainer.querySelector('.product-price')?.dataset.productId) {
                product_id = parseInt(productContainer.querySelector('.product-price').dataset.productId);
            } else {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('id')) {
                    product_id = parseInt(urlParams.get('id'));
                }
            }
        }

        if (!title || !price) {
            console.error('Missing required product information');
            return;
        }

        // Skip size validation for gift cards
        if (!isGiftCard && !isMysteryBox && !size) {
            this.showNotification('Por favor selecciona una talla', 'error');
            return;
        }

        // Generar un ID único para este item específico
        const uniqueItemId = Date.now().toString() + '-' + Math.random().toString(36).substr(2, 5);

        const cartItem = {
            id: uniqueItemId,
            product_id: product_id,
            title: title,
            price: price,
            size: isGiftCard ? null : size,
            quantity: isGiftCard ? 1 : quantity, // Forzar cantidad a 1 solo para gift cards
            image: image,
            isGiftCard: isGiftCard,
            personalization: null,
            mysteryBoxType: isMysteryBox ? productContainer.querySelector('.tipo-option.selected')?.dataset.tipo : null
        };

        console.log('Agregando item al carrito:', cartItem);

        this.cart.push(cartItem);
        this.saveCart();
        this.updateCartIcon();
        this.updateCartModal();
        this.showNotification('Producto agregado al carrito', 'success');
    }

    updateItemQuantity(itemId, change) {
        const itemIndex = this.cart.findIndex(item => item.id === itemId);
        if (itemIndex !== -1) {
            // No permitir cambios de cantidad para gift cards
            if (this.cart[itemIndex].isGiftCard) {
                return;
            }
            
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
            // Calcular el total considerando los precios reales de las gift cards
            const total = this.cart.reduce((sum, item) => {
                let itemPrice = item.price;
                
                // Si es una gift card, usar el precio real
                if (item.isGiftCard) {
                    if (item.realPrice && item.realPrice > 0) {
                        itemPrice = item.realPrice;
                    } else if (item.price === 0 && item.title) {
                        // Intentar extraer el precio del título
                        const priceMatch = item.title.match(/\$(\d+)/);
                        if (priceMatch && priceMatch[1]) {
                            itemPrice = parseFloat(priceMatch[1]);
                        }
                    }
                }
                
                return sum + (itemPrice * item.quantity);
            }, 0);
            
            cartContent.innerHTML = `
                <div class="cart-items">
                    ${this.cart.map(item => {
                        let personalizationInfo = '';
                        if (item.personalization) {
                            personalizationInfo = `
                                <div class="personalization-info" style="
                                    margin-top: 10px;
                                    padding: 15px;
                                    background-color: white;
                                    border-left: 4px solid #2c3e50;
                                    border-radius: 4px;
                                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                    <h4 style="
                                        margin: 0 0 10px 0;
                                        font-size: 16px;
                                        color: #2c3e50;
                                        font-weight: 500;">Personalización</h4>
                                    <p style="
                                        margin: 5px 0;
                                        color: #666;
                                        font-size: 14px;
                                        line-height: 1.5;">
                                        <span style="color: #666;">Nombre: ${item.personalization.name}</span><br>
                                        <span style="color: #666;">Número: ${item.personalization.number}</span><br>
                                        ${item.personalization.patch ? '<span style="color: #666;">Con parche</span>' : ''}
                                    </p>
                                </div>
                            `;
                        }

                        // Agregar información del tipo de Mystery Box si existe
                        if (item.mysteryBoxType) {
                            personalizationInfo += `
                                <div class="personalization-info" style="
                                    margin-top: 10px;
                                    padding: 15px;
                                    background-color: white;
                                    border-left: 4px solid #e74c3c;
                                    border-radius: 4px;
                                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                    <p style="
                                        margin: 5px 0;
                                        color: #666;
                                        font-size: 16px;
                                        line-height: 1.5;">
                                        Tipo: ${item.mysteryBoxType.charAt(0).toUpperCase() + item.mysteryBoxType.slice(1)}
                                    </p>
                                    ${item.unwantedTeam ? `
                                        <div style="
                                            margin-top: 15px;
                                            padding: 15px;
                                            background-color: #fff5f5;
                                            border-radius: 4px;">
                                            <p style="
                                                margin: 0;
                                                color: #e74c3c;
                                                font-size: 16px;
                                                margin-bottom: 5px;">
                                                Equipo no deseado:
                                            </p>
                                            <p style="
                                                margin: 0;
                                                color: #666;
                                                font-size: 16px;">
                                                ${item.unwantedTeam}
                                            </p>
                                        </div>
                                    ` : ''}
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
                <div class="personalization-info" style="
                    margin-top: 10px;
                    padding: 15px;
                    background-color: white;
                    border-left: 4px solid #2c3e50;
                    border-radius: 4px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h4 style="
                        margin: 0 0 10px 0;
                        font-size: 16px;
                        color: #2c3e50;
                        font-weight: 500;">Personalización</h4>
                    <p style="
                        margin: 5px 0;
                        color: #666;
                        font-size: 14px;
                        line-height: 1.5;">
                        <span style="color: #666;">Nombre: ${item.personalization.name}</span><br>
                        <span style="color: #666;">Número: ${item.personalization.number}</span><br>
                        ${item.personalization.patch ? '<span style="color: #666;">• Con parche</span>' : ''}
                    </p>
                </div>
            `;
        }

        // Agregar información del tipo de Mystery Box si existe
        if (item.mysteryBoxType) {
            personalizationHtml += `
                <div class="personalization-info" style="
                    margin-top: 10px;
                    padding: 15px;
                    background-color: white;
                    border-left: 4px solid #e74c3c;
                    border-radius: 4px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <p style="
                        margin: 5px 0;
                        color: #666;
                        font-size: 16px;
                        line-height: 1.5;">
                        Tipo: ${item.mysteryBoxType.charAt(0).toUpperCase() + item.mysteryBoxType.slice(1)}
                    </p>
                    ${item.unwantedTeam ? `
                        <div style="
                            margin-top: 15px;
                            padding: 15px;
                            background-color: #fff5f5;
                            border-radius: 4px;">
                            <p style="
                                margin: 0;
                                color: #e74c3c;
                                font-size: 16px;
                                margin-bottom: 5px;">
                                Equipo no deseado:
                            </p>
                            <p style="
                                margin: 0;
                                color: #666;
                                font-size: 16px;">
                                ${item.unwantedTeam}
                            </p>
                        </div>
                    ` : ''}
                </div>
            `;
        }
        
        if (item.isGiftCard && item.details) {
            const sanitizeText = (text) => {
                if (!text) return '';
                return String(text)
                       .replace(/[<>]/g, '')
                       .replace(/[\u0000-\u001F\u007F-\u009F]/g, '')
                       .trim();
            };
            
            const recipientName = sanitizeText(item.details.recipientName || '');
            const recipientEmail = sanitizeText(item.details.recipientEmail || '');
            const message = sanitizeText(item.details.message || '');
            const senderName = sanitizeText(item.details.senderName || '');
            
            personalizationHtml = `
                <div class="personalization-info" style="
                    margin-top: 10px;
                    padding: 15px;
                    background-color: white;
                    border-left: 4px solid #e2e8f0;
                    border-radius: 4px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h4 style="
                        margin: 0 0 15px 0;
                        font-size: 16px;
                        color: #2d3748;
                        font-weight: 500;">Detalles de la Gift Card</h4>
                    <p style="
                        margin: 8px 0;
                        color: #666;
                        font-size: 16px;
                        line-height: 1.6;">
                        Para: ${recipientName}<br>
                        Email: ${recipientEmail}
                        ${message ? `<br>Mensaje: ${message}` : ''}
                        <br>De: ${senderName}
                    </p>
                </div>
            `;
        }
        
        let displayPrice = item.price;
        
        if (item.isGiftCard) {
            if (item.realPrice && item.realPrice > 0) {
                displayPrice = item.realPrice;
            } else if (item.price === 0 && item.title) {
                const priceMatch = item.title.match(/\$(\d+)/);
                if (priceMatch && priceMatch[1]) {
                    displayPrice = parseFloat(priceMatch[1]);
                }
            }
        }

        let quantityControlsHtml = '';
        if (item.isGiftCard) {
            quantityControlsHtml = `<p style="margin: 4px 0; font-size: 14px; color: #666;">Cantidad: 1</p>`;
        } else {
            quantityControlsHtml = `
                <div class="quantity-controls" style="display: flex; align-items: center; gap: 10px;">
                    <button class="decrease" style="width: 30px; height: 30px; border: 1px solid #ddd; background: #f8f8f8; border-radius: 4px; cursor: pointer;">-</button>
                    <span style="font-size: 16px;">${item.quantity}</span>
                    <button class="increase" style="width: 30px; height: 30px; border: 1px solid #ddd; background: #f8f8f8; border-radius: 4px; cursor: pointer;">+</button>
                </div>
            `;
        }

        // Asegurarse de que la imagen de la gift card tenga la ruta correcta
        let imageUrl = item.image;
        if (item.isGiftCard) {
            const currentPath = window.location.pathname;
            const isInProductosEquipos = currentPath.includes('/Productos-equipos/');
            imageUrl = isInProductosEquipos ? '../img/LogoNav.png' : './img/LogoNav.png';
        }

        return `
            <div class="cart-item" data-id="${item.id}" data-product-id="${item.product_id || ''}" data-size="${item.size || ''}" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; position: relative;">
                <div style="width: 100px; height: 100px; flex-shrink: 0; margin-right: 15px; border-radius: 8px; overflow: hidden;">
                    <img src="${imageUrl}" alt="${item.title}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                </div>
                <div class="cart-item-details" style="flex-grow: 1;">
                    <h4 style="margin: 0 0 8px 0; font-size: 16px; color: #333;">${item.title}</h4>
                    ${!item.isGiftCard && item.size ? `<p class="item-size" style="margin: 4px 0; font-size: 14px; color: #666;">Talla: ${item.size}</p>` : ''}
                    ${personalizationHtml}
                    <p class="item-price" style="margin: 8px 0; font-weight: bold; color: #2c3e50;">Precio: $${displayPrice.toFixed(2)} MXN</p>
                    ${quantityControlsHtml}
                </div>
                <button class="remove-item" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 20px; color: #999; cursor: pointer; padding: 5px;">×</button>
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

        // Mapeo manual de nombres a IDs para productos conocidos
        const productMap = {
            'Bayern Múnich Local 24/25': 9,
            'Bayern Munich Local 24/25': 9,
            'Barcelona Local 24/25': 3,
            'Manchester City Local 24/25': 7,
            'Rayados Local 24/25': 8,
            'AC Milan Local 24/25': 10,
            'Real Madrid Local 24/25': 4
        };

        // Obtener el product_id
        let product_id = productData.product_id;
        
        // Si no hay product_id, intentar obtenerlo del mapeo
        if (!product_id && productData.title) {
            product_id = productMap[productData.title];
            if (product_id) {
                console.log('Product ID asignado por nombre:', product_id);
            }
        }

        // Generar un ID único para este item específico
        const uniqueItemId = Date.now().toString() + '-' + Math.random().toString(36).substr(2, 5);

        const cartItem = {
            id: uniqueItemId,
            product_id: product_id,
            title: productData.title,
            price: productData.price,
            size: productData.size,
            quantity: productData.quantity,
            image: productData.image,
            personalization: productData.personalization ? {
                name: productData.personalization.name,
                number: productData.personalization.number,
                patch: productData.personalization.patch
            } : null,
            mysteryBoxType: productData.tipo || null,
            unwantedTeam: productData.unwantedTeam || null
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