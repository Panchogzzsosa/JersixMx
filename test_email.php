<?php
/**
 * Script para probar el envío de correos
 * 
 * Este archivo permite probar diferentes configuraciones de envío
 * de correo para diagnosticar problemas en servidores de hosting.
 */

// Mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Prueba de Envío de Correo</h1>";

// Cargar dependencias
require_once 'configure_gmail_smtp.php';

// Verificar métodos disponibles
echo "<h2>Métodos de Envío Disponibles:</h2>";
echo "<ul>";
if (function_exists('mail')) {
    echo "<li style='color:green'>PHP mail() - DISPONIBLE</li>";
} else {
    echo "<li style='color:red'>PHP mail() - NO DISPONIBLE</li>";
}

if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<li style='color:green'>PHPMailer - DISPONIBLE</li>";
} else {
    echo "<li style='color:red'>PHPMailer - NO DISPONIBLE</li>";
}
echo "</ul>";

// Información del servidor
echo "<h2>Información del Servidor:</h2>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li>Server Name: " . $_SERVER['SERVER_NAME'] . "</li>";
echo "</ul>";

// Formulario para prueba
echo "<h2>Enviar Correo de Prueba:</h2>";
echo "<form method='post'>";
echo "<label>Email Destino: <input type='email' name='test_email' value='jersixmx@gmail.com'></label><br>";
echo "<label>Asunto: <input type='text' name='test_subject' value='Prueba desde " . $_SERVER['SERVER_NAME'] . "'></label><br>";
echo "<label>Método: <select name='test_method'>";
echo "<option value='gmail'>Gmail SMTP</option>";
echo "<option value='ssl'>Gmail SSL (Puerto 465)</option>";
echo "<option value='phpmail'>PHP mail()</option>";
echo "</select></label><br>";
echo "<input type='submit' name='send_test' value='Enviar Correo de Prueba'>";
echo "</form>";

// Procesar envío de prueba
if (isset($_POST['send_test'])) {
    $to = $_POST['test_email'];
    $subject = $_POST['test_subject'];
    $method = $_POST['test_method'];
    
    $body = "
        <h1>Correo de Prueba</h1>
        <p>Este es un correo de prueba enviado desde " . $_SERVER['SERVER_NAME'] . " usando el método: $method</p>
        <p>Fecha y hora: " . date('Y-m-d H:i:s') . "</p>
        <p>Si puedes ver este correo, la configuración está funcionando correctamente.</p>
    ";
    
    echo "<h2>Resultado:</h2>";
    
    try {
        if ($method == 'gmail') {
            // Usar la configuración normal de Gmail SMTP
            $result = sendGmailEmail($to, $subject, $body);
            echo $result ? 
                "<p style='color:green'>Correo enviado correctamente usando Gmail SMTP.</p>" : 
                "<p style='color:red'>Error al enviar el correo usando Gmail SMTP.</p>";
        } 
        else if ($method == 'ssl') {
            // Probar con SSL en lugar de TLS
            require_once 'vendor/autoload.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'jersixmx@gmail.com';
            $mail->Password = 'onsi aafq qtdg lkyb';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom('jersixmx@gmail.com', 'JersixMx');
            $mail->addAddress($to);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $result = $mail->send();
            echo $result ? 
                "<p style='color:green'>Correo enviado correctamente usando Gmail SSL (puerto 465).</p>" : 
                "<p style='color:red'>Error al enviar el correo usando Gmail SSL: " . $mail->ErrorInfo . "</p>";
        }
        else if ($method == 'phpmail') {
            // Probar con la función mail() nativa de PHP
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: JersixMx <info@" . $_SERVER['SERVER_NAME'] . ">\r\n"; // Usar el dominio actual
            $headers .= "Reply-To: jersixmx@gmail.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "X-Priority: 3\r\n"; // Normal priority
            
            // Verificar configuración de PHP mail
            echo "<h3>Configuración de PHP mail():</h3>";
            echo "<ul>";
            echo "<li>sendmail_path: " . ini_get('sendmail_path') . "</li>";
            echo "<li>SMTP: " . ini_get('SMTP') . "</li>";
            echo "<li>smtp_port: " . ini_get('smtp_port') . "</li>";
            echo "</ul>";
            
            // Crear un archivo de registro específico para PHP mail
            $mailLogFile = __DIR__ . '/logs/phpmail_log.txt';
            if (!file_exists(dirname($mailLogFile))) {
                mkdir(dirname($mailLogFile), 0777, true);
            }
            
            // Registrar el intento de envío
            $logMessage = date('Y-m-d H:i:s') . " - Intentando enviar a: $to - Asunto: $subject\n";
            file_put_contents($mailLogFile, $logMessage, FILE_APPEND);
            
            // Enviar el correo
            $result = mail($to, $subject, $body, $headers);
            
            // Registrar el resultado
            $resultMessage = date('Y-m-d H:i:s') . " - Resultado: " . ($result ? "Éxito" : "Fallo") . "\n";
            file_put_contents($mailLogFile, $resultMessage, FILE_APPEND);
            
            echo $result ? 
                "<p style='color:green'>Correo enviado correctamente usando PHP mail().</p>" : 
                "<p style='color:red'>Error al enviar el correo usando PHP mail().</p>";
                
            echo "<p>Nota: El hecho de que la función mail() devuelva 'true' solo significa que PHP entregó el mensaje al servidor de correo local, no garantiza la entrega final al destinatario.</p>";
            
            // Mostrar instrucciones y sugerencias
            echo "<h3>Recomendaciones si los correos no llegan:</h3>";
            echo "<ol>";
            echo "<li>Verifica en tu carpeta de spam</li>";
            echo "<li>Consulta con tu proveedor de hosting si tienen restricciones o configuraciones especiales para mail()</li>";
            echo "<li>Intenta con una dirección 'From' que use el mismo dominio que tu sitio web</li>";
            echo "<li>Revisa los registros del servidor de correo (pregunta a tu proveedor de hosting)</li>";
            echo "</ol>";
            
            // Mostrar el log si existe
            if (file_exists($mailLogFile)) {
                echo "<h3>Log de PHP mail():</h3>";
                echo "<pre>" . htmlspecialchars(file_get_contents($mailLogFile)) . "</pre>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Error al enviar correo: " . $e->getMessage() . "</p>";
        
        // Mostrar más detalles para diagnóstico
        echo "<h3>Detalles del Error:</h3>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
    
    // Mostrar logs si existen
    $logFile = __DIR__ . '/logs/email_log.txt';
    if (file_exists($logFile)) {
        echo "<h3>Últimas entradas del log:</h3>";
        echo "<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
    }
} 