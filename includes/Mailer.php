<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mailer;
    private $config;
    private $logFile;

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->config = require __DIR__ . '/../config/mail_config.php';
        $this->logFile = __DIR__ . '/../logs/mail.log';
        
        $this->setupMailer();
    }

    private function setupMailer() {
        try {
            // Configuración del servidor
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp']['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp']['username'];
            $this->mailer->Password = $this->config['smtp']['password'];
            $this->mailer->SMTPSecure = $this->config['smtp']['encryption'];
            $this->mailer->Port = $this->config['smtp']['port'];
            $this->mailer->CharSet = $this->config['charset'];

            // Configuración de debug
            if ($this->config['debug']) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            }

            // Configuración del remitente
            $this->mailer->setFrom(
                $this->config['smtp']['from_email'],
                $this->config['smtp']['from_name']
            );

        } catch (Exception $e) {
            $this->logError("Error en la configuración del mailer: " . $e->getMessage());
            throw $e;
        }
    }

    public function sendOrderConfirmation($orderData, $orderItems) {
        try {
            // Limpiar destinatarios anteriores
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Configurar el correo
            $this->mailer->addAddress($orderData['customer_email'], $orderData['customer_name']);
            $this->mailer->Subject = '¡Gracias por tu compra! Confirmación de pedido #' . $orderData['order_id'];
            
            // Generar el contenido HTML del correo
            $body = $this->generateOrderEmailBody($orderData, $orderItems);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            
            // Enviar el correo
            $result = $this->mailer->send();
            
            if ($result) {
                $this->logSuccess("Correo de confirmación enviado para el pedido #" . $orderData['order_id']);
                return true;
            }
            
            return false;

        } catch (Exception $e) {
            $this->logError("Error al enviar correo de confirmación: " . $e->getMessage());
            return false;
        }
    }

    private function generateOrderEmailBody($orderData, $orderItems) {
        // Obtener la URL base del sitio
        $siteUrl = 'https://jersix.mx';
        
        // Generar el HTML del correo
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmación de Pedido</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    line-height: 1.6; 
                    color: #000000;
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f5;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    padding: 0;
                    background-color: #ffffff;
                }
                .header { 
                    text-align: center; 
                    padding: 40px 20px;
                    background: #ffffff;
                }
                .logo {
                    max-width: 120px;
                    margin-bottom: 0;
                }
                h1 {
                    font-size: 24px;
                    font-weight: 600;
                    margin: 40px 0 10px 0;
                }
                .content {
                    padding: 0 40px 40px;
                }
                .greeting {
                    margin-bottom: 30px;
                }
                .order-info-box {
                    background-color: #f9f9f9;
                    border-radius: 4px;
                    padding: 20px;
                    margin-bottom: 30px;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 15px;
                }
                .info-item {
                    margin-bottom: 0;
                }
                .info-label {
                    font-weight: 600;
                    margin-bottom: 4px;
                }
                .info-value {
                    color: #666;
                }
                .shipping-address {
                    margin-top: 30px;
                    padding: 20px;
                    background-color: #f9f9f9;
                    border-radius: 4px;
                }
                .order-summary {
                    margin-top: 30px;
                }
                .order-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .order-table th {
                    text-align: left;
                    padding: 10px;
                    border-bottom: 1px solid #eee;
                    color: #666;
                    font-weight: normal;
                }
                .order-table td {
                    padding: 10px;
                    border-bottom: 1px solid #eee;
                }
                .product-cell {
                    display: flex;
                    align-items: center;
                }
                .product-image {
                    width: 50px;
                    height: 50px;
                    margin-right: 15px;
                    object-fit: cover;
                }
                .product-info {
                    flex-grow: 1;
                }
                .product-name {
                    font-weight: 600;
                    margin-bottom: 4px;
                }
                .product-details {
                    color: #666;
                    font-size: 14px;
                }
                .total-row {
                    font-weight: 600;
                }
                .contact-info {
                    margin-top: 40px;
                    color: #666;
                    font-size: 14px;
                }
                .social-links {
                    margin: 20px 0;
                    text-align: center;
                }
                .social-links a {
                    color: #000;
                    text-decoration: none;
                    margin: 0 10px;
                    font-size: 14px;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    color: #666;
                    font-size: 12px;
                    border-top: 1px solid #eee;
                }
                @media only screen and (max-width: 600px) {
                    .content {
                        padding: 0 20px 20px;
                    }
                    .info-grid {
                        grid-template-columns: 1fr;
                    }
                    .product-image {
                        width: 40px;
                        height: 40px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="' . $siteUrl . '/img/ICON.png" alt="JersixMX Logo" class="logo">
                </div>
                
                <div class="content">
                    <h1>¡Gracias por tu compra!</h1>
                    <div class="greeting">
                        Hola ' . $orderData['customer_name'] . ',<br>
                        Tu pedido ha sido recibido y está siendo procesado. A continuación encontrarás los detalles de tu compra:
                    </div>

                    <div class="order-info-box">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Número de pedido:</div>
                                <div class="info-value">#' . $orderData['order_id'] . '</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Fecha:</div>
                                <div class="info-value">' . date('d/m/Y', strtotime($orderData['created_at'])) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Forma de pago:</div>
                                <div class="info-value">' . $orderData['payment_method'] . '</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Estado:</div>
                                <div class="info-value">' . ($orderData['status'] ?? 'pendiente') . '</div>
                            </div>
                        </div>
                    </div>';

        if (isset($orderData['shipping_address'])) {
            $html .= '
                    <div class="shipping-address">
                        <div class="info-label">Dirección de envío</div>
                        <div class="info-value">' . nl2br(htmlspecialchars($orderData['shipping_address'])) . '</div>
                    </div>';
        }

        $html .= '
                    <div class="order-summary">
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>';

        foreach ($orderItems as $item) {
            // Preparar la URL de la imagen
            $imageUrl = isset($item['image']) && !empty($item['image']) 
                ? $item['image'] 
                : $siteUrl . '/img/ICON.png';
            
            if (strpos($imageUrl, 'http') !== 0 && !empty($imageUrl)) {
                $imageUrl = $siteUrl . '/' . ltrim($imageUrl, '/');
            }
            
            // Inicializar la variable para detalles
            $detailsText = '';
            $hasPersonalization = false;
            
            // Añadir talla si existe y no es N/A (para productos que no son gift cards)
            if (isset($item['size']) && $item['size'] !== 'N/A' && 
                !(stripos($item['name'], 'gift') !== false || stripos($item['name'], 'tarjeta de regalo') !== false)) {
                $detailsText .= 'Talla: ' . $item['size'] . '<br>';
            }
            
            // Para gift cards, usar una imagen específica y formatear especialmente
            if (stripos($item['name'], 'gift') !== false || stripos($item['name'], 'tarjeta de regalo') !== false) {
                // Usar la imagen de LogoNav.png para gift cards
                $imageUrl = $siteUrl . '/img/LogoNav.png';
                
                // Mostrar solo el código de la gift card
                if (isset($item['personalization_number']) && !empty($item['personalization_number'])) {
                    $detailsText = 'Código: <strong>' . $item['personalization_number'] . '</strong><br>';
                }
                
                // No mostrar el correo del destinatario en el correo de confirmación
                // Reemplazar el nombre del producto
                $item['name'] = 'Tarjeta de Regalo JersixMX';
                
                // Mensaje informativo sobre el envío de la gift card
                $detailsText .= '<span style="color: #555; margin-top: 5px; display: block; font-style: italic; font-size: 13px;">
                    La tarjeta de regalo será enviada automáticamente al destinatario.
                </span>';
            } else {
                // Para productos normales, mostrar personalización si existe
                if (isset($item['personalization_name']) && !empty($item['personalization_name'])) {
                    $detailsText .= 'Nombre: ' . $item['personalization_name'] . '<br>';
                    $hasPersonalization = true;
                }
                if (isset($item['personalization_number']) && !empty($item['personalization_number'])) {
                    $detailsText .= 'Número: ' . $item['personalization_number'] . '<br>';
                    $hasPersonalization = true;
                }
                // Verificar si tiene parche (personalization_patch)
                if (isset($item['personalization_patch']) && !empty($item['personalization_patch'])) {
                    // Para Mystery Box, el campo personalization_patch contiene el tipo de caja
                    if (stripos($item['name'], 'Mystery Box') !== false) {
                        // No hacer nada especial aquí, ya que es un tipo de caja, no un parche
                    } 
                    // Para jerseys regulares, indica que tiene parche
                    else {
                        $detailsText .= '<span style="color: green;">Con Parche: ✓</span><br>';
                        $hasPersonalization = true;
                    }
                }
            }
            
            $html .= '
                                <tr>
                                    <td>
                                        <div class="product-cell">
                                            <img src="' . $imageUrl . '" alt="' . htmlspecialchars(strip_tags($item['name'])) . '" class="product-image">
                                            <div class="product-info">
                                                <div class="product-name">' . (isset($item['product_name']) ? htmlspecialchars($item['product_name']) : htmlspecialchars($item['name'])) . '</div>
                                                <div class="product-details">' . $detailsText . '</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>' . $item['quantity'] . '</td>
                                    <td>$' . number_format($item['price'], 2) . ' MXN</td>
                                    <td>$' . number_format($item['price'] * $item['quantity'], 2) . ' MXN</td>
                                </tr>';
        }

        $html .= '
                            </tbody>
                        </table>
                    </div>

                    <div class="contact-info">
                        Si tienes alguna pregunta o inquietud sobre tu pedido, no dudes en contactarnos respondiendo a este correo o a través de WhatsApp al <a href="tel:+528129157795">+52 81 2915 7795</a>.
                    </div>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $siteUrl . '" style="
                            display: inline-block;
                            background-color: #000;
                            color: #fff;
                            padding: 12px 25px;
                            text-decoration: none;
                            border-radius: 4px;
                            font-weight: 500;
                            margin: 10px 0;
                        ">Visitar nuestra tienda</a>
                    </div>

                    <div class="social-links">
                        <a href="https://www.tiktok.com/@jersix.mx">TikTok</a>
                        <a href="https://www.instagram.com/jersix.mx/">Instagram</a>
                        <a href="https://wa.me/528129157795">WhatsApp</a>
                    </div>
                </div>

                <div class="footer">
                    © ' . date('Y') . ' JersixMX. Todos los derechos reservados.
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }

    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] ERROR: $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    private function logSuccess($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] SUCCESS: $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Envía una notificación de compra al administrador
     *
     * @param array $orderData Datos de la orden
     * @param array $orderItems Items de la orden
     * @return bool Verdadero si se envió correctamente, falso en caso contrario
     */
    public function sendAdminNotification($orderData, $orderItems) {
        try {
            // Limpiar destinatarios anteriores
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Configurar el correo
            $this->mailer->addAddress('jersixmx@gmail.com', 'Jersix Admin');
            $this->mailer->Subject = '🔔 Nueva venta: Pedido #' . $orderData['order_id'];
            
            // Generar el contenido HTML del correo
            $body = $this->generateAdminNotificationBody($orderData, $orderItems);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            
            // Enviar el correo
            $result = $this->mailer->send();
            
            if ($result) {
                $this->logSuccess("Notificación enviada al administrador para el pedido #" . $orderData['order_id']);
                return true;
            }
            
            return false;

        } catch (Exception $e) {
            $this->logError("Error al enviar notificación al administrador: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Genera el contenido HTML para la notificación al administrador
     *
     * @param array $orderData Datos de la orden
     * @param array $orderItems Items de la orden
     * @return string Contenido HTML del correo
     */
    private function generateAdminNotificationBody($orderData, $orderItems) {
        $siteUrl = 'https://jersix.mx';
        $totalAmount = 0;
        $productsOverview = '';
        $hasGiftCard = false;
        
        // Calcular total y generar resumen de productos
        foreach ($orderItems as $item) {
            $totalAmount += $item['price'] * $item['quantity'];
            $productsOverview .= '- ' . $item['name'] . ' x ' . $item['quantity'] . ' ($' . number_format($item['price'], 2) . ' MXN)<br>';
            
            if (stripos($item['name'], 'gift') !== false || stripos($item['name'], 'tarjeta de regalo') !== false) {
                $hasGiftCard = true;
            }
        }
        
        // Generar el HTML del correo
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nueva Venta</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    line-height: 1.6; 
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f5;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    padding: 20px;
                    background-color: #ffffff;
                    border-radius: 8px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                }
                .header { 
                    text-align: center;
                    padding: 20px;
                    border-bottom: 1px solid #eee;
                }
                .logo {
                    max-width: 100px;
                    margin: 0 auto;
                    display: block;
                }
                h1 {
                    color: #000;
                    font-size: 22px;
                    margin: 15px 0;
                }
                .highlight {
                    background-color: #f9f9f9;
                    border-left: 4px solid #000;
                    padding: 15px;
                    margin: 20px 0;
                }
                .amount {
                    font-size: 24px;
                    font-weight: bold;
                    color: #000;
                }
                .info-block {
                    margin: 20px 0;
                }
                .info-block h2 {
                    font-size: 18px;
                    margin-bottom: 10px;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 5px;
                }
                .btn {
                    display: inline-block;
                    background-color: #000;
                    color: #fff !important;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 4px;
                    margin: 10px 0;
                    font-weight: bold;
                }
                .alert {
                    background-color: #fff8e1;
                    border-left: 4px solid #ffc107;
                    padding: 15px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="' . $siteUrl . '/img/LogoNav.png" alt="JersixMX Logo" class="logo">
                    <h1>¡Nueva venta realizada!</h1>
                </div>
                
                <div class="highlight">
                    <p>Se ha registrado una nueva venta en tu tienda:</p>
                    <p class="amount">$' . number_format($totalAmount, 2) . ' MXN</p>
                </div>
                
                <div class="info-block">
                    <h2>Datos del cliente</h2>
                    <p><strong>Nombre:</strong> ' . $orderData['customer_name'] . '</p>
                    <p><strong>Correo:</strong> ' . $orderData['customer_email'] . '</p>
                    ' . (isset($orderData['phone']) ? '<p><strong>Teléfono:</strong> ' . $orderData['phone'] . '</p>' : '') . '
                </div>
                
                <div class="info-block">
                    <h2>Detalles del pedido</h2>
                    <p><strong>Pedido #:</strong> ' . $orderData['order_id'] . '</p>
                    <p><strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime($orderData['created_at'])) . '</p>
                    <p><strong>Método de pago:</strong> ' . $orderData['payment_method'] . '</p>
                    <p><strong>Productos:</strong><br>' . $productsOverview . '</p>
                </div>';
                
        if ($hasGiftCard) {
            $html .= '
                <div class="alert">
                    <p><strong>¡Atención!</strong> Esta venta incluye tarjetas de regalo que serán enviadas automáticamente al destinatario.</p>
                </div>';
        }
                
        $html .= '
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $siteUrl . '/admin2/orders.php" class="btn" style="color: white; font-weight: bold; text-decoration: none;">
                        Ver detalle en el panel de administración
                    </a>
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }
}