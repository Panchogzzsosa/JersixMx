<?php
// Verificar si la función writeLog ya está definida para evitar duplicación
if (!function_exists('writeLog')) {
    // Configuración de logger
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/giftcard_emails.log';

    function writeLog($message) {
        global $logFile;
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

// Función para enviar correo de gift card
function sendGiftCardEmail($orderData, $giftcardItem) {
    writeLog("Iniciando envío de gift card por correo para el pedido #" . $orderData['order_id']);
    
    // Obtener datos del gift card
    $recipientEmail = $giftcardItem['personalization_name'] ?? ''; // El email se guardó en personalization_name
    $giftcardCode = $giftcardItem['personalization_number'] ?? ''; // El código se guardó en personalization_number
    $amount = $giftcardItem['price'] ?? 0;

    // Extraer información adicional (mensaje y nombres) si está disponible
    $recipientName = '';
    $senderName = $orderData['customer_name'] ?? 'Alguien';
    $message = '';

    // Procesar la información de la gift card con el nuevo formato
    if (!empty($giftcardItem['personalization_patch'])) {
        try {
            // Desglosar el string separado por "|"
            $parts = explode('|', $giftcardItem['personalization_patch']);
            
            foreach ($parts as $part) {
                if (strpos($part, 'MSG:') === 0) {
                    $message = base64_decode(substr($part, 4));
                } elseif (strpos($part, 'RCP:') === 0) {
                    $recipientName = base64_decode(substr($part, 4));
                } elseif (strpos($part, 'SND:') === 0) {
                    $senderName = base64_decode(substr($part, 4));
                    if (empty($senderName)) {
                        $senderName = $orderData['customer_name'] ?? 'Alguien';
                    }
                }
            }
            
            writeLog("Mensaje decodificado: " . $message);
            writeLog("Receptor decodificado: " . $recipientName);
            writeLog("Remitente decodificado: " . $senderName);
            
        } catch (Exception $e) {
            writeLog("Error al decodificar detalles de la gift card: " . $e->getMessage());
        }
    }

    // Si no hay email, registrar error y salir
    if (empty($recipientEmail)) {
        writeLog("ERROR: No se pudo enviar la gift card porque no hay email del destinatario");
        return false;
    }

    // Incluir archivo de configuración de la base de datos
    require_once __DIR__ . '/config/database.php';

    // Recuperar información adicional de la gift card de la base de datos
    try {
        $pdo = getConnection();
        
        // Obtener detalles de la orden
        $stmt = $pdo->prepare("SELECT order_id, customer_name FROM orders WHERE order_id = ?");
        $stmt->execute([$orderData['order_id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            writeLog("ERROR: No se encontró la orden #" . $orderData['order_id']);
            return false;
        }
    } catch(PDOException $e) {
        writeLog("ERROR: Error de conexión a la base de datos: " . $e->getMessage());
        return false;
    }

    // Asunto del correo
    $subject = "¡Has recibido una Tarjeta de Regalo de JerSix!";
    
    // Contenido HTML del correo
    $htmlMessage = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tarjeta de Regalo JersixMx</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                text-align: center;
                padding: 20px 0;
            }
            .giftcard {
                background: linear-gradient(135deg, #e6e6e6 0%, #b3b3b3 100%);
                border-radius: 15px;
                padding: 30px;
                color: #333;
                margin: 20px 0;
                position: relative;
                overflow: hidden;
            }
            .giftcard::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url("https://jersix.mx/img/LogoNav.png") no-repeat center;
                background-size: 150px;
                opacity: 0.2;
            }
            .amount {
                font-size: 32px;
                font-weight: bold;
                margin-bottom: 20px;
                position: relative;
            }
            .message {
                font-style: italic;
                background: rgba(255,255,255,0.1);
                padding: 15px;
                border-radius: 8px;
                margin: 15px 0;
                position: relative;
            }
            .details {
                position: relative;
                margin-bottom: 10px;
            }
            .code {
                background: #f1f1f1;
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
                font-size: 18px;
                text-align: center;
                font-weight: bold;
                color: #333;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                font-size: 12px;
                color: #777;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://jersix.mx/img/LogoNav.png" alt="JerSix Logo" style="max-width: 150px;">
                <h1>¡Has recibido una Tarjeta de Regalo!</h1>
            </div>
            
            <p>Hola' . (!empty($recipientName) ? ' <strong>' . htmlspecialchars($recipientName) . '</strong>' : '') . ',</p>
            
            <p><strong>' . htmlspecialchars($senderName) . '</strong> te ha enviado una Tarjeta de Regalo de JerSix. ¡Ahora puedes adquirir tus jerseys favoritos!</p>
            
            <div class="giftcard">
                <div class="amount">$' . number_format($amount, 2) . ' MXN</div>';
                
                if (!empty($message)) {
                    $htmlMessage .= '<div class="message">"' . htmlspecialchars($message) . '"</div>';
                }
                
                $htmlMessage .= '<div class="details">
                    <p>Código de regalo:</p>
                    <div class="code">' . $giftcardCode . '</div>
                </div>
            </div>
            
            <h3>¿Cómo usar tu tarjeta de regalo?</h3>
            <ol>
                <li>Visita nuestra tienda en <a href="https://jersix.mx">jersix.mx</a></li>
                <li>Selecciona los productos que deseas comprar</li>
                <li>Al momento de pagar, ingresa el código de tu tarjeta de regalo</li>
                <li>¡Disfruta de tus nuevos jerseys!</li>
            </ol>
            
            <p>Esta tarjeta de regalo no tiene fecha de caducidad y puedes utilizarla en cualquier producto de nuestra tienda.</p>
            
            <div class="footer">
                <p>Si tienes alguna pregunta, contáctanos en <a href="mailto:contacto@jersix.mx">contacto@jersix.mx</a></p>
                <p>&copy; ' . date('Y') . ' JerSix. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    try {
        // Intentar enviar el correo de forma directa sin usar Gmail SMTP
        require_once __DIR__ . '/vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Configuración del servidor (usando la configuración del servidor, no Gmail)
        $mail->isSMTP();
        $mail->Host = 'mail.jersix.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@jersix.mx';
        $mail->Password = 'Jersix.mx141423';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        
        // Configuración del remitente y destinatario
        $mail->setFrom('no-reply@jersix.mx', 'JerSix');
        $mail->addAddress($recipientEmail);
        
        // Configuración del mensaje
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlMessage;
        
        // Enviar el correo
        if ($mail->send()) {
            writeLog("Correo de gift card enviado exitosamente a: " . $recipientEmail);
            return true;
        } else {
            writeLog("ERROR: No se pudo enviar el correo: " . $mail->ErrorInfo);
            
            // Plan B: Intentar con Gmail SMTP como respaldo
            writeLog("Intentando envío alternativo con Gmail SMTP...");
            
            // Verificar que exista el archivo para configurar Gmail SMTP
            if (file_exists(__DIR__ . '/configure_gmail_smtp.php')) {
                require_once __DIR__ . '/configure_gmail_smtp.php';
                $gmailSent = sendGmailEmail($recipientEmail, $subject, $htmlMessage);
                
                if ($gmailSent) {
                    writeLog("Correo de gift card enviado exitosamente usando Gmail SMTP a: " . $recipientEmail);
                    return true;
                } else {
                    writeLog("ERROR: Falló el envío alternativo con Gmail SMTP");
                    return false;
                }
            } else {
                writeLog("ERROR: No se encontró el archivo configure_gmail_smtp.php para el envío alternativo");
                return false;
            }
        }
    } catch (Exception $e) {
        writeLog("ERROR al enviar gift card: " . $e->getMessage());
        
        // Plan B: Intentar con Gmail SMTP como último recurso
        try {
            writeLog("Intentando envío alternativo con Gmail SMTP después de excepción...");
            
            // Verificar que exista el archivo para configurar Gmail SMTP
            if (file_exists(__DIR__ . '/configure_gmail_smtp.php')) {
                require_once __DIR__ . '/configure_gmail_smtp.php';
                $gmailSent = sendGmailEmail($recipientEmail, $subject, $htmlMessage);
                
                if ($gmailSent) {
                    writeLog("Correo de gift card enviado exitosamente usando Gmail SMTP a: " . $recipientEmail);
                    return true;
                } else {
                    writeLog("ERROR: Falló el envío alternativo con Gmail SMTP");
                    return false;
                }
            } else {
                writeLog("ERROR: No se encontró el archivo configure_gmail_smtp.php para el envío alternativo");
                return false;
            }
        } catch (Exception $innerException) {
            writeLog("ERROR CRÍTICO: Todos los intentos de envío fallaron: " . $innerException->getMessage());
            return false;
        }
    }
} 