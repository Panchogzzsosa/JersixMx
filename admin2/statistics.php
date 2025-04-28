<?php
session_start();

// Verificar inicio de sesión
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Mostrar errores durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos
try {
    
    $pdo = getConnection();

    // Estadísticas generales
    $stats = [];
    
    // Total de productos
    $stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    
    // Total de pedidos y ventas
    $order_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
            SUM(CASE WHEN status = 'completed' THEN 
                total_amount
            END) as total_sales
        FROM orders
    ")->fetch(PDO::FETCH_ASSOC);
    
    $stats['total_orders'] = $order_stats['total_orders'];
    $stats['completed_orders'] = $order_stats['completed_orders'];
    $stats['total_sales'] = $order_stats['total_sales'] ?? 0;
    
    // Productos más vendidos
    $top_products = $pdo->query("
        SELECT 
            p.product_id,
            p.name,
            p.price,
            p.image_url as product_image,
            SUM(oi.quantity) as total_quantity,
            SUM(
                CASE 
                    WHEN (SELECT SUM(oi2.quantity * oi2.price) FROM order_items oi2 WHERE oi2.order_id = o.order_id) > 0 THEN
                        (oi.quantity * oi.price) * 
                        (o.total_amount / (SELECT SUM(oi2.quantity * oi2.price) FROM order_items oi2 WHERE oi2.order_id = o.order_id))
                    ELSE oi.quantity * oi.price
                END
            ) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.status = 'completed'
        GROUP BY p.product_id, p.image_url
        ORDER BY total_quantity DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Ventas por mes (últimos 6 meses)
    $monthly_sales = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as order_count,
            SUM(total_amount) as monthly_revenue
        FROM orders
        WHERE status = 'completed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - Jersix</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .topbar h2 {
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
        
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        /* Panels */
        .panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
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
        
        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eaedf3;
        }
        
        .table th {
            font-weight: 600;
            color: var(--dark-color);
            background-color: #f8f9fa;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table tr:hover td {
            background-color: #f8f9fa;
        }
        
        /* Charts */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Estilos para la nueva sección de productos más vendidos */
        .top-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
            display: flex;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, #007bff, #00c6ff);
            border-radius: 12px 0 0 12px;
        }
        
        .product-image-container {
            width: 80px;
            height: 80px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            overflow: hidden;
            background-color: #f9f9f9;
            flex-shrink: 0;
        }
        
        .product-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin: 0 0 8px 0;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 13px;
        }
        
        .product-quantity, .product-revenue {
            display: flex;
            flex-direction: column;
        }
        
        .product-quantity .label, .product-revenue .label {
            color: #888;
            margin-bottom: 3px;
            font-size: 11px;
        }
        
        .product-quantity .value {
            font-weight: 600;
            color: #007bff;
            background: #e6f2ff;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
            text-align: center;
            min-width: 28px;
        }
        
        .product-revenue .value {
            font-weight: 600;
            color: #28a745;
        }
        
        .product-progress {
            height: 5px;
            background: #f1f1f1;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(to right, #007bff, #00c6ff);
            border-radius: 3px;
        }
        
        .empty-data {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-data i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .empty-data h4 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .empty-data p {
            color: #868e96;
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
                    <h2>Estadísticas</h2>
                </div>
                <div class="user-info">
                    <img src="../img/ICON.png" alt="User" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                    <span><?php echo $_SESSION['admin_username'] ?? 'Administrador'; ?></span>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Productos</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stat-card-label">Total en catálogo</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Pedidos</h3>
                        <div class="stat-card-icon" style="background: rgba(40,167,69,0.1); color: var(--success-color);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['total_orders']); ?></div>
                    <div class="stat-card-label">
                        <?php echo number_format($stats['completed_orders']); ?> completados
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Ventas Totales</h3>
                        <div class="stat-card-icon" style="background: rgba(255,193,7,0.1); color: var(--warning-color);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-card-value">$<?php echo number_format($stats['total_sales'], 2); ?></div>
                    <div class="stat-card-label">De pedidos completados</div>
                </div>
            </div>
            
            <!-- Gráfica de Ventas Mensuales -->
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Ventas por Mes</h3>
                    <div class="chart-legend" style="display: flex; align-items: center; gap: 15px;">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; background-color: rgba(54, 162, 235, 0.5); border: 1px solid rgba(54, 162, 235, 1);"></div>
                            <span style="font-size: 12px; color: #666;">Ingresos ($)</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; background-color: rgba(255, 99, 132, 0.2); border: 1px solid rgba(255, 99, 132, 1);"></div>
                            <span style="font-size: 12px; color: #666;">Pedidos</span>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <?php if (!empty($monthly_sales)): ?>
                        <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 50px 20px;">
                            <i class="fas fa-chart-bar" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                            <h4 style="color: #666; margin-bottom: 10px;">No hay datos de ventas disponibles</h4>
                            <p style="color: #999;">Los datos de ventas se mostrarán aquí cuando haya pedidos completados.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Productos más vendidos -->
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Productos más Vendidos</h3>
                </div>
                <div class="panel-body">
                    <?php if (!empty($top_products)): ?>
                        <div class="top-products-grid">
                            <?php foreach ($top_products as $product): ?>
                            <div class="product-card">
                                <div class="product-image-container">
                                    <?php if (!empty($product['product_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-tshirt" style="font-size: 1.8rem; color: #ccc;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h4 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="product-meta">
                                        <div class="product-quantity">
                                            <span class="label">Cantidad:</span>
                                            <span class="value"><?php echo number_format($product['total_quantity']); ?></span>
                                        </div>
                                        <div class="product-revenue">
                                            <span class="label">Ingresos:</span>
                                            <span class="value">$<?php echo number_format($product['total_revenue'], 2); ?></span>
                                        </div>
                                    </div>
                                    <div class="product-progress">
                                        <div class="progress-bar" style="width: <?php echo min(100, ($product['total_quantity'] / max(1, array_column($top_products, 'total_quantity'))[0]) * 100); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-data">
                            <i class="fas fa-info-circle"></i>
                            <h4>No hay datos de ventas disponibles</h4>
                            <p>Los datos se mostrarán cuando haya productos vendidos.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Inicializar el gráfico de ventas mensuales
        document.addEventListener('DOMContentLoaded', function() {
            // Datos para el gráfico de ventas
            const monthlyData = <?php echo json_encode($monthly_sales); ?>;
            
            // Preparar datos para Chart.js
            const months = [];
            const revenue = [];
            const orders = [];
            
            // Si hay datos, procesarlos
            if (monthlyData && monthlyData.length > 0) {
                // Revertir el array para mostrar en orden cronológico
                monthlyData.reverse().forEach(item => {
                    // Formatear el mes para mostrar (ej. "2023-04" a "Abr 2023")
                    const date = new Date(item.month + '-01');
                    const formattedMonth = date.toLocaleDateString('es-ES', { month: 'short', year: 'numeric' });
                    
                    months.push(formattedMonth);
                    revenue.push(parseFloat(item.monthly_revenue || 0));
                    orders.push(parseInt(item.order_count || 0));
                });
            } else {
                // Si no hay datos, mostrar algo por defecto
                const currentDate = new Date();
                for (let i = 5; i >= 0; i--) {
                    const date = new Date(currentDate);
                    date.setMonth(date.getMonth() - i);
                    months.push(date.toLocaleDateString('es-ES', { month: 'short', year: 'numeric' }));
                    revenue.push(0);
                    orders.push(0);
                }
            }
            
            // Crear el gráfico
            const ctx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Ingresos ($)',
                            data: revenue,
                            backgroundColor: function(context) {
                                const chart = context.chart;
                                const {ctx, chartArea} = chart;
                                if (!chartArea) {
                                    return 'rgba(54, 162, 235, 0.5)';
                                }
                                const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                gradient.addColorStop(0, 'rgba(54, 162, 235, 0.2)');
                                gradient.addColorStop(1, 'rgba(54, 162, 235, 0.8)');
                                return gradient;
                            },
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            borderRadius: 6,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Pedidos',
                            data: orders,
                            type: 'line',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 3,
                            pointRadius: 6,
                            pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointHoverRadius: 8,
                            pointHoverBackgroundColor: 'rgba(255, 99, 132, 1)',
                            tension: 0.3,
                            fill: false,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.9)',
                            titleColor: '#333',
                            bodyColor: '#666',
                            borderColor: '#ddd',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            bodyFont: {
                                family: "'Inter', sans-serif",
                                size: 14
                            },
                            titleFont: {
                                family: "'Inter', sans-serif",
                                size: 16,
                                weight: 'bold'
                            },
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y;
                                    return label === 'Ingresos ($)' 
                                        ? `${label}: $${value.toLocaleString('es-MX')}`
                                        : `${label}: ${value.toLocaleString('es-MX')}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: "'Inter', sans-serif"
                                }
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-MX');
                                },
                                font: {
                                    family: "'Inter', sans-serif"
                                }
                            },
                            title: {
                                display: true,
                                text: 'Ingresos ($)',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 13
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                                font: {
                                    family: "'Inter', sans-serif"
                                }
                            },
                            title: {
                                display: true,
                                text: 'Número de Pedidos',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 13
                                }
                            }
                        }
                    }
                }
            });
            
            // Asegurarse de que la barra lateral esté visible
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.add('active');
        });
    </script>
</body>
</html> 