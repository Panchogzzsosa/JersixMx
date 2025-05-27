<?php
session_start();

// Verificar inicio de sesión
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar datos necesarios
if (!isset($_POST['product_id']) || !isset($_POST['field']) || !isset($_POST['value'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$product_id = (int)$_POST['product_id'];
$field = $_POST['field'];
$value = (float)$_POST['value'];

// Validar campo
if (!in_array($field, ['price', 'stock'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Campo no válido']);
    exit();
}

// Validar valor
if ($value < 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'El valor debe ser mayor o igual a 0']);
    exit();
}



// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Actualizar el campo
    $stmt = $pdo->prepare("UPDATE products SET $field = ? WHERE product_id = ?");
    $result = $stmt->execute([$value, $product_id]);
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Error al actualizar el campo');
    }
} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 