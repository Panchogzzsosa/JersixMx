<?php
/**
 * Configuración para envío de correos en hosting compartido
 * Optimizado para evitar problemas de spam y mejorar la entrega
 */

// Configurar correo del administrador
$adminEmail = "jersixmx@gmail.com";

// Configurar correo del remitente (debe coincidir con el dominio)
$senderEmail = "no-reply@jersix.mx";
$senderName = "Jersix.mx";

// Ruta al log de actividad de correos
$mailLogFile = __DIR__ . "/logs/mail_activity.log";

/**
 * Función para enviar correos desde hosting compartido
 * 
 * @param string $to Dirección de correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $htmlBody Cuerpo HTML del correo
 * @param array $attachments Archivos adjuntos opcional (array de rutas)
 * @return bool True si el correo se envió correctamente
 */
function sendHostingEmail($to, $subject, $htmlBody, $attachments = []) {
    global $senderEmail, $senderName;
    
    // Registrar intento de envío
    logHostingMailActivity("Iniciando envío a: $to, asunto: $subject");
    
    // Generar texto plano a partir del HTML
    $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '<div>', '</div>'], "\n", $htmlBody));
    $plainText = preg_replace('/[\t\n]+/', "\n", $plainText);
    
    // Método 1: Usar mail() con encabezados simples - a veces funciona mejor en algunos hostings
    $simpleHeaders = "From: $senderName <$senderEmail>\r\n";
    $simpleHeaders .= "Reply-To: $senderEmail\r\n";
    $simpleHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $result = mail($to, $subject, $htmlBody, $simpleHeaders);
    
    if ($result) {
        logHostingMailActivity("Correo enviado correctamente a: $to usando método simple");
        return true;
    }
    
    // Si el método simple falló, intentar con multipart
    logHostingMailActivity("Método simple falló, intentando con multipart alternativo");
    
    // Generar un boundary para el contenido multiparte
    $boundary = md5(time());
    
    // Cabeceras del correo para multipart
    $headers = [
        'From' => "$senderName <$senderEmail>", 
        'Reply-To' => $senderEmail,
        'Return-Path' => $senderEmail,
        'MIME-Version' => '1.0',
        'X-Mailer' => 'PHP/' . phpversion(),
        'Content-Type' => "multipart/alternative; boundary=\"$boundary\""
    ];
    
    // Convertir array de cabeceras a string
    $headersStr = '';
    foreach ($headers as $name => $value) {
        $headersStr .= "$name: $value\r\n";
    }
    
    // Contenido del mensaje
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= "$plainText\r\n\r\n";
    
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= "$htmlBody\r\n\r\n";
    
    // Agregar adjuntos si existen
    if (!empty($attachments)) {
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $content = chunk_split(base64_encode($content));
                $filename = basename($file);
                
                $message .= "--$boundary\r\n";
                $message .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
                $message .= "$content\r\n\r\n";
            }
        }
    }
    
    $message .= "--$boundary--";
    
    // Enviar el correo con multipart
    $success = mail($to, $subject, $message, $headersStr);
    
    // Registrar la actividad
    logHostingMailActivity(
        $success 
            ? "Correo enviado correctamente a: $to, asunto: $subject (modo multipart)" 
            : "Error al enviar correo a: $to, asunto: $subject (ambos métodos fallaron)"
    );
    
    return $success;
}

/**
 * Función para registrar actividad de correos
 * 
 * @param string $message Mensaje a registrar
 * @return void
 */
function logHostingMailActivity($message) {
    global $mailLogFile;
    
    // Crear directorio de logs si no existe
    $logDir = dirname($mailLogFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Registrar mensaje
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    // Escribir en el archivo de log
    file_put_contents($mailLogFile, $logMessage, FILE_APPEND);
}

/**
 * Función para probar el envío de correo
 * 
 * @param string $to Dirección de correo de prueba
 * @return bool Resultado de la prueba
 */
function testHostingEmail($to) {
    $subject = "Prueba de Correo - Jersix.mx";
    $htmlBody = "<html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            .content { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
            .footer { text-align: center; font-size: 12px; color: #777; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Prueba de Correo</h1>
            </div>
            
            <div class='content'>
                <p>Este es un correo de prueba enviado desde el sistema de Jersix.mx.</p>
                <p>Si estás recibiendo este correo, significa que la configuración de correo está funcionando correctamente.</p>
                <p>Hora de envío: " . date('Y-m-d H:i:s') . "</p>
            </div>
            
            <div class='footer'>
                &copy; " . date('Y') . " Jersix.mx - Todos los derechos reservados
            </div>
        </div>
    </body>
    </html>";
    
    return sendHostingEmail($to, $subject, $htmlBody);
}

/**
 * Recomendaciones para mejorar la entrega de correos en hosting compartido:
 * 
 * 1. Configurar registros SPF, DKIM y DMARC para tu dominio
 * 2. Utilizar una dirección de correo que coincida con el dominio
 * 3. Incluir versión de texto plano junto con HTML
 * 4. No incluir demasiadas imágenes o archivos adjuntos grandes
 * 5. Evitar palabras o frases típicas de spam
 * 6. Mantener una relación equilibrada entre texto e imágenes
 * 7. Verificar que el hosting permite el envío de correos
 * 8. Considerar usar un servicio SMTP externo como Mailgun o SendGrid si persisten problemas
 */
?> 