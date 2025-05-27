<?php
// Incluir configuración SMTP
include_once 'smtp_configuracion.php';

// Asegurar que se envía por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limpiar y validar el email
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    $resultados = [];
    $errores = [];
    
    if (!$email) {
        $errores[] = "El correo electrónico proporcionado no es válido.";
    } else {
        // Crear el asunto y mensaje HTML
        $asunto = "Prueba de JerSix - " . date('Y-m-d H:i:s');
        
        $mensaje = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Prueba de Correo</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                }
                .container {
                    padding: 20px;
                    background-color: #f9f9f9;
                    border-radius: 5px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .logo {
                    max-width: 150px;
                }
                .content {
                    background: white;
                    padding: 20px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .footer {
                    font-size: 12px;
                    text-align: center;
                    color: #777;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="https://jersix.mx/img/LogoNav.png" alt="JerSix Logo" class="logo">
                </div>
                
                <div class="content">
                    <h1>¡Prueba de Correo Exitosa!</h1>
                    <p>Hola,</p>
                    <p>Este es un correo de prueba enviado desde <strong>JerSix</strong> utilizando la configuración de correo con dominio propio.</p>
                    <p>Si has recibido este correo, significa que la configuración de tu servidor de correo está funcionando correctamente.</p>
                    <p>Detalles técnicos:</p>
                    <ul>
                        <li>Fecha y hora: ' . date('Y-m-d H:i:s') . '</li>
                        <li>Enviado desde: no-reply@jersix.mx</li>
                        <li>Servidor: ' . $_SERVER['SERVER_NAME'] . '</li>
                    </ul>
                    <p>¡Gracias por usar nuestro sistema de prueba!</p>
                </div>
                
                <div class="footer">
                    <p>Este es un correo automático de prueba. Por favor no responda a este mensaje.</p>
                    <p>&copy; ' . date('Y') . ' JerSix. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Intentar enviar el correo
        try {
            $resultado = enviarCorreoDominio($email, $asunto, $mensaje);
            
            if ($resultado) {
                $resultados[] = "¡Correo enviado exitosamente a $email!";
                $resultados[] = "Por favor verifica tu bandeja de entrada y carpeta de spam.";
            } else {
                $errores[] = "No se pudo enviar el correo. Revisa el archivo de log en /logs/smtp_mail.log para más detalles.";
            }
        } catch (Exception $e) {
            $errores[] = "Error al enviar el correo: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Envío de Correo - JerSix</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .log-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 30px;
            border: 1px solid #ddd;
        }
        pre {
            background-color: #f1f1f1;
            padding: 15px;
            overflow-x: auto;
            border-radius: 4px;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Prueba de Envío de Correo</h1>
        
        <?php if (isset($resultados) && count($resultados) > 0): ?>
            <div class="success-message">
                <?php foreach ($resultados as $msg): ?>
                    <p><?php echo $msg; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errores) && count($errores) > 0): ?>
            <div class="error-message">
                <?php foreach ($errores as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
            <div class="form-group">
                <label for="email">Correo de destino:</label>
                <input type="email" name="email" id="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <button type="submit" class="btn">Enviar correo de prueba</button>
            <a href="configuracion_dns.php" class="btn" style="background: #6c757d;">Volver a la configuración</a>
        </form>
        
        <?php if (isset($resultado)): ?>
            <div class="log-section">
                <h2>Últimas entradas del log</h2>
                <?php
                if (file_exists($logFile)) {
                    $log = file_get_contents($logFile);
                    $lines = explode("\n", $log);
                    // Mostrar las últimas 20 líneas del log
                    $lines = array_slice($lines, -20);
                    echo '<pre>' . implode("\n", $lines) . '</pre>';
                } else {
                    echo '<p>No se encontró el archivo de log.</p>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 