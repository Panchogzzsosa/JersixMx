<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir la ruta del archivo de log
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/order_debug.log';

// Función para escribir en el log
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Iniciar el logging
writeLog("=== NUEVA ORDEN ===");
writeLog("POST recibido: " . print_r($_POST, true));

header('Content-Type: application/json');

try {
    // Validar datos necesarios
    $requiredFields = ['fullname', 'email', 'phone', 'street', 'colonia', 'city', 'state', 'postal', 'payment_id', 'cart_items'];
    
    writeLog("Verificando campos requeridos...");
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            writeLog("Campo faltante: $field");
            throw new Exception("Campo requerido faltante: $field");
        }
    }

    writeLog("Decodificando items del carrito...");
    $cartItems = json_decode($_POST['cart_items'], true);
    if (!$cartItems) {
        writeLog("Error decodificando cart_items: " . json_last_error_msg());
        throw new Exception('Error al decodificar los items del carrito');
    }

    writeLog("Items del carrito: " . print_r($cartItems, true));

    // Conexión a la base de datos
    writeLog("Conectando a la base de datos...");
    $pdo = new PDO('mysql:host=localhost;dbname=checkout', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    writeLog("Conexión exitosa");

    // Iniciar transacción
    $pdo->beginTransaction();
    writeLog("Transacción iniciada");

    // Insertar la orden
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            customer_name,
            customer_email,
            phone,
            street,
            colony,
            city,
            state,
            zip_code,
            status,
            payment_method,
            payment_id,
            payment_status,
            created_at
        ) VALUES (
            :name,
            :email,
            :phone,
            :street,
            :colony,
            :city,
            :state,
            :zip,
            'pending',
            'paypal',
            :payment_id,
            'paid',
            NOW()
        )
    ");

    $orderData = [
        ':name' => $_POST['fullname'],
        ':email' => $_POST['email'],
        ':phone' => $_POST['phone'],
        ':street' => $_POST['street'],
        ':colony' => $_POST['colonia'],
        ':city' => $_POST['city'],
        ':state' => $_POST['state'],
        ':zip' => $_POST['postal'],
        ':payment_id' => $_POST['payment_id']
    ];

    writeLog("Insertando orden con datos: " . print_r($orderData, true));
    $stmt->execute($orderData);
    $orderId = $pdo->lastInsertId();
    writeLog("Orden creada con ID: $orderId");

    // Insertar items
    writeLog("Insertando items de la orden...");
    $stmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            quantity,
            price,
            size
        ) VALUES (
            :order_id,
            :product_id,
            :quantity,
            :price,
            :size
        )
    ");

    foreach ($cartItems as $item) {
        // Obtener el product_id basado en el título del producto
        $productStmt = $pdo->prepare("SELECT product_id FROM products WHERE name = ?");
        $productStmt->execute([$item['title']]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            writeLog("Producto no encontrado: " . $item['title']);
            throw new Exception('Producto no encontrado: ' . $item['title']);
        }
        
        $itemData = [
            ':order_id' => $orderId,
            ':product_id' => $product['product_id'],
            ':quantity' => $item['quantity'],
            ':price' => $item['price'],
            ':size' => $item['size'] ?? null
        ];
        
        writeLog("Insertando item: " . print_r($itemData, true));
        $stmt->execute($itemData);
    }

    // Actualizar estadísticas de ventas
    $total = array_reduce($cartItems, function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);

    $stmt = $pdo->prepare("
        INSERT INTO sales_stats (total_sales, total_orders)
        VALUES (:total, 1)
        ON DUPLICATE KEY UPDATE
        total_sales = total_sales + :total,
        total_orders = total_orders + 1
    ");
    $stmt->execute([':total' => $total]);

    $pdo->commit();
    writeLog("Transacción completada exitosamente");

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Orden procesada correctamente'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        writeLog("Rollback de la transacción");
    }
    
    writeLog("ERROR: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la orden: ' . $e->getMessage()
    ]);
}

writeLog("=== FIN DEL PROCESO ===\n");