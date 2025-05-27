<?php
// Activar visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para registrar mensajes en el archivo de log
function logMessage($message, $type = 'INFO') {
    // Usar un directorio temporal en lugar de la carpeta actual
    $logFile = sys_get_temp_dir() . '/promociones.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message" . PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Registrar inicio de la ejecución
logMessage('Iniciando promociones.php');

// Verificar si el archivo de configuración existe
if (!file_exists('../config/database.php')) {
    logMessage('Error: El archivo de configuración de la base de datos no existe', 'ERROR');
    die("Error: El archivo de configuración de la base de datos no existe");
}

// Incluir el archivo de configuración
require_once '../config/database.php';

// Obtener conexión usando la función existente
try {
    $conn = getConnection();
    logMessage('Conexión a la base de datos establecida correctamente', 'INFO');
} catch (Exception $e) {
    logMessage('Error al conectar a la base de datos: ' . $e->getMessage(), 'ERROR');
    die("Error al conectar a la base de datos: " . $e->getMessage());
}

session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    logMessage('Usuario no autenticado, redirigiendo a login.php', 'WARNING');
    header('Location: login.php');
    exit;
}

// Verificar si la tabla existe
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'codigos_promocionales'");
    if ($table_check->rowCount() === 0) {
        logMessage('La tabla codigos_promocionales no existe. Intentando crearla...', 'WARNING');
        
        // Crear la tabla
        $create_table_sql = "CREATE TABLE IF NOT EXISTS codigos_promocionales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) NOT NULL UNIQUE,
            descuento DECIMAL(10,2) NOT NULL,
            tipo_descuento ENUM('porcentaje', 'fijo', 'paquete', 'auto') NOT NULL,
            fecha_inicio DATETIME NOT NULL,
            fecha_fin DATETIME NOT NULL,
            usos_maximos INT NOT NULL,
            usos_actuales INT DEFAULT 0,
            estado ENUM('activo', 'inactivo') DEFAULT 'activo',
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->exec($create_table_sql)) {
            logMessage('Tabla codigos_promocionales creada exitosamente', 'SUCCESS');
        } else {
            logMessage('Error al crear la tabla', 'ERROR');
            die("Error al crear la tabla");
        }
    } else {
        logMessage('La tabla codigos_promocionales ya existe', 'INFO');
    }
} catch (Exception $e) {
    logMessage('Excepción al verificar/crear tabla: ' . $e->getMessage(), 'ERROR');
    die("Error: " . $e->getMessage());
}

// Procesar el formulario de creación de código promocional
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        try {
            logMessage('Intentando crear nuevo código promocional', 'INFO');
            
            $codigo = $_POST['codigo'];
            $descuento = $_POST['descuento'];
            $tipo_descuento = $_POST['tipo_descuento'];
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            $usos_maximos = $_POST['usos_maximos'];
            $usos_actuales = 0;
            $estado = 'activo';

            $stmt = $conn->prepare("INSERT INTO codigos_promocionales (codigo, descuento, tipo_descuento, fecha_inicio, fecha_fin, usos_maximos, usos_actuales, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([$codigo, $descuento, $tipo_descuento, $fecha_inicio, $fecha_fin, $usos_maximos, $usos_actuales, $estado]);
            
            if ($stmt->rowCount() > 0) {
                $mensaje = "Código promocional creado exitosamente";
                logMessage('Código promocional creado: ' . $codigo, 'SUCCESS');
            } else {
                throw new Exception("No se pudo crear el código promocional");
            }
        } catch (Exception $e) {
            $error = "Error al crear el código promocional: " . $e->getMessage();
            logMessage($error, 'ERROR');
        }
    }
}

// Obtener lista de códigos promocionales
try {
    logMessage('Obteniendo lista de códigos promocionales', 'INFO');
    $stmt = $conn->query("SELECT * FROM codigos_promocionales ORDER BY fecha_creacion DESC");
    $codigos = $stmt->fetchAll();
    logMessage('Lista de códigos obtenida correctamente', 'INFO');
} catch (Exception $e) {
    $error = $e->getMessage();
    logMessage($error, 'ERROR');
    $codigos = false;
}

