<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

// Verificar que se haya proporcionado un término de búsqueda
if (!isset($_GET['q']) || empty($_GET['q'])) {
    echo json_encode(['success' => false, 'message' => 'No se proporcionó un término de búsqueda', 'products' => []]);
    exit;
}

$searchTerm = trim($_GET['q']);

// Asegurarse de que el término de búsqueda tenga al menos 2 caracteres
if (strlen($searchTerm) < 2) {
    echo json_encode(['success' => false, 'message' => 'El término de búsqueda debe tener al menos 2 caracteres', 'products' => []]);
    exit;
}

try {
    $pdo = getConnection();
    
    // Verificar si la columna status existe en la tabla products
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'status'");
    $statusColumnExists = ($stmt->rowCount() > 0);
    
    // Preparar la consulta SQL para buscar productos
    $sql = "SELECT * FROM products WHERE ";
    
    // Si existe la columna status, solo mostrar productos activos
    if ($statusColumnExists) {
        $sql .= "status = 1 AND ";
    }
    
    // Buscar en nombre, descripción y categoría
    $sql .= "(name LIKE :search OR description LIKE :search OR category LIKE :search) ";
    $sql .= "ORDER BY name ASC LIMIT 10"; // Limitar a 10 resultados para no sobrecargar
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':search', '%' . $searchTerm . '%', PDO::PARAM_STR);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Devolver los resultados como JSON
    echo json_encode([
        'success' => true,
        'count' => count($products),
        'products' => $products
    ]);
    
} catch (Exception $e) {
    // En caso de error, devolver un mensaje de error
    echo json_encode([
        'success' => false,
        'message' => 'Error en la búsqueda: ' . $e->getMessage(),
        'products' => []
    ]);
}