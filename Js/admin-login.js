document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();
        
        // Basic validation
        if (!username || !password) {
            showError('Por favor complete todos los campos');
            return;
        }
        
        try {
            const response = await fetch('verify_admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                window.location.href = 'dashboard.php';
            } else {
                showError(data.message || 'Usuario o contraseña incorrectos');
            }
        } catch (error) {
            showError('Error al iniciar sesión. Por favor intente nuevamente.');
            console.error('Login error:', error);
        }
    });
    
    function showError(message) {
        // Remove any existing error messages
        const existingError = document.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Create and show new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        
        const loginBox = document.querySelector('.login-box');
        loginBox.insertBefore(errorDiv, loginForm);
        
        // Auto-remove error message after 5 seconds
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }
});