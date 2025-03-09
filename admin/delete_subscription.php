<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit();
}

// Database connection
$pdo = require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost:3307;dbname=checkout', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Delete the subscription
    $stmt = $pdo->prepare('DELETE FROM newsletter WHERE id = ?');
    $success = $stmt->execute([$data['id']]);

    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
}