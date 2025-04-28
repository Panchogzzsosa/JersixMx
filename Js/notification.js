/**
 * Utility for displaying non-intrusive notifications
 * This script provides a standardized way to show notifications across the site
 */

class NotificationManager {
    constructor() {
        // Check if CSS is loaded
        this.ensureStylesLoaded();
    }

    ensureStylesLoaded() {
        // Check if the notification styles are already loaded
        let styleLoaded = false;
        const stylesheets = document.querySelectorAll('link[rel="stylesheet"]');
        stylesheets.forEach(sheet => {
            if (sheet.href.includes('notificacion.css')) {
                styleLoaded = true;
            }
        });

        // If styles are not loaded, load them
        if (!styleLoaded) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = '/Css/notificacion.css';
            document.head.appendChild(link);
        }
    }

    /**
     * Show a notification message
     * @param {string} message - The message to display
     * @param {string} type - The type of notification ('success' or 'error')
     * @param {number} duration - How long to show the notification in milliseconds
     */
    show(message, type = 'success', duration = 3000) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);

        // Show with animation
        setTimeout(() => {
            notification.classList.add('show');
            
            // Hide after duration
            setTimeout(() => {
                notification.classList.remove('show');
                
                // Remove from DOM after animation completes
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, duration);
        }, 100);

        return notification;
    }

    /**
     * Show a success notification
     * @param {string} message - The message to display
     * @param {number} duration - How long to show the notification in milliseconds
     */
    success(message, duration = 3000) {
        return this.show(message, 'success', duration);
    }

    /**
     * Show an error notification
     * @param {string} message - The message to display
     * @param {number} duration - How long to show the notification in milliseconds
     */
    error(message, duration = 3000) {
        return this.show(message, 'error', duration);
    }
}

// Create a global instance
const notifications = new NotificationManager();

// For backwards compatibility with existing code
function showNotification(message, isSuccess = true, duration = 3000) {
    if (isSuccess) {
        notifications.success(message, duration);
    } else {
        notifications.error(message, duration);
    }
}