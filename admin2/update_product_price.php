<?php
session_start();

// Verificar inicio de sesión
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar que se han enviado los datos necesarios
if (!isset($_POST['product_id']) || !isset($_POST['price'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$product_id = (int)$_POST['product_id'];
$price = (float)$_POST['price'];

// Validar precio
if ($price <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'El precio debe ser mayor que 0']);
    exit();
}

try {
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Actualizar el precio
    $stmt = $pdo->prepare("UPDATE products SET price = ? WHERE product_id = ?");
    $result = $stmt->execute([$price, $product_id]);
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Error al actualizar el precio');
    }
} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 