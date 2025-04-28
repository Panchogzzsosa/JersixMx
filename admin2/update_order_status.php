<?php
session_start();

// Verificar inicio de sesión
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit(json_encode(['success' => false, 'message' => 'Acceso denegado']));
}

// Verificar datos necesarios
if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    exit(json_encode(['success' => false, 'message' => 'Datos incompletos']));
}



// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Función para calcular el precio real de un producto
function calcularPrecioReal($item) {
    // Precio base del producto
    $precioBase = floatval($item['price']);
    $precioFinal = $precioBase;
    
    // Verificar si tiene personalización (nombre o número)
    $tienePersonalizacion = !empty($item['personalization_name']) || !empty($item['personalization_number']);
    
    // Verificar si tiene parche
    $tieneParche = false;
    if (!empty($item['personalization_patch'])) {
        // Considerar varios casos especiales
        if ($item['personalization_patch'] === '1') {
            // Para jerseys, "1" significa que tiene parche
            $tieneParche = true;
        } else if ($item['personalization_patch'] !== '0' && 
                  $item['personalization_patch'] !== '2' && 
                  $item['personalization_patch'] !== '3' &&
                  strpos($item['personalization_patch'], 'TIPO:') !== 0 &&
                  strpos($item['personalization_patch'], 'RCP:') !== 0) {
            $tieneParche = true;
        }
    }
    
    // Verificar si es una jersey o camiseta
    $nombreProducto = strtolower($item['product_name'] ?? '');
    $esJersey = (
        strpos($nombreProducto, 'jersey') !== false || 
        strpos($nombreProducto, 'camiseta') !== false ||
        strpos($nombreProducto, 'milan') !== false ||
        strpos($nombreProducto, 'manchester') !== false ||
        strpos($nombreProducto, 'barcelona') !== false ||
        strpos($nombreProducto, 'real madrid') !== false ||
        strpos($nombreProducto, 'rayados') !== false ||
        strpos($nombreProducto, 'tigres') !== false ||
        strpos($nombreProducto, 'chivas') !== false ||
        strpos($nombreProducto, 'america') !== false ||
        strpos($nombreProducto, 'arsenal') !== false ||
        strpos($nombreProducto, 'chelsea') !== false ||
        strpos($nombreProducto, 'liverpool') !== false ||
        strpos($nombreProducto, 'atletico') !== false ||
        strpos($nombreProducto, 'ajax') !== false ||
        strpos($nombreProducto, 'juventus') !== false ||
        strpos($nombreProducto, 'tottenham') !== false ||
        strpos($nombreProducto, 'bayern') !== false ||
        strpos($nombreProducto, 'borussia') !== false ||
        strpos($nombreProducto, 'napoli') !== false ||
        strpos($nombreProducto, 'inter') !== false
    ) && strpos($nombreProducto, 'gift card') === false;
    
    // Si es jersey y tiene personalización, añadir costo
    if ($esJersey) {
        if ($tienePersonalizacion) {
            $precioFinal += 100; // Personalización: +$100
        }
        
        if ($tieneParche) {
            $precioFinal += 50; // Parche: +$50
        }
    }
    
    // Calcular subtotal (precio final * cantidad)
    $subtotal = $item['quantity'] * $precioFinal;
    
    return [
        'precio_base' => $precioBase,
        'precio_final' => $precioFinal,
        'subtotal' => $subtotal,
        'tiene_personalizacion' => $tienePersonalizacion,
        'tiene_parche' => $tieneParche,
        'es_jersey' => $esJersey
    ];
}

