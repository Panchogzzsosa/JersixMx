<?php
// Configuración de CORS para permitir solicitudes desde el frontend
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Validar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos de la solicitud
$data = json_decode(file_get_contents('php://input'), true);

// Verificar que se proporcionó un código
if (!isset($data['code']) || empty($data['code'])) {
    echo json_encode(['success' => false, 'message' => 'Por favor proporciona un código de tarjeta de regalo']);
    exit;
}

// Configuración de logger
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/giftcard_redemption.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // También escribir al error_log para mejor depuración
    error_log($message);
}

// Verificar si hay tarjetas de regalo en el carrito (usando cookies)
if (isset($_COOKIE['cart'])) {
    $cart = json_decode(urldecode($_COOKIE['cart']), true);
    $hasGiftCard = false;
    
    if (is_array($cart)) {
        foreach ($cart as $item) {
            if (
                (isset($item['isGiftCard']) && $item['isGiftCard']) ||
                (isset($item['title']) && (strpos($item['title'], 'Tarjeta de Regalo') !== false || strpos($item['title'], 'Gift Card') !== false))
            ) {
                $hasGiftCard = true;
                break;
            }
        }
    }
    
    if ($hasGiftCard) {
        writeLog("Intento de usar tarjeta de regalo cuando hay una tarjeta de regalo en el carrito");
        echo json_encode([
            'success' => false,
            'message' => 'No se pueden usar tarjetas de regalo en compras de tarjetas de regalo'
        ]);
        exit;
    }
}

// Incluir archivo de configuración de la base de datos
require_once __DIR__ . '/config/database.php';

// Conectar a la base de datos
try {
    $pdo = getConnection();
    
    // Limpiar el código de espacios y caracteres no deseados
    $code = trim($data['code']);
    
    writeLog("Verificando código de Gift Card: " . $code);
    
    // Buscar el código en la base de datos
    $stmt = $pdo->prepare("
        SELECT oi.*, o.status, gc.redeemed, gc.balance
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        LEFT JOIN giftcard_redemptions gc ON oi.personalization_number = gc.code
        JOIN products p ON oi.product_id = p.product_id
        WHERE 
            oi.personalization_number = ? 
            AND (p.name LIKE '%Tarjeta de Regalo%' OR p.product_id = 66 OR p.name LIKE '%Gift Card%')
    ");
    
    $stmt->execute([$code]);
    $giftcard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si existe una tabla de seguimiento de redenciones
    $tableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_redemptions'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Crear la tabla si no existe
        $pdo->exec("
            CREATE TABLE giftcard_redemptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                original_amount DECIMAL(10,2) NOT NULL,
                balance DECIMAL(10,2) NOT NULL,
                redeemed TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        writeLog("Tabla giftcard_redemptions creada");
    }
    
    if (!$giftcard) {
        writeLog("Código de Gift Card no encontrado: " . $code);
        echo json_encode(['success' => false, 'message' => 'Código de tarjeta de regalo no válido']);
        exit;
    }
    
    // Verificar si la orden está completa o si ya se procesó el pago
    if ($giftcard['status'] !== 'completed' && $giftcard['status'] !== 'processing') {
        writeLog("Gift Card no válida - Orden no completada: " . $code);
        echo json_encode(['success' => false, 'message' => 'Esta tarjeta de regalo aún no está activa']);
        exit;
    }
    
    // Obtener el saldo disponible
    $amount = $giftcard['price'];
    $balance = $amount;
    
    // Si ya existe un registro en la tabla de redenciones, obtener el saldo actual
    if (isset($giftcard['balance'])) {
        $balance = $giftcard['balance'];
        writeLog("Gift card encontrada con registro en giftcard_redemptions: Saldo = $" . $balance);
    } else {
        // Si no existe registro, crearlo
        $insertStmt = $pdo->prepare("
            INSERT INTO giftcard_redemptions (code, original_amount, balance, redeemed)
            VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE balance = VALUES(balance)
        ");
        $insertStmt->execute([$code, $amount, $balance]);
        writeLog("Nuevo registro creado en giftcard_redemptions para: " . $code . " con saldo = $" . $balance);
    }
    
    // Verificar si todavía tiene saldo
    if ($balance <= 0) {
        writeLog("Gift Card ya utilizada completamente: " . $code . " (Saldo = $" . $balance . ")");
        echo json_encode(['success' => false, 'message' => 'Esta tarjeta de regalo ya ha sido utilizada completamente']);
        exit;
    }
    
    // Verificar transacciones anteriores
    try {
        $transStmt = $pdo->prepare("
            SELECT SUM(amount) as total_used, COUNT(*) as num_transactions
            FROM giftcard_transactions
            WHERE code = ?
        ");
        $transStmt->execute([$code]);
        $transData = $transStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transData && $transData['num_transactions'] > 0) {
            $totalUsed = $transData['total_used'] ?: 0;
            writeLog("Gift Card " . $code . " - Transacciones anteriores: " . $transData['num_transactions'] . 
                    ", Total usado: $" . $totalUsed . ", Saldo actual: $" . $balance);
        } else {
            writeLog("Gift Card " . $code . " - Sin transacciones anteriores registradas");
        }
    } catch (PDOException $e) {
        // Silenciar errores, es solo informativo
        writeLog("Error al verificar transacciones: " . $e->getMessage());
    }
    
    // Devolver información de la Gift Card
    echo json_encode([
        'success' => true, 
        'message' => 'Código de tarjeta de regalo válido',
        'data' => [
            'code' => $code,
            'amount' => $amount,
            'balance' => $balance
        ]
    ]);
    
    writeLog("Gift Card válida: " . $code . " - Saldo: $" . $balance);
    
} catch (PDOException $e) {
    writeLog("Error de base de datos: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al validar la tarjeta de regalo']);
} 