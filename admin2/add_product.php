<?php
session_start();

// Mostrar errores durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar inicio de sesión (comentado por ahora para facilitar pruebas)
/*
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
*/

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos usando la función definida en database.php
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Agregar esta función después de la conexión a la base de datos
function getTeams() {
    global $pdo;
    try {
        // Obtener equipos de la categoría "Jersey" o similares
        $stmt = $pdo->query("SELECT DISTINCT name FROM products WHERE category LIKE '%Jersey%' OR category LIKE '%Camiseta%' ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

$teams = getTeams();

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    $category = $_POST['category'] ?? '';
    
    // Si se selecciona "nueva categoría", usar el valor del campo de nueva categoría
    if ($category === 'nueva' && !empty($_POST['new_category'])) {
        $category = $_POST['new_category'];
    }
    
    // Validación básica
    $errors = [];
    if (empty($name)) {
        $errors[] = "El nombre del producto es obligatorio";
    }
    if (!is_numeric($price) || $price <= 0) {
        $errors[] = "El precio debe ser un número positivo";
    }
    if (!is_numeric($stock) || $stock < 0) {
        $errors[] = "El stock debe ser un número no negativo";
    }
    
    // Procesar imagen si se sube
    $image_url = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['product_image']['type'], $allowed_types)) {
            // Definir la ruta base del proyecto
            $base_path = dirname(dirname(__FILE__)); // Sube un nivel desde admin2
            $upload_dir = $base_path . '/img/products/';
            
            // Crear directorio si no existe y establecer permisos
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $errors[] = "Error al crear el directorio de productos. Por favor, verifica los permisos.";
                } else {
                    // Asegurar que los permisos se establezcan correctamente
                    chmod($upload_dir, 0777);
                }
            }
            
            // Verificar si el directorio es escribible
            if (!is_writable($upload_dir)) {
                $errors[] = "El directorio de productos no tiene permisos de escritura. Ruta: " . $upload_dir;
            } else {
                $filename = time() . '_' . basename($_FILES['product_image']['name']);
                $target_file = $upload_dir . $filename;
                
                // Intentar mover el archivo
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                    // Establecer permisos del archivo
                    chmod($target_file, 0644);
                    $image_url = 'img/products/' . $filename;
                } else {
                    $errors[] = "Error al mover el archivo. Verifica los permisos del servidor. Detalles del error: " . error_get_last()['message'];
                }
            }
        } else {
            $errors[] = "Tipo de archivo no permitido. Solo se permiten JPG, PNG, GIF y WEBP";
        }
    }
    
    // Procesar imágenes adicionales
    $additional_images = [];
    if (isset($_FILES['additional_images'])) {
        $files = $_FILES['additional_images'];
        $total_files = count($files['name']);
        
        // Limitar a 5 imágenes
        $total_files = min($total_files, 5);
        
        // Crear directorio para imágenes adicionales si no existe
        $base_path = dirname(dirname(__FILE__));
        $upload_dir = $base_path . '/img/products/additional/';
        
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $errors[] = "Error al crear el directorio de imágenes adicionales.";
            } else {
                chmod($upload_dir, 0777);
            }
        }
        
        if (is_writable($upload_dir)) {
            for ($i = 0; $i < $total_files; $i++) {
                if ($files['error'][$i] === 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($files['type'][$i], $allowed_types)) {
                        $filename = time() . '_' . $i . '_' . basename($files['name'][$i]);
                        $target_file = $upload_dir . $filename;
                        
                        if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
                            chmod($target_file, 0644);
                            $additional_images[] = 'img/products/additional/' . $filename;
                        } else {
                            $errors[] = "Error al subir la imagen adicional " . ($i + 1) . ": " . error_get_last()['message'];
                        }
                    }
                }
            }
        } else {
            $errors[] = "El directorio de imágenes adicionales no tiene permisos de escritura. Ruta: " . $upload_dir;
        }
    }
    
    // Si no hay errores, insertar el producto y sus imágenes adicionales
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insertar el producto principal
            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, stock, category, image_url) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price, $stock, $category, $image_url]);
            
            $product_id = $pdo->lastInsertId();

            // Insertar imágenes adicionales
            if (!empty($additional_images)) {
                $stmt = $pdo->prepare("
                    INSERT INTO product_images (product_id, image_url) 
                    VALUES (?, ?)
                ");
                
                foreach ($additional_images as $img_url) {
                    $stmt->execute([$product_id, $img_url]);
                }
            }

            $pdo->commit();
            $success_message = "Producto agregado correctamente";
            
            // Limpiar los campos después de un envío exitoso
            $name = $description = '';
            $price = $stock = 0;
            $category = '';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error al guardar el producto: " . $e->getMessage();
        }
    }
}

