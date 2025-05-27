/**
 * Script para gestionar las suscripciones al newsletter
 */
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar todos los formularios de newsletter en la página
    const newsletterForms = document.querySelectorAll('.newsletter-form');
    const notification = document.getElementById('notification');
    
    // Si no hay formularios de newsletter, salir
    if (newsletterForms.length === 0) return;
    
    // Función para mostrar notificaciones
    function showNotification(message, isSuccess = true) {
        // Si no existe el elemento de notificación, salir
        if (!notification) {
            console.error('Elemento de notificación no encontrado');
            return;
        }
        
        // Configurar y mostrar la notificación
        notification.textContent = message;
        notification.className = isSuccess ? 'notification success show' : 'notification error show';
        notification.style.display = 'block';
        
        // Añadir un pequeño efecto de sonido al mostrar la notificación
        console.log('Mostrando notificación:', message);
        
        // Ocultar después de 3 segundos
        setTimeout(function() {
            notification.style.display = 'none';
            notification.className = isSuccess ? 'notification success' : 'notification error';
        }, 3000);
    }
    
    // Agregar evento a cada formulario
    newsletterForms.forEach(form => {
        const input = form.querySelector('.newsletter-input');
        const button = form.querySelector('.newsletter-button');
        
        // Si no existe el input o el botón, saltar este formulario
        if (!input || !button) {
            console.error('Input o botón de newsletter no encontrado');
            return;
        }
        
        // Evento de clic en el botón
        button.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botón de newsletter clickeado');
            submitNewsletter(input.value.trim());
        });
        
        // Permitir enviar con Enter
        input.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                console.log('Enter presionado en input de newsletter');
                submitNewsletter(input.value.trim());
            }
        });
    });
    
    // Función para enviar el correo al servidor
    function submitNewsletter(email) {
        // Validar que haya un correo
        if (!email) {
            showNotification('Por favor, ingresa tu correo electrónico', false);
            return;
        }
        
        console.log('Enviando correo: ' + email);
        
        // Crear un objeto FormData para enviar el correo
        const formData = new FormData();
        formData.append('email', email);
        
        // Determinar la ruta correcta al archivo save_newsletter.php
        const path = window.location.pathname;
        let basePath = '';
        
        // Si estamos en una subcarpeta, necesitamos ajustar la ruta
        if (path.includes('/Productos-equipos/') || path.includes('/admin/')) {
            basePath = '../';
        }
        
        console.log('Ruta de envío: ' + basePath + 'save_newsletter.php');
        
        // Mostrar notificación de carga
        showNotification('Enviando tu suscripción...', true);
        
        // Enviar la solicitud al servidor
        fetch(basePath + 'save_newsletter.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Respuesta recibida', response);
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Datos recibidos', data);
            showNotification(data.message, data.success);
            if (data.success) {
                // Limpiar todos los inputs de newsletter si fue exitoso
                document.querySelectorAll('.newsletter-input').forEach(input => {
                    input.value = '';
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Ocurrió un error al procesar tu solicitud', false);
        });
    }
}); 