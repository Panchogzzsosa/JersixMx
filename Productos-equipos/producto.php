<?php
// Database connection
require_once __DIR__ . '/../config/database.php';

// Verificar si se ha proporcionado un ID de producto
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product_slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// Si no hay ID ni slug, intentar obtener el slug del producto de la URL
if (!$product_id && !$product_slug) {
    $request_uri = $_SERVER['REQUEST_URI'];
    $uri_parts = explode('/', $request_uri);
    $last_part = end($uri_parts);
    
    // Extraer el slug si la URL tiene formato producto-something
    if (strpos($last_part, 'producto-') === 0) {
        $product_slug = $last_part;
    }
    
    // Eliminar posibles parámetros de la URL
    if (strpos($product_slug, '?') !== false) {
        $product_slug = substr($product_slug, 0, strpos($product_slug, '?'));
    }
}

try {
    $pdo = getConnection();
    
    // Buscar producto por ID si está disponible
    if ($product_id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
        $stmt->execute([$product_id]);
    } else if (!empty($product_slug)) {
        // Intentar buscar por slug/nombre
        // Primero, convertimos el slug a un formato posible de nombre
        $product_name = str_replace('producto-', '', $product_slug);
        $product_name = str_replace('-', ' ', $product_name);
        
        // Buscar cualquier producto que contenga partes del nombre
        $stmt = $pdo->prepare('SELECT * FROM products WHERE name LIKE ? LIMIT 1');
        $stmt->execute(['%' . $product_name . '%']);
    } else {
        // Si no hay suficiente información, redirigir a la página de productos
        header('Location: ../productos.php');
        exit;
    }
    
    // Obtener el producto
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        // Si no se encontró el producto, redirigir a la página de productos
        header('Location: ../productos.php');
        exit;
    }
    
    // VERIFICAR SI EL PRODUCTO ESTÁ ACTIVO
    // Primero verificar si existe la columna status
    $hasStatusColumn = false;
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM products LIKE 'status'");
        $stmt->execute();
        $hasStatusColumn = ($stmt->rowCount() > 0);
    } catch (PDOException $e) {
        // Error al verificar la columna, asumimos que no existe
        $hasStatusColumn = false;
    }
    
    // Si la columna existe y el producto está inactivo, mostrar mensaje o redirigir
    if ($hasStatusColumn && isset($product['status']) && $product['status'] == 0) {
        // header('Location: ../productos.php?error=producto_inactivo');
        // exit;
        $product_inactive = true;
    }
    
    // Obtener datos del producto
    $product_name = $product['name'];
    $product_image = $product['image_url'];
    $stock = $product['stock'];
    $price = $product['price'];
    $product_id = $product['product_id'];
    $product_status = $hasStatusColumn ? $product['status'] : 1; // Por defecto activo si no existe la columna
    
    // Obtener stock por talla (aleatorio entre 2 y 8, fijo por una semana)
    $stock_by_size = [];
    $sizes = ['S', 'M', 'L', 'XL', 'XXL']; // Puedes ajustar las tallas aquí
    foreach ($sizes as $size) {
        // Buscar si ya existe un stock fake vigente
        $stmt = $pdo->prepare("SELECT stock, expires_at FROM product_stock_fake WHERE product_id = ? AND size = ?");
        $stmt->execute([$product_id, $size]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = new DateTime();
        $expires = $row ? new DateTime($row['expires_at']) : null;
        if ($row && $expires > $now) {
            // Si existe y no ha expirado, usar ese stock
            $stock_by_size[$size] = $row['stock'];
        } else {
            // Generar uno nuevo y guardarlo
            $random_stock = rand(2, 5);
            $expires_at = (new DateTime('+7 days'))->format('Y-m-d H:i:s');
            $pdo->prepare("REPLACE INTO product_stock_fake (product_id, size, stock, expires_at) VALUES (?, ?, ?, ?)")
                ->execute([$product_id, $size, $random_stock, $expires_at]);
            $stock_by_size[$size] = $random_stock;
        }
    }
    
    // Buscar imágenes adicionales en la base de datos (si existe una tabla de imágenes)
    $additional_images = [];
    try {
        $stmt_images = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
        $stmt_images->execute([$product_id]);
        $db_images = $stmt_images->fetchAll(PDO::FETCH_COLUMN);
        
        if ($db_images && count($db_images) > 0) {
            foreach ($db_images as $img) {
                if (!empty($img)) {
                    $additional_images[] = $img;
                }
            }
        }
    } catch (PDOException $e) {
        // Silenciar errores si la tabla no existe
    }
    
    // Asegurarse de que la ruta de la imagen sea completa
    if ($product_image) {
        // Si la ruta no comienza con http, / o ../, añadir el prefijo
        if (!preg_match('/^https?:\/\/|^\/|^\.\.\//', $product_image)) {
            $product_image = '../' . $product_image;
        }
        
        // Si la ruta no comienza con ../ y no es una URL absoluta
        if (!preg_match('/^https?:\/\//', $product_image) && !preg_match('/^\.\.\//', $product_image)) {
            $product_image = '../' . ltrim($product_image, '/');
        }
    } else {
        // Imagen por defecto si no hay imagen
        $product_image = '../img/default-product.jpg';
    }
    
    // Generar imágenes de miniatura y encontrar imágenes adicionales
    $thumbnails = [];
    
    // Asegurarse de que la imagen principal esté en los thumbnails
    $thumbnails[] = $product_image;
    
    // Añadir imágenes de la base de datos si están disponibles
    if (!empty($additional_images)) {
        foreach ($additional_images as $img) {
            // Verificar la ruta de la imagen
            if (!empty($img)) {
                // Si la ruta no comienza con http, / o ../, añadir el prefijo
                if (!preg_match('/^https?:\/\/|^\/|^\.\.\//', $img)) {
                    $img = '../' . $img;
                }
                
                // Si la ruta no comienza con ../ y no es una URL absoluta
                if (!preg_match('/^https?:\/\//', $img) && !preg_match('/^\.\.\//', $img)) {
                    $img = '../' . ltrim($img, '/');
                }
                
                if (!in_array($img, $thumbnails)) {
                    $thumbnails[] = $img;
                }
            }
        }
    }
    
    // Buscar imágenes relacionadas con el equipo
    $team_name = '';
    
    // Extraer nombre del equipo del nombre del producto
    $product_name_lower = strtolower($product_name);
    $teams = [
        'barcelona' => ['barca', 'barcelona'],
        'real madrid' => ['real madrid', 'madrid'],
        'manchester city' => ['manchester city', 'manchester c'],
        'bayern munich' => ['bayern', 'munchen', 'munich'],
        'milan' => ['milan', 'ac milan'],
        'psg' => ['psg', 'paris'],
        'tigres' => ['tigres'],
        'rayados' => ['rayados', 'monterrey'],
        'america' => ['america', 'águilas'],
        'chivas' => ['chivas', 'guadalajara'],
        'cruz azul' => ['cruz azul'],
        'seleccion mexicana' => ['seleccion', 'mexico', 'tri']
    ];
    
    foreach ($teams as $team => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($product_name_lower, $keyword) !== false) {
                $team_name = $team;
                break 2;
            }
        }
    }
    
    // Buscar imágenes relacionadas con el equipo
    if (!empty($team_name)) {
        $team_name_formatted = str_replace(' ', '', ucwords($team_name));
        
        // Patrones de nombre de archivo a buscar (adaptado a tu estructura de archivos)
        $patterns = [
            $team_name_formatted . 'Local.jpg',
            $team_name_formatted . 'Local.png',
            $team_name_formatted . 'Local.webp',
            $team_name_formatted . '1.jpg',
            $team_name_formatted . '1.png',
            $team_name_formatted . '1.webp',
            $team_name_formatted . '2.jpg',
            $team_name_formatted . '2.png',
            $team_name_formatted . '2.webp',
            $team_name_formatted . '3.jpg',
            $team_name_formatted . '3.png',
            $team_name_formatted . '3.webp'
        ];
        
        // Variaciones adicionales para algunos equipos
        if ($team_name == 'barcelona') {
            $patterns[] = 'Barca2.webp';
            $patterns[] = 'Barca3.png';
        } elseif ($team_name == 'real madrid') {
            $patterns[] = 'RealM2.png';
            $patterns[] = 'RealM3.png';
        } elseif ($team_name == 'manchester city') {
            $patterns[] = 'ManchesterCity.png';
            $patterns[] = 'ManchsterC2.png';
            $patterns[] = 'ManchesterC3.webp';
        } elseif ($team_name == 'bayern munich') {
            $patterns[] = 'BayerMunchenLocal.jpg';
            $patterns[] = 'BayernMunchen1.jpg';
            $patterns[] = 'BayernMunchen2.jpg';
        }
        
        $jersey_dir = $_SERVER['DOCUMENT_ROOT'] . '/img/Jerseys/';
        
        // Buscar en directorio de Jerseys
        foreach ($patterns as $pattern) {
            if (file_exists($jersey_dir . $pattern)) {
                $image_path = '../img/Jerseys/' . $pattern;
                if (!in_array($image_path, $thumbnails)) {
                    $thumbnails[] = $image_path;
                }
            }
        }
        
        // Si no se encuentran imágenes adicionales, buscar por nombre parcial
        if (count($thumbnails) <= 1) {
            $dir_contents = scandir($jersey_dir);
            foreach ($dir_contents as $file) {
                if ($file == '.' || $file == '..' || $file == '.DS_Store') continue;
                
                $file_lower = strtolower($file);
                foreach ($keywords as $keyword) {
                    $keyword_lower = strtolower($keyword);
                    if (strpos($file_lower, $keyword_lower) !== false) {
                        $image_path = '../img/Jerseys/' . $file;
                        if (!in_array($image_path, $thumbnails)) {
                            $thumbnails[] = $image_path;
                        }
                    }
                }
            }
        }
    }
    
    // Si todavía no hay suficientes imágenes, intentar buscar por formato numérico
    if (count($thumbnails) <= 1) {
        $image_base = pathinfo($product_image, PATHINFO_FILENAME);
        $image_ext = pathinfo($product_image, PATHINFO_EXTENSION);
        $image_dir = pathinfo($product_image, PATHINFO_DIRNAME);
        
        for ($i = 2; $i <= 5; $i++) {
            $possible_thumb = $image_dir . '/' . $image_base . $i . '.' . $image_ext;
            // Verificar si el archivo existe en el servidor
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . str_replace('../', '/', $possible_thumb))) {
                $thumbnails[] = $possible_thumb;
            }
        }
    }
    
    // Limitar a 5 imágenes para no sobrecargar la página
    $thumbnails = array_slice($thumbnails, 0, 5);
    
    // Eliminar duplicados
    $thumbnails = array_unique($thumbnails);
    
    // Añadir información de depuración (solo en desarrollo)
    $debug_info = [];
    $debug_info['product_id'] = $product_id;
    $debug_info['product_name'] = $product_name;
    $debug_info['product_image'] = $product_image;
    $debug_info['thumbnails'] = $thumbnails;
    $debug_info['team_name'] = $team_name ?? 'No detectado';
    
    // Generar título para la página
    $page_title = $product_name . ' - JerseyZone';
    
} catch(PDOException $e) {
    // En caso de error, establecer valores por defecto
    $product_name = "Producto no encontrado";
    $product_image = "../img/default-product.jpg";
    $stock = 0;
    $price = '0.00';
    $product_id = 0;
    $thumbnails = [$product_image];
    $page_title = "Producto no encontrado - JerseyZone";
    
    error_log("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($product_name) ? htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8') : 'Detalle de Producto'; ?> | JersixMx</title>
    <link rel="stylesheet" href="../Css/index.css">
    <link rel="stylesheet" href="../Css/productos.css">
    <link rel="stylesheet" href="../Css/cart.css">
    <link rel="stylesheet" href="../Css/notificacion.css">
    <link rel="stylesheet" href="../Css/notificacion.css">
    <link rel="stylesheet" href="../Css/producto-mobile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <script src="../Js/search.js" defer></script>
    <script src="../Js/products-data.js" defer></script>
    <script src="../Js/cart.js" defer></script>
    <script src="../Js/notification.js"></script>
    <script src="../Js/newsletter.js" defer></script>
    <script src="../Js/producto-mobile.js" defer></script>
    <style>
        /* Estilos generales de la página de producto */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #fff;
            font-size: 16px;
            margin: 0;
            padding: 0;
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        main {
            margin-top: 60px; /* Aumentado el margen superior */
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            padding: 30px;
        }

        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-top: 30px;
            margin-bottom: 30px; /* Reducido de 60px a 30px */
            position: relative;
        }

        .product-image-container {
            position: sticky;
            top: 30px;
            height: fit-content;
            margin-bottom: 30px;
            overflow: visible;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .main-image-container {
            position: relative;
            height: 650px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .product-image {
            width: 100%;
            height: auto;
            max-height: 650px;
            object-fit: contain;
            cursor: default;
        }

        .product-thumbnails {
            display: flex;
            gap: 10px; /* Reducido de 15px a 10px */
            justify-content: center;
            flex-wrap: wrap;
            width: 100%;
            padding: 0 15px;
            margin-top: -10px; /* Añadido margen negativo para subir las miniaturas */
        }

        .thumbnail {
            width: 75px;
            height: 75px;
            object-fit: cover;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 10px;
            padding: 2px;
        }

        .thumbnail.active {
            border: 2px solid #333; /* Borde más fino para la miniatura activa */
        }

        /* Botones de navegación de imágenes - Solo flechas */
        .image-navigation {
            position: absolute;
            top: 45%;
            left: 0;
            right: 0;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-50%);
            padding: 0 15px;
            pointer-events: none;
            z-index: 10;
        }

        .prev-image, .next-image {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto;
            transition: background-color 0.3s ease;
        }

        .prev-image:hover, .next-image:hover {
            background: rgba(255, 255, 255, 1);
        }

        .prev-image i, .next-image i {
            font-size: 24px;
            color: #333;
        }

        /* Estilos de información del producto */
        .product-info {
            height: fit-content;
        }

        .product-title {
            font-size: 32px; /* Tamaño de título aumentado */
            font-weight: 600;
            margin-bottom: 25px; /* Margen inferior aumentado */
            color: #000;
            line-height: 1.3;
        }

        .product-price {
            font-size: 25px; /* Tamaño de precio aumentado */
            font-weight: 500; /* Peso de fuente disminuido de bold (700) a medium (500) */
            margin-bottom: 30px; /* Margen inferior aumentado */
            color: #000; /* Color cambiado de #000 (negro) a #555 (gris más claro) */
            letter-spacing: 0.5px; /* Ligero espaciado entre letras para mejor visualización */
        }

        .shipping-info {
            display: flex;
            align-items: center;
            margin-bottom: 35px; /* Margen inferior aumentado */
            color: #666;
            font-size: 18px; /* Tamaño de texto aumentado */
            border-bottom: 1px solid #eee;
            padding-bottom: 20px; /* Padding inferior aumentado */
        }

        .shipping-info p {
            margin: 0;
        }

        /* Selectores de talla y cantidad */
        .section-title {
            font-size: 20px; /* Tamaño de título de sección aumentado */
            font-weight: bold;
            margin-bottom: 20px; /* Margen inferior aumentado */
            margin-top: 30px; /* Margen superior añadido */
            color: #333;
        }

        .size-guide-btn {
            background: none;
            border: none;
            color: #0066cc;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
            font-size: 16px; /* Tamaño de texto aumentado */
            margin-left: 15px; /* Margen izquierdo aumentado */
        }

        .size-options {
            display: flex;
            gap: 15px; /* Gap aumentado */
            margin-bottom: 40px; /* Margen inferior aumentado */
        }

        .size-option {
            width: 60px; /* Tamaño aumentado */
            height: 60px; /* Tamaño aumentado */
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            cursor: pointer;
            font-size: 18px; /* Tamaño de texto aumentado */
            transition: all 0.3s ease;
        }

        .size-option:hover {
            border-color: #333;
        }

        .size-option.selected {
            background-color: #000;
            color: #fff;
            border-color: #000;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            margin-bottom: 30px; /* Margen inferior aumentado */
        }

        .quantity-btn {
            width: 45px; /* Tamaño aumentado */
            height: 45px; /* Tamaño aumentado */
            background: #f5f5f5;
            border: 1px solid #ddd;
            font-size: 22px; /* Tamaño de texto aumentado */
            cursor: pointer;
        }

        .quantity-input {
            width: 70px; /* Anchura aumentada */
            height: 45px; /* Altura aumentada */
            border: 1px solid #ddd;
            text-align: center;
            margin: 0 12px; /* Margen horizontal aumentado */
            font-size: 20px; /* Tamaño de texto aumentado */
        }

        .stock-info {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            padding: 18px; /* Padding aumentado */
            border-radius: 6px; /* Radio de borde aumentado */
            margin-bottom: 40px; /* Margen inferior aumentado */
            font-size: 17px; /* Tamaño de texto aumentado */
            color: #555;
        }

        .stock-info i {
            margin-right: 12px; /* Margen derecho aumentado */
            font-size: 20px; /* Tamaño de icono aumentado */
        }

        /* Botón de compra */
        .add-to-cart-btn {
            width: 100%;
            background-color: #000;
            color: white;
            border: none;
            padding: 20px; /* Padding aumentado */
            font-size: 20px; /* Tamaño de texto aumentado */
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 20px; /* Margen superior añadido */
        }

        .add-to-cart-btn:hover {
            background-color: #333;
        }

        .add-to-cart-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.65;
        }

        /* Modal de guía de tallas */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: auto;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            position: relative;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            animation: modalFadeIn 0.3s ease;
            margin: 0;
        }

        .close {
            color: #333;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            right: 20px;
            top: 15px;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }
        
        .modal-content h2 {
            margin-bottom: 20px;
            text-align: center;
            font-size: 28px;
            color: #333;
        }

        .size-chart {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border: none;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 0 1px #eee;
        }

        .size-chart th,
        .size-chart td {
            border: none;
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .size-chart th {
            background-color: #000;
            color: #fff;
            font-weight: 600;
            font-size: 16px;
        }

        .size-chart td {
            font-size: 15px;
        }

        .size-chart tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .size-chart tr:hover {
            background-color: #f2f2f2;
        }

        .size-chart tr:last-child td {
            border-bottom: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .product-title {
                font-size: 26px;
            }
            
            .thumbnail {
                width: 60px;
                height: 60px;
            }

            .image-navigation {
                display: none;
            }

            /* Estilos de reseñas para móvil */
            .reviews-section {
                padding: 0 15px !important;
            }

            .reviews-container {
                max-width: 100% !important;
            }

            .reviews-carousel {
                margin: 0 !important;
                overflow-x: scroll !important;
                -webkit-overflow-scrolling: touch !important;
                scroll-snap-type: x mandatory !important;
                scrollbar-width: none !important; /* Firefox */
                -ms-overflow-style: none !important; /* IE/Edge */
            }

            .reviews-carousel::-webkit-scrollbar {
                display: none !important; /* Chrome/Safari/Opera */
            }

            .reviews-track {
                display: flex !important;
                gap: 0 !important;
                padding: 10px 0 !important;
                scroll-behavior: smooth !important;
                touch-action: pan-x !important;
                will-change: transform !important;
            }

            .review-card {
                min-width: 100% !important;
                flex: 0 0 100% !important;
                margin: 0 !important;
                padding: 20px !important;
                scroll-snap-align: center !important;
                scroll-snap-stop: always !important;
                user-select: none !important;
                -webkit-user-select: none !important;
            }

            #prevReview, #nextReview {
                display: none !important;
            }

            .review-gallery .images-row {
                gap: 5px !important;
            }

            .review-gallery .images-row img {
                width: 50px !important;
                height: 50px !important;
            }

            body {
                padding-top: 60px;
            }

            main {
                margin-top: 380px;
                max-width: 1200px;
                margin-left: auto;
                margin-right: auto;
                padding: 30px;
            }
        }

        /* Botón de WhatsApp */
        .whatsapp-button {
            position: fixed;
            bottom: 30px; /* Posición inferior aumentada */
            right: 30px; /* Posición derecha aumentada */
            z-index: 999;
        }

        .whatsapp-button a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 70px; /* Tamaño aumentado */
            height: 70px; /* Tamaño aumentado */
            background-color: #25D366;
            color: white;
            border-radius: 50%;
            font-size: 35px; /* Tamaño de icono aumentado */
            text-decoration: none;
            box-shadow: 0 3px 8px rgba(0,0,0,0.3); /* Sombra mejorada */
        }

        /* Eliminar cualquier indicador de zoom que pudiera existir */
        .zoom-indicator {
            display: none;
        }

        /* Estilos para indicador de estado y mensaje de inactividad */
        .product-inactive-message {
            margin-bottom: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .alert i {
            font-size: 24px;
        }
        
        .admin-product-status {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-indicator {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .edit-product-link {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .edit-product-link:hover {
            text-decoration: underline;
        }
    </style>
    <style>
        /* Animación para el modal */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Estilo para la modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 1000;
            overflow: auto;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            position: relative;
            background-color: #fff;
            max-width: 500px;
            width: 85%;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s ease;
            margin: 20px 0;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 15px !important;
        }
        
        .modal h2 {
            font-size: 20px !important;
            margin-bottom: 15px !important;
        }
        
        .modal label {
            font-size: 14px !important;
            margin-bottom: 5px !important;
        }
        
        .modal input[type="text"],
        .modal textarea {
            padding: 8px !important;
            font-size: 14px !important;
        }
        
        .modal .rating-input label {
            font-size: 24px !important;
            margin-right: 3px !important;
        }
        
        .modal button[type="submit"] {
            padding: 10px 15px !important;
            font-size: 15px !important;
        }
        
        /* Ajuste para pantallas más pequeñas */
        @media (max-height: 700px) {
            .modal-content {
                max-height: 85vh;
                padding: 15px;
            }
            
            .form-group {
                margin-bottom: 10px !important;
            }
        }
    </style>
    <style>
        /* Sistema de calificación con estrellas - Versión simplificada */
        .rating-container {
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .stars {
            display: flex;
            flex-direction: row;
            font-size: 45px; /* Estrellas más grandes */
        }
        
        .stars input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .stars label {
            cursor: pointer;
            color: #ddd;
            padding: 0 3px; /* Más espacio entre estrellas */
        }
        
        .stars label.active,
        .stars label.hovered {
            color: #FFD700;
        }
        
        .rating-text {
            margin-top: 10px;
            font-size: 16px; /* Texto un poco más grande */
            color: #666;
        }
    </style>
    <style>
    .lightbox-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    .lightbox-overlay.active {
        display: flex;
    }
    .lightbox-img {
        max-width: 90vw;
        max-height: 80vh;
        border-radius: 10px;
        box-shadow: 0 4px 32px rgba(0,0,0,0.4);
        background: #fff;
    }
    .lightbox-close {
        position: absolute;
        top: 30px;
        right: 40px;
        font-size: 2.5em;
        color: #fff;
        background: none;
        border: none;
        cursor: pointer;
        z-index: 10000;
    }
    </style>
    <style>
        /* ...otros estilos... */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0;
            width: 100%;
        }
        .add-to-cart-btn, .buy-now-btn {
            width: 100%;
            padding: 20px;
            font-size: 20px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 6px;
            text-align: center;
            font-weight: 500;
        }
        .add-to-cart-btn {
            background-color: #000;
            color: white;
        }
        .add-to-cart-btn:hover {
            background-color: #333;
        }
        .buy-now-btn {
            background-color: #CB1010;
            color: white;
        }
        .buy-now-btn:hover {
            background-color: #ff3333;
        }
        .add-to-cart-btn:disabled, .buy-now-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.65;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <button class="menu-toggle">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="logo"><a href="../index.php"><img src="../img/LogoNav.png" alt="JerSix Logo"></a></div>
            <ul class="nav-links">
                <li></li>
                <li></li>
                <li></li>
                <li><a href="../index">Inicio</a></li>
                <li><a href="../productos">Productos</a></li>
                <li><a href="../mistery-box">Mystery Box</a></li>
                <li><a href="../giftcard">Giftcard</a></li>
                <li><a href="tracking.php">Seguimiento</a></li>
            </ul>
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Buscar productos..." id="searchInput">
                <button class="search-button" onclick="performSearch(document.querySelector('.search-input').value)">
                    <span class="material-symbols-outlined">search</span>
                </button>
                <div class="search-results" id="searchResults"></div>
            </div>
            <div class="cart-icon">
                <span class="material-symbols-outlined">shopping_cart</span>
            </div>
        </nav>
    </header>

    <main>
        <?php if (isset($product_inactive) && $product_inactive): ?>
        <div class="product-inactive-message">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Este producto está actualmente inactivo y no es visible para los clientes.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php
        // Verificar si el usuario es administrador (condición simplificada, ajustar según tu sistema)
        $isAdmin = isset($_SESSION['admin_id']);
        
        // Mostrar indicador de estado solo para administradores
        if ($isAdmin):
        ?>
        <div class="admin-product-status">
            <span class="status-indicator <?php echo $product_status ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $product_status ? 'Producto Activo' : 'Producto Inactivo'; ?>
            </span>
            <a href="../admin2/edit_product.php?id=<?php echo $product_id; ?>" class="edit-product-link">
                <i class="fas fa-edit"></i> Editar Producto
            </a>
        </div>
        <?php endif; ?>
        
        <div class="product-detail">
            <div class="product-image-container">
                <div class="main-image-container">
                    <img src="<?php echo $product_image; ?>" alt="<?php echo htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8'); ?>" class="product-image" id="mainImage" loading="lazy">
                </div>
                
                <?php if (count($thumbnails) > 1): ?>
                <div class="image-navigation">
                    <button class="prev-image" onclick="changeImageNav(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button class="next-image" onclick="changeImageNav(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                
                <div class="product-thumbnails">
                    <?php foreach ($thumbnails as $index => $thumbnail): ?>
                    <img src="<?php echo $thumbnail; ?>" alt="<?php echo htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8'); ?> <?php echo $index+1; ?>" 
                         class="thumbnail <?php echo ($index === 0) ? 'active' : ''; ?>" 
                         onclick="changeImage(this)" loading="lazy">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="product-info">
                <h1 class="product-title"><?php echo htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="product-price" data-product-id="<?php echo $product_id; ?>">$ <?php echo number_format($price, 2); ?></p>
                
                <div class="shipping-info">
                    <p>Envío gratis a TODO MÉXICO 🇲🇽</p>
                </div>
                
                <div class="section-title">
                    Talla <button class="size-guide-btn" onclick="document.getElementById('sizeGuideModal').style.display='block'">Guía de tallas</button>
                </div>
                <div class="size-options">    
                    <div class="size-option">S</div>
                    <div class="size-option">M</div>
                    <div class="size-option">L</div>
                    <div class="size-option">XL</div>
                    <div class="size-option">XXL</div>
                </div>

                <div class="section-title">Personalizar Camiseta</div>
                <div class="customization-toggle" style="margin-bottom: 15px;">
                    <select id="customizationSelect" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" width="292.4" height="292.4"><path fill="%23007CB2" d="M287 69.4a17.6 17.6 0 0 0-13-5.4H18.4c-5 0-9.3 1.8-12.9 5.4A17.6 17.6 0 0 0 0 82.2c0 5 1.8 9.3 5.4 12.9l128 127.9c3.6 3.6 7.8 5.4 12.8 5.4s9.2-1.8 12.8-5.4L287 95c3.5-3.6 5.4-7.9 5.4-12.9 0-5-1.9-9.2-5.5-12.7z"/></svg>'); background-repeat: no-repeat; background-position: right 12px center; background-size: 12px;">
                        <option value="no">Sin Personalizar</option>
                        <option value="yes">Personalizar (+$100)</option>
                    </select>
                </div>
                <div class="customization-options" id="customizationFields" style="margin-bottom: 30px; display: none;">
                    <div class="customization-field" style="margin-bottom: 15px;">
                        <label for="jerseyName" style="display: block; margin-bottom: 8px; font-weight: 500;">Nombre en la camiseta:</label>
                        <input type="text" id="jerseyName" name="jerseyName" maxlength="10" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px;" placeholder="Ingresa el nombre" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ ]+" inputmode="text" autocomplete="off">
                        <p style="font-size: 13px; color: #888; margin-top: 5px;">Máximo 10 caracteres permitidos.</p>
                    </div>
                    <div class="customization-field" style="margin-bottom: 15px;">
                        <label for="jerseyNumber" style="display: block; margin-bottom: 8px; font-weight: 500;">Número:</label>
                        <input type="number" id="jerseyNumber" name="jerseyNumber" min="0" max="99" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px;" placeholder="Ingresa el número" oninput="validateJerseyNumber(this)" pattern="[0-9]+" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="customization-field" style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="patchOption" name="patchOption" style="margin-right: 10px;">
                            <span>Agregar parche (+$50)</span>
                        </label>
                    </div>
                </div>

                <div class="section-title">Cantidad</div>
                <div class="quantity-controls">
                    <button class="quantity-btn minus" id="minusBtn">-</button>
                    <input type="number" class="quantity-input" id="quantityInput" value="1" min="1" max="<?php echo $stock; ?>">
                    <button class="quantity-btn plus" id="plusBtn">+</button>
                </div>

                <div class="stock-info" id="stockInfo">
                    <!-- El mensaje se llenará dinámicamente con JS -->
                </div>
                
                <div class="action-buttons">
                    <button class="add-to-cart-btn" <?php echo (!$product_status) ? 'disabled' : ''; ?> onclick="addToCartWithCustomization()">
                        <?php if ($product_status): ?>
                            Agregar al Carrito
                        <?php else: ?>
                            Producto no disponible
                        <?php endif; ?>
                    </button>
                    <button class="buy-now-btn" <?php echo (!$product_status) ? 'disabled' : ''; ?> onclick="buyNowDirect()" style="margin-top: 15px;">
                        <?php if ($product_status): ?>
                            Comprar Ahora
                        <?php else: ?>
                            Producto no disponible
                        <?php endif; ?>
                    </button>
                </div>

                <script>
                function addToCartWithCustomization() {
                    const selectedSize = document.querySelector('.size-option.selected');
                    if (!selectedSize) {
                        showNotification('Por favor selecciona una talla', false);
                        return;
                    }

                    // Verificar si la personalización está habilitada
                    const isCustomizationEnabled = document.getElementById('customizationSelect').value === 'yes';
                    
                    // Obtener datos de personalización
                    const personalization = {
                        name: '',
                        number: '',
                        patch: false
                    };
                    
                    if (isCustomizationEnabled) {
                        personalization.name = document.getElementById('jerseyName').value.trim();
                        personalization.number = document.getElementById('jerseyNumber').value.trim();
                        personalization.patch = document.getElementById('patchOption').checked;
                        
                        // Validar que se hayan ingresado tanto el nombre como el número
                        if (!personalization.name || !personalization.number) {
                            showNotification('Para personalizar la camiseta, debes ingresar tanto el nombre como el número.', false);
                            return;
                        }
                    }

                    // Crear el objeto de producto
                    const productData = {
                        productId: document.querySelector('.product-price').getAttribute('data-product-id'),
                        title: document.querySelector('.product-title').textContent,
                        price: currentPrice,
                        size: selectedSize.textContent,
                        quantity: parseInt(document.getElementById('quantityInput').value),
                        image: document.getElementById('mainImage').src,
                        personalization: isCustomizationEnabled ? personalization : null,
                        isCustomEvent: true
                    };

                    // Disparar evento personalizado
                    const event = new CustomEvent('addToCart', {
                        detail: productData
                    });
                    document.dispatchEvent(event);
                }

                function buyNowDirect() {
                    const selectedSize = document.querySelector('.size-option.selected');
                    if (!selectedSize) {
                        showNotification('Por favor selecciona una talla', false);
                        return;
                    }

                    // Verificar si la personalización está habilitada
                    const isCustomizationEnabled = document.getElementById('customizationSelect').value === 'yes';
                    
                    // Obtener datos de personalización
                    const personalization = {
                        name: '',
                        number: '',
                        patch: false
                    };
                    
                    if (isCustomizationEnabled) {
                        personalization.name = document.getElementById('jerseyName').value.trim();
                        personalization.number = document.getElementById('jerseyNumber').value.trim();
                        personalization.patch = document.getElementById('patchOption').checked;
                        
                        // Validar que se hayan ingresado tanto el nombre como el número
                        if (!personalization.name || !personalization.number) {
                            showNotification('Para personalizar la camiseta, debes ingresar tanto el nombre como el número.', false);
                            return;
                        }
                    }

                    // Crear el objeto de producto
                    const productData = {
                        productId: document.querySelector('.product-price').getAttribute('data-product-id'),
                        title: document.querySelector('.product-title').textContent,
                        price: currentPrice,
                        size: selectedSize.textContent,
                        quantity: parseInt(document.getElementById('quantityInput').value),
                        image: document.getElementById('mainImage').src,
                        personalization: isCustomizationEnabled ? personalization : null,
                        isCustomEvent: true
                    };

                    // Disparar evento personalizado para que el carrito se actualice correctamente
                    const event = new CustomEvent('addToCart', {
                        detail: productData
                    });
                    document.dispatchEvent(event);

                    // Redirigir al checkout
                    window.location.href = '/checkout.html';
                }
                </script>
            </div>
        </div>
        
        <!-- Size Guide Modal -->
        <div id="sizeGuideModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('sizeGuideModal').style.display='none'">&times;</span>
                <h2>Guía de Tallas</h2>
                <table class="size-chart">
                    <thead>
                        <tr>
                            <th>Talla</th>
                            <th>Largo (cm)</th>
                            <th>Ancho (cm)</th>
                            <th>Altura (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>S</td>
                            <td>69-71</td>
                            <td>53-55</td>
                            <td>162-170</td>
                        </tr>
                        <tr>
                            <td>M</td>
                            <td>71-73</td>
                            <td>55-57</td>
                            <td>170-176</td>
                        </tr>
                        <tr>
                            <td>L</td>
                            <td>73-75</td>
                            <td>57-58</td>
                            <td>176-182</td>
                        </tr>
                        <tr>
                            <td>XL</td>
                            <td>75-78</td>
                            <td>58-60</td>
                            <td>182-190</td>
                        </tr>
                        <tr>
                            <td>XXL</td>
                            <td>78-81</td>
                            <td>60-62</td>
                            <td>190-195</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="shipping-info-section" style="max-width: 1200px; margin: -20px auto 40px; padding: 40px 20px;">
        <div class="shipping-container" style="max-width: 900px; margin: 0 auto;">
            <div class="shipping-header" onclick="toggleShippingInfo()" style="text-align: center; margin-bottom: 20px; cursor: pointer; user-select: none;">
                <h2 style="font-size: 28px; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    Información de Envío
                    <i class="fas fa-chevron-down" id="shipping-arrow" style="font-size: 20px; transition: transform 0.3s ease;"></i>
                </h2>
            </div>

            <div id="shipping-content" style="display: none; transition: all 0.3s ease;">
                <p style="text-align: center; font-size: 20px; color: #333; margin-bottom: 30px;">
                    ¡Disfruta de envío <strong>GRATIS</strong> en todos tus pedidos!
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-bottom: 40px;">
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 24px; margin-bottom: 15px;">📦</div>
                        <h3 style="font-size: 18px; margin-bottom: 10px; font-weight: 600;">Tiempo de Envío</h3>
                        <p style="color: #555;">15 días hábiles promedio</p>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 24px; margin-bottom: 15px;">💬</div>
                        <h3 style="font-size: 18px; margin-bottom: 10px; font-weight: 600;">Contacto Personal</h3>
                        <p style="color: #555;">Un "Player" de Jersix te contactará por WhatsApp en 3-5 días hábiles</p>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 24px; margin-bottom: 15px;">🚚</div>
                        <h3 style="font-size: 18px; margin-bottom: 10px; font-weight: 600;">Seguimiento</h3>
                        <p style="color: #555;">Recibirás tu número de rastreo en 8 - 12 días hábiles</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Separador -->
    <div style="max-width: 80%; margin: 0 auto 40px; height: 1px; background-color: #e0e0e0;"></div>

    <!-- Sección de Cuidado de Jersey -->
    <div class="care-section" style="max-width: 1200px; margin: 0 auto 60px; padding: 0 20px;">
        <div class="care-container" style="max-width: 900px; margin: 0 auto;">
            <div class="care-header" onclick="toggleCareInfo()" style="text-align: center; margin-bottom: 20px; cursor: pointer; user-select: none;">
                <h2 style="font-size: 28px; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    Cuidado de tu Jersey
                    <i class="fas fa-chevron-down" id="care-arrow" style="font-size: 20px; transition: transform 0.3s ease;"></i>
                </h2>
            </div>

            <div id="care-content" style="display: none; transition: all 0.3s ease;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-bottom: 40px;">
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 24px; margin-bottom: 15px;">🧼</div>
                        <h3 style="font-size: 18px; margin-bottom: 10px; font-weight: 600;">Lavado</h3>
                        <p style="color: #555;">Lavar a mano o en lavadora con agua fría. Usar detergente suave y no usar blanqueador.</p>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 24px; margin-bottom: 15px;">🔥</div>
                        <h3 style="font-size: 18px; margin-bottom: 10px; font-weight: 600;">Planchado</h3>
                        <p style="color: #555;">Planchar a temperatura baja y por el revés. Evitar planchar sobre estampados o logos.</p>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 24px; margin-bottom: 15px;">👕</div>
                        <h3 style="font-size: 18px; margin-bottom: 10px; font-weight: 600;">Almacenamiento</h3>
                        <p style="color: #555;">Guardar en un lugar seco y fresco. Colgar en una percha para mantener su forma.</p>
                    </div>
                </div>

                <div style="background-color: #f8f8f8; padding: 25px; border-radius: 10px; text-align: center;">
                    <p style="font-size: 16px; color: #333; margin-bottom: 0;">
                        <strong>Consejo:</strong> Para mantener los colores brillantes y la calidad de tu jersey, 
                        evita exponerla directamente al sol por largos períodos.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Separador -->
    <div style="max-width: 80%; margin: 0 auto 40px; height: 1px; background-color: #e0e0e0;"></div>

    <!-- Sección de Reseñas -->
    <div class="reviews-section" style="max-width: 1200px; margin: 0 auto 60px; padding: 0 20px;">
        <h2 style="text-align: center; font-size: 2em; color: #333; margin-bottom: 30px;">Reseñas de Nuestros Clientes</h2>
        <div class="reviews-container" style="overflow-x: auto; scroll-snap-type: x mandatory; display: flex; gap: 25px; width: calc(100% + 40px); margin: 0 -50px; padding: 0 50px; padding-bottom: 10px; align-items: stretch;">
                <?php
                try {
                $stmt = $pdo->prepare('SELECT r.*, p.name as producto_nombre FROM resenas r JOIN products p ON r.producto_id = p.product_id ORDER BY r.fecha_creacion DESC');
                    $stmt->execute();
                $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($reviews as $review):
                    $stars = '';
                        for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $review['calificacion']) {
                            $stars .= '<i class="fas fa-star"></i>';
                        } else if ($i - 0.5 <= $review['calificacion']) {
                            $stars .= '<i class="fas fa-star-half-alt"></i>';
                            } else {
                            $stars .= '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
            <div class="review-card" style="background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); display: flex; flex-direction: column; border: 1.5px solid #000; flex-shrink: 0; width: 370px; scroll-snap-align: start; min-width: 300px; max-width: 95vw;">
                <div class="review-header">
                    <div class="review-info">
                        <h3 style="margin: 0; font-size: 1.1em; color: #333; font-weight: 500;"><?php echo htmlspecialchars($review['nombre']); ?></h3>
                        <p class="product-name" style="color: #666; font-size: 0.9em; margin: 4px 0 0 0;">
                            <a href="producto.php?id=<?php echo $review['producto_id']; ?>" style="color: #666; text-decoration: underline; cursor: pointer;">
                                <?php echo htmlspecialchars($review['producto_nombre']); ?>
                            </a>
                        </p>
                        <div class="review-stars" style="color: #FFD600; margin: 4px 0 0 0; font-size: 1em;">
                            <?php echo $stars; ?>
                    </div>
                        <?php if (isset($review['recomienda'])): ?>
                            <div class="review-recomienda" style="margin: 4px 0 0 0; font-weight: 500; display: flex; align-items: center; gap: 6px; font-size: 0.85em;">
                                <?php if ($review['recomienda'] == 'si' || $review['recomienda'] == 1): ?>
                                    <span title="Recomendado" style="color: #43a047; font-size: 1em;"><i class="fas fa-thumbs-up"></i> Recomendado</span>
                                <?php else: ?>
                                    <span title="No recomendado" style="color: #e53935; font-size: 1em;"><i class="fas fa-thumbs-down"></i> No recomendado</span>
                                <?php endif; ?>
                    </div>
                        <?php endif; ?>
                </div>
            </div>
                <h4 class="review-title" style="color: #333; font-size: 1.1em; margin: 6px 0 4px 0; font-weight: 500;"><?php echo htmlspecialchars($review['titulo']); ?></h4>
                <p class="review-text" style="color: #666; line-height: 1.6; font-size: 0.95em; margin: 0 0 10px 0; word-break: break-word;"><?php echo htmlspecialchars($review['contenido']); ?></p>
                <?php if ($review['imagen_path']): ?>
                    <div class="review-images" style="margin-top: 15px; display: flex; gap: 10px; overflow-x: auto; padding: 5px 0;">
                        <?php
                        $images = json_decode($review['imagen_path'], true);
                        if (!is_array($images)) {
                            $images = [$review['imagen_path']];
                        }
                        foreach ($images as $image) {
                            $image = str_replace('\\/', '/', $image);
                            if (strpos($image, 'uploads/') === 0) {
                                $full_path = '../' . $image;
                            } elseif (strpos($image, '../uploads/') === 0) {
                                $full_path = $image;
                                            } else {
                                $full_path = '../uploads/reviews/' . basename($image);
                                            }
                            echo '<img src="' . htmlspecialchars($full_path) . '" alt="Reseña de ' . htmlspecialchars($review['nombre']) . '" style="width: 120px; height: 120px; object-fit: cover; border-radius: 4px; cursor: pointer; transition: box-shadow 0.2s;" onclick="openLightbox(\'' . htmlspecialchars($full_path) . '\', \'Reseña de ' . htmlspecialchars($review['nombre']) . '\')">';
                                        }
                                        ?>
                                    </div>
                <?php endif; ?>
                <p class="review-date" style="color: #999; font-size: 0.85em; margin-top: auto; text-align: right; padding-top: 15px;">
                    <?php echo date('d/m/Y', strtotime($review['fecha_creacion'])); ?>
                </p>
                                    </div>
            <?php endforeach; } catch(PDOException $e) { echo '<p class="error">No se pudieron cargar las reseñas en este momento.</p>'; } ?>
                                </div>
        <!-- Lightbox para imágenes -->
        <div id="lightbox" class="lightbox-overlay">
            <button class="lightbox-close" aria-label="Cerrar">&times;</button>
            <img src="" alt="Imagen ampliada" class="lightbox-img" />
                            </div>
        <script>
        // Lightbox para imágenes de reseñas (idéntico a index.php)
        document.addEventListener('DOMContentLoaded', function() {
            const lightbox = document.getElementById('lightbox');
            const lightboxImg = document.querySelector('.lightbox-img');
            const closeBtn = document.querySelector('.lightbox-close');
            document.querySelectorAll('.review-images img').forEach(img => {
                img.addEventListener('click', function() {
                    lightbox.classList.add('active');
                    lightboxImg.src = this.src;
                    lightboxImg.alt = this.alt;
                    document.body.style.overflow = 'hidden';
                });
            });
            closeBtn.addEventListener('click', function() {
                lightbox.classList.remove('active');
                lightboxImg.src = '';
                document.body.style.overflow = '';
            });
            lightbox.addEventListener('click', function(e) {
                if (e.target === lightbox) {
                    lightbox.classList.remove('active');
                    lightboxImg.src = '';
                    document.body.style.overflow = '';
                                        }
            });
            document.addEventListener('keydown', function(e) {
                if (lightbox.classList.contains('active') && (e.key === 'Escape' || e.key === 'Esc')) {
                    lightbox.classList.remove('active');
                    lightboxImg.src = '';
                    document.body.style.overflow = '';
                }
            });
        });
        </script>
                                        </div>
    <div style="text-align: center; margin-top: 10px; margin-bottom: 40px;">
        <button id="openReviewForm" style="background: #000; color: #fff; border: none; border-radius: 6px; padding: 14px 28px; font-size: 1.1em; display: inline-flex; align-items: center; gap: 10px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: background 0.2s;">
            <i class="fas fa-pen"></i> Agregar reseña
                </button>
    </div>
    
    <!-- Modal para escribir reseñas -->
    <div id="reviewModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 1000; overflow: auto; justify-content: center; align-items: center;">
        <div class="modal-content" style="position: relative; background-color: #fff; max-width: 500px; width: 85%; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); animation: modalFadeIn 0.3s ease; max-height: 90vh; overflow-y: auto;">
            <span class="close" onclick="document.getElementById('reviewModal').style.display='none'" style="position: absolute; right: 15px; top: 10px; font-size: 24px; font-weight: bold; cursor: pointer; color: #333;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; text-align: center; font-size: 20px;">Escribir una reseña</h2>
            
            <form id="reviewForm" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                <input type="hidden" name="producto_id" value="<?php echo $product_id; ?>">
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="nombre" style="display: block; margin-bottom: 8px; font-weight: 500;">Nombre</label>
                    <input type="text" id="nombre" name="nombre" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="titulo" style="display: block; margin-bottom: 8px; font-weight: 500;">Título de la reseña</label>
                    <input type="text" id="titulo" name="titulo" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Calificación</label>
                    <div class="rating-container">
                        <div class="stars">
                            <input type="radio" id="star1" name="calificacion" value="1" required />
                            <label for="star1" title="1 estrella" data-value="1">★</label>
                            
                            <input type="radio" id="star2" name="calificacion" value="2" />
                            <label for="star2" title="2 estrellas" data-value="2">★</label>
                            
                            <input type="radio" id="star3" name="calificacion" value="3" />
                            <label for="star3" title="3 estrellas" data-value="3">★</label>
                            
                            <input type="radio" id="star4" name="calificacion" value="4" />
                            <label for="star4" title="4 estrellas" data-value="4">★</label>
                            
                            <input type="radio" id="star5" name="calificacion" value="5" />
                            <label for="star5" title="5 estrellas" data-value="5">★</label>
                        </div>
                        <div class="rating-text">Selecciona una calificación</div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="contenido" style="display: block; margin-bottom: 8px; font-weight: 500;">Tu reseña</label>
                    <textarea id="contenido" name="contenido" rows="5" required maxlength="100" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
                    <p id="charCount" style="font-size: 13px; color: #888; text-align: right; margin-top: 2px;">0/100 caracteres</p>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="review_images" style="display: block; margin-bottom: 8px; font-weight: 500;">Sube fotos del producto (opcional)</label>
                    <input type="file" id="review_images" name="review_images[]" accept="image/jpeg, image/png, image/jpg" multiple style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9;">
                    <p style="margin-top: 5px; font-size: 13px; color: #666;">Puedes seleccionar hasta 3 imágenes. Formatos permitidos: JPG, JPEG, PNG. Tamaño máximo por imagen: 5MB</p>
                    <div id="images_preview" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;"></div>
                </div>
                
                <div class="form-group" style="margin-bottom: 30px;">
                    <label style="font-weight: 500; display: block; margin-bottom: 10px;">¿Recomendarías este producto?</label>
                    <div style="display: flex; gap: 20px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="radio" name="recomienda" value="si" checked style="margin-right: 8px;">
                            <span>Sí</span>
                        </label>
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="radio" name="recomienda" value="no" style="margin-right: 8px;">
                            <span>No</span>
                        </label>
                    </div>
                </div>
                
                <button type="submit" name="submit_review" style="background-color: #000; color: white; padding: 14px 25px; border: none; border-radius: 4px; font-size: 16px; width: 100%; cursor: pointer;">Enviar reseña</button>
            </form>

            <script>
            // Previsualización de múltiples imágenes
            document.getElementById('review_images').addEventListener('change', function(e) {
                const files = this.files;
                const previewContainer = document.getElementById('images_preview');
                
                // Limitar a 3 imágenes
                if (files.length > 3) {
                    alert('Solo puedes subir hasta 3 imágenes por reseña.');
                    this.value = '';
                    previewContainer.innerHTML = '';
                    return;
                }
                
                // Limpiar previsualizaciones anteriores
                previewContainer.innerHTML = '';
                
                // Procesar cada archivo
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    
                    // Verificar tamaño (5MB máximo)
                    if (file.size > 5 * 1024 * 1024) {
                        alert(`La imagen ${file.name} es demasiado grande. El tamaño máximo es 5MB.`);
                        this.value = '';
                        previewContainer.innerHTML = '';
                        return;
                    }
                    
                    // Verificar tipo
                    if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
                        alert(`El archivo ${file.name} no es una imagen válida. Solo se permiten archivos JPG, JPEG o PNG.`);
                        this.value = '';
                        previewContainer.innerHTML = '';
                        return;
                    }
                    
                    // Crear un contenedor para la imagen y el botón de eliminar
                    const imgContainer = document.createElement('div');
                    imgContainer.style.position = 'relative';
                    imgContainer.style.marginBottom = '10px';
                    
                    // Mostrar la vista previa
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '100px';
                        img.style.height = '100px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '8px';
                        img.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                        
                        imgContainer.appendChild(img);
                        previewContainer.appendChild(imgContainer);
                    }
                    reader.readAsDataURL(file);
                }
            });
            </script>
        </div>
    </div>
    
    <?php
    // Procesar el envío de la reseña
    if (isset($_POST['submit_review'])) {
        // Sanitizar y validar los datos
        $producto_id = intval($_POST['producto_id']);
        $nombre = trim(htmlspecialchars($_POST['nombre']));
        $calificacion = floatval($_POST['calificacion']);
        $titulo = trim(htmlspecialchars($_POST['titulo']));
        $contenido = trim(htmlspecialchars($_POST['contenido']));
        $recomienda = $_POST['recomienda'] === 'si' ? 'si' : 'no';
        $imagen_path = null;
        $imagen_paths = [];
        
        // Procesar las imágenes si se han subido
        if(isset($_FILES['review_images']) && is_array($_FILES['review_images']['name'])) {
            // Crear directorio para las imágenes de reseñas si no existe
            $upload_dir = "../uploads/reviews/";
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Validar y subir cada imagen
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            
            for ($i = 0; $i < count($_FILES['review_images']['name']); $i++) {
                // Verificar que haya una imagen y no haya errores
                if ($_FILES['review_images']['error'][$i] !== 0 || empty($_FILES['review_images']['tmp_name'][$i])) {
                    continue;
                }
                
                $file_type = $_FILES['review_images']['type'][$i];
                $file_size = $_FILES['review_images']['size'][$i];
                $file_tmp = $_FILES['review_images']['tmp_name'][$i];
                $file_name = $_FILES['review_images']['name'][$i];
                
                // Validar tipo y tamaño
                if (in_array($file_type, $allowed_types) && $file_size <= 5 * 1024 * 1024) {
                    // Generar un nombre único para la imagen basado en timestamp + producto_id + índice
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $unique_filename = 'review_' . $producto_id . '_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                    $upload_path = $upload_dir . $unique_filename;
                    
                    // Intentar mover el archivo
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Usar ruta relativa en la base de datos (sin ../)
                        $imagen_paths[] = 'uploads/reviews/' . $unique_filename;
                        
                        // Asegurar los permisos del archivo para que sea accesible
                        chmod($upload_path, 0644);
                    }
                }
            }
        }
        
        // Convertir las rutas de las imágenes a formato JSON para guardarlas
        $imagen_paths_json = !empty($imagen_paths) ? json_encode($imagen_paths) : null;
        
        // Validar datos
        if (empty($nombre) || empty($titulo) || empty($contenido) || $calificacion < 1 || $calificacion > 5) {
            echo "<script>alert('Por favor, complete todos los campos correctamente.');</script>";
        } else {
            try {
                // Insertar la reseña en la base de datos con la imagen (si existe)
                if ($imagen_paths_json) {
                    $stmt = $pdo->prepare('
                        INSERT INTO resenas (producto_id, nombre, calificacion, titulo, contenido, recomienda, imagen_path)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ');
                    $result = $stmt->execute([$producto_id, $nombre, $calificacion, $titulo, $contenido, $recomienda, $imagen_paths_json]);
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO resenas (producto_id, nombre, calificacion, titulo, contenido, recomienda)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ');
                    $result = $stmt->execute([$producto_id, $nombre, $calificacion, $titulo, $contenido, $recomienda]);
                }
                
                if ($result) {
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            showMiniToast('¡Gracias por tu reseña!');
                            setTimeout(function() { window.location.href = window.location.pathname + '?id=" . $product_id . "'; }, 1800);
                        });
                        </script>";
                } else {
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { showMiniToast('Hubo un error al guardar tu reseña. Por favor, intenta nuevamente.'); });</script>";
                }
            } catch (PDOException $e) {
                echo "<script>alert('Error en el servidor. Por favor, intenta nuevamente más tarde.');</script>";
                error_log("Error al guardar reseña: " . $e->getMessage());
            }
        }
    }
    ?>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Sobre JersixMx</h3>
                <p>Somos una tienda especializada en jerseys deportivos y casuales de alta calidad. Nuestro compromiso es ofrecer diseños únicos y materiales premium para nuestros clientes.</p>
            </div>
            <div class="footer-section">
                <h3>Preguntas Frecuentes</h3>
                <ul>
                    <li><a href="../Preguntas_Frecuentes.html">Envíos y Entregas</a></li>
                    <li><a href="../Preguntas_Frecuentes.html">Devoluciones</a></li>
                    <li><a href="../Preguntas_Frecuentes.html">Métodos de Pago</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Generales</h3>
                <ul>
                    <li><a href="../PoliticaDevolucion">Politica de Devoluciones</a></li>
                    <li><a href="../aviso_privacidad">Aviso de Privacidad</a></li>
                    <li><a href="../TerminosYcondicones">Terminos y Condiciones</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Newsletter</h3>
                <p>Suscríbete para recibir las últimas novedades y ofertas especiales.</p>
                <div class="newsletter-form">
                    <input type="email" placeholder="Tu correo electrónico" class="newsletter-input">
                    <button class="newsletter-button">Suscribirse</button>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="social-links">
                <a href="https://www.tiktok.com/@jersix.mx" class="social-link" target="_blank"><i class="fab fa-tiktok"></i></a>
                <a href="https://www.instagram.com/jersix.mx/" class="social-link" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://wa.me/+528129157795" class="social-link" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
            <p class="copyright">&copy; 2025 Jersix.mx. Todos los derechos reservados.</p>
        </div>
    </footer>
    
    <div class="whatsapp-button">
        <a href="https://wa.me/+528129157795" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>

    <script>
    // Variables para personalización
    let basePrice = parseFloat(document.querySelector('.product-price').textContent.replace('$', '').trim());
    let currentPrice = basePrice;
    const PATCH_PRICE = 50;
    const CUSTOMIZATION_PRICE = 100;

    // Stock por talla desde PHP
    var stockPorTalla = <?php echo json_encode($stock_by_size); ?>;

    // Función para validar el número del jersey
    function validateJerseyNumber(input) {
        // Limitar a máximo 2 dígitos
        if (input.value.length > 2) {
            input.value = input.value.slice(0, 2);
        }
        // Solo validar si hay un valor
        if (input.value !== '') {
            let value = parseInt(input.value);
            if (isNaN(value) || value < 0) {
                input.value = '';
            } else if (value > 99) {
                input.value = 99;
            }
        }
    }

    // Función para actualizar el precio
    function updatePrice() {
        const patchOption = document.getElementById('patchOption');
        const customizationSelect = document.getElementById('customizationSelect');
        // Calcular precio base + personalización + parche
        currentPrice = basePrice;
        if (customizationSelect.value === 'yes') {
            currentPrice += CUSTOMIZATION_PRICE;
        }
        if (patchOption.checked) {
            currentPrice += PATCH_PRICE;
        }
        document.querySelector('.product-price').textContent = '$ ' + currentPrice.toFixed(2);
    }

    // Función para mostrar mensaje aleatorio al cargar la página
    function mostrarMensajeStockInicial() {
        const stockInfo = document.getElementById('stockInfo');
        // 70% probabilidad de "¡Quedan pocos!", 30% de "Disponible en stock"
        const random = Math.random();
        if (random < 0.7) {
            stockInfo.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> <span>¡Quedan pocos!</span>';
        } else {
            stockInfo.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> <span>Disponible en stock</span>';
        }
    }

    // Función para mostrar el stock real al seleccionar talla
    function mostrarStockPorTalla(talla) {
        const stockInfo = document.getElementById('stockInfo');
        const stock = stockPorTalla[talla] || 0;
        if (stock <= 0) {
            stockInfo.innerHTML = '<i class="fas fa-times-circle" style="color: #dc3545;"></i> <span>Producto agotado</span>';
        } else if (stock <= 3) {
            stockInfo.innerHTML = `<i class=\"fas fa-exclamation-circle\" style=\"color: #dc3545;\"></i> <span>¡Últimas ${stock} unidades disponibles!</span>`;
        } else {
            stockInfo.innerHTML = `<i class=\"fas fa-check-circle\" style=\"color: #28a745;\"></i> <span>¡${stock} unidades disponibles!</span>`;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Mostrar mensaje aleatorio al cargar la página
        mostrarMensajeStockInicial();

        // Manejar selección de talla
        const sizeOptions = document.querySelectorAll('.size-option');
        const quantityInput = document.getElementById('quantityInput');
        sizeOptions.forEach(option => {
            option.addEventListener('click', function() {
                sizeOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                const talla = this.textContent.trim();
                const stock = stockPorTalla[talla] || 0;
                quantityInput.max = stock;
                quantityInput.value = 1;
                mostrarStockPorTalla(talla);
            });
        });
    });

    // Event listeners para personalización
    document.getElementById('patchOption').addEventListener('change', updatePrice);
    document.getElementById('customizationSelect').addEventListener('change', function() {
        const addToCartBtn = document.querySelector('.add-to-cart-btn');
        if (this.value === 'yes') {
            document.getElementById('customizationFields').style.display = 'block';
            // Desactivar el botón de agregar al carrito hasta que se completen los campos
            addToCartBtn.disabled = true;
            // Verificar si los campos ya tienen valores
            validatePersonalizationFields();
        } else {
            document.getElementById('customizationFields').style.display = 'none';
            // Limpiar campos de personalización
            document.getElementById('jerseyName').value = '';
            document.getElementById('jerseyNumber').value = '';
            document.getElementById('patchOption').checked = false;
            // Habilitar el botón de agregar al carrito
            addToCartBtn.disabled = false;
        }
        updatePrice();
    });


    // Añadir validación en tiempo real para los campos de personalización
    document.getElementById('jerseyName').addEventListener('input', validatePersonalizationFields);
    document.getElementById('jerseyNumber').addEventListener('input', validatePersonalizationFields);

    // Función para validar los campos de personalización y actualizar el estado del botón
    function validatePersonalizationFields() {
        const customizationSelect = document.getElementById('customizationSelect');
        const addToCartBtn = document.querySelector('.add-to-cart-btn');
        
        // Solo validar si la personalización está habilitada
        if (customizationSelect.value === 'yes') {
            const name = document.getElementById('jerseyName').value.trim();
            const number = document.getElementById('jerseyNumber').value.trim();
            
            // Habilitar el botón solo si ambos campos están completos
            addToCartBtn.disabled = !(name && number);
        }
    }

    // Funciones básicas para el manejo de imágenes
    function changeImage(element) {
        document.getElementById('mainImage').src = element.src;
        
        const thumbnails = document.querySelectorAll('.thumbnail');
        thumbnails.forEach(thumb => {
            thumb.classList.remove('active');
        });
        
        element.classList.add('active');
    }
    
    function changeImageNav(direction) {
        const thumbnails = Array.from(document.querySelectorAll('.thumbnail'));
        const currentIndex = thumbnails.findIndex(thumb => thumb.classList.contains('active'));
        
        let newIndex = currentIndex + direction;
        
        if (newIndex < 0) newIndex = thumbnails.length - 1;
        if (newIndex >= thumbnails.length) newIndex = 0;
        
        changeImage(thumbnails[newIndex]);
    }

    // Nueva función para manejar la imagen fija en móviles
    function handleFixedImages() {
        // Solo ejecutar en dispositivos móviles
        if (window.innerWidth <= 768) {
            const imageContainer = document.querySelector('.product-image-container');
            const productInfo = document.querySelector('.product-info');
            const imageContainerHeight = imageContainer.offsetHeight;
            
            // Función para manejar el scroll
            function handleScroll() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                
                // Si hemos scrolleado más allá de la posición original del contenedor
                if (scrollTop > imageContainer.offsetTop) {
                    imageContainer.classList.add('image-fixed');
                    productInfo.classList.add('content-shifted');
                } else {
                    imageContainer.classList.remove('image-fixed');
                    productInfo.classList.remove('content-shifted');
                }
            }
            
            // Añadir event listener para el scroll
            window.addEventListener('scroll', handleScroll);
            
            // Llamar a la función una vez para establecer el estado inicial
            handleScroll();
        }
    }

    // Asegurar que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM fully loaded");
        
        // Inicializar la funcionalidad de imagen fija
        handleFixedImages();
        
        // Funcionalidad para el botón del menú móvil
        const menuToggle = document.querySelector('.menu-toggle');
        const navLinks = document.querySelector('.nav-links');
        
        if (menuToggle && navLinks) {
            menuToggle.addEventListener('click', function() {
                navLinks.classList.toggle('active');
                menuToggle.classList.toggle('active');
            });
        }
        
        // Manejo de selección de tallas
        const sizeOptions = document.querySelectorAll('.size-option');
        sizeOptions.forEach(option => {
            option.addEventListener('click', function() {
                sizeOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                console.log('Talla seleccionada:', this.textContent);
            });
        });
        
        // Manejo de cantidad con IDs específicos para evitar conflictos
        const minusBtn = document.getElementById('minusBtn');
        const plusBtn = document.getElementById('plusBtn');
        const quantityInput = document.getElementById('quantityInput');
        
        if (minusBtn && plusBtn && quantityInput) {
            // Botón de disminuir
            minusBtn.onclick = function() {
                let currentValue = parseInt(quantityInput.value);
                if (currentValue > 1) {
                    quantityInput.value = currentValue - 1;
                    console.log('Cantidad actualizada a:', quantityInput.value);
                }
            };
            
            // Botón de aumentar
            plusBtn.onclick = function() {
                let currentValue = parseInt(quantityInput.value);
                let maxValue = parseInt(quantityInput.getAttribute('max'));
                if (currentValue < maxValue) {
                    quantityInput.value = currentValue + 1;
                    console.log('Cantidad actualizada a:', quantityInput.value);
                }
            };
            
            // Validación directa del input
            quantityInput.addEventListener('input', function() {
                let value = parseInt(this.value);
                let max = parseInt(this.getAttribute('max'));
                let min = parseInt(this.getAttribute('min'));
                
                if (isNaN(value) || value < min) {
                    this.value = min;
                } else if (value > max) {
                    this.value = max;
                }
            });
        } else {
            console.error('No se encontraron los elementos de control de cantidad');
        }
        
        // Modal de guía de tallas
        const sizeGuideBtn = document.querySelector('.size-guide-btn');
        const sizeGuideModal = document.getElementById('sizeGuideModal');
        const closeBtn = sizeGuideModal.querySelector('.close');
        
        if (sizeGuideBtn && sizeGuideModal && closeBtn) {
            sizeGuideBtn.onclick = function() {
                sizeGuideModal.style.display = 'flex';
            };
            
            closeBtn.onclick = function() {
                sizeGuideModal.style.display = 'none';
            };
            
            window.onclick = function(event) {
                if (event.target == sizeGuideModal) {
                    sizeGuideModal.style.display = 'none';
                }
            };
        }
        
        // Eliminar cualquier comportamiento de zoom en la imagen
        const mainImage = document.getElementById('mainImage');
        if (mainImage) {
            // Eliminar eventos de clic que pudieran estar activando zoom
            mainImage.onclick = null;
            mainImage.style.cursor = 'default';
        }
    });

    function toggleShippingInfo() {
        const content = document.getElementById('shipping-content');
        const arrow = document.getElementById('shipping-arrow');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            arrow.style.transform = 'rotate(180deg)';
        } else {
            content.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
        }
    }

    // Código para manejar las reseñas
    document.addEventListener('DOMContentLoaded', function() {
        // Abrir el modal de reseñas al hacer clic en el botón
        const openReviewBtn = document.getElementById('openReviewForm');
        const reviewModal = document.getElementById('reviewModal');
        
        if (openReviewBtn && reviewModal) {
            openReviewBtn.addEventListener('click', function() {
                reviewModal.style.display = 'flex';
            });
        }
        
        // Cerrar modal al hacer clic fuera de él
        window.addEventListener('click', function(event) {
            if (event.target === reviewModal) {
                reviewModal.style.display = 'none';
            }
        });
        
        // Sistema de calificación por estrellas
        const starLabels = document.querySelectorAll('.rating-input label');
        if (starLabels.length > 0) {
            starLabels.forEach(function(label) {
                label.addEventListener('mouseover', function() {
                    // Al pasar el ratón, colorea esta estrella y todas las anteriores
                    const currentId = this.getAttribute('for');
                    const currentStar = parseInt(currentId.replace('star', ''));
                    
                    starLabels.forEach(function(innerLabel) {
                        const innerLabelId = innerLabel.getAttribute('for');
                        const innerStar = parseInt(innerLabelId.replace('star', ''));
                        
                        // En el sistema RTL (right-to-left), las estrellas más altas están a la izquierda
                        if (innerStar >= currentStar) {
                            innerLabel.style.color = '#FFD700'; // Color dorado
                        } else {
                            innerLabel.style.color = '#ddd'; // Color gris
                        }
                    });
                });
            });
            
            // Restablecer colores al salir del área de calificación
            const ratingInput = document.querySelector('.rating-input');
            if (ratingInput) {
                ratingInput.addEventListener('mouseout', function() {
                    // Restaurar colores basados en la selección actual
                    const selectedStar = document.querySelector('.rating-input input:checked');
                    if (selectedStar) {
                        const selectedValue = parseInt(selectedStar.value);
                        
                        starLabels.forEach(function(label) {
                            const labelId = label.getAttribute('for');
                            const star = parseInt(labelId.replace('star', ''));
                            
                            if (star >= selectedValue) {
                                label.style.color = '#FFD700';
                            } else {
                                label.style.color = '#ddd';
                            }
                        });
                    } else {
                        // Si no hay selección, todas las estrellas son grises
                        starLabels.forEach(function(label) {
                            label.style.color = '#ddd';
                        });
                    }
                });
            }
            
            // Actualizar selección al hacer clic
            document.querySelectorAll('.rating-input input').forEach(function(input) {
                input.addEventListener('change', function() {
                    const value = parseInt(this.value);
                    
                    starLabels.forEach(function(label) {
                        const labelId = label.getAttribute('for');
                        const star = parseInt(labelId.replace('star', ''));
                        
                        if (star >= value) {
                            label.style.color = '#FFD700';
                        } else {
                            label.style.color = '#ddd';
                        }
                    });
                });
            });
        }
        
        // Carrusel de reseñas
        const track = document.getElementById('reviewsTrack');
        const prevButton = document.getElementById('prevReview');
        const nextButton = document.getElementById('nextReview');
        const indicators = document.querySelectorAll('.carousel-indicators .indicator');
        
        if (track && prevButton && nextButton) {
            let currentIndex = 0;
            const reviewCards = document.querySelectorAll('.review-card');
            const totalReviews = reviewCards.length;
            
            // No hacer nada si no hay reseñas
            if (totalReviews === 0) return;
            
            // Actualizar la posición del track
            function updatePosition() {
                if (!track) return;
                
                // Calcular el ancho a desplazar
                const cardWidth = reviewCards[0].offsetWidth;
                const margin = 20; // 10px a cada lado
                const offset = -(currentIndex * (cardWidth + margin));
                
                track.style.transform = `translateX(${offset}px)`;
                
                // Actualizar indicadores
                indicators.forEach((indicator, i) => {
                    indicator.style.backgroundColor = i === currentIndex ? '#000' : '#ddd';
                });
                
                // Actualizar estado de los botones
                prevButton.disabled = currentIndex === 0;
                prevButton.style.opacity = currentIndex === 0 ? '0.5' : '1';
                nextButton.disabled = currentIndex === totalReviews - 1;
                nextButton.style.opacity = currentIndex === totalReviews - 1 ? '0.5' : '1';
            }
            
            // Botón anterior
            prevButton.addEventListener('click', function() {
                if (currentIndex > 0) {
                    currentIndex--;
                    updatePosition();
                }
            });
            
            // Botón siguiente
            nextButton.addEventListener('click', function() {
                if (currentIndex < totalReviews - 1) {
                    currentIndex++;
                    updatePosition();
                }
            });
            
            // Indicadores
            indicators.forEach((indicator, i) => {
                indicator.addEventListener('click', function() {
                    currentIndex = i;
                    updatePosition();
                });
            });
            
            // Inicializar posición
            updatePosition();
            
            // Adaptar carrusel al redimensionar la ventana
            window.addEventListener('resize', updatePosition);
        }
    });

    // Sistema de calificación con estrellas - Implementación JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        const starsContainer = document.querySelector('.stars');
        const ratingLabels = document.querySelectorAll('.stars label');
        const ratingInputs = document.querySelectorAll('.stars input');
        const ratingText = document.querySelector('.rating-text');
        
        if (!starsContainer || !ratingLabels.length || !ratingInputs.length || !ratingText) return;
        
        // Función para actualizar estrellas activas
        function updateStars(selectedValue) {
            ratingLabels.forEach(label => {
                const value = parseInt(label.getAttribute('data-value'));
                if (value <= selectedValue) {
                    label.classList.add('active');
                } else {
                    label.classList.remove('active');
                }
            });
            
            if (selectedValue > 0) {
                ratingText.textContent = selectedValue + ' de 5 estrellas';
            } else {
                ratingText.textContent = 'Selecciona una calificación';
            }
        }
        
        // Manejo de eventos click
        ratingInputs.forEach(input => {
            input.addEventListener('change', function() {
                updateStars(parseInt(this.value));
            });
        });
        
        // Manejo de eventos hover
        ratingLabels.forEach(label => {
            label.addEventListener('mouseenter', function() {
                const hoverValue = parseInt(this.getAttribute('data-value'));
                
                ratingLabels.forEach(l => {
                    const value = parseInt(l.getAttribute('data-value'));
                    if (value <= hoverValue) {
                        l.classList.add('hovered');
                    } else {
                        l.classList.remove('hovered');
                    }
                });
            });
        });
        
        starsContainer.addEventListener('mouseleave', function() {
            ratingLabels.forEach(label => label.classList.remove('hovered'));
            
            // Restaurar la selección actual
            const checkedInput = document.querySelector('.stars input:checked');
            if (checkedInput) {
                updateStars(parseInt(checkedInput.value));
            }
        });
    });

    // Función para abrir el modal de imagen
    function openImageModal(imageSrc) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        
        // Verificar que existen los elementos
        if (!modal || !modalImg) {
            console.error("Error: No se encontró el modal o la imagen");
            return;
        }
        
        // Mostrar modal
        modal.style.display = 'flex';
        
        // Mostrar imagen con manejadores de error mejorados
        modalImg.onerror = function() {
            console.error("Error al cargar la imagen:", imageSrc);
            
            // Probar diferentes formatos de ruta
            let newSrc = imageSrc;
            
            // Si la ruta empieza con ../
            if (imageSrc.startsWith('../')) {
                newSrc = imageSrc.substring(3); // Quitar ../
                console.log("Intentando ruta sin ../:", newSrc);
            } 
            // Si no tiene el prefijo ../
            else if (!imageSrc.startsWith('../')) {
                newSrc = '../' + imageSrc; // Añadir ../
                console.log("Intentando ruta con ../:", newSrc);
            }
            
            // Intentar con la nueva ruta
            modalImg.src = newSrc;
            
            // Si sigue fallando
            this.onerror = function() {
                // Última opción: intentar ruta directa a uploads/reviews
                const filename = imageSrc.split('/').pop();
                newSrc = '../uploads/reviews/' + filename;
                console.log("Último intento con:", newSrc);
                
                modalImg.src = newSrc;
                
                // Si todo falla, mostrar imagen por defecto
                this.onerror = function() {
                    console.error("No se pudo cargar la imagen después de varios intentos");
                    modalImg.src = '../uploads/reviews/imagen_no_disponible.jpg';
                    modalImg.style.opacity = 0.5;
                };
            };
        };
        
        // Establecer la fuente de la imagen
        modalImg.src = imageSrc;
        
        // Evitar que se desplace la página cuando el modal está abierto
        document.body.style.overflow = 'hidden';
        
        // Cerrar el modal al hacer clic fuera de la imagen
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeImageModal();
            }
        });
        
        // Añadir manejador para la tecla Escape
        document.addEventListener('keydown', handleEscapeKey);
    }

    // Función para cerrar el modal de imagen
    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
        document.body.style.overflow = 'auto';
        document.removeEventListener('keydown', handleEscapeKey);
    }

    // Manejador para la tecla Escape
    function handleEscapeKey(event) {
        if (event.key === 'Escape') {
            closeImageModal();
        }
    }
    </script>

    <!-- Modal para mostrar imagen de reseña ampliada -->
    <div id="imageModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); z-index: 1050; opacity: 0; transition: opacity 0.3s ease;">
        <div class="modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 900px;">
            <button onclick="closeImageModal()" style="position: absolute; top: -40px; right: 0; background: rgba(0,0,0,0.5); color: white; border: none; border-radius: 50%; width: 36px; height: 36px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 1060;">
                <i class="fas fa-times"></i>
            </button>
            
            <div style="position: relative;">
                <img id="modalImage" src="" alt="<?php echo htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8'); ?>" style="max-width: 100%; max-height: 80vh; display: block; margin: 0 auto; border-radius: 8px; box-shadow: 0 4px 25px rgba(0,0,0,0.3);">
                
                <div style="position: absolute; bottom: -40px; left: 0; width: 100%; text-align: center; color: white; font-size: 14px; padding: 10px;">
                    <span>Foto de cliente</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function openImageModal(imageSrc) {
        var modal = document.getElementById('imageModal');
        var modalImg = document.getElementById('modalImage');
        
        // Mostrar modal con animación
        modal.style.display = 'block';
        setTimeout(function() {
            modal.style.opacity = '1';
        }, 10);
        
        // Establecer imagen con manejo de errores
        modalImg.src = imageSrc;
        modalImg.onerror = function() {
            this.src = '../uploads/reviews/imagen_no_disponible.jpg';
            this.style.opacity = '0.7';
        };
        
        // Prevenir scroll
        document.body.style.overflow = 'hidden';
        
        // Evento para cerrar al hacer clic fuera
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeImageModal();
            }
        });
        
        // Agregar manejador para tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    }
    
    function closeImageModal() {
        var modal = document.getElementById('imageModal');
        modal.style.opacity = '0';
        setTimeout(function() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }, 300);
    }
    </script>

    <script>
    // Inicializar el slider de reseñas
    document.addEventListener('DOMContentLoaded', function() {
        const track = document.getElementById('reviewsTrack');
        const indicators = document.querySelectorAll('.review-indicator');
        const prevButton = document.getElementById('prevReview');
        const nextButton = document.getElementById('nextReview');
        const reviewCount = <?php echo count($reviews); ?>;
        const maxIndicators = <?php echo min(count($reviews), 5); ?>;
        let currentIndex = 0;
        
        // Función para actualizar la posición del track
        function updateTrackPosition() {
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
            
            // Calcular qué indicador activar
            const normalizedIndex = Math.min(Math.round(currentIndex / reviewCount * (maxIndicators-1)), maxIndicators-1);
            
            // Actualizar indicadores
            indicators.forEach((indicator, i) => {
                indicator.style.backgroundColor = i === normalizedIndex ? '#cc0000' : '#ddd';
            });
        }
        
        // Event listeners para los indicadores
        indicators.forEach((indicator, i) => {
            indicator.addEventListener('click', () => {
                const targetPosition = parseFloat(indicator.getAttribute('data-position') || i);
                currentIndex = Math.round(targetPosition);
                updateTrackPosition();
            });
        });
        
        // Event listeners para los botones de navegación
        if (prevButton) {
            prevButton.addEventListener('click', () => {
                currentIndex = (currentIndex - 1 + reviewCount) % reviewCount;
                updateTrackPosition();
            });
        }
        
        if (nextButton) {
            nextButton.addEventListener('click', () => {
                currentIndex = (currentIndex + 1) % reviewCount;
                updateTrackPosition();
            });
        }
    });

    // Función para abrir el modal de reseña
    document.getElementById('openReviewForm').addEventListener('click', function() {
        document.getElementById('reviewModal').style.display = 'flex';
    });
    </script>

    <script>
    // Validar solo letras en el nombre
    const jerseyNameInput = document.getElementById('jerseyName');
    jerseyNameInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ ]/g, '');
    });
    // Validar solo números en el número (ya está cubierto por el input type, pero reforzamos)
    const jerseyNumberInput = document.getElementById('jerseyNumber');
    jerseyNumberInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    </script>

    <!-- Lightbox para imágenes -->
    <div id="lightbox" class="lightbox-overlay">
        <button class="lightbox-close" aria-label="Cerrar">&times;</button>
        <img src="" alt="Imagen ampliada" class="lightbox-img" />
    </div>
    <script>
    // Lightbox para imágenes de reseñas (idéntico a index.php)
    document.addEventListener('DOMContentLoaded', function() {
        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.querySelector('.lightbox-img');
        const closeBtn = document.querySelector('.lightbox-close');
        document.querySelectorAll('.review-images img').forEach(img => {
            img.addEventListener('click', function() {
                lightbox.classList.add('active');
                lightboxImg.src = this.src;
                lightboxImg.alt = this.alt;
                document.body.style.overflow = 'hidden';
            });
        });
        closeBtn.addEventListener('click', function() {
            lightbox.classList.remove('active');
            lightboxImg.src = '';
            document.body.style.overflow = '';
        });
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                lightbox.classList.remove('active');
                lightboxImg.src = '';
                document.body.style.overflow = '';
            }
        });
        document.addEventListener('keydown', function(e) {
            if (lightbox.classList.contains('active') && (e.key === 'Escape' || e.key === 'Esc')) {
                lightbox.classList.remove('active');
                lightboxImg.src = '';
                document.body.style.overflow = '';
            }
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Contador de caracteres para el textarea de reseña
    const contenidoTextarea = document.getElementById('contenido');
    const charCount = document.getElementById('charCount');
    if (contenidoTextarea && charCount) {
        contenidoTextarea.addEventListener('input', function() {
            charCount.textContent = this.value.length + '/300 caracteres';
        });
    }
    </script>
    <!-- Toast minimalista para notificaciones -->
    <div id="miniToast" style="display:none; position: fixed; top: 32px; right: 32px; background: #222; color: #fff; padding: 14px 28px; border-radius: 8px; font-size: 1.05em; box-shadow: 0 4px 16px rgba(0,0,0,0.12); z-index: 99999; min-width: 180px; text-align: center; font-weight: 500; letter-spacing: 0.01em;"></div>
    <script>
    // Toast minimalista
    function showMiniToast(msg) {
        const toast = document.getElementById('miniToast');
        if (!toast) return;
        toast.textContent = msg;
        toast.style.display = 'block';
        toast.style.opacity = '1';
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s';
            toast.style.opacity = '0';
        }, 2000);
        setTimeout(() => {
            toast.style.display = 'none';
            toast.style.transition = '';
            toast.style.opacity = '1';
        }, 2500);
    }
    </script>

    <script>
    function toggleCareInfo() {
        const content = document.getElementById('care-content');
        const arrow = document.getElementById('care-arrow');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            arrow.style.transform = 'rotate(180deg)';
        } else {
            content.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
        }
    }
    </script>
</body>
</html>
