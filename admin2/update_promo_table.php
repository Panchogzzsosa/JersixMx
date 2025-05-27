<?php
// Activar visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir configuración de base de datos
require_once '../config/database.php';

try {
    $conn = getConnection();
    
    // Modificar la columna tipo_descuento para incluir el nuevo valor 'auto'
    $sql = "ALTER TABLE codigos_promocionales MODIFY COLUMN tipo_descuento ENUM('porcentaje', 'fijo', 'paquete', 'auto') NOT NULL";
    $conn->exec($sql);
    
    echo "Tabla actualizada correctamente";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 