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
$debugFile = $logDir . '/gift_debug.log'; // Nuevo archivo para debug detallado

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function writeDebug($message) {
    global $debugFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($debugFile, $logMessage, FILE_APPEND);
}

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/config/database.php';

// Conectar a la base de datos
try {
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Asegurarse de que PDO arroje excepciones
    $pdo->beginTransaction();
    
    // Limpiar y obtener valores
    $code = trim($data['code']);
    $amount = floatval($data['amount']); // Monto a aplicar
    $orderId = trim($data['order_id']);
    
    writeLog("Aplicando Gift Card: $code ($$amount) al pedido #$orderId");
    writeDebug("INICIANDO TRANSACCIÓN: Aplicando Gift Card $code por $amount a orden $orderId");
    
    // Verificar que exista la tabla de redenciones
    $tableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_redemptions'")->rowCount() > 0;
    
    if (!$tableExists) {
        writeDebug("ERROR: La tabla giftcard_redemptions no existe");
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
        writeDebug("TABLA CREADA: Se creó la tabla giftcard_redemptions");
    }
    
    // Verificar que exista la tabla de transacciones
    $transTableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_transactions'")->rowCount() > 0;
    
    if (!$transTableExists) {
        writeDebug("ERROR: La tabla giftcard_transactions no existe");
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
        writeDebug("TABLA CREADA: Se creó la tabla giftcard_transactions");
    }
    
    // Obtener información de la Gift Card
    $stmt = $pdo->prepare("
        SELECT code, balance, redeemed, original_amount
        FROM giftcard_redemptions
        WHERE code = ?
    ");
    $stmt->execute([$code]);
    $giftcard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    writeDebug("BUSCAR GIFT CARD: Resultado = " . ($giftcard ? 'ENCONTRADA' : 'NO ENCONTRADA'));
    
    if (!$giftcard) {
        // Si no se encuentra, buscar en order_items para ver si existe pero no está en la tabla de redenciones
        $checkOrderItemStmt = $pdo->prepare("
            SELECT oi.personalization_number as code, oi.price as amount
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.personalization_number = ? AND o.status IN ('processing', 'completed')
        ");
        $checkOrderItemStmt->execute([$code]);
        $orderItem = $checkOrderItemStmt->fetch(PDO::FETCH_ASSOC);
        
        writeDebug("BUSCAR EN ORDER_ITEMS: Resultado = " . ($orderItem ? 'ENCONTRADA' : 'NO ENCONTRADA'));
        
        if ($orderItem) {
            // Si existe en order_items pero no en redemptions, crearla
            $originalAmount = $orderItem['amount'];
            $createStmt = $pdo->prepare("
                INSERT INTO giftcard_redemptions 
                (code, original_amount, balance, redeemed, created_at, updated_at)
                VALUES (?, ?, ?, 0, NOW(), NOW())
            ");
            $createResult = $createStmt->execute([$code, $originalAmount, $originalAmount]);
            
            writeDebug("CREAR REGISTRO: Resultado = " . ($createResult ? 'ÉXITO' : 'FALLIDO') . 
                       ", Monto Original = $originalAmount");
                       
            if ($createResult) {
                // Recargar el registro recién creado
                $stmt->execute([$code]);
                $giftcard = $stmt->fetch(PDO::FETCH_ASSOC);
                
                writeDebug("RECARGAR GIFT CARD: Resultado = " . ($giftcard ? 'ÉXITO' : 'FALLIDO'));
            } else {
                throw new Exception('No se pudo crear un registro para la tarjeta de regalo');
            }
        } else {
            throw new Exception('La tarjeta de regalo no existe o no es válida');
        }
    }
    
    writeDebug("GIFT CARD: Código = " . $giftcard['code'] . 
              ", Saldo Actual = " . $giftcard['balance'] . 
              ", Redimida = " . ($giftcard['redeemed'] ? 'SÍ' : 'NO') . 
              ", Monto Original = " . $giftcard['original_amount']);
    
    // Verificar si todavía tiene saldo
    if ($giftcard['balance'] <= 0 || $giftcard['redeemed'] == 1) {
        writeDebug("ERROR: Gift card sin saldo o ya redimida");
        throw new Exception('Esta tarjeta de regalo ya ha sido utilizada completamente');
    }
    
    // Verificar que el monto no sea mayor que el saldo disponible
    if ($amount > $giftcard['balance']) {
        $amount = $giftcard['balance']; // Limitar al saldo disponible
        writeDebug("AJUSTAR MONTO: Nuevo monto = $amount (limitado al saldo disponible)");
    }
    
    // Calcular nuevo saldo
    $newBalance = $giftcard['balance'] - $amount;
    // Asegurar que el nuevo saldo nunca sea negativo
    $newBalance = max(0, $newBalance);
    $redeemed = ($newBalance <= 0) ? 1 : 0;
    
    writeDebug("NUEVO SALDO: $newBalance, Será marcada como redimida = " . ($redeemed ? 'SÍ' : 'NO'));
    
    // Actualizar el saldo
    $updateStmt = $pdo->prepare("
        UPDATE giftcard_redemptions
        SET balance = ?, redeemed = ?, updated_at = NOW()
        WHERE code = ?
    ");
    $updateResult = $updateStmt->execute([$newBalance, $redeemed, $code]);
    $rowsAffected = $updateStmt->rowCount();
    
    writeDebug("ACTUALIZAR SALDO: Resultado = " . ($updateResult ? 'ÉXITO' : 'FALLIDO') . 
              ", Filas afectadas = $rowsAffected" .
              ", Saldo anterior = " . $giftcard['balance'] . 
              ", Monto utilizado = $amount" .
              ", Nuevo saldo = $newBalance" .
              ", Código = $code");
    
    if ($rowsAffected == 0) {
        writeDebug("ERROR CRÍTICO: No se actualizó el saldo de la gift card aunque la query no dio error");
        
        // Intentar una actualización más directa
        $directUpdateSql = "UPDATE giftcard_redemptions SET balance = $newBalance, redeemed = $redeemed, updated_at = NOW() WHERE code = '$code'";
        writeDebug("Intentando actualización directa con SQL: $directUpdateSql");
        
        try {
            $directUpdateResult = $pdo->exec($directUpdateSql);
            writeDebug("Resultado actualización directa: $directUpdateResult filas afectadas");
            
            if ($directUpdateResult == 0) {
                throw new Exception('No se pudo actualizar el saldo de la tarjeta de regalo');
            }
        } catch (PDOException $e) {
            writeDebug("ERROR en actualización directa: " . $e->getMessage());
            throw new Exception('No se pudo actualizar el saldo de la tarjeta de regalo');
        }
    }
    
    // Registrar la transacción
    $transStmt = $pdo->prepare("
        INSERT INTO giftcard_transactions (code, order_id, amount, transaction_date)
        VALUES (?, ?, ?, NOW())
    ");
    $transResult = $transStmt->execute([$code, $orderId, $amount]);
    $transId = $pdo->lastInsertId();
    
    writeDebug("REGISTRAR TRANSACCIÓN: Resultado = " . ($transResult ? 'ÉXITO' : 'FALLIDO') . 
              ", ID generado = $transId" . 
              ", Código = $code" .
              ", Orden = $orderId" .
              ", Monto = $amount");
    
    if (!$transResult || !$transId) {
        writeDebug("ERROR: No se pudo registrar la transacción");
        throw new Exception('No se pudo registrar la transacción de la tarjeta de regalo');
    }
    
    // Si la gift card fue completamente utilizada (saldo = 0), marcar la orden como completada
    if ($redeemed == 1) {
        // Obtener el ID de la orden que contiene esta gift card original (no la orden donde se está aplicando)
        $gcOrderStmt = $pdo->prepare("
            SELECT o.order_id 
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.personalization_number = ? 
            AND (p.name LIKE '%Tarjeta de Regalo%' OR p.name LIKE '%Gift Card%')
            AND o.status != 'completed'
        ");
        $gcOrderStmt->execute([$code]);
        $gcOrderData = $gcOrderStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($gcOrderData) {
            // Actualizar el estado de la orden a "completed"
            writeDebug("ACTUALIZAR ORDEN A COMPLETADA: Gift Card agotada, actualizando orden " . $gcOrderData['order_id']);
            
            $completeOrderStmt = $pdo->prepare("
                UPDATE orders 
                SET status = 'completed'
                WHERE order_id = ?
            ");
            $completeResult = $completeOrderStmt->execute([$gcOrderData['order_id']]);
            
            writeDebug("RESULTADO ACTUALIZACIÓN ORDEN: " . ($completeResult ? "ÉXITO" : "FALLIDO") . 
                      " - Orden #" . $gcOrderData['order_id'] . " marcada como completada porque gift card fue totalmente redimida");
        } else {
            writeDebug("No se encontró la orden origen de la gift card o ya está marcada como completada");
        }
    }
    
    // Verificar si la transacción se guardó correctamente
    $checkTransStmt = $pdo->prepare("
        SELECT * FROM giftcard_transactions WHERE id = ?
    ");
    $checkTransStmt->execute([$transId]);
    $transaction = $checkTransStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        writeDebug("ERROR CRÍTICO: La transacción no se encontró después de insertarla (ID: $transId)");
        
        // Intentar un INSERT directo
        $directInsertSql = "INSERT INTO giftcard_transactions (code, order_id, amount, transaction_date) VALUES ('$code', '$orderId', $amount, NOW())";
        writeDebug("Intentando INSERT directo: $directInsertSql");
        
        try {
            $directInsertResult = $pdo->exec($directInsertSql);
            $directInsertId = $pdo->lastInsertId();
            writeDebug("Resultado INSERT directo: $directInsertResult filas afectadas, nuevo ID: $directInsertId");
            
            if ($directInsertResult == 0 || !$directInsertId) {
                throw new Exception('No se pudo registrar la transacción después de múltiples intentos');
            }
        } catch (PDOException $e) {
            writeDebug("ERROR en INSERT directo: " . $e->getMessage());
            throw new Exception('Error al registrar la transacción: ' . $e->getMessage());
        }
    }
    
    // Guardar la información de la Gift Card en la orden
    $orderStmt = $pdo->prepare("
        UPDATE orders
        SET payment_notes = CONCAT(IFNULL(payment_notes, ''), 'Gift Card aplicada: ', ?, ' - Monto: $', ?)
        WHERE order_id = ?
    ");
    $orderResult = $orderStmt->execute([$code, $amount, $orderId]);
    $orderRowsAffected = $orderStmt->rowCount();
    
    writeDebug("ACTUALIZAR ORDEN: Resultado = " . ($orderResult ? 'ÉXITO' : 'FALLIDO') . 
              ", Filas afectadas = $orderRowsAffected");
    
    // También actualizar el payment_method a "giftcard" o "giftcard+paypal"
    $updatePaymentMethodStmt = $pdo->prepare("
        UPDATE orders 
        SET payment_method = CASE 
            WHEN payment_method = 'paypal' THEN 'giftcard+paypal'
            ELSE 'giftcard'
        END
        WHERE order_id = ?
    ");
    $updatePaymentMethodResult = $updatePaymentMethodStmt->execute([$orderId]);
    writeDebug("ACTUALIZAR MÉTODO DE PAGO: Resultado = " . ($updatePaymentMethodResult ? 'ÉXITO' : 'FALLIDO'));
    
    // Volver a verificar el saldo actualizado
    $verifyStmt = $pdo->prepare("SELECT balance, redeemed FROM giftcard_redemptions WHERE code = ?");
    $verifyStmt->execute([$code]);
    $updatedGiftcard = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($updatedGiftcard) {
        writeDebug("VERIFICACIÓN POST-ACTUALIZACIÓN: Saldo = " . $updatedGiftcard['balance'] . 
                  ", Redimida = " . ($updatedGiftcard['redeemed'] ? 'SÍ' : 'NO'));
                  
        // Verificar si el saldo se actualizó correctamente
        if (abs($updatedGiftcard['balance'] - $newBalance) > 0.01) {
            writeDebug("ERROR CRÍTICO: El saldo no se actualizó correctamente. Esperado: $newBalance, Actual: " . $updatedGiftcard['balance']);
            throw new Exception('Error en la actualización del saldo de la tarjeta de regalo');
        }
    } else {
        writeDebug("ERROR CRÍTICO: No se pudo verificar el estado actualizado de la gift card");
        throw new Exception('Error al verificar el estado actualizado de la tarjeta de regalo');
    }
    
    // Verificar explícitamente que la transacción se haya registrado
    $checkTransStmt = $pdo->prepare("SELECT id FROM giftcard_transactions WHERE code = ? AND order_id = ? AND amount = ?");
    $checkTransStmt->execute([$code, $orderId, $amount]);
    $transactionExists = $checkTransStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transactionExists) {
        writeDebug("ERROR CRÍTICO: No se encontró la transacción recién creada en la base de datos");
        throw new Exception('Error al registrar la transacción de la tarjeta de regalo');
    }
    
    $pdo->commit();
    writeDebug("TRANSACCIÓN COMPLETADA: El proceso se completó exitosamente");
    
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
        writeDebug("ROLLBACK: Se deshizo la transacción debido a un error");
    }
    
    writeLog("Error al aplicar Gift Card: " . $e->getMessage());
    writeDebug("ERROR EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    echo json_encode(['success' => false, 'message' => 'Error al aplicar la tarjeta de regalo: ' . $e->getMessage()]);
} 