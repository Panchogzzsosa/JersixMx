document.addEventListener('DOMContentLoaded', function() {
    const searchContainer = document.querySelector('.search-container');
    const searchInput = document.querySelector('.search-input');
    const searchButton = document.querySelector('.search-button');
    const navLinks = document.querySelector('.nav-links');

    // Toggle search container
    searchButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        searchContainer.classList.toggle('active');
        if (window.innerWidth <= 768) {
            navLinks.classList.remove('active');
        }
    });

    // Prevent search container from closing when clicking inside it
    searchContainer.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Close search container when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchContainer.contains(e.target) && !searchButton.contains(e.target)) {
            searchContainer.classList.remove('active');
        }
    });

    // Prevent search input from triggering navigation menu toggle
    searchInput.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    // Prevent form submission
    const searchForm = searchInput.closest('form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    }
});