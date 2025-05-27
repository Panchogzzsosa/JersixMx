<?php
session_start();

// Mostrar errores durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar inicio de sesión
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar que se ha enviado el ID del producto y el status
if (!isset($_POST['product_id']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$product_id = (int)$_POST['product_id'];
$status = (int)$_POST['status']; // 1 = activo/visible, 0 = inactivo/oculto

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit();
}

// Verificar si la columna status existe en la tabla
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'status'");
    $statusColumnExists = ($stmt->rowCount() > 0);
    
    if (!$statusColumnExists) {
        // Crear la columna si no existe
        $pdo->exec("ALTER TABLE products ADD COLUMN status TINYINT(1) DEFAULT 1");
    }
    
    // Actualizar el estado del producto
    $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE product_id = ?");
    $result = $stmt->execute([$status, $product_id]);
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => $status ? 'Producto activado correctamente' : 'Producto desactivado correctamente'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
    }
} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 