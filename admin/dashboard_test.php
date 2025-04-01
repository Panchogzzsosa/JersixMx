<?php
session_start();
// Comentamos temporalmente la redirección
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: login.html');
//     exit();
// }

// Comentamos la conexión a la base de datos
// try {
//     $pdo = new PDO('mysql:host=localhost:3307;dbname=checkout', 'root', '');
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// } catch(PDOException $e) {
//     die('Error de conexión a la base de datos');
// }

// Simplemente mostrar un mensaje
echo "Si puedes ver este mensaje, el archivo PHP está funcionando correctamente.";
echo "<br>";
echo "Problema de sesión: " . (isset($_SESSION['admin_id']) ? "No" : "Sí, no hay sesión activa");
?> 