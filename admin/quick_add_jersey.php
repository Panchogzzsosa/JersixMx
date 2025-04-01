<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.html');
    exit();
}

// Database connection
require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getConnection();

        // Recoger datos del formulario
        $equipo = filter_input(INPUT_POST, 'equipo', FILTER_SANITIZE_STRING);
        $tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_STRING);
        $temporada = filter_input(INPUT_POST, 'temporada', FILTER_SANITIZE_STRING);
        $categoria = filter_input(INPUT_POST, 'categoria', FILTER_SANITIZE_STRING);
        $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
        $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

        // Validaciones básicas
        if (empty($equipo)) {
            throw new Exception('El nombre del equipo es obligatorio');
        }
        if (empty($tipo)) {
            throw new Exception('El tipo de jersey es obligatorio');
        }
        if (empty($categoria)) {
            throw new Exception('La categoría es obligatoria');
        }
        if (!$precio || $precio <= 0) {
            throw new Exception('El precio debe ser un número positivo');
        }
        if (!$stock || $stock < 0) {
            throw new Exception('El stock debe ser un número no negativo');
        }

        // Generar nombre del producto con el formato: "Equipo Tipo Temporada_Abreviada"
        // Ejemplo: "Barcelona Local 24/25"
        $temporadaAbreviada = '';
        if (!empty($temporada) && $categoria !== 'Retro') {
            // Extraer los últimos dos dígitos de cada año si el formato es YYYY-YYYY
            if (preg_match('/(\d{4})[\/\-](\d{4})/', $temporada, $matches)) {
                $primerAnio = substr($matches[1], 2, 2);
                $segundoAnio = substr($matches[2], 2, 2);
                $temporadaAbreviada = $primerAnio . '/' . $segundoAnio;
            } else {
                // Si no tiene el formato esperado, usar tal cual
                $temporadaAbreviada = $temporada;
            }
        }

        // Construir el nombre según el formato requerido
        $nombre = $equipo . ' ' . $tipo;
        if (!empty($temporadaAbreviada)) {
            $nombre .= ' ' . $temporadaAbreviada;
        }

        // Generar descripción del producto
        $descripcion = "Jersey oficial " . $tipo . " de " . $equipo;
        if (!empty($temporada) && $categoria !== 'Retro') {
            $descripcion .= " para la temporada " . $temporada;
        } elseif ($categoria === 'Retro') {
            $descripcion .= ", edición retro";
        }
        $descripcion .= ". 100% poliéster, ajuste regular.";

        // Procesar imagen
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['imagen']['type'], $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido. Solo se permiten JPG, PNG y WebP.');
            }

            if ($_FILES['imagen']['size'] > $maxSize) {
                throw new Exception('El archivo es demasiado grande. Máximo 5MB permitido.');
            }

            // Crear directorio si no existe
            $uploadDir = '../img/Jerseys/';
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new Exception('No se pudo crear el directorio de carga.');
                }
                // Asegurarse de que el directorio tenga los permisos adecuados
                chmod($uploadDir, 0777);
            } else if (!is_writable($uploadDir)) {
                // Intentar cambiar permisos si el directorio existe pero no es escribible
                chmod($uploadDir, 0777);
                
                if (!is_writable($uploadDir)) {
                    // Si aún no se puede escribir, mostrar un mensaje más detallado
                    $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
                    throw new Exception('El directorio de carga no tiene permisos de escritura. Permisos actuales: ' . $perms . '. Por favor, contacta al administrador del sistema para asignar permisos 0777 al directorio ' . $uploadDir);
                }
            }

            // Verificar permisos de escritura
            if (!is_writable($uploadDir)) {
                throw new Exception('El directorio de carga no tiene permisos de escritura.');
            }

            // Generar nombre único para el archivo
            $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $uploadPath = $uploadDir . $filename;

            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadPath)) {
                $error = error_get_last();
                throw new Exception('Error al subir la imagen: ' . ($error ? $error['message'] : 'Error desconocido'));
            }

            // Ruta relativa para guardar en la base de datos
            $imageUrl = 'img/Jerseys/' . $filename;
        } else {
            throw new Exception('Por favor seleccione una imagen.');
        }

        // Insertar en la base de datos
        $query = "INSERT INTO products (name, price, stock, category, description, image_url) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$nombre, $precio, $stock, $categoria, $descripcion, $imageUrl]);

        // Redireccionar a la página de productos con mensaje de éxito
        $_SESSION['message'] = 'Jersey "' . $nombre . '" agregado exitosamente.';
        $_SESSION['message_type'] = 'success';
        header('Location: products.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = 'error';
        header('Location: products.php');
        exit();
    }
} else {
    // Si alguien accede directamente a este archivo, redirigir a products.php
    header('Location: products.php');
    exit();
} 