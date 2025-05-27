<?php
// Database connection
require_once __DIR__ . '/../config/database.php';

// Configuración de encabezados para respuestas JSON
header('Content-Type: application/json');

// Verificar los parámetros requeridos
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Validar los parámetros
if ($product_id <= 0) {
    echo json_encode(['error' => 'ID de producto no válido']);
    exit;
}

if ($offset < 0) {
    echo json_encode(['error' => 'Offset no válido']);
    exit;
}

// Número de reseñas a cargar por página
$limit = 5;

try {
    $pdo = getConnection();
    
    // Obtener reseñas adicionales
    $stmt = $pdo->prepare('
        SELECT *,
        DATE_FORMAT(fecha_creacion, "%d/%m/%Y") as fecha_formateada
        FROM resenas 
        WHERE producto_id = ? 
        ORDER BY fecha_creacion DESC
        LIMIT ?, ?
    ');
    $stmt->bindParam(1, $product_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->bindParam(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar si hay más reseñas disponibles
    $stmt = $pdo->prepare('
        SELECT COUNT(*) 
        FROM resenas 
        WHERE producto_id = ?
    ');
    $stmt->execute([$product_id]);
    $total = $stmt->fetchColumn();
    
    $has_more = ($offset + $limit) < $total;
    
    // Preparar la respuesta
    echo json_encode([
        'reviews' => $reviews,
        'has_more' => $has_more,
        'total' => $total,
        'current_offset' => $offset,
        'limit' => $limit
    ]);
    
} catch (PDOException $e) {
    // Error en la base de datos
    http_response_code(500);
    echo json_encode(['error' => 'Error en el servidor', 'details' => $e->getMessage()]);
    exit;
}
?> 