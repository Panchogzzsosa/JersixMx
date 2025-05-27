<?php
// Configuración para mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Prueba de Configuración de PayPal</h1>";
echo "<p>Este script prueba la conexión a la API de PayPal y verifica la configuración del Checkout.</p>";

// Detectar entorno
$server_name = $_SERVER['SERVER_NAME'] ?? 'desconocido';
$isLocalhost = ($server_name === 'localhost' || strpos($server_name, '127.0.0.1') !== false);

echo "<h2>Información del Entorno</h2>";
echo "<ul>";
echo "<li>Servidor: " . htmlspecialchars($server_name) . "</li>";
echo "<li>Entorno detectado: " . ($isLocalhost ? "Localhost (Desarrollo)" : "Producción (cPanel)") . "</li>";
echo "<li>Versión PHP: " . phpversion() . "</li>";
echo "</ul>";

// Verificar si el archivo process_order.php es accesible
echo "<h2>Prueba de Acceso a process_order.php</h2>";
$process_order_path = __DIR__ . '/process_order.php';
if (file_exists($process_order_path)) {
    echo "<p style='color: green;'>✓ El archivo process_order.php existe en el servidor.</p>";
    echo "<p>Tamaño del archivo: " . filesize($process_order_path) . " bytes</p>";
    echo "<p>Última modificación: " . date("Y-m-d H:i:s", filemtime($process_order_path)) . "</p>";
    
    // Verificar permisos
    $perms = fileperms($process_order_path);
    $perms_str = sprintf('%o', $perms);
    echo "<p>Permisos: " . $perms_str . "</p>";
} else {
    echo "<p style='color: red;'>✗ El archivo process_order.php NO existe en la ruta esperada.</p>";
}

