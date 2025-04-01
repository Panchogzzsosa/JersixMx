<?php
// Database configuration
$host = 'localhost';
$dbname = 'checkout';
$username = 'root';
$password = '';
$port = 3307;

function getConnection() {
    global $host, $dbname, $username, $password, $port;
    
    try {
        // Set connection options with proper error handling
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT => false // Disable persistent connections to avoid issues
        ];
        
        // Construct DSN with explicit port
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        
        // Attempt to create connection
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Test the connection with timeout
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        $stmt = $pdo->query('SELECT 1');
        if (!$stmt) {
            throw new PDOException("Failed to execute test query");
        }
        
        // Set timezone to match MySQL server
        $pdo->exec("SET time_zone = '+00:00'");
        
        return $pdo;
        
    } catch(PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        
        // Check for specific error conditions
        $error_message = $e->getMessage();
        if (strpos($error_message, 'Connection refused') !== false || 
            strpos($error_message, 'No connection') !== false || 
            strpos($error_message, 'Access denied') !== false) {
            throw new Exception("Error de conexión: Por favor, verifique que MySQL esté ejecutándose en el puerto $port");
        }
        
        if (strpos($error_message, 'Unknown database') !== false) {
            throw new Exception("Error de base de datos: La base de datos '$dbname' no existe");
        }
        
        throw new Exception("Error de conexión a la base de datos. Por favor, contacte al administrador del sistema.");
    }
}

// Create a global PDO instance for backward compatibility
try {
    $pdo = getConnection();
} catch (Exception $e) {
    // Log the error but don't expose it directly
    error_log($e->getMessage());
    // Don't throw here to maintain backward compatibility
}