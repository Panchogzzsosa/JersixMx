<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/success_error.log');

// Get the preference ID from the URL
$preference_id = $_GET['preference_id'] ?? null;
$payment_id = $_GET['payment_id'] ?? null;
$status = $_GET['status'] ?? null;

// Only process if we have a successful payment
if ($status === 'approved' && $payment_id) {
    // Update order status in database

    try {
        $conn = new mysqli('216.245.211.58', 'jersixmx_usuario_total', '?O*6o6&Hs&~Q', 'jersixmx_checkout');
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Update the order status
        $stmt = $conn->prepare("UPDATE orders SET payment_status = ?, payment_id = ? WHERE preference_id = ?");
        $payment_status = 'completed';
        $stmt->bind_param("sss", $payment_status, $payment_id, $preference_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            error_log("No order found with preference_id: " . $preference_id);
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Error updating order: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Exitoso - JerSix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
        }
        .success-container {
            text-align: center;
            padding: 2rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        .success-icon {
            color: #4CAF50;
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .success-title {
            color: #333;
            margin-bottom: 1rem;
        }
        .success-message {
            color: #666;
            margin-bottom: 2rem;
        }
        .back-button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .back-button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h1 class="success-title">¡Pago Exitoso!</h1>
        <p class="success-message">Tu pago ha sido procesado correctamente. Gracias por tu compra.</p>
        <a href="index.php" class="back-button">Volver al inicio</a>
    </div>
</body>
</html>