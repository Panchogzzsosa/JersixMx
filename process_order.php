<?php
// Desactivar la visualización de errores en la salida - solo registrar en logs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configurar el manejador de errores personalizado
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $message = date('Y-m-d H:i:s') . " [Error $errno] $errstr en $errfile:$errline\n";
    error_log($message, 3, __DIR__ . '/logs/order_debug.log');
    return true;
});

// Configurar el manejador de excepciones personalizado
set_exception_handler(function($e) {
    $message = date('Y-m-d H:i:s') . " [Excepción] " . $e->getMessage() . "\n";
    $message .= "Stack trace:\n" . $e->getTraceAsString() . "\n";
    error_log($message, 3, __DIR__ . '/logs/order_debug.log');
});

// Incluir la clase Mailer
require_once __DIR__ . '/includes/Mailer.php';

// Control de buffer estricto para evitar cualquier salida antes del JSON
ob_start();

// Función para enviar respuesta JSON y terminar la ejecución
function sendJsonResponse($success, $message, $data = []) {
    // Terminar y limpiar todos los buffers de salida
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Si es una respuesta exitosa y hay un ID de orden, redirigir a success.html
    if ($success && isset($data['order_id'])) {
        $order_id = $data['order_id'];
        
        // Registrar en el log antes de redirigir
        writeLog("Redirección a success.html con order_id: {$order_id}");
        
        // Asegurar que no hay errores previos en el buffer
        if (ob_get_length()) ob_clean();
        
        // Establecer encabezados para evitar caché y redirigir
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Location: success.html?order_id=' . $order_id);
        exit;
    }
    
    // Para otros casos, continuar con la respuesta JSON normal
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
    
    // Intentar codificar la respuesta como JSON con mejor manejo de errores
    try {
        $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Verificar si hubo errores en la codificación JSON
        if ($jsonResponse === false) {
            // Registrar error JSON en el log
            $jsonError = json_last_error_msg();
            writeLog("Error en JSON: $jsonError - Enviando respuesta simplificada");
            
            // Preparar una respuesta simplificada para asegurar que es válida
            $simpleResponse = json_encode([
                'success' => $success,
                'message' => 'Error en el procesamiento de datos. Por favor contactar a soporte.'
            ]);
            
            if ($simpleResponse === false) {
                // Si incluso la respuesta simple falla, enviar una respuesta fija
                echo '{"success":false,"message":"Error interno del servidor"}';
            } else {
                echo $simpleResponse;
            }
        } else {
            // Enviar la respuesta JSON normal
            echo $jsonResponse;
        }
    } catch (Exception $e) {
        // En caso de cualquier excepción, asegurar alguna respuesta JSON válida
        writeLog("Excepción al generar JSON: " . $e->getMessage());
        echo '{"success":false,"message":"Error al procesar la respuesta"}';
    }
    
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
    $requiredFields = ['fullname', 'email', 'phone', 'street', 'colonia', 'city', 'state', 'postal', 'payment_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            writeLog("ERROR: Campo requerido faltante: {$field}");
            throw new Exception("Campo requerido faltante: {$field}");
        }
    }

    // Verificar si hay código promocional y modificar el payment_id para incluir esta información
    $originalPaymentId = $data['payment_id'];
    $compositePaymentId = $originalPaymentId;
    
    if (isset($_POST['promo_code']) && !empty($_POST['promo_code'])) {
        $promoCode = $_POST['promo_code'];
        $promoDiscount = floatval($_POST['promo_discount'] ?? 0);
        // Incluir el código promocional y el descuento en el payment_id
        $compositePaymentId = $originalPaymentId . " [PROMO:" . $promoCode . ":$" . number_format($promoDiscount, 2) . "]";
        writeLog("Payment ID modificado para incluir código promocional: " . $compositePaymentId);
    }
    
    // Verificar si los datos del carrito están en "cart_items" o en "cart"
    if (isset($data['cart_items']) && !empty($data['cart_items'])) {
        $cartItemsJson = $data['cart_items'];
        writeLog("Usando datos del carrito desde 'cart_items'");
    } elseif (isset($data['cart']) && !empty($data['cart'])) {
        $cartItemsJson = $data['cart'];
        writeLog("Usando datos del carrito desde 'cart'");
    } else {
        writeLog("ERROR: No se encontraron datos del carrito en 'cart_items' ni en 'cart'");
        throw new Exception("Datos del carrito no encontrados");
    }

    writeLog("Decodificando items del carrito...");
    
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

    // Crear la orden primero
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
        ':payment_id' => $compositePaymentId,
        ':payment_status' => $paymentStatus
    ];

    writeLog("Insertando orden con datos: " . print_r($orderData, true));
    $stmt->execute($orderData);
    $orderId = $pdo->lastInsertId();
    writeLog("Orden creada con ID: $orderId");

    // Preparar la sentencia para insertar items
    $stmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            cart_item_id,
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
            :cart_item_id,
            :quantity,
            :size,
            :price,
            :subtotal,
            :personalization_name,
            :personalization_number,
            :personalization_patch
        )
    ");

    // Verificar que no haya duplicados en los items del carrito
    $processedIds = [];
    
    // Asegurar que $cartItems sea un array y tenga los índices correctos
    $cartItemsArray = json_decode(json_encode($cartItems), true);
    
    foreach ($cartItemsArray as $index => $item) {
        writeLog("=== PROCESANDO NUEVO ITEM DEL CARRITO ===");
        writeLog("Índice del item: " . $index);
        writeLog("Total de items en el carrito: " . count($cartItemsArray));
        
        if (empty($item['id']) || empty($item['title'])) {
            writeLog("ADVERTENCIA: Item inválido encontrado, saltando...");
            continue;
        }

        writeLog("Datos originales del item: " . print_r($item, true));
        writeLog("ID del item en el carrito: " . ($item['id'] ?? 'No disponible'));
        writeLog("Product ID: " . ($item['product_id'] ?? 'No disponible'));
        writeLog("Título: " . ($item['title'] ?? 'No disponible'));
        writeLog("Talla: " . ($item['size'] ?? 'No disponible'));

        // Verificar si este item ya fue procesado usando una combinación única de id y título y talla
        $itemKey = $item['id'] . '-' . $item['title'] . '-' . $item['size'];
        if (isset($processedIds[$itemKey])) {
            writeLog("ADVERTENCIA: Item ya procesado, saltando duplicado. ID: " . $item['id'] . ", Título: " . $item['title'] . ", Talla: " . $item['size']);
            continue;
        }
        $processedIds[$itemKey] = true;

        $productId = null;
        
        // Registro detallado para depuración
        writeLog("Procesando item: " . print_r($item, true));
        
        // Verificar si es una gift card
        if (isset($item['isGiftCard']) && $item['isGiftCard']) {
            writeLog("Usando directamente el producto ID 66 para la gift card");
            $productId = 66;
            
            // Para gift cards, usar el precio real guardado en realPrice si está disponible
            if (isset($item['realPrice']) && $item['realPrice'] > 0) {
                writeLog("Usando precio real de gift card: " . $item['realPrice']);
                $item['price'] = $item['realPrice'];
            } else if ($item['price'] == 0 && preg_match('/\$(\d+)/', $item['title'], $matches)) {
                // Si el precio es 0, intentar extraerlo del título (formato: "Tarjeta de Regalo JerSix $XXX MXN")
                $extractedPrice = intval($matches[1]);
                if ($extractedPrice > 0) {
                    writeLog("Extrayendo precio de gift card del título: " . $extractedPrice);
                    $item['price'] = $extractedPrice;
                }
            }
        } else if (isset($item['title']) && (
            stripos($item['title'], 'Tarjeta de Regalo') !== false || 
            stripos($item['title'], 'Gift Card') !== false
        )) {
            writeLog("DETECTADA: Gift card por título: " . $item['title']);
            $productId = 66; // ID del producto de gift card
            
            // Importante: para gift cards, no verificamos el precio ya que el monto es variable
            writeLog("No se verifica el precio para gift card, usando el valor proporcionado: " . $item['price']);
            
            // Si el precio es 0, intentar extraerlo del título
            if ($item['price'] == 0 && preg_match('/\$(\d+)/', $item['title'], $matches)) {
                $extractedPrice = intval($matches[1]);
                if ($extractedPrice > 0) {
                    writeLog("Extrayendo precio de gift card del título: " . $extractedPrice);
                    $item['price'] = $extractedPrice;
                }
            }
        } else {
            // Usar el product_id directamente del item del carrito
            $productId = intval($item['product_id']);
            
            // Buscar el producto por ID
            $productStmt = $pdo->prepare("SELECT product_id, price, name FROM products WHERE product_id = ?");
            $productStmt->execute([$productId]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            // Si no se encuentra por ID, intentar buscar por nombre
            if (!$product && isset($item['title'])) {
                writeLog("Producto no encontrado con ID: " . $productId . ", intentando buscar por nombre: " . $item['title']);
                
                // Intentar diferentes variaciones del nombre
                $productName = $item['title'];
                $productStmt = $pdo->prepare("SELECT product_id, price, name FROM products WHERE name = ?");
                $productStmt->execute([$productName]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                
                // Si no se encuentra, intentar con una versión simplificada del nombre
                if (!$product) {
                    // Extraer el nombre del equipo y la temporada
                    if (preg_match('/^([^0-9]+)\s+([0-9\/]+)$/', $productName, $matches)) {
                        $teamName = trim($matches[1]);
                        $season = trim($matches[2]);
                        
                        // Buscar por nombre del equipo
                        $productStmt = $pdo->prepare("SELECT product_id, price, name FROM products WHERE name LIKE ?");
                        $productStmt->execute([$teamName . '%']);
                        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                        
                        writeLog("Buscando por nombre del equipo: " . $teamName . ", resultado: " . ($product ? "encontrado" : "no encontrado"));
                    }
                }
                
                // Si aún no se encuentra, crear el producto en la base de datos
                if (!$product) {
                    writeLog("Producto no encontrado en la base de datos, creando nuevo registro: " . $productName);
                    
                    // Insertar el nuevo producto
                    $insertStmt = $pdo->prepare("INSERT INTO products (name, price, image_url) VALUES (?, ?, ?)");
                    $imageUrl = isset($item['image']) ? $item['image'] : '';
                    $insertStmt->execute([$productName, $item['price'], $imageUrl]);
                    
                    // Obtener el ID del nuevo producto
                    $productId = $pdo->lastInsertId();
                    
                    // Obtener el producto recién creado
                    $productStmt = $pdo->prepare("SELECT product_id, price, name FROM products WHERE product_id = ?");
                    $productStmt->execute([$productId]);
                    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                    
                    writeLog("Nuevo producto creado con ID: " . $productId);
                } else {
                    writeLog("Producto encontrado por nombre. ID: " . $product['product_id']);
                    $productId = $product['product_id'];
                }
            }
            
            if (!$product) {
                writeLog("Producto no encontrado con ID: " . $productId . " ni por nombre: " . $item['title']);
                throw new Exception('Producto no encontrado: ' . $item['title']);
            }
            
            // Verificar que el precio coincida con el de la base de datos
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
        if ($item['title'] === 'Mystery Box' && (isset($item['tipo']) || isset($item['mysteryBoxType']))) {
            // Código para Mystery Box
            $tipoNombre = '';
            $tipo = $item['tipo'] ?? $item['mysteryBoxType'] ?? '';
            
            if ($tipo === 'champions') {
                $tipoNombre = 'Champions League';
            } elseif ($tipo === 'ligamx') {
                $tipoNombre = 'Liga MX';
            } elseif ($tipo === 'Retro') {
                $tipoNombre = 'Retro';
            } elseif ($tipo === 'Europa') {
                $tipoNombre = 'Liga Europea';
            }
            else{
                $tipoNombre = 'ERROR';
            }
            
            // Guardar con formato "TIPO:" seguido del valor codificado
            $personalizationPatch = 'TIPO:' . base64_encode($tipoNombre);
            
            writeLog("Mystery Box detectada con tipo: " . $tipoNombre . " (codificado como: " . $personalizationPatch . ")");
        }
        
        // Calcular subtotal
        $subtotal = $item['price'] * $item['quantity'];
        
        // NUEVO: Calcular precio ajustado para jerseys con personalización
        if (!isset($item['isGiftCard']) && 
            $productId !== 66 && 
            $item['title'] !== 'Mystery Box' &&
            (stripos($item['title'], 'jersey') !== false || stripos($item['title'], 'camiseta') !== false)) {
            
            // Verificar si tiene personalización (nombre o número)
            $tienePersonalizacion = !empty($personalizationName) || !empty($personalizationNumber);
            
            // Verificar si tiene parche
            $tieneParche = !empty($personalizationPatch) && $personalizationPatch !== "0";
            
            // Calcular precio adicional
            $precioAdicional = 0;
            if ($tienePersonalizacion) {
                $precioAdicional += 100; // +$100 por personalización
                writeLog("Añadiendo $100 al precio por personalización: " . $item['title']);
            }
            
            if ($tieneParche) {
                $precioAdicional += 50; // +$50 por parche
                writeLog("Añadiendo $50 al precio por parche: " . $item['title']);
            }
            
            // Si hay un precio adicional, actualizar el precio y subtotal
            if ($precioAdicional > 0) {
                $precioOriginal = $item['price'];
                $item['price'] += $precioAdicional;
                $subtotal = $item['price'] * $item['quantity'];
                
                writeLog("Precio ajustado por personalizaciones: $precioOriginal → " . $item['price'] . 
                         " (Personalización: " . ($tienePersonalizacion ? "Sí" : "No") . 
                         ", Parche: " . ($tieneParche ? "Sí" : "No") . ")");
            }
        }
        
        $itemData = [
            ':order_id' => $orderId,
            ':product_id' => isset($item['isGiftCard']) && $item['isGiftCard'] ? 66 : $productId,
            ':quantity' => $item['quantity'],
            ':size' => isset($item['isGiftCard']) && $item['isGiftCard'] ? 'N/A' : ($item['size'] ?? 'N/A'),
            ':price' => $item['price'],
            ':subtotal' => $subtotal,
            ':personalization_name' => $personalizationName,
            ':personalization_number' => $personalizationNumber,
            ':personalization_patch' => $personalizationPatch,
            ':cart_item_id' => isset($item['id']) && !empty($item['id']) ? 
                $item['id'] : 
                $orderId . '-' . $productId . '-' . uniqid()
        ];

        writeLog("Datos preparados para inserción: " . print_r($itemData, true));
        
        // Verificar que el product_id sea válido antes de insertar
        if (!isset($item['isGiftCard']) || !$item['isGiftCard']) {
            if ($productId <= 0) {
                writeLog("Error: product_id inválido para item no-gift card: " . $productId);
                throw new Exception('Error: ID de producto inválido para ' . $item['title']);
            }
        }
        
        try {
            $stmt->execute($itemData);
            writeLog("✅ Item insertado exitosamente en la base de datos");
            writeLog("Order ID: " . $orderId);
            writeLog("Product ID: " . $itemData[':product_id']);
            writeLog("Cart Item ID: " . $itemData[':cart_item_id']);
        } catch (Exception $e) {
            writeLog("❌ Error al insertar item: " . $e->getMessage());
            throw $e;
        }
        
        writeLog("=== FIN PROCESAMIENTO DE ITEM ===\n");
    }

    // Actualizar estadísticas de ventas
    $total = array_reduce($cartItems, function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);

    // Verificar si hay descuento de giftcard
    $paymentNotes = '';
    if ($giftcardCode) {
        $paymentNotes = "Gift Card aplicada: $giftcardCode - Monto: $" . number_format($giftcardAmount, 2);
        
        // Actualizar las notas de pago en la tabla de órdenes
        $updateNotesStmt = $pdo->prepare("UPDATE orders SET payment_notes = ? WHERE order_id = ?");
        $updateNotesStmt->execute([$paymentNotes, $orderId]);
        writeLog("Notas de pago actualizadas con información de Gift Card: $paymentNotes");
        
        // ACTUALIZAR EL SALDO DE LA GIFT CARD AL REALIZAR LA COMPRA
        try {
            // Verificar si existe la tabla giftcard_redemptions
            $gcTableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_redemptions'")->rowCount() > 0;
            
            if ($gcTableExists) {
                // Obtener información actual de la gift card
                $gcStmt = $pdo->prepare("
                    SELECT balance, redeemed FROM giftcard_redemptions 
                    WHERE code = ?
                ");
                $gcStmt->execute([$giftcardCode]);
                $giftcardInfo = $gcStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($giftcardInfo) {
                    // Calcular nuevo saldo
                    $newBalance = max(0, $giftcardInfo['balance'] - $giftcardAmount);
                    $redeemed = ($newBalance <= 0) ? 1 : 0;
                    
                    // Actualizar el saldo
                    $updateGcStmt = $pdo->prepare("
                        UPDATE giftcard_redemptions
                        SET balance = ?, redeemed = ?, updated_at = NOW()
                        WHERE code = ?
                    ");
                    $updateResult = $updateGcStmt->execute([$newBalance, $redeemed, $giftcardCode]);
                    
                    if ($updateResult) {
                        writeLog("Saldo de Gift Card $giftcardCode actualizado: $" . $giftcardInfo['balance'] . " -> $" . $newBalance);
                        
                        // Registrar la transacción
                        $transTableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_transactions'")->rowCount() > 0;
                        
                        if ($transTableExists) {
                            $transStmt = $pdo->prepare("
                                INSERT INTO giftcard_transactions (code, order_id, amount, transaction_date)
                                VALUES (?, ?, ?, NOW())
                            ");
                            $transResult = $transStmt->execute([$giftcardCode, $orderId, $giftcardAmount]);
                            
                            if ($transResult) {
                                writeLog("Transacción de Gift Card registrada: ID " . $pdo->lastInsertId());
                            } else {
                                writeLog("ADVERTENCIA: No se pudo registrar la transacción de Gift Card");
                            }
                        }
                    } else {
                        writeLog("ERROR: No se pudo actualizar el saldo de la Gift Card $giftcardCode");
                    }
                } else {
                    writeLog("ADVERTENCIA: La Gift Card $giftcardCode no se encontró en la tabla giftcard_redemptions");
                }
            } else {
                writeLog("ADVERTENCIA: La tabla giftcard_redemptions no existe");
            }
        } catch (Exception $e) {
            writeLog("ERROR al actualizar saldo de Gift Card: " . $e->getMessage());
            // No interrumpir el proceso de orden si falla la actualización de la gift card
        }
        
        // Si se aplicó un descuento con gift card, restar del total para las estadísticas
        $total = max(0, $total - $giftcardAmount);
        writeLog("Ajustando total para estadísticas por descuento de Gift Card: {$giftcardAmount}. Total ajustado: {$total}");
    }

    // Verificar si hay 2 jerseys y la promoción automática está activa
    $jerseyCount = 0;
    $jerseyTotal = 0;
    foreach ($cartItems as $item) {
        // Acepta precios como string o float, y permite pequeñas variaciones por redondeo
        if (abs(floatval($item['price']) - 799.00) < 0.05) {
            $jerseyCount += $item['quantity'];
            $jerseyTotal += (floatval($item['price']) * $item['quantity']);
        }
    }

    // Verificar si la promoción automática está activa
    $autoPromoStmt = $pdo->prepare("SELECT estado FROM codigos_promocionales WHERE codigo = 'AUTO2XJERSEY' AND estado = 'activo'");
    $autoPromoStmt->execute();
    $autoPromo = $autoPromoStmt->fetch(PDO::FETCH_ASSOC);

    if ($jerseyCount == 2 && $autoPromo) {
        $promo_discount = 598.00; // 1598 - 1000
        $total = max(0, $total - $promo_discount);
        
        // Agregar nota sobre la promoción automática
        $paymentNotes = $paymentNotes ? $paymentNotes . " | " : "";
        $paymentNotes .= "Promoción automática 2x1 aplicada - Descuento: $" . number_format($promo_discount, 2);
        
        writeLog("Promoción automática 2x1 aplicada: Descuento de $" . $promo_discount);
    }

    // Actualizar las notas de pago en la tabla de órdenes
    if ($paymentNotes) {
        $updateNotesStmt = $pdo->prepare("UPDATE orders SET payment_notes = ? WHERE order_id = ?");
        $updateNotesStmt->execute([$paymentNotes, $orderId]);
        writeLog("Notas de pago actualizadas: $paymentNotes");
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
    
    // Asegurar que el email esté presente y sea válido
    if (empty($completeOrderData['customer_email']) && !empty($data['email'])) {
        $completeOrderData['customer_email'] = $data['email'];
        
        // Actualizar en la base de datos si no estaba guardado
        $updateEmailStmt = $pdo->prepare("
            UPDATE orders 
            SET customer_email = ? 
            WHERE order_id = ? AND (customer_email IS NULL OR customer_email = '')
        ");
        $updateEmailStmt->execute([$data['email'], $orderId]);
        writeLog("Email del cliente actualizado en la orden: " . $data['email']);
    }
    
    // Verificar que tengamos un email válido
    if (empty($completeOrderData['customer_email'])) {
        writeLog("ADVERTENCIA: No se encontró email del cliente para el pedido #" . $orderId);
    } elseif (!filter_var($completeOrderData['customer_email'], FILTER_VALIDATE_EMAIL)) {
        writeLog("ADVERTENCIA: El email del cliente no es válido: " . $completeOrderData['customer_email']);
    } else {
        writeLog("Email del cliente validado correctamente: " . $completeOrderData['customer_email']);
    }
    
    // Después de procesar la orden exitosamente, enviar el correo de confirmación
    if (!empty($completeOrderData['customer_email']) && filter_var($completeOrderData['customer_email'], FILTER_VALIDATE_EMAIL)) {
        try {
            $mailer = new Mailer();
            $emailSent = $mailer->sendOrderConfirmation($completeOrderData, $orderItems);
            writeLog("Correo de confirmación " . ($emailSent ? "enviado" : "FALLIDO") . " para el pedido #" . $orderId);
            
            // Enviar notificación al administrador sobre la nueva compra
            $notificationSent = $mailer->sendAdminNotification($completeOrderData, $orderItems);
            writeLog("Notificación al administrador " . ($notificationSent ? "enviada" : "FALLIDA") . " para el pedido #" . $orderId);
        } catch (Exception $e) {
            writeLog("ERROR en envío de correo: " . $e->getMessage());
        }
    } else {
        writeLog("ERROR: No se pudo enviar el correo de confirmación porque el email no es válido o está vacío");
    }
    
    // NUEVO: Procesar el envío de gift cards si existen en el pedido
    try {
        $giftCardItems = [];
        
        // Identificar los items que son gift cards
        foreach ($orderItems as $item) {
            if (isset($item['isGiftCard']) && $item['isGiftCard'] && !empty($item['personalization_name'])) {
                $giftCardItems[] = $item;
                writeLog("Gift card detectada para envío: " . $item['personalization_name']);
            }
        }
        
        // Si hay gift cards, incluir el archivo necesario y enviarlas
        if (!empty($giftCardItems)) {
            writeLog("Se encontraron " . count($giftCardItems) . " gift cards para enviar");
            
            // Verificar que exista el archivo para enviar gift cards
            if (file_exists(__DIR__ . '/send_giftcard_email.php')) {
                require_once __DIR__ . '/send_giftcard_email.php';
                
                // Enviar cada gift card
                foreach ($giftCardItems as $giftCard) {
                    $result = sendGiftCardEmail($completeOrderData, $giftCard);
                    writeLog("Envío de gift card a " . $giftCard['personalization_name'] . ": " . ($result ? "EXITOSO" : "FALLIDO"));
                    
                    // Si el envío fue exitoso, marcarla como enviada en la base de datos
                    if ($result) {
                        try {
                            $updateStmt = $pdo->prepare("
                                UPDATE order_items 
                                SET giftcard_sent = 1 
                                WHERE order_id = ? AND item_id = ?
                            ");
                            $updateStmt->execute([$orderId, $giftCard['item_id']]);
                            writeLog("Gift card marcada como enviada en la base de datos");
                        } catch (PDOException $ex) {
                            writeLog("Error al actualizar estado de gift card: " . $ex->getMessage());
                        }
                    }
                }
            } else {
                writeLog("ERROR: No se encontró el archivo send_giftcard_email.php para enviar gift cards");
            }
        } else {
            writeLog("No se encontraron gift cards para enviar en esta orden");
        }
    } catch (Exception $e) {
        writeLog("ERROR al procesar el envío de gift cards: " . $e->getMessage());
    }

    // Actualizar el total_amount de la orden en la base de datos
    // Asignar el total original antes de modificarlo para códigos promocionales
    $originalTotal = $total;
    
    // Procesar código promocional si se proporcionó
    if (isset($_POST['promo_code']) && !empty($_POST['promo_code']) && 
        isset($_POST['promo_discount']) && !empty($_POST['promo_discount'])) {
        
        $promo_code = $_POST['promo_code'];
        $promo_discount = floatval($_POST['promo_discount']);
        $promo_type = isset($_POST['promo_type']) ? $_POST['promo_type'] : 'fijo';
        
        // Buscar el código promocional en la base de datos para obtener su información real
        try {
            $promoStmt = $pdo->prepare("SELECT * FROM codigos_promocionales WHERE codigo = ?");
            $promoStmt->execute([$promo_code]);
            $promoInfo = $promoStmt->fetch(PDO::FETCH_ASSOC);
            
            // Si se encontró el código en la base de datos, usar sus datos
            if ($promoInfo) {
                // Verificar que el monto del descuento sea consistente
                if (abs($promoInfo['descuento'] - $promo_discount) > 0.01) {
                    writeLog("ADVERTENCIA: Monto de descuento diferente. Frontend: {$promo_discount}, DB: {$promoInfo['descuento']}");
                    
                    // Si es un porcentaje, calculamos el monto real basado en el total
                    if ($promoInfo['tipo_descuento'] === 'porcentaje') {
                        $porcentaje = $promoInfo['descuento'];
                        $promo_discount = ($originalTotal * $porcentaje) / 100;
                        writeLog("Aplicando descuento porcentual: {$porcentaje}% = ${promo_discount}");
                    } elseif ($promoInfo['tipo_descuento'] === 'paquete') {
                        // Para paquetes de 2 jerseys, verificamos que haya exactamente 2 jerseys
                        $jerseyCount = 0;
                        foreach ($cartItems as $item) {
                            if ($item['price'] == 799.00) { // Precio de jersey
                                $jerseyCount += $item['quantity'];
                            }
                        }
                        
                        if ($jerseyCount == 2) {
                            $promo_discount = 598.00; // 1598 - 1000
                            writeLog("Aplicando descuento de paquete: ${promo_discount}");
                        } else {
                            writeLog("ADVERTENCIA: El código de paquete requiere exactamente 2 jerseys");
                            throw new Exception("El código de paquete requiere exactamente 2 jerseys");
                        }
                    } else {
                        // Para descuentos fijos, usamos el valor de la base de datos
                        $promo_discount = $promoInfo['descuento'];
                        writeLog("Usando monto fijo de la base de datos: ${promo_discount}");
                    }
                }
            } else {
                writeLog("ADVERTENCIA: Código promocional {$promo_code} no encontrado en la base de datos");
            }
        } catch (Exception $e) {
            writeLog("Error al consultar información del código promocional: " . $e->getMessage());
            // Continuamos con los datos del frontend como respaldo
        }
        
        // Registrar el código promocional en las columnas disponibles
        try {
            // Modificar el payment_id para incluir el código promocional
            $currentPaymentId = '';
            $getPaymentIdStmt = $pdo->prepare("SELECT payment_id FROM orders WHERE order_id = ?");
            $getPaymentIdStmt->execute([$orderId]);
            $currentPaymentId = $getPaymentIdStmt->fetchColumn();
            
            // Incluir el código promocional en el payment_id
            $newPaymentId = $currentPaymentId . " [PROMO:" . $promo_code . ":$" . number_format($promo_discount, 2) . "]";
            
            // Actualizar payment_id
            $updatePaymentIdStmt = $pdo->prepare("UPDATE orders SET payment_id = ? WHERE order_id = ?");
            $updatePaymentIdStmt->execute([$newPaymentId, $orderId]);
            
            writeLog("Payment ID actualizado con información del código promocional: " . $newPaymentId);
            
            // Actualizar el contador de usos del código promocional
            $updateUsosStmt = $pdo->prepare("UPDATE codigos_promocionales SET usos_actuales = usos_actuales + 1 WHERE codigo = ?");
            $updateUsosStmt->execute([$promo_code]);
            
            // Calcular el monto final después del descuento promocional
            // El total ya tiene descontada la gift card, ahora restamos el descuento promocional
            $totalBeforeDiscount = $total;
            $total = max(0, $total - $promo_discount);
            
            writeLog("Total antes del descuento promocional: ${totalBeforeDiscount}");
            writeLog("Descuento aplicado: ${promo_discount}");
            writeLog("Total después del descuento: ${total}");
            
            // Guardar detalles en el registro de pagos (payment_notes)
            $paymentNotes = $paymentNotes ?: "";
            $payment_notes = $paymentNotes . ($paymentNotes ? " | " : "") . 
                            "Código promocional aplicado: " . $promo_code . 
                            " - Descuento: $" . number_format($promo_discount, 2) . 
                            " - Total original: $" . number_format($totalBeforeDiscount, 2) . 
                            " - Total con descuento: $" . number_format($total, 2);
            
            // Actualizar notas de pago en la base de datos
            $updateNotesStmt = $pdo->prepare("UPDATE orders SET payment_notes = ? WHERE order_id = ?");
            $updateNotesStmt->execute([$payment_notes, $orderId]);
            
            writeLog("Código promocional aplicado: $promo_code, descuento: $promo_discount en pedido #$orderId");
            writeLog("Total original: $originalTotal, Total después del descuento promocional: $total");
            
        } catch (Exception $e) {
            writeLog("Error al registrar código promocional: " . $e->getMessage());
            // No interrumpimos el proceso si falla este paso
        }
    }

    // Actualizar el monto total de la orden (ahora con el valor real pagado)
    $updateTotalStmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
    $updateTotalStmt->execute([$total, $orderId]);
    writeLog("Total final de la orden actualizado: {$total}");
    
    // Si hay un descuento (diferencia entre el total original y el final), 
    // verificar que el total_amount se haya actualizado correctamente
    if (($giftcardAmount > 0 || (isset($promo_discount) && $promo_discount > 0)) 
        && $originalTotal > $total) {
        
        $totalDiscount = $originalTotal - $total;
        
        try {
            // Agregar un registro en el log para asegurar que se ve el descuento
            writeLog("RESUMEN DE ORDEN #$orderId: Total original: ${originalTotal}, Total con descuentos: ${total}, Descuento total: ${totalDiscount}");
            
            // Verificar que el total se haya actualizado correctamente
            $verifyTotalStmt = $pdo->prepare("SELECT total_amount FROM orders WHERE order_id = ?");
            $verifyTotalStmt->execute([$orderId]);
            $verifiedTotal = $verifyTotalStmt->fetchColumn();
            
            writeLog("Verificación: total_amount en base de datos = ${verifiedTotal}");
            
            if (abs($verifiedTotal - $total) > 0.01) {
                writeLog("⚠️ ADVERTENCIA: El total verificado (${verifiedTotal}) no coincide con el esperado (${total})");
                
                // Intentar actualizar nuevamente con force update
                $forceUpdateStmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
                $forceUpdateStmt->execute([$total, $orderId]);
                
                // Verificar de nuevo después de la actualización forzada
                $verifyTotalStmt->execute([$orderId]);
                $reVerifiedTotal = $verifyTotalStmt->fetchColumn();
                
                writeLog("Después de actualización forzada: total_amount = ${reVerifiedTotal}");
                
                if (abs($reVerifiedTotal - $total) > 0.01) {
                    // Como último recurso, hacer update directo con valores literales
                    $directQuery = "UPDATE orders SET total_amount = {$total} WHERE order_id = {$orderId}";
                    $pdo->exec($directQuery);
                    writeLog("Actualización directa SQL: {$directQuery}");
                }
            } else {
                writeLog("✅ Verificación exitosa: El total en la base de datos coincide con el esperado");
            }
            
        } catch (Exception $e) {
            writeLog("Error al verificar/actualizar el total_amount: " . $e->getMessage());
            // Intento final de actualización del total
            try {
                $finalAttemptStmt = $pdo->exec("UPDATE orders SET total_amount = {$total} WHERE order_id = {$orderId}");
                writeLog("Intento final de actualización total_amount = {$total}");
            } catch (Exception $e2) {
                writeLog("Error final al actualizar total_amount: " . $e2->getMessage());
            }
        }
    }

    // Enviar respuesta de éxito siempre que se haya creado la orden correctamente
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