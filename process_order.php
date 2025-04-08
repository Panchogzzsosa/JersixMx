<?php
// Desactivar la visualización de errores en la salida - solo registrar en logs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Importar archivo de envío de correos
require_once __DIR__ . '/order_confirmation_email.php';

// Control de buffer estricto para evitar cualquier salida antes del JSON
ob_start();

// Función para enviar respuesta JSON y terminar la ejecución
function sendJsonResponse($success, $message, $data = []) {
    // Terminar y limpiar todos los buffers de salida
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Establecer headers
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    
    // Preparar respuesta
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    // Agregar datos adicionales si existen
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    // Enviar JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

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
    
    // También escribir al error_log de PHP
    error_log($message);
}

// Registrar inicio de proceso
writeLog("=== NUEVA ORDEN ===");
writeLog("POST: " . print_r($_POST, true));

// Obtener datos del formulario
$data = $_POST;

// Obtener información de Gift Card si se aplicó alguna
$giftcardCode = isset($_POST['giftcard_code']) ? $_POST['giftcard_code'] : null;
$giftcardAmount = isset($_POST['giftcard_amount']) ? floatval($_POST['giftcard_amount']) : 0;
$isFullDiscount = isset($_POST['is_full_discount']) && $_POST['is_full_discount'] === 'true';

// Log de información de Gift Card
if ($giftcardCode) {
    writeLog("Gift Card aplicada: " . $giftcardCode . " por $" . $giftcardAmount);
    if ($isFullDiscount) {
        writeLog("*** La Gift Card cubre completamente el total del pedido ***");
    }
}

