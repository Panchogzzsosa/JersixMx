<?php
// Activar visualización de errores durante desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Establecer encabezados para JSON API
header('Content-Type: application/json');

// Función para registrar logs
function logMessage($message, $type = 'INFO') {
    // Usar directorio temporal
    $logFile = sys_get_temp_dir() . '/promocode.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message" . PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Incluir configuración de base de datos
require_once 'config/database.php';

// Verificar si la solicitud es POST y tiene contenido JSON
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$input || !isset($input['code'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Solicitud inválida'
    ]);
    exit;
}

// Obtener y sanitizar el código promocional
$codigo = trim(strtoupper($input['code']));

if (empty($codigo)) {
    echo json_encode([
        'success' => false,
        'message' => 'El código promocional es requerido'
    ]);
    exit;
}

// Verificar si hay tarjetas de regalo en el carrito (usando la sesión o cookie)
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
        logMessage("Intento de usar código promocional con tarjeta de regalo en el carrito: $codigo", 'WARNING');
        echo json_encode([
            'success' => false,
            'message' => 'No se pueden usar códigos promocionales en compras de tarjetas de regalo'
        ]);
        exit;
    }
}

try {
    // Obtener conexión a la base de datos
    $pdo = getConnection();
    
    // Consultar si el código existe y está activo
    $stmt = $pdo->prepare("
        SELECT * FROM codigos_promocionales 
        WHERE codigo = ? 
        AND estado = 'activo' 
        AND fecha_inicio <= NOW() 
        AND fecha_fin >= NOW() 
        AND (usos_maximos > usos_actuales OR usos_maximos = 0)
    ");
    
    $stmt->execute([$codigo]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$promo) {
        // Buscar si existe pero está inactivo o expirado para dar mensaje más preciso
        $stmt = $pdo->prepare("SELECT * FROM codigos_promocionales WHERE codigo = ?");
        $stmt->execute([$codigo]);
        $checkPromo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checkPromo) {
            if ($checkPromo['estado'] === 'inactivo') {
                $message = 'El código promocional ya no está disponible';
            } elseif (strtotime($checkPromo['fecha_fin']) < time()) {
                $message = 'El código promocional ha expirado';
            } elseif (strtotime($checkPromo['fecha_inicio']) > time()) {
                $message = 'El código promocional aún no está activo';
            } elseif ($checkPromo['usos_maximos'] <= $checkPromo['usos_actuales'] && $checkPromo['usos_maximos'] > 0) {
                $message = 'El código promocional ha alcanzado el límite de usos';
            } else {
                $message = 'Código promocional no válido';
            }
        } else {
            $message = 'Código promocional no encontrado';
        }
        
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        
        logMessage("Código promocional inválido: $codigo - $message", 'WARNING');
        exit;
    }
    
    // El código es válido, retornar información
    $response = [
        'success' => true,
        'message' => 'Código promocional válido',
        'data' => [
            'codigo' => $promo['codigo'],
            'descuento' => (float)$promo['descuento'],
            'tipo_descuento' => $promo['tipo_descuento'],
            'usos_restantes' => $promo['usos_maximos'] > 0 ? $promo['usos_maximos'] - $promo['usos_actuales'] : 'ilimitado'
        ]
    ];
    
    // Si es un paquete de 2 jerseys, agregar información específica
    if ($promo['tipo_descuento'] === 'paquete') {
        $response['data']['paquete_info'] = [
            'precio_original' => 1598.00, // 2 jerseys de 799
            'precio_paquete' => 1000.00,
            'ahorro' => 598.00
        ];
    }
    
    echo json_encode($response);
    logMessage("Código promocional válido: $codigo", 'SUCCESS');
    
} catch (PDOException $e) {
    // Error de base de datos
    logMessage("Error de base de datos: " . $e->getMessage(), 'ERROR');
    
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor al verificar el código',
        'error' => $e->getMessage()
    ]);
} 