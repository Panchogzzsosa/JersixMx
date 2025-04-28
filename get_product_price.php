<?php
// Database connection
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

try {
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