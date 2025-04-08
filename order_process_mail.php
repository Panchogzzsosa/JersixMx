<?php
/**
 * Gestión de correos para el proceso de órdenes
 * Utiliza configuración optimizada para hosting compartido
 */

// Incluir la configuración de correo
require_once __DIR__ . '/configure_hosting_mail.php';

/**
 * Envía el correo de confirmación de orden al cliente
 * 
 * @param int $orderId ID de la orden
 * @param string $customerName Nombre del cliente
 * @param string $customerEmail Correo del cliente
 * @param string $orderDate Fecha de la orden
 * @param string $paymentMethod Método de pago
 * @param float $totalAmount Monto total
 * @param array $products Productos de la orden
 * @return bool Éxito del envío
 */
function sendOrderConfirmationEmail($orderId, $customerName, $customerEmail, $orderDate, $paymentMethod, $totalAmount, $products) {
    // Validar correo del cliente
    if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        logHostingMailActivity("No se pudo enviar confirmación: correo de cliente inválido o vacío");
        return false;
    }
    
    // Construir la tabla de productos
    $productsTable = '';
    $subtotal = 0;
    
    if (!empty($products) && is_array($products)) {
        $productsTable .= '
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f8f9fa;">
                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Producto</th>
                    <th style="padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">Cantidad</th>
                    <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Precio</th>
                    <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Subtotal</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($products as $product) {
            $productName = $product['name'] ?? 'Producto';
            $quantity = $product['quantity'] ?? 1;
            $price = $product['price'] ?? 0;
            $itemSubtotal = $quantity * $price;
            $subtotal += $itemSubtotal;
            
            $productsTable .= '
                <tr>
                    <td style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">' . htmlspecialchars($productName) . '</td>
                    <td style="padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">' . $quantity . '</td>
                    <td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">$' . number_format($price, 2) . '</td>
                    <td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">$' . number_format($itemSubtotal, 2) . '</td>
                </tr>';
        }
        
        $productsTable .= '
                <tr>
                    <td colspan="3" style="padding: 10px; text-align: right; font-weight: bold;">Total:</td>
                    <td style="padding: 10px; text-align: right; font-weight: bold;">$' . number_format($totalAmount, 2) . '</td>
                </tr>
            </tbody>
        </table>';
    } else {
        $productsTable = '<p>No hay productos detallados en esta orden.</p>';
    }
    
    // Asunto del correo
    $subject = "Confirmación de Orden #$orderId - Jersix.mx";
    
    // Cuerpo del correo
    $htmlBody = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            .logo { margin-bottom: 20px; }
            .order-info { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
            .products { margin-bottom: 20px; }
            .footer { text-align: center; font-size: 12px; color: #777; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; }
            .button { display: inline-block; background-color: #000; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 4px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">
                    <img src="https://jersix.mx/assets/logo/logo.png" alt="Jersix" style="max-width: 150px;">
                </div>
                <h1>¡Gracias por tu compra!</h1>
                <p>Hemos recibido tu orden correctamente.</p>
            </div>
            
            <div class="order-info">
                <h2>Detalles de la Orden</h2>
                <p><strong>Número de orden:</strong> #' . $orderId . '</p>
                <p><strong>Fecha:</strong> ' . $orderDate . '</p>
                <p><strong>Cliente:</strong> ' . htmlspecialchars($customerName) . '</p>
                <p><strong>Correo:</strong> ' . htmlspecialchars($customerEmail) . '</p>
                <p><strong>Método de pago:</strong> ' . htmlspecialchars($paymentMethod) . '</p>
            </div>
            
            <div class="products">
                <h2>Productos</h2>
                ' . $productsTable . '
            </div>
            
            <p>Te informaremos cuando tu pedido esté listo para entrega o envío.</p>
            
            <div style="text-align: center;">
                <a href="https://jersix.mx/mi-cuenta" class="button">Ver mis pedidos</a>
            </div>
            
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Jersix.mx - Todos los derechos reservados</p>
                <p>Este correo es automático, por favor no responder.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Enviar el correo
    return sendHostingEmail($customerEmail, $subject, $htmlBody);
}

/**
 * Envía notificación de nueva orden al administrador
 * 
 * @param int $orderId ID de la orden
 * @param string $customerName Nombre del cliente
 * @param string $customerEmail Correo del cliente
 * @param string $orderDate Fecha de la orden
 * @param string $paymentMethod Método de pago
 * @param float $totalAmount Monto total
 * @return bool Éxito del envío
 */
function sendNewOrderNotificationEmail($orderId, $customerName, $customerEmail, $orderDate, $paymentMethod, $totalAmount) {
    global $adminEmail;
    
    // Validar correo del administrador
    if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        logHostingMailActivity("No se pudo enviar notificación: correo de administrador inválido o vacío");
        return false;
    }
    
    // Asunto del correo
    $subject = "Nueva Orden #$orderId - Jersix.mx";
    
    // Cuerpo del correo
    $htmlBody = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            .alert { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
            .order-info { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
            .footer { text-align: center; font-size: 12px; color: #777; margin-top: 30px; }
            .button { display: inline-block; background-color: #000; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 4px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>¡Nueva Orden Recibida!</h1>
            </div>
            
            <div class="alert">
                <p>Se ha recibido una nueva orden en el sistema.</p>
            </div>
            
            <div class="order-info">
                <h2>Detalles de la Orden</h2>
                <p><strong>Número de orden:</strong> #' . $orderId . '</p>
                <p><strong>Fecha:</strong> ' . $orderDate . '</p>
                <p><strong>Cliente:</strong> ' . htmlspecialchars($customerName) . '</p>
                <p><strong>Correo:</strong> ' . htmlspecialchars($customerEmail) . '</p>
                <p><strong>Método de pago:</strong> ' . htmlspecialchars($paymentMethod) . '</p>
                <p><strong>Monto total:</strong> $' . number_format($totalAmount, 2) . '</p>
            </div>
            
            <div style="text-align: center;">
                <a href="https://jersix.mx/admin2/orders.php" class="button">Ver detalles en el admin</a>
            </div>
            
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Jersix.mx - Sistema de Notificaciones</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Enviar el correo
    return sendHostingEmail($adminEmail, $subject, $htmlBody);
}
?> 