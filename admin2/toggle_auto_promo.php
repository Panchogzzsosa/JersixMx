<?php
// Activar visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir configuración de base de datos
require_once '../config/database.php';

// Verificar si el usuario está logueado
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    $conn = getConnection();
    
    // Verificar si existe la promoción automática
    $stmt = $conn->prepare("SELECT id FROM codigos_promocionales WHERE codigo = 'AUTO2XJERSEY'");
    $stmt->execute();
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $estado = $_POST['estado'] === 'activo' ? 'activo' : 'inactivo';
    
    if ($promo) {
        // Actualizar estado existente
        $stmt = $conn->prepare("UPDATE codigos_promocionales SET estado = ? WHERE codigo = 'AUTO2XJERSEY'");
        $stmt->execute([$estado]);
    } else {
        // Crear nueva promoción automática
        $stmt = $conn->prepare("
            INSERT INTO codigos_promocionales 
            (codigo, descuento, tipo_descuento, fecha_inicio, fecha_fin, usos_maximos, usos_actuales, estado) 
            VALUES 
            ('AUTO2XJERSEY', 598.00, 'auto', NOW(), '2099-12-31 23:59:59', 0, 0, ?)
        ");
        $stmt->execute([$estado]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Promoción ' . ($estado === 'activo' ? 'activada' : 'desactivada') . ' correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 