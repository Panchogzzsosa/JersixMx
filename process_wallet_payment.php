<?php
// Incluir archivo de configuración de la base de datos
require_once __DIR__ . '/config/database.php';

// Database connection
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    // Extract customer information
    $customerData = [
        'first_name' => $data['firstName'] ?? '',
        'last_name' => $data['lastName'] ?? '',
        'email' => $data['email'] ?? '',
        'phone' => $data['phone'] ?? '',
        'address_line1' => $data['address'] ?? '',
        'address_line2' => $data['address2'] ?? '',
        'city' => $data['city'] ?? '',
        'state' => $data['state'] ?? '',
        'postal_code' => $data['postalCode'] ?? '',
        'country' => $data['country'] ?? ''
    ];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert customer data
    $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country) VALUES (:first_name, :last_name, :email, :phone, :address_line1, :address_line2, :city, :state, :postal_code, :country)");
    $stmt->execute($customerData);
    $customerId = $pdo->lastInsertId();
    
    // Create order
    $orderStmt = $pdo->prepare("INSERT INTO orders (customer_id, total_amount, payment_method, payment_status) VALUES (:customer_id, :total_amount, 'wallet', 'paid')");
    $orderStmt->execute([
        'customer_id' => $customerId,
        'total_amount' => $data['totalAmount'] ?? 0
    ]);
    $orderId = $pdo->lastInsertId();
    
    // Insert order items
    $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, size) VALUES (:order_id, :product_id, :quantity, :price, :size)");
    
    foreach ($data['items'] as $item) {
        $itemStmt->execute([
            'order_id' => $orderId,
            'product_id' => $item['product_id'] ?? $item['id'] ?? 0,
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'size' => $item['size'] ?? null
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order processed successfully',
        'order_id' => $orderId
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to process order',
        'message' => $e->getMessage()
    ]);
}