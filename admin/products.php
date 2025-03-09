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

// Get products data
$query = "SELECT * FROM products ORDER BY name ASC";
$products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);



// Handle product deletion if requested
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if ($product_id) {
        try {
            // Get product image URL before deletion
            $stmt = $pdo->prepare('SELECT image_url FROM products WHERE product_id = ?');
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            // Delete the product image if it exists
            if ($product && $product['image_url']) {
                $imagePath = '../' . $product['image_url'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            // Delete the product from database
            $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?');
            $stmt->execute([$product_id]);

            // Refresh the products list
            $products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die('Error al eliminar el producto');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Jersey Store</title>
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
                <li class="nav-item active">
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
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Gestión de Productos</h1>
                <div class="admin-controls">
                    <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="logout.php" class="btn btn-primary">Cerrar Sesión</a>
                </div>
            </div>
            
            <div class="content-actions">
                <a href="add_product.php" class="btn btn-primary"><i class="fas fa-plus"></i> Agregar Producto</a>
            </div>
            <br>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Categoría</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <?php if ($product['image_url']): ?>
                                    <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    <span>No imagen</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($product['stock']); ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td class="actions">
                                <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-small btn-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este producto?');">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <button type="submit" name="delete_product" class="btn btn-small btn-delete">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>


        </div>
    </div>
</body>
</html>