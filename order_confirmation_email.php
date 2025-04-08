<?php
// Incluir la configuración de correo optimizado para hosting
require_once __DIR__ . '/configure_hosting_mail.php';

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
                            <td class="text-right"><span class="status">' . htmlspecialchars($orderStatus) . '</span></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Dirección de Envío -->
                <div class="section">
                    <h2>Dirección de Envío</h2>
                    
                    <p>
                        ' . htmlspecialchars($address['street']) . '<br>
                        ' . htmlspecialchars($address['colonia']) . '<br>
                        ' . htmlspecialchars($address['city']) . ', ' . htmlspecialchars($address['state']) . ' C.P. ' . htmlspecialchars($address['postal']) . '<br>
                        ' . htmlspecialchars($address['country']) . '
                    </p>
                </div>
                
                <!-- Productos -->
                <div class="section">
                    <h2>Productos</h2>
                    
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
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right"><strong>Subtotal:</strong></td>
                                <td class="text-right">$' . number_format($subtotal, 2) . ' MXN</td>
                            </tr>';
    
    if ($shipping > 0) {
        $htmlMessage .= '
                            <tr>
                                <td colspan="3" class="text-right"><strong>Envío:</strong></td>
                                <td class="text-right">$' . number_format($shipping, 2) . ' MXN</td>
                            </tr>';
    }
    
    if ($discount > 0) {
        $htmlMessage .= '
                            <tr>
                                <td colspan="3" class="text-right"><strong>Descuento:</strong></td>
                                <td class="text-right">- $' . number_format($discount, 2) . ' MXN</td>
                            </tr>';
    }
    
    $htmlMessage .= '
                            <tr>
                                <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                <td class="text-right"><strong>$' . number_format($total, 2) . ' MXN</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <p>Recibirás notificaciones sobre el estado de tu pedido a través de correo electrónico. Si tienes alguna pregunta, no dudes en contactarnos.</p>
                
                <p style="text-align: center; margin-top: 30px;">
                    <a href="https://jersix.mx" style="display: inline-block; background-color: #000; color: #fff; padding: 12px 25px; border-radius: 4px; text-decoration: none; text-transform: uppercase; font-weight: bold; font-size: 14px;">
                        Visitar Tienda
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
    
    // Usar la nueva función de envío optimizada para hosting
    writeLogEmail("Enviando correo usando la función optimizada sendHostingEmail()");
    $result = sendHostingEmail($customerEmail, $subject, $htmlMessage);
    
    // Registrar resultado
    if ($result) {
        writeLogEmail("Correo enviado exitosamente a " . $customerEmail);
    } else {
        writeLogEmail("ERROR: No se pudo enviar el correo a " . $customerEmail);
    }
    
    return $result;
} 