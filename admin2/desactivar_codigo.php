<?php
// Incluir archivo de configuración
require_once '../config/database.php';

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
    exit;
}

// Obtener ID del código promocional
$id = $_POST['id'];

try {
    // Obtener conexión a la base de datos
    $conn = getConnection();
    
    // Verificar si el código existe
    $stmt = $conn->prepare("SELECT codigo FROM codigos_promocionales WHERE id = ?");
    $stmt->execute([$id]);
    $codigo = $stmt->fetchColumn();
    
    if (!$codigo) {
        echo json_encode(['success' => false, 'message' => 'Código promocional no encontrado']);
        exit;
    }
    
    // Desactivar el código (cambiar estado a inactivo)
    $stmt = $conn->prepare("UPDATE codigos_promocionales SET estado = 'inactivo' WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Código promocional desactivado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo desactivar el código promocional']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} 