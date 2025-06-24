<?php
session_start();

// Simplificar la verificación de sesión
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos
try {
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Obtener estadísticas de ventas
try {
    $stats_stmt = $pdo->query("SELECT total_sales, total_orders FROM sales_stats LIMIT 1");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $stats = ['total_sales' => 0, 'total_orders' => 0];
}

// Obtener pedidos con información del cliente y total del pedido
try {
    // Verificar gift cards redimidas y actualizar órdenes correspondientes a completadas
    try {
        // Verificar si existen las tablas necesarias
        $tablesExist = $pdo->query("SHOW TABLES LIKE 'giftcard_redemptions'")->rowCount() > 0;
        
        if ($tablesExist) {
            // Buscar gift cards que estén redimidas (saldo = 0)
            $redeemedGiftcardsStmt = $pdo->query("
                SELECT r.code, r.redeemed 
                FROM giftcard_redemptions r 
                WHERE r.redeemed = 1
            ");
            $redeemedGiftcards = $redeemedGiftcardsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($redeemedGiftcards as $giftcard) {
                // Buscar órdenes que contengan estas gift cards y no estén completadas
                $orderStmt = $pdo->prepare("
                    SELECT o.order_id 
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.order_id
                    JOIN products p ON oi.product_id = p.product_id
                    WHERE oi.personalization_number = ? 
                    AND (p.name LIKE '%Tarjeta de Regalo%' OR p.name LIKE '%Gift Card%')
                    AND o.status != 'completed'
                ");
                $orderStmt->execute([$giftcard['code']]);
                $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($orderData) {
                    // Actualizar el estado de la orden a "completed"
                    $updateStmt = $pdo->prepare("
                        UPDATE orders SET status = 'completed' WHERE order_id = ?
                    ");
                    $updateStmt->execute([$orderData['order_id']]);
                    
                    // Actualizar estadísticas (sumar a ventas totales y pedidos completados)
                    // Primero obtenemos el total de la orden
                    $orderTotalStmt = $pdo->prepare("
                        SELECT SUM(oi.quantity * oi.price) as order_total
                        FROM order_items oi
                        WHERE oi.order_id = ?
                    ");
                    $orderTotalStmt->execute([$orderData['order_id']]);
                    $orderTotalData = $orderTotalStmt->fetch(PDO::FETCH_ASSOC);
                    $orderTotal = $orderTotalData['order_total'] ?? 0;
                    
                    // Verificar si debemos procesar este pedido para estadísticas (si no es pago con gift card)
                    $orderMethodStmt = $pdo->prepare("SELECT payment_method FROM orders WHERE order_id = ?");
                    $orderMethodStmt->execute([$orderData['order_id']]);
                    $paymentMethod = $orderMethodStmt->fetchColumn();
                    
                    // Obtener estadísticas actuales
                    $currentStatsStmt = $pdo->query("SELECT total_sales, total_orders FROM sales_stats LIMIT 1");
                    $currentStats = $currentStatsStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($currentStats) {
                        if ($paymentMethod !== 'giftcard') {
                            // Si NO es pago exclusivo con gift card, sumar también el valor de la venta
                            $newTotalSales = $currentStats['total_sales'] + $orderTotal;
                            $newTotalOrders = $currentStats['total_orders'] + 1;
                            
                            $updateStatsStmt = $pdo->prepare("
                                UPDATE sales_stats 
                                SET total_sales = ?, total_orders = ?
                            ");
                            $updateStatsStmt->execute([$newTotalSales, $newTotalOrders]);
                            
                            error_log("Estadísticas actualizadas: Ventas +{$orderTotal}, Órdenes +1 (Orden #{$orderData['order_id']})");
                        } else {
                            // Si es pago exclusivo con gift card, solo incrementar el contador de órdenes
                            $newTotalOrders = $currentStats['total_orders'] + 1;
                            
                            $updateStatsStmt = $pdo->prepare("
                                UPDATE sales_stats 
                                SET total_orders = ?
                            ");
                            $updateStatsStmt->execute([$newTotalOrders]);
                            
                            error_log("Estadísticas actualizadas: Solo contador de Órdenes +1, sin sumar ventas (Orden #{$orderData['order_id']} pagada 100% con gift card)");
                        }
                    }
                    
                    // Registrar en el log
                    error_log("Gift Card {$giftcard['code']} redimida completamente - Orden #{$orderData['order_id']} actualizada a 'completed'");
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error al verificar gift cards redimidas: " . $e->getMessage());
    }
    
    $stmt = $pdo->query("
        SELECT 
            o.*,
            COUNT(DISTINCT oi.order_item_id) as total_items,
            SUM(oi.quantity * oi.price) as order_total_before_discount,
            CASE 
                WHEN o.payment_notes REGEXP 'Gift Card aplicada:.+\\- Monto: \\$([0-9.]+)' THEN
                    SUM(oi.quantity * oi.price) - (
                        SELECT CAST(REGEXP_REPLACE(
                            REGEXP_SUBSTR(o.payment_notes, 'Gift Card aplicada:.+\\- Monto: \\$([0-9.]+)'),
                            'Gift Card aplicada:.+\\- Monto: \\$', ''
                        ) AS DECIMAL(10,2))
                    )
                ELSE SUM(oi.quantity * oi.price)
            END as order_total,
            GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as products_summary
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        GROUP BY 
            o.order_id, 
            o.customer_name, 
            o.customer_email, 
            o.status, 
            o.created_at,
            o.phone,
            o.street,
            o.colony,
            o.city,
            o.state,
            o.zip_code,
            o.payment_status,
            o.payment_notes
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error al obtener pedidos: " . $e->getMessage();
    $orders = [];
}

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
            // Registro para depuración
            error_log("Producto {$item['product_id']} ({$nombreProducto}) tiene personalización (+$100): Nombre: " . ($item['personalization_name'] ?? 'N/A') . ", Número: " . ($item['personalization_number'] ?? 'N/A'));
        }
        
        if ($tieneParche) {
            $precioFinal += 50; // Parche: +$50
            // Registro para depuración
            error_log("Producto {$item['product_id']} ({$nombreProducto}) tiene parche (+$50): " . ($item['personalization_patch'] ?? 'N/A'));
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Panel de Administración - Jersix</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --sidebar-width: 240px;
            --topbar-height: 60px;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #333;
            background-color: #f5f7fa;
            line-height: 1.5;
        }
        
        .dashboard-layout {
            display: grid;
            grid-template-columns: var(--sidebar-width) 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            width: var(--sidebar-width);
            padding-top: 15px;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .sidebar .nav-menu {
            list-style: none;
            padding: 15px 0;
        }
        
        .sidebar .nav-item {
            margin: 5px 0;
        }
        
        .sidebar .nav-item a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-radius: 4px;
            margin: 0 8px;
            transition: var(--transition);
        }
        
        .sidebar .nav-item a i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .sidebar .nav-item a:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-item.active a {
            color: white;
            background: var(--primary-color);
        }
        
        /* Main content */
        .main-content {
            grid-column: 2;
            padding: 30px;
        }
        
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .topbar h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            background: white;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .user-info span {
            margin-right: 15px;
            color: var(--secondary-color);
        }
        
        /* Panel */
        .panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .order-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .order-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .order-date {
            font-size: 0.9em;
            color: var(--secondary-color);
        }

        .status-badges {
            display: flex;
            gap: 10px;
        }

        .status-badge, .payment-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-badge.completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.pending {
            background-color: #fff3e0;
            color: #ef6c00;
        }

        .payment-badge.paid {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .payment-badge.pending {
            background-color: #fce4ec;
            color: #c2185b;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
        }

        .customer-info, .shipping-info, .order-summary {
            padding: 15px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .customer-info h4, .shipping-info h4, .order-summary h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn-view, .btn-edit, .btn-print {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-view {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-edit {
            background-color: var(--warning-color);
            color: var(--dark-color);
        }

        .btn-print {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-view:hover, .btn-edit:hover, .btn-print:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .panel-header {
            padding: 20px;
            border-bottom: 1px solid #eaedf3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        /* Table */
        .table-container {
            width: 100%;
            overflow-x: auto;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            max-width: 90%;
            margin: 0 auto;
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .table-container th {
            background-color: #f8f9fa;
            color: var(--dark-color);
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid #eaedf3;
        }
        
        .table-container td {
            padding: 6px 10px;
            border-bottom: 1px solid #eaedf3;
            vertical-align: top;
        }
        
        .table-container tr:last-child td {
            border-bottom: none;
        }
        
        .table-container tr:hover {
            background-color: #f8f9fa;
        }
        
        .product-thumbnail {
            width: 35px;
            height: 35px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .product-name {
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .product-category {
            font-size: 0.9em;
            color: var(--secondary-color);
        }
        
        .product-description {
            font-size: 0.85em;
            color: #555;
            max-width: 200px;
        }
        
        .order-items {
            margin-top: 15px;
            overflow-x: auto;
        }
        
        .order-items table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .order-items th {
            background-color: #f8f9fa;
            font-weight: 500;
            text-align: left;
            padding: 6px 8px;
            border-bottom: 1px solid #eaedf3;
        }
        
        .order-items td {
            padding: 6px 8px;
            border-bottom: 1px solid #eaedf3;
            vertical-align: middle;
        }
        
        .personalization-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 0.9em;
        }
        
        .personalization-list li {
            margin-bottom: 2px;
        }
        
        /* Botones */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        /* Estados de pedidos */
        .order-status {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-pending {
            background-color: rgba(255,193,7,0.1);
            color: #ffc107;
        }
        
        .status-processing {
            background-color: rgba(0,123,255,0.1);
            color: #007bff;
        }
        
        .status-completed {
            background-color: rgba(40,167,69,0.1);
            color: #28a745;
        }
        
        .status-cancelled {
            background-color: rgba(220,53,69,0.1);
            color: #dc3545;
        }
        
        /* Detalles del pedido */
        .order-details {
            display: none;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 10px 20px;
        }
        
        .order-details.active {
            display: block;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .info-group {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .info-group h4 {
            color: var(--primary-color);
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eaedf3;
        }
        
        .info-group p {
            margin: 8px 0;
            line-height: 1.6;
        }
        
        .info-group strong {
            color: var(--dark-color);
            font-weight: 600;
            min-width: 140px;
            display: inline-block;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .order-items {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .order-items table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .order-items th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .order-items td {
            padding: 12px 15px;
            border-top: 1px solid #dee2e6;
        }
        
        .order-details {
            margin: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        /* Notificaciones */
        .notification {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            transition: opacity 0.3s;
        }
        
        .success-message {
            background-color: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        .error-message {
            background-color: rgba(220,53,69,0.1);
            color: var(--danger-color);
        }
        
        .notification i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                width: 0;
                padding-top: 60px;
            }
            
            .sidebar.active {
                width: var(--sidebar-width);
            }
            
            .main-content {
                grid-column: 1;
                padding-top: calc(var(--topbar-height) + 20px);
            }
            
            .mobile-toggle {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background: var(--primary-color);
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .customer-info {
                grid-template-columns: 1fr;
            }
            
            /* Estilos para vista móvil de pedidos */
            .table-container {
                overflow-x: visible;
                margin: 0;
                padding: 0 15px;
            }
            
            table, thead, tbody, tr, th, td {
                display: block;
                width: 100%;
            }
            
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            tr {
                margin-bottom: 20px;
                border: 1px solid #e9ecef;
                border-radius: var(--border-radius);
                background-color: white;
                box-shadow: var(--box-shadow);
                overflow: hidden;
            }
            
            td {
                border: none;
                position: relative;
                padding: 12px 15px;
                padding-left: 50%;
                text-align: right;
            }
            
            td:before {
                position: absolute;
                top: 12px;
                left: 15px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: var(--secondary-color);
                content: attr(data-label);
            }
            
            td:last-child {
                border-bottom: 0;
            }
            
            .action-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-end;
            }
            
            .btn-sm {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            /* Ajustes para los detalles del pedido */
            .order-details {
                margin: 10px 0;
                padding: 15px;
            }
            
            .order-items table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .order-items th, .order-items td {
                display: table-cell;
                padding: 10px;
            }
            
            .order-items td:before {
                display: none;
            }
            
            /* Ajustes para los modales */
            .modal-content {
                width: 95%;
                margin: 10px auto;
            }
            
            .product-entry {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
        
        .no-image {
            width: 60px;
            height: 60px;
            background-color: #f8f9fa;
            border: 1px solid #eaedf3;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: var(--secondary-color);
        }
        
        .stats-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-card-header h3 {
            font-size: 16px;
            color: var(--secondary-color);
            margin: 0;
        }
        
        .stat-card-header i {
            font-size: 20px;
            color: var(--primary-color);
        }
        
        .stat-card-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fff;
            margin: 20px auto;
            width: 90%;
            max-width: 1000px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eaedf3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--secondary-color);
        }

        .close:hover {
            color: var(--danger-color);
        }

        .form-sections {
            padding: 20px;
        }

        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
        }

        .form-section h4 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--dark-color);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .product-entry {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            align-items: end;
        }

        .delete-product {
            padding: 8px;
            height: 38px;
        }

        .form-footer {
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #eaedf3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-section h4 {
            color: var(--dark-color);
            font-size: 18px;
        }

        .button-section {
            display: flex;
            gap: 10px;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        /* Agregar el estilo para el modal de confirmación */
        .confirm-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .confirm-modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            width: 90%;
            max-width: 400px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
        }

        .confirm-modal-content h4 {
            margin-bottom: 20px;
            color: var(--danger-color);
        }

        .confirm-modal-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .status-select {
            padding: 8px 30px 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='currentColor' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 10px;
            min-width: 140px;
            max-width: 100%;
            text-align: left;
        }

        /* Estilos específicos para cada estado */
        .status-pending {
            background-color: #fff8e1;
            color: #f57c00;
            border: 1px solid #ffe0b2;
        }

        .status-processing {
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .status-cancelled {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        /* Estilos para las opciones del select */
        .status-select option {
            background-color: white;
            color: #333;
            padding: 8px;
        }

        /* Efecto hover */
        .status-select:hover {
            opacity: 0.9;
        }

        /* Efecto focus */
        .status-select:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        /* Asegurar que el texto sea visible */
        .status-select::-ms-expand {
            display: none;
        }

        /* Ajustes para mejor legibilidad */
        .status-select {
            text-shadow: none;
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.2px;
        }
        
        .gift-card-badge, .promo-code-badge {
            display: inline-flex;
            align-items: center;
            font-size: 0.7rem;
            font-weight: 500;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 3px;
        }
        
        .gift-card-badge {
            background-color: #e3f6ea;
            color: #28a745;
            border: 1px solid #c3e6cb;
        }
        
        .promo-code-badge {
            background-color: #fff3cd;
            color: #ffc107;
            border: 1px solid #ffeeba;
        }
        
        .gift-card-badge i, .promo-code-badge i {
            margin-right: 3px;
            font-size: 0.7rem;
        }
        
        .payment-badges {
            margin-top: 4px;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Jersix.mx</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Inicio</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php">
                        <i class="fas fa-box"></i>
                        <span>Productos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="inventario.php">
                        <i class="fas fa-warehouse"></i>
                        <span>Inventario</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="orders.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Ventas Web</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="ventas.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Ventas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="newsletter.php">
                        <i class="fas fa-users"></i>
                        <span>Clientes / Newsletter</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="giftcards.php">
                        <i class="fas fa-gift"></i>
                        <span>Gift Cards</span>
                    </a>
                </li>
                <li class="nav-item ">
                    <a href="promociones.php">
                        <i class="fas fa-percent"></i>
                        <span>Promociones</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="banner_config.php">
                        <i class="fas fa-image"></i>
                        <span>Banner</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="banner_manager.php">
                        <i class="fas fa-images"></i>
                        <span>Fotos y Lo más vendido</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pedidos.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Pedidos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <div>
                    <h1>Gestión de Pedidos</h1>
                </div>
                <div class="user-info">
                    <img src="../img/ICON.png" alt="User" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                    <span><?php echo $_SESSION['admin_name'] ?? 'Administrador'; ?></span>
                </div>
            </div>

            <div class="stats-panel">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Ventas Totales</h3>
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-card-value">$<?php echo number_format($stats['total_sales'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Pedidos Completados</h3>
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['total_orders']; ?></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Lista de Pedidos</h3>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-primary" onclick="openAddOrderModal()">
                            <i class="fas fa-plus"></i> Agregar Orden
                        </button>
                    </div>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3 class="empty-state-title">No hay pedidos disponibles</h3>
                        <p class="empty-state-description">Los pedidos aparecerán aquí cuando los clientes realicen compras</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Pedido</th>
                                    <th>Cliente</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <?php 
                                    $isGiftcardPayment = ($order['payment_method'] === 'giftcard' || $order['payment_method'] === 'giftcard+paypal' || strpos($order['payment_notes'] ?? '', 'Gift Card aplicada') !== false);
                                    $hasPromoCode = (strpos($order['payment_notes'] ?? '', 'Código promocional aplicado') !== false);
                                    ?>
                                    <tr data-order-id="<?php echo $order['order_id']; ?>" <?php echo $isGiftcardPayment ? 'data-payment="giftcard"' : ''; ?> <?php echo $hasPromoCode ? 'data-payment="promocode"' : ''; ?>>
                                        <td data-label="ID Pedido">#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td data-label="Cliente">
                                            <div><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                            <div class="payment-badges" style="margin-top: 4px; display: flex; gap: 5px;">
                                                <?php if ($isGiftcardPayment): ?>
                                                    <div class="gift-card-badge">
                                                        <i class="fas fa-gift"></i> Gift Card
                                                    </div>
                                                <?php endif; ?>
                                                <?php 
                                                // Mostrar badge de promo si hay código promocional o promoción automática
                                                $hasPromoBadge = false;
                                                $payment_notes = $order['payment_notes'] ?? '';
                                                if (strpos($payment_notes, 'Código promocional aplicado') !== false || strpos($payment_notes, 'Promoción automática') !== false) {
                                                    $hasPromoBadge = true;
                                                }
                                                if ($hasPromoBadge): ?>
                                                    <div class="promo-code-badge">
                                                        <i class="fas fa-percent"></i> Promo
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td data-label="Items"><?php echo $order['total_items']; ?> productos</td>
                                        <td data-label="Total">
                                            <?php 
                                            // Obtener items para calcular el precio real incluyendo personalizaciones
                                            $real_total_stmt = $pdo->prepare("
                                                SELECT 
                                                    oi.*,
                                                    p.name as product_name
                                                FROM order_items oi
                                                LEFT JOIN products p ON oi.product_id = p.product_id
                                                WHERE oi.order_id = ?
                                            ");
                                            $real_total_stmt->execute([$order['order_id']]);
                                            $items = $real_total_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            // Calcular el total real
                                            $real_total = 0;
                                            foreach ($items as $item) {
                                                $priceData = calcularPrecioReal($item);
                                                $real_total += $priceData['subtotal'];
                                            }
                                            
                                            // Verificar si hay descuento de giftcard
                                            $discount_amount = 0;
                                            $isGiftcardPayment = ($order['payment_method'] === 'giftcard' || $order['payment_method'] === 'giftcard+paypal' || strpos($order['payment_notes'] ?? '', 'Gift Card aplicada') !== false);
                                            
                                            if ($isGiftcardPayment) {
                                                // Obtener notas de pago para buscar descuento
                                                $notes_stmt = $pdo->prepare("SELECT payment_notes FROM orders WHERE order_id = ?");
                                                $notes_stmt->execute([$order['order_id']]);
                                                $payment_notes = $notes_stmt->fetchColumn();
                                                
                                                if (!empty($payment_notes) && preg_match('/Gift Card aplicada:.+- Monto: \$([0-9.]+)/', $payment_notes, $matches)) {
                                                    $discount_amount = floatval($matches[1]);
                                                }
                                            }
                                            
                                            // Verificar si hay descuento de código promocional o promoción automática
                                            $hasPromo = false;
                                            $promo_discount = 0;
                                            $payment_notes = $order['payment_notes'] ?? '';
                                            if (!empty($payment_notes)) {
                                                // Código promocional tradicional
                                                if (preg_match('/Código promocional aplicado:.+- Descuento: \$([0-9.]+)/', $payment_notes, $matches)) {
                                                    $promo_discount = floatval($matches[1]);
                                                    $hasPromo = true;
                                                }
                                                // Promoción automática
                                                if (preg_match('/Promoción automática [^\-]+- Descuento: \$([0-9.]+)/', $payment_notes, $matches)) {
                                                    $promo_discount = floatval($matches[1]);
                                                    $hasPromo = true;
                                                }
                                            }
                                            
                                            // Mostrar precios según el tipo de descuento
                                            if ($order['payment_method'] === 'giftcard') {
                                                echo '<div style="font-weight: 500; color: #28a745;">$0 <small style="color: #6c757d; text-decoration: line-through;">($' . number_format($real_total, 2) . ')</small></div>';
                                            } elseif ($hasPromo && $promo_discount > 0) {
                                                $paid_amount = $real_total - $promo_discount;
                                                echo '<div>';
                                                echo '<span style="font-weight: 500; color: #28a745;">$' . number_format($paid_amount, 2) . '</span><br>';
                                                echo '<small style="color: #6c757d; text-decoration: line-through;">$' . number_format($real_total, 2) . '</small>';
                                                echo '</div>';
                                            } else {
                                                echo '$' . number_format($real_total, 2);
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Estado">
                                            <select class="status-select status-<?php echo $order['status']; ?>" 
                                                    data-order-id="<?php echo $order['order_id']; ?>"
                                                    data-previous-status="<?php echo $order['status']; ?>"
                                                    <?php if ($order['payment_method'] === 'giftcard'): ?>data-giftcard="true"<?php endif; ?>
                                                    onchange="updateOrderStatus(this)">
                                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>En proceso</option>
                                                <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completado</option>
                                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                                            </select>
                                        </td>
                                        <td data-label="Fecha"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                        <td data-label="Acciones">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-primary expand-button" onclick="toggleOrderDetails(<?php echo $order['order_id']; ?>)">
                                                    <i class="fas fa-chevron-down"></i> Detalles
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="editOrder(<?php echo $order['order_id']; ?>)">
                                                    <i class="fas fa-edit"></i> Editar
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteOrder(<?php echo $order['order_id']; ?>)">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr data-order-id="<?php echo $order['order_id']; ?>" <?php echo $isGiftcardPayment ? 'data-payment="giftcard"' : ''; ?> <?php echo $hasPromoCode ? 'data-payment="promocode"' : ''; ?>>
                                        <td colspan="8">
                                            <div id="order-details-<?php echo $order['order_id']; ?>" class="order-details">
                                                <div class="customer-info">
                                                    <div class="info-group">
                                                        <h4>Información Personal</h4>
                                                        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                                        <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                                                    </div>
                                                    <div class="info-group">
                                                        <h4>Dirección de Envío</h4>
                                                        <p><strong>Calle y Número:</strong> <?php echo htmlspecialchars($order['street']); ?></p>
                                                        <p><strong>Colonia:</strong> <?php echo htmlspecialchars($order['colony']); ?></p>
                                                        <p><strong>Ciudad:</strong> <?php echo htmlspecialchars($order['city']); ?></p>
                                                        <p><strong>Estado:</strong> <?php echo htmlspecialchars($order['state']); ?></p>
                                                        <p><strong>Código Postal:</strong> <?php echo htmlspecialchars($order['zip_code']); ?></p>
                                                    </div>
                                                </div>
                                                
                                                <div class="order-items">
                                                    <table>
                                                        <thead>
                                                            <tr>
                                                                <th>Imagen</th>
                                                                <th>Producto</th>
                                                                <th>Detalles</th>
                                                                <th>Talla</th>
                                                                <th>Cantidad</th>
                                                                <th>Precio Unitario</th>
                                                                <th>Total</th>
                                                                <th>Personalización</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $items_stmt = $pdo->prepare("
                                                                SELECT 
                                                                    oi.*,
                                                                    p.name as product_name,
                                                                    p.image_url,
                                                                    p.category,
                                                                    p.description,
                                                                    oi.personalization_name,
                                                                    oi.personalization_number,
                                                                    oi.personalization_patch
                                                                FROM order_items oi
                                                                LEFT JOIN products p ON oi.product_id = p.product_id
                                                                WHERE oi.order_id = ?
                                                            ");
                                                            $items_stmt->execute([$order['order_id']]);
                                                            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                                                            
                                                            $order_subtotal = 0;
                                                            foreach ($items as $item):
                                                                // Usar la función de calcular precio real
                                                                $priceData = calcularPrecioReal($item);
                                                                
                                                                // Usar los datos calculados
                                                                $basePrice = $priceData['precio_base'];
                                                                $displayPrice = $priceData['precio_final'];
                                                                $item_total = $priceData['subtotal'];
                                                                $hasPersonalization = $priceData['tiene_personalizacion'];
                                                                $hasPatch = $priceData['tiene_parche'];
                                                                $isJersey = $priceData['es_jersey'];
                                                                
                                                                // Acumular el subtotal de la orden
                                                                $order_subtotal += $item_total;
                                                            ?>
                                                                <tr>
                                                                    <td>
                                                                        <?php if (!empty($item['image_url'])): ?>
                                                                            <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                                                 class="product-thumbnail">
                                                                        <?php else: ?>
                                                                            <div class="no-image">Sin imagen</div>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                                        <?php if (!empty($item['category'])): ?>
                                                                            <span class="product-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if (!empty($item['description'])): ?>
                                                                            <div class="product-description">
                                                                                <?php echo htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : ''); ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td><strong><?php echo htmlspecialchars($item['size']); ?></strong></td>
                                                                    <td><?php echo $item['quantity']; ?></td>
                                                                    <td>
                                                                        <?php if ($isJersey && ($hasPersonalization || $hasPatch)): ?>
                                                                            <div class="price-container">
                                                                                <div class="personalized-price">$<?php echo number_format($displayPrice, 2); ?></div>
                                                                            </div>
                                                                            <?php if ($hasPersonalization): ?>
                                                                                <div class="price-detail personalization"></div>
                                                                            <?php endif; ?>
                                                                            <?php if ($hasPatch): ?>
                                                                                <div class="price-detail patch"></div>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            $<?php echo number_format($displayPrice, 2); ?>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>$<?php echo number_format($item_total, 2); ?></td>
                                                                    <td>
                                                                        <?php if (!empty($item['personalization_name']) || !empty($item['personalization_number']) || ($item['product_name'] === 'Mystery Box' && !empty($item['personalization_patch']))): ?>
                                                                            <div style="font-size: 0.9em;">
                                                                                <?php if (!empty($item['personalization_name'])): ?>
                                                                                    <div>
                                                                                        <span style="color: #6b7280;">Nombre:</span> 
                                                                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($item['personalization_name']); ?></span>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($item['personalization_number'])): ?>
                                                                                    <div style="margin-top: 2px;">
                                                                                        <span style="color: #6b7280;">Número:</span> 
                                                                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($item['personalization_number']); ?></span>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                                <?php if ($item['product_name'] === 'Mystery Box' && !empty($item['personalization_patch'])): ?>
                                                                                    <div style="margin-top: 2px;">
                                                                                        <span style="color: #6b7280;">Tipo:</span> 
                                                                                        <span style="font-weight: 500; color: #16a34a;"><?php 
                                                                                            // Verificar si usa el nuevo formato con prefijo TIPO:
                                                                                            if (strpos($item['personalization_patch'], 'TIPO:') === 0) {
                                                                                                // Extraer y decodificar el tipo y el equipo no deseado
                                                                                                $patchData = explode('|', $item['personalization_patch']);
                                                                                                $tipoEncoded = substr($patchData[0], 5);
                                                                                                echo htmlspecialchars(base64_decode($tipoEncoded));
                                                                                                
                                                                                                // Mostrar equipo no deseado si existe
                                                                                                if (isset($patchData[1]) && strpos($patchData[1], 'UNWANTED:') === 0) {
                                                                                                    $unwantedTeamEncoded = substr($patchData[1], 9);
                                                                                                    $unwantedTeam = base64_decode($unwantedTeamEncoded);
                                                                                                    echo '</span></div>';
                                                                                                    echo '<div style="margin-top: 4px;">';
                                                                                                    echo '<span style="color: #6b7280;">Equipo no deseado:</span>';
                                                                                                    echo '<span style="font-weight: 500; color: #e74c3c;"> ' . htmlspecialchars($unwantedTeam) . '</span>';
                                                                                                }
                                                                                            } else {
                                                                                                // Formato antiguo con números
                                                                                                $tipos = [
                                                                                                    '1' => 'Champions League',
                                                                                                    '2' => 'Liga MX',
                                                                                                    '3' => 'Liga Europea'
                                                                                                ];
                                                                                                echo isset($tipos[$item['personalization_patch']]) ? $tipos[$item['personalization_patch']] : 'No especificado';
                                                                                            }
                                                                                        ?></span>
                                                            
                                                                                    </div>
                                                                                <?php elseif (!empty($item['personalization_patch'])): ?>
                                                                                    <?php
                                                                                    // No mostrar "Parche" para gift cards y tarjetas de regalo
                                                                                    $is_giftcard = stripos($item['product_name'], 'Tarjeta de Regalo') !== false || 
                                                                                                  stripos($item['product_name'], 'Gift Card') !== false;
                                                                                    
                                                                                    // Verificar si el campo personalization_patch tiene el formato de datos de gift card
                                                                                    $is_giftcard_data = strpos($item['personalization_patch'], 'RCP:') === 0 || 
                                                                                                      strpos($item['personalization_patch'], '|MSG:') !== false ||
                                                                                                      strpos($item['personalization_patch'], '|SND:') !== false;
                                                                                    
                                                                                    if (!$is_giftcard && !$is_giftcard_data):
                                                                                    ?>
                                                                                    <div style="margin-top: 2px; color: #16a34a;">
                                                                                        <i class="fas fa-check-circle" style="margin-right: 2px;"></i>Parche
                                                                                    </div>
                                                                                    <?php endif; ?>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <span style="color: #6b7280;">-</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            
                                                            <tr class="order-summary-row">
                                                                <td colspan="6" class="text-right" style="text-align: right; font-weight: 500;">Subtotal:</td>
                                                                <td>$<?php echo number_format($order_subtotal, 2); ?></td>
                                                                <td></td>
                                                            </tr>
                                                            
                                                            <?php
                                                            // Verificar si hay notas de pago que indiquen descuento de gift card
                                                            $discount_amount = 0;
                                                            $has_discount = false;
                                                            $is_full_giftcard = $order['payment_method'] === 'giftcard';
                                                            
                                                            // Obtener notas de pago de la orden
                                                            $notes_stmt = $pdo->prepare("SELECT payment_notes FROM orders WHERE order_id = ?");
                                                            $notes_stmt->execute([$order['order_id']]);
                                                            $payment_notes = $notes_stmt->fetchColumn();
                                                            
                                                            if (!empty($payment_notes)) {
                                                                // Buscar información de descuento con gift card
                                                                if (preg_match('/Gift Card aplicada:.+\- Monto: \$([0-9.]+)/', $payment_notes, $matches)) {
                                                                    $discount_amount = floatval($matches[1]);
                                                                    $has_discount = true;
                                                                    
                                                                    // Si el descuento es igual o mayor al subtotal, ajustarlo al subtotal exacto
                                                                    if ($discount_amount >= $order_subtotal || abs($discount_amount - $order_subtotal) < 0.01) {
                                                                        $discount_amount = $order_subtotal;
                                                                    }
                                                                }
                                                                
                                                                // Buscar información de código promocional
                                                                $promo_discount = 0;
                                                                $has_promo = false;
                                                                $promo_code = '';
                                                                
                                                                if (preg_match('/Código promocional aplicado: ([A-Za-z0-9]+) - Descuento: \$([0-9.]+)/', $payment_notes, $matches)) {
                                                                    $promo_code = $matches[1];
                                                                    $promo_discount = floatval($matches[2]);
                                                                    $has_promo = true;
                                                                }
                                                                
                                                                // Mostrar fila de descuento de Gift Card si existe
                                                                if ($has_discount):
                                                            ?>
                                                                <tr class="discount-row">
                                                                    <td colspan="6" class="text-right" style="text-align: right; font-weight: 500; color:rgb(0, 0, 0);">
                                                                        Descuento (Gift Card):
                                                                    </td>
                                                                    <td style="color: #dc3545;">-$<?php echo number_format($discount_amount, 2); ?></td>
                                                                    <td></td>
                                                                </tr>
                                                            <?php 
                                                                endif;
                                                                
                                                                // Mostrar fila de descuento por código promocional si existe
                                                                if ($has_promo):
                                                            ?>
                                                                <tr class="discount-row" style="background-color: #FFFFFF; border: 1px solid #f0f0f0;">
                                                                    <td colspan="6" class="text-right" style="text-align: right; font-weight: 500; color:rgb(0, 0, 0);">
                                                                        Código Promocional <span style="font-weight: bold;"><?php echo $promo_code; ?></span>:
                                                                    </td>
                                                                    <td style="color: #FF0000; font-weight: 500;">-$<?php echo number_format($promo_discount, 2); ?></td>
                                                                    <td></td>
                                                                </tr>
                                                            <?php
                                                                endif;
                                                                
                                                                // Calcular el total final después de todos los descuentos
                                                                $total_discount = $discount_amount + $promo_discount;
                                                                $remaining = $order_subtotal - $total_discount;
                                                                $remaining = max(0, $remaining); // Asegurar que no sea negativo
                                                                
                                                                // Mostrar el total si hay algún descuento
                                                                if ($has_discount || $has_promo):
                                                                    // Mostrar el total solo si no es pago completo con gift card o si hay un monto restante
                                                                    if (!$is_full_giftcard || $remaining > 0):
                                                            ?>
                                                                <tr class="total-row">
                                                                    <td colspan="6" class="text-right" style="text-align: right; font-weight: 700; font-size: 1.1em;">TOTAL:</td>
                                                                    <td style="font-weight: 700; font-size: 1.1em;">$<?php echo number_format($remaining, 2); ?></td>
                                                                    <td></td>
                                                                </tr>
                                                            <?php 
                                                                    endif;
                                                                endif;
                                                            }
                                                            
                                                            // Si es pago completo con gift card, mostrar total como 0.00
                                                            if ($is_full_giftcard):
                                                            ?>
                                                                <tr class="total-row">
                                                                    <td colspan="6" class="text-right" style="text-align: right; font-weight: 700; font-size: 1.1em;">TOTAL PAGADO:</td>
                                                                    <td style="font-weight: 700; font-size: 1.1em;">$0.00</td>
                                                                    <td></td>
                                                                </tr>
                                                            <?php
                                                            // Si no hay descuento ni es pago con gift card, mostrar solo el total
                                                            elseif (!$has_discount && !isset($has_promo)):
                                                            ?>
                                                                <tr class="total-row">
                                                                    <td colspan="6" class="text-right" style="text-align: right; font-weight: 700; font-size: 1.1em;">TOTAL:</td>
                                                                    <td style="font-weight: 700; font-size: 1.1em;">$<?php echo number_format($order_subtotal, 2); ?></td>
                                                                    <td></td>
                                                                </tr>
                                                            <?php endif; ?>
                                                            <?php
                                                            // Verificar si hay descuento de promoción automática
                                                            $auto_promo_discount = 0;
                                                            $has_auto_promo = false;
                                                            $auto_promo_name = '';
                                                            if (!empty($payment_notes) && preg_match('/Promoción automática ([^:]+):? aplicada - Descuento: \$([0-9.]+)/', $payment_notes, $matches)) {
                                                                $auto_promo_name = trim($matches[1]);
                                                                $auto_promo_discount = floatval($matches[2]);
                                                                $has_auto_promo = true;
                                                            }
                                                            if ($has_auto_promo): ?>
                                                            <tr class="discount-row" style="background-color: #FFFFFF; border: 1px solid #f0f0f0;">
                                                                <td colspan="6" class="text-right" style="text-align: right; font-weight: 500; color:rgb(0, 0, 0);">
                                                                    Promoción automática <?php echo htmlspecialchars($auto_promo_name); ?>:
                                                                </td>
                                                                <td style="color: #FF0000; font-weight: 500;">-$<?php echo number_format($auto_promo_discount, 2); ?></td>
                                                                <td></td>
                                                            </tr>
                                                            <tr class="total-row">
                                                                <td colspan="6" class="text-right" style="text-align: right; font-weight: 700; font-size: 1.1em;">TOTAL:</td>
                                                                <td style="font-weight: 700; font-size: 1.1em;">$<?php echo number_format($order_subtotal - $auto_promo_discount, 2); ?></td>
                                                                <td></td>
                                                            </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                
                                                <?php if (!empty($payment_notes)): ?>
                                                <div class="payment-notes" style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
                                                    <h4 style="margin-top: 0; color: #007bff; font-size: 1rem;">Información de pago</h4>
                                                    <p style="margin-bottom: 0;"><?php echo nl2br(htmlspecialchars($payment_notes)); ?></p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal para agregar orden -->
    <div id="addOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Agregar Nueva Orden</h3>
                <span class="close" onclick="closeAddOrderModal()">&times;</span>
            </div>
            <form id="addOrderForm" onsubmit="submitOrder(event)">
                <div class="form-sections">
                    <div class="form-section">
                        <h4>Información del Cliente</h4>
                        <div class="form-group">
                            <label for="customer_name">Nombre*</label>
                            <input type="text" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="form-group">
                            <label for="customer_email">Email*</label>
                            <input type="email" id="customer_email" name="customer_email" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Teléfono (opcional)</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                    </div>

                    <div class="form-section">
                        <h4>Dirección de Envío</h4>
                        <div class="form-group">
                            <label for="street">Calle (opcional)</label>
                            <input type="text" id="street" name="street">
                        </div>
                        <div class="form-group">
                            <label for="colony">Colonia (opcional)</label>
                            <input type="text" id="colony" name="colony">
                        </div>
                        <div class="form-group">
                            <label for="city">Ciudad (opcional)</label>
                            <input type="text" id="city" name="city">
                        </div>
                        <div class="form-group">
                            <label for="state">Estado (opcional)</label>
                            <input type="text" id="state" name="state">
                        </div>
                        <div class="form-group">
                            <label for="zip_code">Código Postal (opcional)</label>
                            <input type="text" id="zip_code" name="zip_code">
                        </div>
                    </div>

                    <div class="form-section">
                        <h4>Productos</h4>
                        <div id="products-container">
                            <div class="product-entry">
                                <div class="form-group">
                                    <label>Producto*</label>
                                    <select name="products[]" class="product-select" required onchange="updatePrice(this)">
                                        <option value="">Seleccionar producto</option>
                                        <?php
                                        $products_stmt = $pdo->query("SELECT product_id, name, price FROM products WHERE status = 1 ORDER BY name");
                                        while ($product = $products_stmt->fetch()) {
                                            echo "<option value='{$product['product_id']}' data-price='{$product['price']}'>{$product['name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Talla*</label>
                                    <select name="sizes[]" required>
                                        <option value="">Seleccionar talla</option>
                                        <option value="XS">XS</option>
                                        <option value="S">S</option>
                                        <option value="M">M</option>
                                        <option value="L">L</option>
                                        <option value="XL">XL</option>
                                        <option value="XXL">XXL</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Cantidad*</label>
                                    <input type="number" name="quantities[]" min="1" value="1" required onchange="updateSubtotal(this)">
                                </div>
                                <div class="form-group">
                                    <label>Precio Unitario*</label>
                                    <input type="number" name="prices[]" step="0.01" required onchange="updateSubtotal(this)">
                                </div>
                                <div class="form-group">
                                    <label>Subtotal</label>
                                    <input type="number" class="subtotal" step="0.01" readonly>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addProductEntry()">
                            <i class="fas fa-plus"></i> Agregar Producto
                        </button>
                    </div>
                </div>

                <div class="form-footer">
                    <div class="total-section">
                        <h4>Total: $<span id="orderTotal">0.00</span></h4>
                    </div>
                    <div class="button-section">
                        <button type="button" class="btn btn-secondary" onclick="closeAddOrderModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Orden</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Agregar el modal de confirmación -->
    <div id="confirmDeleteModal" class="confirm-modal">
        <div class="confirm-modal-content">
            <h4><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h4>
            <p>¿Estás seguro de que deseas eliminar esta orden? Esta acción no se puede deshacer.</p>
            <div class="confirm-modal-buttons">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">Cancelar</button>
                <button class="btn btn-danger" onclick="confirmDelete()">Eliminar</button>
            </div>
        </div>
    </div>

    <!-- Agregar el modal de edición -->
    <div id="editOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Orden</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="editOrderForm" onsubmit="submitEditOrder(event)">
                <input type="hidden" id="edit_order_id" name="order_id">
                <div class="form-sections">
                    <div class="form-section">
                        <h4>Información del Cliente</h4>
                        <div class="form-group">
                            <label for="edit_customer_name">Nombre*</label>
                            <input type="text" id="edit_customer_name" name="customer_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_customer_email">Email*</label>
                            <input type="email" id="edit_customer_email" name="customer_email" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_phone">Teléfono (opcional)</label>
                            <input type="tel" id="edit_phone" name="phone">
                        </div>
                    </div>

                    <div class="form-section">
                        <h4>Dirección de Envío</h4>
                        <div class="form-group">
                            <label for="edit_street">Calle (opcional)</label>
                            <input type="text" id="edit_street" name="street">
                        </div>
                        <div class="form-group">
                            <label for="edit_colony">Colonia (opcional)</label>
                            <input type="text" id="edit_colony" name="colony">
                        </div>
                        <div class="form-group">
                            <label for="edit_city">Ciudad (opcional)</label>
                            <input type="text" id="edit_city" name="city">
                        </div>
                        <div class="form-group">
                            <label for="edit_state">Estado (opcional)</label>
                            <input type="text" id="edit_state" name="state">
                        </div>
                        <div class="form-group">
                            <label for="edit_zip_code">Código Postal (opcional)</label>
                            <input type="text" id="edit_zip_code" name="zip_code">
                        </div>
                    </div>

                    <div class="form-section">
                        <h4>Productos</h4>
                        <div id="edit_products_container">
                            <!-- Los productos se cargarán dinámicamente -->
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addEditProductEntry()">
                            <i class="fas fa-plus"></i> Agregar Producto
                        </button>
                    </div>
                </div>

                <div class="form-footer">
                    <div class="total-section">
                        <h4>Total: $<span id="editOrderTotal">0.00</span></h4>
                    </div>
                    <div class="button-section">
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleOrderDetails(orderId) {
            const details = document.getElementById(`order-details-${orderId}`);
            details.classList.toggle('active');
            
            const button = event.currentTarget;
            const icon = button.querySelector('i');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }
        
        function updateOrderStatus(select) {
            const orderId = select.getAttribute('data-order-id');
            const newStatus = select.value;
            const previousStatus = select.getAttribute('data-previous-status');
            
            // Remover clase anterior y agregar nueva clase
            select.classList.remove(`status-${previousStatus}`);
            select.classList.add(`status-${newStatus}`);
            
            fetch('update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}&status=${newStatus}&previous_status=${previousStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Estado del pedido actualizado correctamente');
                    
                    // Actualizar las estadísticas si se proporcionaron
                    if (data.stats) {
                        updateStats(data.stats);
                    }
                    
                    // Actualizar el estado anterior
                    select.setAttribute('data-previous-status', newStatus);
                } else {
                    // Si hay error, revertir el cambio de clase
                    select.classList.remove(`status-${newStatus}`);
                    select.classList.add(`status-${previousStatus}`);
                    throw new Error(data.message || 'Error al actualizar el estado del pedido');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', error.message || 'Error al actualizar el estado del pedido');
                // Revertir el cambio en el select
                select.value = previousStatus;
                // Revertir la clase
                select.classList.remove(`status-${newStatus}`);
                select.classList.add(`status-${previousStatus}`);
            });
        }
        
        function updateStats(stats) {
            const salesElement = document.querySelector('.stat-card:nth-child(1) .stat-card-value');
            const ordersElement = document.querySelector('.stat-card:nth-child(2) .stat-card-value');
            
            if (salesElement) {
                salesElement.textContent = '$' + parseFloat(stats.total_sales).toFixed(2);
            }
            if (ordersElement) {
                ordersElement.textContent = stats.total_orders;
            }
        }
        
        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = `notification ${type === 'success' ? 'success-message' : 'error-message'}`;
            notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.querySelector('.main-content').insertBefore(notification, document.querySelector('.panel'));
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        function openAddOrderModal() {
            document.getElementById('addOrderModal').style.display = 'block';
        }

        function closeAddOrderModal() {
            document.getElementById('addOrderModal').style.display = 'none';
        }

        function addProductEntry() {
            const container = document.getElementById('products-container');
            const entry = container.querySelector('.product-entry').cloneNode(true);
            
            // Limpiar valores
            entry.querySelectorAll('input').forEach(input => {
                input.value = '';
                if (input.name === 'prices[]') {
                    input.removeAttribute('readonly');
                }
            });
            entry.querySelector('select').selectedIndex = 0;
            
            // Agregar botón de eliminar
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger delete-product';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.onclick = function() {
                entry.remove();
                calculateTotal();
            };
            entry.appendChild(deleteBtn);
            
            container.appendChild(entry);
        }

        function updatePrice(select) {
            const price = select.options[select.selectedIndex].getAttribute('data-price');
            const entry = select.closest('.product-entry');
            const priceInput = entry.querySelector('input[name="prices[]"]');
            // Establecer el precio pero permitir editarlo
            priceInput.value = price;
            priceInput.removeAttribute('readonly');
            updateSubtotal(select);
        }

        function updateSubtotal(element) {
            const entry = element.closest('.product-entry');
            const quantity = entry.querySelector('input[name="quantities[]"]').value;
            const price = entry.querySelector('input[name="prices[]"]').value;
            const subtotal = quantity * price;
            entry.querySelector('.subtotal').value = subtotal.toFixed(2);
            calculateTotal();
        }

        function calculateTotal() {
            const subtotals = document.querySelectorAll('.subtotal');
            let total = 0;
            subtotals.forEach(input => {
                total += parseFloat(input.value || 0);
            });
            document.getElementById('orderTotal').textContent = total.toFixed(2);
        }

        function submitOrder(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            fetch('add_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Orden creada correctamente');
                    closeAddOrderModal();
                    location.reload(); // Recargar para mostrar la nueva orden
                } else {
                    throw new Error(data.message || 'Error al crear la orden');
                }
            })
            .catch(error => {
                showNotification('error', error.message);
            });
        }

        let orderToDelete = null;

        function deleteOrder(orderId) {
            orderToDelete = orderId;
            document.getElementById('confirmDeleteModal').style.display = 'block';
        }

        function closeConfirmModal() {
            document.getElementById('confirmDeleteModal').style.display = 'none';
            orderToDelete = null;
        }

        function confirmDelete() {
            if (!orderToDelete) return;

            fetch('delete_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderToDelete}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Orden eliminada correctamente');
                    // Actualizar las estadísticas si se proporcionaron
                    if (data.stats) {
                        updateStats(data.stats);
                    }
                    // Eliminar la fila de la tabla
                    const rows = document.querySelectorAll(`tr[data-order-id="${orderToDelete}"]`);
                    rows.forEach(row => row.remove());
                    closeConfirmModal();
                } else {
                    throw new Error(data.message || 'Error al eliminar la orden');
                }
            })
            .catch(error => {
                showNotification('error', error.message);
                closeConfirmModal();
            });
        }

        function editOrder(orderId) {
            // Cargar los datos de la orden
            fetch(`get_order.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fillEditForm(data.order);
                        document.getElementById('editOrderModal').style.display = 'block';
                    } else {
                        showNotification('error', data.message || 'Error al cargar la orden');
                    }
                })
                .catch(error => {
                    showNotification('error', 'Error al cargar la orden');
                });
        }

        function fillEditForm(order) {
            // Llenar campos del formulario
            document.getElementById('edit_order_id').value = order.order_id;
            document.getElementById('edit_customer_name').value = order.customer_name;
            document.getElementById('edit_customer_email').value = order.customer_email;
            document.getElementById('edit_phone').value = order.phone;
            document.getElementById('edit_street').value = order.street;
            document.getElementById('edit_colony').value = order.colony;
            document.getElementById('edit_city').value = order.city;
            document.getElementById('edit_state').value = order.state;
            document.getElementById('edit_zip_code').value = order.zip_code;

            // Limpiar y llenar contenedor de productos
            const container = document.getElementById('edit_products_container');
            container.innerHTML = '';

            order.items.forEach(item => {
                const entry = createProductEntry(item);
                container.appendChild(entry);
            });

            calculateEditTotal();
        }

        function createProductEntry(item = null) {
            const div = document.createElement('div');
            div.className = 'product-entry';
            div.innerHTML = `
                <div class="form-group">
                    <label>Producto*</label>
                    <select name="products[]" class="product-select" required onchange="updatePrice(this)">
                        <option value="">Seleccionar producto</option>
                        <?php
                        $products_stmt = $pdo->query("SELECT product_id, name, price FROM products WHERE status = 1 ORDER BY name");
                        while ($product = $products_stmt->fetch()) {
                            echo "<option value='{$product['product_id']}' data-price='{$product['price']}'>{$product['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Talla*</label>
                    <select name="sizes[]" required>
                        <option value="">Seleccionar talla</option>
                        <option value="XS">XS</option>
                        <option value="S">S</option>
                        <option value="M">M</option>
                        <option value="L">L</option>
                        <option value="XL">XL</option>
                        <option value="XXL">XXL</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cantidad*</label>
                    <input type="number" name="quantities[]" min="1" value="1" required onchange="updateSubtotal(this)">
                </div>
                <div class="form-group">
                    <label>Precio Unitario*</label>
                    <input type="number" name="prices[]" step="0.01" required onchange="updateSubtotal(this)">
                </div>
                <div class="form-group">
                    <label>Subtotal</label>
                    <input type="number" class="subtotal" step="0.01" readonly>
                </div>
            `;

            // Agregar botón de eliminar
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger delete-product';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.onclick = function() {
                div.remove();
                calculateEditTotal();
            };
            div.appendChild(deleteBtn);

            // Si hay datos del item, llenarlos
            if (item) {
                console.log('Item a cargar:', item); // Para depuración
                const select = div.querySelector('select[name="products[]"]');
                const sizeSelect = div.querySelector('select[name="sizes[]"]');
                const quantityInput = div.querySelector('input[name="quantities[]"]');
                const priceInput = div.querySelector('input[name="prices[]"]');

                select.value = item.product_id;
                sizeSelect.value = item.size;
                quantityInput.value = item.quantity;
                priceInput.value = item.price;
                updateSubtotal(quantityInput);
            }

            return div;
        }

        function addEditProductEntry() {
            const container = document.getElementById('edit_products_container');
            const entry = createProductEntry();
            container.appendChild(entry);
        }

        function closeEditModal() {
            document.getElementById('editOrderModal').style.display = 'none';
        }

        function calculateEditTotal() {
            const subtotals = document.querySelectorAll('#edit_products_container .subtotal');
            let total = 0;
            subtotals.forEach(input => {
                total += parseFloat(input.value || 0);
            });
            document.getElementById('editOrderTotal').textContent = total.toFixed(2);
        }

        function submitEditOrder(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            // Debug: mostrar datos que se enviarán
            console.log('Datos del formulario:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            fetch('update_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Orden actualizada correctamente');
                    closeEditModal();
                    location.reload();
                } else {
                    throw new Error(data.message || 'Error al actualizar la orden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', error.message);
            });
        }
    </script>
</body>
</html>