document.addEventListener('DOMContentLoaded', function() {
    // Limpiar la URL para que no muestre el order_id
    if (window.history && window.history.replaceState) {
        // Mantener el order_id en una variable si se necesita usar
        const urlParams = new URLSearchParams(window.location.search);
        const orderId = urlParams.get('order_id');
        
        // Reemplazar la URL actual sin los parámetros
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Evitar que el usuario vuelva a la página de checkout
    window.history.pushState(null, null, window.location.href);
    window.addEventListener('popstate', function() {
        window.history.pushState(null, null, window.location.href);
    });
}); 