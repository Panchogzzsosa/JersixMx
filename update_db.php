<?php
// Archivo para actualizar la estructura de la base de datos
// Este script añade la columna de número de seguimiento a la tabla de pedidos

require_once 'config/database.php';

try {
    $pdo = getConnection();
    
    // Verificar si la columna ya existe
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'tracking_number'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // La columna no existe, la añadimos
        $pdo->exec("ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(100) NULL AFTER status");
        echo "Columna 'tracking_number' añadida correctamente a la tabla 'orders'.<br>";
    } else {
        echo "La columna 'tracking_number' ya existe en la tabla 'orders'.<br>";
    }
    
    // Verificar si la columna carrier_name ya existe
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'carrier_name'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // La columna no existe, la añadimos
        $pdo->exec("ALTER TABLE orders ADD COLUMN carrier_name VARCHAR(50) DEFAULT 'DHL' AFTER tracking_number");
        echo "Columna 'carrier_name' añadida correctamente a la tabla 'orders'.<br>";
    } else {
        echo "La columna 'carrier_name' ya existe en la tabla 'orders'.<br>";
    }
    
    // Verificar si la columna shipping_date ya existe
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'shipping_date'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // La columna no existe, la añadimos
        $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_date DATETIME NULL AFTER carrier_name");
        echo "Columna 'shipping_date' añadida correctamente a la tabla 'orders'.<br>";
    } else {
        echo "La columna 'shipping_date' ya existe en la tabla 'orders'.<br>";
    }
    
    echo "¡Actualización de la base de datos completada con éxito!";
    
} catch (PDOException $e) {
    die("Error al actualizar la base de datos: " . $e->getMessage());
}
?> 