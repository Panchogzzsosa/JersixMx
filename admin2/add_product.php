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

// Conexión a la base de datos
try {
    $pdo = new PDO('mysql:host=localhost;dbname=checkout', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['product_image']['type'], $allowed_types)) {
            $upload_dir = '../img/products/';
            
            // Crear directorio si no existe
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = time() . '_' . $_FILES['product_image']['name'];
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                $image_url = 'img/products/' . $filename;
            } else {
                $errors[] = "Error al subir la imagen";
            }
        } else {
            $errors[] = "Tipo de archivo no permitido. Solo se permiten JPG, PNG y GIF";
        }
    }
    
    // Si no hay errores, insertar el producto
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, stock, category, image_url) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price, $stock, $category, $image_url]);
            
            $success_message = "Producto agregado correctamente";
            
            // Limpiar los campos después de un envío exitoso
            $name = $description = '';
            $price = $stock = 0;
            $category = '';
        } catch (PDOException $e) {
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
        
        /* Nuevos estilos para la sección de jerseys rápidos */
        .quick-jersey-section {
            margin-bottom: 30px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #eaedf3;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            color: var(--secondary-color);
            border-bottom: 2px solid transparent;
            transition: var(--transition);
        }
        
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .jersey-templates {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .jersey-template {
            border: 1px solid #eaedf3;
            border-radius: var(--border-radius);
            padding: 15px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            position: relative;
        }
        
        .jersey-template:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: var(--box-shadow);
        }
        
        .jersey-template.selected {
            border-color: var(--primary-color);
            background-color: rgba(0,123,255,0.05);
        }
        
        .jersey-template.selected::after {
            content: "✓";
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--primary-color);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .jersey-img {
            width: 100%;
            height: 100px;
            object-fit: contain;
            margin-bottom: 10px;
        }
        
        .jersey-info {
            font-size: 13px;
        }
        
        .jersey-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .jersey-price {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .quick-form {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .jersey-sizes {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .size-checkbox {
            display: none;
        }
        
        .size-label {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .size-checkbox:checked + .size-label {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-primary {
            background-color: rgba(0,123,255,0.1);
            color: var(--primary-color);
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
                <li class="nav-item active">
                    <a href="add_product.php">
                        <i class="fas fa-plus-circle"></i>
                        <span>Agregar Producto</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#">
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
            
            <!-- New Quick Jersey Section -->
            <div class="panel quick-jersey-section">
                <div class="panel-header">
                    <h3 class="panel-title">Agregar Jersey Rápidamente</h3>
                    <span class="badge badge-primary">Nuevo</span>
                </div>
                <div class="panel-body">
                    <div class="tabs">
                        <div class="tab active" data-tab="templates">Plantillas</div>
                        <div class="tab" data-tab="team">Por Equipo</div>
                        <div class="tab" data-tab="custom">Personalizado</div>
                    </div>
                    
                    <!-- Templates Tab -->
                    <div class="tab-content active" id="templates-content">
                        <p>Selecciona una plantilla para comenzar rápidamente:</p>
                        
                        <div class="jersey-templates">
                            <div class="jersey-template" data-template="local" data-price="799.99" data-category="Jersey Futbol">
                                <img src="../img/templates/jersey_local.jpg" alt="Jersey Local" class="jersey-img">
                                <div class="jersey-info">
                                    <div class="jersey-title">Jersey Local</div>
                                    <div class="jersey-price">$799.99</div>
                                </div>
                            </div>
                            
                            <div class="jersey-template" data-template="visitante" data-price="799.99" data-category="Jersey Futbol">
                                <img src="../img/templates/jersey_visitante.jpg" alt="Jersey Visitante" class="jersey-img">
                                <div class="jersey-info">
                                    <div class="jersey-title">Jersey Visitante</div>
                                    <div class="jersey-price">$799.99</div>
                                </div>
                            </div>
                            
                            <div class="jersey-template" data-template="alternativo" data-price="899.99" data-category="Jersey Futbol">
                                <img src="../img/templates/jersey_alternativo.jpg" alt="Jersey Alternativo" class="jersey-img">
                                <div class="jersey-info">
                                    <div class="jersey-title">Jersey Alternativo</div>
                                    <div class="jersey-price">$899.99</div>
                                </div>
                            </div>
                            
                            <div class="jersey-template" data-template="portero" data-price="899.99" data-category="Jersey Futbol">
                                <img src="../img/templates/jersey_portero.jpg" alt="Jersey Portero" class="jersey-img">
                                <div class="jersey-info">
                                    <div class="jersey-title">Jersey Portero</div>
                                    <div class="jersey-price">$899.99</div>
                                </div>
                            </div>
                            
                            <div class="jersey-template" data-template="retro" data-price="999.99" data-category="Jersey Retro">
                                <img src="../img/templates/jersey_retro.jpg" alt="Jersey Retro" class="jersey-img">
                                <div class="jersey-info">
                                    <div class="jersey-title">Jersey Retro</div>
                                    <div class="jersey-price">$999.99</div>
                                </div>
                            </div>
                            
                            <div class="jersey-template" data-template="conmemorativo" data-price="1199.99" data-category="Jersey Edición Especial">
                                <img src="../img/templates/jersey_especial.jpg" alt="Edición Especial" class="jersey-img">
                                <div class="jersey-info">
                                    <div class="jersey-title">Edición Especial</div>
                                    <div class="jersey-price">$1,199.99</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="quick-form" id="template-form" style="display: none;">
                            <h4>Completar detalles del jersey</h4>
                            
                            <form method="post" enctype="multipart/form-data" id="quick-jersey-form">
                                <div class="form-row">
                                    <div class="form-group" style="flex: 2;">
                                        <label class="form-label" for="quick_name">Nombre del Jersey*</label>
                                        <input type="text" id="quick_name" name="name" class="form-control" required>
                                    </div>
                                    
                                    <div class="form-group" style="flex: 1;">
                                        <label class="form-label" for="quick_price">Precio*</label>
                                        <input type="number" id="quick_price" name="price" class="form-control" step="0.01" min="0" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Tallas disponibles</label>
                                    <div class="jersey-sizes">
                                        <input type="checkbox" id="size_xs" name="sizes[]" value="XS" class="size-checkbox">
                                        <label for="size_xs" class="size-label">XS</label>
                                        
                                        <input type="checkbox" id="size_s" name="sizes[]" value="S" class="size-checkbox">
                                        <label for="size_s" class="size-label">S</label>
                                        
                                        <input type="checkbox" id="size_m" name="sizes[]" value="M" class="size-checkbox" checked>
                                        <label for="size_m" class="size-label">M</label>
                                        
                                        <input type="checkbox" id="size_l" name="sizes[]" value="L" class="size-checkbox" checked>
                                        <label for="size_l" class="size-label">L</label>
                                        
                                        <input type="checkbox" id="size_xl" name="sizes[]" value="XL" class="size-checkbox" checked>
                                        <label for="size_xl" class="size-label">XL</label>
                                        
                                        <input type="checkbox" id="size_xxl" name="sizes[]" value="XXL" class="size-checkbox">
                                        <label for="size_xxl" class="size-label">XXL</label>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group" style="flex: 1;">
                                        <label class="form-label" for="quick_team">Equipo</label>
                                        <select id="quick_team" name="team" class="form-control">
                                            <option value="">Seleccionar equipo</option>
                                            <?php foreach ($teams as $team): ?>
                                                <option value="<?php echo htmlspecialchars($team); ?>"><?php echo htmlspecialchars($team); ?></option>
                                            <?php endforeach; ?>
                                            <option value="otro">Otro equipo</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" style="flex: 1;">
                                        <label class="form-label" for="quick_year">Temporada</label>
                                        <select id="quick_year" name="year" class="form-control">
                                            <option value="2023-2024">2023-2024</option>
                                            <option value="2022-2023">2022-2023</option>
                                            <option value="2021-2022">2021-2022</option>
                                            <option value="retro">Retro/Clásico</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="quick_stock">Stock inicial por talla</label>
                                    <input type="number" id="quick_stock" name="stock" class="form-control" value="10" min="0" required>
                                    <span class="form-hint">Cantidad inicial para cada talla seleccionada</span>
                                </div>
                                
                                <input type="hidden" id="quick_description" name="description" value="">
                                <input type="hidden" id="quick_category" name="category" value="">
                                <input type="hidden" id="quick_template" name="template" value="">
                                
                                <div class="form-actions">
                                    <button type="button" id="cancel-template" class="btn btn-secondary">Cancelar</button>
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-bolt"></i> Crear Jersey Rápido
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Team Tab -->
                    <div class="tab-content" id="team-content">
                        <p>Agrega rápidamente jerseys por equipo:</p>
                        
                        <div class="form-group">
                            <label class="form-label" for="bulk_team">Selecciona un equipo</label>
                            <select id="bulk_team" name="bulk_team" class="form-control">
                                <option value="">Seleccionar equipo</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo htmlspecialchars($team); ?>"><?php echo htmlspecialchars($team); ?></option>
                                <?php endforeach; ?>
                                <option value="nuevo">+ Agregar nuevo equipo</option>
                            </select>
                        </div>
                        
                        <div id="team-jerseys-form" style="display: none;">
                            <div class="panel" style="box-shadow: none; border: 1px solid #eaedf3;">
                                <div class="panel-header">
                                    <h4 class="panel-title">Jerseys para <span id="selected-team-name">equipo</span></h4>
                                </div>
                                <div class="panel-body">
                                    <form method="post" id="bulk-team-form">
                                        <div class="jersey-templates" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));">
                                            <div class="jersey-template">
                                                <div class="form-check" style="margin-bottom: 10px;">
                                                    <input type="checkbox" id="team_local" name="team_jerseys[]" value="local" class="form-check-input" checked>
                                                    <label for="team_local" class="form-check-label">Incluir</label>
                                                </div>
                                                <img src="../img/templates/jersey_local.jpg" alt="Local" class="jersey-img" style="height: 80px;">
                                                <div class="jersey-info">
                                                    <div class="jersey-title">Local</div>
                                                    <input type="number" name="price_local" class="form-control" value="799.99" step="0.01" min="0" style="width: 100%; margin-top: 5px;">
                                                </div>
                                            </div>
                                            
                                            <div class="jersey-template">
                                                <div class="form-check" style="margin-bottom: 10px;">
                                                    <input type="checkbox" id="team_visitante" name="team_jerseys[]" value="visitante" class="form-check-input" checked>
                                                    <label for="team_visitante" class="form-check-label">Incluir</label>
                                                </div>
                                                <img src="../img/templates/jersey_visitante.jpg" alt="Visitante" class="jersey-img" style="height: 80px;">
                                                <div class="jersey-info">
                                                    <div class="jersey-title">Visitante</div>
                                                    <input type="number" name="price_visitante" class="form-control" value="799.99" step="0.01" min="0" style="width: 100%; margin-top: 5px;">
                                                </div>
                                            </div>
                                            
                                            <div class="jersey-template">
                                                <div class="form-check" style="margin-bottom: 10px;">
                                                    <input type="checkbox" id="team_alternativo" name="team_jerseys[]" value="alternativo" class="form-check-input">
                                                    <label for="team_alternativo" class="form-check-label">Incluir</label>
                                                </div>
                                                <img src="../img/templates/jersey_alternativo.jpg" alt="Alternativo" class="jersey-img" style="height: 80px;">
                                                <div class="jersey-info">
                                                    <div class="jersey-title">Alternativo</div>
                                                    <input type="number" name="price_alternativo" class="form-control" value="899.99" step="0.01" min="0" style="width: 100%; margin-top: 5px;">
                                                </div>
                                            </div>
                                            
                                            <div class="jersey-template">
                                                <div class="form-check" style="margin-bottom: 10px;">
                                                    <input type="checkbox" id="team_portero" name="team_jerseys[]" value="portero" class="form-check-input">
                                                    <label for="team_portero" class="form-check-label">Incluir</label>
                                                </div>
                                                <img src="../img/templates/jersey_portero.jpg" alt="Portero" class="jersey-img" style="height: 80px;">
                                                <div class="jersey-info">
                                                    <div class="jersey-title">Portero</div>
                                                    <input type="number" name="price_portero" class="form-control" value="899.99" step="0.01" min="0" style="width: 100%; margin-top: 5px;">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row" style="margin-top: 20px;">
                                            <div class="form-group" style="flex: 1;">
                                                <label class="form-label">Tallas disponibles</label>
                                                <div class="jersey-sizes">
                                                    <input type="checkbox" id="team_size_xs" name="team_sizes[]" value="XS" class="size-checkbox">
                                                    <label for="team_size_xs" class="size-label">XS</label>
                                                    
                                                    <input type="checkbox" id="team_size_s" name="team_sizes[]" value="S" class="size-checkbox">
                                                    <label for="team_size_s" class="size-label">S</label>
                                                    
                                                    <input type="checkbox" id="team_size_m" name="team_sizes[]" value="M" class="size-checkbox" checked>
                                                    <label for="team_size_m" class="size-label">M</label>
                                                    
                                                    <input type="checkbox" id="team_size_l" name="team_sizes[]" value="L" class="size-checkbox" checked>
                                                    <label for="team_size_l" class="size-label">L</label>
                                                    
                                                    <input type="checkbox" id="team_size_xl" name="team_sizes[]" value="XL" class="size-checkbox" checked>
                                                    <label for="team_size_xl" class="size-label">XL</label>
                                                    
                                                    <input type="checkbox" id="team_size_xxl" name="team_sizes[]" value="XXL" class="size-checkbox">
                                                    <label for="team_size_xxl" class="size-label">XXL</label>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group" style="flex: 1;">
                                                <label class="form-label" for="team_stock">Stock inicial por talla</label>
                                                <input type="number" id="team_stock" name="team_stock" class="form-control" value="10" min="0">
                                            </div>
                                            
                                            <div class="form-group" style="flex: 1;">
                                                <label class="form-label" for="team_year">Temporada</label>
                                                <select id="team_year" name="team_year" class="form-control">
                                                    <option value="2023-2024">2023-2024</option>
                                                    <option value="2022-2023">2022-2023</option>
                                                    <option value="2021-2022">2021-2022</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="button" id="cancel-team" class="btn btn-secondary">Cancelar</button>
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-bolt"></i> Crear Jerseys de Equipo
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Custom Tab -->
                    <div class="tab-content" id="custom-content">
                        <p>Crea un jersey personalizado con opciones avanzadas:</p>
                        
                        <p>Esta sección te permite crear jerseys personalizados con opciones adicionales como:</p>
                        <ul style="margin-left: 20px; margin-bottom: 20px;">
                            <li>Personalización de nombres y números</li>
                            <li>Parches y emblemas especiales</li>
                            <li>Versiones auténticas vs réplicas</li>
                            <li>Combinaciones de tallas y precios personalizados</li>
                        </ul>
                        
                        <a href="javascript:void(0)" id="show-custom-form" class="btn btn-primary">
                            <i class="fas fa-tshirt"></i> Crear Jersey Personalizado
                        </a>
                    </div>
                </div>
            </div>
            
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
                                        // Añadir "Retro" a la lista si no está en las categorías existentes
                                        $hasRetro = false;
                                        foreach ($categories as $cat): 
                                            $hasRetro = $hasRetro || ($cat === 'Retro');
                                        ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category ?? '') === $cat ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat); ?>
                                            </option>
                                        <?php endforeach; 
                                        
                                        // Añadir Retro si no existe
                                        if (!$hasRetro): ?>
                                            <option value="Retro" <?php echo ($category ?? '') === 'Retro' ? 'selected' : ''; ?>>Retro</option>
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
                                    <label class="form-label" for="product_image">Imagen del Producto</label>
                                    <div class="file-input-container">
                                        <label class="file-input-button">
                                            <i class="fas fa-cloud-upload-alt"></i> Seleccionar Imagen
                                            <input type="file" id="product_image" name="product_image" class="file-input" accept="image/*">
                                        </label>
                                    </div>
                                    <div id="image-preview" class="image-preview"></div>
                                    <span class="form-hint">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB</span>
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
        // Responsive sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (window.innerWidth <= 768) {
                mobileToggle.style.display = 'flex';
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    mobileToggle.style.display = 'flex';
                } else {
                    mobileToggle.style.display = 'none';
                    sidebar.classList.add('active');
                }
            });
            
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
            
            // Mostrar/ocultar campo de nueva categoría
            const categorySelect = document.getElementById('category');
            const newCategoryGroup = document.getElementById('new-category-group');
            
            if (categorySelect && newCategoryGroup) {
                categorySelect.addEventListener('change', function() {
                    if (this.value === 'nueva') {
                        newCategoryGroup.style.display = 'block';
                    } else {
                        newCategoryGroup.style.display = 'none';
                    }
                });
                
                // Si ya está seleccionada "nueva categoría", mostrar el campo
                if (categorySelect.value === 'nueva') {
                    newCategoryGroup.style.display = 'block';
                }
            }
            
            // Vista previa de la imagen
            const fileInput = document.getElementById('product_image');
            const imagePreview = document.getElementById('image-preview');
            
            if (fileInput && imagePreview) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            imagePreview.style.backgroundImage = `