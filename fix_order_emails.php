<?php
/**
 * Script para solucionar problemas de envío de correos de confirmación de pedido
 * Este script modificará la configuración para adaptarse al servidor de producción
 */

// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Obtener información del servidor
$serverInfo = [
    'hostname' => gethostname(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
    'domain' => $_SERVER['HTTP_HOST'] ?? 'jersix.mx',
    'php_version' => PHP_VERSION,
    'sapi' => php_sapi_name(),
    'is_localhost' => in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])
];

// Archivos a verificar y modificar
$files = [
    'order_confirmation_email.php',
    'configure_gmail_smtp.php',
    'configure_hosting_mail.php',
    'add_to_mail_queue.php',
    'send_email_queue.php'
];

// Verificar y crear directorios necesarios
$dirs = [
    'logs',
    'mail_queue',
    'mail_queue/processed'
];

// Resultados de las operaciones
$results = [];
$logs = [];

// Función para registrar logs
function addLog($message) {
    global $logs;
    $logs[] = $message;
    echo "$message<br>";
}

// 1. Verificar y crear directorios
addLog("Verificando directorios necesarios...");
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!file_exists($path)) {
        if (mkdir($path, 0777, true)) {
            addLog("✅ Directorio creado: $dir");
            $results['dirs'][$dir] = true;
        } else {
            addLog("❌ Error al crear directorio: $dir");
            $results['dirs'][$dir] = false;
        }
    } else {
        addLog("✓ Directorio ya existe: $dir");
        $results['dirs'][$dir] = true;
        
        // Verificar permisos y corregir si es necesario
        if (!is_writable($path)) {
            if (chmod($path, 0777)) {
                addLog("✅ Permisos corregidos para: $dir");
            } else {
                addLog("⚠️ No se pudieron corregir permisos para: $dir");
            }
        }
    }
}

// 2. Verificar archivos de configuración
addLog("<hr>Verificando archivos de configuración...");
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        addLog("✓ Archivo existe: $file");
        $results['files'][$file] = true;
    } else {
        addLog("❌ Archivo no encontrado: $file");
        $results['files'][$file] = false;
    }
}

// 3. Verificar archivo de log de correos
$orderEmailLog = __DIR__ . '/logs/order_emails.log';
if (!file_exists($orderEmailLog)) {
    if (file_put_contents($orderEmailLog, "[" . date('Y-m-d H:i:s') . "] Archivo de log creado para correos de pedidos\n")) {
        chmod($orderEmailLog, 0666);
        addLog("✅ Archivo de log creado: order_emails.log");
        $results['logs']['order_emails'] = true;
    } else {
        addLog("❌ Error al crear archivo de log: order_emails.log");
        $results['logs']['order_emails'] = false;
    }
} else {
    addLog("✓ Archivo de log ya existe: order_emails.log");
    $results['logs']['order_emails'] = true;
}

