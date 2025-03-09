<?php
// Database connection
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getConnection();
    
    // Admin credentials
    $adminUsername = '1234';
    $adminPassword = password_hash('1234', PASSWORD_DEFAULT);
    
    // Check if admin already exists
    $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
    $stmt->execute([$adminUsername]);
    
    if ($stmt->fetch()) {
        echo "Admin user already exists.\n";
    } else {
        // Insert new admin
        $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
        $stmt->execute([$adminUsername, $adminPassword]);
        echo "Admin user created successfully.\n";
    }
    
} catch(PDOException $e) {
    die('Error: ' . $e->getMessage());
}