document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.createElement('button');
    navToggle.className = 'nav-toggle';
    navToggle.innerHTML = '<i class="fas fa-bars"></i>';
    document.body.appendChild(navToggle);

    const sidebar = document.querySelector('.sidebar');

    navToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
    });

    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
        if (!sidebar.contains(event.target) && !navToggle.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });
});