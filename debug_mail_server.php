<?php
/**
 * Script para diagnosticar problemas de envío de correo en servidor de producción
 * Este script probará varios métodos de envío e informará sobre los resultados
 */

// Activar reporte de errores para diagnóstico
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración del correo
$testEmail = isset($_POST['email']) ? $_POST['email'] : 'jersixmx@gmail.com';
$serverInfo = [
    'hostname' => gethostname(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
    'php_version' => PHP_VERSION,
    'sapi' => php_sapi_name(),
    'mail_enabled' => function_exists('mail')
];

// Preparar archivo de log para este diagnóstico
$logFile = __DIR__ . '/logs/mail_diagnosis_' . date('Ymd_His') . '.log';
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

// Función para registrar en el log
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    file_put_contents($logFile, $log, FILE_APPEND);
    return $log;
}

// Iniciar el log
logMessage("=== INICIO DE DIAGNÓSTICO DE CORREO ===");
logMessage("Host: " . $serverInfo['hostname']);
logMessage("Software: " . $serverInfo['server_software']);
logMessage("PHP: " . $serverInfo['php_version'] . " (" . $serverInfo['sapi'] . ")");
logMessage("Mail() habilitado: " . ($serverInfo['mail_enabled'] ? 'Sí' : 'No'));

// Resultados de las pruebas
$results = [];

// PRUEBA 1: mail() nativo de PHP
function testNativeMail($to) {
    $subject = "Prueba de mail() nativo - " . date('H:i:s');
    $message = "Este es un mensaje de prueba desde " . $_SERVER['HTTP_HOST'] . " usando la función mail() nativa de PHP.";
    $headers = "From: no-reply@jersix.mx\r\n";
    $headers .= "Reply-To: jersixmx@gmail.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    logMessage("Prueba mail() nativo a: $to");
    
    try {
        $result = mail($to, $subject, $message, $headers);
        if ($result) {
            logMessage("mail() nativo: Éxito");
            return true;
        } else {
            logMessage("mail() nativo: Falló");
            return false;
        }
    } catch (Exception $e) {
        logMessage("mail() nativo: Error - " . $e->getMessage());
        return false;
    }
}

// PRUEBA 2: Configuración de Hosting
function testHostingMail($to) {
    logMessage("Prueba configuración del hosting a: $to");
    
    if (!file_exists(__DIR__ . '/configure_hosting_mail.php')) {
        logMessage("configure_hosting_mail.php no encontrado");
        return false;
    }
    
    try {
        require_once __DIR__ . '/configure_hosting_mail.php';
        $subject = "Prueba de Hosting - " . date('H:i:s');
        $message = "<p>Este es un mensaje de prueba usando la configuración del hosting.</p>";
        $result = sendHostingEmail($to, $subject, $message);
        
        if ($result) {
            logMessage("Hosting mail: Éxito");
            return true;
        } else {
            logMessage("Hosting mail: Falló");
            return false;
        }
    } catch (Exception $e) {
        logMessage("Hosting mail: Error - " . $e->getMessage());
        return false;
    }
}

// PRUEBA 3: Gmail SMTP con PHPMailer
function testGmailSMTP($to) {
    logMessage("Prueba Gmail SMTP a: $to");
    
    if (!file_exists(__DIR__ . '/configure_gmail_smtp.php')) {
        logMessage("configure_gmail_smtp.php no encontrado");
        return false;
    }
    
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        logMessage("PHPMailer no encontrado (vendor/autoload.php)");
        return false;
    }
    
    try {
        require_once __DIR__ . '/configure_gmail_smtp.php';
        $subject = "Prueba de Gmail SMTP - " . date('H:i:s');
        $message = "<p>Este es un mensaje de prueba usando Gmail SMTP con PHPMailer.</p>";
        
        $result = sendGmailEmail($to, $subject, $message);
        if ($result) {
            logMessage("Gmail SMTP: Éxito");
            return true;
        } else {
            logMessage("Gmail SMTP: Falló");
            return false;
        }
    } catch (Exception $e) {
        logMessage("Gmail SMTP: Error - " . $e->getMessage());
        return false;
    }
}

