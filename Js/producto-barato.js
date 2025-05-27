function changeImage(thumbnail) {
    // Get the main image element
    const mainImage = document.getElementById('mainImage');
    
    // Update the main image source
    mainImage.src = thumbnail.src;
    
    // Remove active class from all thumbnails
    const thumbnails = document.querySelectorAll('.thumbnail');
    thumbnails.forEach(thumb => thumb.classList.remove('active'));
    
    // Add active class to clicked thumbnail
    thumbnail.classList.add('active');
    
    // Add fade effect
    mainImage.style.opacity = '0';
    setTimeout(() => {
        mainImage.style.opacity = '1';
    }, 50);
}

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle functionality
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    menuToggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
    });

    const mainImage = document.getElementById('mainImage');
    mainImage.style.transition = 'opacity 0.3s ease';
    // Quantity controls
    const minusBtn = document.querySelector('.minus');
    const plusBtn = document.querySelector('.plus');
    const quantityInput = document.querySelector('.quantity-input');
    const priceElement = document.querySelector('.product-price');
    const basePrice = 9.00;
    const personalizationPrice = 200.00;

    function updatePrice() {
        const isPersonalized = personalizationSelect.value === 'custom';
        const quantity = parseInt(quantityInput.value);
        const total = (basePrice + (isPersonalized ? personalizationPrice : 0)) * quantity;
        priceElement.textContent = `$ ${total.toFixed(2)}`;
    }

    minusBtn.addEventListener('click', () => {
        const currentValue = parseInt(quantityInput.value);
        if (currentValue > 1) {
            quantityInput.value = currentValue - 1;
        }
    });

    plusBtn.addEventListener('click', () => {
        const currentValue = parseInt(quantityInput.value);
        quantityInput.value = currentValue + 1;
    });

    quantityInput.addEventListener('change', () => {
        if (quantityInput.value < 1) {
            quantityInput.value = 1;
        }
        updatePrice();
    });

    // Personalization controls
    const personalizationSelect = document.getElementById('personalization-select');
    const personalizationFields = document.getElementById('personalization-fields');
    const jerseyName = document.getElementById('jersey-name');
    const jerseyNumber = document.getElementById('jersey-number');

    personalizationSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            personalizationFields.style.display = 'block';
        } else {
            personalizationFields.style.display = 'none';
            jerseyName.value = '';
            jerseyNumber.value = '';
        }
        updatePrice();
    });

    // Add to cart button
    const addToCartBtn = document.querySelector('.add-to-cart-btn');
    addToCartBtn.addEventListener('click', () => {
        const quantity = parseInt(quantityInput.value);
        const size = document.querySelector('.size-option.selected')?.textContent || '';
        
        if (!size) {
            alert('Por favor seleccione una talla');
            return;
        }

        if (personalizationSelect.value === 'custom') {
            if (!jerseyName.value.trim() || !jerseyNumber.value.trim()) {
                alert('Por favor ingrese tanto el nombre como el número para personalizar la camiseta');
                return;
            }
            if (jerseyNumber.value < 1 || jerseyNumber.value > 99) {
                alert('El número debe estar entre 1 y 99');
                return;
            }
        }

        const personalization = personalizationSelect.value === 'custom' ? {
            name: jerseyName.value,
            number: jerseyNumber.value
        } : null;
        
        const isPersonalized = personalizationSelect.value === 'custom';
        const itemPrice = basePrice + (isPersonalized ? personalizationPrice : 0);

        const cartItem = {
            product: 'Barcelona Jersey 24/25',
            quantity,
            size,
            personalization,
            price: itemPrice
        };

        // Add to cart using CartManager if available
        if (window.cartManager) {
            window.cartManager.addToCart(cartItem);
        } else {
            console.log('Adding to cart:', cartItem);
        }
    });

    // Size selection
    const sizeOptions = document.querySelectorAll('.size-option');
    sizeOptions.forEach(option => {
        option.addEventListener('click', () => {
            sizeOptions.forEach(opt => opt.classList.remove('selected'));
            option.classList.add('selected');
        });
    });

    // Initialize price
    updatePrice();
});