// Registrar fin de la ejecución
logMessage('Promociones.php cargado correctamente', 'INFO');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Jersix</title>
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
        
        /* Panel styles */
        .panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
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

        /* Form styles */
        .form-control {
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            padding: 10px;
            width: 100%;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--dark-color);
        }

        /* Button styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            gap: 8px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        /* Table styles */
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            background-color: var(--light-color);
            font-weight: 600;
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .table tr:hover {
            background-color: #f8fafc;
        }

        /* Badge styles */
        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
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
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        .switch input {display:none;}
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        input:checked + .slider {
            background-color: #28a745;
        }
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        .switch-label {
            margin-left: 12px;
            font-weight: 500;
            color: #333;
            vertical-align: middle;
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
                <li class="nav-item active">
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
                    <h2>Panel de Administración - Códigos Promocionales</h2>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <img src="../img/ICON.png" alt="User" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                        <span><?php echo $_SESSION['admin_username'] ?? 'Administrador'; ?></span>
                    </div>
                </div>
            </div>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Formulario de Creación -->
                <div class="col-md-4">
                    <div class="panel">
                        <div class="panel-header">
                            <h3 class="panel-title">
                                <i class="fas fa-plus-circle me-2"></i>
                                Crear Nuevo Código
                            </h3>
                        </div>
                        <div class="panel-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="create">
                                
                                <div class="mb-3">
                                    <label for="codigo" class="form-label">Código</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="codigo" 
                                           name="codigo" 
                                           placeholder="Ejemplo: VERANO2024" 
                                           pattern="[A-Z0-9]+"
                                           style="text-transform: uppercase;"
                                           required>
                                    <small class="text-muted" style="display: block; margin-top: 5px; color: #6c757d;">
                                        Use letras mayúsculas y números sin espacios ni caracteres especiales
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <label for="descuento" class="form-label">Descuento</label>
                                    <input type="number" class="form-control" id="descuento" name="descuento" required>
                                </div>

                                <div class="mb-3">
                                    <label for="tipo_descuento" class="form-label">Tipo de Descuento</label>
                                    <select class="form-control" id="tipo_descuento" name="tipo_descuento" required>
                                        <option value="porcentaje">Porcentaje (%)</option>
                                        <option value="fijo">Monto Fijo ($)</option>
                                        <option value="paquete">Paquete de 2 Jerseys</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                                    <input type="datetime-local" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                                </div>

                                <div class="mb-3">
                                    <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                                    <input type="datetime-local" class="form-control" id="fecha_fin" name="fecha_fin" required>
                                </div>

                                <div class="mb-3">
                                    <label for="usos_maximos" class="form-label">Usos Máximos</label>
                                    <input type="number" class="form-control" id="usos_maximos" name="usos_maximos" required>
                                </div>

                                <button type="submit" class="btn btn-primary w-100" style="margin-top: 20px;">
                                    <i class="fas fa-save"></i>
                                    Crear Código
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Lista de Códigos -->
                <div class="col-md-8">
                    <div class="panel">
                        <div class="panel-header">
                            <h3 class="panel-title">
                                <i class="fas fa-list me-2"></i>
                                Códigos Promocionales
                            </h3>
                        </div>
                        <div class="panel-body">
                            <?php if ($codigos && count($codigos) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Descuento</th>
                                                <th>Tipo</th>
                                                <th>Inicio</th>
                                                <th>Fin</th>
                                                <th>Usos</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($codigos as $codigo): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($codigo['codigo']); ?></strong></td>
                                                    <td>
                                                        <?php 
                                                        echo $codigo['tipo_descuento'] === 'porcentaje' 
                                                            ? $codigo['descuento'] . '%' 
                                                            : '$' . number_format($codigo['descuento'], 2);
                                                        ?>
                                                    </td>
                                                    <td><?php echo ucfirst($codigo['tipo_descuento']); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($codigo['fecha_inicio'])); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($codigo['fecha_fin'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo $codigo['usos_actuales'] . '/' . $codigo['usos_maximos']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $codigo['estado'] === 'activo' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($codigo['estado']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-danger" 
                                                                onclick="desactivarCodigo('<?php echo $codigo['id']; ?>')"
                                                                style="padding: 5px 10px; display: inline-flex; align-items: center; gap: 5px;">
                                                            <i class="fas fa-trash"></i>
                                                            <span>Desactivar</span>
                                                        </button>
                                                        <?php if ($codigo['estado'] === 'inactivo'): ?>
                                                        <button class="btn btn-sm btn-danger" 
                                                                onclick="eliminarCodigo('<?php echo $codigo['id']; ?>')"
                                                                style="padding: 5px 10px; display: inline-flex; align-items: center; gap: 5px; margin-left: 5px;">
                                                            <i class="fas fa-times"></i>
                                                            <span>Eliminar</span>
                                                        </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <p>No hay códigos promocionales registrados.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">
                        <i class="fas fa-tags me-2"></i>
                        Promociones Automáticas
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="promo-switch-row" style="display: flex; align-items: center; width: 100%;">
                                        <span class="switch-label" style="font-weight:600;">Promoción Hot Sale</span>
                                        <div style="display: flex; align-items: center; gap: 10px; margin-left: auto;">
                                            <label class="switch">
                                                <input type="checkbox" id="promo2jerseys" <?php 
                                                    $stmt = $conn->query("SELECT estado FROM codigos_promocionales WHERE codigo = 'AUTO2XJERSEY'");
                                                    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    $isActive = ($promo && $promo['estado'] === 'activo');
                                                    echo $isActive ? 'checked' : '';
                                                ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <span id="promo-status-label" style="min-width: 90px; font-weight: 600; color: <?php echo $isActive ? '#28a745' : '#dc3545'; ?>;">
                                                <?php echo $isActive ? 'Activa' : 'Desactivada'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.add('active');
        });

        function desactivarCodigo(id) {
            if (confirm('¿Estás seguro de que deseas desactivar este código promocional?')) {
                const formData = new FormData();
                formData.append('id', id);

                fetch('desactivar_codigo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                });
            }
        }

        function eliminarCodigo(id) {
            if (confirm('¿Estás seguro de que deseas eliminar permanentemente este código promocional? Esta acción no se puede deshacer.')) {
                const formData = new FormData();
                formData.append('id', id);

                // Mostrar indicador de carga
                const btn = event.target.closest('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
                btn.disabled = true;

                // Deshabilitar todos los botones durante la operación
                const allButtons = document.querySelectorAll('button');
                allButtons.forEach(button => {
                    if (button !== btn) {
                        button.disabled = true;
                    }
                });

                fetch('eliminar_codigo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Respuesta recibida:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Datos recibidos:', data);
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        // Restaurar los botones
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        allButtons.forEach(button => {
                            if (button !== btn) {
                                button.disabled = false;
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                    // Restaurar los botones
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    allButtons.forEach(button => {
                        if (button !== btn) {
                            button.disabled = false;
                        }
                    });
                });
            }
        }

        const promoSwitch = document.getElementById('promo2jerseys');
        const promoStatusLabel = document.getElementById('promo-status-label');
        if (promoSwitch && promoStatusLabel) {
            promoSwitch.addEventListener('change', function() {
                const isActive = this.checked;
                const formData = new FormData();
                formData.append('action', 'toggle_auto_promo');
                formData.append('estado', isActive ? 'activo' : 'inactivo');
                fetch('toggle_auto_promo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Cambia el texto y color automáticamente
                    if (isActive) {
                        promoStatusLabel.textContent = 'Activa';
                        promoStatusLabel.style.color = '#28a745';
                    } else {
                        promoStatusLabel.textContent = 'Desactivada';
                        promoStatusLabel.style.color = '#dc3545';
                    }
                    if (!data.success) {
                        // Si hay error, revertir el switch y el texto
                        promoSwitch.checked = !isActive;
                        if (!isActive) {
                            promoStatusLabel.textContent = 'Activa';
                            promoStatusLabel.style.color = '#28a745';
                        } else {
                            promoStatusLabel.textContent = 'Desactivada';
                            promoStatusLabel.style.color = '#dc3545';
                        }
                    }
                })
                .catch(() => {
                    promoSwitch.checked = !isActive;
                    if (!isActive) {
                        promoStatusLabel.textContent = 'Activa';
                        promoStatusLabel.style.color = '#28a745';
                    } else {
                        promoStatusLabel.textContent = 'Desactivada';
                        promoStatusLabel.style.color = '#dc3545';
                    }
                });
            });
        }
    </script>
</body>
</html> 