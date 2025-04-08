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

// Verificar que se ha proporcionado un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: products.php?error=no_id');
    exit();
}

$product_id = $_GET['id'];

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Obtener información del producto para eliminar la imagen si existe
try {
    $stmt = $pdo->prepare("SELECT image_url FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: products.php?error=not_found');
        exit();
    }
    
    // Eliminar el producto
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // Eliminar la imagen si existe y no es la imagen por defecto
    if (!empty($product['image_url']) && strpos($product['image_url'], 'default.jpg') === false && file_exists('../' . $product['image_url'])) {
        unlink('../' . $product['image_url']);
    }
    
    // Redireccionar a la página de productos con mensaje de éxito
    header('Location: products.php?deleted=true');
    exit();
    
} catch(PDOException $e) {
    die('Error al eliminar el producto: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Producto - Panel de Administración - Jersix</title>
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
        
        .user-info .btn {
            margin-left: 10px;
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
        
        /* Confirm delete */
        .confirm-delete {
            text-align: center;
            padding: 30px;
        }
        
        .warning-icon {
            font-size: 48px;
            color: var(--warning-color);
            margin-bottom: 20px;
        }
        
        .delete-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .delete-message {
            color: var(--secondary-color);
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .product-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            background: var(--light-color);
            padding: 20px;
            border-radius: var(--border-radius);
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .product-preview img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        
        .product-preview-name {
            font-weight: 600;
            font-size: 16px;
        }
        
        .delete-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
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
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-lg {
            padding: 12px 24px;
            font-size: 16px;
        }
        
        /* Error message */
        .error-message {
            padding: 15px;
            border-radius: var(--border-radius);
            background-color: rgba(220,53,69,0.1);
            color: var(--danger-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .error-message i {
            margin-right: 10px;
            font-size: 18px;
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
            
            .delete-actions {
                flex-direction: column;
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
                <li class="nav-item active">
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
                <li class="nav-item">
                    <a href="#">
                        <i class="fas fa-users"></i>
                        <span>Clientes</span>
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
                <h1>Eliminar Producto</h1>
                <div class="user-info">
                    <span>Usuario: Admin</span>
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Volver a Productos
                    </a>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Confirmar Eliminación</h3>
                </div>
                <div class="panel-body">
                    <div class="confirm-delete">
                        <div class="warning-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h2 class="delete-title">¿Estás seguro?</h2>
                        <p class="delete-message">
                            Estás a punto de eliminar el siguiente producto. Esta acción no se puede deshacer y eliminará permanentemente este producto de la base de datos.
                        </p>
                        
                        <div class="product-preview">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <div style="width: 60px; height: 60px; background-color: #e9ecef; display: flex; align-items: center; justify-content: center; border-radius: 4px; margin-right: 15px;">
                                    <i class="fas fa-image" style="color: #adb5bd;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-preview-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        </div>
                        
                        <form method="post" class="delete-actions">
                            <input type="hidden" name="confirm_delete" value="1">
                            <a href="products.php" class="btn btn-secondary btn-lg">Cancelar</a>
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="fas fa-trash"></i> Eliminar Producto
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Mobile menu toggle -->
    <div class="mobile-toggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </div>
    
    <script>
        // Responsive sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (window.innerWidth <= 768) {
                mobileToggle.style.display = 'flex';
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    mobileToggle.style.display = 'flex';
                } else {
                    mobileToggle.style.display = 'none';
                    sidebar.classList.add('active');
                }
            });
            
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        });
    </script>
</body>
</html> 