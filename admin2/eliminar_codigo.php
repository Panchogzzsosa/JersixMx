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
    
    // Verificar si el código existe y si está inactivo (solo permitir eliminar códigos inactivos)
    $stmt = $conn->prepare("SELECT codigo, estado FROM codigos_promocionales WHERE id = ?");
    $stmt->execute([$id]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'Código promocional no encontrado']);
        exit;
    }
    
    if ($promo['estado'] !== 'inactivo') {
        echo json_encode(['success' => false, 'message' => 'Solo se pueden eliminar códigos inactivos']);
        exit;
    }
    
    // Eliminar el código
    $stmt = $conn->prepare("DELETE FROM codigos_promocionales WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Código promocional eliminado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo eliminar el código promocional']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} 