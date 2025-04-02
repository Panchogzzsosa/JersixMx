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
        // Opción 1: Redirigir a la página de productos con un mensaje
        header('Location: ../productos.php?error=producto_inactivo');
        exit;
        
        // Opción 2 (alternativa): Mostrar un mensaje en esta página
        // $product_inactive = true;
    }
    
    // Obtener datos del producto
    $product_name = $product['name'];
    $product_image = $product['image_url'];
    $stock = $product['stock'];
    $price = $product['price'];
    $product_id = $product['product_id'];
    $product_status = $hasStatusColumn ? $product['status'] : 1; // Por defecto activo si no existe la columna
    
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
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../Css/index.css">
    <link rel="stylesheet" href="../Css/productos.css">
    <link rel="stylesheet" href="../Css/cart.css">
    <link rel="stylesheet" href="../Css/notificacion.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <script src="../Js/search.js" defer></script>
    <script src="../Js/products-data.js" defer></script>
    <script src="../Js/cart.js" defer></script>
    <script src="../Js/newsletter.js" defer></script>
    <style>
        /* Estilos generales de la página de producto */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #fff;
            font-size: 16px; /* Tamaño base de fuente aumentado */
        }

        main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px; /* Padding aumentado */
        }

        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px; /* Gap aumentado */
            margin-top: 30px; /* Margen superior aumentado */
            margin-bottom: 60px; /* Margen inferior aumentado */
        }

        /* Estilos de imágenes */
        .product-image-container {
            position: relative;
            margin-bottom: 30px;
            overflow: visible; /* Cambiado de hidden para evitar comportamientos de zoom */
        }

        .product-image {
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
            max-height: 650px;
            object-fit: contain;
            cursor: default; /* Asegura que no aparezca cursor de zoom */
        }

        .product-thumbnails {
            display: flex;
            gap: 15px; /* Gap aumentado */
            margin-top: 25px; /* Margen superior aumentado */
            justify-content: center; /* Cambiado de flex-start a center para centrar */
            flex-wrap: wrap; /* Permite que las miniaturas se envuelvan en varias líneas si es necesario */
        }

        .thumbnail {
            width: 75px; /* Tamaño de miniatura */
            height: 75px; /* Tamaño de miniatura */
            object-fit: cover;
            border: 1px solid #e0e0e0; /* Borde más fino y de color más claro */
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 10px; /* Margen inferior */
            padding: 2px; /* Padding reducido */
            box-shadow: none; /* Eliminar cualquier sombra */
        }

        .thumbnail.active {
            border: 2px solid #333; /* Borde más fino para la miniatura activa */
        }

    

        /* Botones de navegación de imágenes - Solo flechas */
        .image-navigation {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-50%);
            padding: 0 15px; /* Padding horizontal */
            pointer-events: none; /* Para que los clics pasen a través del contenedor */
        }

        .prev-image, .next-image {
            width: 40px;
            height: 40px;
            background: transparent; /* Fondo transparente */
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto; /* Para que los botones reciban clics */
        }

        .prev-image i, .next-image i {
            font-size: 24px; /* Tamaño de icono aumentado */
            color: #333; /* Color oscuro para las flechas */
        }

        .prev-image:hover i, .next-image:hover i {
            color: #000; /* Color negro al pasar el mouse */
        }

        /* Estilos de información del producto */
        .product-info {
            padding: 0;
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
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px; /* Padding aumentado */
            width: 90%; /* Ancho aumentado */
            max-width: 700px; /* Ancho máximo aumentado */
            border-radius: 8px; /* Radio de borde aumentado */
        }

        .close {
            float: right;
            font-size: 30px; /* Tamaño de texto aumentado */
            font-weight: bold;
            cursor: pointer;
        }

        .size-guide-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px; /* Margen superior aumentado */
            font-size: 18px; /* Tamaño de texto aumentado */
        }

        .size-guide-table th, .size-guide-table td {
            border: 1px solid #ddd;
            padding: 15px; /* Padding aumentado */
            text-align: center;
        }

        .size-guide-table th {
            background-color: #f5f5f5;
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
</head>
<body>
    <header>
        <nav class="navbar">
            <button class="menu-toggle">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="logo"><img src="../img/LogoNav.png" alt="JerSix Logo"></div>
            <ul class="nav-links">
                <li></li>
                <li></li>
                <li></li>
                <li><a href="../index">Inicio</a></li>
                <li><a href="../productos">Productos</a></li>
                <li><a href="../mistery-box">Mistery Box</a></li>
                <li><a href="../giftcard">Giftcard</a></li>
            </ul>
            <div class="search-container">
                <input type="text" placeholder="Buscar productos..." class="search-input">
                <button class="search-button">
                    <span class="material-symbols-outlined">search</span>
                </button>
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
                <img src="<?php echo $product_image; ?>" alt="<?php echo htmlspecialchars($product_name); ?>" class="product-image" id="mainImage" loading="lazy">
                
                <?php if (count($thumbnails) > 1): ?>
                <div class="image-navigation">
                    <button class="prev-image" onclick="changeImageNav(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button class="next-image" onclick="changeImageNav(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                
                <div class="product-thumbnails">
                    <?php foreach ($thumbnails as $index => $thumbnail): ?>
                    <img src="<?php echo $thumbnail; ?>" alt="<?php echo htmlspecialchars($product_name); ?> <?php echo $index+1; ?>" 
                         class="thumbnail <?php echo ($index === 0) ? 'active' : ''; ?>" 
                         onclick="changeImage(this)" loading="lazy">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="product-info">
                <h1 class="product-title"><?php echo htmlspecialchars($product_name); ?></h1>
                <p class="product-price" data-product-id="<?php echo $product_id; ?>">$ <?php echo number_format($price, 2); ?></p>
                
                <div class="shipping-info">
                    <p>Envío gratis a TODO MÉXICO 🇲🇽</p>
                </div>
                
                <div class="section-title">
                    Talla <button class="size-guide-btn" onclick="document.getElementById('sizeGuideModal').style.display='block'">Guía de tallas</button>
                </div>
                <div class="size-options">
                    <div class="size-option">XS</div>    
                    <div class="size-option">S</div>
                    <div class="size-option">M</div>
                    <div class="size-option">L</div>
                    <div class="size-option">XL</div>
                </div>

                <div class="section-title">Cantidad</div>
                <div class="quantity-controls">
                    <button class="quantity-btn minus" id="minusBtn">-</button>
                    <input type="number" class="quantity-input" id="quantityInput" value="1" min="1" max="<?php echo $stock; ?>">
                    <button class="quantity-btn plus" id="plusBtn">+</button>
                </div>
                
                <div class="stock-info">
                    <i class="fas fa-box"></i>
                    <span>Stock disponible: <strong><?php echo $stock; ?></strong> unidades</span>
                </div>
                
                <button class="add-to-cart-btn" <?php echo (!$product_status) ? 'disabled' : ''; ?>>
                    <?php if ($product_status): ?>
                        Agregar al Carrito
                    <?php else: ?>
                        Producto no disponible
                    <?php endif; ?>
                </button>
            </div>
        </div>
        
        <!-- Size Guide Modal -->
        <div id="sizeGuideModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('sizeGuideModal').style.display='none'">&times;</span>
                <h2 style="font-size: 26px; margin-bottom: 20px;">Guía de Tallas</h2>
                <table class="size-guide-table">
                    <thead>
                        <tr>
                            <th>Talla</th>
                            <th>Pecho (cm)</th>
                            <th>Largo (cm)</th>
                            <th>Hombros (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>XS</td>
                            <td>96-101</td>
                            <td>71</td>
                            <td>44</td>
                        </tr>
                        <tr>
                            <td>S</td>
                            <td>96-101</td>
                            <td>71</td>
                            <td>44</td>
                        </tr>
                        <tr>
                            <td>M</td>
                            <td>101-106</td>
                            <td>73</td>
                            <td>46</td>
                        </tr>
                        <tr>
                            <td>L</td>
                            <td>106-111</td>
                            <td>75</td>
                            <td>48</td>
                        </tr>
                        <tr>
                            <td>XL</td>
                            <td>96-101</td>
                            <td>71</td>
                            <td>44</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Sobre Jersix</h3>
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
                <a href="https://wa.me/+528123584236" class="social-link" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
            <p class="copyright">&copy; 2025 Jersix.mx. Todos los derechos reservados.</p>
        </div>
    </footer>
    
    <div class="whatsapp-button">
        <a href="https://wa.me/+528123584236" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>

    <script>
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

    // Asegurar que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM fully loaded");
        
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
                sizeGuideModal.style.display = 'block';
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
    </script>
</body>
</html> 