try {
    // Registrar inicio detallado del proceso
    writeLog("Iniciando procesamiento de orden con " . count($_POST) . " campos POST.");
    
    // Incluir archivo de configuración de la base de datos
    require_once __DIR__ . '/config/database.php';
    
    // Conectar a la base de datos
    try {
        $pdo = getConnection();
        writeLog("Conexión a base de datos establecida correctamente");
        
        // Verificar existencia de tablas necesarias
        $tablesRequired = ['orders', 'order_items', 'products'];
        $tablesExisting = [];
        
        $tablesQuery = $pdo->query("SHOW TABLES");
        while ($tableName = $tablesQuery->fetchColumn()) {
            $tablesExisting[] = $tableName;
        }
        
        $missingTables = array_diff($tablesRequired, $tablesExisting);
        if (!empty($missingTables)) {
            writeLog("ERROR CRÍTICO: Faltan tablas en la base de datos: " . implode(", ", $missingTables));
            throw new Exception("Error en la estructura de la base de datos. Por favor contacte al administrador.");
        }
        
        writeLog("Verificación de tablas completada. Todas las tablas requeridas existen.");
    } catch (PDOException $e) {
        writeLog("ERROR CRÍTICO: No se pudo conectar a la base de datos: " . $e->getMessage());
        throw new Exception("Error de conexión a la base de datos. Por favor intente más tarde.");
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    writeLog("Transacción iniciada");
    
    // Validar datos requeridos
    $requiredFields = ['fullname', 'email', 'phone', 'street', 'colonia', 'city', 'state', 'postal', 'payment_id', 'cart_items'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            writeLog("ERROR: Campo requerido faltante: {$field}");
            throw new Exception("Campo requerido faltante: {$field}");
        }
    }

    writeLog("Decodificando items del carrito...");
    
    $cartItemsJson = $data['cart_items'];
    
    try {
        // Limpiar caracteres problemáticos
        $cartItemsJson = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $cartItemsJson);
        
        // Intentar decodificar con manejo de errores mejorado
        $cartItems = json_decode($cartItemsJson, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonError = json_last_error_msg();
            writeLog("Error decodificando JSON: $jsonError");
            throw new Exception("Error al decodificar datos del carrito: $jsonError");
        }
        
        if (!is_array($cartItems) || empty($cartItems)) {
            writeLog("El carrito está vacío o no es un array válido");
            throw new Exception("El carrito está vacío o tiene un formato inválido");
        }
        
        writeLog("Carrito decodificado correctamente con " . count($cartItems) . " items");
    } catch (Exception $e) {
        writeLog("Error al procesar JSON del carrito: " . $e->getMessage());
        throw new Exception("Error al procesar datos del carrito: " . $e->getMessage());
    }

    // Sanitizar los datos de los items del carrito
    foreach ($cartItems as &$item) {
        // Sanitizar campos básicos pero sin eliminar entidades HTML
        if (isset($item['title'])) $item['title'] = trim($item['title']);
        if (isset($item['size'])) $item['size'] = trim($item['size']);
        
        // Sanitizar datos de gift card si existen
        if (isset($item['details'])) {
            if (isset($item['details']['recipientName'])) 
                $item['details']['recipientName'] = trim($item['details']['recipientName']);
            if (isset($item['details']['recipientEmail'])) 
                $item['details']['recipientEmail'] = filter_var($item['details']['recipientEmail'], FILTER_SANITIZE_EMAIL);
            if (isset($item['details']['message'])) 
                $item['details']['message'] = trim($item['details']['message']);
            if (isset($item['details']['senderName'])) 
                $item['details']['senderName'] = trim($item['details']['senderName']);

            // NUEVO: Verificar si tiene detalles de gift card y forzar la marca
            if (isset($item['details']['type']) && $item['details']['type'] === 'giftcard') {
                $item['isGiftCard'] = true;
                writeLog("FORZADO: Marcando item como gift card basado en details.type = giftcard");
            }
        }
        
        // NUEVO: Verificar el título para identificar tarjetas de regalo
        if (isset($item['title']) && stripos($item['title'], 'Tarjeta de Regalo') !== false) {
            $item['isGiftCard'] = true;
            writeLog("FORZADO: Marcando item como gift card basado en el título: " . $item['title']);
        }
    }
    
    writeLog("Items del carrito después de sanitización: " . print_r($cartItems, true));

    // TEST: Verificar la estructura de la tabla order_items antes de continuar
    try {
        $testPdo = new PDO('mysql:host=localhost;dbname=checkout', 'root', '');
        $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $testStmt = $testPdo->prepare('DESCRIBE order_items');
        $testStmt->execute();
        $tableStructure = $testStmt->fetchAll(PDO::FETCH_ASSOC);
        writeLog("Estructura de la tabla order_items: " . print_r($tableStructure, true));
        
        // Verificar si podemos insertar correctamente en una tabla de prueba
        $testPdo->exec("CREATE TEMPORARY TABLE temp_order_items LIKE order_items");
        $testInsert = $testPdo->prepare("
            INSERT INTO temp_order_items (
                order_id, 
                product_id, 
                quantity, 
                size, 
                price,
                subtotal,
                personalization_name, 
                personalization_number, 
                personalization_patch
            ) VALUES (1, 1, 1, 'TEST', 100.00, 100.00, NULL, NULL, NULL)
        ");
        $testInsert->execute();
        writeLog("TEST: Inserción en tabla temporal exitosa");
    } catch (PDOException $e) {
        writeLog("TEST ERROR: Error al verificar estructura de tabla: " . $e->getMessage());
    }

    // Verificar si podemos acceder a la tabla products
    try {
        $checkTable = $pdo->query("SELECT COUNT(*) as total FROM products");
        $result = $checkTable->fetch(PDO::FETCH_ASSOC);
        writeLog("Tabla products accesible. Total productos: " . $result['total']);
    } catch (PDOException $e) {
        writeLog("Error al acceder a la tabla products: " . $e->getMessage());
    }

    // Crear una tabla temporal para diagnosticar problemas
    writeLog("Creando tabla temporal para diagnóstico...");
    try {
        $pdo->exec("CREATE TEMPORARY TABLE IF NOT EXISTS temp_diagnostic AS SELECT * FROM products LIMIT 0");
        writeLog("Tabla temporal creada correctamente");
        
        // Insertar un producto de diagnóstico en la tabla temporal
        $tempInsert = $pdo->prepare("INSERT INTO temp_diagnostic SELECT * FROM products WHERE product_id = 66");
        $tempInsert->execute();
        writeLog("Datos copiados a tabla temporal");
        
        // Verificar si podemos leer de la tabla temporal
        $tempCheck = $pdo->query("SELECT * FROM temp_diagnostic");
        $tempProducts = $tempCheck->fetchAll(PDO::FETCH_ASSOC);
        writeLog("Productos en tabla temporal: " . print_r($tempProducts, true));
    } catch (PDOException $e) {
        writeLog("Error al crear/usar tabla temporal: " . $e->getMessage());
    }

    // Verificar si es un pago completo con Gift Card
    $paymentMethod = 'paypal'; // Valor predeterminado
    $paymentStatus = 'paid';   // Valor predeterminado
    
    // Si viene el parámetro payment_method y es giftcard, usarlo
    if (isset($data['payment_method']) && $data['payment_method'] === 'giftcard') {
        $paymentMethod = 'giftcard';
        $paymentStatus = 'paid';
        writeLog("Método de pago: Gift Card (Pago Completo)");
    } else if ($isFullDiscount) {
        // Si es un descuento completo pero procesado a través de PayPal (con 0.01)
        $paymentMethod = 'giftcard+paypal';
        writeLog("Método de pago: Gift Card + PayPal (nominal)");
    }

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
            :payment_method,
            :payment_id,
            :payment_status,
            NOW()
        )
    ");

    $orderData = [
        ':name' => $data['fullname'],
        ':email' => $data['email'],
        ':phone' => $data['phone'],
        ':street' => $data['street'],
        ':colony' => $data['colonia'],
        ':city' => $data['city'],
        ':state' => $data['state'],
        ':zip' => $data['postal'],
        ':payment_method' => $paymentMethod,
        ':payment_id' => $data['payment_id'],
        ':payment_status' => $paymentStatus
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
            size,
            price,
            subtotal,
            personalization_name,
            personalization_number,
            personalization_patch
        ) VALUES (
            :order_id,
            :product_id,
            :quantity,
            :size,
            :price,
            :subtotal,
            :personalization_name,
            :personalization_number,
            :personalization_patch
        )
    ");

    foreach ($cartItems as $item) {
        $productId = null;
        
        // Registro detallado para depuración
        writeLog("Procesando item: " . print_r($item, true));
        
        // Verificar si es una gift card
        if (isset($item['isGiftCard']) && $item['isGiftCard']) {
            writeLog("Usando directamente el producto ID 66 para la gift card");
            $productId = 66;
        } else if (isset($item['title']) && (
            stripos($item['title'], 'Tarjeta de Regalo') !== false || 
            stripos($item['title'], 'Gift Card') !== false
        )) {
            writeLog("DETECTADA: Gift card por título: " . $item['title']);
            $productId = 66; // ID del producto de gift card
            
            // Importante: para gift cards, no verificamos el precio ya que el monto es variable
            writeLog("No se verifica el precio para gift card, usando el valor proporcionado: " . $item['price']);
        } else {
            // Para productos normales, buscamos por nombre exacto primero
            $productStmt = $pdo->prepare("SELECT product_id, price FROM products WHERE name = ?");
            $productStmt->execute([$item['title']]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            // Si no encuentra, busca con LIKE (más flexible)
            if (!$product) {
                writeLog("Producto no encontrado con nombre exacto: " . $item['title'] . ". Intentando búsqueda con LIKE.");
                $productStmt = $pdo->prepare("SELECT product_id, price, name FROM products WHERE name LIKE ?");
                $productStmt->execute(['%' . $item['title'] . '%']);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    writeLog("Producto encontrado con LIKE: " . $product['name']);
                }
            }
            
            if (!$product) {
                writeLog("Producto no encontrado después de múltiples intentos: " . $item['title']);
                throw new Exception('Producto no encontrado: ' . $item['title']);
            }
            
            // Verificar que el precio coincida con el de la base de datos para productos normales
            $productId = $product['product_id'];
            
            // Verificar si el precio ha cambiado significativamente (tolerancia de 0.01)
            $priceDifference = abs($product['price'] - $item['price']);
            if ($priceDifference > 0.01) {
                writeLog("Advertencia: El precio del producto ha cambiado. Base de datos: " . $product['price'] . ", Carrito: " . $item['price']);
                // Usar el precio de la base de datos para asegurar precisión
                $item['price'] = $product['price'];
            }
            
            writeLog("Usando producto ID " . $productId . " para " . $item['title']);
        }

        // Procesar información de personalización
        $personalization = isset($item['personalization']) ? $item['personalization'] : null;
        
        // Para gift cards, usamos los detalles del item
        $personalizationName = null;
        $personalizationNumber = null;
        $personalizationPatch = null;

        if (isset($item['isGiftCard']) && $item['isGiftCard'] && isset($item['details'])) {
            // Sanitizar datos
            $recipientEmail = filter_var($item['details']['recipientEmail'] ?? '', FILTER_SANITIZE_EMAIL);
            $recipientName = trim($item['details']['recipientName'] ?? '');
            $message = trim($item['details']['message'] ?? '');
            $senderName = trim($item['details']['senderName'] ?? '');
            
            $personalizationName = $recipientEmail;
            $personalizationNumber = 'GC-' . substr(md5($item['id']), 0, 7);
            
            // Crear una cadena simple con toda la información codificada en base64
            $giftcardInfo = 'RCP:' . base64_encode($recipientName) . 
                            '|MSG:' . base64_encode($message) . 
                            '|SND:' . base64_encode($senderName);
                            
            // Uso directo de texto en lugar de JSON
            $personalizationPatch = $giftcardInfo;
            
            writeLog("Detalles de gift card (texto plano): " . 
                    "\nPara: " . $recipientName . 
                    "\nEmail: " . $recipientEmail . 
                    "\nMensaje: " . $message . 
                    "\nDe: " . $senderName);
        } elseif ($personalization) {
            $personalizationName = $personalization['name'] ?? null;
            $personalizationNumber = $personalization['number'] ?? null;
            
            // Para jerseys con parche
            $personalizationPatch = ($personalization && isset($personalization['patch'])) 
                ? ($personalization['patch'] ? "1" : "0") 
                : "0";
        }
        
        // Código específico para Mystery Box - Ahora fuera del condicional de personalización
        if ($item['title'] === 'Mystery Box' && isset($item['tipo'])) {
            // Código para Mystery Box
            $tipoNombre = '';
            if ($item['tipo'] === 'champions') {
                $tipoNombre = 'Champions League';
            } elseif ($item['tipo'] === 'ligamx') {
                $tipoNombre = 'Liga MX';
            } else {
                $tipoNombre = 'Liga Europea';
            }
            
            // Guardar con formato "TIPO:" seguido del valor codificado
            $personalizationPatch = 'TIPO:' . base64_encode($tipoNombre);
            
            writeLog("Mystery Box detectada con tipo: " . $tipoNombre . " (codificado como: " . $personalizationPatch . ")");
        }
        
        // Calcular subtotal
        $subtotal = $item['price'] * $item['quantity'];
        
        $itemData = [
            ':order_id' => $orderId,
            ':product_id' => $productId,
            ':quantity' => $item['quantity'],
            ':size' => isset($item['isGiftCard']) && $item['isGiftCard'] ? 'N/A' : ($item['size'] ?? 'N/A'),
            ':price' => $item['price'],
            ':subtotal' => $subtotal,
            ':personalization_name' => $personalizationName,
            ':personalization_number' => $personalizationNumber,
            ':personalization_patch' => $personalizationPatch
        ];
        
        writeLog("Insertando item con personalización: " . print_r($itemData, true));
        $stmt->execute($itemData);
    }

    // Actualizar estadísticas de ventas
    $total = array_reduce($cartItems, function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);

    // Si se aplicó un descuento con gift card, restar del total para las estadísticas
    if ($giftcardCode && $giftcardAmount > 0) {
        $total = max(0, $total - $giftcardAmount);
        writeLog("Ajustando total para estadísticas por descuento de Gift Card: {$giftcardAmount}. Total ajustado: {$total}");
    }

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

    // Cargar funciones de gift card pero NO enviar automáticamente
    require_once 'send_giftcard_email.php';
    
    // Buscar gift cards en la orden actual
    $giftcardStmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ? AND (p.product_id = 66 OR p.name LIKE '%Tarjeta de Regalo%' OR p.name LIKE '%Gift Card%')
    ");
    $giftcardStmt->execute([$orderId]);
    $giftcards = $giftcardStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Registrar la presencia de gift cards, pero NO enviarlas automáticamente
    if ($giftcards) {
        writeLog("Se encontraron " . count($giftcards) . " gift cards en la orden #" . $orderId . ". Pendientes de envío manual desde el panel de administración.");
        
        // Asegurarse de que la columna giftcard_sent existe en la tabla order_items
        try {
            $columnExists = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'giftcard_sent'")->rowCount() > 0;
            
            if (!$columnExists) {
                $pdo->exec("ALTER TABLE order_items ADD COLUMN giftcard_sent TINYINT(1) DEFAULT 0");
                writeLog("Columna giftcard_sent añadida a la tabla order_items");
            }
            
            // Marcar las gift cards como NO enviadas (0) para que aparezcan en el panel
            foreach ($giftcards as $giftcard) {
                $markStmt = $pdo->prepare("
                    UPDATE order_items 
                    SET giftcard_sent = 0 
                    WHERE order_item_id = ?
                ");
                $markStmt->execute([$giftcard['order_item_id']]);
                writeLog("Gift card marcada como pendiente de envío: " . $giftcard['personalization_name']);
            }
        } catch (Exception $e) {
            writeLog("ERROR al preparar la tabla para gift cards: " . $e->getMessage());
        }
    }

    // Procesar redención de Gift Card si se aplicó alguna
    if ($giftcardCode && $giftcardAmount > 0) {
        writeLog("Procesando redención de Gift Card: " . $giftcardCode . " por $" . $giftcardAmount);
        
        try {
            // Verificar que exista la tabla de redenciones
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
                SELECT oi.*, o.status, gc.redeemed, gc.balance, gc.original_amount
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                LEFT JOIN giftcard_redemptions gc ON oi.personalization_number = gc.code
                JOIN products p ON oi.product_id = p.product_id
                WHERE 
                    oi.personalization_number = ? 
                    AND (p.name LIKE '%Tarjeta de Regalo%' OR p.product_id = 66 OR p.name LIKE '%Gift Card%')
            ");
            
            $stmt->execute([$giftcardCode]);
            $giftcard = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$giftcard) {
                writeLog("ERROR: Gift Card no encontrada: " . $giftcardCode);
                throw new Exception("La tarjeta de regalo no es válida");
            }
            
            // Verificar si ya existe en la tabla de redenciones
            $redemptionExists = $pdo->prepare("SELECT * FROM giftcard_redemptions WHERE code = ?");
            $redemptionExists->execute([$giftcardCode]);
            $redemption = $redemptionExists->fetch(PDO::FETCH_ASSOC);
            
            if (!$redemption) {
                // Crear registro de redención
                $originalAmount = $giftcard['price'];
                $newBalance = $originalAmount - $giftcardAmount;
                $redeemed = ($newBalance <= 0 || $isFullDiscount) ? 1 : 0;
                
                $insertStmt = $pdo->prepare("
                    INSERT INTO giftcard_redemptions (code, original_amount, balance, redeemed)
                    VALUES (?, ?, ?, ?)
                ");
                $insertStmt->execute([$giftcardCode, $originalAmount, $newBalance, $redeemed]);
                
                writeLog("Nuevo registro de Gift Card creado: " . $giftcardCode . " - Saldo: $" . $newBalance);
            } else {
                // Actualizar saldo
                $newBalance = $redemption['balance'] - $giftcardAmount;
                $redeemed = ($newBalance <= 0 || $isFullDiscount) ? 1 : 0;
                
                $updateStmt = $pdo->prepare("
                    UPDATE giftcard_redemptions
                    SET balance = ?, redeemed = ?
                    WHERE code = ?
                ");
                $updateStmt->execute([$newBalance, $redeemed, $giftcardCode]);
                
                writeLog("Saldo de Gift Card actualizado: " . $giftcardCode . " - Nuevo saldo: $" . $newBalance);
            }
            
            // Registrar la transacción
            $transStmt = $pdo->prepare("
                INSERT INTO giftcard_transactions (code, order_id, amount)
                VALUES (?, ?, ?)
            ");
            $transStmt->execute([$giftcardCode, $orderId, $giftcardAmount]);
            
            writeLog("Transacción de Gift Card registrada: " . $giftcardCode . " - Monto: $" . $giftcardAmount);
            
            // Actualizar notas del pedido
            $paymentNotes = "Gift Card aplicada: " . $giftcardCode . " - Monto: $" . $giftcardAmount;
            if ($isFullDiscount) {
                $paymentNotes .= " (Pago completo con Gift Card)";
            }
            
            $notesStmt = $pdo->prepare("
                UPDATE orders
                SET payment_notes = CONCAT(IFNULL(payment_notes, ''), ?)
                WHERE order_id = ?
            ");
            $notesStmt->execute([$paymentNotes, $orderId]);
            
            writeLog("Notas de pago actualizadas para el pedido #" . $orderId);
            
        } catch (Exception $e) {
            writeLog("ERROR al procesar Gift Card: " . $e->getMessage());
            // Continuamos con el proceso de pedido aunque haya error en la Gift Card
        }
    }

    // Obtener los items de la orden para incluirlos en el correo
    $itemsStmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.image_url as product_image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Asegurar que cada item tenga un título e imagen
    foreach ($orderItems as &$item) {
        // Si no hay título específico, usar el nombre del producto
        if (empty($item['title'])) {
            $item['title'] = $item['product_name'];
        }
        
        // Si no hay imagen específica, usar la imagen del producto
        if (empty($item['image'])) {
            $item['image'] = $item['product_image'];
        }
        
        // Si la imagen no tiene URL absoluta, añadir el dominio
        if (!empty($item['image']) && strpos($item['image'], 'http') !== 0) {
            // Asegurarse de que la URL sea absoluta
            $item['image'] = 'https://jersix.mx/' . ltrim($item['image'], '/');
        }
        
        // Si aún no hay imagen, usar la imagen por defecto
        if (empty($item['image'])) {
            $item['image'] = 'https://jersix.mx/img/ICON.png';
        }
    }
    unset($item); // Romper la referencia
    
    // Obtener datos completos de la orden
    $orderDataStmt = $pdo->prepare("
        SELECT * FROM orders WHERE order_id = ?
    ");
    $orderDataStmt->execute([$orderId]);
    $completeOrderData = $orderDataStmt->fetch(PDO::FETCH_ASSOC);
    
    // Añadir el email a los datos de la orden (si no está)
    if (!isset($completeOrderData['customer_email'])) {
        $completeOrderData['customer_email'] = $data['email'];
    }
    
    // Enviar correo de confirmación
    $emailSent = sendOrderConfirmationEmail($completeOrderData, $orderItems);
    writeLog("Correo de confirmación " . ($emailSent ? "enviado" : "FALLIDO") . " para el pedido #" . $orderId);
    
    sendJsonResponse(true, 'Orden procesada correctamente', ['order_id' => $orderId]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        writeLog("Rollback de la transacción");
    }
    
    writeLog("ERROR: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    
    // Enviar respuesta de error
    sendJsonResponse(false, 'Error al procesar la orden: ' . $e->getMessage());
}

// Asegurarse de que no haya más salida
exit;