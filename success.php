<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/success_error.log');

// Importar archivo de envío de correos
require_once __DIR__ . '/order_confirmation_email.php';

// Get the preference ID from the URL
$preference_id = $_GET['preference_id'] ?? null;
$payment_id = $_GET['payment_id'] ?? null;
$status = $_GET['status'] ?? null;

// Only process if we have a successful payment
if ($status === 'approved' && $payment_id) {
    // Update order status in database

    try {
        $conn = new mysqli('216.245.211.58', 'jersixmx_usuario_total', '?O*6o6&Hs&~Q', 'jersixmx_checkout');
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Update the order status
        $stmt = $conn->prepare("UPDATE orders SET payment_status = ?, payment_id = ? WHERE preference_id = ?");
        $payment_status = 'completed';
        $stmt->bind_param("sss", $payment_status, $payment_id, $preference_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            error_log("No order found with preference_id: " . $preference_id);
        } else {
            // Obtener detalles de la orden para enviar el correo
            $orderStmt = $conn->prepare("
                SELECT o.*, c.email as customer_email
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.preference_id = ?
            ");
            $orderStmt->bind_param("s", $preference_id);
            $orderStmt->execute();
            $orderResult = $orderStmt->get_result();
            $orderData = $orderResult->fetch_assoc();
            
            if ($orderData) {
                // Obtener los productos del pedido
                $itemsStmt = $conn->prepare("
                    SELECT * FROM order_items 
                    WHERE order_id = ?
                ");
                $itemsStmt->bind_param("i", $orderData['order_id']);
                $itemsStmt->execute();
                $itemsResult = $itemsStmt->get_result();
                $orderItems = [];
                
                while ($item = $itemsResult->fetch_assoc()) {
                    $orderItems[] = $item;
                }
                
                // Enviar correo de confirmación
                if (!empty($orderData['customer_email']) && count($orderItems) > 0) {
                    sendOrderConfirmationEmail($orderData, $orderItems);
                    error_log("Correo de confirmación enviado para la orden #" . $orderData['order_id']);
                }
                
                $itemsStmt->close();
            }
            
            $orderStmt->close();
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Error updating order: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="img/ICON.png" type="image/x-icon">
    <title>Compra Exitosa | JersixMx</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            color: #333;
            line-height: 1.6;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .success-header {
            margin-bottom: 2rem;
            text-align: center;
            padding: 1rem 0;
        }
        
        .check-icon {
            display: inline-block;
            width: 50px;
            height: 50px;
            background-color: #2ecc71;
            border-radius: 50%;
            color: white;
            line-height: 50px;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        h1 {
            font-weight: 500;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .order-number {
            font-size: 1rem;
            color: #666;
            margin-bottom: 1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
            padding: 2rem 0;
        }
        
        .info-item {
            text-align: center;
        }
        
        .info-icon {
            color: #666;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
        }
        
        .info-title {
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .info-text {
            font-size: 0.9rem;
            color: #666;
        }
        
        .guarantee {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        
        .guarantee-title {
            font-weight: 500;
            display: inline;
        }
        
        .guarantee-text {
            font-size: 0.9rem;
            color: #333;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 2rem;
            padding: 0.75rem;
            color: #333;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        
        .back-link:hover {
            background-color: #f5f5f5;
        }
        
        .back-icon {
            margin-right: 0.5rem;
        }
        
        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="success-header">
        <div class="check-icon">
            <i class="fas fa-check"></i>
        </div>
        <h1>Compra Exitosa</h1>
        <div class="order-number">Pedido #<span id="order-id">Cargando...</span></div>
    </header>
    
    <div class="info-grid">
        <div class="info-item">
            <div class="info-icon">
                <i class="fas fa-box"></i>
            </div>
            <h3 class="info-title">Tiempo de Envío</h3>
            <p class="info-text">15 días hábiles promedio</p>
        </div>
        
        <div class="info-item">
            <div class="info-icon">
                <i class="fas fa-comments"></i>
            </div>
            <h3 class="info-title">Contacto Personal</h3>
            <p class="info-text">Un "Player" de Jersix te contactará por WhatsApp en 3-5 días hábiles</p>
        </div>
        
        <div class="info-item">
            <div class="info-icon">
                <i class="fas fa-truck"></i>
            </div>
            <h3 class="info-title">Seguimiento</h3>
            <p class="info-text">Recibirás tu número de rastreo en 8 - 12 días hábiles</p>
        </div>
    </div>
    
    <a href="index.php" class="back-link">
        <i class="fas fa-home back-icon"></i>Volver al inicio
    </a>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Página de compra exitosa cargada');
            
            // Obtener el ID de la orden desde la URL
            const urlParams = new URLSearchParams(window.location.search);
            const orderId = urlParams.get('order_id');
            
            // Mostrar el ID de la orden si está disponible
            if (orderId) {
                document.getElementById('order-id').textContent = orderId;
                console.log('ID de orden detectado:', orderId);
            } else {
                document.getElementById('order-id').textContent = 'Procesado';
                console.log('No se detectó ID de orden en la URL');
            }
            
            // Limpiar datos del carrito y promociones
            try {
                console.log('Limpiando datos almacenados...');
                
                // Limpiar carrito
                if (localStorage.getItem('cart')) {
                    localStorage.removeItem('cart');
                    console.log('Carrito eliminado correctamente');
                }
                
                // Limpiar información de gift cards
                if (localStorage.getItem('giftcard_applied')) {
                    localStorage.removeItem('giftcard_applied');
                    console.log('Datos de gift card eliminados');
                }
                
                // Limpiar información de códigos promocionales
                if (localStorage.getItem('promocode_applied')) {
                    localStorage.removeItem('promocode_applied');
                    console.log('Datos de código promocional eliminados');
                }
                
                console.log('Todos los datos han sido limpiados correctamente');
            } catch (error) {
                console.error('Error al limpiar datos:', error);
            }
        });
    </script>
</body>
</html>