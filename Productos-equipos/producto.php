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
    
    // Obtener datos del producto
    $product_name = $product['name'];
    $product_image = $product['image_url'];
    $stock = $product['stock'];
    $price = $product['price'];
    $product_id = $product['product_id'];
    
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
    <script src="../Js/Producto-equipos.js" defer></script>
    <script src="../Js/search.js" defer></script>
    <script src="../Js/products-data.js" defer></script>
    <script src="../Js/cart.js" defer></script>
    <script src="../Js/newsletter.js" defer></script>
    <style>
        /* Size Guide Styles */
        .size-guide-btn {
            background: none;
            border: none;
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 10px;
        }

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
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .size-guide-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .size-guide-table th,
        .size-guide-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }

        .size-guide-table th {
            background-color: #f5f5f5;
        }
        .product-detail {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }

        .product-image-container {
            position: relative;
            overflow: hidden;
        }

        .product-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .product-image-container:hover .product-image {
            transform: none;
            cursor: default;
        }

        .product-thumbnails {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            border-radius: 4px;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            object-fit: cover;
        }

        .thumbnail:hover,
        .thumbnail.active {
            opacity: 1;
        }

        /* Estilos para navegación de imágenes */
        .image-navigation {
            position: absolute;
            width: 100%;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            padding: 0 10px;
            pointer-events: none;
        }

        .prev-image, .next-image {
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease;
        }

        .prev-image:hover, .next-image:hover {
            background-color: rgba(255, 255, 255, 1);
        }

        .prev-image i, .next-image i {
            color: #333;
            font-size: 14px;
        }

        /* Estilos para indicador de zoom */
        .zoom-indicator {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .zoom-indicator i {
            font-size: 14px;
        }

        .product-image-container:hover .zoom-indicator {
            opacity: 1;
        }

        .product-info {
            padding: 1rem 0;
        }

        .product-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .product-price {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1.5rem;
        }

        .size-selector {
            margin-bottom: 2rem;
        }

        .size-selector h3 {
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .size-options {
            display: flex;
            gap: 1rem;
        }

        .size-option {
            width: 50px;
            height: 50px;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .size-option:hover {
            border-color: #333;
        }

        .size-option.selected {
            background: #333;
            color: white;
            border-color: #333;
        }

        .shipping-info {
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: #666;
        }

        .personalization {
            margin-bottom: 2rem;
        }

        .quantity-selector {
            margin-bottom: 2rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .quantity-input {
            width: 60px;
            height: 40px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .personalization select {
            width: 100%;
            padding: 0.8rem;
            margin-top: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .personalization-input {
            width: 100%;
            padding: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        #personalization-fields {
            margin-top: 1rem;
        }

        .add-to-cart-btn {
            width: 100%;
            padding: 1rem;
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .add-to-cart-btn:hover {
            background: #000;
        }

        @media (max-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr;
            }
        }

        /* Estilos para el panel de depuración */
        .debug-panel {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
        }
        
        .debug-panel h3 {
            margin-top: 0;
            color: #333;
            font-size: 16px;
        }
        
        .debug-panel pre {
            margin: 0;
            white-space: pre-wrap;
        }
        
        .refresh-cache-btn {
            display: inline-block;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            margin-top: 15px;
            font-family: Arial, sans-serif;
            transition: background-color 0.3s;
        }
        
        .refresh-cache-btn:hover {
            background-color: #218838;
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
                <li><a href="../giftcard" class="active">Giftcard</a></li>
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
        <div class="product-detail">
            <div class="product-image-container">
                <img src="<?php echo $product_image; ?>" alt="<?php echo htmlspecialchars($product_name); ?>" class="product-image" id="mainImage" loading="lazy">
                <?php if (count($thumbnails) > 1): ?>
                <div class="product-thumbnails">
                    <?php foreach ($thumbnails as $index => $thumbnail): ?>
                    <img src="<?php echo $thumbnail; ?>" alt="<?php echo htmlspecialchars($product_name); ?> <?php echo $index+1; ?>" 
                         class="thumbnail <?php echo ($index === 0) ? 'active' : ''; ?>" 
                         onclick="changeImage(this)" loading="lazy">
                    <?php endforeach; ?>
                </div>
                
                <!-- Botones de navegación de imágenes -->
                <div class="image-navigation">
                    <button class="prev-image" onclick="changeImageNav(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button class="next-image" onclick="changeImageNav(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                
                <!-- Indicador de zoom -->
                <div class="zoom-indicator">
                    <i class="fas fa-search-plus"></i> Mueva el cursor para hacer zoom
                </div>
                <?php endif; ?>
                
                <!-- Panel de depuración (oculto por defecto) -->
                <?php if (isset($_GET['debug'])): ?>
                <div class="debug-panel">
                    <h3>Información de Depuración</h3>
                    <pre><?php echo json_encode($debug_info, JSON_PRETTY_PRINT); ?></pre>
                    <a href="?id=<?php echo $product_id; ?>&debug=1&refreshCache=<?php echo time(); ?>" class="refresh-cache-btn">Recargar caché de imágenes</a>
                </div>
                <?php endif; ?>
            </div>
            <div class="product-info">
                <h1 class="product-title"><?php echo htmlspecialchars($product_name); ?></h1>
                <p class="product-price" data-product-id="<?php echo $product_id; ?>">$ <?php echo number_format($price, 2); ?></p>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const priceElement = document.querySelector('.product-price');
                    const productId = priceElement.getAttribute('data-product-id');

                    function updatePrice() {
                        fetch(`../get_product_price.php?id=${productId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    priceElement.textContent = `$ ${parseFloat(data.price).toFixed(2)}`;
                                }
                            })
                            .catch(error => console.error('Error fetching price:', error));
                    }

                    // Update price initially
                    updatePrice();

                    // Update price every 30 seconds
                    setInterval(updatePrice, 30000);
                });
                </script>
                <div class="shipping-info">
                    <p>Envío gratis a TODO MÉXICO 🇲🇽</p>
                </div>
                <div class="size-selector">
                    <h3>Talla <button class="size-guide-btn" onclick="document.getElementById('sizeGuideModal').style.display='block'">Guía de tallas</button></h3>
                    <div class="size-options">
                        <div class="size-option">XS</div>
                        <div class="size-option">S</div>
                        <div class="size-option">M</div>
                        <div class="size-option">L</div>
                        <div class="size-option">XL</div>
                    </div>
                </div>

                <!-- Size Guide Modal -->
                <div id="sizeGuideModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="document.getElementById('sizeGuideModal').style.display='none'">&times;</span>
                        <h2>Guía de Tallas</h2>
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
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="quantity-selector">
                    <h3>Cantidad</h3>
                    <div class="quantity-controls">
                        <button class="quantity-btn minus">-</button>
                        <input type="number" class="quantity-input" value="1" min="1" max="<?php echo $stock; ?>">
                        <button class="quantity-btn plus">+</button>
                    </div>
                    <br>
                    <div class="stock-info" style="background-color: #f8f9fa; padding: 12px 16px; border-radius: 6px; margin-top: 10px; font-size: 0.95rem; color: #495057; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-box" style="color: #6c757d;"></i>
                        <span>Stock disponible: <strong><?php echo $stock; ?></strong> unidades</span>
                    </div>
                </div>
                <div class="personalization" style="display: none;">
                    <h3>Personalizar Camiseta</h3>
                    <select id="personalization-select" disabled>
                        <option value="none">Sin Personalizar</option>
                        <option value="custom">Personalizar</option>
                    </select>
                    <div id="personalization-fields" style="display: none;">
                        <input type="text" placeholder="Nombre en la camiseta" class="personalization-input" id="jersey-name" maxlength="20" disabled>
                        <input type="number" placeholder="Número" class="personalization-input" id="jersey-number" min="1" max="99" disabled>
                    </div>
                </div>
                <button class="add-to-cart-btn">Agregar al Carrito</button>
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
    
    <div id="notification" class="notification"></div>
</body>
</html> 