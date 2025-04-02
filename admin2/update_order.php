<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=checkout', 'root', '');
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
    
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch(PDOException $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 