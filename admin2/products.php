<?php
session_start();

// Mostrar errores durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    $stmt = $pdo->query("SELECT * FROM products ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error al obtener productos: " . $e->getMessage();
    $products = [];
}

// Mensaje de eliminación exitosa
$success_message = null;
if (isset($_GET['deleted']) && $_GET['deleted'] == 'true') {
    $success_message = "El producto ha sido eliminado correctamente.";
}

// Error al intentar eliminar
$error_message = null;
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_id':
            $error_message = "No se especificó el ID del producto a eliminar.";
            break;
        case 'not_found':
            $error_message = "El producto que intentas eliminar no existe.";
            break;
        default:
            $error_message = "Ocurrió un error al procesar la solicitud.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Panel de Administración - Jersix</title>
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
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 8px 16px;
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
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #bd2130;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
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
            margin-bottom: 10px;
        }
        
        .panel-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .panel-body {
            padding: 20px;
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
            margin: 0 20px 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 12px 15px;
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        td {
            padding: 15px;
            vertical-align: middle;
            min-height: 80px;
        }
        
        tr:hover {
            background-color: rgba(0,123,255,0.03);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin: 0 auto;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-start;
        }
        
        td.actions {
            padding-top: 20px;
            vertical-align: top;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 30px;
            margin-top: 5px;
        }
        
        .btn-sm i {
            margin-right: 4px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-in-stock {
            background-color: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        .status-low-stock {
            background-color: rgba(255,193,7,0.1);
            color: var(--warning-color);
        }
        
        .status-out-of-stock {
            background-color: rgba(220,53,69,0.1);
            color: var(--danger-color);
        }
        
        .product-name {
            font-weight: 500;
        }
        
        .product-price {
            font-weight: 600;
        }
        
        .product-category {
            display: inline-block;
            padding: 3px 8px;
            background-color: #f8f9fa;
            border-radius: 50px;
            font-size: 12px;
            color: var(--secondary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state-icon {
            font-size: 48px;
            color: #e9ecef;
            margin-bottom: 10px;
        }
        
        .empty-state-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .empty-state-description {
            color: var(--secondary-color);
            margin-bottom: 20px;
        }
        
        .search-filter {
            display: flex;
            gap: 15px;
            margin: 0 20px 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: var(--border-radius);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: var(--transition);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
        }
        
        .filter-select {
            min-width: 200px;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: var(--border-radius);
            background-color: white;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: var(--transition);
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
        }
        
        /* Error message */
        .error-message {
            padding: 15px;
            border-radius: var(--border-radius);
            background-color: rgba(220,53,69,0.1);
            color: var(--danger-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .error-message i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        /* Mensaje de éxito */
        .success-message {
            padding: 15px;
            border-radius: var(--border-radius);
            background-color: rgba(40,167,69,0.1);
            color: var(--success-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .success-message i {
            margin-right: 10px;
            font-size: 18px;
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
            
            /* Estilos para vista móvil de productos */
            .table-container {
                overflow-x: visible;
                margin: 0;
                padding: 0 15px;
            }
            
            table, thead, tbody, tr, th, td {
                display: block;
                width: 100%;
            }
            
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            tr {
                margin-bottom: 20px;
                border: 1px solid #e9ecef;
                border-radius: var(--border-radius);
                background-color: white;
                box-shadow: var(--box-shadow);
                overflow: hidden;
            }
            
            td {
                border: none;
                position: relative;
                padding: 12px 15px;
                padding-left: 50%;
                text-align: right;
            }
            
            td:before {
                position: absolute;
                top: 12px;
                left: 15px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: var(--secondary-color);
                content: attr(data-label);
            }
            
            td:last-child {
                border-bottom: 0;
            }
            
            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-end;
            }
            
            .btn-sm {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            /* Ajustes para la imagen del producto */
            .product-image {
                width: 80px;
                height: 80px;
                margin: 0 auto 10px;
            }
            
            /* Ajustes para el filtro de búsqueda */
            .search-filter {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-select {
                width: 100%;
            }
        }
        
        /* Estilos para el interruptor de activación/desactivación */
        .toggle-container {
            display: flex;
            align-items: center;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            margin-right: 10px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--success-color);
        }
        
        input:focus + .toggle-slider {
            box-shadow: 0 0 1px var(--success-color);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .toggle-label {
            font-size: 13px;
            font-weight: 500;
        }
        
        input:checked ~ .toggle-label {
            color: var(--success-color);
        }
        
        input:not(:checked) ~ .toggle-label {
            color: var(--secondary-color);
        }
        
        /* Estilos para campos editables */
        .editable-field {
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.2s;
            display: inline-block;
        }
        
        .editable-field:hover {
            background-color: rgba(0,123,255,0.05);
        }
        
        .editable-field.editing {
            background-color: #fff;
            border: 2px solid var(--primary-color);
            padding: 4px;
        }
        
        .edit-input {
            width: 80px;
            padding: 4px;
            border: none;
            font-size: inherit;
            font-weight: inherit;
            font-family: inherit;
            text-align: left;
            background: transparent;
            color: inherit;
        }
        
        .edit-input:focus {
            outline: none;
        }
        
        /* Tooltip para edición */
        .editable-field::after {
            content: 'Doble clic para editar';
            position: absolute;
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
            margin-top: 5px;
            z-index: 1000;
        }
        
        .editable-field:hover::after {
            opacity: 1;
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
                <li class="nav-item active">
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
                        <span>Compras</span>
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
                    <a href="banner_config.php">
                        <i class="fas fa-image"></i>
                        <span>Banner</span>
                    </a>
                </li>
                <li class="nav-item">
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
            <!-- Top Bar -->
            <div class="topbar">
                <div>
                    <h1>Gestión de Productos</h1>
                </div>
                <div class="user-info">
                    <img src="../img/ICON.png" alt="User" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                    <span><?php echo $_SESSION['admin_name'] ?? 'Administrador'; ?></span>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Lista de Productos</h3>
                    <div class="panel-actions">
                        <a href="add_product.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Agregar
                        </a>
                        <a href="export_meta_csv.php" class="btn btn-sm btn-success" style="background-color: #28a745; border-color: #28a745; color: #ffffff; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s ease;">
                            <i class="fas fa-file-export"></i> Exportar para Meta
                        </a>
                        <button id="toggleAllVisibilityBtn" class="btn btn-sm btn-warning">
                            <i class="fas fa-eye"></i> Cambiar Visibilidad
                        </button>
                    </div>
                </div>
                
                <div class="search-filter">
                    <input type="text" class="search-input" placeholder="Buscar producto..." id="searchInput">
                    <select class="filter-select" id="categoryFilter">
                        <option value="">Todas las categorías</option>
                        <?php 
                        // Obtener categorías únicas
                        $categories = [];
                        foreach ($products as $product) {
                            if (!empty($product['category']) && !in_array($product['category'], $categories)) {
                                $categories[] = $product['category'];
                            }
                        }
                        
                        // Añadir la categoría "Retro" si no existe ya
                        if (!in_array('Retro', $categories)) {
                            $categories[] = 'Retro';
                        }
                        
                        sort($categories);
                        
                        foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3 class="empty-state-title">No hay productos disponibles</h3>
                        <p class="empty-state-description">Comienza añadiendo un nuevo producto a tu inventario</p>
                        <a href="add_product.php" class="btn btn-primary">Agregar Producto</a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Imagen</th>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Estado Stock</th>
                                    <th>Visible</th>
                                    <th style="width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody">
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td data-label="Imagen">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                            <?php else: ?>
                                                <div class="product-image">Sin imagen</div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Producto">
                                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <?php if (!empty($product['description'])): ?>
                                                <small><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . (strlen($product['description']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Categoría">
                                            <?php if (!empty($product['category'])): ?>
                                                <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                                            <?php else: ?>
                                                <span class="product-category">Sin categoría</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Precio">
                                            <span class="editable-field product-price" 
                                                  data-product-id="<?php echo $product['product_id']; ?>" 
                                                  data-field="price"
                                                  data-original-value="<?php echo $product['price']; ?>" 
                                                  ondblclick="makeEditable(this)">
                                                $<?php echo number_format($product['price'], 2); ?>
                                            </span>
                                        </td>
                                        <td data-label="Stock">
                                            <span class="editable-field product-stock" 
                                                  data-product-id="<?php echo $product['product_id']; ?>" 
                                                  data-field="stock"
                                                  data-original-value="<?php echo $product['stock']; ?>" 
                                                  ondblclick="makeEditable(this)">
                                                <?php echo htmlspecialchars($product['stock']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Estado Stock">
                                            <?php
                                            $stock = intval($product['stock']);
                                            if ($stock <= 0): ?>
                                                <span class="status-badge status-out-of-stock">Sin stock</span>
                                            <?php elseif ($stock < 5): ?>
                                                <span class="status-badge status-low-stock">Stock bajo</span>
                                            <?php else: ?>
                                                <span class="status-badge status-in-stock">En stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Visible">
                                            <div class="toggle-container">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" class="status-toggle" 
                                                           data-product-id="<?php echo $product['product_id']; ?>" 
                                                           <?php echo (isset($product['status']) && $product['status'] == 1) ? 'checked' : ''; ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <span class="toggle-label" id="status-label-<?php echo $product['product_id']; ?>">
                                                    <?php echo (isset($product['status']) && $product['status'] == 1) ? 'Visible' : 'Oculto'; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td data-label="Acciones" class="actions">
                                            <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Editar
                                            </a>
                                            <a href="manage_product_images.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-images"></i> Imágenes
                                            </a>
                                            <a href="delete_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar este producto?')">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
            
            // Filtro de búsqueda y categoría
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const productsTableBody = document.getElementById('productsTableBody');
            
            if (searchInput && categoryFilter && productsTableBody) {
                const rows = productsTableBody.querySelectorAll('tr');
                
                function filterProducts() {
                    const searchTerm = searchInput.value.toLowerCase();
                    const category = categoryFilter.value;
                    
                    rows.forEach(row => {
                        const productName = row.querySelector('.product-name').textContent.toLowerCase();
                        const productCategory = row.querySelector('.product-category').textContent;
                        
                        const matchesSearch = productName.includes(searchTerm);
                        const matchesCategory = category === '' || productCategory === category;
                        
                        if (matchesSearch && matchesCategory) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }
                
                searchInput.addEventListener('input', filterProducts);
                categoryFilter.addEventListener('change', filterProducts);
            }
            
            // Botón para cambiar la visibilidad de todos los productos
            const toggleAllVisibilityBtn = document.getElementById('toggleAllVisibilityBtn');
            if (toggleAllVisibilityBtn) {
                toggleAllVisibilityBtn.addEventListener('click', function() {
                    // Verificar si todos los productos están visibles o no
                    const statusToggles = document.querySelectorAll('.status-toggle');
                    const allVisible = Array.from(statusToggles).every(toggle => toggle.checked);
                    const newStatus = allVisible ? 0 : 1; // Cambiar a 0 si todos son visibles, a 1 si no
                    
                    // Confirmar la acción
                    if (confirm(`¿Estás seguro de que deseas ${allVisible ? 'ocultar' : 'mostrar'} todos los productos?`)) {
                        // Mostrar indicador de carga
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
                        this.disabled = true;
                        
                        // Enviar solicitud AJAX para actualizar todos los productos
                        fetch('update_all_products_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `status=${newStatus}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Actualizar todos los toggles y etiquetas
                                statusToggles.forEach(toggle => {
                                    toggle.checked = !allVisible;
                                    const productId = toggle.getAttribute('data-product-id');
                                    const statusLabel = document.getElementById(`status-label-${productId}`);
                                    if (statusLabel) {
                                        statusLabel.textContent = !allVisible ? 'Visible' : 'Oculto';
                                    }
                                });
                                
                                // Actualizar el texto del botón
                                this.innerHTML = allVisible ? 
                                    '<i class="fas fa-eye"></i> Mostrar Todos' : 
                                    '<i class="fas fa-eye-slash"></i> Ocultar Todos';
                                
                                // Mostrar mensaje de éxito
                                showNotification('success', data.message);
                            } else {
                                // Mostrar mensaje de error
                                showNotification('error', data.message || 'Error al actualizar los productos');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showNotification('error', 'Error de conexión al actualizar los productos');
                        })
                        .finally(() => {
                            // Restaurar el botón
                            this.disabled = false;
                        });
                    }
                });
            }
        });
        
        // Función para manejar el cambio de estado (activar/desactivar productos)
        document.addEventListener('DOMContentLoaded', function() {
            const statusToggles = document.querySelectorAll('.status-toggle');
            
            statusToggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const productId = this.getAttribute('data-product-id');
                    const isActive = this.checked ? 1 : 0;
                    const statusLabel = document.getElementById(`status-label-${productId}`);
                    
                    // Mostrar indicador de carga
                    if (statusLabel) {
                        statusLabel.textContent = 'Actualizando...';
                    }
                    
                    // Enviar solicitud AJAX para actualizar el estado
                    fetch('update_product_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&status=${isActive}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Actualizar la etiqueta de estado
                            if (statusLabel) {
                                statusLabel.textContent = isActive ? 'Visible' : 'Oculto';
                            }
                            
                            // Mostrar mensaje de éxito
                            showNotification('success', `Producto ${isActive ? 'activado' : 'desactivado'} correctamente`);
                        } else {
                            // Revertir el toggle si hay error
                            this.checked = !this.checked;
                            if (statusLabel) {
                                statusLabel.textContent = this.checked ? 'Visible' : 'Oculto';
                            }
                            
                            // Mostrar mensaje de error
                            showNotification('error', data.message || 'Error al actualizar el estado');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Revertir el toggle si hay error
                        this.checked = !this.checked;
                        if (statusLabel) {
                            statusLabel.textContent = this.checked ? 'Visible' : 'Oculto';
                        }
                        
                        // Mostrar mensaje de error
                        showNotification('error', 'Error de conexión al actualizar el estado');
                    });
                });
            });
            
            // Función para mostrar notificaciones
            function showNotification(type, message) {
                const notification = document.createElement('div');
                notification.className = `notification ${type === 'success' ? 'success-message' : 'error-message'}`;
                notification.innerHTML = `
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    <span>${message}</span>
                `;
                
                document.querySelector('.main-content').insertBefore(notification, document.querySelector('.panel'));
                
                // Eliminar la notificación después de 3 segundos
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 3000);
            }
        });
        
        function makeEditable(element) {
            const originalValue = element.getAttribute('data-original-value');
            const productId = element.getAttribute('data-product-id');
            const field = element.getAttribute('data-field');
            const isPrice = field === 'price';
            
            // Crear input
            const input = document.createElement('input');
            input.type = 'number';
            input.step = isPrice ? '0.01' : '1';
            input.min = '0';
            input.value = originalValue;
            input.className = 'edit-input';
            
            // Guardar el contenido original
            const originalContent = element.innerHTML;
            
            // Añadir clase de edición
            element.classList.add('editing');
            
            // Reemplazar contenido con input
            element.innerHTML = '';
            element.appendChild(input);
            
            // Enfocar el input
            input.focus();
            
            // Manejar el evento blur
            input.addEventListener('blur', function() {
                finishEditing();
            });
            
            // Manejar tecla Enter y Escape
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    finishEditing();
                } else if (e.key === 'Escape') {
                    element.classList.remove('editing');
                    element.innerHTML = originalContent;
                }
            });
            
            function finishEditing() {
                const newValue = parseFloat(input.value);
                
                if (isNaN(newValue) || newValue < 0) {
                    showNotification('error', `Por favor ingresa un ${isPrice ? 'precio' : 'stock'} válido`);
                    element.classList.remove('editing');
                    element.innerHTML = originalContent;
                    return;
                }
                
                // Mostrar indicador de carga
                element.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                // Enviar actualización al servidor
                fetch('update_product_field.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&field=${field}&value=${newValue}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        element.classList.remove('editing');
                        
                        // Formatear el valor según el tipo de campo
                        const displayValue = isPrice ? '$' + newValue.toFixed(2) : newValue.toString();
                        element.innerHTML = displayValue;
                        element.setAttribute('data-original-value', newValue);
                        
                        // Actualizar el estado del stock si es necesario
                        if (field === 'stock') {
                            updateStockStatus(element.closest('tr'), newValue);
                        }
                        
                        showNotification('success', `${isPrice ? 'Precio' : 'Stock'} actualizado correctamente`);
                    } else {
                        throw new Error(data.message || `Error al actualizar el ${isPrice ? 'precio' : 'stock'}`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    element.classList.remove('editing');
                    element.innerHTML = originalContent;
                    showNotification('error', error.message || `Error al actualizar el ${isPrice ? 'precio' : 'stock'}`);
                });
            }
        }
        
        function updateStockStatus(row, newStock) {
            const statusCell = row.querySelector('td:nth-child(6)');
            if (statusCell) {
                let newStatus;
                if (newStock <= 0) {
                    newStatus = '<span class="status-badge status-out-of-stock">Sin stock</span>';
                } else if (newStock < 5) {
                    newStatus = '<span class="status-badge status-low-stock">Stock bajo</span>';
                } else {
                    newStatus = '<span class="status-badge status-in-stock">En stock</span>';
                }
                statusCell.innerHTML = newStatus;
            }
        }
    </script>
</body>
</html> 