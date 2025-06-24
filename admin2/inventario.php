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
    
    // Procesar actualización de inventario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id']) && isset($_POST['size']) && isset($_POST['stock'])) {
        $product_id = $_POST['product_id'];
        $size = $_POST['size'];
        $stock = (int)$_POST['stock'];
        
        // Verificar si ya existe el registro
        $stmt = $pdo->prepare("SELECT id FROM product_inventory WHERE product_id = ? AND size = ?");
        $stmt->execute([$product_id, $size]);
        
        if ($stmt->fetch()) {
            // Actualizar existente
            $stmt = $pdo->prepare("UPDATE product_inventory SET stock = ?, updated_at = NOW() WHERE product_id = ? AND size = ?");
            $stmt->execute([$stock, $product_id, $size]);
        } else {
            // Insertar nuevo
            $stmt = $pdo->prepare("INSERT INTO product_inventory (product_id, size, stock) VALUES (?, ?, ?)");
            $stmt->execute([$product_id, $size, $stock]);
        }
        
        // Actualizar el stock total en la tabla products
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock = (
                SELECT COALESCE(SUM(stock), 0) 
                FROM product_inventory 
                WHERE product_id = ?
            )
            WHERE product_id = ?
        ");
        $stmt->execute([$product_id, $product_id]);
        
        $success_message = "Inventario actualizado correctamente";
    }
    
    // Obtener productos con su inventario
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
    
    $where_conditions = ["p.category != 'Gift Card'"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(p.name LIKE ? OR p.category LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($category_filter)) {
        $where_conditions[] = "p.category = ?";
        $params[] = $category_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            p.product_id,
            p.name,
            p.category,
            p.price,
            p.stock as total_stock,
            p.status,
            GROUP_CONCAT(CONCAT(pi.size, ':', pi.stock) ORDER BY pi.size SEPARATOR ',') as inventory_data
        FROM products p
        LEFT JOIN product_inventory pi ON p.product_id = pi.product_id
        WHERE $where_clause
        GROUP BY p.product_id
        ORDER BY p.name
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener categorías únicas para el filtro
    $categories_stmt = $pdo->prepare("
        SELECT DISTINCT category 
        FROM products 
        WHERE category != 'Gift Card' 
        ORDER BY category
    ");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtener estadísticas de inventario
    $total_products = count($products);
    $low_stock_products = 0;
    $out_of_stock_products = 0;
    $total_items = 0;
    
    foreach ($products as $product) {
        $total_items += $product['total_stock'];
        if ($product['total_stock'] == 0) {
            $out_of_stock_products++;
        } elseif ($product['total_stock'] <= 5) {
            $low_stock_products++;
        }
    }
    
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Función para procesar datos de inventario
function parseInventoryData($inventory_data) {
    if (!$inventory_data) return [];
    
    $inventory = [];
    $items = explode(',', $inventory_data);
    
    foreach ($items as $item) {
        $parts = explode(':', $item);
        if (count($parts) == 2) {
            $inventory[$parts[0]] = (int)$parts[1];
        }
    }
    
    return $inventory;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario - Jersix</title>
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
        
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-card-title {
            font-size: 14px;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .stat-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(0,123,255,0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .stat-card-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .stat-card-label {
            font-size: 13px;
            color: var(--secondary-color);
        }
        
        .stat-card:nth-child(2) .stat-card-icon {
            background: rgba(255,193,7,0.1);
            color: var(--warning-color);
        }
        
        .stat-card:nth-child(3) .stat-card-icon {
            background: rgba(220,53,69,0.1);
            color: var(--danger-color);
        }
        
        .stat-card:nth-child(4) .stat-card-icon {
            background: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        /* Inventory table */
        .inventory-table {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eaedf3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .inventory-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .inventory-table th,
        .inventory-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eaedf3;
        }
        
        .inventory-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 13px;
        }
        
        .inventory-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .product-name {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .product-category {
            font-size: 12px;
            color: var(--secondary-color);
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .size-input {
            width: 60px;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            font-size: 13px;
        }
        
        .size-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .stock-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .stock-high {
            background: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        .stock-medium {
            background: rgba(255,193,7,0.1);
            color: var(--warning-color);
        }
        
        .stock-low {
            background: rgba(220,53,69,0.1);
            color: var(--danger-color);
        }
        
        .stock-empty {
            background: rgba(108,117,125,0.1);
            color: var(--secondary-color);
        }
        
        .update-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
        }
        
        .update-btn:hover {
            background: var(--primary-dark);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        .status-inactive {
            background: rgba(108,117,125,0.1);
            color: var(--secondary-color);
        }
        
        /* Alert messages */
        .alert {
            padding: 12px 16px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: rgba(40,167,69,0.1);
            color: var(--success-color);
            border: 1px solid rgba(40,167,69,0.2);
        }
        
        /* Search and filters */
        .search-filters {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .search-filters h3 {
            margin-bottom: 15px;
            color: var(--dark-color);
            font-size: 16px;
            font-weight: 600;
        }
        
        .filters-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }
        
        .filter-group label {
            font-size: 13px;
            color: var(--secondary-color);
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .search-input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .category-select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .category-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .search-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .clear-btn {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .clear-btn:hover {
            background: #5a6268;
        }
        
        .search-results {
            margin-top: 15px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 13px;
            color: var(--secondary-color);
        }
        
        .search-results strong {
            color: var(--dark-color);
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .loading-indicator {
            display: none;
            text-align: center;
            padding: 20px;
            color: var(--secondary-color);
        }
        
        .loading-indicator.show {
            display: block;
        }
        
        .loading-indicator i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .main-content {
                grid-column: 1;
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: auto;
            }
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
                        <span>Fotos y Lo mas vendido</span>
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
                    <h2>Gestión de Inventario</h2>
                    <p style="color: var(--secondary-color); margin-top: 5px;">Administra el stock por tallas de tus productos</p>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Total Productos</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($total_products); ?></div>
                    <div class="stat-card-label">En catálogo</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Stock Bajo</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($low_stock_products); ?></div>
                    <div class="stat-card-label">≤ 5 unidades</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Sin Stock</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($out_of_stock_products); ?></div>
                    <div class="stat-card-label">Agotados</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Total Unidades</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-warehouse"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($total_items); ?></div>
                    <div class="stat-card-label">En inventario</div>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="search-filters">
                <h3><i class="fas fa-search"></i> Buscar y Filtrar Productos</h3>
                <form method="GET" action="inventario.php">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">Buscar por nombre o categoría:</label>
                            <input type="text" id="search" name="search" class="search-input" 
                                   placeholder="Ej: Barcelona, Equipos, Selecciones..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="category">Filtrar por categoría:</label>
                            <select id="category" name="category" class="category-select">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" 
                                            <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <a href="inventario.php" class="clear-btn" style="text-decoration: none; display: flex; align-items: center;">
                                    <i class="fas fa-times"></i> Limpiar
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($search) || !empty($category_filter)): ?>
                    <div class="search-results">
                        <i class="fas fa-info-circle"></i> 
                        Mostrando <strong><?php echo count($products); ?></strong> productos
                        <?php if (!empty($search)): ?>
                            que coinciden con "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php endif; ?>
                        <?php if (!empty($category_filter)): ?>
                            en la categoría "<strong><?php echo htmlspecialchars($category_filter); ?></strong>"
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Inventory Table -->
            <div class="inventory-table">
                <div class="table-header">
                    <h3 class="table-title">Inventario por Producto</h3>
                </div>
                <div class="loading-indicator" id="loadingIndicator">
                    <i class="fas fa-spinner"></i> Buscando productos...
                </div>
                <div class="table-container" id="tableContainer">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Stock Total</th>
                                <th>Talla S</th>
                                <th>Talla M</th>
                                <th>Talla L</th>
                                <th>Talla XL</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px 20px; color: var(--secondary-color);">
                                        <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                                        <h3 style="margin-bottom: 10px; color: var(--dark-color);">No se encontraron productos</h3>
                                        <p>
                                            <?php if (!empty($search) || !empty($category_filter)): ?>
                                                No hay productos que coincidan con tu búsqueda.
                                                <br>
                                                <a href="inventario.php" style="color: var(--primary-color); text-decoration: none; margin-top: 10px; display: inline-block;">
                                                    <i class="fas fa-arrow-left"></i> Ver todos los productos
                                                </a>
                                            <?php else: ?>
                                                No hay productos disponibles en el inventario.
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <?php 
                                    $inventory = parseInventoryData($product['inventory_data']);
                                    $total_stock = $product['total_stock'];
                                    
                                    // Determinar clase del badge de stock
                                    $stock_class = 'stock-empty';
                                    if ($total_stock > 10) {
                                        $stock_class = 'stock-high';
                                    } elseif ($total_stock > 5) {
                                        $stock_class = 'stock-medium';
                                    } elseif ($total_stock > 0) {
                                        $stock_class = 'stock-low';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                        </td>
                                        <td>
                                            <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                                        </td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $product['status'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $product['status'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="stock-badge <?php echo $stock_class; ?>">
                                                <?php echo number_format($total_stock); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                <input type="hidden" name="size" value="S">
                                                <input type="number" name="stock" value="<?php echo $inventory['S'] ?? 0; ?>" 
                                                       min="0" class="size-input" 
                                                       onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                <input type="hidden" name="size" value="M">
                                                <input type="number" name="stock" value="<?php echo $inventory['M'] ?? 0; ?>" 
                                                       min="0" class="size-input" 
                                                       onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                <input type="hidden" name="size" value="L">
                                                <input type="number" name="stock" value="<?php echo $inventory['L'] ?? 0; ?>" 
                                                       min="0" class="size-input" 
                                                       onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                <input type="hidden" name="size" value="XL">
                                                <input type="number" name="stock" value="<?php echo $inventory['XL'] ?? 0; ?>" 
                                                       min="0" class="size-input" 
                                                       onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td>
                                            <button type="submit" class="update-btn" onclick="updateInventory(<?php echo $product['product_id']; ?>)">
                                                <i class="fas fa-save"></i> Guardar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function updateInventory(productId) {
            // Esta función se puede usar para actualizaciones más complejas en el futuro
            console.log('Actualizando inventario para producto:', productId);
        }
        
        // Auto-submit forms when input changes
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.size-input');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    this.form.submit();
                });
            });
            
            // Auto-search functionality
            const searchInput = document.getElementById('search');
            const categorySelect = document.getElementById('category');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const tableContainer = document.getElementById('tableContainer');
            let searchTimeout;
            
            // Show loading indicator
            function showLoading() {
                loadingIndicator.classList.add('show');
                tableContainer.classList.add('loading');
            }
            
            // Hide loading indicator
            function hideLoading() {
                loadingIndicator.classList.remove('show');
                tableContainer.classList.remove('loading');
            }
            
            // Auto-search when typing (with delay)
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                showLoading();
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500); // Wait 500ms after user stops typing
            });
            
            // Auto-search when category changes
            categorySelect.addEventListener('change', function() {
                showLoading();
                this.form.submit();
            });
            
            // Manual search button
            const searchForm = searchInput.form;
            searchForm.addEventListener('submit', function() {
                showLoading();
            });
            
            // Highlight search terms in results
            const searchTerm = '<?php echo htmlspecialchars($search); ?>';
            if (searchTerm) {
                const productNames = document.querySelectorAll('.product-name');
                productNames.forEach(element => {
                    const text = element.innerHTML;
                    const highlightedText = text.replace(
                        new RegExp(searchTerm, 'gi'),
                        match => `<mark style="background-color: #fff3cd; padding: 1px 3px; border-radius: 2px;">${match}</mark>`
                    );
                    element.innerHTML = highlightedText;
                });
            }
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + F to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                }
                
                // Escape to clear search
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    categorySelect.value = '';
                    window.location.href = 'inventario.php';
                }
            });
        });
    </script>
</body>
</html>
