<?php
session_start();

// Mostrar errores durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Si ya hay una sesión activa, redirigir al dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Verificar la estructura de la tabla admins
try {
    // Comprobar si la tabla admins existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'admins'")->rowCount() > 0;
    
    // Si la tabla no existe, crearla con la estructura correcta
    if (!$tableExists) {
        $pdo->exec("CREATE TABLE admins (
            admin_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            admin_name VARCHAR(100),
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        echo "<div style='background-color:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:4px;'>
            Tabla 'admins' creada correctamente.</div>";
    } else {
        // Si la tabla existe, verificar si tiene la columna admin_name
        $hasNameColumn = $pdo->query("SHOW COLUMNS FROM admins LIKE 'admin_name'")->rowCount() > 0;
        
        if (!$hasNameColumn) {
            // Añadir columna admin_name si no existe
            $pdo->exec("ALTER TABLE admins ADD COLUMN admin_name VARCHAR(100) AFTER password");
            echo "<div style='background-color:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:4px;'>
                Columna 'admin_name' añadida a la tabla 'admins'.</div>";
        }
    }
    
    // Verificar si hay administradores en la tabla
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    
    if ($adminCount == 0) {
        // Crear administrador por defecto con la nueva contraseña
        $stmt = $pdo->prepare("INSERT INTO admins (username, password, admin_name, email) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', password_hash('Jersix1414', PASSWORD_DEFAULT), 'Administrador', 'admin@jersix.mx']);
        
        echo "<div style='background-color:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:4px;'>
            Administrador por defecto creado correctamente.</div>";
    }
} catch(PDOException $e) {
    echo "<div style='background-color:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;'>
        Error en la base de datos: " . $e->getMessage() . "</div>";
}

// Procesar el formulario cuando se envía
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Debes introducir usuario y contraseña';
    } else {
        try {
            // Verificar las credenciales
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Para pruebas: acceso directo con credenciales predeterminadas (actualizada)
            if (($username === 'admin' && $password === 'Jersix1414') || 
                ($admin && password_verify($password, $admin['password']))) {
                
                // Inicio de sesión exitoso
                $_SESSION['admin_id'] = $admin['admin_id'] ?? 1;
                $_SESSION['admin_name'] = $admin['admin_name'] ?? $admin['username'] ?? 'Administrador';
                $_SESSION['admin_logged_in'] = true;
                
                // Redirigir al dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        } catch(PDOException $e) {
            $error = 'Error al verificar credenciales: ' . $e->getMessage();
        }
    }
}

// Mostrar administradores para desarrollo
try {
    $stmt = $pdo->query("SELECT admin_id, username, admin_name, email FROM admins");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($admins)) {
        $adminsList = "<div style='background-color:#f8f9fa; padding:10px; margin-bottom:15px; border-radius:4px;'><h4>Administradores existentes:</h4><ul>";
        foreach ($admins as $admin) {
            $adminsList .= "<li>" . htmlspecialchars($admin['username']) . " (ID: " . $admin['admin_id'] . ")</li>";
        }
        $adminsList .= "</ul></div>";
    }
} catch(PDOException $e) {
    // Ignorar error
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Panel de Administración</title>
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
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: var(--secondary-color);
        }
        
        .login-form .form-group {
            margin-bottom: 20px;
        }
        
        .login-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .login-form .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: var(--border-radius);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }
        
        .login-form .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .login-form .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .login-form .btn:hover {
            background-color: var(--primary-dark);
        }
        
        .error-message {
            padding: 12px;
            background-color: rgba(220,53,69,0.1);
            color: var(--danger-color);
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo img {
            height: 60px;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: var(--secondary-color);
            font-size: 12px;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            transition: color 0.2s;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="../img/LogoNav.png" alt="Jersix Logo">
        </div>
        
        <div class="login-header">
            <h1>Panel de Administración</h1>
            <p>Introduce tus credenciales para acceder</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($adminsList)): ?>
            <?php /* echo $adminsList; */ ?>
        <?php endif; ?>
        
        <form method="post" class="login-form">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Ingresa tu usuario" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Ingresa tu contraseña" required>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </span>
                </div>
            </div>
            
            <button type="submit" class="btn">Iniciar Sesión</button>
        </form>
        
        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> Jersix.mx - Todos los derechos reservados
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>