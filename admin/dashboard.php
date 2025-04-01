<?php
// Mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Añadir mensaje de diagnóstico
echo "Verificando sesión... ";
if (!isset($_SESSION['admin_id'])) {
    echo "No hay sesión de administrador. Redirigiendo a login.html";
    header('Location: login.html');
    exit();
} else {
    echo "Sesión de administrador encontrada.<br>";
}

// Diagnóstico de conexión a base de datos
echo "Intentando conectar a la base de datos... ";
try {
    $pdo = new PDO('mysql:host=localhost:3307;dbname=checkout', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Conexión exitosa a la base de datos.<br>";
} catch(PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
    die();
}

// Si llegas aquí, imprime un mensaje de éxito y detén la ejecución para diagnóstico
echo "Todo parece estar funcionando correctamente hasta este punto. Si ves este mensaje, el problema puede estar en el código posterior.";
exit();

// Get customer data with their orders
// Get active customers (those with orders in the last 30 days)
$activeCustomersQuery = "
    SELECT COUNT(DISTINCT c.customer_id) as active_count
    FROM customers c
    INNER JOIN orders o ON c.customer_id = o.customer_id
    WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND o.status != 'cancelled'
";
$activeCustomers = $pdo->query($activeCustomersQuery)->fetch(PDO::FETCH_ASSOC);

$query = "
    SELECT 
        c.customer_id,
        CONCAT(c.first_name, ' ', c.last_name) as name,
        c.email,
        c.phone,
        c.address_line1 as address,
        c.city,
        c.state,
        c.postal_code,
        o.order_date,
        o.total_amount,
        o.status,
        CASE 
            WHEN o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Activo'
            ELSE 'Inactivo'
        END as customer_status
    FROM customers c
    INNER JOIN orders o ON c.customer_id = o.customer_id
    WHERE o.status != 'cancelled'
    GROUP BY c.customer_id
    ORDER BY o.order_date DESC
";

$customers = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Jersix</title>
    <link rel="stylesheet" href="../Css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Jersix.mx</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item active">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a>
                </li>
                <li class="nav-item">
                    <a href="products.php"><i class="fas fa-shopping-bag"></i> Productos</a>
                </li>
                <li class="nav-item">
                    <a href="orders.php"><i class="fas fa-shopping-cart"></i> Pedidos</a>
                </li>
                <li class="nav-item">
                    <a href="customers.php"><i class="fas fa-users"></i> Clientes</a>
                </li>
                <li class="nav-item">
                    <a href="newsletter.php"><i class="fas fa-envelope"></i> Correos</a>
                </li>
                <li class="nav-item">
                    <a href="csv_generator.php"><i class="fas fa-file-csv"></i> Generador CSV</a>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Dashboard</h1>
                <div class="admin-controls">
                    <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="logout.php" class="btn btn-primary">Cerrar Sesión</a>
                </div>
            </div>
            
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ordenes Totales</h3>
                        <i class="fas fa-shopping-cart text-primary"></i>
                    </div>
                    <p class="card-value"><?php echo count($customers); ?></p>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ventas Totales</h3>
                        <i class="fas fa-chart-line text-success"></i>
                    </div>
                    <p class="card-value">$<?php 
                        $total = array_sum(array_column($customers, 'total_amount'));
                        echo number_format($total, 2);
                    ?></p>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Clientes Activos</h3>
                        <i class="fas fa-users text-warning"></i>
                    </div>
                    <p class="card-value"><?php echo $activeCustomers['active_count']; ?></p>
                </div>
            </div>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Clientes</th>
                            <th>Contacto</th>
                            <th>Dirección</th>
                            <th>Fecha de Orden</th>
                            <th>Estatus</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td>
                                <div>Email: <?php echo htmlspecialchars($customer['email']); ?></div>
                                <div>Tel: <?php echo htmlspecialchars($customer['phone']); ?></div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($customer['address']); ?></div>
                                <div><?php echo htmlspecialchars($customer['city'] . ', ' . $customer['state'] . ' ' . $customer['postal_code']); ?></div>
                            </td>
                            <td><?php echo $customer['order_date'] ? date('d/m/Y', strtotime($customer['order_date'])) : 'N/A'; ?></td>
                            <td><span class="text-<?php 
                                echo match($customer['status']) {
                                    'completed' => 'success',
                                    'pending' => 'warning',
                                    'cancelled' => 'danger',
                                    default => 'muted'
                                };
                            ?>"><?php echo ucfirst($customer['status'] ?? 'N/A'); ?></span></td>
                            <td>$<?php echo number_format($customer['total_amount'] ?? 0, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const navItems = document.querySelectorAll('.nav-item');
            
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>