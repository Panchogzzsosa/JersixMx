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

// Verificar que se proporcionaron todos los datos necesarios
if (!isset($data['code']) || empty($data['code']) || 
    !isset($data['amount']) || !is_numeric($data['amount']) || 
    !isset($data['order_id']) || empty($data['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
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
}

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/config/database.php';

// Conectar a la base de datos
try {
    $pdo = getConnection();
    $pdo->beginTransaction();
    
    // Limpiar y obtener valores
    $code = trim($data['code']);
    $amount = floatval($data['amount']); // Monto a aplicar
    $orderId = trim($data['order_id']);
    
    writeLog("Aplicando Gift Card: $code ($$amount) al pedido #$orderId");
    
    // Verificar que exista la tabla de redenciones
    $tableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_redemptions'")->rowCount() > 0;
    
    if (!$tableExists) {
        throw new Exception('La tabla de redenciones no existe');
    }
    
    // Verificar que exista la tabla de transacciones
    $transTableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_transactions'")->rowCount() > 0;
    
    if (!$transTableExists) {
        // Crear la tabla si no existe
        $pdo->exec("
            CREATE TABLE giftcard_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL,
                order_id VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        writeLog("Tabla giftcard_transactions creada");
    }
    
    // Obtener información de la Gift Card
    $stmt = $pdo->prepare("
        SELECT code, balance, redeemed
        FROM giftcard_redemptions
        WHERE code = ?
    ");
    $stmt->execute([$code]);
    $giftcard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$giftcard) {
        throw new Exception('La tarjeta de regalo no existe o no es válida');
    }
    
    // Verificar si todavía tiene saldo
    if ($giftcard['balance'] <= 0 || $giftcard['redeemed'] == 1) {
        throw new Exception('Esta tarjeta de regalo ya ha sido utilizada completamente');
    }
    
    // Verificar que el monto no sea mayor que el saldo disponible
    if ($amount > $giftcard['balance']) {
        $amount = $giftcard['balance']; // Limitar al saldo disponible
        writeLog("Ajustando monto a aplicar al saldo disponible: $$amount");
    }
    
    // Calcular nuevo saldo
    $newBalance = $giftcard['balance'] - $amount;
    $redeemed = ($newBalance <= 0) ? 1 : 0;
    
    // Actualizar el saldo
    $updateStmt = $pdo->prepare("
        UPDATE giftcard_redemptions
        SET balance = ?, redeemed = ?
        WHERE code = ?
    ");
    $updateStmt->execute([$newBalance, $redeemed, $code]);
    
    // Registrar la transacción
    $transStmt = $pdo->prepare("
        INSERT INTO giftcard_transactions (code, order_id, amount)
        VALUES (?, ?, ?)
    ");
    $transStmt->execute([$code, $orderId, $amount]);
    
    // Guardar la información de la Gift Card en la orden
    $orderStmt = $pdo->prepare("
        UPDATE orders
        SET payment_notes = CONCAT(IFNULL(payment_notes, ''), 'Gift Card aplicada: ', ?, ' - Monto: $', ?)
        WHERE order_id = ?
    ");
    $orderStmt->execute([$code, $amount, $orderId]);
    
    $pdo->commit();
    
    // Devolver la información actualizada
    echo json_encode([
        'success' => true,
        'message' => 'Tarjeta de regalo aplicada correctamente',
        'data' => [
            'code' => $code,
            'amount_applied' => $amount,
            'remaining_balance' => $newBalance,
            'fully_redeemed' => ($redeemed == 1)
        ]
    ]);
    
    writeLog("Gift Card $code aplicada exitosamente. Monto: $$amount, Saldo restante: $$newBalance");
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    writeLog("Error al aplicar Gift Card: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al aplicar la tarjeta de regalo: ' . $e->getMessage()]);
} 