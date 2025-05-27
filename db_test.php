<?php
// Configuración para mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Prueba de Conexión a Base de Datos</h1>";
echo "<p>Este script prueba la conexión a la base de datos y muestra información detallada para solucionar problemas.</p>";

// Detectar entorno
$server_name = $_SERVER['SERVER_NAME'] ?? 'desconocido';
$isLocalhost = ($server_name === 'localhost' || strpos($server_name, '127.0.0.1') !== false);

echo "<h2>Información del Entorno</h2>";
echo "<ul>";
echo "<li>Servidor: " . htmlspecialchars($server_name) . "</li>";
echo "<li>Entorno detectado: " . ($isLocalhost ? "Localhost (Desarrollo)" : "Producción (cPanel)") . "</li>";
echo "<li>Versión PHP: " . phpversion() . "</li>";
echo "<li>Extensiones PHP cargadas: " . implode(', ', get_loaded_extensions()) . "</li>";
echo "</ul>";

// Cargar configuración de base de datos
require_once __DIR__ . '/config/database.php';

echo "<h2>Configuración de Base de Datos</h2>";
echo "<ul>";
echo "<li>Host: " . htmlspecialchars($host) . "</li>";
echo "<li>Puerto: " . htmlspecialchars($port) . "</li>";
echo "<li>Base de datos: " . htmlspecialchars($dbname) . "</li>";
echo "<li>Usuario: " . htmlspecialchars($username) . "</li>";
echo "<li>Contraseña: " . (empty($password) ? "Vacía" : "Configurada") . "</li>";
echo "</ul>";

// Probar conexión usando mysqli para información adicional
echo "<h2>Prueba de Conexión con MySQLi</h2>";
try {
    $mysqli = new mysqli($host, $username, $password, $dbname, $port);
    
    if ($mysqli->connect_error) {
        echo "<p style='color: red;'>Error de conexión con MySQLi: " . htmlspecialchars($mysqli->connect_error) . "</p>";
    } else {
        echo "<p style='color: green;'>Conexión con MySQLi exitosa.</p>";
        
        // Mostrar información adicional
        echo "<ul>";
        echo "<li>Versión MySQL: " . htmlspecialchars($mysqli->server_info) . "</li>";
        echo "<li>Estadísticas de conexión: " . htmlspecialchars($mysqli->host_info) . "</li>";
        
        // Probar una consulta simple
        $result = $mysqli->query("SHOW TABLES");
        if ($result) {
            echo "<li>Tablas en la base de datos:</li>";
            echo "<ul>";
            while ($row = $result->fetch_row()) {
                echo "<li>" . htmlspecialchars($row[0]) . "</li>";
            }
            echo "</ul>";
            $result->free();
        } else {
            echo "<li style='color: red;'>Error al consultar tablas: " . htmlspecialchars($mysqli->error) . "</li>";
        }
        
        echo "</ul>";
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Excepción MySQLi: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Probar conexión usando PDO
echo "<h2>Prueba de Conexión con PDO</h2>";
try {
    $pdo = getConnection();
    echo "<p style='color: green;'>Conexión con PDO exitosa.</p>";
    
    // Probar una consulta simple
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>PDO pudo conectarse y ejecutar una consulta.</p>";
    
    // Probar tablas específicas usadas en checkout
    $requiredTables = ['orders', 'order_items', 'products'];
    echo "<h3>Verificación de Tablas Requeridas</h3>";
    echo "<ul>";
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            echo "<li style='color: green;'>" . htmlspecialchars($table) . ": Existe</li>";
            
            // Mostrar estructura de la tabla
            $structStmt = $pdo->query("DESCRIBE " . $table);
            $structure = $structStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<ul>";
            foreach ($structure as $column) {
                echo "<li>" . htmlspecialchars($column['Field']) . " - " . htmlspecialchars($column['Type']) . "</li>";
            }
            echo "</ul>";
            
        } else {
            echo "<li style='color: red;'>" . htmlspecialchars($table) . ": No existe</li>";
        }
    }
    
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error de conexión con PDO: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Detalles del error:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// Instrucciones para solucionar problemas comunes
echo "<h2>Soluciones Comunes</h2>";
echo "<ol>";
echo "<li>Si estás en cPanel y ves un error de conexión, asegúrate de que las credenciales en database.php sean correctas.</li>";
echo "<li>El puerto 3306 es el estándar en cPanel, mientras que XAMPP podría usar 3307.</li>";
echo "<li>Verifica que el usuario de la base de datos tenga los permisos necesarios.</li>";
echo "<li>Si falta alguna tabla, deberías importar la estructura de la base de datos desde un respaldo.</li>";
echo "</ol>";

echo "<p><a href='index.php'>Volver al inicio</a></p>"; 