// PRUEBA 4: Correo de confirmación de pedido
function testOrderEmail($to) {
    logMessage("Prueba correo de confirmación a: $to");
    
    if (!file_exists(__DIR__ . '/order_confirmation_email.php')) {
        logMessage("order_confirmation_email.php no encontrado");
        return false;
    }
    
    try {
        require_once __DIR__ . '/order_confirmation_email.php';
        
        // Datos de prueba
        $orderData = [
            'order_id' => 'TEST-' . date('YmdHis'),
            'customer_name' => 'Cliente de Prueba',
            'customer_email' => $to,
            'created_at' => date('Y-m-d H:i:s'),
            'payment_method' => 'Tarjeta de crédito',
            'status' => 'Procesando',
            'street' => 'Calle de Prueba 123',
            'colonia' => 'Colonia Prueba',
            'city' => 'Ciudad de Prueba',
            'state' => 'Estado',
            'postal' => '12345',
            'shipping_cost' => 99.00,
            'discount' => 50.00,
            'total' => 599.00
        ];
        
        $orderItems = [
            [
                'product_id' => 1,
                'title' => 'Jersey de Prueba',
                'price' => 550.00,
                'quantity' => 1,
                'size' => 'M',
                'personalization_name' => 'TEST',
                'personalization_number' => '99',
                'image' => 'https://jersix.mx/img/ICON.png'
            ]
        ];
        
        $result = sendOrderConfirmationEmail($orderData, $orderItems);
        if ($result) {
            logMessage("Correo de confirmación: Éxito");
            return true;
        } else {
            logMessage("Correo de confirmación: Falló");
            return false;
        }
    } catch (Exception $e) {
        logMessage("Correo de confirmación: Error - " . $e->getMessage());
        return false;
    }
}

// PRUEBA 5: Cola de correos
function testEmailQueue($to) {
    logMessage("Prueba sistema de cola a: $to");
    
    if (!file_exists(__DIR__ . '/add_to_mail_queue.php')) {
        logMessage("add_to_mail_queue.php no encontrado");
        return false;
    }
    
    try {
        require_once __DIR__ . '/add_to_mail_queue.php';
        $subject = "Prueba de Cola - " . date('H:i:s');
        $message = "<p>Este es un mensaje de prueba usando el sistema de cola.</p>";
        
        $result = sendMail($to, $subject, $message, true, "TEST-QUEUE");
        if ($result) {
            logMessage("Cola de correos: Éxito al encolar");
            return true;
        } else {
            logMessage("Cola de correos: Falló al encolar");
            return false;
        }
    } catch (Exception $e) {
        logMessage("Cola de correos: Error - " . $e->getMessage());
        return false;
    }
}

// Ejecutar pruebas si se envía el formulario
if (isset($_POST['run_tests'])) {
    $results['native'] = testNativeMail($testEmail);
    $results['hosting'] = testHostingMail($testEmail);
    $results['gmail'] = testGmailSMTP($testEmail);
    $results['order'] = testOrderEmail($testEmail);
    $results['queue'] = testEmailQueue($testEmail);
    
    // Intentar procesar la cola de correos
    if (file_exists(__DIR__ . '/send_email_queue.php')) {
        logMessage("Procesando cola de correos...");
        try {
            include_once __DIR__ . '/send_email_queue.php';
            logMessage("Cola procesada");
        } catch (Exception $e) {
            logMessage("Error al procesar cola: " . $e->getMessage());
        }
    }
    
    // Resultados globales
    $anySuccess = in_array(true, $results);
    logMessage("=== RESULTADOS ===");
    logMessage("mail() nativo: " . ($results['native'] ? "ÉXITO" : "FALLÓ"));
    logMessage("Hosting mail: " . ($results['hosting'] ? "ÉXITO" : "FALLÓ"));
    logMessage("Gmail SMTP: " . ($results['gmail'] ? "ÉXITO" : "FALLÓ"));
    logMessage("Correo pedido: " . ($results['order'] ? "ÉXITO" : "FALLÓ"));
    logMessage("Cola: " . ($results['queue'] ? "ÉXITO" : "FALLÓ"));
    logMessage("Algún método tuvo éxito: " . ($anySuccess ? "SÍ" : "NO"));
}

// Verificar archivos de configuración
$filesExist = [
    'configure_hosting_mail.php' => file_exists(__DIR__ . '/configure_hosting_mail.php'),
    'configure_gmail_smtp.php' => file_exists(__DIR__ . '/configure_gmail_smtp.php'),
    'order_confirmation_email.php' => file_exists(__DIR__ . '/order_confirmation_email.php'),
    'add_to_mail_queue.php' => file_exists(__DIR__ . '/add_to_mail_queue.php'),
    'send_email_queue.php' => file_exists(__DIR__ . '/send_email_queue.php'),
    'vendor/autoload.php' => file_exists(__DIR__ . '/vendor/autoload.php'),
    'vendor/phpmailer' => file_exists(__DIR__ . '/vendor/phpmailer')
];

