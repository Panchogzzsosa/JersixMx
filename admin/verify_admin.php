<?php
session_start();

// Database connection

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']));
}

// Get POST data
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    die(json_encode(['success' => false, 'message' => 'Por favor complete todos los campos']));
}

// Query to check admin credentials
$stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = ?');
$stmt->execute([$username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify credentials
if ($admin && password_verify($password, $admin['password'])) {
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']);
}