// Obtener categorías para el select
try {
    $categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Producto - Panel de Administración - Jersix</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
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
        
        .dashboard-layout {
            display: grid;
            grid-template-columns: var(--sidebar-width) 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
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
        
        /* Main content */
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
        
        .user-info {
            display: flex;
            align-items: center;
            background: white;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .user-info span {
            margin-right: 15px;
            color: var(--secondary-color);
        }
        
        .user-info .btn {
            margin-left: 10px;
        }
        
        /* Panel */
        .panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .panel-header {
            padding: 20px;
            border-bottom: 1px solid #eaedf3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .panel-body {
            padding: 20px;
        }
        
        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: var(--border-radius);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-hint {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--secondary-color);
        }
        
        .file-input-container {
            position: relative;
            margin-top: 10px;
        }
        
        .file-input-button {
            display: inline-block;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border: 1px dashed #ced4da;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            text-align: center;
        }
        
        .file-input-button:hover {
            background-color: #e9ecef;
        }
        
        .file-input-button i {
            margin-right: 5px;
        }
        
        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .image-preview {
            margin-top: 10px;
            width: 100%;
            height: 150px;
            border-radius: var(--border-radius);
            background-color: #f8f9fa;
            background-size: cover;
            background-position: center;
            border: 1px solid #ced4da;
            display: none;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 14px;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-lg {
            padding: 12px 24px;
            font-size: 16px;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background-color: rgba(220,53,69,0.1);
            border-left-color: var(--danger-color);
            color: var(--danger-color);
        }
        
        .alert-success {
            background-color: rgba(40,167,69,0.1);
            border-left-color: var(--success-color);
            color: var(--success-color);
        }
        
        .alert-icon {
            margin-right: 10px;
            font-size: 16px;
        }
        
        .alert-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .alert-list {
            margin: 10px 0 0 25px;
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
            
            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
            }
        }

        /* Estilos para imágenes adicionales */
        .additional-images-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .additional-image-item {
            position: relative;
            padding-top: 100%;
            background-size: cover;
            background-position: center;
            border-radius: var(--border-radius);
            border: 1px solid #ced4da;
        }

        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .additional-images-container {
            border: 2px dashed #ced4da;
            padding: 20px;
            border-radius: var(--border-radius);
            background-color: #f8f9fa;
            transition: var(--transition);
        }

        .additional-images-container:hover {
            border-color: var(--primary-color);
            background-color: rgba(0,123,255,0.05);
        }

        .drag-drop-hint {
            text-align: center;
            color: var(--secondary-color);
            margin: 10px 0;
            font-size: 0.9em;
        }

        /* Estilos para la previsualización de imagen principal */
        .image-preview-container {
            position: relative;
            margin-top: 15px;
            display: inline-block;
        }

        .image-preview {
            width: 200px;
            height: 200px;
            border-radius: var(--border-radius);
            background-color: #f8f9fa;
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            border: 1px solid #ced4da;
            display: none;
        }

        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
            border: none;
            padding: 0;
        }

        .remove-image:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .file-input-button {
            display: inline-block;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border: 1px dashed #ced4da;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            text-align: center;
        }

        .file-input-button:hover {
            background-color: #e9ecef;
            border-color: var(--primary-color);
        }

        .file-input-button i {
            margin-right: 5px;
            color: var(--primary-color);
        }

        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
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
                    <a href="orders.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Pedidos</span>
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
                    <a href="promociones.php">
                        <i class="fas fa-percent"></i>
                        <span>Promociones</span>
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
            <!-- Top Bar -->
            <div class="topbar">
                <h1>Agregar Nuevo Producto</h1>
                <div class="user-info">
                    <span>Usuario: Admin</span>
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Volver a Productos
                    </a>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <div class="alert-title">
                        <i class="fas fa-exclamation-circle alert-icon"></i>
                        Por favor corrige los siguientes errores:
                    </div>
                    <ul class="alert-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <div class="alert-title">
                        <i class="fas fa-check-circle alert-icon"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                    <p>El producto ha sido agregado correctamente a la base de datos.</p>
                </div>
            <?php endif; ?>
            
            <!-- Regular Product Form Panel -->
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Información del Producto</h3>
                </div>
                <div class="panel-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-grid">
                            <div>
                                <div class="form-group">
                                    <label class="form-label" for="name">Nombre del Producto *</label>
                                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                    <span class="form-hint">Ejemplo: Camisa Deportiva Azul</span>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="price">Precio *</label>
                                    <input type="number" id="price" name="price" class="form-control" value="<?php echo htmlspecialchars($price ?? ''); ?>" step="0.01" min="0" required>
                                    <span class="form-hint">Ingresa el precio sin el signo de $</span>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="stock">Stock *</label>
                                    <input type="number" id="stock" name="stock" class="form-control" value="<?php echo htmlspecialchars($stock ?? ''); ?>" min="0" required>
                                    <span class="form-hint">Cantidad disponible del producto</span>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="category">Categoría</label>
                                    <select id="category" name="category" class="form-control">
                                        <option value="">Selecciona una categoría</option>
                                        <?php 
                                        // Añadir "Retro" y "Edición Especial" a la lista si no están en las categorías existentes
                                        $hasRetro = false;
                                        $hasEspecial = false;
                                        foreach ($categories as $cat): 
                                            $hasRetro = $hasRetro || ($cat === 'Retro');
                                            $hasEspecial = $hasEspecial || ($cat === 'Edición Especial');
                                        ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category ?? '') === $cat ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat); ?>
                                            </option>
                                        <?php endforeach; 
                                        
                                        // Añadir Retro si no existe
                                        if (!$hasRetro): ?>
                                            <option value="Retro" <?php echo ($category ?? '') === 'Retro' ? 'selected' : ''; ?>>Retro</option>
                                        <?php endif;
                                        
                                        // Añadir Edición Especial si no existe
                                        if (!$hasEspecial): ?>
                                            <option value="Edición Especial" <?php echo ($category ?? '') === 'Edición Especial' ? 'selected' : ''; ?>>Edición Especial</option>
                                        <?php endif; ?>
                                        <option value="nueva">+ Nueva Categoría</option>
                                    </select>
                                    <span class="form-hint">Selecciona una categoría existente o crea una nueva</span>
                                </div>
                                
                                <div class="form-group" id="new-category-group" style="display: none;">
                                    <label class="form-label" for="new_category">Nueva Categoría</label>
                                    <input type="text" id="new_category" name="new_category" class="form-control">
                                    <span class="form-hint">Nombre de la nueva categoría</span>
                                </div>
                            </div>
                            
                            <div>
                                <div class="form-group">
                                    <label class="form-label" for="description">Descripción</label>
                                    <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                                    <span class="form-hint">Descripción detallada del producto</span>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="product_image">Imagen Principal del Producto</label>
                                    <div class="file-input-container">
                                        <label class="file-input-button">
                                            <i class="fas fa-cloud-upload-alt"></i> Seleccionar Imagen Principal
                                            <input type="file" id="product_image" name="product_image" class="file-input" accept="image/*">
                                        </label>
                                    </div>
                                    <div id="main-image-preview" class="image-preview-container">
                                        <div id="image-preview" class="image-preview"></div>
                                        <button type="button" id="remove-main-image" class="remove-image" style="display: none;">×</button>
                                    </div>
                                    <span class="form-hint">Formatos permitidos: JPG, PNG, GIF, WEBP. Tamaño máximo: 2MB</span>
                                </div>

                                <!-- Nuevo campo para imágenes adicionales -->
                                <div class="form-group">
                                    <label class="form-label">Imágenes Adicionales</label>
                                    <div class="additional-images-container">
                                        <div class="file-input-container">
                                            <label class="file-input-button">
                                                <i class="fas fa-images"></i> Seleccionar Imágenes Adicionales
                                                <input type="file" id="additional_images" name="additional_images[]" class="file-input" accept="image/*" multiple>
                                            </label>
                                        </div>
                                        <div id="additional-images-preview" class="additional-images-preview"></div>
                                        <span class="form-hint">Puedes seleccionar múltiples imágenes. Máximo 5 imágenes adicionales.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="products.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Guardar Producto
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Mobile menu toggle -->
    <div class="mobile-toggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Previsualización de imagen principal
            const mainImageInput = document.getElementById('product_image');
            const imagePreview = document.getElementById('image-preview');
            const removeMainImage = document.getElementById('remove-main-image');
            
            if (mainImageInput && imagePreview && removeMainImage) {
                mainImageInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            imagePreview.style.backgroundImage = `url(${e.target.result})`;
                            imagePreview.style.display = 'block';
                            removeMainImage.style.display = 'flex';
                        };
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
                
                // Eliminar imagen principal
                removeMainImage.addEventListener('click', function() {
                    imagePreview.style.backgroundImage = '';
                    imagePreview.style.display = 'none';
                    removeMainImage.style.display = 'none';
                    mainImageInput.value = ''; // Limpiar el input file
                });
            }

            // Manejar imágenes adicionales
            const additionalImagesInput = document.getElementById('additional_images');
            const additionalImagesPreview = document.getElementById('additional-images-preview');
            
            if (additionalImagesInput && additionalImagesPreview) {
                additionalImagesInput.addEventListener('change', function() {
                    // Limitar a 5 imágenes
                    if (this.files.length > 5) {
                        alert('Por favor, selecciona máximo 5 imágenes adicionales.');
                        this.value = '';
                        return;
                    }

                    additionalImagesPreview.innerHTML = '';
                    
                    Array.from(this.files).forEach((file, index) => {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const imageContainer = document.createElement('div');
                            imageContainer.className = 'additional-image-item';
                            imageContainer.style.backgroundImage = `url(${e.target.result})`;
                            
                            const removeButton = document.createElement('div');
                            removeButton.className = 'remove-image';
                            removeButton.innerHTML = '×';
                            removeButton.onclick = function() {
                                // Crear un nuevo FileList sin esta imagen
                                const dt = new DataTransfer();
                                const files = additionalImagesInput.files;
                                for (let i = 0; i < files.length; i++) {
                                    if (i !== index) dt.items.add(files[i]);
                                }
                                additionalImagesInput.files = dt.files;
                                imageContainer.remove();
                            };
                            
                            imageContainer.appendChild(removeButton);
                            additionalImagesPreview.appendChild(imageContainer);
                        };
                        
                        reader.readAsDataURL(file);
                    });
                });
            }
        });
    </script>
</body>
</html>