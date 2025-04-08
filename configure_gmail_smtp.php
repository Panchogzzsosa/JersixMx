<?php
/**
 * Script para configurar el envío de correos usando una cuenta de Gmail
 * 
 * Este script utiliza la biblioteca PHPMailer para enviar correos
 * a través del servidor SMTP de Gmail.
 */

// Comprobar si ya existe la carpeta vendor, si no, mostrar instrucciones
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "=============================================================\n";
    echo "Para usar Gmail como servidor SMTP, necesitas instalar PHPMailer.\n";
    echo "Sigue estos pasos:\n\n";
    echo "1. Abre una terminal y navega a: " . __DIR__ . "\n";
    echo "2. Ejecuta: composer require phpmailer/phpmailer\n";
    echo "3. Si no tienes Composer, descárgalo de: https://getcomposer.org/download/\n";
    echo "=============================================================\n";
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Función para enviar correo usando Gmail
 * 
 * @param string $to Dirección de correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $body Cuerpo del correo (HTML)
 * @param array $attachments Archivos adjuntos (opcional)
 * @return bool Resultado del envío
 */
function sendGmailEmail($to, $subject, $body, $attachments = []) {
    // Configuración de Gmail
    $gmailEmail = 'jersixmx@gmail.com';
    $gmailName = 'JersixMx';
    
    // Cargar contraseña desde un archivo seguro si existe
    // (más seguro que tenerla directamente en el código)
    $passwordFile = __DIR__ . '/smtp_credentials.php';
    if (file_exists($passwordFile)) {
        include $passwordFile; // Este archivo debe definir $gmailPassword
    } else {
        // Fallback a la contraseña hardcodeada (menos seguro)
        $gmailPassword = 'onsi aafq qtdg lkyb'; // Contraseña de aplicación proporcionada
    }
    
    $domainName = 'jersix.mx'; // Dominio principal para Message-ID
    
    // Crear instancia de PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $gmailEmail;
        $mail->Password = $gmailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Probar con SSL si TLS falla en el hosting 
        // (descomenta estas líneas y comenta las anteriores si es necesario)
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        // $mail->Port = 465;
        
        // Opciones adicionales de SMTP para mejorar la entrega
        $mail->SMTPKeepAlive = true; // Mantener la conexión para múltiples envíos
        $mail->Timeout = 60; // Aumentar el tiempo de espera
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Para debugging - habilitar esto al diagnosticar problemas en el hosting
        // $mail->SMTPDebug = 2; // Nivel de detalle: 1=errores, 2=errores y mensajes
        // $mail->Debugoutput = function($str, $level) {
        //     $logDir = __DIR__ . '/logs';
        //     if (!file_exists($logDir)) mkdir($logDir, 0777, true);
        //     file_put_contents($logDir . '/smtp_debug.log', date('Y-m-d H:i:s') . " [$level] $str\n", FILE_APPEND);
        // };
        
        // Configuración de charset para evitar problemas con acentos
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Cabeceras importantes para evitar el SPAM
        $mail->MessageID = '<' . time() . '-' . md5($to . $subject) . '@' . $domainName . '>';
        $mail->XMailer = 'JerSix Mailer 1.0';
        $mail->Priority = 3; // Normal priority
        
        // Cabeceras adicionales anti-spam
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
        $mail->addCustomHeader('List-Unsubscribe', '<mailto:unsubscribe@' . $domainName . '>, <https://' . $domainName . '/unsubscribe>');
        $mail->addCustomHeader('Feedback-ID', 'ORDEN:JerSix');
        $mail->addCustomHeader('X-Mailer-RecptId', md5($to));
        
        // Remitentes y destinatarios
        $mail->setFrom($gmailEmail, $gmailName);
        $mail->addReplyTo($gmailEmail, $gmailName);
        $mail->addAddress($to);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Crear versión alternativa en texto plano para mejorar la entrega
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body));
        $textBody = preg_replace('/[\t\n]+/', "\n", $textBody); // Normalizar saltos de línea
        $mail->AltBody = $textBody;
        
        // Archivos adjuntos
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        // Enviar correo
        $result = $mail->send();
        
        if ($result) {
            // Registrar el éxito en un archivo de log
            error_log("Correo enviado exitosamente a $to - Asunto: $subject", 0);
            // Registrar en el log detallado
            logEmailAttempt($to, $subject, true);
        }
        
        return $result;
    } catch (Exception $e) {
        $errorMessage = $mail->ErrorInfo;
        error_log("Error al enviar correo a $to: " . $errorMessage);
        // Registrar en el log detallado
        logEmailAttempt($to, $subject, false, $errorMessage);
        return false;
    }
}

// Función para probar el envío de correo (descomentar para probar)
function testGmailSend() {
    $to = 'jersixmx@gmail.com'; // Enviar a la misma cuenta como prueba
    $subject = 'Prueba de correo desde JerSix';
    $body = '
        <h1>Prueba de correo</h1>
        <p>Este es un correo de prueba enviado desde JerSix usando Gmail SMTP.</p>
        <p>Si puedes ver este correo, la configuración SMTP está funcionando correctamente.</p>
    ';
    
    $result = sendGmailEmail($to, $subject, $body);
    
    if ($result) {
        echo "¡Correo enviado correctamente!\n";
    } else {
        echo "Error al enviar el correo.\n";
    }
}

// Ejecutar la prueba
// testGmailSend();

// Comentado para evitar envíos de prueba automáticos en producción
// Para probar manualmente, crea un archivo test_email.php con:
// <?php
// require_once 'configure_gmail_smtp.php';
// testGmailSend();
// ?>

// Función para registrar logs detallados de envío de correo
function logEmailAttempt($to, $subject, $success, $errorInfo = '') {
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/email_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'ÉXITO' : 'ERROR';
    $message = "[$timestamp] $status - Para: $to - Asunto: $subject";
    
    if (!$success && !empty($errorInfo)) {
        $message .= " - Error: $errorInfo";
    }
    
    file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND);
}

echo "=============================================================\n";
echo "INSTRUCCIONES PARA USAR GMAIL COMO SERVIDOR SMTP:\n\n";
echo "1. Edita este archivo y busca la variable \$gmailPassword\n";
echo "2. Crea una contraseña de aplicación en tu cuenta de Google:\n";
echo "   a. Ve a https://myaccount.google.com/security\n";
echo "   b. En 'Iniciar sesión en Google', selecciona 'Contraseñas de aplicaciones'\n";
echo "   c. Selecciona 'Otra (nombre personalizado)' y escribe 'JerSix Web'\n";
echo "   d. Copia la contraseña generada y pégala en la variable \$gmailPassword\n";
echo "3. Asegúrate de habilitar la verificación en dos pasos en tu cuenta de Google\n";
echo "4. Una vez configurado, incluye este archivo en donde necesites enviar correos\n";
echo "5. Usa la función sendGmailEmail() en lugar de mail()\n";
echo "=============================================================\n"; 