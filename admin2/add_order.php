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
    // Usar la función getConnection definida en database.php
    $pdo = getConnection();
    
    $pdo->beginTransaction();
    
    // Insertar la orden con campos opcionales como cadenas vacías en lugar de null
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            customer_name, 
            customer_email, 
            phone, 
            street, 
            colony, 
            city, 
            state, 
            zip_code, 
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([
        $_POST['customer_name'],
        $_POST['customer_email'],
        $_POST['phone'] ?: '',         // Si está vacío, usar cadena vacía
        $_POST['street'] ?: '',        // Si está vacío, usar cadena vacía
        $_POST['colony'] ?: '',        // Si está vacío, usar cadena vacía
        $_POST['city'] ?: '',          // Si está vacío, usar cadena vacía
        $_POST['state'] ?: '',         // Si está vacío, usar cadena vacía
        $_POST['zip_code'] ?: ''       // Si está vacío, usar cadena vacía
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    // Insertar los productos de la orden
    $stmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            quantity,
            price,
            size
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($_POST['products'] as $index => $product_id) {
        if (empty($product_id)) continue;
        
        $quantity = $_POST['quantities'][$index];
        $price = $_POST['prices'][$index];
        $size = $_POST['sizes'][$index];
        
        $stmt->execute([
            $order_id,
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