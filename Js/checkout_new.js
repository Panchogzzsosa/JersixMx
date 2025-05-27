// Get cart items from localStorage
const cartItems = JSON.parse(localStorage.getItem('cart')) || [];
const shippingCost = 0; // Fixed shipping cost in MXN

// Initialize MercadoPago
const mp = new MercadoPago('TEST-32885cbc-2564-471d-9330-547dec496a35', {
    locale: 'es-MX'
});

// Function to format price
function formatPrice(price) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(price);
}

// Calculate and display order summary
function updateOrderSummary() {
    const cartSummaryElement = document.getElementById('cart-summary');
    const subtotalElement = document.getElementById('subtotal-amount');
    const shippingElement = document.getElementById('shipping-amount');
    const totalElement = document.getElementById('total-amount');

    // Clear previous items
    cartSummaryElement.innerHTML = '';

    // Calculate subtotal
    let subtotal = 0;
    cartItems.forEach(item => {
        subtotal += item.price * item.quantity;

        // Add item to summary without price
        const itemElement = document.createElement('div');
        itemElement.className = 'cart-item';
        itemElement.innerHTML = `
            <div class="cart-item-image">
                <img src="${item.image}" alt="${item.title}" />
            </div>
            <div class="cart-item-details">
                <span class="cart-item-name">${item.title} x${item.quantity}</span>
            </div>
        `;
        cartSummaryElement.appendChild(itemElement);
    });

    // Update summary amounts
    subtotalElement.textContent = formatPrice(subtotal);
    shippingElement.textContent = formatPrice(shippingCost);
    totalElement.textContent = formatPrice(subtotal + shippingCost);
}

// Email verification system
let isEmailVerified = false;
const sendCodeBtn = document.getElementById('send-code-btn');
const verifyCodeBtn = document.getElementById('verify-code-btn');
const verificationSection = document.querySelector('.verification-section');

sendCodeBtn.addEventListener('click', async () => {
    const email = document.getElementById('email').value;
    if (!email) {
        alert('Por favor ingresa un correo electrónico');
        return;
    }

    try {
        sendCodeBtn.disabled = true;
        sendCodeBtn.textContent = 'Enviando...';

        const response = await fetch('verify_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'send',
                email: email
            })
        });

        const data = await response.json();
        verificationSection.style.display = 'block';
        
        if (response.ok && data.status === 'success') {
            alert('Código de verificación enviado. Por favor revisa tu correo.');
            sendCodeBtn.textContent = 'Reenviar código';
        } else {
            throw new Error(data.message || 'Error al enviar el código de verificación');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al enviar el código de verificación. Por favor intenta más tarde.');
        sendCodeBtn.disabled = false;
        sendCodeBtn.textContent = 'Enviar código';
    }
});

verifyCodeBtn.addEventListener('click', async () => {
    const email = document.getElementById('email').value;
    const code = document.getElementById('verification-code').value;

    try {
        const response = await fetch('verify_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'verify',
                email: email,
                code: code
            })
        });

        const data = await response.json();
        if (data.status === 'success') {
            isEmailVerified = true;
            alert('Email verificado correctamente');
            document.getElementById('email').readOnly = true;
            verificationSection.style.display = 'none';
            sendCodeBtn.style.display = 'none';
        } else {
            alert(data.message || 'Código inválido');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al verificar el código');
    }
});

// Function to handle wallet payment
async function handleWalletPayment() {
    if (!validateForm()) {
        alert('Por favor completa todos los campos obligatorios');
        return;
    }
    if (!isEmailVerified) {
        alert('Por favor verifica tu correo electrónico antes de continuar');
        return;
    }

    const formData = {
        firstName: document.getElementById('fullname').value.split(' ')[0],
        lastName: document.getElementById('fullname').value.split(' ').slice(1).join(' '),
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        address: document.getElementById('street').value,
        address2: document.getElementById('colonia').value,
        city: document.getElementById('city').value,
        state: document.getElementById('state').value,
        postalCode: document.getElementById('postal').value,
        country: 'MX',
        totalAmount: parseFloat(document.getElementById('total-amount').textContent.replace(/[^0-9.-]+/g, '')),
        items: cartItems.map(item => ({
            id: item.id,
            quantity: item.quantity,
            price: item.price,
            size: item.size
        }))
    };

    try {
        const response = await fetch('process_wallet_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();
        if (data.success) {
            // Clear cart and redirect to success page
            localStorage.removeItem('cart');
            window.location.href = 'success.html';
        } else {
            throw new Error(data.message || 'Error al procesar el pago');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al procesar el pago. Por favor intenta nuevamente.');
    }
}

// Create wallet button
const walletButton = document.createElement('button');
walletButton.id = 'wallet-button';
walletButton.className = 'submit-button';
walletButton.textContent = 'Pagar con Wallet';
walletButton.onclick = handleWalletPayment;

// Initialize page
document.addEventListener('DOMContentLoaded', () => {
    updateOrderSummary();
    
    // Add wallet button to the form
    const walletContainer = document.getElementById('wallet_container');
    if (walletContainer) {
        walletContainer.appendChild(walletButton);
    }

    // Add form submit handler
    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        // Add input event listeners to all form fields
        const formFields = contactForm.querySelectorAll('input');
        formFields.forEach(field => {
            field.addEventListener('input', validateForm);
        });

        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (validateForm()) {
                handleWalletPayment();
            }
        });
    }

    // Initial validation
    validateForm();
});

// Function to validate form fields and control button state
function validateForm() {
    const formFields = {
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        fullname: document.getElementById('fullname').value,
        street: document.getElementById('street').value,
        colonia: document.getElementById('colonia').value,
        city: document.getElementById('city').value,
        state: document.getElementById('state').value,
        postal: document.getElementById('postal').value
    };

    // Check if all fields are filled
    const isFormValid = Object.values(formFields).every(value => value.trim() !== '');
    
    // Get the wallet button
    const walletButton = document.getElementById('wallet-button');
    if (walletButton) {
        walletButton.disabled = !isFormValid;
        walletButton.style.opacity = isFormValid ? '1' : '0.5';
    }

    return isFormValid;
}