try {
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $previous_status = $_POST['previous_status'] ?? '';
    
    // Actualizar el estado del pedido
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    // Obtener estadísticas actuales
    $stats_stmt = $pdo->query("SELECT total_sales, total_orders FROM sales_stats LIMIT 1");
    $current_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si el nuevo estado es 'completed' y el estado anterior no lo era
    if ($new_status === 'completed' && $previous_status !== 'completed') {
        // Obtener información del pedido y giftcard aplicada
        $order_info_stmt = $pdo->prepare("
            SELECT o.*, 
                SUM(oi.quantity * oi.price) as full_order_total 
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.order_id = ?
            GROUP BY o.order_id
        ");
        $order_info_stmt->execute([$order_id]);
        $order_info = $order_info_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obtener todos los items para calcular el total real incluyendo personalizaciones
        $items_stmt = $pdo->prepare("
            SELECT 
                oi.*,
                p.name as product_name,
                p.category,
                p.description
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
        ");
        $items_stmt->execute([$order_id]);
        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular el total real considerando personalizaciones y parches
        $real_order_total = 0;
        foreach ($order_items as $item) {
            // Usar la función de calcular precio real que considera personalizaciones y parches
            $priceData = calcularPrecioReal($item);
            $real_order_total += $priceData['subtotal'];
        }
        
        // Verificar si debemos procesar este pedido para estadísticas
        $should_count_for_stats = true;
        $should_count_orders_only = false;
        $order_total = $real_order_total; // Usar el total real calculado
        $discount_amount = 0;
        
        // Verificar si es un pago exclusivamente con gift card (no suma a estadísticas de ventas, pero sí a pedidos)
        if ($order_info['payment_method'] === 'giftcard') {
            error_log("Orden #{$order_id} pagada exclusivamente con gift card - NO se suma a estadísticas de ventas, pero SÍ a pedidos completados");
            $should_count_for_stats = false;
            $should_count_orders_only = true;
        }
        // Verificar si es un pago parcial con gift card o código promocional
        else if ($order_info['payment_method'] === 'giftcard+paypal' || 
                 strpos($order_info['payment_notes'] ?? '', 'Gift Card aplicada') !== false ||
                 strpos($order_info['payment_notes'] ?? '', 'Código promocional aplicado') !== false) {
            
            error_log("Orden #{$order_id} pagada parcialmente con gift card o código promocional");
            
            // Verificar notas de pago para encontrar información del descuento
            if (!empty($order_info['payment_notes'])) {
                // Buscar información de descuento con gift card en las notas
                if (preg_match('/Gift Card aplicada:.+\- Monto: \$([0-9.]+)/', $order_info['payment_notes'], $matches)) {
                    $discount_amount = floatval($matches[1]);
                    error_log("Monto de descuento de Gift Card encontrado en notas: {$discount_amount}");
                    
                    // Solo se suma la diferencia (lo que realmente pagó el cliente)
                    $order_total = max(0, $order_total - $discount_amount);
                    error_log("Total ajustado: {$order_total}");
                }
                
                // Buscar información de descuento con código promocional en las notas
                if (preg_match('/Código promocional aplicado:.+\- Descuento: \$([0-9.]+)/', $order_info['payment_notes'], $matches)) {
                    $promo_discount = floatval($matches[1]);
                    error_log("Monto de descuento de Código Promocional encontrado en notas: {$promo_discount}");
                    
                    // Solo se suma la diferencia (lo que realmente pagó el cliente)
                    $order_total = max(0, $order_total - $promo_discount);
                    error_log("Total ajustado después de código promocional: {$order_total}");
                }
            }
        }
        
        // Solo actualizar estadísticas si debe contarse para estadísticas
        if ($should_count_for_stats) {
            $new_total_sales = $current_stats['total_sales'] + $order_total;
            $new_total_orders = $current_stats['total_orders'] + 1;
            
            error_log("Actualizando estadísticas: Ventas +{$order_total}, Órdenes +1");
            
            $pdo->exec("
                UPDATE sales_stats 
                SET total_sales = {$new_total_sales},
                    total_orders = {$new_total_orders}
            ");
        } else if ($should_count_orders_only) {
            // Solo incrementar el contador de órdenes pero no las ventas (para gift cards al 100%)
            $new_total_orders = $current_stats['total_orders'] + 1;
            
            error_log("Actualizando estadísticas: Solo incrementando contador de órdenes +1 (gift card 100%)");
            
            $pdo->exec("
                UPDATE sales_stats 
                SET total_orders = {$new_total_orders}
            ");
        } else {
            error_log("No se actualizaron estadísticas para orden #{$order_id} (gift card)");
        }
    }
    // Si el estado anterior era 'completed' y el nuevo no lo es
    else if ($previous_status === 'completed' && $new_status !== 'completed') {
        // Obtener información del pedido y giftcard aplicada
        $order_info_stmt = $pdo->prepare("
            SELECT o.*, 
                SUM(oi.quantity * oi.price) as full_order_total 
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.order_id = ?
            GROUP BY o.order_id
        ");
        $order_info_stmt->execute([$order_id]);
        $order_info = $order_info_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obtener todos los items para calcular el total real incluyendo personalizaciones
        $items_stmt = $pdo->prepare("
            SELECT 
                oi.*,
                p.name as product_name,
                p.category,
                p.description
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
        ");
        $items_stmt->execute([$order_id]);
        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular el total real considerando personalizaciones y parches
        $real_order_total = 0;
        foreach ($order_items as $item) {
            // Usar la función de calcular precio real que considera personalizaciones y parches
            $priceData = calcularPrecioReal($item);
            $real_order_total += $priceData['subtotal'];
        }
        
        // Verificar si debemos procesar este pedido para estadísticas
        $should_count_for_stats = true;
        $should_count_orders_only = false;
        $order_total = $real_order_total; // Usar el total real calculado
        $discount_amount = 0;
        
        // Verificar si es un pago exclusivamente con gift card (no afecta a estadísticas)
        if ($order_info['payment_method'] === 'giftcard') {
            error_log("Orden #{$order_id} pagada exclusivamente con gift card - NO se resta de estadísticas");
            $should_count_for_stats = false;
        }
        // Verificar si es un pago parcial con gift card o código promocional
        else if ($order_info['payment_method'] === 'giftcard+paypal' || 
                 strpos($order_info['payment_notes'] ?? '', 'Gift Card aplicada') !== false ||
                 strpos($order_info['payment_notes'] ?? '', 'Código promocional aplicado') !== false) {
            
            error_log("Revertir orden #{$order_id} pagada parcialmente con gift card o código promocional");
            
            // Verificar notas de pago para encontrar información del descuento
            if (!empty($order_info['payment_notes'])) {
                // Buscar información de descuento con gift card en las notas
                if (preg_match('/Gift Card aplicada:.+\- Monto: \$([0-9.]+)/', $order_info['payment_notes'], $matches)) {
                    $discount_amount = floatval($matches[1]);
                    error_log("Monto de descuento de Gift Card encontrado en notas: {$discount_amount}");
                    
                    // Solo se resta la diferencia (lo que realmente pagó el cliente)
                    $order_total = max(0, $order_total - $discount_amount);
                    error_log("Total ajustado: {$order_total}");
                }
                
                // Buscar información de descuento con código promocional en las notas
                if (preg_match('/Código promocional aplicado:.+\- Descuento: \$([0-9.]+)/', $order_info['payment_notes'], $matches)) {
                    $promo_discount = floatval($matches[1]);
                    error_log("Monto de descuento de Código Promocional encontrado en notas: {$promo_discount}");
                    
                    // Solo se resta la diferencia (lo que realmente pagó el cliente)
                    $order_total = max(0, $order_total - $promo_discount);
                    error_log("Total ajustado después de código promocional: {$order_total}");
                }
            }
        }
        
        // Solo actualizar estadísticas si debe contarse para estadísticas
        if ($should_count_for_stats) {
            $new_total_sales = max(0, $current_stats['total_sales'] - $order_total);
            $new_total_orders = max(0, $current_stats['total_orders'] - 1);
            
            error_log("Actualizando estadísticas: Ventas -{$order_total}, Órdenes -1");
            
            $pdo->exec("
                UPDATE sales_stats 
                SET total_sales = {$new_total_sales},
                    total_orders = {$new_total_orders}
            ");
        } else {
            error_log("No se actualizaron estadísticas para orden #{$order_id} (gift card)");
        }
    }
    
    $pdo->commit();
    
    // Obtener las estadísticas actualizadas
    $stats_stmt = $pdo->query("SELECT total_sales, total_orders FROM sales_stats LIMIT 1");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch(PDOException $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 