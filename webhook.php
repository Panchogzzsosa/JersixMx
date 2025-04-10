<?php
require_once 'vendor/autoload.php';

// Configuración de MercadoPago
MercadoPago\SDK::setAccessToken('TEST-6508159281724345-012716-7b8ae032875284d169250de531a5040c-485581473');

// Verificar la firma del webhook
$webhookSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$expectedSignature = hash_hmac('sha256', file_get_contents('php://input'), 'f91c13814fe54bab9da3151247949b3a3a2d490d611ee3918bfada895fb8b36b');

if (!hash_equals($webhookSignature, $expectedSignature)) {
    error_log('Firma del webhook inválida');
    http_response_code(401);
    exit;
}

// Database connection

try {
    $pdo = new PDO('mysql:host=localhost:3307;dbname=checkout', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

// Get the POST data
$raw_post_data = file_get_contents('php://input');
$notification = json_decode($raw_post_data);

// Verify and process the notification
if ($notification) {
    try {
        // Get payment information
        $payment_id = $notification->data->id;
        $external_reference = $notification->external_reference;
        $status = $notification->status;
        $payment_status = $notification->payment_status;
        $payment_type = $notification->payment_type;
        $total_amount = $notification->transaction_amount;

        // Get customer information from the notification
        $customer_data = $notification->payer;
        
        // Insert or update customer with full address information
        $stmt = $pdo->prepare('INSERT INTO customers (first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                phone = VALUES(phone),
                address_line1 = VALUES(address_line1),
                address_line2 = VALUES(address_line2),
                city = VALUES(city),
                state = VALUES(state),
                postal_code = VALUES(postal_code),
                country = VALUES(country)')
        ;
        $stmt->execute([
            $customer_data->first_name,
            $customer_data->last_name,
            $customer_data->email,
            $customer_data->phone->number,
            $customer_data->address->street_name,
            $customer_data->address->street_number,
            $customer_data->address->city,
            $customer_data->address->state,
            $customer_data->address->zip_code,
            $customer_data->address->country
        ]);
        $customer_id = $pdo->lastInsertId();

        // Create order
        $stmt = $pdo->prepare('INSERT INTO orders (customer_id, order_date, total_amount, status, payment_status, payment_id, external_reference) VALUES (?, NOW(), ?, ?, ?, ?, ?)');
        $stmt->execute([
            $customer_id,
            $total_amount,
            $status,
            $payment_status,
            $payment_id,
            $external_reference
        ]);
        $order_id = $pdo->lastInsertId();

        // Insert order items with size information
        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, price, size) VALUES (?, ?, ?, ?, ?)');
        foreach ($notification->items as $item) {
            $stmt->execute([
                $order_id,
                $item->product_id ?? $item->id ?? 0,
                $item->quantity,
                $item->unit_price,
                $item->size ?? null
            ]);

            // Update product stock
            $update_stock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE product_id = ?');
            $update_stock->execute([$item->quantity, $item->product_id ?? $item->id ?? 0]);
        }

        // Log successful order
        error_log("Order processed successfully: Order ID " . $order_id);
        http_response_code(200);
    } catch (Exception $e) {
        error_log('Error processing order: ' . $e->getMessage());
        http_response_code(500);
    }
} else {
    error_log('Invalid notification data received');
    http_response_code(400);
}