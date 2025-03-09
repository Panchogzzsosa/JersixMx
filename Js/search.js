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
        resultItem.innerHTML = `
            <img src="${product.image}" alt="${product.name}" class="result-image">
            <div class="result-info">
                <h4>${product.name}</h4>
                <p>${product.team} - ${product.category}</p>
                <span class="result-price">$${product.price}</span>
            </div>
        `;
        resultItem.addEventListener('click', () => {
            window.location.href = product.url;
        });
        return resultItem;
    }

    function performSearch(searchTerm) {
        searchResults.innerHTML = '';
        if (!searchTerm) {
            searchResults.style.display = 'none';
            return;
        }

        const normalizedSearchTerm = searchTerm.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        
        const matchedProducts = productsData.filter(product => {
            const normalizedName = product.name.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const normalizedTeam = product.team.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const normalizedCategory = product.category.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            
            return normalizedName.includes(normalizedSearchTerm) ||
                   normalizedTeam.includes(normalizedSearchTerm) ||
                   normalizedCategory.includes(normalizedSearchTerm);
        });

        if (matchedProducts.length > 0) {
            matchedProducts.forEach(product => {
                searchResults.appendChild(createSearchResultItem(product));
            });
            searchResults.style.display = 'block';
        } else {
            searchResults.innerHTML = '<div class="no-results">No se encontraron productos</div>';
            searchResults.style.display = 'block';
        }
    }

    // Handle search functionality
    searchInput.addEventListener('input', (e) => {
        performSearch(e.target.value);
    });

    searchInput.addEventListener('focus', () => {
        if (searchInput.value) {
            searchResults.style.display = 'block';
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
});