<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.html');
    exit();
}

// Función para verificar y corregir permisos de directorio
function checkAndFixDirectory($path, $createIfNotExists = true) {
    $result = [
        'path' => $path,
        'exists' => false,
        'writable' => false,
        'fixed' => false,
        'permissions' => '',
        'error' => ''
    ];
    
    try {
        // Si la ruta no existe
        if (!file_exists($path)) {
            if ($createIfNotExists) {
                if (!mkdir($path, 0777, true)) {
                    $result['error'] = "No se pudo crear el directorio";
                    return $result;
                }
                chmod($path, 0777);
                $result['exists'] = true;
                $result['fixed'] = true;
                $result['writable'] = is_writable($path);
            } else {
                $result['error'] = "El directorio no existe";
                return $result;
            }
        } else {
            $result['exists'] = true;
            $result['writable'] = is_writable($path);
            
            // Intentar arreglar permisos si no es escribible
            if (!$result['writable']) {
                chmod($path, 0777);
                $result['writable'] = is_writable($path);
                $result['fixed'] = $result['writable'];
            }
        }
        
        // Obtener permisos actuales
        $result['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Directorios a verificar
$directories = [
    '../img/',
    '../img/Jerseys/',
    '../temp/',
    '../temp/uploads/',
    '../img/Retro/',
    '../img/LoMasVendido/'
];

// Verificar y arreglar cada directorio
$results = [];
foreach ($directories as $dir) {
    $results[] = checkAndFixDirectory($dir);
}

// Verificar si se solicitó una corrección automática
$autoFixed = false;
if (isset($_POST['auto_fix']) && $_POST['auto_fix'] === 'yes') {
    $autoFixed = true;
    // Este código se ejecutará como el usuario del servidor web (normalmente www-data)
    // Intentar cambiar permisos de directorios problemáticos
    foreach ($results as &$result) {
        if (!$result['writable'] && $result['exists']) {
            chmod($result['path'], 0777);
            $result['writable'] = is_writable($result['path']);
            $result['fixed'] = $result['writable'];
            $result['permissions'] = substr(sprintf('%o', fileperms($result['path'])), -4);
        }
    }
}

// Incluir también información del usuario que ejecuta PHP
$serverInfo = [
    'user' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'No disponible',
    'group' => function_exists('posix_getgrgid') ? posix_getgrgid(posix_getegid())['name'] : 'No disponible',
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
    'is_windows' => DIRECTORY_SEPARATOR === '\\'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Permisos - Jersix</title>
    <link rel="stylesheet" href="../Css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <style>
        .permissions-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .directory-status {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 5px;
        }
        
        .directory-status.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        .directory-status.warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
        }
        
        .directory-status.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        
        .server-info {
            background-color: #e2f0fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #b8daff;
        }
        
        .terminal-commands {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px solid #dee2e6;
            font-family: monospace;
        }
        
        .terminal-command {
            background-color: #343a40;
            color: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
            overflow-x: auto;
        }
        
        .fix-button {
            background-color: #ffc107;
            color: #212529;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 10px;
        }
        
        .fix-button:hover {
            background-color: #e0a800;
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
                <h1>Verificación de Permisos</h1>
                <div class="admin-controls">
                    <a href="products.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver a Productos</a>
                </div>
            </div>
            
            <div class="server-info">
                <h3><i class="fas fa-server"></i> Información del Servidor</h3>
                <p><strong>Usuario de PHP:</strong> <?php echo htmlspecialchars($serverInfo['user']); ?></p>
                <p><strong>Grupo de PHP:</strong> <?php echo htmlspecialchars($serverInfo['group']); ?></p>
                <p><strong>Versión de PHP:</strong> <?php echo htmlspecialchars($serverInfo['php_version']); ?></p>
                <p><strong>Software del Servidor:</strong> <?php echo htmlspecialchars($serverInfo['server_software']); ?></p>
                <p><strong>Sistema Operativo:</strong> <?php echo $serverInfo['is_windows'] ? 'Windows' : 'Unix/Linux/MacOS'; ?></p>
            </div>
            
            <div class="permissions-container">
                <h3><i class="fas fa-folder-open"></i> Estado de Directorios</h3>
                
                <?php if ($autoFixed): ?>
                <div class="directory-status success">
                    <p><i class="fas fa-check-circle"></i> <strong>Se ha intentado corregir automáticamente los permisos de los directorios.</strong></p>
                </div>
                <?php endif; ?>
                
                <?php foreach ($results as $result): ?>
                    <div class="directory-status <?php echo (!$result['exists'] || !$result['writable']) ? 'error' : 'success'; ?>">
                        <h4><?php echo htmlspecialchars($result['path']); ?></h4>
                        <p><strong>Existe:</strong> <?php echo $result['exists'] ? 'Sí' : 'No'; ?></p>
                        <p><strong>Escribible:</strong> <?php echo $result['writable'] ? 'Sí' : 'No'; ?></p>
                        <?php if ($result['exists']): ?>
                            <p><strong>Permisos:</strong> <?php echo htmlspecialchars($result['permissions']); ?></p>
                        <?php endif; ?>
                        <?php if ($result['error']): ?>
                            <p><strong>Error:</strong> <?php echo htmlspecialchars($result['error']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$autoFixed): ?>
                <form method="post">
                    <input type="hidden" name="auto_fix" value="yes">
                    <button type="submit" class="fix-button"><i class="fas fa-wrench"></i> Intentar Corregir Automáticamente</button>
                </form>
                <?php endif; ?>
                
                <div class="terminal-commands">
                    <h3><i class="fas fa-terminal"></i> Comandos para Corregir Manualmente</h3>
                    <p>Si la corrección automática no funciona, puedes ejecutar estos comandos desde la terminal del servidor:</p>
                    
                    <?php foreach ($directories as $dir): ?>
                    <div class="terminal-command">
                        chmod 777 <?php echo htmlspecialchars(realpath($dir) ?: $dir); ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <p><strong>Nota:</strong> Es posible que necesites acceso SSH o FTP al servidor para ejecutar estos comandos.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 