// 4. Modificar order_confirmation_email.php para adaptarlo al servidor de producción
if ($results['files']['order_confirmation_email.php']) {
    addLog("<hr>Adaptando order_confirmation_email.php al servidor...");
    
    $file = __DIR__ . '/order_confirmation_email.php';
    $content = file_get_contents($file);
    
    // Hacer backup del archivo original
    $backupFile = $file . '.bak_' . date('YmdHis');
    file_put_contents($backupFile, $content);
    addLog("✓ Backup creado: " . basename($backupFile));
    
    // Modificaciones necesarias para producción
    $changes = [
        // 1. Asegurar que el remitente sea no-reply@dominio
        'From: JerSix <jersixmx@gmail.com>' => 'From: JerSix <no-reply@' . $serverInfo['domain'] . '>',
        
        // 2. Cambiar estrategia de envío para priorizar método del hosting en producción
        'require_once __DIR__ . \'/configure_gmail_smtp.php\';' => 
        '// Determinar si estamos en producción o desarrollo
        $isProduction = !in_array($_SERVER[\'REMOTE_ADDR\'] ?? \'\', [\'127.0.0.1\', \'::1\']);
        
        if ($isProduction) {
            // En producción, intentar primero con el método del hosting
            require_once __DIR__ . \'/configure_hosting_mail.php\';
            $mailResult = sendHostingEmail($customerEmail, $subject, $htmlMessage);
            
            if ($mailResult) {
                writeLogEmail("Método Hosting: Correo enviado exitosamente a " . $customerEmail);
                return true;
            } else {
                writeLogEmail("ERROR: Método Hosting falló para " . $customerEmail . ". Intentando Gmail SMTP.");
                require_once __DIR__ . \'/configure_gmail_smtp.php\';
                $mailResult = sendGmailEmail($customerEmail, $subject, $htmlMessage);
            }
        } else {
            // En desarrollo, usar primero Gmail SMTP
            require_once __DIR__ . \'/configure_gmail_smtp.php\';
            $mailResult = sendGmailEmail($customerEmail, $subject, $htmlMessage);
        }',
    ];
    
    // Aplicar cambios
    $newContent = $content;
    foreach ($changes as $search => $replace) {
        $newContent = str_replace($search, $replace, $newContent);
    }
    
    // Guardar cambios
    if ($content !== $newContent) {
        if (file_put_contents($file, $newContent)) {
            addLog("✅ order_confirmation_email.php adaptado al servidor");
            $results['updated']['order_confirmation_email.php'] = true;
        } else {
            addLog("❌ Error al modificar order_confirmation_email.php");
            $results['updated']['order_confirmation_email.php'] = false;
        }
    } else {
        addLog("⚠️ No se detectaron cambios necesarios en order_confirmation_email.php");
        $results['updated']['order_confirmation_email.php'] = 'no_changes';
    }
}

// 5. Probar envío de correo
addLog("<hr>Probando envío de correo...");

// Verificar si PHPMailer está disponible
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    addLog("✓ PHPMailer disponible");
    $results['phpmailer'] = true;
} else {
    addLog("⚠️ PHPMailer no encontrado. Instalando...");
    $results['phpmailer'] = false;
    
    // Intentar instalar PHPMailer si composer está disponible
    $composerPath = exec('which composer 2>/dev/null');
    if (!empty($composerPath)) {
        $cmd = "cd " . __DIR__ . " && $composerPath require phpmailer/phpmailer 2>&1";
        $output = [];
        exec($cmd, $output, $returnVar);
        
        if ($returnVar === 0) {
            addLog("✅ PHPMailer instalado correctamente");
            $results['phpmailer_install'] = true;
        } else {
            addLog("❌ Error al instalar PHPMailer: " . implode("\n", $output));
            $results['phpmailer_install'] = false;
        }
    } else {
        addLog("❌ Composer no disponible. No se puede instalar PHPMailer automáticamente");
        $results['phpmailer_install'] = false;
    }
}

// 6. Probar envío de correo de prueba si se solicita
if (isset($_POST['test_email']) && !empty($_POST['email'])) {
    $testEmail = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    addLog("Enviando correo de prueba a $testEmail...");
    
    // Intentar enviar usando order_confirmation_email.php
    if ($results['files']['order_confirmation_email.php']) {
        require_once __DIR__ . '/order_confirmation_email.php';
        
        // Datos de prueba
        $orderData = [
            'order_id' => 'TEST-' . date('YmdHis'),
            'customer_name' => 'Cliente de Prueba',
            'customer_email' => $testEmail,
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
        
        try {
            $sent = sendOrderConfirmationEmail($orderData, $orderItems);
            if ($sent) {
                addLog("✅ Correo enviado correctamente a $testEmail");
                $results['test_email'] = true;
            } else {
                addLog("❌ Error al enviar correo a $testEmail");
                $results['test_email'] = false;
            }
        } catch (Exception $e) {
            addLog("❌ Excepción al enviar correo: " . $e->getMessage());
            $results['test_email'] = false;
        }
    } else {
        addLog("❌ No se puede probar envío sin order_confirmation_email.php");
        $results['test_email'] = false;
    }
}

// 7. Procesar la cola de correos si se solicita
if (isset($_POST['process_queue']) && $results['files']['send_email_queue.php']) {
    addLog("<hr>Procesando cola de correos...");
    
    try {
        include_once __DIR__ . '/send_email_queue.php';
        addLog("✅ Cola de correos procesada");
        $results['process_queue'] = true;
    } catch (Exception $e) {
        addLog("❌ Error al procesar cola: " . $e->getMessage());
        $results['process_queue'] = false;
    }
}

// 8. Mostrar instrucciones para configurar CRON
$hasSuccessfulMethod = false;
if (isset($results['test_email']) && $results['test_email']) {
    $hasSuccessfulMethod = true;
}

// Guardar resultados en archivo de log
$logFile = __DIR__ . '/logs/fix_order_emails_' . date('Ymd_His') . '.log';
$logContent = "=== RESULTADOS DE FIX_ORDER_EMAILS.PHP ===\n";
$logContent .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
$logContent .= "Servidor: " . $serverInfo['hostname'] . "\n";
$logContent .= "Dominio: " . $serverInfo['domain'] . "\n\n";
$logContent .= implode("\n", $logs) . "\n";
file_put_contents($logFile, $logContent);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solución de Correos - Jersix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
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
            margin-right: 10px;
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
        .warning {
            color: orange;
            font-weight: bold;
        }
        .code-box {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow: auto;
            font-family: monospace;
            margin: 10px 0;
        }
        hr {
            border: 0;
            border-top: 1px solid #eee;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>Solución de Problemas de Correo - Jersix</h1>
    
    <div class="container">
        <h2>Información del Servidor</h2>
        <p><strong>Hostname:</strong> <?php echo htmlspecialchars($serverInfo['hostname']); ?></p>
        <p><strong>Software:</strong> <?php echo htmlspecialchars($serverInfo['server_software']); ?></p>
        <p><strong>Dominio:</strong> <?php echo htmlspecialchars($serverInfo['domain']); ?></p>
        <p><strong>PHP:</strong> <?php echo htmlspecialchars($serverInfo['php_version']); ?> (<?php echo htmlspecialchars($serverInfo['sapi']); ?>)</p>
        <p><strong>Entorno:</strong> <?php echo $serverInfo['is_localhost'] ? 'Desarrollo (localhost)' : 'Producción'; ?></p>
    </div>
    
    <div class="container">
        <h2>Estado del Sistema</h2>
        
        <h3>Directorios</h3>
        <ul>
            <?php foreach ($results['dirs'] ?? [] as $dir => $status): ?>
            <li>
                <?php echo htmlspecialchars($dir); ?>: 
                <?php if ($status): ?>
                <span class="success">✓</span>
                <?php else: ?>
                <span class="error">✗</span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        
        <h3>Archivos</h3>
        <ul>
            <?php foreach ($results['files'] ?? [] as $file => $status): ?>
            <li>
                <?php echo htmlspecialchars($file); ?>: 
                <?php if ($status): ?>
                <span class="success">✓</span>
                <?php else: ?>
                <span class="error">✗</span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        
        <h3>Actualizaciones</h3>
        <ul>
            <?php foreach ($results['updated'] ?? [] as $file => $status): ?>
            <li>
                <?php echo htmlspecialchars($file); ?>: 
                <?php if ($status === true): ?>
                <span class="success">Actualizado</span>
                <?php elseif ($status === 'no_changes'): ?>
                <span class="warning">Sin cambios necesarios</span>
                <?php else: ?>
                <span class="error">Error al actualizar</span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="container">
        <h2>Probar Envío de Correo</h2>
        <form method="post">
            <div class="form-group">
                <label for="email">Correo de prueba:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? 'jersixmx@gmail.com'); ?>" required>
            </div>
            
            <button type="submit" name="test_email">Enviar Correo de Prueba</button>
            <button type="submit" name="process_queue">Procesar Cola de Correos</button>
        </form>
    </div>
    
    <?php if ($hasSuccessfulMethod): ?>
    <div class="container">
        <h2 class="success">Configuración para CRON Job</h2>
        <p>Para procesar automáticamente la cola de correos, configura un CRON job en tu panel de hosting:</p>
        
        <div class="code-box">
            */10 * * * * php <?php echo __DIR__ ?>/send_email_queue.php
        </div>
        
        <p>Esto ejecutará el script cada 10 minutos para procesar correos en cola.</p>
    </div>
    <?php endif; ?>
    
    <div class="container">
        <h2>Resumen de Correcciones</h2>
        
        <?php if (isset($results['updated']['order_confirmation_email.php']) && $results['updated']['order_confirmation_email.php'] === true): ?>
        <p class="success">✅ Se ha adaptado el sistema de correos para el servidor de producción.</p>
        <?php endif; ?>
        
        <?php if (isset($results['test_email'])): ?>
            <?php if ($results['test_email']): ?>
            <p class="success">✅ La prueba de envío de correo fue exitosa. El sistema está funcionando correctamente.</p>
            <?php else: ?>
            <p class="error">❌ La prueba de envío de correo falló. Revisa los logs para más detalles.</p>
            <?php endif; ?>
        <?php endif; ?>
        
        <h3>Recomendaciones Adicionales</h3>
        <ol>
            <li>Configura un registro SPF para tu dominio para mejorar la entrega de correos.</li>
            <li>Verifica que las direcciones de correo de destino no estén filtrando los mensajes como spam.</li>
            <li>Configura alertas para la carpeta mail_queue si contiene elementos sin procesar.</li>
        </ol>
    </div>
    
    <div class="container">
        <h2>Pasos para Configurar SPF</h2>
        <p>Agrega el siguiente registro TXT en la configuración DNS de tu dominio:</p>
        
        <div class="code-box">
            v=spf1 a mx include:_spf.google.com ~all
        </div>
        
        <p>Esto permitirá que los correos enviados desde tu dominio pasen las verificaciones de autenticidad.</p>
    </div>
    
    <p><a href="index.php">Volver al inicio</a> | <a href="debug_mail_server.php">Diagnóstico Avanzado</a></p>
</body>
</html> 