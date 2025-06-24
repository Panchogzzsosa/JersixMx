<?php
session_start();

// Verificar inicio de sesión
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getConnection();
    
    // Crear tabla product_inventory
    $sql = "
    CREATE TABLE IF NOT EXISTS `product_inventory` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `product_id` int(11) NOT NULL,
      `size` varchar(10) NOT NULL,
      `stock` int(11) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `product_size_unique` (`product_id`, `size`),
      FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($sql);
    echo "Tabla product_inventory creada exitosamente.<br>";
    
    // Verificar si ya existen datos en la tabla
    $stmt = $pdo->query("SELECT COUNT(*) FROM product_inventory");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Insertar tallas comunes para productos existentes
        $sizes = ['S', 'M', 'L', 'XL'];
        
        foreach ($sizes as $size) {
            $sql = "
            INSERT INTO `product_inventory` (`product_id`, `size`, `stock`) 
            SELECT 
                p.product_id,
                ?,
                CASE WHEN p.stock > 0 THEN FLOOR(p.stock / 4) ELSE 0 END
            FROM products p
            WHERE p.category != 'Gift Card'
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$size]);
        }
        
        echo "Datos iniciales insertados exitosamente.<br>";
    } else {
        echo "La tabla ya contiene datos. No se insertaron datos duplicados.<br>";
    }
    
    // Actualizar el stock total en la tabla products
    $sql = "
    UPDATE products 
    SET stock = (
        SELECT COALESCE(SUM(stock), 0) 
        FROM product_inventory 
        WHERE product_id = products.product_id
    )
    WHERE category != 'Gift Card'
    ";
    
    $pdo->exec($sql);
    echo "Stock total actualizado en la tabla products.<br>";
    
    echo "<br><strong>¡Tabla de inventario creada y configurada exitosamente!</strong><br>";
    echo "<a href='inventario.php'>Ir a Gestión de Inventario</a>";
    
} catch(PDOException $e) {
    die('Error: ' . $e->getMessage());
}
?> 