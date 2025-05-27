<?php
// Activar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT estado FROM codigos_promocionales WHERE codigo = 'AUTO2XJERSEY' LIMIT 1");
    $stmt->execute();
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($promo) {
        echo json_encode(['success' => true, 'estado' => $promo['estado']]);
    } else {
        echo json_encode(['success' => true, 'estado' => 'inactivo']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 