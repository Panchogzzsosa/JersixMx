document.addEventListener('DOMContentLoaded', () => {
    const sortSelect = document.getElementById('sort-products');
    const productsContainer = document.querySelector('.products-container');

    // Add event listener for sort selection
    sortSelect.addEventListener('change', () => {
        const products = Array.from(document.querySelectorAll('.product-card'));
        const sortedProducts = sortProducts(products);
        
        // Clear the container
        productsContainer.innerHTML = '';
        
        // Add sorted products back to the container
        sortedProducts.forEach(product => {
            productsContainer.appendChild(product);
        });
    });

    function sortProducts(products) {
        const sortValue = sortSelect.value;
        
        return [...products].sort((a, b) => {
            const priceA = parseFloat(a.querySelector('.price').textContent.replace('$', '').trim());
            const priceB = parseFloat(b.querySelector('.price').textContent.replace('$', '').trim());
            
            switch (sortValue) {
                case 'price-low':
                    return priceA - priceB;
                case 'price-high':
                    return priceB - priceA;
                case 'newest':
                    // For demo purposes, we'll use the current order as "newest"
                    return 0;
                default: // 'featured'
                    return 0;
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const filterToggle = document.querySelector('.filter-toggle');
    const filtersSidebar = document.querySelector('.filters-sidebar');

    filterToggle.addEventListener('click', function() {
        filtersSidebar.classList.toggle('show');
    });

    // Close filters when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            const isClickInside = filtersSidebar.contains(event.target) || filterToggle.contains(event.target);
            
            if (!isClickInside && filtersSidebar.classList.contains('show')) {
                filtersSidebar.classList.remove('show');
            }
        }
    });
});

    // Get all filter checkboxes
    const filterCheckboxes = document.querySelectorAll('.filter-section input[type="checkbox"]');
    const productsContainer = document.querySelector('.products-container');
    const productCards = document.querySelectorAll('.product-card');

    // Add event listeners to all checkboxes
    filterCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', filterProducts);
    });

    function filterProducts() {
        // Get selected filters
        const selectedCategories = getSelectedFilters('category');
        const selectedLeagues = getSelectedFilters('league');
        const selectedSizes = getSelectedFilters('size');

        // Show all products if no filters are selected
        if (selectedCategories.length === 0 && selectedLeagues.length === 0 && selectedSizes.length === 0) {
            productCards.forEach(card => card.style.display = 'block');
            return;
        }

        // Filter products
        productCards.forEach(card => {
            const category = card.dataset.category;
            const league = card.dataset.league;
            const matchesCategory = selectedCategories.length === 0 || selectedCategories.includes(category);
            const matchesLeague = selectedLeagues.length === 0 || selectedLeagues.includes(league);

            // Show/hide card based on filters
            if (matchesCategory && matchesLeague) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function getSelectedFilters(type) {
        const checkboxes = document.querySelectorAll(`input[type="checkbox"][data-${type}]`);
        return Array.from(checkboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.dataset[type]);
    }

    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });
    }