<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}



// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    // Actualizar la información de la orden
    $stmt = $pdo->prepare("
        UPDATE orders SET
            customer_name = ?,
            customer_email = ?,
            phone = ?,
            street = ?,
            colony = ?,
            city = ?,
            state = ?,
            zip_code = ?
        WHERE order_id = ?
    ");
    
    $stmt->execute([
        $_POST['customer_name'],
        $_POST['customer_email'],
        $_POST['phone'] ?: '',
        $_POST['street'] ?: '',
        $_POST['colony'] ?: '',
        $_POST['city'] ?: '',
        $_POST['state'] ?: '',
        $_POST['zip_code'] ?: '',
        $_POST['order_id']
    ]);
    
    // Eliminar items existentes
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->execute([$_POST['order_id']]);
    
    // Insertar nuevos items
    $stmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            quantity,
            price,
            size
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    // Antes de la inserción de items, agregar log para depuración
    error_log('POST data: ' . print_r($_POST, true));
    
    // Modificar el bucle de inserción de items
    foreach ($_POST['products'] as $index => $product_id) {
        if (empty($product_id)) continue;
        
        $size = isset($_POST['sizes'][$index]) ? $_POST['sizes'][$index] : '';
        $quantity = isset($_POST['quantities'][$index]) ? $_POST['quantities'][$index] : 1;
        $price = isset($_POST['prices'][$index]) ? $_POST['prices'][$index] : 0;
        
        error_log("Insertando item: product_id=$product_id, size=$size, quantity=$quantity, price=$price");
        
        $stmt->execute([
            $_POST['order_id'],
            $product_id,
            $quantity,
            $price,
            $size
        ]);
    }
    
    // Recalcular el total de la orden para actualizar las estadísticas si es necesario
    $total_stmt = $pdo->prepare("
        SELECT SUM(quantity * price) as order_total 
        FROM order_items 
        WHERE order_id = ?
    ");
    $total_stmt->execute([$_POST['order_id']]);
    $order_total = $total_stmt->fetch(PDO::FETCH_ASSOC)['order_total'];
    
    // Verificar si hay descuentos aplicados por gift card
    $payment_notes_stmt = $pdo->prepare("SELECT payment_notes FROM orders WHERE order_id = ?");
    $payment_notes_stmt->execute([$_POST['order_id']]);
    $payment_notes = $payment_notes_stmt->fetchColumn();
    
    // Si hay notas de pago, buscar información de descuento
    if (!empty($payment_notes)) {
        // Buscar información de descuento con gift card
        if (preg_match('/Gift Card aplicada:.+\- Monto: \$([0-9.]+)/', $payment_notes, $matches)) {
            $discount_amount = floatval($matches[1]);
            // Restar el descuento del total
            $order_total = max(0, $order_total - $discount_amount);
            
            // Registrar el ajuste para depuración
            error_log("Ajustando total de orden #{$_POST['order_id']} por descuento de Gift Card: {$discount_amount}. Total ajustado: {$order_total}");
        }
    }
    
    // Actualizar estadísticas si la orden estaba completada
    $status_stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ?");
    $status_stmt->execute([$_POST['order_id']]);
    $order_status = $status_stmt->fetchColumn();
    
    if ($order_status === 'completed') {
        // Actualizar las estadísticas con el nuevo total
        $stats_stmt = $pdo->query("SELECT total_sales FROM sales_stats LIMIT 1");
        $current_sales = $stats_stmt->fetchColumn();
        
        // Obtener el total anterior de esta orden (si existe registro)
        $old_total_stmt = $pdo->prepare("
            SELECT order_total FROM order_history 
            WHERE order_id = ? ORDER BY updated_at DESC LIMIT 1
        ");
        $old_total_stmt->execute([$_POST['order_id']]);
        $old_total = $old_total_stmt->fetchColumn();
        
        if ($old_total) {
            // Ajustar las ventas totales: restar el viejo total y sumar el nuevo
            $new_total_sales = $current_sales - $old_total + $order_total;
        } else {
            // Si no hay registro previo, simplemente sumar el nuevo total
            $new_total_sales = $current_sales + $order_total;
        }
        
        $pdo->exec("UPDATE sales_stats SET total_sales = {$new_total_sales}");
        
        // Registrar este cambio en el historial
        $history_stmt = $pdo->prepare("
            INSERT INTO order_history (order_id, order_total, updated_at)
            VALUES (?, ?, NOW())
        ");
        $history_stmt->execute([$_POST['order_id'], $order_total]);
    }
    
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch(PDOException $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 