<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Acceso denegado');
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
    $nombreProducto = strtolower($item['product_name']);
    $esJersey = (strpos($nombreProducto, 'jersey') !== false || 
                strpos($nombreProducto, 'camiseta') !== false ||
                strpos($nombreProducto, 'milan') !== false ||  // Agregar palabras clave comunes
                strpos($nombreProducto, 'manchester') !== false ||
                strpos($nombreProducto, 'barcelona') !== false ||
                strpos($nombreProducto, 'real madrid') !== false) &&
                strpos($nombreProducto, 'gift card') === false;
    
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
    // Conectar a la base de datos
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Actualizando precios de productos personalizados</h1>";
    
    // Función para registrar mensajes de depuración
    function writeLog($message) {
        echo "<div style='color: #666; font-family: monospace; margin: 2px 0; font-size: 13px;'>[DEBUG] $message</div>";
    }
    
    // Mostrar un ejemplo de cálculo
    echo "<div style='background: #f0f7ff; padding: 15px; margin-bottom: 20px; border: 1px solid #cce5ff; border-radius: 5px;'>";
    echo "<h3>Cómo se calculan los precios:</h3>";
    echo "<ul>";
    echo "<li><strong>Jersey básico:</strong> Precio base (ej. $799)</li>";
    echo "<li><strong>Jersey con nombre/número:</strong> Precio base + $100 (ej. $799 + $100 = $899)</li>";
    echo "<li><strong>Jersey con parche:</strong> Precio base + $50 (ej. $799 + $50 = $849)</li>";
    echo "<li><strong>Jersey con nombre/número y parche:</strong> Precio base + $100 + $50 (ej. $799 + $100 + $50 = $949)</li>";
    echo "</ul>";
    echo "<p>Este script actualizará todos los precios de jerseys personalizados en la base de datos según estas reglas.</p>";
    echo "</div>";
    
    // Obtener todas las órdenes
    $stmtOrdenes = $pdo->query("SELECT order_id FROM orders ORDER BY order_id");
    $ordenes = $stmtOrdenes->fetchAll(PDO::FETCH_COLUMN);
    
    $totalOrdenesActualizadas = 0;
    $totalProductosActualizados = 0;
    
    foreach ($ordenes as $orderId) {
        echo "<h2>Procesando orden #$orderId</h2>";
        
        // Obtener los productos de la orden
        $stmtItems = $pdo->prepare("
            SELECT oi.*, p.name as product_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            echo "<p>No se encontraron productos para esta orden.</p>";
            continue;
        }
        
        $totalOrden = 0;
        $ordenActualizada = false;
        
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>
              <tr>
                <th>Producto</th>
                <th>Precio Base</th>
                <th>Personalización</th>
                <th>Parche</th>
                <th>Precio Final</th>
                <th>Cantidad</th>
                <th>Subtotal</th>
                <th>Estado</th>
              </tr>";
        
        foreach ($items as $item) {
            $infoProducto = calcularPrecioReal($item);
            $totalOrden += $infoProducto['subtotal'];
            
            // Agregar logs de depuración detallados
            echo "<div class='debug-info' style='margin-bottom: 10px; padding: 8px; background: #f5f5f5; border-left: 3px solid #0066cc; font-family: monospace;'>";
            echo "<strong>Información detallada para {$item['product_name']} (ID: {$item['order_item_id']})</strong><br>";
            echo "¿Es jersey? " . ($infoProducto['es_jersey'] ? "<span style='color:green'>Sí</span>" : "<span style='color:red'>No</span>") . "<br>";
            echo "¿Tiene nombre/número? " . ($infoProducto['tiene_personalizacion'] ? "<span style='color:green'>Sí</span> (Nombre: '{$item['personalization_name']}', Número: '{$item['personalization_number']}')" : "<span style='color:red'>No</span>") . "<br>";
            echo "¿Tiene parche? " . ($infoProducto['tiene_parche'] ? "<span style='color:green'>Sí</span> (Valor: '{$item['personalization_patch']}')" : "<span style='color:red'>No</span>") . "<br>";
            echo "Precio base: $<span style='color:blue'>{$infoProducto['precio_base']}</span><br>";
            echo "Precio calculado: $<span style='color:green'>{$infoProducto['precio_final']}</span><br>";
            echo "Precio actual en BD: $<span style='color:" . ($item['price'] != $infoProducto['precio_final'] ? "red" : "green") . "'>{$item['price']}</span><br>";
            echo "</div>";
            
            echo "<tr>
                  <td>{$item['product_name']}</td>
                  <td>\${$infoProducto['precio_base']}</td>
                  <td>" . ($infoProducto['tiene_personalizacion'] ? "Sí (+\$100)" : "No") . "</td>
                  <td>" . ($infoProducto['tiene_parche'] ? "Sí (+\$50)" : "No") . "</td>
                  <td>\${$infoProducto['precio_final']}</td>
                  <td>{$item['quantity']}</td>
                  <td>\${$infoProducto['subtotal']}</td>";
            
            // Verificar si el precio actual es diferente del precio calculado o si es un producto que debería actualizarse
            $debeActualizarse = $item['price'] != $infoProducto['precio_final'];
            
            // SIEMPRE actualizar si es un jersey con personalización o parche, incluso si el precio parece igual
            if ($infoProducto['es_jersey'] && ($infoProducto['tiene_personalizacion'] || $infoProducto['tiene_parche'])) {
                $debeActualizarse = true;
                echo "<div style='color: orange; font-weight: bold;'>Forzando actualización de jersey personalizado</div>";
            }
            
            if ($debeActualizarse) {
                // Actualizar el precio del producto
                $stmtUpdatePrecio = $pdo->prepare("
                    UPDATE order_items 
                    SET price = ?, subtotal = ? 
                    WHERE order_item_id = ?
                ");
                $stmtUpdatePrecio->execute([
                    $infoProducto['precio_final'], 
                    $infoProducto['subtotal'], 
                    $item['order_item_id']
                ]);
                
                echo "<td style='color:green;'>Actualizado: \${$item['price']} → \${$infoProducto['precio_final']}</td>";
                $totalProductosActualizados++;
                $ordenActualizada = true;
            } else {
                echo "<td>Sin cambios</td>";
            }
            
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Obtener el total actual de la orden
        $stmtTotalActual = $pdo->prepare("SELECT total_amount FROM orders WHERE order_id = ?");
        $stmtTotalActual->execute([$orderId]);
        $totalActual = $stmtTotalActual->fetchColumn();
        
        echo "<p>Total actual en base de datos: \$$totalActual</p>";
        echo "<p>Total calculado: \$$totalOrden</p>";
        
        // Actualizar el total de la orden si ha cambiado
        if ($totalActual != $totalOrden && $ordenActualizada) {
            $stmtUpdateTotal = $pdo->prepare("
                UPDATE orders 
                SET total_amount = ? 
                WHERE order_id = ?
            ");
            $stmtUpdateTotal->execute([$totalOrden, $orderId]);
            
            echo "<p style='color:green;'>Total de la orden actualizado: \$$totalActual → \$$totalOrden</p>";
            $totalOrdenesActualizadas++;
        } else {
            echo "<p>El total de la orden no necesita actualización.</p>";
        }
        
        echo "<hr>";
    }
    
    echo "<h2>Resumen de la actualización</h2>";
    echo "<p>Órdenes procesadas: " . count($ordenes) . "</p>";
    echo "<p>Órdenes actualizadas: $totalOrdenesActualizadas</p>";
    echo "<p>Productos actualizados: $totalProductosActualizados</p>";
    
    if ($totalProductosActualizados > 0) {
        echo "<p style='color:green;'>¡Actualización completada! Los precios ahora reflejan correctamente las personalizaciones.</p>";
    } else {
        echo "<p>No se encontraron productos que necesiten actualización.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 