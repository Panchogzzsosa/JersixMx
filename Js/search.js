document.addEventListener('DOMContentLoaded', function() {
    const searchContainer = document.querySelector('.search-container');
    const searchButton = document.querySelector('.search-button');
    const searchInput = document.querySelector('.search-input');
    const searchResults = document.createElement('div');
    searchResults.className = 'search-results';
    searchContainer.appendChild(searchResults);

    // Toggle search container on mobile
    searchButton.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            e.preventDefault();
            searchContainer.classList.toggle('active');
            if (searchContainer.classList.contains('active')) {
                searchInput.focus();
            }
        }
    });

    // Close search container when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && 
            !searchContainer.contains(e.target) && 
            !searchButton.contains(e.target)) {
            searchContainer.classList.remove('active');
        }
    });

    function createSearchResultItem(product) {
        const resultItem = document.createElement('div');
        resultItem.className = 'search-result-item';
        
        // Construir la URL de la imagen correctamente
        let imageUrl = product.image_url;
        
        // Verificar si la imagen existe y manejar diferentes formatos de ruta
        if (imageUrl) {
            // Si la ruta no comienza con http o https
            if (!imageUrl.startsWith('http')) {
                // Si no comienza con una barra, añadir el prefijo adecuado
                if (!imageUrl.startsWith('/') && !imageUrl.startsWith('../')) {
                    // Si estamos en la carpeta Productos-equipos, ajustar la ruta
                    if (location.pathname.includes('/Productos-equipos/')) {
                        imageUrl = '../' + imageUrl;
                    } else {
                        imageUrl = imageUrl;
                    }
                }
            }
        } else {
            // Usar imagen por defecto si no hay imagen
            imageUrl = location.pathname.includes('/Productos-equipos/') ? '../img/default-product.jpg' : 'img/default-product.jpg';
        }
        
        // Construir la URL del producto correctamente basado en la ubicación actual
        let productUrl;
        if (location.pathname.includes('/Productos-equipos/')) {
            productUrl = 'producto.php?id=' + product.product_id;
        } else {
            productUrl = 'Productos-equipos/producto.php?id=' + product.product_id;
        }
        
        resultItem.innerHTML = `
            <img src="${imageUrl}" alt="${product.name}" class="result-image" onerror="this.src='${location.pathname.includes('/Productos-equipos/') ? '../img/default-product.jpg' : 'img/default-product.jpg'}'">
            <div class="result-info">
                <h4>${product.name}</h4>
                <p>${product.category || ''}</p>
                <span class="result-price">$${parseFloat(product.price).toFixed(2)}</span>
            </div>
        `;
        
        resultItem.addEventListener('click', () => {
            window.location.href = productUrl;
        });
        
        return resultItem;
    }

    function performSearch(searchTerm) {
        searchResults.innerHTML = '';
        if (!searchTerm || searchTerm.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        // Mostrar indicador de carga
        searchResults.innerHTML = '<div class="loading">Buscando...</div>';
        searchResults.style.display = 'block';
        
        // Realizar búsqueda mediante AJAX a la base de datos
        // Usar ruta absoluta para asegurar que funcione desde cualquier ubicación
        const searchUrl = location.pathname.includes('/Productos-equipos/') ? '../search_products.php' : 'search_products.php';
        fetch(searchUrl + '?q=' + encodeURIComponent(searchTerm))
            .then(response => response.json())
            .then(data => {
                searchResults.innerHTML = '';
                
                if (data.products && data.products.length > 0) {
                    data.products.forEach(product => {
                        searchResults.appendChild(createSearchResultItem(product));
                    });
                } else {
                    searchResults.innerHTML = `
                        <div class="no-results">
                            <p>¿No encuentras lo que buscas? ¡Escríbenos!</p>
                            <a href="https://wa.me/528117602053" target="_blank" class="whatsapp-link">
                                <i class="fab fa-whatsapp"></i> Contactar por WhatsApp
                            </a>
                        </div>`;
                }
                
                searchResults.style.display = 'block';
            })
            .catch(error => {
                console.error('Error en la búsqueda:', error);
                searchResults.innerHTML = '<div class="error">Error al buscar productos</div>';
                searchResults.style.display = 'block';
            });
    }

    // Agregar un pequeño retraso para no hacer búsquedas con cada pulsación
    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch(e.target.value);
        }, 300);
    });

    searchInput.addEventListener('focus', () => {
        if (searchInput.value && searchInput.value.length >= 2) {
            performSearch(searchInput.value);
        }
    });

    // Cerrar resultados cuando se hace clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-container')) {
            searchResults.style.display = 'none';
        }
    });

    searchButton.addEventListener('click', (e) => {
        e.preventDefault();
        if (window.innerWidth > 768) {
            performSearch(searchInput.value);
        }
    });
    
    // Función global para búsqueda desde otros lugares
    window.performSearch = function(term) {
        if (searchInput) {
            searchInput.value = term;
            performSearch(term);
        }
    };
});