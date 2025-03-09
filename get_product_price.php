<?php
header('Content-Type: application/json');

// Get the product ID from the request
$productId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($productId)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid product ID'
    ]);
    exit;
}

// Include database configuration
require_once 'config/database.php';

try {
    // Get database connection from config
    $conn = $pdo;
    
    // Prepare and execute query to get product price with improved product matching
    $stmt = $conn->prepare("SELECT price FROM products WHERE product_id = ? OR name LIKE ?");
    $stmt->execute([$productId, '%' . trim($productId) . '%']);
    
    // Log the query for debugging
    error_log('Product search query for ID: ' . $productId);
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'success' => true,
            'price' => floatval($row['price'])
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Product not found'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}