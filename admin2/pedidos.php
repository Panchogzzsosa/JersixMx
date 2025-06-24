<?php
// Activar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
$conn = getConnection();

// Crear tabla si no existe
$conn->exec("CREATE TABLE IF NOT EXISTS pedidos_internos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente VARCHAR(100),
    cantidad INT,
    producto VARCHAR(100),
    imagen VARCHAR(255),
    talla VARCHAR(20),
    precio_compra DECIMAL(10,2),
    precio_venta DECIMAL(10,2),
    detalles TEXT,
    estado ENUM('nada', 'pedido', 'enviado', 'recibido') DEFAULT 'nada',
    orden INT DEFAULT 0,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Verificar si la columna imagen existe
$stmt = $conn->query("SHOW COLUMNS FROM pedidos_internos LIKE 'imagen'");
if ($stmt->rowCount() == 0) {
    $conn->exec("ALTER TABLE pedidos_internos ADD COLUMN imagen VARCHAR(255) AFTER producto");
}

// Verificar si la columna orden existe
$stmt = $conn->query("SHOW COLUMNS FROM pedidos_internos LIKE 'orden'");
if ($stmt->rowCount() == 0) {
    $conn->exec("ALTER TABLE pedidos_internos ADD COLUMN orden INT DEFAULT 0 AFTER estado");
    // Inicializar el orden según el id
    $conn->exec("UPDATE pedidos_internos SET orden = id");
}

// Obtener productos disponibles
$productos = $conn->query("SELECT product_id as id, name as nombre, price as precio, image_url FROM products WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Insertar pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        // Buscar la imagen del producto seleccionado
        $imagen = '';
        foreach ($productos as $prod) {
            if ($prod['nombre'] === $_POST['producto']) {
                $imagen = $prod['image_url'];
                break;
            }
        }
        $stmt = $conn->prepare("INSERT INTO pedidos_internos (cliente, cantidad, producto, imagen, talla, precio_compra, precio_venta, detalles, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['cliente'],
            $_POST['cantidad'],
            $_POST['producto'],
            $imagen,
            $_POST['talla'],
            $_POST['precio_compra'],
            $_POST['precio_venta'],
            $_POST['detalles'],
            $_POST['estado'] ?? 'nada'
        ]);
        header('Location: pedidos.php');
        exit;
    } elseif ($_POST['action'] === 'update_status') {
        $stmt = $conn->prepare("UPDATE pedidos_internos SET estado = ? WHERE id = ?");
        $stmt->execute([$_POST['estado'], $_POST['id']]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } elseif ($_POST['action'] === 'edit') {
        $stmt = $conn->prepare("UPDATE pedidos_internos SET cliente = ?, cantidad = ?, producto = ?, talla = ?, precio_compra = ?, precio_venta = ?, detalles = ?, estado = ? WHERE id = ?");
        $stmt->execute([
            $_POST['cliente'],
            $_POST['cantidad'],
            $_POST['producto'],
            $_POST['talla'],
            $_POST['precio_compra'],
            $_POST['precio_venta'],
            $_POST['detalles'],
            $_POST['estado'],
            $_POST['id']
        ]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } elseif ($_POST['action'] === 'delete') {
        $stmt = $conn->prepare("DELETE FROM pedidos_internos WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } elseif ($_POST['action'] === 'move') {
        $id = intval($_POST['id']);
        $direction = $_POST['direction'];
        // Obtener el pedido actual
        $pedido = $conn->query("SELECT id, orden FROM pedidos_internos WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        if ($pedido) {
            if ($direction === 'up') {
                // Buscar el pedido anterior
                $anterior = $conn->query("SELECT id, orden FROM pedidos_internos WHERE orden < {$pedido['orden']} ORDER BY orden DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($anterior) {
                    // Intercambiar orden
                    $conn->exec("UPDATE pedidos_internos SET orden = {$anterior['orden']} WHERE id = {$pedido['id']}");
                    $conn->exec("UPDATE pedidos_internos SET orden = {$pedido['orden']} WHERE id = {$anterior['id']}");
                }
            } elseif ($direction === 'down') {
                // Buscar el pedido siguiente
                $siguiente = $conn->query("SELECT id, orden FROM pedidos_internos WHERE orden > {$pedido['orden']} ORDER BY orden ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($siguiente) {
                    // Intercambiar orden
                    $conn->exec("UPDATE pedidos_internos SET orden = {$siguiente['orden']} WHERE id = {$pedido['id']}");
                    $conn->exec("UPDATE pedidos_internos SET orden = {$pedido['orden']} WHERE id = {$siguiente['id']}");
                }
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// Obtener pedido para editar
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_pedido') {
    $stmt = $conn->prepare("SELECT * FROM pedidos_internos WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($pedido);
    exit;
}

// Obtener pedidos ordenados
$pedidos = $conn->query("SELECT * FROM pedidos_internos ORDER BY orden ASC, fecha DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos Internos</title>
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
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --sidebar-width: 240px;
            --topbar-height: 60px;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; font-size: 14px; color: #333; background-color: #f5f7fa; line-height: 1.5; }
        .dashboard-layout { display: grid; grid-template-columns: var(--sidebar-width) 1fr; min-height: 100vh; }
        .sidebar { background-color: var(--dark-color); color: white; position: fixed; height: 100vh; width: var(--sidebar-width); padding-top: 15px; overflow-y: auto; transition: var(--transition); z-index: 1000; }
        .sidebar-header { padding: 0 20px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 20px; font-weight: 600; }
        .sidebar .nav-menu { list-style: none; padding: 15px 0; }
        .sidebar .nav-item { margin: 5px 0; }
        .sidebar .nav-item a { color: rgba(255,255,255,0.7); text-decoration: none; display: flex; align-items: center; padding: 12px 20px; border-radius: 4px; margin: 0 8px; transition: var(--transition); }
        .sidebar .nav-item a i { margin-right: 10px; font-size: 18px; }
        .sidebar .nav-item a:hover { color: white; background: rgba(255,255,255,0.1); }
        .sidebar .nav-item.active a { color: white; background: var(--primary-color); }
        .main-content { grid-column: 2; padding: 30px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .topbar h1 { font-size: 24px; font-weight: 600; color: var(--dark-color); }
        .user-info { display: flex; align-items: center; background: white; padding: 10px 15px; border-radius: var(--border-radius); box-shadow: var(--box-shadow); }
        .user-info span { margin-right: 15px; color: var(--secondary-color); }
        .panel { background: white; border-radius: var(--border-radius); box-shadow: var(--box-shadow); margin-bottom: 30px; overflow: hidden; }
        .panel-header { padding: 20px; border-bottom: 1px solid #eaedf3; display: flex; justify-content: space-between; align-items: center; }
        .panel-title { font-size: 16px; font-weight: 600; color: var(--dark-color); }
        .btn { background: var(--primary-color); color: white; border: none; border-radius: var(--border-radius); padding: 8px 18px; font-weight: 500; cursor: pointer; transition: var(--transition); font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .btn:hover { background: var(--primary-dark); }
        .table-container {
            width: 100%;
            overflow-x: auto;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            margin: 0 auto;
            background: white;
            max-width: 95%;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        th {
            background: #f8f9fa;
            color: var(--dark-color);
            font-size: 1.05rem;
            font-weight: 600;
            padding: 12px 10px;
            border-bottom: 1.5px solid #eaedf3;
        }
        td {
            font-size: 0.98rem;
            padding: 10px 10px;
            border-bottom: 1px solid #eaedf3;
            vertical-align: middle;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background-color: #f5f7fa;
        }
        .ganancia {
            font-weight: bold;
            color: #28a745;
        }
        .estado-select {
            padding: 8px 30px 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='currentColor' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 10px;
            min-width: 120px;
            max-width: 100%;
            text-align: left;
            transition: border 0.2s;
        }
        .estado-nada { background-color: #f8f9fa; color: #6c757d; border: 1px solid #e0e0e0; }
        .estado-pedido { background-color: #fff8e1; color: #f57c00; border: 1px solid #ffe0b2; }
        .estado-enviado { background-color: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb; }
        .estado-recibido { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .actions-cell {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: flex-start;
        }
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: background 0.2s;
            font-size: 18px;
            display: flex;
            align-items: center;
            margin-top: 15px;
        }
        .btn-icon.btn-edit { color: var(--primary-color); }
        .btn-icon.btn-delete { color: var(--danger-color); }
        .btn-icon:hover { background-color: #f8f9fa; }
        @media (max-width: 900px) {
            .table-container { max-width: 100%; }
            table, thead, tbody, tr, th, td { display: block; width: 100%; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { margin-bottom: 20px; border: 1px solid #e9ecef; border-radius: var(--border-radius); background-color: white; box-shadow: var(--box-shadow); overflow: hidden; }
            td { border: none; position: relative; padding: 12px 15px; padding-left: 50%; text-align: right; }
            td:before { position: absolute; top: 12px; left: 15px; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: 600; color: var(--secondary-color); content: attr(data-label); }
            td:last-child { border-bottom: 0; }
            .actions-cell { justify-content: flex-end; }
        }
        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background-color: #fff; margin: 40px auto; width: 95%; max-width: 600px; border-radius: var(--border-radius); box-shadow: var(--box-shadow); }
        .modal-header { padding: 20px; border-bottom: 1px solid #eaedf3; display: flex; justify-content: space-between; align-items: center; }
        .close { font-size: 24px; cursor: pointer; color: var(--secondary-color); }
        .close:hover { color: var(--danger-color); }
        .form-section { padding: 20px; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; }
        .form-group label { font-weight: 500; margin-bottom: 5px; color: var(--dark-color); }
        .form-group input, .form-group textarea { padding: 8px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; }
        .form-group textarea { min-height: 36px; }
        .form-footer { padding: 20px; background-color: #f8f9fa; border-top: 1px solid #eaedf3; display: flex; justify-content: flex-end; }
        .select2-container {
            width: 100% !important;
        }
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
            padding-left: 12px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .select2-dropdown {
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .select2-search--dropdown .select2-search__field {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .select2-results__option {
            padding: 8px 12px;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--primary-color);
        }
        .btn-details {
            color: var(--secondary-color);
            font-size: 18px;
            margin-top: 0;
        }
        .btn-details:focus {
            outline: none;
        }
        .btn-move {
            color: var(--secondary-color);
            font-size: 16px;
            margin-top: 15px;
        }
        .btn-move:focus {
            outline: none;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
<div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Jersix.mx</h2>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php"><i class="fas fa-home"></i><span>Inicio</span></a></li>
            <li class="nav-item"><a href="products.php"><i class="fas fa-box"></i><span>Productos</span></a></li>
            <li class="nav-item"><a href="inventario.php"><i class="fas fa-warehouse"></i><span>Inventario</span></a></li>
            <li class="nav-item"><a href="orders.php"><i class="fas fa-shopping-cart"></i><span>Compras</span></a></li>
            <li class="nav-item"><a href="newsletter.php"><i class="fas fa-users"></i><span>Clientes / Newsletter</span></a></li>
            <li class="nav-item"><a href="giftcards.php"><i class="fas fa-gift"></i><span>Gift Cards</span></a></li>
            <li class="nav-item"><a href="promociones.php"><i class="fas fa-percent"></i><span>Promociones</span></a></li>
            <li class="nav-item"><a href="banner_config.php"><i class="fas fa-image"></i><span>Banner</span></a></li>
            <li class="nav-item"><a href="banner_manager.php"><i class="fas fa-images"></i><span>Fotos y Lo más vendido</span></a></li>
            <li class="nav-item active"><a href="pedidos.php"><i class="fas fa-clipboard-list"></i><span>Pedidos</span></a></li>
            <li class="nav-item"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a></li>
        </ul>
    </aside>
    <!-- Main Content -->
    <main class="main-content">
        <div class="topbar">
            <div><h1>Pedidos Internos</h1></div>
            <div class="user-info">
                <img src="../img/ICON.png" alt="User" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                <span><?php echo $_SESSION['admin_username'] ?? 'Administrador'; ?></span>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title"><i class=""></i>Lista de Pedidos</h3>
                <button class="btn" onclick="openAddPedidoModal()"><i class="fas fa-plus"></i> Agregar Pedido</button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Cliente</th>
                            <th>Precio de compra</th>
                            <th>Precio de venta</th>
                            <th>Ganancia</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos as $p): ?>
                        <tr>
                            <td>
                                <button class="btn-icon btn-details" onclick="toggleDetalles(<?= $p['id'] ?>)">
                                    <i class="fas fa-chevron-down" id="icon-detalles-<?= $p['id'] ?>"></i>
                                </button>
                            </td>
                            <td><?= htmlspecialchars($p['cliente']) ?></td>
                            <td>$<?= number_format($p['precio_compra'],2) ?></td>
                            <td>$<?= number_format($p['precio_venta'],2) ?></td>
                            <td class="ganancia">$<?= number_format(($p['precio_venta']-$p['precio_compra'])*$p['cantidad'],2) ?></td>
                            <td>
                                <select class="estado-select estado-<?= $p['estado'] ?>" 
                                        onchange="updateStatus(<?= $p['id'] ?>, this.value)"
                                        data-id="<?= $p['id'] ?>">
                                    <option value="nada" <?= $p['estado'] == 'nada' ? 'selected' : '' ?>>Nada</option>
                                    <option value="pedido" <?= $p['estado'] == 'pedido' ? 'selected' : '' ?>>Pedido</option>
                                    <option value="enviado" <?= $p['estado'] == 'enviado' ? 'selected' : '' ?>>Enviado</option>
                                    <option value="recibido" <?= $p['estado'] == 'recibido' ? 'selected' : '' ?>>Recibido</option>
                                </select>
                            </td>
                            <td class="actions-cell">
                                <button class="btn-icon btn-move" onclick="moverPedido(<?= $p['id'] ?>, 'up')" title="Subir"><i class="fas fa-arrow-up"></i></button>
                                <button class="btn-icon btn-move" onclick="moverPedido(<?= $p['id'] ?>, 'down')" title="Bajar"><i class="fas fa-arrow-down"></i></button>
                                <button class="btn-icon btn-edit" onclick="editPedido(<?= $p['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon btn-delete" onclick="deletePedido(<?= $p['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <tr id="detalles-<?= $p['id'] ?>" style="display:none; background:#f8f9fa;">
                            <td colspan="7">
                                <div style="display:flex; gap:24px; align-items:flex-start; padding:18px 10px;">
                                    <div>
                                        <?php if (!empty($p['imagen'])): ?>
                                            <img src="../<?= htmlspecialchars($p['imagen']) ?>" alt="Imagen" style="width:60px; height:60px; object-fit:cover; border-radius:8px;">
                                        <?php else: ?>
                                            <div style="width:60px; height:60px; background:#f0f0f0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#bbb; font-size:22px;">-</div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:grid; gap:6px;">
                                        <div><b>Cantidad:</b> <?= htmlspecialchars($p['cantidad']) ?></div>
                                        <div><b>Producto:</b> <?= htmlspecialchars($p['producto']) ?></div>
                                        <div><b>Talla:</b> <?= htmlspecialchars($p['talla']) ?></div>
                                        <div><b>Detalles:</b> <?= nl2br(htmlspecialchars($p['detalles'])) ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<!-- Modal para agregar pedido -->
<div id="addPedidoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Agregar Pedido</h3>
            <span class="close" onclick="closeAddPedidoModal()">&times;</span>
        </div>
        <form method="POST" class="form-section" onsubmit="return validatePedidoForm()">
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label>Cliente</label><input type="text" name="cliente" required></div>
            <div class="form-group"><label>Cantidad</label><input type="number" name="cantidad" min="1" required></div>
            <div class="form-group">
                <label>Producto</label>
                <select name="producto" class="producto-select" required onchange="mostrarImagenProducto(this, 'add')">
                    <option value="">Seleccione un producto</option>
                    <?php foreach ($productos as $prod): ?>
                        <option value="<?= htmlspecialchars($prod['nombre']) ?>" data-precio="<?= $prod['precio'] ?>" data-imagen="<?= htmlspecialchars($prod['image_url']) ?>">
                            <?= htmlspecialchars($prod['nombre']) ?> - $<?= number_format($prod['precio'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="preview-imagen-add" style="margin-top:8px;"></div>
            </div>
            <div class="form-group"><label>Talla</label><input type="text" name="talla" required></div>
            <div class="form-group"><label>Precio de compra</label><input type="number" name="precio_compra" min="0" step="0.01" required></div>
            <div class="form-group"><label>Precio de venta</label><input type="number" name="precio_venta" min="0" step="0.01" required></div>
            <div class="form-group"><label>Detalles</label><textarea name="detalles"></textarea></div>
            <div class="form-group">
                <label>Estado</label>
                <select name="estado" class="estado-select" required>
                    <option value="nada">Nada</option>
                    <option value="pedido">Pedido</option>
                    <option value="enviado">Enviado</option>
                    <option value="recibido">Recibido</option>
                </select>
            </div>
            <div class="form-footer"><button class="btn" type="submit"><i class="fas fa-plus"></i> Agregar</button></div>
        </form>
    </div>
</div>
<!-- Modal para editar pedido -->
<div id="editPedidoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Pedido</h3>
            <span class="close" onclick="closeEditPedidoModal()">&times;</span>
        </div>
        <form method="POST" class="form-section" onsubmit="return submitEditForm(event)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group"><label>Cliente</label><input type="text" name="cliente" id="edit_cliente" required></div>
            <div class="form-group"><label>Cantidad</label><input type="number" name="cantidad" id="edit_cantidad" min="1" required></div>
            <div class="form-group">
                <label>Producto</label>
                <select name="producto" id="edit_producto" class="producto-select" required onchange="mostrarImagenProducto(this, 'edit')">
                    <option value="">Seleccione un producto</option>
                    <?php foreach ($productos as $prod): ?>
                        <option value="<?= htmlspecialchars($prod['nombre']) ?>" data-precio="<?= $prod['precio'] ?>" data-imagen="<?= htmlspecialchars($prod['image_url']) ?>">
                            <?= htmlspecialchars($prod['nombre']) ?> - $<?= number_format($prod['precio'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="preview-imagen-edit" style="margin-top:8px;"></div>
            </div>
            <div class="form-group"><label>Talla</label><input type="text" name="talla" id="edit_talla" required></div>
            <div class="form-group"><label>Precio de compra</label><input type="number" name="precio_compra" id="edit_precio_compra" min="0" step="0.01" required></div>
            <div class="form-group"><label>Precio de venta</label><input type="number" name="precio_venta" id="edit_precio_venta" min="0" step="0.01" required></div>
            <div class="form-group"><label>Detalles</label><textarea name="detalles" id="edit_detalles"></textarea></div>
            <div class="form-group">
                <label>Estado</label>
                <select name="estado" id="edit_estado" class="estado-select" required>
                    <option value="nada">Nada</option>
                    <option value="pedido">Pedido</option>
                    <option value="enviado">Enviado</option>
                    <option value="recibido">Recibido</option>
                </select>
            </div>
            <div class="form-footer">
                <button type="button" class="btn" style="background: var(--secondary-color);" onclick="closeEditPedidoModal()">Cancelar</button>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<script>
$(document).ready(function() {
    // Inicializar Select2 en los selectores de producto
    $('.producto-select').select2({
        placeholder: 'Buscar producto...',
        allowClear: true,
        language: {
            noResults: function() {
                return "No se encontraron productos";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });

    // Actualizar precio de venta cuando se selecciona un producto
    $('.producto-select').on('change', function() {
        const precio = $(this).find(':selected').data('precio');
        if (precio) {
            const form = $(this).closest('form');
            form.find('input[name="precio_venta"]').val(precio);
        }
    });
});

function openAddPedidoModal() {
    document.getElementById('addPedidoModal').style.display = 'block';
    // Reiniciar el selector de productos
    $('.producto-select').val('').trigger('change');
}

function closeAddPedidoModal() {
    document.getElementById('addPedidoModal').style.display = 'none';
}

function validatePedidoForm() {
    return true;
}

function updateStatus(id, status) {
    fetch('pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_status&id=${id}&estado=${status}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.querySelector(`select[data-id="${id}"]`);
            select.className = `estado-select estado-${status}`;
        }
    })
    .catch(error => console.error('Error:', error));
}

function mostrarImagenProducto(select, tipo) {
    const imagen = select.options[select.selectedIndex].getAttribute('data-imagen');
    const precio = select.options[select.selectedIndex].getAttribute('data-precio');
    // Lógica para precio de compra automático
    let compra = '';
    if (precio === '799' || precio === '799.00') compra = 200;
    else if (precio === '899' || precio === '899.00') compra = 300;
    // Detectar el formulario
    let form;
    if (tipo === 'add') form = document.querySelector('#addPedidoModal form');
    else if (tipo === 'edit') form = document.querySelector('#editPedidoModal form');
    if (form && compra) {
        form.querySelector('input[name="precio_compra"]').value = compra;
    }
    // Imagen
    const preview = document.getElementById('preview-imagen-' + tipo);
    if (imagen) {
        preview.innerHTML = `<img src="../${imagen}" alt="Imagen" style="width:60px; height:60px; object-fit:cover; border-radius:8px; box-shadow:0 2px 8px #0001;">`;
    } else {
        preview.innerHTML = '<div style="width:60px; height:60px; background:#f0f0f0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#bbb; font-size:22px;">-</div>';
    }
}

function editPedido(id) {
    fetch(`pedidos.php?action=get_pedido&id=${id}`)
        .then(response => response.json())
        .then(pedido => {
            document.getElementById('edit_id').value = pedido.id;
            document.getElementById('edit_cliente').value = pedido.cliente;
            document.getElementById('edit_cantidad').value = pedido.cantidad;
            $('#edit_producto').val(pedido.producto).trigger('change');
            mostrarImagenProducto(document.getElementById('edit_producto'), 'edit');
            document.getElementById('edit_talla').value = pedido.talla;
            document.getElementById('edit_precio_compra').value = pedido.precio_compra;
            document.getElementById('edit_precio_venta').value = pedido.precio_venta;
            document.getElementById('edit_detalles').value = pedido.detalles;
            document.getElementById('edit_estado').value = pedido.estado;
            document.getElementById('editPedidoModal').style.display = 'block';
        })
        .catch(error => console.error('Error:', error));
}

function closeEditPedidoModal() {
    document.getElementById('editPedidoModal').style.display = 'none';
}

function submitEditForm(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    fetch('pedidos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEditPedidoModal();
            location.reload(); // Recargar para mostrar los cambios
        }
    })
    .catch(error => console.error('Error:', error));
    
    return false;
}

function deletePedido(id) {
    if (confirm('¿Estás seguro de que deseas eliminar este pedido?')) {
        fetch('pedidos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Recargar para mostrar los cambios
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

function toggleDetalles(id) {
    const fila = document.getElementById('detalles-' + id);
    const icon = document.getElementById('icon-detalles-' + id);
    if (fila.style.display === 'none') {
        fila.style.display = '';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        fila.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

function moverPedido(id, direction) {
    fetch('pedidos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=move&id=${id}&direction=${direction}`
    })
    .then(res => res.json())
    .then(data => { if (data.success) location.reload(); });
}

window.onclick = function(event) {
    var modal = document.getElementById('addPedidoModal');
    if (event.target == modal) {
        closeAddPedidoModal();
    }
}
</script>
</body>
</html> 