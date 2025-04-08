<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si se proporcionó un código
if (!isset($_GET['code']) || empty($_GET['code'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Código de gift card no proporcionado']);
    exit;
}

$code = trim($_GET['code']);



// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

try {
    // Conectar a la base de datos
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si existe la tabla de transacciones
    $tableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_transactions'")->rowCount() > 0;
    
    if (!$tableExists) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'La tabla de transacciones no existe']);
        exit;
    }
    
    // Obtener información de la Gift Card
    $redemptionStmt = $pdo->prepare("
        SELECT * FROM giftcard_redemptions WHERE code = ?
    ");
    $redemptionStmt->execute([$code]);
    $redemption = $redemptionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$redemption) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Gift card no encontrada']);
        exit;
    }
    
    // Obtener todas las transacciones
    $transactionStmt = $pdo->prepare("
        SELECT * FROM giftcard_transactions 
        WHERE code = ? 
        ORDER BY transaction_date DESC
    ");
    $transactionStmt->execute([$code]);
    $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Devolver los datos
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'redemption' => $redemption,
        'transactions' => $transactions
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al obtener transacciones: ' . $e->getMessage()]);
} 