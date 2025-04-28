<?php
// Archivo de configuración para datos sensibles de Gift Cards

// Configuración del envío de correos
$EMAIL_CONFIG = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_auth' => true,
    'smtp_username' => 'JersixMx@gmail.com',
    'smtp_password' => 'onsi aafq qtdg lkyb', // Contraseña de aplicación actualizada
    'smtp_secure' => 'ssl',
    'smtp_port' => 465,
    'sender_email' => 'JersixMx@gmail.com',
    'sender_name' => 'JerSix'
];

/**
 * IMPORTANTE: Para usar Gmail, necesitas:
 * 1. Activar la verificación en 2 pasos en tu cuenta de Gmail
 * 2. Generar una contraseña de aplicación específica para esta aplicación:
 *    - Ve a tu cuenta de Google -> Seguridad -> Contraseñas de aplicaciones
 *    - Selecciona "Otra" como aplicación y dale un nombre (ej. "JerSix Tienda")
 *    - Copia la contraseña generada y pégala en 'smtp_password'
 * 
 * NOTA: Configuración actualizada para usar SSL en el puerto 465,
 * que suele ser más confiable que TLS para Gmail.
 */ 