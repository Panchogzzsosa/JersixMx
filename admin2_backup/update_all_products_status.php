<?php
session_start();

// Verificar inicio de sesión
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar si se recibió el parámetro de estado
if (!isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No se especificó el estado']);
    exit();
}

$status = intval($_POST['status']);

// Conexión a la base de datos
try {
    $pdo = new PDO('mysql:host=localhost;dbname=checkout', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Actualizar todos los productos
    $stmt = $pdo->prepare("UPDATE products SET status = ?");
    $result = $stmt->execute([$status]);
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Todos los productos han sido actualizados correctamente']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error al actualizar los productos']);
    }
} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?> 