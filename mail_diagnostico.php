<?php
// Configuración para mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para mostrar resultados
function mostrarResultado($titulo, $resultado, $esExito = true) {
    $color = $esExito ? 'green' : 'red';
    $icono = $esExito ? '✓' : '✗';
    echo "<div style='margin: 10px 0; padding: 10px; border-left: 5px solid $color; background-color: #f9f9f9;'>";
    echo "<strong style='color: $color;'>$icono $titulo:</strong> $resultado";
    echo "</div>";
}

// Función para mostrar información
function mostrarInfo($titulo, $info) {
    echo "<div style='margin: 10px 0; padding: 10px; border-left: 5px solid #007bff; background-color: #f9f9f9;'>";
    echo "<strong style='color: #007bff;'>ℹ️ $titulo:</strong> $info";
    echo "</div>";
}

// Función para mostrar comandos
function mostrarComando($comando, $resultado) {
    echo "<div style='margin: 10px 0; padding: 10px; background-color: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;'>";
    echo "<div style='font-family: monospace; background-color: #333; color: #fff; padding: 5px;'>$ $comando</div>";
    echo "<pre style='margin: 5px 0 0 0; max-height: 200px; overflow-y: auto;'>" . htmlspecialchars($resultado) . "</pre>";
    echo "</div>";
}

// Crear carpeta para logs si no existe
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    if (mkdir($logDir, 0777, true)) {
        mostrarResultado("Creación de carpeta de logs", "La carpeta $logDir ha sido creada exitosamente");
    } else {
        mostrarResultado("Creación de carpeta de logs", "No se pudo crear la carpeta $logDir", false);
    }
} else {
    // Verificar si la carpeta tiene permisos de escritura
    if (is_writable($logDir)) {
        mostrarResultado("Permisos de carpeta", "La carpeta $logDir tiene permisos de escritura");
    } else {
        mostrarResultado("Permisos de carpeta", "La carpeta $logDir NO tiene permisos de escritura. Ejecuta: chmod 777 $logDir", false);
    }
}

// Archivo de log para esta prueba
$logFile = $logDir . '/mail_diagnostico.log';

// Escribir al archivo de log
function escribirLog($mensaje) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $mensaje\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    return true;
}

echo "<h1>Diagnóstico de Correo Electrónico</h1>";
echo "<p>Esta herramienta identifica problemas con el envío de correos en tu servidor.</p>";

// Iniciar log
escribirLog("--- INICIO DE DIAGNÓSTICO DE CORREO ---");
escribirLog("Servidor: " . $_SERVER['SERVER_NAME']);
escribirLog("PHP Version: " . phpversion());

// Verificar si la función mail() está disponible
if (function_exists('mail')) {
    mostrarResultado("Función mail()", "La función mail() está disponible");
    escribirLog("La función mail() está disponible");
} else {
    mostrarResultado("Función mail()", "La función mail() NO está disponible. Contacta a tu proveedor de hosting", false);
    escribirLog("ERROR: La función mail() NO está disponible");
}

// Verificar la configuración de PHP para correo
echo "<h2>Configuración de PHP para Correo</h2>";

$mailConfig = [
    'SMTP' => ini_get('SMTP'),
    'smtp_port' => ini_get('smtp_port'),
    'sendmail_from' => ini_get('sendmail_from'),
    'sendmail_path' => ini_get('sendmail_path')
];

foreach ($mailConfig as $key => $value) {
    mostrarInfo($key, $value ?: "No configurado");
    escribirLog("Configuración PHP - $key: " . ($value ?: "No configurado"));
}

// Verificar si se puede escribir en el archivo de log
if (escribirLog("Prueba de escritura en log")) {
    mostrarResultado("Archivo de log", "Se puede escribir en el archivo de log");
} else {
    mostrarResultado("Archivo de log", "No se puede escribir en el archivo de log", false);
}

// Verificar si podemos ejecutar comandos para obtener más información
$canExecuteCommands = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));

