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

// Conexión a la base de datos
try {
    $pdo = getConnection();

    // Obtener correos de newsletter
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM newsletter");
    $total_newsletter = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener correos únicos de orders
    $stmt = $pdo->query("SELECT COUNT(DISTINCT customer_email) as total FROM orders");
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener todos los correos únicos combinados
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT email) as total_unique 
        FROM (
            SELECT email FROM newsletter 
            UNION 
            SELECT customer_email as email FROM orders
        ) as combined_emails
    ");
    $total_unique_emails = $stmt->fetch(PDO::FETCH_ASSOC)['total_unique'];
    
    // Obtener los suscriptores del newsletter
    $stmt = $pdo->query("
        SELECT 
            id,
            email,
            subscribed_at
        FROM newsletter
        ORDER BY subscribed_at DESC
    ");
    $newsletter_subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener todos los correos únicos de diferentes tablas
    // Obtener correos de newsletter
    $subscriber_emails = $pdo->query("SELECT email FROM newsletter")->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtener correos únicos de orders con nombre del cliente
    $stmt = $pdo->query("
        SELECT DISTINCT 
            customer_email as email,
            customer_name,
            MAX(created_at) as last_order,
            COUNT(*) as total_orders
        FROM orders 
        GROUP BY customer_email, customer_name
        ORDER BY MAX(created_at) DESC
    ");
    $order_emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Combinar y eliminar duplicados manteniendo la información del cliente
    $all_emails = array_merge(
        array_map(function($email) {
            return [
                'email' => $email,
                'type' => 'subscriber',
                'name' => null,
                'last_order' => null,
                'total_orders' => 0
            ];
        }, $subscriber_emails),
        array_map(function($order) {
            return [
                'email' => $order['email'],
                'type' => 'customer',
                'name' => $order['customer_name'],
                'last_order' => $order['last_order'],
                'total_orders' => $order['total_orders']
            ];
        }, $order_emails)
    );

    // Eliminar duplicados manteniendo la información más completa
    $unique_emails = [];
    foreach ($all_emails as $email_data) {
        $email = $email_data['email'];
        if (!isset($unique_emails[$email]) || 
            ($email_data['type'] === 'customer' && $unique_emails[$email]['type'] === 'subscriber')) {
            $unique_emails[$email] = $email_data;
        }
    }
    $all_emails = array_values($unique_emails);
    
} catch(PDOException $e) {
    $total_newsletter = 0;
    $total_orders = 0;
    $total_unique_emails = 0;
    $newsletter_subscribers = [];
    $all_emails = [];
    $error_message = "Error al obtener datos: " . $e->getMessage();
}

// Obtener clientes
try {
    // Obtener todos los clientes que han realizado pedidos
    $stmt = $pdo->query("
        SELECT 
            o.customer_email,
            o.customer_name,
            o.phone,
            MAX(o.created_at) as last_order,
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(oi.quantity * oi.price) as total_spent
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        GROUP BY o.customer_email, o.customer_name, o.phone
        ORDER BY MAX(o.created_at) DESC
    ");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Imprimir la consulta y los resultados
    error_log("Consulta de clientes ejecutada");
    error_log("Número de clientes encontrados: " . count($customers));
    
} catch(PDOException $e) {
    $customers = [];
    $error_message = "Error al obtener clientes: " . $e->getMessage();
    error_log($error_message);
}

// Debug: Verificar la conexión y las tablas
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    error_log("Tablas en la base de datos: " . implode(", ", $tables));
    
    $orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    error_log("Número total de órdenes: " . $orderCount);
} catch(PDOException $e) {
    error_log("Error al verificar la base de datos: " . $e->getMessage());
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
            $stmt = $pdo->prepare("INSERT INTO newsletter (email, status) VALUES (?, 1)");
            $stmt->execute([$email]);
            $success_message = "Suscriptor agregado correctamente";
            
            // Actualizar la lista de suscriptores
            $stmt = $pdo->query("SELECT * FROM newsletter ORDER BY email ASC");
            $newsletter_subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            
            .panel-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .tabs {
                width: 100%;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 5px;
            }
            
            .tab {
                padding: 10px 15px;
            }
            
            .table-container {
                margin: 0 -20px;
                width: calc(100% + 40px);
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            table {
                min-width: 800px;
            }

            /* Estilos específicos para la tabla de productos en móvil */
            .product-table th,
            .product-table td {
                padding: 12px 8px;
                font-size: 13px;
            }

            .product-table td:first-child {
                position: sticky;
                left: 0;
                background: white;
                z-index: 1;
            }

            .product-table .product-image {
                width: 50px;
                height: 50px;
            }

            .product-table .product-actions {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .product-table .btn {
                width: 100%;
                padding: 6px 10px;
                font-size: 12px;
            }

            .product-table .badge {
                display: inline-block;
                margin: 2px 0;
            }

            /* Mejoras para el formulario de productos */
            .product-form {
                padding: 15px;
            }

            .product-form .form-group {
                margin-bottom: 15px;
            }

            .product-form .form-control {
                font-size: 16px;
            }

            .product-form .btn-group {
                flex-direction: column;
                gap: 10px;
            }

            .product-form .btn {
                width: 100%;
                margin: 0;
            }

            /* Mejoras para la vista de detalles del producto */
            .product-details {
                padding: 15px;
            }

            .product-details .product-header {
                flex-direction: column;
                gap: 15px;
            }

            .product-details .product-image {
                width: 100%;
                max-width: 200px;
                margin: 0 auto;
            }

            .product-details .product-info {
                width: 100%;
            }

            .product-details .product-actions {
                width: 100%;
                justify-content: center;
            }
            
            /* Mejoras para la tabla de clientes en móvil */
            .customer-table {
                display: block;
                width: 100%;
                min-width: auto;
            }
            
            .customer-table thead {
                display: none;
            }
            
            .customer-table tbody {
                display: block;
                width: 100%;
            }
            
            .customer-table tr {
                display: block;
                width: 100%;
                margin-bottom: 15px;
                border: 1px solid #e9ecef;
                border-radius: var(--border-radius);
                background: white;
                box-shadow: var(--box-shadow);
            }
            
            .customer-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 15px;
                border: none;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .customer-table td:last-child {
                border-bottom: none;
            }
            
            .customer-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--secondary-color);
                margin-right: 10px;
            }
            
            .customer-table .badge {
                margin-left: auto;
            }
            
            .customer-table .product-actions {
                width: 100%;
                justify-content: flex-end;
                margin-top: 10px;
            }
            
            /* Mejoras para la tabla de suscriptores en móvil */
            .subscriber-table {
                display: block;
                width: 100%;
                min-width: auto;
            }
            
            .subscriber-table thead {
                display: none;
            }
            
            .subscriber-table tbody {
                display: block;
                width: 100%;
            }
            
            .subscriber-table tr {
                display: block;
                width: 100%;
                margin-bottom: 15px;
                border: 1px solid #e9ecef;
                border-radius: var(--border-radius);
                background: white;
                box-shadow: var(--box-shadow);
            }
            
            .subscriber-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 15px;
                border: none;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .subscriber-table td:last-child {
                border-bottom: none;
            }
            
            .subscriber-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--secondary-color);
                margin-right: 10px;
            }
            
            .subscriber-table .product-actions {
                width: 100%;
                justify-content: flex-end;
                margin-top: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .panel {
                margin: 0 -15px;
                border-radius: 0;
            }
            
            .panel-body {
                padding: 15px;
            }
            
            .form-control {
                font-size: 16px; /* Evita zoom en iOS */
            }
            
            .alert {
                margin: 0 -15px 15px;
                border-radius: 0;
            }

            /* Ajustes adicionales para pantallas muy pequeñas */
            .product-table td {
                padding: 10px 6px;
                font-size: 12px;
            }

            .product-table .btn {
                padding: 4px 8px;
                font-size: 11px;
            }

            .product-form label {
                font-size: 14px;
            }

            .product-details h2 {
                font-size: 18px;
            }

            .product-details .price {
                font-size: 20px;
            }
            
            /* Ajustes adicionales para tablas en pantallas muy pequeñas */
            .customer-table td,
            .subscriber-table td {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .customer-table td::before,
            .subscriber-table td::before {
                font-size: 12px;
            }
            
            .customer-table .btn,
            .subscriber-table .btn {
                padding: 4px 8px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Botón de menú móvil -->
        <button class="mobile-toggle" id="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
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
                        <span>Compras</span>
                    </a>
                </li>
                <li class="nav-item active">
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
                    <h1>Clientes / Newsletter</h1>
                </div>
                <div class="user-info">
                    <img src="../img/ICON.png" alt="User" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                    <span><?php echo $_SESSION['admin_name'] ?? 'Administrador'; ?></span>
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
                    <div class="stat-label">Total de Correos</div>
                    <div class="stat-value"><?php echo $total_unique_emails; ?></div>
                    <div class="stat-label">Base de Datos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Suscriptores</div>
                    <div class="stat-value"><?php echo $total_newsletter; ?></div>
                    <div class="stat-label">Newsletter</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Clientes con Correo</div>
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                    <div class="stat-label">Compradores</div>
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
                        <div style="margin-bottom: 20px;">
                            <input type="text" 
                                   id="customerSearch" 
                                   class="form-control" 
                                   placeholder="Buscar por nombre, correo o teléfono..." 
                                   style="max-width: 300px;">
                        </div>
                        <?php if (empty($customers)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="empty-state-title">No hay clientes registrados</h3>
                                <p class="empty-state-description">
                                    Aún no hay clientes que hayan realizado compras.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="customer-table">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Correo</th>
                                            <th>Teléfono</th>
                                            <th>Último Pedido</th>
                                            <th>Total Pedidos</th>
                                            <th>Total Gastado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="customersTableBody">
                                        <?php foreach ($customers as $customer): ?>
                                            <tr class="customer-row">
                                                <td data-label="Cliente"><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                                <td data-label="Correo"><?php echo htmlspecialchars($customer['customer_email']); ?></td>
                                                <td data-label="Teléfono"><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                                                <td data-label="Último Pedido">
                                                    <?php 
                                                        $last_order_date = new DateTime($customer['last_order']);
                                                        echo $last_order_date->format('d/m/Y');
                                                        
                                                        $days = $last_order_date->diff(new DateTime())->days;
                                                        if ($days < 30): 
                                                    ?>
                                                        <span class="badge badge-success">Reciente</span>
                                                    <?php elseif ($days < 90): ?>
                                                        <span class="badge badge-warning">Hace <?php echo $days; ?> días</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Total Pedidos"><?php echo $customer['total_orders']; ?></td>
                                                <td data-label="Total Gastado">$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                                <td data-label="Acciones">
                                                    <div class="product-actions">
                                                        <a href="javascript:void(0)" onclick="emailSingleCustomer('<?php echo htmlspecialchars($customer['customer_email']); ?>')" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-envelope"></i> Correo
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
                        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                            <input type="text" 
                                   id="subscriberSearch" 
                                   class="form-control" 
                                   placeholder="Buscar por correo..." 
                                   style="max-width: 300px;">
                            <button class="btn btn-primary" id="add-subscriber-btn">
                                <i class="fas fa-plus"></i> Agregar Suscriptor
                            </button>
                        </div>

                        <?php if (empty($newsletter_subscribers)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-envelope-open"></i>
                                </div>
                                <h3 class="empty-state-title">No hay suscriptores</h3>
                                <p class="empty-state-description">
                                    Aún no hay personas suscritas al newsletter.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="subscriber-table">
                                    <thead>
                                        <tr>
                                            <th>Correo</th>
                                            <th>Fecha de Suscripción</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="subscribersTableBody">
                                        <?php foreach ($newsletter_subscribers as $subscriber): ?>
                                            <tr class="subscriber-row">
                                                <td data-label="Correo"><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                                <td data-label="Fecha de Suscripción">
                                                    <?php 
                                                        $sub_date = new DateTime($subscriber['subscribed_at']);
                                                        echo $sub_date->format('d/m/Y H:i');
                                                    ?>
                                                </td>
                                                <td data-label="Acciones">
                                                    <div class="product-actions">
                                                        <a href="javascript:void(0)" onclick="emailSingleSubscriber('<?php echo htmlspecialchars($subscriber['email']); ?>')" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-envelope"></i> Enviar Correo
                                                        </a>
                                                        <button onclick="deleteSubscriber(<?php echo $subscriber['id']; ?>)" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i> Eliminar
                                                        </button>
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
                                        <span>Todos los Suscriptores (<?php echo count($subscriber_emails); ?>)</span>
                                    </label>
                                    
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="select_customers" id="select-customers">
                                        <span>Todos los Clientes (<?php echo count($customers); ?>)</span>
                                    </label>
                                </div>
                                
                                <div style="margin-top: 15px; max-height: 200px; overflow-y: auto; border: 1px solid #ced4da; border-radius: var(--border-radius); padding: 10px;">
                                    <?php if (empty($all_emails)): ?>
                                        <p>No hay destinatarios disponibles</p>
                                    <?php else: ?>
                                        <?php foreach ($all_emails as $email_data): ?>
                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 5px 0;">
                                                <input type="checkbox" name="recipients[]" value="<?php echo htmlspecialchars($email_data['email']); ?>" 
                                                       class="<?php echo $email_data['type']; ?>-checkbox recipient-checkbox">
                                                <span>
                                                    <?php echo htmlspecialchars($email_data['email']); ?>
                                                    <?php if ($email_data['type'] === 'customer'): ?>
                                                        <span style="margin-left: 4px; color: #6b7280;">(<?php echo htmlspecialchars($email_data['name']); ?>)</span>
                                                        <span class="badge badge-success">Cliente</span>
                                                        <?php if ($email_data['total_orders'] > 0): ?>
                                                            <span class="badge badge-primary"><?php echo $email_data['total_orders']; ?> pedido(s)</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge badge-primary">Suscriptor</span>
                                                    <?php endif; ?>
                                                </span>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Código existente para las pestañas
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    
                    const tabId = tab.getAttribute('data-tab');
                    document.getElementById(tabId + '-content').classList.add('active');
                });
            });

            // Agregar el evento de búsqueda para clientes
            const searchInput = document.getElementById('customerSearch');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchText = this.value.toLowerCase();
                    const rows = document.querySelectorAll('.customer-row');
                    
                    rows.forEach(row => {
                        const name = row.children[0].textContent.toLowerCase();
                        const email = row.children[1].textContent.toLowerCase();
                        const phone = row.children[2].textContent.toLowerCase();
                        
                        if (name.includes(searchText) || 
                            email.includes(searchText) || 
                            phone.includes(searchText)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Código existente para el formulario de suscriptor
            const addSubscriberBtn = document.getElementById('add-subscriber-btn');
            const addSubscriberForm = document.getElementById('add-subscriber-form');
            const cancelAddSubscriber = document.getElementById('cancel-add-subscriber');
            
            if (addSubscriberBtn && addSubscriberForm && cancelAddSubscriber) {
                addSubscriberBtn.addEventListener('click', () => {
                    addSubscriberForm.style.display = 'block';
                    addSubscriberBtn.style.display = 'none';
                });
                
                cancelAddSubscriber.addEventListener('click', () => {
                    addSubscriberForm.style.display = 'none';
                    addSubscriberBtn.style.display = 'block';
                });
            }

            // Código existente para selección de destinatarios
            const selectAll = document.getElementById('select-all');
            const selectSubscribers = document.getElementById('select-subscribers');
            const selectCustomers = document.getElementById('select-customers');
            
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    document.querySelectorAll('.recipient-checkbox').forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    
                    if (selectSubscribers) selectSubscribers.checked = this.checked;
                    if (selectCustomers) selectCustomers.checked = this.checked;
                });
            }

            // Manejo del menú móvil
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const sidebar = document.getElementById('sidebar');
            
            if (mobileMenuToggle && sidebar) {
                mobileMenuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('active');
                });

                // Cerrar menú al hacer clic fuera
                document.addEventListener('click', (e) => {
                    if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                        sidebar.classList.remove('active');
                    }
                });
            }
        });

        // Mantener las funciones globales existentes
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
            // Cambiar a la pestaña de envío de correo
            document.querySelector('.tab[data-tab="send-email"]').click();
            
            // Seleccionar solo este correo en la lista de destinatarios
            document.querySelectorAll('.recipient-checkbox').forEach(checkbox => {
                checkbox.checked = checkbox.value === email;
            });
            
            // Enfocar el campo de asunto
            document.getElementById('email_subject').focus();
        }

        function deleteSubscriber(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este suscriptor?')) {
                // Aquí puedes agregar la lógica para eliminar el suscriptor
                alert('Funcionalidad en desarrollo: Eliminar suscriptor ' + id);
            }
        }
    </script>
</body>
</html>