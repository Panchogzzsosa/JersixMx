<?php
// Configuración de logger
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/order_emails.log';

// Evitamos redefinir la función si ya existe
if (!function_exists('writeLogEmail')) {
    function writeLogEmail($message) {
        global $logFile;
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

/**
 * Función para enviar correo de confirmación al cliente
 * 
 * @param array $orderData Datos de la orden
 * @param array $orderItems Items del pedido
 * @return bool Resultado del envío
 */
function sendOrderConfirmationEmail($orderData, $orderItems) {
    writeLogEmail("Iniciando envío de correo de confirmación para el pedido #" . $orderData['order_id']);
    
    // Verificar datos necesarios
    if (empty($orderData['customer_email'])) {
        writeLogEmail("ERROR: No hay email del cliente para el pedido #" . $orderData['order_id']);
        return false;
    }
    
    // Registrar los datos recibidos para diagnóstico
    writeLogEmail("Datos de la orden: " . print_r($orderData, true));
    writeLogEmail("Items del pedido (" . count($orderItems) . "): " . print_r(array_slice($orderItems, 0, 2), true) . (count($orderItems) > 2 ? "... [más items]" : ""));
    
    // Datos del cliente
    $customerName = $orderData['customer_name'] ?? 'Cliente';
    $customerEmail = $orderData['customer_email'];
    $orderId = $orderData['order_id'];
    $orderDate = date('d/m/Y', strtotime($orderData['created_at'] ?? 'now'));
    $paymentMethod = $orderData['payment_method'] ?? 'Transferencia/Depósito';
    $orderStatus = $orderData['status'] ?? 'Procesando';
    
    // Datos de dirección
    $address = [
        'street' => $orderData['street'] ?? '',
        'colonia' => $orderData['colonia'] ?? '',
        'city' => $orderData['city'] ?? '',
        'state' => $orderData['state'] ?? '',
        'postal' => $orderData['postal'] ?? '',
        'country' => 'México'
    ];
    
    // Calcular totales
    $subtotal = 0;
    $shipping = $orderData['shipping_cost'] ?? 0;
    $discount = $orderData['discount'] ?? 0;
    
    // Generar HTML para los items
    $itemsHtml = '';
    foreach ($orderItems as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $subtotal += $itemTotal;
        
        $itemSize = $item['size'] ?? 'N/A';
        $itemName = $item['title'] ?? 'Producto';
        $itemImage = $item['image'] ?? 'https://jersix.mx/img/ICON.png';
        
        // Personalización
        $personalization = '';
        if (!empty($item['personalization_name']) || !empty($item['personalization_number'])) {
            $personalization = '<div style="font-size: 12px; color: #666; margin-top: 5px;">';
            if (!empty($item['personalization_name'])) {
                $personalization .= '<div>Nombre: ' . htmlspecialchars($item['personalization_name']) . '</div>';
            }
            if (!empty($item['personalization_number'])) {
                $personalization .= '<div>Número: ' . htmlspecialchars($item['personalization_number']) . '</div>';
            }
            $personalization .= '</div>';
        }
        
        $itemsHtml .= '
        <tr>
            <td style="padding: 15px; border-bottom: 1px solid #eee;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 80px; vertical-align: middle;">
                            <img src="' . $itemImage . '" alt="' . $itemName . '" style="width: 60px; height: auto; border-radius: 4px; display: block;" width="60" height="60">
                        </td>
                        <td style="vertical-align: middle;">
                            <div style="font-weight: 500;">' . htmlspecialchars($itemName) . '</div>
                            <div style="font-size: 14px; color: #666; margin-top: 5px;">Talla: ' . htmlspecialchars($itemSize) . '</div>
                            ' . $personalization . '
                        </td>
                    </tr>
                </table>
            </td>
            <td style="padding: 15px; border-bottom: 1px solid #eee; text-align: center;">' . $item['quantity'] . '</td>
            <td style="padding: 15px; border-bottom: 1px solid #eee; text-align: right;">$' . number_format($item['price'], 2) . ' MXN</td>
            <td style="padding: 15px; border-bottom: 1px solid #eee; text-align: right;">$' . number_format($itemTotal, 2) . ' MXN</td>
        </tr>';
    }
    
    // Calcular total
    $total = $subtotal + $shipping - $discount;
    
    // Asunto del correo - evitar caracteres especiales en el asunto
    $subject = "Confirmacion de Pedido #" . $orderId . " - JerSix";
    
    // Plantilla HTML - reducir el uso de estilos inline y mejorar la estructura
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
        <title>Confirmación de Pedido</title>
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
            .section {
                background-color: #f9f9f9;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
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
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                padding: 12px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
                border-bottom: 2px solid #eee;
            }
            .product-img {
                width: 60px;
                height: auto;
                border-radius: 4px;
                display: block;
            }
            .product-cell {
                padding: 15px;
                border-bottom: 1px solid #eee;
            }
            .text-center {
                text-align: center;
            }
            .text-right {
                text-align: right;
            }
            .status {
                background-color: #E5F6FD;
                color: #0085CA;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 14px;
            }
            a {
                color: #0085CA;
                text-decoration: none;
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
                <h1>¡Gracias por tu compra!</h1>
                
                <p>Hola <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
                
                <p>Tu pedido ha sido recibido y está siendo procesado. A continuación encontrarás los detalles de tu compra:</p>
                
                <!-- Información del Pedido -->
                <div class="section">
                    <h2>Información del Pedido</h2>
                    
                    <table>
                        <tr>
                            <td><strong>Número de Pedido:</strong></td>
                            <td class="text-right">#' . $orderId . '</td>
                        </tr>
                        <tr>
                            <td><strong>Fecha:</strong></td>
                            <td class="text-right">' . $orderDate . '</td>
                        </tr>
                        <tr>
                            <td><strong>Forma de pago:</strong></td>
                            <td class="text-right">' . htmlspecialchars($paymentMethod) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Estado:</strong></td>
                            <td class="text-right">
                                <span class="status">' . htmlspecialchars($orderStatus) . '</span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Dirección de Envío -->
                <div class="section">
                    <h2>Dirección de Envío</h2>
                    <p style="margin: 0; line-height: 1.8;">
                        ' . htmlspecialchars($address['street']) . '<br>
                        ' . htmlspecialchars($address['colonia']) . '<br>
                        ' . htmlspecialchars($address['city']) . ', ' . htmlspecialchars($address['state']) . ' ' . htmlspecialchars($address['postal']) . '<br>
                        ' . htmlspecialchars($address['country']) . '
                    </p>
                </div>
                
                <!-- Resumen del Pedido -->
                <h2>Resumen del Pedido</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $itemsHtml . '
                    </tbody>
                </table>
                
                <!-- Totales -->
                <table style="margin-top: 20px;">
                    <tr>
                        <td class="text-right" style="width: 80%;">Subtotal:</td>
                        <td class="text-right">$' . number_format($subtotal, 2) . ' MXN</td>
                    </tr>';
                    
    if ($shipping > 0) {
        $htmlMessage .= '
                    <tr>
                        <td class="text-right">Envío:</td>
                        <td class="text-right">$' . number_format($shipping, 2) . ' MXN</td>
                    </tr>';
    }
    
    if ($discount > 0) {
        $htmlMessage .= '
                    <tr>
                        <td class="text-right">Descuento:</td>
                        <td class="text-right">-$' . number_format($discount, 2) . ' MXN</td>
                    </tr>';
    }
    
    $htmlMessage .= '
                    <tr>
                        <td class="text-right" style="font-weight: bold; border-top: 2px solid #eee;">Total:</td>
                        <td class="text-right" style="font-weight: bold; border-top: 2px solid #eee;">$' . number_format($total, 2) . ' MXN</td>
                    </tr>
                </table>
                
                <!-- Mensaje final -->
                <p>Si tienes alguna pregunta o inquietud sobre tu pedido, no dudes en contactarnos respondiendo a este correo o a través de WhatsApp al <a href="https://wa.me/+528129157795" style="color: #0085CA; text-decoration: none;">+52 8129157795</a>.</p>
                
                <p>¡Gracias por elegir JerSix!</p>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p>
                    <a href="https://www.tiktok.com/@jersix.mx" style="display: inline-block; margin: 0 10px; color: #333; text-decoration: none;" target="_blank">TikTok</a>
                    <a href="https://www.instagram.com/jersix.mx/" style="display: inline-block; margin: 0 10px; color: #333; text-decoration: none;" target="_blank">Instagram</a>
                    <a href="https://wa.me/+528129157795" style="display: inline-block; margin: 0 10px; color: #333; text-decoration: none;" target="_blank">WhatsApp</a>
                </p>
                <p>&copy; ' . date('Y') . ' JerSix. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Configuración para el envío del correo
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: JerSix <jersixmx@gmail.com>',
        'Reply-To: jersixmx@gmail.com',
        'X-Mailer: PHP/' . phpversion()
    );
    
    try {
        // Intentar enviar usando PHPMailer con Gmail SMTP
        require_once __DIR__ . '/configure_gmail_smtp.php';
        
        // Usar la función de Gmail SMTP para enviar
        $mailResult = sendGmailEmail($customerEmail, $subject, $htmlMessage);
        
        if ($mailResult) {
            writeLogEmail("Método Gmail SMTP: Correo enviado exitosamente a " . $customerEmail);
            return true;
        } else {
            writeLogEmail("ERROR: Método Gmail SMTP falló para " . $customerEmail);
            
            // Plan B: Usar método de cola de correos
            $mailFolder = __DIR__ . '/mail_queue';
            if (!file_exists($mailFolder)) {
                mkdir($mailFolder, 0777, true);
            }
            
            $mailData = [
                'to' => $customerEmail,
                'subject' => $subject,
                'body' => $htmlMessage,
                'headers' => $headers,
                'timestamp' => time(),
                'order_id' => $orderId
            ];
            
            $mailFile = $mailFolder . '/order_' . $orderId . '_' . time() . '.json';
            if (file_put_contents($mailFile, json_encode($mailData))) {
                writeLogEmail("Plan B: Correo guardado para procesamiento posterior en " . $mailFile);
                return true;
            } else {
                writeLogEmail("ERROR: No se pudo guardar el correo en cola: " . $mailFile);
                return false;
            }
        }
    } catch (Exception $e) {
        writeLogEmail("ERROR al enviar correo: " . $e->getMessage());
        return false;
    }
} 