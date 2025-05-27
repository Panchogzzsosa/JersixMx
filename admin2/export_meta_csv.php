<?php
session_start();

// Verificar inicio de sesión
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

// Obtener productos
try {
    $stmt = $pdo->query("SELECT * FROM products WHERE status = 1 ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die('Error al obtener productos: ' . $e->getMessage());
}

// Configurar headers para descarga de CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=meta_productos_' . date('Y-m-d') . '.csv');

// Crear archivo CSV
$output = fopen('php://output', 'w');

// Escribir BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Escribir encabezados
fputcsv($output, [
    'id',
    'title',
    'description',
    'availability',
    'condition',
    'price',
    'link',
    'image_link'
]);

// URL base del sitio
$base_url = 'https://jersix.mx/';

// Escribir datos de productos
foreach ($products as $product) {
    // Determinar disponibilidad
    $availability = 'out of stock';
    if ($product['stock'] > 0) {
        $availability = 'in stock';
    }

    // Construir URL del producto
    $product_url = $base_url . 'Productos-equipos/producto.php?id=' . $product['product_id'];
    
    // Construir URL de la imagen
    $image_url = !empty($product['image_url']) ? $base_url . $product['image_url'] : '';

    // Escribir fila de producto
    fputcsv($output, [
        $product['product_id'],
        $product['name'],
        $product['description'],
        $availability,
        'new', // Meta requiere 'new' para productos nuevos
        number_format($product['price'], 2, '.', '') . ' MXN',
        $product_url,
        $image_url
    ]);
}

// Cerrar archivo
fclose($output); 