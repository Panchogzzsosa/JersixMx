<?php
session_start();

// Mostrar errores durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar inicio de sesión (comentado por ahora para facilitar pruebas)
/*
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
*/

// Verificar que se ha proporcionado un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: products.php?error=no_id');
    exit();
}

$product_id = $_GET['id'];

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Obtener datos del producto
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: products.php?error=not_found');
        exit();
    }
} catch(PDOException $e) {
    die('Error al obtener datos del producto: ' . $e->getMessage());
}

// Obtener imágenes adicionales del producto
try {
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$product_id]);
    $additional_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $additional_images = [];
    // Intentar crear la tabla si no existe
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_images (
                image_id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                image_url VARCHAR(255) NOT NULL,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
            )
        ");
    } catch(PDOException $ex) {
        // Ignorar error si ocurre
    }
}

// Obtener categorías para el select
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $categories = [];
}

// Inicializar variables de mensajes
$success_message = null;
$error_message = null;

// Procesar formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    $category = $_POST['category'] ?? '';
    
    // Validar datos
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "El nombre del producto es obligatorio";
    }
    
    if (!is_numeric($price) || $price < 0) {
        $errors[] = "El precio debe ser un número positivo";
    }
    
    if (!is_numeric($stock) || $stock < 0) {
        $errors[] = "El stock debe ser un número positivo";
    }
    
    // Si hay errores, mostrarlos
    if (!empty($errors)) {
        $error_message = implode("<br>", $errors);
    } else {
        // Procesar la imagen si se ha subido una nueva
        $image_url = $product['image_url']; // Mantener la imagen actual por defecto
        
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = '../uploads/products/';
            
            // Crear directorio si no existe
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;
            
            // Verificar que sea una imagen válida
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    // Si existe una imagen anterior, eliminarla (excepto la imagen por defecto)
                    if (!empty($product['image_url']) && strpos($product['image_url'], 'default.jpg') === false && file_exists('../' . $product['image_url'])) {
                        unlink('../' . $product['image_url']);
                    }
                    
                    $image_url = 'uploads/products/' . $file_name;
                } else {
                    $error_message = "Error al subir la imagen";
                }
            } else {
                $error_message = "Formato de imagen no permitido. Use: JPG, JPEG, PNG o GIF";
            }
        }
        
        // Si no hay errores con la imagen, actualizar el producto
        if (!isset($error_message)) {
            try {
                // Verificar si la columna status existe en la tabla products
                $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'status'");
                $statusColumnExists = ($stmt->rowCount() > 0);
                
                if ($statusColumnExists) {
                    // Si la columna status existe, incluirla en la actualización
                    $status = isset($_POST['status']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET name = ?, description = ?, price = ?, stock = ?, category = ?, status = ?, image_url = ?
                        WHERE product_id = ?
                    ");
                    
                    $stmt->execute([
                        $name,
                        $description,
                        $price,
                        $stock,
                        $category,
                        $status,
                        $image_url,
                        $product_id
                    ]);
                } else {
                    // Si la columna status no existe, crearla primero
                    $pdo->exec("ALTER TABLE products ADD COLUMN status TINYINT(1) DEFAULT 1");
                    
                    // Ahora podemos usar la columna status en la actualización
                    $status = isset($_POST['status']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET name = ?, description = ?, price = ?, stock = ?, category = ?, status = ?, image_url = ?
                        WHERE product_id = ?
                    ");
                    
                    $stmt->execute([
                        $name,
                        $description,
                        $price,
                        $stock,
                        $category,
                        $status,
                        $image_url,
                        $product_id
                    ]);
                    
                    $success_message = "Producto actualizado correctamente. También se ha agregado la columna 'status' a la tabla.";
                }
                
                if (!isset($success_message)) {
                    $success_message = "Producto actualizado correctamente";
                }
                
                // Actualizar los datos del producto para mostrar los cambios
                $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch(PDOException $e) {
                $error_message = "Error al actualizar el producto: " . $e->getMessage();
            }
        }
    }

    // Procesar imágenes adicionales
    if (!empty($_FILES['additional_images']['name'][0])) {
        $upload_dir = '../uploads/products/';
        
        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Subir cada imagen adicional
        $uploaded_images = [];
        $total_files = count($_FILES['additional_images']['name']);
        
        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['additional_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['additional_images']['name'][$i], PATHINFO_EXTENSION);
                $file_name = uniqid() . '_' . $i . '.' . $file_extension;
                $target_file = $upload_dir . $file_name;
                
                // Verificar que sea una imagen válida
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array(strtolower($file_extension), $allowed_types)) {
                    if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], $target_file)) {
                        $uploaded_images[] = 'uploads/products/' . $file_name;
                    }
                }
            }
        }
        
        // Insertar las imágenes adicionales en la base de datos
        if (!empty($uploaded_images)) {
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)");
            
            $sort_order = count($additional_images); // Empezar desde el último orden actual
            
            foreach ($uploaded_images as $image) {
                $stmt->execute([$product_id, $image, $sort_order]);
                $sort_order++;
            }
        }
    }

    // Manejar eliminación de imágenes adicionales
    if (isset($_POST['delete_image']) && !empty($_POST['delete_image'])) {
        $image_ids = $_POST['delete_image'];
        foreach ($image_ids as $image_id) {
            // Obtener la URL de la imagen primero para poder eliminar el archivo
            $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE image_id = ? AND product_id = ?");
            $stmt->execute([$image_id, $product_id]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($image) {
                // Eliminar el archivo físico
                if (file_exists('../' . $image['image_url'])) {
                    unlink('../' . $image['image_url']);
                }
                
                // Eliminar el registro de la base de datos
                $stmt = $pdo->prepare("DELETE FROM product_images WHERE image_id = ? AND product_id = ?");
                $stmt->execute([$image_id, $product_id]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Panel de Administración - Jersix</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --sidebar-width: 240px;
            --topbar-height: 60px;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #333;
            background-color: #f5f7fa;
            line-height: 1.5;
        }
        
        .dashboard-layout {
            display: grid;
            grid-template-columns: var(--sidebar-width) 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            width: var(--sidebar-width);
            padding-top: 15px;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .sidebar .nav-menu {
            list-style: none;
            padding: 15px 0;
        }
        
        .sidebar .nav-item {
            margin: 5px 0;
        }
        
        .sidebar .nav-item a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-radius: 4px;
            margin: 0 8px;
            transition: var(--transition);
        }
        
        .sidebar .nav-item a i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .sidebar .nav-item a:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-item.active a {
            color: white;
            background: var(--primary-color);
        }
        
        /* Main content */
        .main-content {
            grid-column: 2;
            padding: 30px;
        }
        
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .topbar h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            background: white;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .user-info span {
            margin-right: 15px;
            color: var(--secondary-color);
        }
        
        /* Panel */
        .panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .panel-header {
            padding: 20px;
            border-bottom: 1px solid #eaedf3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .panel-body {
            padding: 20px;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: var(--border-radius);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .form-check-input {
            margin-right: 10px;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 14px;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #bd2130;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .buttons-container {
            display: flex;
            gap: 10px;
            justify-content: flex-start;
            margin-top: 20px;
        }
        
        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .alert-success {
            background-color: rgba(40,167,69,0.1);
            color: var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(220,53,69,0.1);
            color: var(--danger-color);
        }
        
        /* Image preview */
        .product-image-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 10px;
            border: 1px solid #e9ecef;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                width: 0;
                padding-top: 60px;
            }
            
            .sidebar.active {
                width: var(--sidebar-width);
            }
            
            .main-content {
                grid-column: 1;
                padding-top: calc(var(--topbar-height) + 20px);
            }
            
            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .form-col {
                width: 100%;
            }
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-col {
            flex: 1;
        }
        
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            grid-gap: 15px;
            margin-bottom: 15px;
        }
        
        .image-item {
            position: relative;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 5px;
        }
        
        .image-actions {
            margin-top: 5px;
            display: flex;
            align-items: center;
            font-size: 12px;
        }
        
        .image-actions input {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Jersix.mx</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Inicio</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php">
                        <i class="fas fa-box"></i>
                        <span>Productos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Pedidos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="newsletter.php">
                        <i class="fas fa-users"></i>
                        <span>Clientes / Newsletter</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="giftcards.php">
                        <i class="fas fa-gift"></i>
                        <span>Gift Cards</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="promociones.php">
                        <i class="fas fa-percent"></i>
                        <span>Promociones</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <h1>Editar Producto</h1>
                <div class="user-info">
                    <span>Usuario: Admin</span>
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Volver a Productos
                    </a>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Información del Producto</h3>
                </div>
                
                <div class="panel-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label class="form-label" for="name">Nombre del Producto*</label>
                                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="price">Precio*</label>
                                    <input type="number" id="price" name="price" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="stock">Stock*</label>
                                    <input type="number" id="stock" name="stock" min="0" class="form-control" value="<?php echo htmlspecialchars($product['stock']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-col">
                                <div class="form-group">
                                    <label class="form-label" for="category">Categoría</label>
                                    <input type="text" id="category" name="category" class="form-control" value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>" list="categories">
                                    <datalist id="categories">
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Imagen Actual</label>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <div>
                                            <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="Imagen actual" class="product-image-preview">
                                        </div>
                                    <?php else: ?>
                                        <p>No hay imagen</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="image">Cambiar Imagen</label>
                                    <input type="file" id="image" name="image" class="form-control">
                                    <small>Deja en blanco para mantener la imagen actual</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="description">Descripción</label>
                            <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="status">Estado</label>
                            <div class="form-check">
                                <?php 
                                // Verificar si la columna status existe
                                $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'status'");
                                $statusColumnExists = ($stmt->rowCount() > 0);
                                
                                // Si existe, mostrar el checkbox con el valor actual
                                if ($statusColumnExists): 
                                ?>
                                    <input type="checkbox" id="status" name="status" class="form-check-input" <?php echo ($product['status'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                    <label for="status" class="form-check-label">Activo (visible en la tienda)</label>
                                <?php else: ?>
                                    <input type="checkbox" id="status" name="status" class="form-check-input" checked disabled>
                                    <label for="status" class="form-check-label">Activo (función no disponible - columna status no existe)</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Imágenes Adicionales</label>
                            <div class="additional-images-container">
                                <?php if (empty($additional_images)): ?>
                                    <p>No hay imágenes adicionales</p>
                                <?php else: ?>
                                    <div class="image-grid">
                                        <?php foreach ($additional_images as $img): ?>
                                            <div class="image-item">
                                                <img src="../<?php echo htmlspecialchars($img['image_url']); ?>" alt="Imagen adicional" class="product-image-preview">
                                                <div class="image-actions">
                                                    <input type="checkbox" name="delete_image[]" value="<?php echo $img['image_id']; ?>" id="delete_<?php echo $img['image_id']; ?>">
                                                    <label for="delete_<?php echo $img['image_id']; ?>">Eliminar</label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="additional_images">Agregar Más Imágenes</label>
                            <input type="file" id="additional_images" name="additional_images[]" class="form-control" multiple accept="image/*">
                            <small>Puedes seleccionar múltiples archivos. Formatos permitidos: JPG, PNG, GIF, WEBP</small>
                        </div>
                        
                        <div class="buttons-container">
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                            <a href="products.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Opcional: aquí puedes agregar JavaScript para preview de imagen, validación, etc.
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Mostrar preview
                    const img = document.querySelector('.product-image-preview');
                    if (img) {
                        img.src = event.target.result;
                    } else {
                        const newImg = document.createElement('img');
                        newImg.src = event.target.result;
                        newImg.classList.add('product-image-preview');
                        e.target.parentNode.insertBefore(newImg, e.target);
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html> 