<?php
require_once '../config/database.php';

$sql = "CREATE TABLE IF NOT EXISTS codigos_promocionales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    descuento DECIMAL(10,2) NOT NULL,
    tipo_descuento ENUM('porcentaje', 'fijo') NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    usos_maximos INT NOT NULL,
    usos_actuales INT DEFAULT 0,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla de códigos promocionales creada exitosamente";
} else {
    echo "Error al crear la tabla: " . $conn->error;
}

$conn->close();
?> 