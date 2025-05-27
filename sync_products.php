<?php
// Script para sincronizar productos en la base de datos
// Este script debe ejecutarse manualmente para actualizar la base de datos

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'jersixmx';
$username = 'root';
$password = '';

try {
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Leer el archivo products-data.js
    $jsContent = file_get_contents('Js/products-data.js');
    
    // Extraer los datos de productos usando expresiones regulares
    preg_match_all('/{\s*id:\s*(\d+),\s*name:\s*"([^"]+)",\s*team:\s*"([^"]+)",\s*category:\s*"([^"]+)",\s*league:\s*"([^"]+)",\s*price:\s*"([^"]+)",\s*image:\s*"([^"]+)",\s*url:\s*"([^"]+)",\s*productId:\s*"([^"]+)"\s*}/', $jsContent, $matches, PREG_SET_ORDER);
    
    $products = [];
    foreach ($matches as $match) {
        $products[] = [
            'id' => $match[1],
            'name' => $match[2] . ' 24/25', // Agregar temporada al nombre
            'team' => $match[3],
            'category' => $match[4],
            'league' => $match[5],
            'price' => $match[6],
            'image' => $match[7],
            'url' => $match[8],
            'productId' => $match[9]
        ];
    }
    
    echo "Encontrados " . count($products) . " productos en products-data.js\n";
    
    // Verificar si la tabla products existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'products'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Crear la tabla products si no existe
        $pdo->exec("CREATE TABLE products (
            product_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            stock INT DEFAULT 0,
            image_url VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Tabla products creada\n";
    }
    
    // Insertar o actualizar productos
    $insertStmt = $pdo->prepare("INSERT INTO products (product_id, name, price, image_url) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price), image_url = VALUES(image_url)");
    
    foreach ($products as $product) {
        $insertStmt->execute([
            $product['id'],
            $product['name'],
            $product['price'],
            $product['image']
        ]);
        echo "Producto sincronizado: " . $product['name'] . " (ID: " . $product['id'] . ")\n";
    }
    
    echo "Sincronización completada con éxito\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 