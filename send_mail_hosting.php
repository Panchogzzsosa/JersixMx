<?php
/**
 * Este archivo proporciona una función alternativa para enviar correos utilizando
 * la configuración del servidor de hosting, en caso de que la función mail() nativa falle.
 */

/**
 * Función para enviar correo utilizando la configuración del servidor de hosting
 * 
 * @param string $to Dirección de correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $message Cuerpo del correo (HTML)
 * @param string $from Dirección de correo del remitente
 * @param string $fromName Nombre del remitente
 * @return bool Resultado del envío
 */
function sendMailHosting($to, $subject, $message, $from = 'no-reply@jersix.mx', $fromName = 'JerSix') {
    // Información de registro
    if (function_exists('writeLogEmail')) {
        writeLogEmail("Intentando enviar correo con método alternativo a: " . $to);
    }
    
    // Cabeceras del correo
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $from . '>',
        'Reply-To: jersixmx@gmail.com',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 1',
        'X-MSMail-Priority: High'
    ];
    
    // Parámetros adicionales para mail()
    $parameters = '-f' . $from;
    
    // Intentar enviar el correo con parámetros adicionales
    $result = mail($to, $subject, $message, implode("\r\n", $headers), $parameters);
    
    if ($result && function_exists('writeLogEmail')) {
        writeLogEmail("Correo enviado exitosamente usando método alternativo del hosting.");
    } else if (function_exists('writeLogEmail')) {
        writeLogEmail("ERROR: El envío falló usando método alternativo del hosting.");
    }
    
    return $result;
}

// Función para verificar si el servidor de correo está funcionando
function testMailServer($testEmail = 'jersixmx@gmail.com') {
    $subject = 'Test de servidor de correo - JerSix';
    $message = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Test de Correo</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h1 style="color: #333;">Prueba de Servidor de Correo</h1>
            <p>Este es un mensaje de prueba para verificar que el servidor de correo está funcionando correctamente.</p>
            <p>Fecha de prueba: ' . date('Y-m-d H:i:s') . '</p>
            <p>Servidor: ' . $_SERVER['SERVER_NAME'] . '</p>
            <hr>
            <p style="font-size: 12px; color: #777;">Este es un correo automatizado, por favor no responder.</p>
        </div>
    </body>
    </html>';
    
    // Intentar enviar correo de prueba
    $result = sendMailHosting($testEmail, $subject, $message);
    
    return $result ? "Correo de prueba enviado exitosamente a $testEmail" : "Error al enviar correo de prueba";
}

// Si este archivo se ejecuta directamente, realizar una prueba
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    // Crear carpeta de logs si no existe
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    // Definir función de log si no existe
    if (!function_exists('writeLogEmail')) {
        function writeLogEmail($message) {
            $logFile = __DIR__ . '/logs/mail_test.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] $message\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            echo $logMessage . "<br>";
        }
    }
    
    // Mostrar resultados de la prueba
    echo '<h1>Prueba de Servidor de Correo</h1>';
    echo '<p>' . testMailServer() . '</p>';
    echo '<p><a href="index.php">Volver al inicio</a></p>';
} 