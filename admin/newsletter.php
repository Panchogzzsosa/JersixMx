<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.html');
    exit();
}

// Database connection

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die('Error de conexión a la base de datos');
}

// Get all newsletter subscriptions
$query = "SELECT * FROM newsletter ORDER BY subscription_date DESC";
$subscriptions = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Count total subscriptions
$total_subscriptions = count($subscriptions);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Newsletter - Jersey Store</title>
    <link rel="stylesheet" href="../Css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <style>
        .newsletter-stats {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .newsletter-stats h3 {
            color: #333;
            margin-bottom: 10px;
        }
        .table-responsive {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 600;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            color: #555;
        }
        .table tr:hover {
            background-color: #f8f9fa;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .status-active {
            background-color: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Jersey Store</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
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
                <li class="nav-item active">
                    <a href="newsletter.php"><i class="fas fa-envelope"></i> Correos</a>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Gestión de Newsletter</h1>
                <div class="admin-controls">
                    <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="logout.php" class="btn btn-primary">Cerrar Sesión</a>
                </div>
            </div>
            
            <div class="newsletter-stats">
                <h3>Resumen de Suscripciones</h3>
                <p>Total de suscriptores: <strong><?php echo $total_subscriptions; ?></strong></p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Correo Electrónico</th>
                            <th>Fecha de Suscripción</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptions as $subscription): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subscription['id']); ?></td>
                            <td><?php echo htmlspecialchars($subscription['email']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($subscription['subscription_date'])); ?></td>
                            <td>
                                <span class="status-badge status-active">Activo</span>
                            </td>
                            <td>
                                <button class="btn btn-danger" onclick="deleteSubscription(<?php echo $subscription['id']; ?>)">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function deleteSubscription(id) {
        if (confirm('¿Estás seguro de que deseas eliminar esta suscripción?')) {
            fetch('delete_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar la suscripción: ' + (data.message || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        }
    }
    </script>
</body>
</html>