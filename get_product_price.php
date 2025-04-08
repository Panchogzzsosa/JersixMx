<?php
// Database connection
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

try {
    // Verificar si es una gift card primero
    if (isset($_GET['id']) && (
        strtolower($_GET['id']) === 'tarjeta de regalo jersix' || 
        stripos($_GET['id'], 'gift card') !== false ||
        stripos($_GET['id'], 'tarjeta de regalo') !== false
    )) {
        // Si es una gift card, devolver un precio válido (puede ser cualquiera, se utilizará el especificado en el carrito)
        echo json_encode([
            'success' => true,
            'price' => 100.00, // Un valor predeterminado
            'product_id' => 66, // ID de la tarjeta de regalo
            'is_giftcard' => true
        ]);
        exit;
    }
    
    $pdo = getConnection();
    
    // Check if we have an ID or a product name parameter
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        // If numeric, search by ID
        if (is_numeric($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT product_id, price FROM products WHERE product_id = ?');
            $stmt->execute([intval($_GET['id'])]);
        } else {
            // If not numeric, assume it's a product identifier
            $stmt = $pdo->prepare('SELECT product_id, price FROM products WHERE name LIKE ?');
            $stmt->execute(['%' . $_GET['id'] . '%']);
        }
    } else {
        // Invalid request
        echo json_encode([
            'success' => false,
            'message' => 'Invalid product identifier'
        ]);
        exit;
    }
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo json_encode([
            'success' => true,
            'price' => $product['price'],
            'product_id' => $product['product_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}