if ($canExecuteCommands) {
    mostrarResultado("Ejecución de comandos", "La función exec() está disponible para diagnóstico adicional");
    escribirLog("La función exec() está disponible");
    
    // Verificar el programa de correo
    exec("which sendmail", $sendmailOutput, $sendmailReturnVar);
    if ($sendmailReturnVar === 0) {
        $sendmailPath = $sendmailOutput[0];
        mostrarResultado("Sendmail", "Sendmail está disponible en: $sendmailPath");
        escribirLog("Sendmail está disponible en: $sendmailPath");
        
        // Verificar la versión
        exec("$sendmailPath -d0.1 < /dev/null", $versionOutput, $versionReturnVar);
        $versionInfo = implode("\n", array_slice($versionOutput, 0, 5));
        mostrarComando("$sendmailPath -d0.1 < /dev/null", $versionInfo);
        escribirLog("Sendmail versión: " . $versionInfo);
    } else {
        mostrarResultado("Sendmail", "Sendmail no está disponible", false);
        escribirLog("ERROR: Sendmail no está disponible");
    }
    
    // Verificar los registros DNS para el dominio
    $domain = 'jersix.mx';
    // Verificar registro MX
    exec("dig +short MX $domain", $mxOutput, $mxReturnVar);
    if (!empty($mxOutput)) {
        mostrarResultado("Registros MX", "Registros MX encontrados para $domain: " . implode(", ", $mxOutput));
        escribirLog("Registros MX encontrados para $domain: " . implode(", ", $mxOutput));
    } else {
        mostrarResultado("Registros MX", "No se encontraron registros MX para $domain", false);
        escribirLog("ERROR: No se encontraron registros MX para $domain");
    }
    
    // Verificar registro SPF
    exec("dig +short TXT $domain | grep -i spf", $spfOutput, $spfReturnVar);
    if (!empty($spfOutput)) {
        mostrarResultado("Registro SPF", "Registro SPF encontrado para $domain: " . implode(", ", $spfOutput));
        escribirLog("Registro SPF encontrado para $domain: " . implode(", ", $spfOutput));
    } else {
        mostrarResultado("Registro SPF", "No se encontró registro SPF para $domain. Esto puede afectar la entrega de correos", false);
        escribirLog("ADVERTENCIA: No se encontró registro SPF para $domain");
    }
} else {
    mostrarResultado("Ejecución de comandos", "La función exec() está deshabilitada. No se pueden realizar diagnósticos adicionales", false);
    escribirLog("La función exec() está deshabilitada");
}

// Intentar enviar un correo de prueba
if (isset($_POST['test_email']) && !empty($_POST['test_email'])) {
    $testEmail = $_POST['test_email'];
    $subject = 'Prueba de diagnóstico de correo - ' . date('Y-m-d H:i:s');
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Diagnóstico de Correo</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h1 style="color: #333;">Prueba de Diagnóstico</h1>
            <p>Este es un correo de diagnóstico enviado desde <strong>' . $_SERVER['SERVER_NAME'] . '</strong>.</p>
            <p>Fecha: ' . date('Y-m-d H:i:s') . '</p>
            <p>Si recibiste este correo, significa que el envío básico funciona.</p>
            <hr>
            <p style="font-size: 12px; color: #777;">Diagnóstico automático.</p>
        </div>
    </body>
    </html>';
    
    // Headers para el correo
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: JerSix <no-reply@jersix.mx>',
        'Reply-To: jersixmx@gmail.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Registrar intento
    escribirLog("Intentando enviar correo a: $testEmail");
    escribirLog("Asunto: $subject");
    escribirLog("Headers: " . implode(", ", $headers));
    
    // Primer intento: mail() estándar
    echo "<h2>Intentando enviar correo de prueba a $testEmail</h2>";
    
    // Capturar la salida y errores
    ob_start();
    $mailResult = mail($testEmail, $subject, $message, implode("\r\n", $headers));
    $mailOutput = ob_get_clean();
    
    if ($mailResult) {
        mostrarResultado("Envío de correo", "El correo ha sido enviado correctamente usando mail()");
        escribirLog("ÉXITO: Correo enviado correctamente usando mail()");
    } else {
        mostrarResultado("Envío de correo", "Falló el envío de correo usando mail()", false);
        escribirLog("ERROR: Falló el envío de correo usando mail()");
        escribirLog("Salida de mail(): $mailOutput");
        
        // Segundo intento: con parámetros adicionales
        mostrarInfo("Segundo intento", "Intentando con parámetros adicionales...");
        $additional_parameters = '-f no-reply@jersix.mx';
        
        ob_start();
        $mailResult2 = mail($testEmail, $subject, $message, implode("\r\n", $headers), $additional_parameters);
        $mailOutput2 = ob_get_clean();
        
        if ($mailResult2) {
            mostrarResultado("Segundo intento", "El correo ha sido enviado correctamente usando parámetros adicionales");
            escribirLog("ÉXITO: Correo enviado correctamente usando parámetros adicionales");
        } else {
            mostrarResultado("Segundo intento", "Falló el segundo intento de envío", false);
            escribirLog("ERROR: Falló el segundo intento de envío");
            escribirLog("Salida de mail() con parámetros: $mailOutput2");
            
            // Tercer intento: sin headers personalizados
            mostrarInfo("Tercer intento", "Intentando sin headers personalizados...");
            
            ob_start();
            $mailResult3 = mail($testEmail, $subject, "Prueba de correo simple sin HTML\n\nFecha: " . date('Y-m-d H:i:s'));
            $mailOutput3 = ob_get_clean();
            
            if ($mailResult3) {
                mostrarResultado("Tercer intento", "El correo básico ha sido enviado correctamente");
                escribirLog("ÉXITO: Correo básico enviado correctamente");
            } else {
                mostrarResultado("Tercer intento", "Todos los intentos de envío fallaron", false);
                escribirLog("ERROR CRÍTICO: Todos los intentos de envío fallaron");
                escribirLog("Salida de mail() simple: $mailOutput3");
            }
        }
    }
}

