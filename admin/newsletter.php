<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.html');
    exit();
}

// Database connection
require_once __DIR__ . '/../config/database.php';

// Mensaje para operaciones
$message = '';
$messageType = '';

// Procesar eliminación si se solicita
if (isset($_POST['delete_id'])) {
    try {
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("DELETE FROM newsletter WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        
        if ($stmt->rowCount() > 0) {
            $message = "Suscriptor eliminado correctamente";
            $messageType = "success";
        } else {
            $message = "No se encontró el suscriptor";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Error al eliminar: " . $e->getMessage();
        $messageType = "error";
    }
}

// Procesar exportación a CSV si se solicita
if (isset($_POST['export_csv'])) {
    try {
        $pdo = getConnection();
        
        $stmt = $pdo->query("SELECT email, subscribed_at FROM newsletter ORDER BY subscribed_at DESC");
        $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($subscribers) > 0) {
            // Generar contenido CSV
            $filename = "newsletter_subscribers_" . date("Y-m-d") . ".csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            
            $output = fopen('php://output', 'w');
            
            // Encabezados CSV
            fputcsv($output, array('Email', 'Fecha de Suscripción'));
            
            // Datos
            foreach ($subscribers as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit;
        }
    } catch (PDOException $e) {
        $message = "Error al exportar: " . $e->getMessage();
        $messageType = "error";
    }
}

// Obtener todos los suscriptores
try {
    $pdo = getConnection();
    
    // Verificar si la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'newsletter'");
    $tableExists = ($stmt->rowCount() > 0);
    
    if (!$tableExists) {
        // Crear la tabla si no existe según la estructura del archivo SQL
        $sql = "CREATE TABLE newsletter (
            id INT(11) NOT NULL AUTO_INCREMENT,
            email VARCHAR(100) NOT NULL,
            subscribed_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY idx_newsletter_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        $pdo->exec($sql);
        $message = "Se ha creado la tabla 'newsletter'";
        $messageType = "success";
    }
    
    // Obtener todos los suscriptores
    $query = "SELECT * FROM newsletter ORDER BY subscribed_at DESC";
    $subscriptions = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de suscripciones
    $total_subscriptions = count($subscriptions);
    
} catch(PDOException $e) {
    $message = "Error de conexión a la base de datos: " . $e->getMessage();
    $messageType = "error";
    $subscriptions = [];
    $total_subscriptions = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Newsletter - Jersix</title>
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
            overflow-x: auto;
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
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        .btn-success {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-success:hover {
            background-color: #218838;
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
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .search-form {
            display: flex;
            gap: 10px;
        }
        .search-form input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 250px;
        }
        .no-data {
            text-align: center;
            padding: 40px 0;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Jersix.mx</h2>
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
                <li class="nav-item">
                    <a href="csv_generator.php"><i class="fas fa-file-csv"></i> Generador CSV</a>
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
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="newsletter-stats">
                <h3>Resumen de Suscripciones</h3>
                <p>Total de suscriptores: <strong><?php echo $total_subscriptions; ?></strong></p>
            </div>
            
            <div class="actions-bar">
                <form class="search-form" method="GET">
                    <input type="text" name="search" placeholder="Buscar por correo electrónico..." 
                          value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if (isset($_GET['search'])): ?>
                        <a href="newsletter.php" class="btn btn-primary">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </form>
                
                <form method="POST">
                    <button type="submit" name="export_csv" class="btn btn-success">
                        <i class="fas fa-download"></i> Exportar a CSV
                    </button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Correo Electrónico</th>
                            <th>Fecha de Suscripción</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subscriptions)): ?>
                            <tr>
                                <td colspan="5" class="no-data">No hay suscriptores disponibles</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subscriptions as $subscription): ?>
                                <?php
                                // Filtrar por búsqueda si existe
                                if (isset($_GET['search']) && !empty($_GET['search'])) {
                                    $search = strtolower($_GET['search']);
                                    $email = strtolower($subscription['email']);
                                    if (strpos($email, $search) === false) {
                                        continue;
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subscription['id']); ?></td>
                                    <td><?php echo htmlspecialchars($subscription['email']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($subscription['subscribed_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-active">Activo</span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_id" value="<?php echo $subscription['id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar esta suscripción?');">
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
    </div>
</body>
</html>