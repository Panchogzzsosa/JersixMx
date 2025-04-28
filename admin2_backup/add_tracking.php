<?php
session_start();

// Verificar si el usuario está autenticado como administrador
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos usando la función definida en database.php
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';

// Obtener información del pedido
try {
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            COUNT(DISTINCT oi.order_item_id) as total_items,
            SUM(oi.quantity * oi.price) as order_total,
            GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as products_summary
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE o.order_id = ?
        GROUP BY 
            o.order_id, 
            o.customer_name, 
            o.customer_email, 
            o.status, 
            o.created_at,
            o.phone,
            o.street,
            o.colony,
            o.city,
            o.state,
            o.zip_code,
            o.payment_status,
            o.tracking_number,
            o.carrier_name,
            o.shipping_date
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header('Location: orders.php');
        exit();
    }
} catch(PDOException $e) {
    $error_message = "Error al cargar el pedido: " . $e->getMessage();
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tracking_number = isset($_POST['tracking_number']) ? trim($_POST['tracking_number']) : '';
    $carrier_name = isset($_POST['carrier_name']) ? trim($_POST['carrier_name']) : 'DHL';
    $shipping_date = isset($_POST['shipping_date']) ? trim($_POST['shipping_date']) : null;
    
    // Validar datos
    if (empty($tracking_number)) {
        $error_message = "El número de seguimiento es obligatorio.";
    } else {
        try {
            // Preparar la consulta para actualizar
            $update_stmt = $pdo->prepare("
                UPDATE orders 
                SET tracking_number = ?, 
                    carrier_name = ?, 
                    shipping_date = ?, 
                    status = CASE WHEN status = 'pending' THEN 'shipped' ELSE status END
                WHERE order_id = ?
            ");
            
            // Ejecutar la consulta
            $update_stmt->execute([$tracking_number, $carrier_name, $shipping_date, $order_id]);
            
            // Mensaje de éxito
            $success_message = "Información de seguimiento actualizada correctamente.";
            
            // Recargar la información del pedido
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Enviar correo al cliente con la información de seguimiento
            if (!empty($order['customer_email'])) {
                // Códigos para enviar correo
                $to = $order['customer_email'];
                $subject = 'Tu pedido #' . $order_id . ' ha sido enviado';
                $tracking_url = 'https://' . $_SERVER['HTTP_HOST'] . '/tracking.php?tracking=' . urlencode($tracking_number);
                
                $message = '
                <html>
                <head>
                    <title>Tu pedido ha sido enviado</title>
                    <style>
                        body, html {
                            margin: 0;
                            padding: 0;
                            font-family: Arial, Helvetica, sans-serif;
                            color: #333333;
                            line-height: 1.6;
                        }
                        .email-container {
                            max-width: 600px;
                            margin: 0 auto;
                        }
                        .header {
                            background-color: #000000;
                            padding: 25px;
                            text-align: center;
                        }
                        .header img {
                            max-width: 180px;
                            height: auto;
                        }
                        .content {
                            padding: 30px;
                            background-color: #ffffff;
                            border: 1px solid #eeeeee;
                        }
                        h1 {
                            color: #333333;
                            font-size: 24px;
                            margin-top: 0;
                            margin-bottom: 20px;
                        }
                        p {
                            margin-bottom: 20px;
                            font-size: 16px;
                        }
                        .tracking-info {
                            background-color: #f9f9f9;
                            border-radius: 6px;
                            padding: 20px;
                            margin: 25px 0;
                        }
                        .tracking-label {
                            font-weight: bold;
                            display: block;
                            margin-bottom: 5px;
                            color: #555555;
                        }
                        .tracking-number {
                            font-family: monospace;
                            font-size: 18px;
                            letter-spacing: 1px;
                            background-color: #eeeeee;
                            padding: 10px 15px;
                            border-radius: 4px;
                            display: inline-block;
                            margin: 5px 0 15px;
                        }
                        .btn {
                            display: inline-block;
                            background-color: #cc0000;
                            color: white;
                            text-decoration: none;
                            padding: 12px 25px;
                            border-radius: 4px;
                            font-weight: bold;
                            text-align: center;
                        }
                        .btn:hover {
                            background-color: #aa0000;
                        }
                        .footer {
                            padding: 20px;
                            text-align: center;
                            background-color: #f5f5f5;
                            font-size: 12px;
                            color: #777777;
                        }
                        .order-summary {
                            margin: 25px 0;
                        }
                        .divider {
                            height: 1px;
                            background-color: #eeeeee;
                            margin: 25px 0;
                        }
                    </style>
                </head>
                <body>
                    <div class="email-container">
                        <div class="header">
                            <img src="https://' . $_SERVER['HTTP_HOST'] . '/img/LOGO.png" alt="JERSIX">
                        </div>
                        <div class="content">
                            <h1>¡Tu pedido ha sido enviado!</h1>
                            <p>Hola <strong>' . htmlspecialchars($order['customer_name']) . '</strong>,</p>
                            <p>Nos complace informarte que tu pedido #' . $order_id . ' ha sido enviado y está en camino.</p>
                            
                            <div class="tracking-info">
                                <span class="tracking-label">Transportista:</span>
                                <strong>' . htmlspecialchars($carrier_name) . '</strong>
                                
                                <span class="tracking-label">Número de seguimiento:</span>
                                <div class="tracking-number">' . htmlspecialchars($tracking_number) . '</div>
                                
                                <a href="' . $tracking_url . '" class="btn">SEGUIR MI PEDIDO</a>
                            </div>
                            
                            <div class="divider"></div>
                            
                            <p>Puedes seguir el estado de tu envío en tiempo real haciendo clic en el botón de arriba o visitando nuestra página de seguimiento en cualquier momento.</p>
                            
                            <p>Si tienes alguna pregunta sobre tu pedido, no dudes en contactarnos respondiendo a este correo electrónico o a través de nuestros canales de atención al cliente.</p>
                            
                            <p>¡Gracias por confiar en Jersix!</p>
                            
                            <p>Saludos,<br>El equipo de Jersix</p>
                        </div>
                        <div class="footer">
                            <p>&copy; ' . date('Y') . ' Jersix. Todos los derechos reservados.</p>
                            <p>Este correo fue enviado a ' . htmlspecialchars($order['customer_email']) . '</p>
                        </div>
                    </div>
                </body>
                </html>
                ';
                
                // Headers para el correo
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: Jersix <info@jersix.com>\r\n";
                
                // Enviar el correo
                if (mail($to, $subject, $message, $headers)) {
                    $success_message .= " Se ha enviado un correo al cliente con la información de seguimiento.";
                } else {
                    $error_message = "Se actualizó la información de seguimiento, pero no se pudo enviar el correo al cliente.";
                }
            }
        } catch(PDOException $e) {
            $error_message = "Error al actualizar la información de seguimiento: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Seguimiento - Panel de Administración - Jersix</title>
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
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .panel-body {
            padding: 20px;
        }
        
        /* Form styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
            font-weight: 500;
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
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Order info */
        .order-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            border-left: 3px solid var(--primary-color);
        }
        
        .info-card h3 {
            margin-bottom: 10px;
            font-size: 16px;
            color: var(--dark-color);
        }
        
        .info-card p {
            margin: 0;
            color: var(--secondary-color);
        }
        
        .info-card strong {
            color: var(--dark-color);
        }
        
        .tracking-preview {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 20px;
        }
        
        .tracking-preview h3 {
            margin-bottom: 15px;
            font-size: 16px;
            color: var(--dark-color);
        }
        
        .tracking-number {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
        }
        
        .tracking-number i {
            color: #cc0000;
            font-size: 18px;
            margin-right: 10px;
        }
        
        .tracking-number .number {
            font-family: monospace;
            font-size: 16px;
            letter-spacing: 1px;
        }
        
        .tracking-link {
            margin-top: 15px;
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .tracking-link a {
            color: var(--primary-color);
            text-decoration: none;
            word-break: break-all;
        }
        
        @media (max-width: 768px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                grid-column: 1;
                padding: 15px;
            }
            
            .order-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Jersix Admin</h2>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="orders.php">
                        <i class="fas fa-shopping-cart"></i> Pedidos
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php">
                        <i class="fas fa-tshirt"></i> Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a href="giftcards.php">
                        <i class="fas fa-gift"></i> Tarjetas de Regalo
                    </a>
                </li>
                <li class="nav-item">
                    <a href="newsletter.php">
                        <i class="fas fa-envelope"></i> Newsletter
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>Agregar Seguimiento</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrador'); ?></span>
                    <a href="logout.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
            
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <div class="panel">
                <div class="panel-header">
                    <h2>Detalles del Pedido #<?php echo $order_id; ?></h2>
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
                
                <div class="panel-body">
                    <div class="order-info">
                        <div class="info-card">
                            <h3>Información del Cliente</h3>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></p>
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($order['phone'] ?? ''); ?></p>
                        </div>
                        
                        <div class="info-card">
                            <h3>Dirección de Envío</h3>
                            <p><strong>Calle:</strong> <?php echo htmlspecialchars($order['street'] ?? ''); ?></p>
                            <p><strong>Colonia:</strong> <?php echo htmlspecialchars($order['colony'] ?? ''); ?></p>
                            <p><strong>Ciudad:</strong> <?php echo htmlspecialchars($order['city'] ?? ''); ?>, <?php echo htmlspecialchars($order['state'] ?? ''); ?></p>
                            <p><strong>Código Postal:</strong> <?php echo htmlspecialchars($order['zip_code'] ?? ''); ?></p>
                        </div>
                        
                        <div class="info-card">
                            <h3>Detalles del Pedido</h3>
                            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'] ?? 'now')); ?></p>
                            <p><strong>Total:</strong> $<?php echo number_format($order['order_total'] ?? 0, 2); ?> MXN</p>
                            <p><strong>Estado:</strong> <?php echo htmlspecialchars($order['status'] ?? 'pending'); ?></p>
                            <p><strong>Pago:</strong> <?php echo htmlspecialchars($order['payment_status'] ?? 'pending'); ?></p>
                        </div>
                        
                        <div class="info-card">
                            <h3>Productos</h3>
                            <p><?php echo htmlspecialchars($order['products_summary'] ?? ''); ?></p>
                        </div>
                    </div>
                    
                    <form action="add_tracking.php?id=<?php echo $order_id; ?>" method="POST">
                        <div class="form-group">
                            <label for="tracking_number">Número de Seguimiento</label>
                            <input type="text" id="tracking_number" name="tracking_number" class="form-control" 
                                value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>" 
                                placeholder="Ingresa el número de guía" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="carrier_name">Transportista</label>
                            <select id="carrier_name" name="carrier_name" class="form-control">
                                <option value="DHL" <?php echo ($order['carrier_name'] ?? '') === 'DHL' ? 'selected' : ''; ?>>DHL</option>
                                <option value="Estafeta" <?php echo ($order['carrier_name'] ?? '') === 'Estafeta' ? 'selected' : ''; ?>>Estafeta</option>
                                <option value="FedEx" <?php echo ($order['carrier_name'] ?? '') === 'FedEx' ? 'selected' : ''; ?>>FedEx</option>
                                <option value="UPS" <?php echo ($order['carrier_name'] ?? '') === 'UPS' ? 'selected' : ''; ?>>UPS</option>
                                <option value="Correos de México" <?php echo ($order['carrier_name'] ?? '') === 'Correos de México' ? 'selected' : ''; ?>>Correos de México</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_date">Fecha de Envío</label>
                            <input type="datetime-local" id="shipping_date" name="shipping_date" class="form-control" 
                                value="<?php echo $order['shipping_date'] ? date('Y-m-d\TH:i', strtotime($order['shipping_date'])) : date('Y-m-d\TH:i'); ?>">
                        </div>
                        
                        <?php if (!empty($order['tracking_number'])): ?>
                        <div class="tracking-preview">
                            <h3>Vista Previa del Seguimiento</h3>
                            
                            <div class="tracking-number">
                                <i class="fas fa-barcode"></i>
                                <div class="number"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                            </div>
                            
                            <div class="tracking-link">
                                <strong>Enlace para el cliente:</strong><br>
                                <a href="../tracking.php?tracking=<?php echo urlencode($order['tracking_number']); ?>" target="_blank">
                                    <?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/tracking.php?tracking=' . urlencode($order['tracking_number']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Información de Seguimiento
                            </button>
                            
                            <a href="orders.php" class="btn btn-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 