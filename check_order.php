<?php
// Configuración para mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Verificación de Orden</h1>";

// Cargar configuración de base de datos
require_once __DIR__ . '/config/database.php';

// Obtener ID de la orden (opcional)
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;

try {
    // Conectar a la base de datos
    $pdo = getConnection();
    echo "<p style='color: green;'>✓ Conexión a la base de datos exitosa.</p>";
    
    // Si no se proporcionó un ID específico, mostrar las últimas órdenes
    if (!$order_id) {
        $stmt = $pdo->query("SELECT * FROM orders ORDER BY order_id DESC LIMIT 5");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Últimas 5 órdenes:</h2>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Cliente</th><th>Email</th><th>Fecha</th><th>Estado</th><th>Método de Pago</th><th>ID de Pago</th><th>Ver detalles</th></tr>";
        
        foreach ($orders as $order) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($order['order_id']) . "</td>";
            echo "<td>" . htmlspecialchars($order['customer_name']) . "</td>";
            echo "<td>" . htmlspecialchars($order['customer_email']) . "</td>";
            echo "<td>" . htmlspecialchars($order['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($order['status']) . "</td>";
            echo "<td>" . htmlspecialchars($order['payment_method']) . "</td>";
            echo "<td>" . htmlspecialchars($order['payment_id']) . "</td>";
            echo "<td><a href='check_order.php?order_id=" . $order['order_id'] . "'>Ver detalles</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        // Mostrar detalles de una orden específica
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo "<p style='color: red;'>La orden #$order_id no existe en la base de datos.</p>";
        } else {
            echo "<h2>Detalles de la orden #" . htmlspecialchars($order_id) . "</h2>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Campo</th><th>Valor</th></tr>";
            
            foreach ($order as $field => $value) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($field) . "</strong></td>";
                echo "<td>" . htmlspecialchars($value) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Mostrar items de la orden
            $stmt = $pdo->prepare("
                SELECT oi.*, p.name as product_name 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Items de la orden:</h3>";
            if (empty($items)) {
                echo "<p>No se encontraron items para esta orden.</p>";
            } else {
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th><th>Talla</th><th>Personalización</th></tr>";
                
                foreach ($items as $item) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                    echo "<td>$" . htmlspecialchars($item['price']) . "</td>";
                    echo "<td>$" . htmlspecialchars($item['subtotal']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['size']) . "</td>";
                    
                    $personalization = '';
                    if (!empty($item['personalization_name'])) {
                        $personalization .= "Nombre: " . $item['personalization_name'] . "<br>";
                    }
                    if (!empty($item['personalization_number'])) {
                        $personalization .= "Número: " . $item['personalization_number'] . "<br>";
                    }
                    echo "<td>" . $personalization . "</td>";
                    
                    echo "</tr>";
                }
                echo "</table>";
                
                // Calcular total
                $total = array_reduce($items, function($sum, $item) {
                    return $sum + $item['subtotal'];
                }, 0);
                
                echo "<p><strong>Total de la orden: $" . number_format($total, 2) . " MXN</strong></p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Botones de navegación
echo "<div style='margin-top: 20px;'>";
echo "<a href='check_order.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Ver todas las órdenes</a>";
echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background-color: #555; color: white; text-decoration: none; border-radius: 5px;'>Volver al inicio</a>";
echo "</div>";
?> 