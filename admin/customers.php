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
    $pdo = getConnection();
} catch(Exception $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Get customers data
$query = "SELECT * FROM customers ORDER BY created_at DESC";
$customers = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Jersey Store</title>
    <link rel="stylesheet" href="../Css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">

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
                <li class="nav-item active">
                    <a href="customers.php"><i class="fas fa-users"></i> Clientes</a>
                </li>
                <li class="nav-item">
                    <a href="newsletter.php"><i class="fas fa-envelope"></i> Correos</a>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Gestión de Clientes</h1>
                <div class="admin-controls">
                    <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="logout.php" class="btn btn-primary">Cerrar Sesión</a>
                </div>
            </div>
            
            <div class="content-actions">
                <div class="search-box">
                    <input type="text" id="searchCustomers" placeholder="Buscar clientes...">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Ciudad</th>
                            <th>Estado</th>
                            <th>Código Postal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['customer_id']); ?></td>
                            <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo htmlspecialchars($customer['address_line1']); ?></td>
                            <td><?php echo htmlspecialchars($customer['city']); ?></td>
                            <td><?php echo htmlspecialchars($customer['state']); ?></td>
                            <td><?php echo htmlspecialchars($customer['postal_code']); ?></td>
                            <td class="actions">
                                <button class="btn-icon view-customer" data-id="<?php echo $customer['customer_id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon edit-customer" data-id="<?php echo $customer['customer_id']; ?>">
                                    <i class="fas fa-edit"></i>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('searchCustomers');
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });

            // View customer details
            document.querySelectorAll('.view-customer').forEach(button => {
                button.addEventListener('click', function() {
                    const customerId = this.getAttribute('data-id');
                    alert('Ver detalles del cliente ' + customerId);
                });
            });

            // Edit customer
            document.querySelectorAll('.edit-customer').forEach(button => {
                button.addEventListener('click', function() {
                    const customerId = this.getAttribute('data-id');
                    alert('Editar cliente ' + customerId);
                });
            });
        });
    </script>
</body>
</html>