<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if (!isset($_POST['order_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de orden no proporcionado']);
    exit();
}



// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    $order_id = (int)$_POST['order_id'];
    
    // Verificar si la orden estaba completada y obtener el total
    $order_stmt = $pdo->prepare("
        SELECT status, payment_notes,
        (SELECT SUM(quantity * price) FROM order_items WHERE order_id = orders.order_id) as total_amount
        FROM orders 
        WHERE order_id = ?
    ");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si la orden estaba completada, actualizar las estadísticas
    if ($order && $order['status'] === 'completed') {
        // Obtener estadísticas actuales
        $stats_stmt = $pdo->query("SELECT total_sales, total_orders FROM sales_stats LIMIT 1");
        $current_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar si hay descuentos aplicados por gift card
        $total_amount = $order['total_amount'];
        
        if (!empty($order['payment_notes'])) {
            // Buscar información de descuento con gift card
            if (preg_match('/Gift Card aplicada:.+\- Monto: \$([0-9.]+)/', $order['payment_notes'], $matches)) {
                $discount_amount = floatval($matches[1]);
                // Restar el descuento del total
                $total_amount = max(0, $total_amount - $discount_amount);
                
                // Registrar el ajuste para depuración
                error_log("Ajustando total de orden #{$order_id} por descuento de Gift Card al eliminar: {$discount_amount}. Total ajustado: {$total_amount}");
            }
        }
        
        // Calcular nuevos valores asegurando que no sean negativos
        $new_total_sales = max(0, $current_stats['total_sales'] - $total_amount);
        $new_total_orders = max(0, $current_stats['total_orders'] - 1);
        
        $pdo->exec("
            UPDATE sales_stats 
            SET total_sales = {$new_total_sales},
                total_orders = {$new_total_orders}
        ");
    }
    
    // Eliminar los items de la orden
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    
    // Eliminar la orden
    $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
    
    $pdo->commit();
    
    // Obtener las estadísticas actualizadas
    $stats_stmt = $pdo->query("SELECT total_sales, total_orders FROM sales_stats LIMIT 1");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch(PDOException $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 