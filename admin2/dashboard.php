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

// Conexión a la base de datos usando la función definida en database.php
try {
    $pdo = getConnection();

    // Contar productos
    $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

    // Obtener estadísticas de pedidos
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
            SUM(CASE WHEN status = 'completed' THEN 
                -- Usar directamente el total_amount para todos los pedidos completados
                -- ya que este campo contiene el monto final después de descuentos
                total_amount
            END) as total_sales
        FROM orders
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener el último pedido
    $stmt = $pdo->query("
        SELECT 
            o.order_id,
            o.customer_name,
            o.customer_email,
            o.status,
            o.created_at,
            o.total_amount,
            COUNT(oi.order_item_id) as total_items
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        GROUP BY o.order_id, o.total_amount
        ORDER BY o.created_at DESC
        LIMIT 1
    ");
    $lastOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    // Total de pedidos
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    
    // Pedidos recientes
    $recentOrdersStmt = $pdo->prepare("
        SELECT 
            o.order_id, 
            o.customer_name, 
            o.status, 
            o.created_at,
            o.total_amount as total
        FROM orders o
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recentOrdersStmt->execute();
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total de ingresos
    $totalRevenue = $pdo->query("
        SELECT SUM(total_amount) 
        FROM orders
    ")->fetchColumn() ?? 0;
    
    // Gift cards vendidas
    $giftcardsSold = $pdo->query("
        SELECT COUNT(*) 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE p.name LIKE '%Tarjeta de Regalo%' OR oi.personalization_name LIKE '%@%'
    ")->fetchColumn() ?? 0;
    
    // Gift cards enviadas
    $giftcardsSent = $pdo->query("
        SELECT COUNT(*) 
        FROM order_items 
        WHERE giftcard_sent = 1
    ")->fetchColumn() ?? 0;
    
    // Gift cards pendientes
    $giftcardsPending = $giftcardsSold - $giftcardsSent;
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
    $lastOrder = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Jersix</title>
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
        
        .stat-card:nth-child(2) .stat-card-icon {
            background: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        .stat-card:nth-child(3) .stat-card-icon {
            background: rgba(255,193,7,0.1);
            color: var(--warning-color);
        }
        
        /* Quick actions panel */
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
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .action-card {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid #eaedf3;
            text-decoration: none;
            color: #333;
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow);
            border-color: var(--primary-color);
            color: #333;
        }
        
        .action-icon {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .action-title {
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
        }
        
        .action-description {
            font-size: 12px;
            color: var(--secondary-color);
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
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
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

        .last-order {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }

        .order-id {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            color: white;
            font-size: 13px;
            font-weight: 500;
        }

        .order-details {
            padding: 20px;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-group {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
        }

        .info-group i {
            width: 16px;
            color: #888;
        }

        .info-group span {
            font-size: 14px;
        }

        .view-orders-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background: #f8f9fa;
            color: #007bff;
            text-decoration: none;
            border-top: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .view-orders-btn:hover {
            background: #007bff;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 40px;
            color: #ddd;
            margin-bottom: 10px;
        }

        .empty-state p {
            margin: 0;
            font-size: 15px;
        }

        /* Notifications */
        .notification-badge {
            position: relative;
            display: inline-block;
            margin-right: 15px;
            font-size: 20px;
            color: var(--secondary-color);
        }
        
        .notification-badge.warning {
            color: var(--warning-color);
        }
        
        .notification-badge .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-notification {
            margin-bottom: 20px;
            border-left: 4px solid var(--warning-color);
        }
        
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .notification-item i {
            font-size: 20px;
            color: var(--warning-color);
            margin-top: 3px;
        }
        
        .notification-item h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        
        .notification-item p {
            margin: 0 0 10px 0;
            color: var(--secondary-color);
        }
        
        .notification-item .btn {
            display: inline-block;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .notification-item .btn-warning {
            background-color: var(--warning-color);
            color: #333;
        }
        
        .notifications {
            display: flex;
            align-items: center;
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
                <li class="nav-item active">
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
                    <h2>Panel de Administración</h2>
                </div>
                <div class="user-menu">
                    <div class="notifications">
                        <?php /* Eliminar notificación de giftcards */ ?>
                    </div>
                    <div class="user-info">
                        <img src="../img/ICON.png" alt="User" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                        <span><?php echo $_SESSION['admin_username'] ?? 'Administrador'; ?></span>
                    </div>
                </div>
            </div>
            
            <?php /* Eliminar alerta de giftcards pendientes */ ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Productos</h3>
                        <div class="stat-card-icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($productCount); ?></div>
                    <div class="stat-card-label">Total en catálogo</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Pedidos</h3>
                        <div class="stat-card-icon">
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
                        <div class="stat-card-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-card-value">$<?php echo number_format($stats['total_sales'] ?? 0, 2); ?></div>
                    <div class="stat-card-label">De pedidos completados</div>
                </div>

                <?php if ($giftcardsPending > 0): ?>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Gift Cards Pendientes</h3>
                        <div class="stat-card-icon" style="background: rgba(106, 90, 205, 0.15);">
                            <i class="fas fa-gift" style="color: #6a5acd;"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($giftcardsPending); ?></div>
                    <a href="giftcards.php" style="display: inline-block; text-align: center; margin-top: 0.5rem; background-color: #6a5acd; color: white; border: none; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 500;">Enviar ahora</a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php /* Eliminar panel dedicado a gift cards pendientes */ ?>
            
            <!-- Quick Actions Panel -->
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Acciones Rápidas</h3>
                </div>
                <div class="panel-body">
                    <div class="quick-actions">
                        <a href="products.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <h4 class="action-title">Ver Productos</h4>
                            <p class="action-description">Gestiona tu inventario</p>
                        </a>
                        
                        <a href="orders.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h4 class="action-title">Ver Pedidos</h4>
                            <p class="action-description">Gestiona los pedidos de clientes</p>
                        </a>
                        
                        <a href="statistics.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="action-title">Estadísticas</h4>
                            <p class="action-description">Analiza tus ventas</p>
                        </a>
                        
                        <a href="newsletter.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 class="action-title">Clientes</h4>
                            <p class="action-description">Gestiona tus clientes</p>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Actividad Reciente</h3>
                </div>
                <div class="panel-body">
                    <?php if ($lastOrder): ?>
                        <div class="last-order">
                            <div class="order-header">
                                <div class="order-id">
                                    Pedido #<?php echo $lastOrder['order_id']; ?>
                                </div>
                                <?php
                                $status_colors = [
                                    'pending' => '#ffc107',
                                    'processing' => '#007bff',
                                    'completed' => '#28a745',
                                    'cancelled' => '#dc3545'
                                ];
                                $status_text = [
                                    'pending' => 'Pendiente',
                                    'processing' => 'Procesando',
                                    'completed' => 'Completado',
                                    'cancelled' => 'Cancelado'
                                ];
                                $status_color = $status_colors[$lastOrder['status']] ?? '#6c757d';
                                ?>
                                <div class="order-status" style="background-color: <?php echo $status_color; ?>">
                                    <?php echo $status_text[$lastOrder['status']] ?? $lastOrder['status']; ?>
                                </div>
                            </div>
                            <div class="order-details">
                                <div class="order-info">
                                    <div class="info-group">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($lastOrder['customer_name']); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($lastOrder['customer_email']); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <i class="fas fa-box"></i>
                                        <span><?php echo $lastOrder['total_items']; ?> artículos</span>
                                    </div>
                                    <div class="info-group">
                                        <i class="fas fa-dollar-sign"></i>
                                        <span>$<?php echo number_format($lastOrder['total_amount'], 2); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php 
                                            $date = new DateTime($lastOrder['created_at']);
                                            echo $date->format('d/m/Y H:i'); 
                                        ?></span>
                                    </div>
                                </div>
                            </div>
                            <a href="orders.php" class="view-orders-btn">
                                Ver todos los pedidos <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No hay pedidos registrados aún.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Script básico para inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // La barra lateral siempre estará visible
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.add('active');
        });
    </script>
</body>
</html> 
</html> 