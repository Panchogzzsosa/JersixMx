// Incluir información de Gift Card si está presente
const giftcardCode = document.querySelector('input[name="giftcard_code"]');
const giftcardAmount = document.querySelector('input[name="giftcard_amount"]');

// Si hay una Gift Card aplicada, incluirla en la solicitud
if (giftcardCode && giftcardAmount) {
    formData.append('giftcard_code', giftcardCode.value);
    formData.append('giftcard_amount', giftcardAmount.value);
    console.log('Aplicando Gift Card:', giftcardCode.value, 'por $' + giftcardAmount.value);
}

// Obtener los items del carrito
const cart = JSON.parse(localStorage.getItem('cart')) || [];

if (cart.length === 0) {
    console.error('El carrito está vacío');
    return;
}

// Log para debugging
console.log('Items en el carrito:', cart);

// Preparar los items para enviar al servidor
const cartItems = cart.map(item => {
    // Asegurarse de que se incluye el product_id real
    const cartItem = {
        id: item.id,
        product_id: item.product_id || 0, // Asegurar que se envía el product_id real
        title: item.title,
        price: item.price,
        quantity: item.quantity || 1,
        size: item.size || null,
        personalization: item.personalization || null
    };
    
    // Para gift cards, incluir los detalles
    if (item.isGiftCard) {
        cartItem.isGiftCard = true;
        cartItem.details = item.details || {};
    }
    
    console.log('Producto a enviar:', cartItem);
    return cartItem;
});

// Enviar el formulario
try {
} catch (error) {
    console.error('Error al procesar el pedido:', error);
} 