// Más información y recomendaciones
echo "<h2>Recomendaciones para Solucionar Problemas</h2>";
echo "<ol>";
echo "<li>Verifica que tu hosting permita el envío de correos desde PHP.</li>";
echo "<li>Contacta a tu proveedor de hosting para confirmar si hay limitaciones o configuraciones especiales para enviar correos.</li>";
echo "<li>Configura registros SPF para tu dominio para mejorar la entrega de correos.</li>";
echo "<li>Considera usar un servicio de envío de correos como SendGrid, Mailgun o Amazon SES.</li>";
echo "<li>Verifica que el correo no-reply@jersix.mx esté configurado correctamente en el servidor de correo.</li>";
echo "</ol>";

// Verificar archivos clave
echo "<h2>Verificación de Archivos Clave</h2>";
$archivos = [
    __DIR__ . '/order_confirmation_email.php',
    __DIR__ . '/send_mail_hosting.php',
    __DIR__ . '/process_order.php'
];

foreach ($archivos as $archivo) {
    if (file_exists($archivo)) {
        mostrarResultado("Archivo", "El archivo " . basename($archivo) . " existe");
    } else {
        mostrarResultado("Archivo", "El archivo " . basename($archivo) . " NO existe", false);
    }
}

escribirLog("--- FIN DE DIAGNÓSTICO DE CORREO ---");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Correo - JerSix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .container {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="email"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Enviar Correo de Prueba para Diagnóstico</h2>
        <p>Completa el formulario para enviar un correo de prueba y diagnosticar problemas.</p>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="test_email">Correo electrónico para la prueba:</label>
                <input type="email" id="test_email" name="test_email" required>
            </div>
            <button type="submit">Enviar Correo de Diagnóstico</button>
        </form>
    </div>
    
    <div>
        <h2>Acciones Recomendadas</h2>
        <ol>
            <li>Ejecuta esta prueba y revisa los resultados.</li>
            <li>Si el envío falla, descarga el archivo de log (<a href="logs/mail_diagnostico.log" download>mail_diagnostico.log</a>) y envíalo a tu administrador.</li>
            <li>Verifica que tus archivos de configuración estén correctamente actualizados.</li>
            <li>Comprueba que la carpeta 'logs' tenga permisos de escritura.</li>
        </ol>
        
        <p><a href="index.php">Volver al inicio</a></p>
    </div>
</body>
</html> 