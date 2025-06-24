<?php
session_start();
require_once '../config/database.php';

try {
    $pdo = getConnection();
    
    // Obtener todos los productos disponibles
    $stmt = $pdo->query("SELECT product_id, name FROM products WHERE status = 1 ORDER BY name");
    $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener productos destacados con sus detalles
    $stmt = $pdo->query("
        SELECT fp.position, fp.product_id, p.name, p.price, p.image_url
        FROM featured_products fp
        JOIN products p ON fp.product_id = p.product_id
        WHERE fp.position IN ('producto1', 'producto2', 'producto3')
    ");
    $productPositions = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productPositions[$row['position']] = $row;
    }

    // Obtener las imágenes actuales del banner
    $stmt = $pdo->query("SELECT * FROM banner_images ORDER BY FIELD(position, 'imagen1', 'imagen2', 'imagen3')");
    $bannerImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear array asociativo para fácil acceso
    $imagePositions = [
        'imagen1' => null,
        'imagen2' => null,
        'imagen3' => null
    ];
    
    foreach ($bannerImages as $image) {
        $imagePositions[$image['position']] = $image;
    }

    // Procesar acciones POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_featured_product':
                if (isset($_POST['position'], $_POST['product_id'])) {
                    // Eliminar el producto actual de la posición
                    $stmt = $pdo->prepare("DELETE FROM featured_products WHERE position = ?");
                    $stmt->execute([$_POST['position']]);
                    
                    // Insertar el nuevo producto en la posición
                    $stmt = $pdo->prepare("
                        INSERT INTO featured_products (position, product_id) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$_POST['position'], $_POST['product_id']]);
                    
                    $_SESSION['success_message'] = "Producto destacado actualizado correctamente.";
                    header('Location: banner_manager.php');
                    exit();
                }
                break;
                
            case 'delete_featured_product':
                if (isset($_POST['position'])) {
                    $stmt = $pdo->prepare("DELETE FROM featured_products WHERE position = ?");
                    $stmt->execute([$_POST['position']]);
                    header('Location: banner_manager.php');
                    exit();
                }
                break;

            case 'upload_banner_image':
                if (isset($_POST['position']) && isset($_FILES['banner_image'])) {
                    $position = $_POST['position'];
                    $file = $_FILES['banner_image'];
                    
                    // Validar el archivo
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!in_array($file['type'], $allowedTypes)) {
                        $_SESSION['error_message'] = "Solo se permiten archivos JPG, PNG y WEBP.";
                        break;
                    }
                    
                    // Crear directorio si no existe
                    $uploadDir = '../img/banner/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Generar nombre único para el archivo
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'banner_' . $position . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . $filename;
                    
                    // Mover el archivo
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Eliminar imagen anterior si existe
                        $stmt = $pdo->prepare("SELECT image_url FROM banner_images WHERE position = ?");
                        $stmt->execute([$position]);
                        $oldImage = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($oldImage && file_exists('../' . $oldImage['image_url'])) {
                            unlink('../' . $oldImage['image_url']);
                        }
                        
                        // Actualizar base de datos
                        $relativeFilepath = 'img/banner/' . $filename;
                        $stmt = $pdo->prepare("REPLACE INTO banner_images (position, image_url) VALUES (?, ?)");
                        $stmt->execute([$position, $relativeFilepath]);
                        
                        $_SESSION['success_message'] = "Imagen subida correctamente.";
                        header('Location: banner_manager.php');
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Error al subir la imagen.";
                    }
                }
                break;
                
            case 'delete_banner_image':
                if (isset($_POST['position'])) {
                    $position = $_POST['position'];
                    
                    // Obtener la imagen actual
                    $stmt = $pdo->prepare("SELECT image_url FROM banner_images WHERE position = ?");
                    $stmt->execute([$position]);
                    $image = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Eliminar el archivo si existe
                    if ($image && file_exists('../' . $image['image_url'])) {
                        unlink('../' . $image['image_url']);
                    }
                    
                    // Eliminar el registro de la base de datos
                    $stmt = $pdo->prepare("DELETE FROM banner_images WHERE position = ?");
                    $stmt->execute([$position]);
                    
                    $_SESSION['success_message'] = "Imagen eliminada correctamente.";
                    header('Location: banner_manager.php');
                    exit();
                }
                break;
        }
    }
} catch (Exception $e) {
    error_log("Error en banner_manager.php: " . $e->getMessage());
    $_SESSION['error_message'] = "Ha ocurrido un error. Por favor, inténtelo de nuevo.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Banner y Lo mas vendido | Jersix</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <style>
        /* Reutilizamos los estilos del dashboard */
        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --sidebar-width: 240px;
            --topbar-height: 60px;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #333;
            background-color: #f5f7fa;
            line-height: 1.5;
        }

        /* Estilos del Sidebar */
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            width: var(--sidebar-width);
            padding-top: 15px;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .sidebar .nav-menu {
            list-style: none;
            padding: 15px 0;
        }
        
        .sidebar .nav-item {
            margin: 5px 0;
        }
        
        .sidebar .nav-item a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-radius: 4px;
            margin: 0 8px;
            transition: var(--transition);
        }
        
        .sidebar .nav-item a i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .sidebar .nav-item a:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-item.active a {
            color: white;
            background: var(--primary-color);
        }

        .dashboard-layout {
            display: grid;
            grid-template-columns: var(--sidebar-width) 1fr;
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            grid-column: 2;
            padding: 30px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .topbar h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                width: 0;
                padding-top: 60px;
            }
            
            .sidebar.active {
                width: var(--sidebar-width);
            }
            
            .main-content {
                grid-column: 1;
                padding-top: calc(var(--topbar-height) + 20px);
            }
            
            .mobile-toggle {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background: var(--primary-color);
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
        }

        /* Contenido específico para el gestor de banner */
        .banner-section, .featured-products-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .image-grid, .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .image-item, .product-item {
            position: relative;
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            overflow: hidden;
            cursor: move;
        }

        .image-item img, .product-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .item-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
        }

        .btn-action {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-action:hover {
            background: white;
            transform: scale(1.1);
        }

        .btn-action.delete {
            color: var(--danger-color);
        }

        .upload-form, .product-form {
            margin-top: 20px;
            padding: 20px;
            background: var(--light-color);
            border-radius: var(--border-radius);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
        }

        .product-info {
            padding: 10px;
        }

        .product-info h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
        }

        .product-info p {
            margin: 5px 0 0;
            color: var(--secondary-color);
            font-size: 13px;
        }

        /* Estilos para el drag and drop */
        .ui-sortable-helper {
            box-shadow: var(--box-shadow);
        }

        .ui-sortable-placeholder {
            visibility: visible !important;
            border: 2px dashed #ccc;
            background: #f9f9f9;
            height: 150px;
        }

        .banner-positions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .banner-position {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .banner-position h3 {
            margin-bottom: 15px;
            font-size: 16px;
            color: var(--dark-color);
        }

        .image-container {
            position: relative;
            margin-bottom: 15px;
            border: 2px dashed #ddd;
            border-radius: var(--border-radius);
            overflow: hidden;
            aspect-ratio: 16/9;
        }

        .current-image {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .current-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .empty-image {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #f8f9fa;
            color: #adb5bd;
        }

        .empty-image i {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .btn-action {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-action.delete {
            color: var(--danger-color);
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        .upload-form {
            margin-top: 15px;
        }

        .product-positions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .product-position {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .product-position h3 {
            margin-bottom: 15px;
            font-size: 16px;
            color: var(--dark-color);
        }

        .product-container {
            position: relative;
            margin-bottom: 15px;
            border: 2px dashed #ddd;
            border-radius: var(--border-radius);
            overflow: hidden;
            min-height: 200px;
        }

        .current-product {
            position: relative;
            padding: 15px;
            background: #f8f9fa;
        }

        .current-product img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-bottom: 10px;
        }

        .empty-product {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 200px;
            background: #f8f9fa;
            color: #adb5bd;
            text-align: center;
            padding: 20px;
        }

        .empty-product i {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .empty-product small {
            margin-top: 5px;
            color: #6c757d;
        }

        .product-info {
            padding: 10px 0;
        }

        .product-info h4 {
            margin: 0;
            font-size: 16px;
            color: var(--dark-color);
        }

        .product-info p {
            margin: 5px 0 0;
            color: var(--primary-color);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Jersix.mx</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Inicio</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php">
                        <i class="fas fa-box"></i>
                        <span>Productos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="inventario.php">
                        <i class="fas fa-warehouse"></i>
                        <span>Inventario</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Ventas Web</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="ventas.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Ventas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="newsletter.php">
                        <i class="fas fa-users"></i>
                        <span>Clientes / Newsletter</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="giftcards.php">
                        <i class="fas fa-gift"></i>
                        <span>Gift Cards</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="Promociones.php">
                        <i class="fas fa-percent me-2"></i>
                        <span>Promociones</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="banner_config.php">
                        <i class="fas fa-image"></i>
                        <span>Banner</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="banner_manager.php">
                        <i class="fas fa-images"></i>
                        <span>Fotos y Lo más vendido</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pedidos.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Pedidos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>Gestor de Fotos y Lo más vendido</h1>
            </div>

            <!-- Mensajes de éxito o error -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Banner Section -->
            <section class="banner-section">
                <h2 class="section-title">Imágenes del Banner</h2>
                <div class="banner-positions">
                    <?php
                    $positions = [
                        'imagen1' => 'Primera Imagen',
                        'imagen2' => 'Segunda Imagen',
                        'imagen3' => 'Tercera Imagen'
                    ];
                    
                    foreach ($positions as $position => $title): ?>
                        <div class="banner-position">
                            <h3><?php echo $title; ?></h3>
                            <div class="image-container">
                                <?php if (isset($imagePositions[$position]) && !empty($imagePositions[$position]['image_url'])): ?>
                                    <div class="current-image">
                                        <img src="../<?php echo htmlspecialchars($imagePositions[$position]['image_url']); ?>" alt="<?php echo $title; ?>">
                                        <button class="btn-action delete" onclick="deleteBannerImage('<?php echo $position; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-image">
                                        <i class="fas fa-image"></i>
                                        <p>No hay imagen</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form class="upload-form" action="banner_manager.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_banner_image">
                                <input type="hidden" name="position" value="<?php echo $position; ?>">
                                <div class="form-group">
                                    <label for="banner_<?php echo $position; ?>">Seleccionar imagen</label>
                                    <input type="file" id="banner_<?php echo $position; ?>" name="banner_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                                    <small class="form-text text-muted">Formatos permitidos: JPG, PNG, WEBP. Tamaño recomendado: 1920x1080px</small>
                                </div>
                                <button type="submit" class="btn-submit">
                                    <?php echo isset($imagePositions[$position]) ? 'Actualizar' : 'Subir'; ?> Imagen
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Featured Products Section -->
            <section class="featured-products-section">
                <h2 class="section-title">Productos Destacados</h2>
                <div class="product-positions">
                    <?php
                    $positions = [
                        'producto1' => 'Primer Producto Destacado',
                        'producto2' => 'Segundo Producto Destacado',
                        'producto3' => 'Tercer Producto Destacado'
                    ];
                    
                    foreach ($positions as $position => $title): ?>
                        <div class="product-position">
                            <h3><?php echo $title; ?></h3>
                            <div class="product-container">
                                <?php if (isset($productPositions[$position])): ?>
                                    <div class="current-product">
                                        <img src="../<?php echo htmlspecialchars($productPositions[$position]['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($productPositions[$position]['name']); ?>">
                                        <div class="product-info">
                                            <h4><?php echo htmlspecialchars($productPositions[$position]['name']); ?></h4>
                                            <p>$<?php echo number_format($productPositions[$position]['price'], 2); ?></p>
                                        </div>
                                        <button class="btn-action delete" onclick="deleteFeaturedProduct('<?php echo $position; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-product">
                                        <i class="fas fa-box"></i>
                                        <p>No hay producto seleccionado</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form class="product-form" action="banner_manager.php" method="POST">
                                <input type="hidden" name="action" value="update_featured_product">
                                <input type="hidden" name="position" value="<?php echo $position; ?>">
                                <div class="form-group">
                                    <label for="product_<?php echo $position; ?>">Seleccionar producto</label>
                                    <select id="product_<?php echo $position; ?>" name="product_id" class="form-control" required>
                                        <option value="">Seleccionar producto...</option>
                                        <?php foreach ($allProducts as $product): ?>
                                        <option value="<?php echo $product['product_id']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn-submit">
                                    <?php echo isset($productPositions[$position]) ? 'Actualizar' : 'Seleccionar'; ?> Producto
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function() {
            // Hacer las imágenes del banner ordenables
            $("#bannerImages").sortable({
                update: function(event, ui) {
                    const items = $(this).sortable("toArray", { attribute: "data-id" });
                    updateOrder('banner_images', items);
                }
            });

            // Hacer los productos destacados ordenables
            $("#featuredProducts").sortable({
                update: function(event, ui) {
                    const items = $(this).sortable("toArray", { attribute: "data-id" });
                    updateOrder('featured_products', items);
                }
            });

            // Funcionalidad del sidebar en móviles
            const sidebar = document.querySelector('.sidebar');
            
            // Agregar botón de toggle para móviles si no existe
            if (!document.querySelector('.mobile-toggle')) {
                const toggleButton = document.createElement('button');
                toggleButton.className = 'mobile-toggle';
                toggleButton.innerHTML = '<i class="fas fa-bars"></i>';
                document.body.appendChild(toggleButton);
                
                toggleButton.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Cerrar sidebar al hacer clic fuera de él en móviles
            document.addEventListener('click', function(event) {
                const isMobile = window.innerWidth <= 768;
                if (isMobile && !sidebar.contains(event.target) && !event.target.closest('.mobile-toggle')) {
                    sidebar.classList.remove('active');
                }
            });
        });

        function updateOrder(table, items) {
            $.post('banner_manager.php', {
                action: 'update_order',
                table: table,
                items: JSON.stringify(items)
            });
        }

        function deleteFeaturedProduct(position) {
            if (confirm('¿Estás seguro de que deseas eliminar este producto destacado?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'banner_manager.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_featured_product';
                
                const positionInput = document.createElement('input');
                positionInput.type = 'hidden';
                positionInput.name = 'position';
                positionInput.value = position;
                
                form.appendChild(actionInput);
                form.appendChild(positionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteBannerImage(position) {
            if (confirm('¿Estás seguro de que deseas eliminar esta imagen del banner?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'banner_manager.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_banner_image';
                
                const positionInput = document.createElement('input');
                positionInput.type = 'hidden';
                positionInput.name = 'position';
                positionInput.value = position;
                
                form.appendChild(actionInput);
                form.appendChild(positionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 