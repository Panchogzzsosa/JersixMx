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

// Función para validar archivo CSV
function validateCSV($file) {
    // Verificar tipo de archivo
    $mimeType = mime_content_type($file['tmp_name']);
    if ($mimeType !== 'text/plain' && $mimeType !== 'text/csv' && $mimeType !== 'application/vnd.ms-excel') {
        return 'El archivo debe ser CSV. Tipo detectado: ' . $mimeType;
    }
    
    // Verificar tamaño (máximo 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return 'El archivo es demasiado grande. Máximo 5MB permitido.';
    }
    
    return true;
}

// Función para procesar la imagen de un producto
function processProductImage($imageUrl, $allowImportWithoutImages = false) {
    // Si se permite importar sin imágenes y no hay URL
    if ($allowImportWithoutImages && empty($imageUrl)) {
        return '';
    }
    
    // Si la URL está vacía y no se permiten productos sin imágenes
    if (empty($imageUrl)) {
        throw new Exception('URL de imagen vacía');
    }
    
    // Si es una URL web, intentar descargar la imagen
    if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
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
        
        // Generar nombre único para el archivo
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'jpg'; // Extensión por defecto
        }
        $filename = uniqid() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;
        
        // Descargar imagen
        $imageData = @file_get_contents($imageUrl);
        if ($imageData === false) {
            throw new Exception('No se pudo descargar la imagen desde: ' . $imageUrl);
        }
        
        if (file_put_contents($uploadPath, $imageData) === false) {
            throw new Exception('Error al guardar la imagen descargada');
        }
        
        return 'img/Jerseys/' . $filename;
    } else {
        throw new Exception('URL de imagen inválida: ' . $imageUrl);
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getConnection();
        
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            // Validar archivo CSV
            $validateResult = validateCSV($_FILES['csv_file']);
            if ($validateResult !== true) {
                throw new Exception($validateResult);
            }
            
            // Leer el archivo CSV
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if (!$handle) {
                throw new Exception('No se pudo abrir el archivo CSV');
            }
            
            // Leer la primera línea para obtener los encabezados
            $headers = fgetcsv($handle);
            if (!$headers) {
                throw new Exception('El archivo CSV está vacío o tiene un formato incorrecto');
            }
            
            // Verificar que los encabezados sean correctos
            $requiredHeaders = ['name', 'price', 'stock', 'category', 'description', 'image_url'];
            $missingHeaders = array_diff($requiredHeaders, array_map('strtolower', $headers));
            
            if (!empty($missingHeaders)) {
                throw new Exception('Faltan columnas requeridas en el CSV: ' . implode(', ', $missingHeaders));
            }
            
            // Mapear los índices de las columnas
            $columnMap = array_flip(array_map('strtolower', $headers));
            
            // Preparar la consulta para inserción
            $query = "INSERT INTO products (name, price, stock, category, description, image_url) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            
            // Obtener opción para importar productos sin imágenes
            $allowImportWithoutImages = isset($_POST['allow_no_images']) ? true : false;
            
            // Contador de productos añadidos
            $addedCount = 0;
            $errorCount = 0;
            $errors = [];
            
            // Procesar cada línea del CSV
            $lineNumber = 1; // Primera línea son los encabezados
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                
                try {
                    // Verificar que la línea tenga suficientes columnas
                    if (count($row) < count($requiredHeaders)) {
                        throw new Exception("La línea $lineNumber no tiene suficientes columnas");
                    }
                    
                    // Obtener los datos de cada columna
                    $name = trim($row[$columnMap['name']]);
                    $price = floatval(trim($row[$columnMap['price']]));
                    $stock = intval(trim($row[$columnMap['stock']]));
                    $category = trim($row[$columnMap['category']]);
                    $description = trim($row[$columnMap['description']]);
                    $imageUrl = trim($row[$columnMap['image_url']]);
                    
                    // Validar datos básicos
                    if (empty($name)) throw new Exception("Nombre vacío");
                    if ($price <= 0) throw new Exception("Precio inválido: $price");
                    if ($stock < 0) throw new Exception("Stock inválido: $stock");
                    if (empty($category)) throw new Exception("Categoría vacía");
                    
                    // Procesar imagen (descargar si es una URL)
                    $finalImageUrl = processProductImage($imageUrl, $allowImportWithoutImages);
                    
                    // Insertar en la base de datos
                    $stmt->execute([$name, $price, $stock, $category, $description, $finalImageUrl]);
                    $addedCount++;
                    
                } catch (Exception $e) {
                    $errorCount++;
                    $errors[] = "Error en línea $lineNumber: " . $e->getMessage();
                    // Continuar con la siguiente línea
                    continue;
                }
            }
            
            fclose($handle);
            
            // Mensaje de resultado
            if ($addedCount > 0) {
                $message = "Se agregaron $addedCount productos exitosamente.";
                if ($errorCount > 0) {
                    $message .= " Hubo $errorCount errores.";
                }
                $messageType = 'success';
            } else {
                $message = "No se pudo agregar ningún producto. Hubo $errorCount errores.";
                $messageType = 'error';
            }
            
            // Incluir detalles de errores si los hay
            if (!empty($errors)) {
                $message .= " Primeros errores: " . implode('; ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= " y " . (count($errors) - 3) . " más.";
                }
            }
            
        } else {
            throw new Exception('Por favor seleccione un archivo CSV.');
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Crear estructura de ejemplo CSV para descargar
$sampleCsvContent = "name,price,stock,category,description,image_url\n";
$sampleCsvContent .= "\"Jersey Real Madrid Local\",999.00,10,\"Equipos\",\"Jersey oficial Real Madrid temporada 2023-2024\",\"https://ejemplo.com/imagen.jpg\"\n";
$sampleCsvContent .= "\"Jersey Barcelona Visitante\",899.00,15,\"Equipos\",\"Jersey oficial FC Barcelona temporada 2023-2024\",\"https://ejemplo.com/imagen2.jpg\"";

$sampleCsvFile = '../temp/sample_products.csv';
$tempDir = '../temp';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}
file_put_contents($sampleCsvFile, $sampleCsvContent);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Masiva de Productos - Jersix</title>
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

        .help-text {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .instructions {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .instructions h3 {
            margin-top: 0;
            color: #2c3e50;
        }

        .instructions ul {
            padding-left: 20px;
        }

        .instructions li {
            margin-bottom: 0.5rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .checkbox-group input {
            width: auto;
            margin-right: 0.5rem;
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
                <li class="nav-item">
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
                <li class="nav-item active">
                    <a href="csv_generator.php"><i class="fas fa-file-csv"></i> Generador CSV</a>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Carga Masiva de Productos</h1>
                <div class="admin-controls">
                    <a href="csv_generator.php" class="btn btn-primary"><i class="fas fa-file-csv"></i> Generador de CSV</a>
                    <a href="products.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver a Productos</a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="instructions">
                <h3>Instrucciones para la carga masiva</h3>
                <ul>
                    <li>Prepare un archivo CSV con los siguientes encabezados: name, price, stock, category, description, image_url</li>
                    <li>El campo image_url debe contener una URL válida a una imagen</li>
                    <li>Las categorías disponibles son: Equipos, Retro, Selecciones</li>
                    <li>Puede <a href="<?php echo $sampleCsvFile; ?>" download>descargar un archivo CSV de ejemplo aquí</a></li>
                </ul>
            </div>

            <div class="form-container">
                <form action="bulk_product_upload.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file">Archivo CSV</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="help-text">El archivo debe estar en formato CSV con separación por comas</div>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="allow_no_images" name="allow_no_images">
                        <label for="allow_no_images">Permitir productos sin imágenes</label>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Cargar Productos
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 