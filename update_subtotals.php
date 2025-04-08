<?php
// Script para actualizar el campo subtotal en todas las filas de order_items

// Incluir archivo de configuración de la base de datos
require_once __DIR__ . '/config/database.php';

try {
    // Conexión a la base de datos
    $pdo = getConnection();
    
    echo "Conectado a la base de datos. Iniciando actualización de subtotales...\n";
    
    // Obtener todas las filas en order_items
    $stmt = $pdo->query("SELECT order_item_id, quantity, price FROM order_items");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Se encontraron " . count($items) . " items para actualizar.\n";
    
    // Preparar la consulta de actualización
    $updateStmt = $pdo->prepare("UPDATE order_items SET subtotal = :subtotal WHERE order_item_id = :id");
    
    // Contador de filas actualizadas
    $updated = 0;
    
    // Actualizar cada fila
    foreach ($items as $item) {
        $subtotal = $item['quantity'] * $item['price'];
        $updateStmt->execute([
            ':subtotal' => $subtotal,
            ':id' => $item['order_item_id']
        ]);
        $updated++;
    }
    
    echo "Se actualizaron $updated items correctamente.\n";
    echo "Proceso completado con éxito.\n";
    
} catch (PDOException $e) {
    echo "Error en la base de datos: " . $e->getMessage() . "\n";
}
?> 