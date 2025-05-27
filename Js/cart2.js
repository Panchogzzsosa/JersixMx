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
        this.updateCartIcon();
        this.updateCartModal();
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
        // Custom addToCart event for personalized products and mystery box
        document.addEventListener('addToCart', (e) => {
            this.addToCartWithCustomization(e.detail);
        });

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
        const productContainer = event.target.closest('.product-detail') || event.target.closest('.product-card');
        if (!productContainer) return;

        const title = productContainer.querySelector('.product-title')?.textContent;
        const price = parseFloat(productContainer.querySelector('.product-price')?.textContent.replace('$', '').trim());
        const currentPath = window.location.pathname;
        const isInProductosEquipos = currentPath.includes('/Productos-equipos/');
        const image = productContainer.querySelector('.product-image')?.src || (productContainer.classList.contains('giftcard-container') ? (isInProductosEquipos ? '../img/LogoNav.png' : './img/LogoNav.png') : null);
        const size = productContainer.querySelector('.size-option.selected')?.textContent;
        const quantity = parseInt(productContainer.querySelector('.quantity-input')?.value || '1');
        const tipo = productContainer.querySelector('.tipo-option.selected')?.dataset.tipo;

        if (!title || !price || !image) {
            console.error('Missing required product information');
            return;
        }

        // Validate size selection if size options exist
        if (productContainer.querySelector('.size-options') && !size) {
            this.showNotification('Por favor, selecciona una talla', 'error');
            return;
        }

        // Get personalization info if available
        let personalization = null;
        const jerseyName = productContainer.querySelector('#jerseyName');
        const jerseyNumber = productContainer.querySelector('#jerseyNumber');
        const patchOption = productContainer.querySelector('#patchOption');
        
        if (jerseyName && jerseyNumber) {
            personalization = {
                name: jerseyName.value.trim(),
                number: jerseyNumber.value.trim(),
                patch: patchOption ? patchOption.checked : false
            };
            
            // Only set personalization if at least one field has a value
            if (!personalization.name && !personalization.number && !personalization.patch) {
                personalization = null;
            }
        }

        const cartItem = {
            id: Date.now().toString(),
            title,
            price,
            size,
            quantity,
            image,
            personalization,
            tipo
        };

        this.cart.push(cartItem);
        this.saveCart();
        this.updateCartIcon();
        this.updateCartModal();
        this.showNotification('Producto agregado al carrito', 'success');
    }
    
    addToCartWithCustomization(detail) {
        if (!detail) {
            console.error('Missing product details');
            return;
        }
        
        // Si el producto ya tiene toda la información necesaria (caso Mystery Box)
        if (detail.id && detail.title && detail.price && detail.size) {
            this.cart.push(detail);
            this.saveCart();
            this.updateCartIcon();
            this.updateCartModal();
            this.showNotification('Producto agregado al carrito', 'success');
            return;
        }

        // Caso para productos personalizados (jerseys)
        if (!detail.customization) {
            console.error('Missing customization details');
            return;
        }
        
        const customization = detail.customization;
        const titleElement = document.querySelector('h1.product-title') || document.querySelector('h3.product-title') || document.querySelector('.product-title');
        const title = titleElement ? titleElement.textContent.trim() : null;
        const currentPath = window.location.pathname;
        const isInProductosEquipos = currentPath.includes('/Productos-equipos/');
        const mainImage = document.querySelector('#mainImage');
        const image = mainImage ? mainImage.src : null;
        
        if (!title || !customization.price || !image) {
            console.error('Missing required product information');
            return;
        }
        
        // Create personalization object if name or number exists
        const personalization = {
            name: customization.name || '',
            number: customization.number || '',
            patch: customization.patch || false
        };
        
        // Check if personalization fields have values (indicating personalization is being used)
        const isPersonalized = personalization.name !== '' || personalization.number !== '';
        
        // Check if personalization is being used but name or number is empty
        if (isPersonalized && (!personalization.name || !personalization.number)) {
            this.showNotification('Para personalizar tu jersey, debes ingresar tanto el nombre como el número', 'error');
            return;
        }
        
        const cartItem = {
            id: Date.now().toString(),
            title,
            price: customization.price,
            size: customization.size,
            quantity: customization.quantity,
            image,
            personalization: (personalization.name || personalization.number || personalization.patch) ? personalization : null
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
        this.modal.classList.add('open');
        this.overlay.classList.add('show');
        this.updateCartModal();
        document.body.style.overflow = 'hidden';
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
                    window.location.href = 'checkout.html';
                    this.closeCart();
                });
            }
        }
    }

    generateCartItemHTML(item, personalizationInfo = '') {
        return `
            <div class="cart-item" data-id="${item.id}">
                <img src="${item.image}" alt="${item.title}">
                <div class="cart-item-details">
                    <h4>${item.title}</h4>
                    ${item.size ? `<p>Talla: ${item.size}</p>` : ''}
                    ${item.tipo ? `<p>Tipo: ${item.tipo === 'champions' ? 'Champions' : item.tipo === 'ligamx' ? 'LigaMX' : 'Liga Europea'}</p>` : ''}
                    ${item.personalization ? `
                        <div class="personalization-info">
                            <p>Nombre: ${item.personalization.name}</p>
                            <p>Número: ${item.personalization.number}</p>
                        </div>
                    ` : ''}
                    <p>Precio: $${item.price.toFixed(2)}</p>
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
}

// Initialize shopping cart
const shoppingCart = new ShoppingCart();