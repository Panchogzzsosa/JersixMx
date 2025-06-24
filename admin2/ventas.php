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
    
    // Procesar registro de nueva venta
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_sale'])) {
        $product_id = $_POST['product_id'];
        $size = $_POST['size'];
        $price = (float)$_POST['price'];
        $location = $_POST['location'];
        $sale_type = $_POST['sale_type'];
        $notes = $_POST['notes'] ?? '';
        $sale_date = $_POST['sale_date'] ?? date('Y-m-d');
        // Si solo se recibe la fecha, agregar hora 00:00:00
        if (strlen($sale_date) === 10) {
            $sale_date .= ' 00:00:00';
        }
        
        // Insertar la venta
        $stmt = $pdo->prepare("
            INSERT INTO sales (product_id, size, price, location, sale_type, notes, sale_date, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$product_id, $size, $price, $location, $sale_type, $notes, $sale_date]);
        
        // Actualizar el inventario (reducir stock)
        $stmt = $pdo->prepare("
            UPDATE product_inventory 
            SET stock = GREATEST(stock - 1, 0), updated_at = NOW() 
            WHERE product_id = ? AND size = ?
        ");
        $stmt->execute([$product_id, $size]);
        
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
        
        $success_message = "Venta registrada correctamente";
    }
    
    // Procesar eliminación de venta
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sale'])) {
        $sale_id = (int)$_POST['sale_id'];
        
        try {
            // Obtener información de la venta antes de eliminarla
            $stmt = $pdo->prepare("
                SELECT product_id, size 
                FROM sales 
                WHERE sale_id = ?
            ");
            $stmt->execute([$sale_id]);
            $sale_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sale_info) {
                // Eliminar la venta
                $stmt = $pdo->prepare("DELETE FROM sales WHERE sale_id = ?");
                $stmt->execute([$sale_id]);
                
                // Restaurar el inventario (aumentar stock)
                $stmt = $pdo->prepare("
                    UPDATE product_inventory 
                    SET stock = stock + 1, updated_at = NOW() 
                    WHERE product_id = ? AND size = ?
                ");
                $stmt->execute([$sale_info['product_id'], $sale_info['size']]);
                
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
                $stmt->execute([$sale_info['product_id'], $sale_info['product_id']]);
                
                $success_message = "Venta eliminada correctamente y stock restaurado";
            } else {
                $error_message = "Venta no encontrada";
            }
        } catch (Exception $e) {
            $error_message = "Error al eliminar la venta: " . $e->getMessage();
        }
    }
    
    // Obtener productos para el formulario
    $products_stmt = $pdo->prepare("
        SELECT product_id, name, category, price 
        FROM products 
        WHERE category != 'Gift Card' 
        ORDER BY name
    ");
    $products_stmt->execute();
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener ventas registradas
    $sales_stmt = $pdo->prepare("
        SELECT 
            s.sale_id,
            s.size,
            s.price,
            s.location,
            s.sale_type,
            s.notes,
            s.sale_date,
            s.created_at,
            p.name as product_name,
            p.category,
            p.image_url
        FROM sales s
        JOIN products p ON s.product_id = p.product_id
        ORDER BY s.sale_date DESC
        LIMIT 100
    ");
    $sales_stmt->execute();
    $sales = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas de ventas
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            SUM(price) as total_revenue,
            AVG(price) as avg_price,
            COUNT(CASE WHEN sale_type = 'online' THEN 1 END) as online_sales,
            COUNT(CASE WHEN sale_type = 'offline' THEN 1 END) as offline_sales
        FROM sales
        WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Ventas - Jersix</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            background: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        .stat-card:nth-child(3) .stat-card-icon {
            background: rgba(255,193,7,0.1);
            color: var(--warning-color);
        }
        
        .stat-card:nth-child(4) .stat-card-icon {
            background: rgba(220,53,69,0.1);
            color: var(--danger-color);
        }
        
        /* Form and table containers */
        .form-container, .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .form-header, .table-header {
            padding: 20px;
            border-bottom: 1px solid #eaedf3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .form-title, .table-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .form-body {
            padding: 20px;
        }
        
        /* Form styles */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-size: 13px;
            color: var(--secondary-color);
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
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
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
            font-size: 12px;
            padding: 6px 12px;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        /* Table styles */
        .sales-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .sales-table th,
        .sales-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eaedf3;
        }
        
        .sales-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 13px;
        }
        
        .sales-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .sale-type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .sale-type-online {
            background: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        .sale-type-offline {
            background: rgba(255,193,7,0.1);
            color: var(--warning-color);
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
        
        .alert-danger {
            background: rgba(220,53,69,0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220,53,69,0.2);
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .select2-container .select2-selection--single {
            height: 40px;
            padding: 5px 10px;
            font-size: 14px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #333;
            line-height: 28px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px;
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
                <li class="nav-item active">
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
                    <h2>Registro de Ventas</h2>
                    <p style="color: var(--secondary-color); margin-top: 5px;">Registra ventas online y offline</p>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Ventas (30 días)</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['total_sales'] ?? 0); ?></div>
                    <div class="stat-card-label">Total de ventas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Ingresos (30 días)</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-card-value">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                    <div class="stat-card-label">Total de ingresos</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Precio Promedio</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                    <div class="stat-card-value">$<?php echo number_format($stats['avg_price'] ?? 0, 2); ?></div>
                    <div class="stat-card-label">Por venta</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Ventas Online</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['online_sales'] ?? 0); ?></div>
                    <div class="stat-card-label">vs <?php echo number_format($stats['offline_sales'] ?? 0); ?> offline</div>
                </div>
            </div>
            
            <!-- Register Sale Form -->
            <div class="form-container">
                <div class="form-header">
                    <h3 class="form-title"><i class="fas fa-plus-circle"></i> Registrar Nueva Venta</h3>
                </div>
                <div class="form-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="product_id">Producto *</label>
                                <select id="product_id" name="product_id" class="form-control" required>
                                    <option value="">Seleccionar producto</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['product_id']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?> (<?php echo htmlspecialchars($product['category']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="size">Talla *</label>
                                <select id="size" name="size" class="form-control" required>
                                    <option value="">Seleccionar talla</option>
                                    <option value="S">S</option>
                                    <option value="M">M</option>
                                    <option value="L">L</option>
                                    <option value="XL">XL</option>
                                    <option value="XXL">XXL</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="price">Precio de Venta *</label>
                                <input type="number" id="price" name="price" class="form-control" 
                                       step="0.01" min="0" required placeholder="0.00">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="location">Ubicación/Lugar *</label>
                                <input type="text" id="location" name="location" class="form-control" 
                                       required placeholder="Ej: Tienda física, Instagram, Facebook, etc.">
                            </div>
                            
                            <div class="form-group">
                                <label for="sale_type">Tipo de Venta *</label>
                                <select id="sale_type" name="sale_type" class="form-control" required>
                                    <option value="">Seleccionar tipo</option>
                                    <option value="online">Online (Página web)</option>
                                    <option value="offline">Offline (Fuera de página)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="sale_date">Fecha de Venta</label>
                                <input type="date" id="sale_date" name="sale_date" 
                                       class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="notes">Notas Adicionales</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3" 
                                          placeholder="Información adicional sobre la venta (opcional)"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <input type="hidden" name="register_sale" value="1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Registrar Venta
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sales History Table -->
            <div class="table-container">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <h3 class="table-title"><i class="fas fa-history"></i> Historial de Ventas</h3>
                    <div style="display: flex; gap: 10px;">
                        <a href="exportar_ventas_pdf.php" target="_blank" class="btn btn-danger" style="padding: 10px 14px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-pdf" style="font-size: 20px;"></i>
                        </a>
                        <a href="exportar_ventas_excel.php" class="btn btn-success" style="background-color: #28a745; border-color: #28a745; color: #fff; padding: 10px 14px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-excel" style="font-size: 20px; color: #fff;"></i>
                        </a>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Talla</th>
                                <th>Precio</th>
                                <th>Ubicación</th>
                                <th>Tipo</th>
                                <th>Notas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px 20px; color: var(--secondary-color);">
                                        <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                                        <h3 style="margin-bottom: 10px; color: var(--dark-color);">No hay ventas registradas</h3>
                                        <p>Registra tu primera venta usando el formulario de arriba.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?></div>
                                            <div style="font-size: 12px; color: var(--secondary-color);"><?php echo date('H:i', strtotime($sale['sale_date'])); ?></div>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <?php
                                                $img_url = !empty($sale['image_url']) ? '../' . htmlspecialchars($sale['image_url']) : '../img/default-product.jpg';
                                                ?>
                                                <img src="<?php echo $img_url; ?>" alt="<?php echo htmlspecialchars($sale['product_name']); ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #eee; background: #fafafa;">
                                                <span class="product-name"><?php echo htmlspecialchars($sale['product_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="product-category"><?php echo htmlspecialchars($sale['category']); ?></span>
                                        </td>
                                        <td>
                                            <span style="font-weight: 500; color: var(--dark-color);"><?php echo htmlspecialchars($sale['size']); ?></span>
                                        </td>
                                        <td>
                                            <span style="font-weight: 600; color: var(--success-color);">$<?php echo number_format($sale['price'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span style="font-size: 13px;"><?php echo htmlspecialchars($sale['location']); ?></span>
                                        </td>
                                        <td>
                                            <span class="sale-type-badge sale-type-<?php echo $sale['sale_type']; ?>">
                                                <?php echo $sale['sale_type'] === 'online' ? 'Online' : 'Offline'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($sale['notes'])): ?>
                                                <span style="font-size: 12px; color: var(--secondary-color);" title="<?php echo htmlspecialchars($sale['notes']); ?>">
                                                    <?php echo htmlspecialchars(substr($sale['notes'], 0, 30)) . (strlen($sale['notes']) > 30 ? '...' : ''); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--secondary-color); font-style: italic;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="delete_sale" value="1">
                                                <input type="hidden" name="sale_id" value="<?php echo $sale['sale_id']; ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar esta venta?');">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                </button>
                                            </form>
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
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Auto-fill price when product is selected
        document.getElementById('product_id').addEventListener('change', function() {
            const productId = this.value;
            const priceInput = document.getElementById('price');
            
            if (productId) {
                // Get the selected option
                const selectedOption = this.options[this.selectedIndex];
                const productText = selectedOption.text;
                
                // Extract price from product text (assuming it's in the format "Product Name (Category) - $Price")
                const priceMatch = productText.match(/\$(\d+(?:\.\d{2})?)/);
                if (priceMatch) {
                    priceInput.value = priceMatch[1];
                }
            }
        });
        
        // Set current date and time as default
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.getElementById('sale_date').value = localDateTime;
        });
        
        // Inicializar Select2 en el select de productos
        $(document).ready(function() {
            $('#product_id').select2({
                width: '100%',
                placeholder: 'Seleccionar producto',
                language: {
                    noResults: function() {
                        return "No se encontraron productos";
                    },
                    searching: function() {
                        return "Buscando...";
                    }
                }
            });
        });
    </script>
</body>
</html> 