// Verificar si la carpeta logs existe y es escribible
$logs_path = __DIR__ . '/logs';
echo "<h2>Prueba de Carpeta de Logs</h2>";
if (!file_exists($logs_path)) {
    echo "<p>La carpeta de logs no existe. Intentando crearla...</p>";
    if (mkdir($logs_path, 0777, true)) {
        echo "<p style='color: green;'>✓ Carpeta de logs creada correctamente.</p>";
    } else {
        echo "<p style='color: red;'>✗ No se pudo crear la carpeta de logs.</p>";
    }
} else {
    echo "<p style='color: green;'>✓ La carpeta de logs existe.</p>";
    if (is_writable($logs_path)) {
        echo "<p style='color: green;'>✓ La carpeta de logs tiene permisos de escritura.</p>";
        
        // Intentar escribir un archivo de prueba
        $test_log = $logs_path . '/test_' . time() . '.log';
        if (file_put_contents($test_log, "Test log entry - " . date("Y-m-d H:i:s"))) {
            echo "<p style='color: green;'>✓ Se pudo escribir un archivo de prueba en la carpeta de logs.</p>";
            // Eliminar el archivo de prueba
            unlink($test_log);
        } else {
            echo "<p style='color: red;'>✗ No se pudo escribir un archivo de prueba en la carpeta de logs.</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ La carpeta de logs NO tiene permisos de escritura.</p>";
    }
}

// Probar conexión a la base de datos
echo "<h2>Prueba de Conexión a Base de Datos</h2>";
try {
    require_once __DIR__ . '/config/database.php';
    $pdo = getConnection();
    echo "<p style='color: green;'>✓ Conexión a la base de datos exitosa.</p>";
    
    // Verificar tablas esenciales
    $requiredTables = ['orders', 'order_items', 'products'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            $missingTables[] = $table;
        }
    }
    
    if (empty($missingTables)) {
        echo "<p style='color: green;'>✓ Todas las tablas requeridas existen.</p>";
    } else {
        echo "<p style='color: red;'>✗ Faltan las siguientes tablas: " . implode(", ", $missingTables) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error de conexión a la base de datos: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Prueba de solicitud HTTP
echo "<h2>Prueba de Solicitud HTTP</h2>";
echo "<p>Haciendo una solicitud de prueba a process_order.php...</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . "/process_order.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "test=1");
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

curl_close($ch);

echo "<p>Código de respuesta HTTP: " . $http_code . "</p>";

if ($http_code >= 200 && $http_code < 300) {
    echo "<p style='color: green;'>✓ La solicitud HTTP fue exitosa.</p>";
} else {
    echo "<p style='color: red;'>✗ La solicitud HTTP falló con el código " . $http_code . "</p>";
}

if (!empty($curl_error)) {
    echo "<p style='color: red;'>Error de cURL: " . htmlspecialchars($curl_error) . "</p>";
}

echo "<p>Respuesta:</p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Verificar la configuración JavaScript de PayPal
echo "<h2>Prueba de Configuración de PayPal</h2>";

// Obtener el contenido del archivo checkout.js
$checkout_js_path = __DIR__ . '/Js/checkout.js';
if (file_exists($checkout_js_path)) {
    echo "<p style='color: green;'>✓ El archivo checkout.js existe.</p>";
    
    // Buscar la configuración de PayPal en el archivo
    $checkout_js = file_get_contents($checkout_js_path);
    
    if (strpos($checkout_js, 'paypal.Buttons') !== false) {
        echo "<p style='color: green;'>✓ Se encontró la configuración de PayPal en checkout.js</p>";
    } else {
        echo "<p style='color: red;'>✗ No se encontró la configuración de PayPal en checkout.js</p>";
    }
    
    // Verificar las URL de fetch
    if (strpos($checkout_js, "fetch('process_order.php'") !== false) {
        echo "<p style='color: green;'>✓ Se encontró la llamada a process_order.php</p>";
    } else {
        echo "<p style='color: red;'>✗ No se encontró la llamada a process_order.php. Asegúrate de que la URL es correcta.</p>";
    }
} else {
    echo "<p style='color: red;'>✗ El archivo checkout.js NO existe en la ruta esperada.</p>";
}

echo "<h2>Verificación de Cross-Origin</h2>";
echo "<p>Verificando cabeceras CORS para determinar si hay problemas de origen cruzado...</p>";

// Verificar si el archivo .htaccess existe
$htaccess_path = __DIR__ . '/.htaccess';
if (file_exists($htaccess_path)) {
    echo "<p style='color: green;'>✓ El archivo .htaccess existe.</p>";
    
    // Verificar si tiene reglas CORS
    $htaccess_content = file_get_contents($htaccess_path);
    
    if (strpos($htaccess_content, 'Access-Control-Allow') !== false) {
        echo "<p style='color: green;'>✓ El archivo .htaccess contiene reglas CORS.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ El archivo .htaccess no contiene reglas CORS explícitas.</p>";
        echo "<p>Considera agregar las siguientes reglas al archivo .htaccess:</p>";
        echo "<pre>
# Habilitar CORS
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin *
    Header set Access-Control-Allow-Methods 'GET, POST, OPTIONS'
    Header set Access-Control-Allow-Headers 'Content-Type, Authorization'
</IfModule>
        </pre>";
    }
} else {
    echo "<p style='color: orange;'>⚠ El archivo .htaccess no existe. Considera crear uno con reglas CORS.</p>";
}

// Instrucciones para solucionar problemas comunes
echo "<h2>Soluciones Comunes para Problemas de PayPal</h2>";
echo "<ol>";
echo "<li>Asegúrate de que la URL del script process_order.php sea accesible desde la web.</li>";
echo "<li>Verifica que las credenciales de la base de datos estén correctamente configuradas.</li>";
echo "<li>Si hay problemas de CORS, añade las cabeceras apropiadas en el archivo .htaccess.</li>";
echo "<li>Verifica que la carpeta 'logs' tenga permisos de escritura (chmod 777).</li>";
echo "<li>Asegúrate de que el script fetch() esté enviando los datos del formulario correctamente.</li>";
echo "<li>Si el error persiste, revisa los logs de error de PHP en tu panel de cPanel.</li>";
echo "</ol>";

// Añadir botón para probar nuevamente
echo "<p><a href='paypal_test.php' class='button' style='display: inline-block; padding: 10px 20px; background-color: #0070ba; color: white; text-decoration: none; border-radius: 5px;'>Ejecutar prueba nuevamente</a></p>";
echo "<p><a href='db_test.php' class='button' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Ir a prueba de base de datos</a></p>";
echo "<p><a href='index.php' class='button' style='display: inline-block; padding: 10px 20px; background-color: #555; color: white; text-decoration: none; border-radius: 5px;'>Volver al inicio</a></p>"; 