// Interfaz HTML 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Correo - Jersix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
        }
        h2 {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        pre {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow: auto;
            max-height: 300px;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table th, .info-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .info-table th {
            background-color: #f2f2f2;
            text-align: left;
        }
        .file-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .file-exists {
            background-color: #d4edda;
            color: #155724;
        }
        .file-missing {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <h1>Diagnóstico de Envío de Correos - Jersix</h1>
    
    <div class="container">
        <h2>Información del Servidor</h2>
        <table class="info-table">
            <tr>
                <th>Hostname</th>
                <td><?php echo htmlspecialchars($serverInfo['hostname']); ?></td>
            </tr>
            <tr>
                <th>Software</th>
                <td><?php echo htmlspecialchars($serverInfo['server_software']); ?></td>
            </tr>
            <tr>
                <th>PHP</th>
                <td><?php echo htmlspecialchars($serverInfo['php_version']); ?> (<?php echo htmlspecialchars($serverInfo['sapi']); ?>)</td>
            </tr>
            <tr>
                <th>mail() disponible</th>
                <td><?php echo $serverInfo['mail_enabled'] ? '<span class="success">Sí</span>' : '<span class="error">No</span>'; ?></td>
            </tr>
        </table>
        
        <h2>Archivos de Configuración</h2>
        <table class="info-table">
            <?php foreach ($filesExist as $file => $exists): ?>
            <tr>
                <th><?php echo htmlspecialchars($file); ?></th>
                <td>
                    <span class="file-status <?php echo $exists ? 'file-exists' : 'file-missing'; ?>">
                        <?php echo $exists ? 'Existe' : 'No existe'; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="container">
        <h2>Ejecutar Pruebas</h2>
        <form method="post">
            <div class="form-group">
                <label for="email">Correo de prueba:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($testEmail); ?>" required>
            </div>
            
            <button type="submit" name="run_tests">Ejecutar Todas las Pruebas</button>
        </form>
    </div>
    
    <?php if (isset($_POST['run_tests'])): ?>
    <div class="container">
        <h2>Resultados</h2>
        <table class="info-table">
            <tr>
                <th>Método</th>
                <th>Resultado</th>
            </tr>
            <tr>
                <td>mail() nativo</td>
                <td><?php echo $results['native'] ? '<span class="success">ÉXITO</span>' : '<span class="error">FALLÓ</span>'; ?></td>
            </tr>
            <tr>
                <td>Configuración del Hosting</td>
                <td><?php echo $results['hosting'] ? '<span class="success">ÉXITO</span>' : '<span class="error">FALLÓ</span>'; ?></td>
            </tr>
            <tr>
                <td>Gmail SMTP</td>
                <td><?php echo $results['gmail'] ? '<span class="success">ÉXITO</span>' : '<span class="error">FALLÓ</span>'; ?></td>
            </tr>
            <tr>
                <td>Correo de Confirmación</td>
                <td><?php echo $results['order'] ? '<span class="success">ÉXITO</span>' : '<span class="error">FALLÓ</span>'; ?></td>
            </tr>
            <tr>
                <td>Sistema de Cola</td>
                <td><?php echo $results['queue'] ? '<span class="success">ÉXITO</span>' : '<span class="error">FALLÓ</span>'; ?></td>
            </tr>
        </table>
        
        <h3>Recomendaciones</h3>
        <?php if (in_array(true, $results)): ?>
            <p class="success">✅ Al menos un método funciona. Utiliza el método que ha sido exitoso para tu sitio.</p>
            <?php if ($results['native']): ?>
                <p>- La función mail() nativa funciona. Puedes usar el método del hosting.</p>
            <?php endif; ?>
            <?php if ($results['gmail']): ?>
                <p>- Gmail SMTP funciona. Este es el método más confiable.</p>
            <?php endif; ?>
            <?php if ($results['queue']): ?>
                <p>- El sistema de cola funciona. Asegúrate de configurar el cron job para procesar la cola.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="error">❌ Ningún método ha tenido éxito. Revisa los logs para más detalles.</p>
            <p>Posibles soluciones:</p>
            <ul>
                <li>Solicita a tu proveedor de hosting que habilite las conexiones SMTP salientes</li>
                <li>Verifica que la contraseña de aplicación de Google sea correcta</li>
                <li>Configura registros SPF y DKIM para tu dominio</li>
                <li>Considera usar un servicio externo como SendGrid o Mailgun</li>
            </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="container">
        <h2>Log de Diagnóstico</h2>
        <pre><?php echo file_exists($logFile) ? htmlspecialchars(file_get_contents($logFile)) : 'No hay información de log disponible.'; ?></pre>
    </div>
    
    <div class="container">
        <h2>Soluciones para servidores de hosting</h2>
        <ol>
            <li><strong>Método 1 - Configurar SPF y DMARC</strong>: Añade registros DNS para mejorar la entregabilidad:
                <pre>TXT @ "v=spf1 a mx ip4:IP_DE_TU_SERVIDOR ~all"</pre>
            </li>
            <li><strong>Método 2 - Usar servicio SMTP externo</strong>: Considera usar SendGrid o Mailgun para enviar correos.</li>
            <li><strong>Método 3 - Verificación manual</strong>: Revisa el panel de control de correo en tu hosting para verificar si hay correos rechazados.</li>
            <li><strong>Método 4 - Personalizar cabeceras</strong>: Asegúrate de que las cabeceras From y Reply-To sean correctas.</li>
        </ol>
    </div>
    
    <p><a href="index.php">Volver al inicio</a></p>
</body>
</html> 