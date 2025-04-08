<?php
/**
 * Prueba de envío de correos con la configuración optimizada para hosting
 */

// Mostrar todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir la configuración de correo para hosting
require_once 'configure_hosting_mail.php';

// Interfaz básica de prueba
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Prueba de correo para hosting</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #4CAF50; color: white; padding: 10px 15px; border: none; cursor: pointer; }
        .log { background: #f5f5f5; padding: 15px; margin-top: 20px; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Prueba de correo optimizada para hosting</h1>
        <p>Este script utiliza múltiples métodos para enviar correos, priorizando la compatibilidad con hosting compartido.</p>";

// Funcionalidad de envío de prueba
$result = null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $to = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? 'Prueba de Jersix';
    $content = $_POST['content'] ?? '';
    
    if (empty($to) || empty($subject) || empty($content)) {
        $message = "<p class='error'>Error: Todos los campos son requeridos.</p>";
    } else {
        // Construir el cuerpo del mensaje HTML
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .content { margin-bottom: 30px; }
                .footer { text-align: center; font-size: 12px; color: #777; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Jersix.mx</h1>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " Jersix.mx - Todos los derechos reservados
                </div>
            </div>
        </body>
        </html>";
        
        // Enviar correo usando la configuración optimizada para hosting
        $result = sendHostingEmail($to, $subject, $htmlBody);
        
        if ($result) {
            $message = "<p class='success'>Correo enviado correctamente. Revisa tu bandeja de entrada y carpeta de spam.</p>";
        } else {
            $message = "<p class='error'>Error al enviar el correo. Revisa los logs para más detalles.</p>";
        }
    }
}

// Formulario de prueba
echo $message;

echo "
        <form method='post'>
            <div class='form-group'>
                <label for='email'>Correo destino:</label>
                <input type='email' id='email' name='email' value='" . ($_POST['email'] ?? 'jersixmx@gmail.com') . "' required>
            </div>
            <div class='form-group'>
                <label for='subject'>Asunto:</label>
                <input type='text' id='subject' name='subject' value='" . ($_POST['subject'] ?? 'Prueba desde Jersix') . "' required>
            </div>
            <div class='form-group'>
                <label for='content'>Contenido:</label>
                <textarea id='content' name='content' rows='5' required>" . ($_POST['content'] ?? "Este es un correo de prueba.\n\nSi puedes ver este mensaje, la configuración de correo está funcionando correctamente.") . "</textarea>
            </div>
            <button type='submit' name='submit'>Enviar correo de prueba</button>
        </form>";

// Mostrar logs si existen
$logFile = __DIR__ . '/logs/hosting_mail.log';
if (file_exists($logFile)) {
    echo "<h2>Registro de actividad</h2>";
    echo "<div class='log'><pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre></div>";
}

echo "
        <h2>Recomendaciones para el envío de correos en hosting compartido</h2>
        <ol>
            <li>Utiliza una dirección 'From' con el mismo dominio que tu sitio web</li>
            <li>Mantén un equilibrio entre texto y HTML en tus correos</li>
            <li>Evita palabras que activen filtros de spam</li>
            <li>Configura registros SPF, DKIM y DMARC para tu dominio</li>
            <li>Si después de todo no funciona, considera usar servicios como SendGrid, Mailgun o Amazon SES</li>
        </ol>
    </div>
</body>
</html>";
?> 