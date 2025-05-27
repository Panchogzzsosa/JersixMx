/**
 * Script para manejar el comportamiento de la imagen del producto en dispositivos móviles
 */
document.addEventListener('DOMContentLoaded', function() {
    // Deshabilitamos este script para usar la solución CSS
    // Solo lo dejamos como referencia por si necesitamos volver a habilitarlo después
    
    /*
    // Solo aplicar en dispositivos móviles
    if (window.innerWidth <= 768) {
        // Elementos principales
        const navbar = document.querySelector('header');
        const imageContainer = document.querySelector('.product-image-container');
        const productInfo = document.querySelector('.product-info');
        
        // Asegurarnos de que los elementos existen
        if (!navbar || !imageContainer || !productInfo) return;
        
        // Capturar alturas iniciales
        const navbarHeight = navbar.offsetHeight;
        const imageContainerHeight = imageContainer.offsetHeight;
        
        // Clonar el contenedor de imágenes para usarlo como placeholder
        const placeholderDiv = document.createElement('div');
        placeholderDiv.classList.add('image-placeholder');
        placeholderDiv.style.height = imageContainerHeight + 'px';
        placeholderDiv.style.display = 'none';
        imageContainer.parentNode.insertBefore(placeholderDiv, imageContainer);
        
        // Función para manejar el desplazamiento
        function handleScroll() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            // Si hemos llegado a la posición de la imagen
            if (scrollTop >= imageContainer.offsetTop - navbarHeight) {
                // Fijar la imagen
                imageContainer.style.position = 'fixed';
                imageContainer.style.top = navbarHeight + 'px';
                imageContainer.style.left = '0';
                imageContainer.style.width = '100%';
                imageContainer.style.zIndex = '100';
                
                // Mostrar el placeholder para mantener el espacio
                placeholderDiv.style.display = 'block';
            } else {
                // Devolver la imagen a su posición normal
                imageContainer.style.position = 'relative';
                imageContainer.style.top = 'auto';
                imageContainer.style.left = 'auto';
                imageContainer.style.width = '100%';
                imageContainer.style.zIndex = '1';
                
                // Ocultar el placeholder
                placeholderDiv.style.display = 'none';
            }
        }
        
        // Aplicar el comportamiento inicial
        handleScroll();
        
        // Escuchar eventos de desplazamiento
        window.addEventListener('scroll', handleScroll);
        
        // Reajustar en caso de cambio de tamaño de ventana
        window.addEventListener('resize', function() {
            // Si ya no estamos en móvil, restablecer todo
            if (window.innerWidth > 768) {
                imageContainer.style.position = 'relative';
                imageContainer.style.top = 'auto';
                imageContainer.style.left = 'auto';
                imageContainer.style.width = '100%';
                imageContainer.style.zIndex = '1';
                placeholderDiv.style.display = 'none';
            } else {
                // Recalcular alturas
                const newNavbarHeight = navbar.offsetHeight;
                const newImageContainerHeight = imageContainer.offsetHeight;
                
                placeholderDiv.style.height = newImageContainerHeight + 'px';
                
                // Reaplicar el comportamiento
                handleScroll();
            }
        });
    }
    */
}); 