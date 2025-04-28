<?php
// Archivo de configuración para datos sensibles de Gift Cards

// Configuración del envío de correos
$EMAIL_CONFIG = [
    'smtp_host' => 'mail.jersix.mx',
    'smtp_auth' => true,
    'smtp_username' => 'no-reply@jersix.mx',
    'smtp_password' => 'Jersix.mx141423',
    'smtp_secure' => 'ssl',
    'smtp_port' => 465,
    'sender_email' => 'no-reply@jersix.mx',
    'sender_name' => 'JersixMx'
];

/**
 * IMPORTANTE: Esta configuración usa el servidor de correo configurado en mail.jersix.mx
 * 
 * Si tienes problemas con esta configuración, puedes intentar con Gmail como alternativa:
 * 'smtp_host' => 'smtp.gmail.com',
 * 'smtp_username' => 'jersixmx@gmail.com', 
 * 'smtp_password' => 'onsi aafq qtdg lkyb'
 * 
 * NOTA: Configuración actualizada para usar SSL en el puerto 465,
 * que suele ser más confiable para la mayoría de los servidores de correo.
 */ 