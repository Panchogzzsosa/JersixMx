/**
 * Js/Producto-equipos.js
 * Script para manejar las funcionalidades de la página de producto
 */

// Índice de la imagen actual
let currentImageIndex = 0;
let thumbnails = [];

// Función para cambiar la imagen principal
function changeImage(element) {
    const mainImage = document.getElementById('mainImage');
    if (!mainImage) return;
    
    mainImage.src = element.src;
    
    // Actualizar la clase "active" de las miniaturas
    thumbnails.forEach((thumb, index) => {
        thumb.classList.remove('active');
        if (thumb === element) {
            currentImageIndex = index;
        }
    });
    element.classList.add('active');
}

// Función para navegar entre imágenes con los botones de navegación
function changeImageNav(direction) {
    thumbnails = Array.from(document.querySelectorAll('.thumbnail'));
    const totalImages = thumbnails.length;
    if (totalImages <= 1) return;
    
    // Calcular el nuevo índice
    let newIndex = currentImageIndex + direction;
    
    // Manejar los límites
    if (newIndex < 0) newIndex = totalImages - 1;
    if (newIndex >= totalImages) newIndex = 0;
    
    // Cambiar la imagen
    changeImage(thumbnails[newIndex]);
}

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar thumbnails
    thumbnails = Array.from(document.querySelectorAll('.thumbnail'));
    // Si hay al menos una miniatura, hacerla activa
    if (thumbnails.length > 0) {
        thumbnails[0].classList.add('active');
    }
    
    // Inicializar selección de tallas
    const sizeOptions = document.querySelectorAll('.size-option');
    sizeOptions.forEach(option => {
        option.addEventListener('click', function() {
            sizeOptions.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
    
    // Inicializar controles de cantidad
    const quantityInput = document.querySelector('.quantity-input');
    const minusBtn = document.querySelector('.quantity-btn.minus');
    const plusBtn = document.querySelector('.quantity-btn.plus');
    
    if (quantityInput && minusBtn && plusBtn) {
        // Obtener el stock máximo del producto
        const maxStock = parseInt(quantityInput.getAttribute('max') || '10');
        
        minusBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            if (value > 1) {
                quantityInput.value = value - 1;
            }
        });
        
        plusBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            if (value < maxStock) {
                quantityInput.value = value + 1;
            }
        });
        
        // Asegurar que la cantidad no exceda el stock
        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (isNaN(value) || value < 1) {
                this.value = 1;
            } else if (value > maxStock) {
                this.value = maxStock;
            }
        });
    }
    
    // Funcionalidad para zoom en imagen
    const mainImage = document.getElementById('mainImage');
    
    if (mainImage) {
        mainImage.addEventListener('mousemove', function(e) {
            const { left, top, width, height } = this.getBoundingClientRect();
            const x = (e.clientX - left) / width;
            const y = (e.clientY - top) / height;
            
            // Aplicar transformación para zoom
            this.style.transformOrigin = `${x * 100}% ${y * 100}%`;
            this.style.transform = 'scale(1.5)';
        });
        
        mainImage.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    }
    
    // Agregar manejadores de error para las imágenes
    const allImages = document.querySelectorAll('img');
    allImages.forEach(img => {
        img.addEventListener('error', function() {
            // Si la imagen falla, intentar con una imagen de respaldo
            if (!this.src.includes('default-product.jpg')) {
                console.log('Error cargando imagen:', this.src);
                // Redirigir a imagen por defecto
                if (this.classList.contains('thumbnail')) {
                    // Si es una miniatura, simplemente ocultarla
                    this.style.display = 'none';
                } else {
                    // Si es la imagen principal, usar una imagen por defecto
                    if (this.src.includes('../')) {
                        this.src = '../img/default-product.jpg';
                    } else {
                        this.src = 'img/default-product.jpg';
                    }
                }
            }
        });
    });
    
    // Agregar control de navegación con teclado
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            changeImageNav(-1);
        } else if (e.key === 'ArrowRight') {
            changeImageNav(1);
        }
    });
    
    // Si existe un botón de agregar al carrito, agregar funcionalidad
    const addToCartBtn = document.querySelector('.add-to-cart-btn');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            // Obtener información del producto
            const productTitle = document.querySelector('.product-title').textContent;
            const productPrice = document.querySelector('.product-price').textContent;
            const productImage = document.getElementById('mainImage').src;
            const productId = document.querySelector('.product-price').getAttribute('data-product-id');
            const selectedSize = document.querySelector('.size-option.selected')?.textContent || 'M';
            const quantity = parseInt(document.querySelector('.quantity-input')?.value || '1');
            
            console.log('Agregando al carrito:', {
                title: productTitle,
                price: productPrice,
                image: productImage,
                id: productId,
                size: selectedSize,
                quantity: quantity
            });
            
            // Llamar a la función addToCart si está disponible
            if (typeof shoppingCart !== 'undefined' && shoppingCart.addToCart) {
                // Extraer el precio numérico
                const priceValue = parseFloat(productPrice.replace(/[^\d.-]/g, ''));
                
                shoppingCart.addToCart({
                    id: productId + '-' + selectedSize,
                    title: productTitle,
                    price: priceValue,
                    image: productImage,
                    size: selectedSize,
                    quantity: quantity
                });
                
                // Mostrar mensaje de éxito si hay una función de notificación
                if (typeof showNotification === 'function') {
                    showNotification('Producto agregado al carrito', true);
                } else {
                    alert('¡Producto agregado al carrito!');
                }
            } else {
                console.error('No se encontró la función addToCart');
                alert('Error al agregar al carrito. Por favor, intente nuevamente.');
            }
        });
    }
});