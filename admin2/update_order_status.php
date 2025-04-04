<?php
session_start();

// Verificar inicio de sesión
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit(json_encode(['success' => false, 'message' => 'Acceso denegado']));
}

// Verificar datos necesarios
if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    exit(json_encode(['success' => false, 'message' => 'Datos incompletos']));
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=checkout', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $previous_status = $_POST['previous_status'] ?? '';
    
    // Actualizar el estado del pedido
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    // Obtener estadísticas actuales
    $stats_stmt = $pdo->query("SELECT total_sales, total_orders FROM sales_stats LIMIT 1");
    $current_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si el nuevo estado es 'completed' y el estado anterior no lo era
    if ($new_status === 'completed' && $previous_status !== 'completed') {
        // Calcular el total del pedido
        $total_stmt = $pdo->prepare("
            SELECT SUM(quantity * price) as order_total 
            FROM order_items 
            WHERE order_id = ?
        ");
        $total_stmt->execute([$order_id]);
        $order_total = $total_stmt->fetch(PDO::FETCH_ASSOC)['order_total'];
        
        // Actualizar las estadísticas asegurando valores no negativos
        $new_total_sales = $current_stats['total_sales'] + $order_total;
        $new_total_orders = $current_stats['total_orders'] + 1;
        
        $pdo->exec("
            UPDATE sales_stats 
            SET total_sales = {$new_total_sales},
                total_orders = {$new_total_orders}
        ");
    }
    // Si el estado anterior era 'completed' y el nuevo no lo es
    else if ($previous_status === 'completed' && $new_status !== 'completed') {
        // Calcular el total del pedido
        $total_stmt = $pdo->prepare("
            SELECT SUM(quantity * price) as order_total 
            FROM order_items 
            WHERE order_id = ?
        ");
        $total_stmt->execute([$order_id]);
        $order_total = $total_stmt->fetch(PDO::FETCH_ASSOC)['order_total'];
        
        // Actualizar las estadísticas asegurando valores no negativos
        $new_total_sales = max(0, $current_stats['total_sales'] - $order_total);
        $new_total_orders = max(0, $current_stats['total_orders'] - 1);
        
        $pdo->exec("
            UPDATE sales_stats 
            SET total_sales = {$new_total_sales},
                total_orders = {$new_total_orders}
        ");
    }
    
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