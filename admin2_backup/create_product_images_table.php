<?php
// Conexión a la base de datos
try {
    $pdo = new PDO('mysql:host=localhost;dbname=checkout', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear tabla de imágenes de productos
    $sql = "
    CREATE TABLE IF NOT EXISTS product_images (
        image_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "Tabla product_images creada correctamente";
    
} catch(PDOException $e) {
    die('Error: ' . $e->getMessage());
}
?> 