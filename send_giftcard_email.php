<?php
// Incluir la configuración optimizada para hosting
require_once __DIR__ . '/configure_hosting_mail.php';

// Configuración de logger
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/giftcard_emails.log';

// Evitamos redefinir la función si ya existe
if (!function_exists('writeLogGiftcardEmail')) {
    function writeLogGiftcardEmail($message) {
        global $logFile;
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

/**
 * Función para enviar correo con la Tarjeta de Regalo
 * 
 * @param array $giftcardData Datos de la tarjeta de regalo
 * @return bool Resultado del envío
 */
function sendGiftCardEmail($giftcardData) {
    // Registrar inicio del proceso
    writeLogGiftcardEmail("Iniciando envío de correo para tarjeta de regalo: " . print_r($giftcardData, true));
    
    // Verificar datos necesarios
    if (empty($giftcardData['recipient_email'])) {
        writeLogGiftcardEmail("ERROR: No hay email del destinatario para la tarjeta de regalo");
        return false;
    }
    
    // Datos de la tarjeta de regalo
    $recipientName = $giftcardData['recipient_name'] ?? 'Estimado/a';
    $recipientEmail = $giftcardData['recipient_email'];
    $senderName = $giftcardData['sender_name'] ?? 'Alguien especial';
    $message = $giftcardData['message'] ?? 'Disfruta de esta tarjeta de regalo.';
    $amount = number_format($giftcardData['amount'] ?? 0, 2);
    $code = $giftcardData['code'] ?? 'N/A';
    $expirationDate = date('d/m/Y', strtotime('+1 year'));
    
    if (isset($giftcardData['expiration_date']) && !empty($giftcardData['expiration_date'])) {
        $expirationDate = date('d/m/Y', strtotime($giftcardData['expiration_date']));
    }
    
    // Asunto del correo
    $subject = "¡Has Recibido una Tarjeta de Regalo de $senderName!";
    
    // Plantilla HTML
    $htmlMessage = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
        <meta name="x-apple-disable-message-reformatting" />
        <meta name="format-detection" content="telephone=no" />
        <meta name="color-scheme" content="light" />
        <meta name="supported-color-schemes" content="light" />
        <title>Tarjeta de Regalo</title>
        <style type="text/css">
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f7f7f7;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin-top: 20px;
                margin-bottom: 20px;
            }
            .header {
                background-color: #f0f0f0;
                padding: 20px;
                text-align: center;
            }
            .content {
                padding: 30px;
            }
            .giftcard {
                background: linear-gradient(45deg, #000000, #333333);
                color: white;
                border-radius: 10px;
                padding: 30px;
                text-align: center;
                margin: 20px 0;
                position: relative;
                overflow: hidden;
            }
            .giftcard:before {
                content: "";
                position: absolute;
                top: -10px;
                left: -10px;
                width: 200%;
                height: 200%;
                background: repeating-linear-gradient(
                    45deg,
                    rgba(255,255,255,0.05),
                    rgba(255,255,255,0.05) 10px,
                    rgba(255,255,255,0.08) 10px,
                    rgba(255,255,255,0.08) 20px
                );
            }
            .giftcard-amount {
                font-size: 32px;
                font-weight: bold;
                margin: 15px 0;
            }
            .giftcard-code {
                background-color: rgba(255,255,255,0.2);
                padding: 10px;
                border-radius: 4px;
                letter-spacing: 3px;
                font-family: monospace;
                margin: 15px 0;
                font-size: 18px;
            }
            .message {
                background-color: #f9f9f9;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                font-style: italic;
            }
            .footer {
                background-color: #f2f2f2;
                padding: 20px;
                text-align: center;
                font-size: 14px;
                color: #777;
            }
            h1 {
                color: #000000;
                margin-bottom: 20px;
                font-size: 24px;
            }
            h2 {
                font-size: 18px;
                margin-bottom: 15px;
                color: #000;
            }
            a {
                color: #0085CA;
                text-decoration: none;
            }
            .button {
                display: inline-block;
                background-color: #000;
                color: #fff;
                padding: 12px 25px;
                border-radius: 4px;
                text-decoration: none;
                text-transform: uppercase;
                font-weight: bold;
                font-size: 14px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <img src="https://jersix.mx/img/LogoNav.png" alt="JerSix Logo" width="100" height="100" style="max-width: 150px; display: block; margin: 0 auto;">
            </div>
            
            <!-- Contenido -->
            <div class="content">
                <h1>¡Has Recibido una Tarjeta de Regalo!</h1>
                
                <p>Hola <strong>' . htmlspecialchars($recipientName) . '</strong>,</p>
                
                <p><strong>' . htmlspecialchars($senderName) . '</strong> te ha enviado una Tarjeta de Regalo para que disfrutes comprando en JerSix.</p>
                
                <!-- Tarjeta de Regalo -->
                <div class="giftcard">
                    <div>TARJETA DE REGALO</div>
                    <div class="giftcard-amount">$' . $amount . ' MXN</div>
                    <div>Código:</div>
                    <div class="giftcard-code">' . $code . '</div>
                    <div>Válida hasta: ' . $expirationDate . '</div>
                </div>
                
                <!-- Mensaje -->
                <div class="message">
                    <p><em>"' . htmlspecialchars($message) . '"</em></p>
                    <p style="text-align: right; margin: 0;">- ' . htmlspecialchars($senderName) . '</p>
                </div>
                
                <h2>¿Cómo utilizar tu Tarjeta de Regalo?</h2>
                
                <ol>
                    <li>Visita <a href="https://jersix.mx">jersix.mx</a> y agrega productos a tu carrito</li>
                    <li>Al momento de pagar, ingresa el código en la sección "Cupón o Tarjeta de Regalo"</li>
                    <li>¡Disfruta de tus productos JerSix!</li>
                </ol>
                
                <p style="text-align: center; margin-top: 30px;">
                    <a href="https://jersix.mx" class="button">
                        Ir a la Tienda
                    </a>
                </p>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p>&copy; ' . date('Y') . ' JerSix. Todos los derechos reservados.</p>
                <p>
                    <a href="https://jersix.mx">jersix.mx</a> |
                    <a href="mailto:info@jersix.mx">info@jersix.mx</a>
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    // Usar la función de envío optimizada para hosting
    writeLogGiftcardEmail("Enviando correo usando la función optimizada sendHostingEmail()");
    $result = sendHostingEmail($recipientEmail, $subject, $htmlMessage);
    
    // Registrar resultado
    if ($result) {
        writeLogGiftcardEmail("Correo de gift card enviado exitosamente a " . $recipientEmail);
    } else {
        writeLogGiftcardEmail("ERROR: No se pudo enviar el correo de gift card a " . $recipientEmail);
    }
    
    return $result;
} 