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

// Obtener suscriptores del newsletter
try {
    // Verificar si la tabla existe
    $tables = $pdo->query("SHOW TABLES LIKE 'subscribers'")->fetchAll();
    
    if (empty($tables)) {
        // La tabla no existe, hay que crearla
        $pdo->exec("
            CREATE TABLE subscribers (
                subscriber_id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                subscription_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                active TINYINT(1) DEFAULT 1
            )
        ");
        $subscribers = [];
    } else {
        // La tabla existe, obtener los datos
        $stmt = $pdo->query("SELECT * FROM subscribers ORDER BY subscription_date DESC");
        $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $subscribers = [];
    $error_message = "Error al obtener suscriptores: " . $e->getMessage();
}

// Obtener clientes
try {
    // Verificar si la tabla customers existe
    $tables = $pdo->query("SHOW TABLES LIKE 'customers'")->fetchAll();
    
    if (empty($tables)) {
        $customers = [];
    } else {
        // La tabla existe, obtener los datos
        $stmt = $pdo->query("
            SELECT 
                c.customer_id,
                CONCAT(c.first_name, ' ', c.last_name) as name,
                c.email,
                c.phone,
                c.address_line1,
                c.city,
                c.state,
                c.postal_code
            FROM customers c
            ORDER BY c.customer_id DESC
        ");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Intentar obtener información de pedidos si la tabla existe
        $hasPedidos = $pdo->query("SHOW TABLES LIKE 'orders'")->fetchAll();
        
        if (!empty($hasPedidos)) {
            foreach ($customers as &$customer) {
                // Obtener último pedido y total
                $stmt = $pdo->prepare("
                    SELECT 
                        MAX(order_date) as last_order_date,
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_spent
                    FROM orders
                    WHERE customer_id = ?
                ");
                $stmt->execute([$customer['customer_id']]);
                $orderData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $customer['last_order_date'] = $orderData['last_order_date'] ?? null;
                $customer['total_orders'] = $orderData['total_orders'] ?? 0;
                $customer['total_spent'] = $orderData['total_spent'] ?? 0;
            }
        }
    }
} catch(PDOException $e) {
    $customers = [];
    $error_message = "Error al obtener clientes: " . $e->getMessage();
}

// Procesar envío de correo masivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_newsletter'])) {
    $subject = $_POST['email_subject'] ?? '';
    $message = $_POST['email_message'] ?? '';
    $recipients = $_POST['recipients'] ?? [];
    
    $errors = [];
    
    if (empty($subject)) {
        $errors[] = "El asunto del correo es obligatorio";
    }
    if (empty($message)) {
        $errors[] = "El contenido del mensaje es obligatorio";
    }
    if (empty($recipients)) {
        $errors[] = "Debes seleccionar al menos un destinatario";
    }
    
    if (empty($errors)) {
        // Aquí iría la lógica para enviar correos
        // Por ahora, solo simulamos el envío
        $success_message = "Mensaje enviado correctamente a " . count($recipients) . " destinatarios";
    }
}

// Procesar nueva suscripción
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subscriber'])) {
    $email = $_POST['subscriber_email'] ?? '';
    
    $errors = [];
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Correo electrónico inválido";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (?)");
            $stmt->execute([$email]);
            $success_message = "Suscriptor agregado correctamente";
            
            // Actualizar la lista de suscriptores
            $stmt = $pdo->query("SELECT * FROM subscribers ORDER BY subscription_date DESC");
            $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Error de duplicado
                $errors[] = "El correo ya está registrado como suscriptor";
            } else {
                $errors[] = "Error al agregar suscriptor: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes y Newsletter - Panel de Administración - Jersix</title>
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
        
        /* Tabs */
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
        
        /* Table */
        .table-container {
            overflow-x: auto;
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
            border-bottom: 1px solid #e9ecef;
        }
        
        tr:hover {
            background-color: rgba(0,123,255,0.03);
        }
        
        /* Forms */
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
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-hint {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--secondary-color);
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            margin: 10px 0;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: var(--secondary-color);
            font-size: 13px;
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
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        /* Badges */
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
        
        .badge-success {
            background-color: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        .badge-warning {
            background-color: rgba(255,193,7,0.1);
            color: var(--warning-color);
        }
        
        .badge-danger {
            background-color: rgba(220,53,69,0.1);
            color: var(--danger-color);
        }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: rgba(40,167,69,0.1);
            border-left-color: var(--success-color);
            color: var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(220,53,69,0.1);
            border-left-color: var(--danger-color);
            color: var(--danger-color);
        }
        
        /* Empty state */
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
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
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
                <li class="nav-item">
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
                <li class="nav-item active">
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
                <h1>Clientes y Newsletter</h1>
                <div class="user-info">
                    <span>Usuario: Admin</span>
                    <a href="dashboard.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Volver al Dashboard
                    </a>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total de Clientes</div>
                    <div class="stat-value"><?php echo count($customers); ?></div>
                    <div class="stat-label">Registrados</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Suscriptores</div>
                    <div class="stat-value"><?php echo count($subscribers); ?></div>
                    <div class="stat-label">Newsletter</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Clientes Activos</div>
                    <div class="stat-value">
                        <?php 
                            $activeCustomers = 0;
                            foreach ($customers as $customer) {
                                if (!empty($customer['last_order_date']) && strtotime($customer['last_order_date']) > strtotime('-30 days')) {
                                    $activeCustomers++;
                                }
                            }
                            echo $activeCustomers;
                        ?>
                    </div>
                    <div class="stat-label">Últimos 30 días</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Ventas Totales</div>
                    <div class="stat-value">
                        $<?php 
                            $totalSales = 0;
                            foreach ($customers as $customer) {
                                $totalSales += $customer['total_spent'] ?? 0;
                            }
                            echo number_format($totalSales, 2);
                        ?>
                    </div>
                    <div class="stat-label">De todos los clientes</div>
                </div>
            </div>
            
            <div class="panel">
                <div class="panel-header">
                    <div class="tabs">
                        <div class="tab active" data-tab="customers">Clientes</div>
                        <div class="tab" data-tab="subscribers">Suscriptores</div>
                        <div class="tab" data-tab="send-email">Enviar Correo</div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <!-- Tab de Clientes -->
                    <div class="tab-content active" id="customers-content">
                        <?php if (empty($customers)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="empty-state-title">No hay clientes registrados</h3>
                                <p class="empty-state-description">
                                    Aún no hay clientes en la base de datos. Los clientes aparecerán aquí cuando realicen una compra.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Contacto</th>
                                            <th>Dirección</th>
                                            <th>Último Pedido</th>
                                            <th>Pedidos</th>
                                            <th>Total Gastado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($customer['email']); ?></div>
                                                    <div><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($customer['address_line1'])): ?>
                                                        <div><?php echo htmlspecialchars($customer['address_line1']); ?></div>
                                                        <div>
                                                            <?php echo htmlspecialchars(($customer['city'] ?? '') . ', ' . ($customer['state'] ?? '') . ' ' . ($customer['postal_code'] ?? '')); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div>Sin dirección registrada</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($customer['last_order_date'])): ?>
                                                        <div><?php echo date('d/m/Y', strtotime($customer['last_order_date'])); ?></div>
                                                        <?php 
                                                            $days = (time() - strtotime($customer['last_order_date'])) / (60 * 60 * 24);
                                                            if ($days < 30): 
                                                        ?>
                                                            <span class="badge badge-success">Reciente</span>
                                                        <?php elseif ($days < 90): ?>
                                                            <span class="badge badge-warning">Hace <?php echo floor($days); ?> días</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Inactivo</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div>Sin pedidos</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $customer['total_orders'] ?? 0; ?></td>
                                                <td>$<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 5px;">
                                                        <a href="javascript:void(0)" onclick="emailSingleCustomer('<?php echo htmlspecialchars($customer['email']); ?>')" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-envelope"></i> Correo
                                                        </a>
                                                        <a href="javascript:void(0)" class="btn btn-sm btn-secondary">
                                                            <i class="fas fa-eye"></i> Ver
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab de Suscriptores -->
                    <div class="tab-content" id="subscribers-content">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h3 style="margin-bottom: 5px;">Lista de Suscriptores</h3>
                                <p style="color: var(--secondary-color);">Personas suscritas al newsletter</p>
                            </div>
                            <button class="btn btn-primary" id="add-subscriber-btn">
                                <i class="fas fa-plus"></i> Agregar Suscriptor
                            </button>
                        </div>
                        
                        <div id="add-subscriber-form" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin-bottom: 15px;">Agregar Nuevo Suscriptor</h4>
                            <form method="post" style="display: flex; gap: 10px;">
                                <input type="email" name="subscriber_email" placeholder="Correo electrónico" class="form-control" required>
                                <input type="hidden" name="add_subscriber" value="1">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-secondary" id="cancel-add-subscriber">
                                    <i class="fas fa-times"></i> Cancelar
                                </button>
                            </form>
                        </div>
                        
                        <?php if (empty($subscribers)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-envelope-open"></i>
                                </div>
                                <h3 class="empty-state-title">No hay suscriptores</h3>
                                <p class="empty-state-description">
                                    Aún no hay personas suscritas al newsletter. Puedes agregar suscriptores manualmente o integrar un formulario en el sitio web.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Email</th>
                                            <th>Fecha de Suscripción</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subscribers as $subscriber): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($subscriber['subscription_date'])); ?></td>
                                                <td>
                                                    <?php if ($subscriber['active']): ?>
                                                        <span class="badge badge-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 5px;">
                                                        <a href="javascript:void(0)" onclick="emailSingleSubscriber('<?php echo htmlspecialchars($subscriber['email']); ?>')" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-envelope"></i> Correo
                                                        </a>
                                                        <a href="javascript:void(0)" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i> Eliminar
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab de Envío de Correo -->
                    <div class="tab-content" id="send-email-content">
                        <h3 style="margin-bottom: 20px;">Enviar Correo Masivo</h3>
                        
                        <form method="post">
                            <div class="form-group">
                                <label class="form-label">Destinatarios</label>
                                <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="select_all" id="select-all">
                                        <span style="font-weight: 600;">Seleccionar Todos</span>
                                    </label>
                                    
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="select_subscribers" id="select-subscribers">
                                        <span>Todos los Suscriptores (<?php echo count($subscribers); ?>)</span>
                                    </label>
                                    
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="select_customers" id="select-customers">
                                        <span>Todos los Clientes (<?php echo count($customers); ?>)</span>
                                    </label>
                                </div>
                                
                                <div style="margin-top: 15px; max-height: 200px; overflow-y: auto; border: 1px solid #ced4da; border-radius: var(--border-radius); padding: 10px;">
                                    <?php if (empty($customers) && empty($subscribers)): ?>
                                        <p>No hay destinatarios disponibles</p>
                                    <?php else: ?>
                                        <?php foreach ($subscribers as $subscriber): ?>
                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 5px 0;">
                                                <input type="checkbox" name="recipients[]" value="<?php echo htmlspecialchars($subscriber['email']); ?>" class="subscriber-checkbox recipient-checkbox">
                                                <span><?php echo htmlspecialchars($subscriber['email']); ?> <span class="badge badge-primary">Suscriptor</span></span>
                                            </label>
                                        <?php endforeach; ?>
                                        
                                        <?php foreach ($customers as $customer): ?>
                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 5px 0;">
                                                <input type="checkbox" name="recipients[]" value="<?php echo htmlspecialchars($customer['email']); ?>" class="customer-checkbox recipient-checkbox">
                                                <span><?php echo htmlspecialchars($customer['email']); ?> (<?php echo htmlspecialchars($customer['name']); ?>) <span class="badge badge-success">Cliente</span></span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="email_subject">Asunto del Correo</label>
                                <input type="text" name="email_subject" id="email_subject" class="form-control" placeholder="Escribe el asunto del correo" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="email_message">Mensaje</label>
                                <textarea name="email_message" id="email_message" class="form-control" placeholder="Escribe el mensaje del correo" required></textarea>
                                <span class="form-hint">Puedes usar HTML básico para dar formato al mensaje</span>
                            </div>
                            
                            <button type="submit" name="send_newsletter" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Enviar Mensaje
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Función para cambiar de pestañas
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Quitar clase activa de todas las pestañas
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                // Añadir clase activa a la pestaña actual
                tab.classList.add('active');
                
                // Ocultar todos los contenidos de pestañas
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Mostrar el contenido de la pestaña seleccionada
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(tabId + '-content').classList.add('active');
            });
        });
        
        // Mostrar/ocultar formulario de agregar suscriptor
        const addSubscriberBtn = document.getElementById('add-subscriber-btn');
        const addSubscriberForm = document.getElementById('add-subscriber-form');
        const cancelAddSubscriber = document.getElementById('cancel-add-subscriber');
        
        addSubscriberBtn.addEventListener('click', () => {
            addSubscriberForm.style.display = 'block';
            addSubscriberBtn.style.display = 'none';
        });
        
        cancelAddSubscriber.addEventListener('click', () => {
            addSubscriberForm.style.display = 'none';
            addSubscriberBtn.style.display = 'block';
        });
        
        // Lógica para seleccionar destinatarios de correo
        const selectAll = document.getElementById('select-all');
        const selectSubscribers = document.getElementById('select-subscribers');
        const selectCustomers = document.getElementById('select-customers');
        
        // Seleccionar todos
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.recipient-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            
            selectSubscribers.checked = this.checked;
            selectCustomers.checked = this.checked;
        });
        
        // Seleccionar todos los suscriptores
        selectSubscribers.addEventListener('change', function() {
            document.querySelectorAll('.subscriber-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            
            updateSelectAllState();
        });
        
        // Seleccionar todos los clientes
        selectCustomers.addEventListener('change', function() {
            document.querySelectorAll('.customer-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            
            updateSelectAllState();
        });
        
        // Actualizar estado de "Seleccionar todos"
        function updateSelectAllState() {
            const allCheckboxes = document.querySelectorAll('.recipient-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.recipient-checkbox:checked');
            
            selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
        }
        
        // Actualizar cuando se cambia cualquier checkbox individual
        document.querySelectorAll('.recipient-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Comprobar si todas las casillas de suscriptores están marcadas
                const allSubscribers = document.querySelectorAll('.subscriber-checkbox');
                const checkedSubscribers = document.querySelectorAll('.subscriber-checkbox:checked');
                selectSubscribers.checked = allSubscribers.length === checkedSubscribers.length;
                
                // Comprobar si todas las casillas de clientes están marcadas
                const allCustomers = document.querySelectorAll('.customer-checkbox');
                const checkedCustomers = document.querySelectorAll('.customer-checkbox:checked');
                selectCustomers.checked = allCustomers.length === checkedCustomers.length;
                
                // Actualizar estado de "Seleccionar todos"
                updateSelectAllState();
            });
        });
        
        // Funciones para enviar correo a un destinatario específico
        function emailSingleCustomer(email) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelector('.tab[data-tab="send-email"]').classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.getElementById('send-email-content').classList.add('active');
            
            // Desmarcar todos los checkboxes
            document.querySelectorAll('.recipient-checkbox').forEach(checkbox => {
                checkbox.checked = checkbox.value === email;
            });
            
            // Actualizar estados de los selectores
            updateSelectAllState();
            
            // Enfocar en el asunto del correo
            document.getElementById('email_subject').focus();
        }
        
        function emailSingleSubscriber(email) {
            emailSingleCustomer(email); // Reutilizamos la misma función
        }
    </script>
</body>
</html>