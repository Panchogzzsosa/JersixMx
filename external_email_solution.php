<?php
/**
 * Solución para envío de correos con SendGrid
 * 
 * Esta es una alternativa al servicio de correo del hosting
 * que utiliza la API de SendGrid para garantizar la entrega.
 * 
 * REQUISITOS:
 * 1. Regístrate en SendGrid (tienen un plan gratuito)
 * 2. Obtén una API Key desde el panel de SendGrid
 * 3. Instala la librería con: composer require sendgrid/sendgrid
 */

// Configuración de SendGrid
$sendgridApiKey = 'TU_API_KEY_AQUI'; // Reemplazar con tu API key
$senderEmail = 'no-reply@jersix.mx'; // Usar tu dominio verificado
$senderName = 'Jersix.mx';

// Log de actividad
$logFile = __DIR__ . '/logs/sendgrid_activity.log';

/**
 * Enviar correo usando SendGrid
 * 
 * @param string $to Correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $htmlContent Contenido HTML
 * @param string $plainContent Contenido texto plano (opcional)
 * @return bool Resultado del envío
 */
function sendWithSendGrid($to, $subject, $htmlContent, $plainContent = '') {
    global $sendgridApiKey, $senderEmail, $senderName;
    
    // Si no se proporcionó contenido en texto plano, generarlo del HTML
    if (empty($plainContent)) {
        $plainContent = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlContent));
    }
    
    // Registrar intento
    logSendGridActivity("Iniciando envío a $to - Asunto: $subject");
    
    try {
        // Verificar que la librería está instalada
        if (!class_exists('SendGrid')) {
            throw new Exception("La librería de SendGrid no está instalada. Ejecuta: composer require sendgrid/sendgrid");
        }
        
        // Crear email
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($senderEmail, $senderName);
        $email->setSubject($subject);
        $email->addTo($to);
        $email->addContent("text/plain", $plainContent);
        $email->addContent("text/html", $htmlContent);
        
        // Enviar email
        $sendgrid = new \SendGrid($sendgridApiKey);
        $response = $sendgrid->send($email);
        
        // Verificar respuesta
        $statusCode = $response->statusCode();
        $success = ($statusCode >= 200 && $statusCode < 300);
        
        // Registrar resultado
        if ($success) {
            logSendGridActivity("Correo enviado exitosamente a $to - Código: $statusCode");
        } else {
            $responseBody = json_decode($response->body(), true);
            $errorMessage = isset($responseBody['errors'][0]['message']) ? $responseBody['errors'][0]['message'] : 'Error desconocido';
            logSendGridActivity("Error al enviar correo a $to - Código: $statusCode - Mensaje: $errorMessage");
        }
        
        return $success;
        
    } catch (Exception $e) {
        logSendGridActivity("Excepción al enviar correo a $to: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para probar el envío con SendGrid
 * 
 * @param string $to Correo de prueba
 * @return bool Resultado de la prueba
 */
function testSendGrid($to) {
    $subject = 'Prueba de SendGrid - ' . date('Y-m-d H:i:s');
    $htmlContent = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            .content { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Prueba de SendGrid</h1>
            </div>
            <div class='content'>
                <p>Este es un correo de prueba enviado usando SendGrid.</p>
                <p>Si puedes ver este mensaje, la configuración está funcionando correctamente.</p>
                <p>Fecha y hora: " . date('Y-m-d H:i:s') . "</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendWithSendGrid($to, $subject, $htmlContent);
}

/**
 * Registrar actividad en el log
 * 
 * @param string $message Mensaje a registrar
 * @return void
 */
function logSendGridActivity($message) {
    global $logFile;
    
    // Crear directorio si no existe
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Escribir en el log
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Instrucciones para configurar SendGrid:
 * 
 * 1. Crea una cuenta en SendGrid (tienen plan gratuito con 100 emails/día)
 * 2. Verifica tu dominio en SendGrid (importante para mejorar la entrega)
 * 3. Genera una API Key desde el panel de SendGrid
 * 4. Instala la librería con Composer: composer require sendgrid/sendgrid
 * 5. Reemplaza 'TU_API_KEY_AQUI' con tu clave de API real
 * 6. Incluye este archivo en tu proyecto y usa sendWithSendGrid() para enviar correos
 * 
 * Este método es más confiable que la función mail() de PHP, especialmente 
 * en servidores compartidos donde el envío de correo puede estar restringido.
 */

// Ejemplo de uso:
// include_once 'external_email_solution.php';
// sendWithSendGrid('correo@ejemplo.com', 'Asunto del correo', '<p>Contenido HTML</p>');
?> 