<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.html');
    exit();
}

// Database connection

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO('mysql:host=localhost:3307;dbname=checkout', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Validate and sanitize input
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
        $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

        // Validate image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido. Solo se permiten JPG, PNG y WebP.');
            }

            if ($_FILES['image']['size'] > $maxSize) {
                throw new Exception('El archivo es demasiado grande. Máximo 5MB permitido.');
            }

            // Check if upload directory exists, if not create it
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

            // Check if directory is writable
            if (!is_writable($uploadDir)) {
                throw new Exception('El directorio de carga no tiene permisos de escritura.');
            }

            // Generate unique filename
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $uploadPath = $uploadDir . $filename;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $error = error_get_last();
                throw new Exception('Error al subir la imagen: ' . ($error ? $error['message'] : 'Unknown error'));
            }

            // Insert product into database
            $query = "INSERT INTO products (name, price, stock, category, description, image_url) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$name, $price, $stock, $category, $description, 'img/Jerseys/' . $filename]);

            $message = 'Producto agregado exitosamente';
            $messageType = 'success';
        } else {
            throw new Exception('Por favor seleccione una imagen.');
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Nuevo Producto - Jersix</title>
    <link rel="stylesheet" href="../Css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <style>
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-group textarea {
            height: 150px;
            resize: vertical;
        }

        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Jersix.mx</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a>
                </li>
                <li class="nav-item active">
                    <a href="products.php"><i class="fas fa-shopping-bag"></i> Productos</a>
                </li>
                <li class="nav-item">
                    <a href="orders.php"><i class="fas fa-shopping-cart"></i> Pedidos</a>
                </li>
                <li class="nav-item">
                    <a href="customers.php"><i class="fas fa-users"></i> Clientes</a>
                </li>
                <li class="nav-item">
                    <a href="newsletter.php"><i class="fas fa-envelope"></i> Correos</a>
                </li>
                <li class="nav-item">
                    <a href="csv_generator.php"><i class="fas fa-file-csv"></i> Generador CSV</a>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Agregar Nuevo Producto</h1>
                <div class="admin-controls">
                    <a href="products.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver a Productos</a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="form-container">
                <form action="add_product.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Nombre del Producto</label>
                        <input type="text" id="name" name="name" required placeholder="Ej: Barcelona Local 24/25">
                        <p class="help-text">Formato recomendado: Equipo + Tipo + Temporada (Ej: Barcelona Local 24/25)</p>
                    </div>

                    <div class="form-group">
                        <label for="price">Precio</label>
                        <input type="number" id="price" name="price" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="stock">Stock</label>
                        <input type="number" id="stock" name="stock" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Categoría</label>
                        <select id="category" name="category" required>
                            <option value="">Seleccionar categoría</option>
                            <option value="Equipos">Equipos</option>
                            <option value="Retro">Retro</option>
                            <option value="Selecciones">Selecciones</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Descripción</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="image">Imagen del Producto</label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Agregar Producto
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>