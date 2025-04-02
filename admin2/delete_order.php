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

try {
    $pdo = new PDO('mysql:host=localhost;dbname=checkout', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    $order_id = (int)$_POST['order_id'];
    
    // Verificar si la orden estaba completada y obtener el total
    $order_stmt = $pdo->prepare("
        SELECT status,
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
        
        // Calcular nuevos valores asegurando que no sean negativos
        $new_total_sales = max(0, $current_stats['total_sales'] - $order['total_amount']);
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