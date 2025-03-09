document.addEventListener('DOMContentLoaded', function() {
    // Initialize Intersection Observer for smooth animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, { threshold: 0.1 });

    // Observe all sections for fade-in effect
    document.querySelectorAll('section').forEach(section => {
        observer.observe(section);
    });

    // Mobile menu functionality
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    menuToggle.addEventListener('click', function() {
        navLinks.classList.toggle('active');
    });

    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        const searchContainer = document.querySelector('.search-container');
        const searchButton = document.querySelector('.search-button');

        if (!event.target.closest('.nav-links') && !event.target.closest('.menu-toggle')) {
            navLinks.classList.remove('active');
        }

        // Don't close search container if clicking search button or inside search container
        if (searchContainer && !searchContainer.contains(event.target) && !searchButton.contains(event.target)) {
            searchContainer.classList.remove('active');
        }
    });

    // Handle search button click
    const searchButton = document.querySelector('.search-button');
    const searchContainer = document.querySelector('.search-container');
    
    if (searchButton && searchContainer) {
        searchButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            searchContainer.classList.toggle('active');
        });

        // Prevent search container from closing when clicking inside it
        searchContainer.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Carousel functionality
    const slides = document.querySelectorAll('.carousel-slide');
    const prevButton = document.querySelector('.carousel-button.prev');
    const nextButton = document.querySelector('.carousel-button.next');
    let currentSlide = 0;

    if (slides.length > 0) {
        // Initialize the first slide
        slides[currentSlide].classList.add('active');

        function showSlide(index) {
            // Remove active class from all slides
            slides.forEach(slide => {
                slide.classList.remove('active', 'previous');
            });

            // Add active class to current slide
            slides[index].classList.add('active');

            // Add previous class to the slide that's going out
            if (index === 0) {
                slides[slides.length - 1].classList.add('previous');
            } else {
                slides[index - 1].classList.add('previous');
            }
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }

        function prevSlide() {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            showSlide(currentSlide);
        }

        if (prevButton && nextButton) {
            prevButton.addEventListener('click', prevSlide);
            nextButton.addEventListener('click', nextSlide);
        }

        // Auto advance slides
        setInterval(nextSlide, 5000);
    }

    // Products filtering functionality
    const productsContainer = document.querySelector('.products-container');
    if (productsContainer) {
        const productCards = Array.from(productsContainer.querySelectorAll('.product-card'));
        const filterCheckboxes = document.querySelectorAll('.filter-section input[type="checkbox"]');
        const sortSelect = document.querySelector('.sort-options select');

        // Store original products data
        const originalProducts = productCards.map(card => ({
            element: card,
            name: card.querySelector('h3').textContent,
            price: parseFloat(card.querySelector('.price').textContent.replace('$', '').replace(',', '')),
            categories: getProductCategories(card),
            league: getProductLeague(card),
            size: getProductSize(card)
        }));

        function getProductCategories(card) {
            const name = card.querySelector('h3').textContent.toLowerCase();
            const categories = [];
            if (name.includes('local')) categories.push('locales');
            if (name.includes('visitante')) categories.push('visitantes');
            if (name.includes('retro')) categories.push('retro');
            if (name.includes('edición especial')) categories.push('edición especial');
            return categories;
        }

        function getProductLeague(card) {
            const name = card.querySelector('h3').textContent.toLowerCase();
            // Check for Liga MX teams first
            if (name.includes('tigres') || 
                name.includes('rayados') || 
                name.includes('américa') || 
                name.includes('america') || 
                name.includes('chivas') || 
                name.includes('cruz azul')) {
                return 'liga mx';
            }
            // Other leagues remain unchanged
            if (name.includes('manchester') || name.includes('liverpool')) return 'premier league';
            if (name.includes('barcelona') || name.includes('real madrid')) return 'laliga';
            if (name.includes('psg')) return 'ligue 1';
            if (name.includes('selección') || name.includes('seleccion')) return 'selecciones';
            return '';
        }

        function getProductSize(card) {
            // In a real application, this would come from the product data
            // For now, we'll assume all products are available in all sizes
            return ['s', 'm', 'l'];
        }

        function filterProducts() {
            const selectedCategories = Array.from(document.querySelectorAll('.filter-section:nth-child(1) input:checked'))
                .map(checkbox => checkbox.parentElement.textContent.trim().toLowerCase());
            
            const selectedLeagues = Array.from(document.querySelectorAll('.filter-section:nth-child(2) input:checked'))
                .map(checkbox => checkbox.parentElement.textContent.trim().toLowerCase());
            
            const selectedSizes = Array.from(document.querySelectorAll('.filter-section:nth-child(3) input:checked'))
                .map(checkbox => checkbox.parentElement.textContent.trim().toLowerCase());

            const filteredProducts = originalProducts.filter(product => {
                const categoryMatch = selectedCategories.length === 0 || 
                    product.categories.some(cat => selectedCategories.includes(cat));
                const leagueMatch = selectedLeagues.length === 0 || 
                    selectedLeagues.includes(product.league);
                const sizeMatch = selectedSizes.length === 0 || 
                    product.size.some(s => selectedSizes.includes(s));

                return categoryMatch && leagueMatch && sizeMatch;
            });

            return filteredProducts;
        }

        function sortProducts(products) {
            const sortValue = sortSelect.value;
            return [...products].sort((a, b) => {
                switch (sortValue) {
                    case 'price-low':
                        return a.price - b.price;
                    case 'price-high':
                        return b.price - a.price;
                    case 'newest':
                        return -1; // For demo purposes, maintain original order
                    default: // 'featured'
                        return 0;
                }
            });
        }

        function updateProductsDisplay() {
            const filteredProducts = filterProducts();
            const sortedProducts = sortProducts(filteredProducts);

            // Hide all products first
            productCards.forEach(card => card.style.display = 'none');

            // Show filtered and sorted products
            sortedProducts.forEach(product => {
                product.element.style.display = '';
            });
        }

        // Add event listeners to filters and sort select
        filterCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateProductsDisplay);
        });

        if (sortSelect) {
            sortSelect.addEventListener('change', updateProductsDisplay);
        }
    }
});