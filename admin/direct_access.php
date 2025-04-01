<?php
// Iniciar sesión si aún no está iniciada
session_start();

// Establecer las variables de sesión necesarias para simular un inicio de sesión exitoso
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'admin'; // O el nombre que prefieras
$_SESSION['admin_id'] = 1; // O el ID que corresponda en tu sistema

// Redirigir al dashboard
header('Location: dashboard.php'); // Ajusta esta URL a la correcta de tu dashboard
exit;
?> 