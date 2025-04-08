// Incluir información de Gift Card si está presente
const giftcardCode = document.querySelector('input[name="giftcard_code"]');
const giftcardAmount = document.querySelector('input[name="giftcard_amount"]');

// Si hay una Gift Card aplicada, incluirla en la solicitud
if (giftcardCode && giftcardAmount) {
    formData.append('giftcard_code', giftcardCode.value);
    formData.append('giftcard_amount', giftcardAmount.value);
    console.log('Aplicando Gift Card:', giftcardCode.value, 'por $' + giftcardAmount.value);
}

// Enviar el formulario
try {
} catch (error) {
    console.error('Error al procesar el pedido:', error);
} 