<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Database connection
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

// Get the POST data
$raw_post_data = file_get_contents('php://input');
$order_data = json_decode($raw_post_data);

// Log the received data
error_log('Received order data: ' . $raw_post_data);

if ($order_data) {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Insert customer data
        $stmt = $pdo->prepare('INSERT INTO customers (first_name, last_name, email, phone, address_line1, city, state, postal_code, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $order_data->customer->first_name,
            $order_data->customer->last_name,
            $order_data->customer->email,
            $order_data->customer->phone ?? '',
            $order_data->shipping_address->street_name . ' ' . $order_data->shipping_address->street_number,
            $order_data->shipping_address->city,
            $order_data->shipping_address->state,
            $order_data->shipping_address->zip_code,
            'Mexico'
        ]);
        $customer_id = $pdo->lastInsertId();

        // Create order
        $stmt = $pdo->prepare('INSERT INTO orders (customer_id, order_date, total_amount, status, payment_status) VALUES (?, NOW(), ?, ?, ?)');
        $stmt->execute([
            $customer_id,
            $order_data->total_amount,
            'pending', // Default status
            'pending'  // Default payment status
        ]);
        $order_id = $pdo->lastInsertId();

        // Store shipping address
        $stmt = $pdo->prepare('INSERT INTO order_addresses (order_id, street, street_number, neighborhood, city, state, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $order_id,
            $order_data->shipping_address->street_name,
            $order_data->shipping_address->street_number,
            $order_data->shipping_address->neighborhood ?? '',
            $order_data->shipping_address->city,
            $order_data->shipping_address->state,
            $order_data->shipping_address->zip_code
        ]);

        // Process order items
        if (!isset($order_data->items) || empty($order_data->items)) {
            throw new Exception('Order items are missing');
        }

        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, price, size) VALUES (?, ?, ?, ?, ?)');
        foreach ($order_data->items as $item) {
            if (!isset($item->id) || !isset($item->quantity) || !isset($item->unit_price)) {
                throw new Exception('Invalid item data');
            }

            $stmt->execute([
                $order_id,
                $item->product_id ?? $item->id ?? 0,
                $item->quantity,
                $item->unit_price,
                $item->size ?? 'M' // Default size if not specified
            ]);

            // Update product stock
            $update_stock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE product_id = ?');
            $update_stock->execute([$item->quantity, $item->product_id ?? $item->id ?? 0]);
        }

        // Commit transaction
        $pdo->commit();

        // Log successful order creation
        error_log("Order created successfully: Order ID " . $order_id);
        http_response_code(200);
        echo json_encode(['success' => true, 'order_id' => $order_id]);

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error processing order: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    error_log('Invalid order data received');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order data']);
}