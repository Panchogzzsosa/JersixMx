<?php
session_start();

// Verificar inicio de sesión
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Manejar la activación/desactivación del banner
if (isset($_POST['toggle_banner'])) {
    try {
        $pdo = getConnection();
        $bannerId = $_POST['banner_id'];
        $nuevoEstado = $_POST['nuevo_estado'];
        
        // Si estamos activando un banner, primero desactivamos todos
        if ($nuevoEstado == 1) {
            $stmt = $pdo->prepare("UPDATE banner_config SET activo = 0");
            $stmt->execute();
        }
        
        // Actualizar el estado del banner seleccionado
        $stmt = $pdo->prepare("UPDATE banner_config SET activo = ? WHERE id = ?");
        $stmt->execute([$nuevoEstado, $bannerId]);

        $_SESSION['success_message'] = "Estado del banner actualizado exitosamente.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch(PDOException $e) {
        $error = "Error al actualizar el estado del banner: " . $e->getMessage();
    }
}

// Manejar la eliminación de banner
if (isset($_POST['delete_banner'])) {
    try {
        $pdo = getConnection();
        $bannerId = $_POST['banner_id'];
        
        $stmt = $pdo->prepare("DELETE FROM banner_config WHERE id = ?");
        $stmt->execute([$bannerId]);

        $_SESSION['success_message'] = "Banner eliminado exitosamente.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch(PDOException $e) {
        $error = "Error al eliminar el banner: " . $e->getMessage();
    }
}

// Verificar si se envió el formulario de crear/editar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_banner']) && !isset($_POST['toggle_banner'])) {
    try {
        $pdo = getConnection();
        
        $mensaje = $_POST['mensaje'] ?? '';
        $colorTexto = $_POST['color_texto'] ?? '#FFFFFF';
        $colorFondo = $_POST['color_fondo'] ?? '#000000';
        $activo = isset($_POST['activo']) ? 1 : 0;

        // Desactivar todos los banners si el nuevo está activo
        if ($activo) {
            $stmt = $pdo->prepare("UPDATE banner_config SET activo = 0");
            $stmt->execute();
        }

        // Insertar nuevo banner
        $stmt = $pdo->prepare("INSERT INTO banner_config (mensaje, color_texto, color_fondo, activo) VALUES (?, ?, ?, ?)");
        $stmt->execute([$mensaje, $colorTexto, $colorFondo, $activo]);

        $_SESSION['success_message'] = "Banner guardado exitosamente.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch(PDOException $e) {
        $error = "Error al guardar el banner: " . $e->getMessage();
    }
}

// Obtener banners existentes
try {
    $pdo = getConnection();
    $banners = $pdo->query("SELECT * FROM banner_config ORDER BY created_at DESC")->fetchAll();
} catch(PDOException $e) {
    $error = "Error al obtener los banners: " . $e->getMessage();
    $banners = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Banner - Panel de Administración</title>
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
        
        .panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .panel-header {
            padding: 20px;
            border-bottom: 1px solid #eaedf3;
        }
        
        .panel-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .panel-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .color-input {
            width: 100px !important;
            height: 40px;
            padding: 2px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            border: 1px solid #eaedf3;
        }
        
        .table th {
            background: var(--light-color);
            font-weight: 600;
            text-align: left;
        }
        
        .color-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            vertical-align: middle;
            margin-right: 8px;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background: var(--success-color);
            color: white;
        }
        
        .badge-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .delete-form {
            display: inline;
        }
        
        .delete-btn {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1050;
        }
        
        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .modal-header {
            margin-bottom: 15px;
        }
        
        .modal-footer {
            margin-top: 15px;
            text-align: right;
        }
        
        .modal-footer .btn {
            margin-left: 10px;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .actions-column {
            white-space: nowrap;
        }
        
        .actions-column .btn {
            margin-right: 5px;
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
                <li class="nav-item active">
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
            <div class="topbar">
                <h1>Configuración del Banner</h1>
                <div class="user-info">
                    <img src="../img/ICON.png" alt="User" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                    <span><?php echo $_SESSION['admin_username'] ?? 'Administrador'; ?></span>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Crear Nuevo Banner</h3>
                </div>
                <div class="panel-body">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="mensaje">Mensaje del Banner:</label>
                            <input type="text" class="form-control" id="mensaje" name="mensaje" required>
                        </div>

                        <div class="form-group">
                            <label for="color_texto">Color del Texto:</label>
                            <input type="color" class="form-control color-input" id="color_texto" name="color_texto" value="#FFFFFF">
                        </div>

                        <div class="form-group">
                            <label for="color_fondo">Color del Fondo:</label>
                            <input type="color" class="form-control color-input" id="color_fondo" name="color_fondo" value="#000000">
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="activo" checked>
                                Activar Banner
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">Guardar Banner</button>
                    </form>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Banners Existentes</h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Mensaje</th>
                                    <th>Color Texto</th>
                                    <th>Color Fondo</th>
                                    <th>Estado</th>
                                    <th>Fecha Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($banners as $banner): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($banner['mensaje']); ?></td>
                                    <td>
                                        <span class="color-preview" style="background-color: <?php echo htmlspecialchars($banner['color_texto']); ?>"></span>
                                        <?php echo htmlspecialchars($banner['color_texto']); ?>
                                    </td>
                                    <td>
                                        <span class="color-preview" style="background-color: <?php echo htmlspecialchars($banner['color_fondo']); ?>"></span>
                                        <?php echo htmlspecialchars($banner['color_fondo']); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $banner['activo'] ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $banner['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $banner['created_at']; ?></td>
                                    <td class="actions-column">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $banner['activo'] ? '0' : '1'; ?>">
                                            <input type="hidden" name="toggle_banner" value="1">
                                            <button type="submit" class="btn <?php echo $banner['activo'] ? 'btn-secondary' : 'btn-success'; ?>" title="<?php echo $banner['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                <i class="fas <?php echo $banner['activo'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                                <?php echo $banner['activo'] ? 'Desactivar' : 'Activar'; ?>
                                            </button>
                                        </form>
                                        <button class="btn btn-danger delete-btn" 
                                                onclick="showDeleteModal(<?php echo $banner['id']; ?>, '<?php echo htmlspecialchars($banner['mensaje'], ENT_QUOTES); ?>')">
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
        </main>
    </div>

    <!-- Modal de confirmación de eliminación -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Confirmar Eliminación</h4>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <p>¿Estás seguro de que deseas eliminar este banner?</p>
            <p id="bannerMessage" style="font-style: italic;"></p>
            <div class="modal-footer">
                <form method="POST" class="delete-form">
                    <input type="hidden" name="banner_id" id="deleteBannerId">
                    <input type="hidden" name="delete_banner" value="1">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showDeleteModal(bannerId, mensaje) {
            document.getElementById('deleteModal').style.display = 'block';
            document.getElementById('deleteBannerId').value = bannerId;
            document.getElementById('bannerMessage').textContent = mensaje;
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            var modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html> 