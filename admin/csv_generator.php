<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.html');
    exit();
}

$message = '';
$messageType = '';

// Definir equipos por categoría
$equipos = [
    'Equipos' => [
        'Real Madrid', 'Barcelona', 'Manchester United', 'Liverpool', 'Bayern Munich',
        'PSG', 'Juventus', 'Milan', 'Inter', 'Borussia Dortmund',
        'Chelsea', 'Arsenal', 'Manchester City', 'Atletico Madrid', 'Napoli',
        'Ajax', 'Benfica', 'Porto', 'Sporting Lisboa', 'PSV',
        'Boca Juniors', 'River Plate', 'America', 'Chivas', 'Pumas',
        'Cruz Azul', 'Tigres', 'Monterrey', 'Santos', 'Toluca'
    ],
    'Selecciones' => [
        'México', 'España', 'Argentina', 'Brasil', 'Alemania',
        'Francia', 'Italia', 'Inglaterra', 'Portugal', 'Holanda',
        'Bélgica', 'Colombia', 'Uruguay', 'Chile', 'Croacia',
        'Dinamarca', 'Suecia', 'Suiza', 'Estados Unidos', 'Canadá',
        'Japón', 'Corea del Sur', 'Australia', 'Egipto', 'Marruecos',
        'Senegal', 'Ghana', 'Nigeria', 'Costa Rica', 'Ecuador'
    ],
    'Retro' => [
        'Brasil 1994', 'Argentina 1986', 'México 1986', 'Alemania 1990', 'Italia 1982',
        'Holanda 1978', 'España 2010', 'Francia 1998', 'Inglaterra 1966', 'Brasil 1970',
        'Barcelona 1992', 'Manchester United 1999', 'Real Madrid 2002', 'Milan 1994', 'Juventus 1996',
        'Ajax 1995', 'Boca Juniors 2000', 'River Plate 1986', 'América 1988', 'Cruz Azul 1997'
    ]
];

// Generar CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos del formulario
        if (empty($_POST['equipos']) || !is_array($_POST['equipos'])) {
            throw new Exception('Por favor, selecciona al menos un equipo');
        }

        if (empty($_POST['tipo_jersey']) || !is_array($_POST['tipo_jersey'])) {
            throw new Exception('Por favor, selecciona al menos un tipo de jersey');
        }

        $precio_base = filter_input(INPUT_POST, 'precio_base', FILTER_VALIDATE_FLOAT);
        if (!$precio_base || $precio_base <= 0) {
            throw new Exception('Por favor, ingresa un precio base válido');
        }

        $stock_inicial = filter_input(INPUT_POST, 'stock_inicial', FILTER_VALIDATE_INT);
        if (!$stock_inicial || $stock_inicial < 0) {
            throw new Exception('Por favor, ingresa un stock inicial válido');
        }

        // Crear contenido CSV
        $csvContent = "name,price,stock,category,description,image_url\n";
        
        // Temporadas seleccionadas
        $temporadas = isset($_POST['temporada']) && is_array($_POST['temporada']) ? $_POST['temporada'] : ['2023-2024'];
        
        // Tipos de jersey seleccionados
        $tipos_jersey = $_POST['tipo_jersey'];
        
        // Para cada equipo seleccionado
        foreach ($_POST['equipos'] as $categoriaEquipo) {
            list($categoria, $equipo) = explode('|', $categoriaEquipo);
            
            // Para cada tipo de jersey
            foreach ($tipos_jersey as $tipo) {
                // Para cada temporada (si aplica)
                if ($categoria != 'Retro') {
                    foreach ($temporadas as $temporada) {
                        // Calcular precio (varía ligeramente)
                        $variacion = rand(-100, 100);
                        $precio = $precio_base + $variacion;
                        if ($tipo == 'Autentica') {
                            $precio += 500; // Auténticas son más caras
                        }
                        
                        // Crear nombre del producto
                        $nombre = "Jersey " . $tipo . " " . $equipo;
                        if ($categoria != 'Retro') {
                            $nombre .= " " . $temporada;
                        }
                        
                        // Descripción del producto
                        $descripcion = "Jersey oficial " . $tipo . " de " . $equipo;
                        if ($categoria != 'Retro') {
                            $descripcion .= " para la temporada " . $temporada;
                        } else {
                            $descripcion .= ", edición retro.";
                        }
                        $descripcion .= " 100% poliéster, ajuste regular.";
                        
                        // URL de imagen (ficticia, se reemplazará con imágenes reales)
                        $imageUrl = "https://ejemplo.com/jerseys/" . strtolower(str_replace(' ', '-', $equipo)) . "-" . strtolower($tipo) . ".jpg";
                        
                        // Añadir línea al CSV
                        $csvContent .= "\"" . $nombre . "\"," . 
                                     number_format($precio, 2, '.', '') . "," . 
                                     $stock_inicial . "," . 
                                     "\"" . $categoria . "\"," . 
                                     "\"" . $descripcion . "\"," . 
                                     "\"" . $imageUrl . "\"\n";
                    }
                } else {
                    // Para productos retro no usamos temporada actual
                    $precio = $precio_base + rand(200, 500); // Los retro son más caros generalmente
                    
                    $nombre = "Jersey " . $tipo . " " . $equipo;
                    $descripcion = "Jersey retro " . $tipo . " de " . $equipo . ". Edición de colección, 100% poliéster, ajuste clásico.";
                    $imageUrl = "https://ejemplo.com/jerseys-retro/" . strtolower(str_replace(' ', '-', $equipo)) . ".jpg";
                    
                    $csvContent .= "\"" . $nombre . "\"," . 
                                 number_format($precio, 2, '.', '') . "," . 
                                 $stock_inicial . "," . 
                                 "\"" . $categoria . "\"," . 
                                 "\"" . $descripcion . "\"," . 
                                 "\"" . $imageUrl . "\"\n";
                }
            }
        }
        
        // Crear archivo CSV para descargar
        $tempDir = '../temp';
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        $csvFile = $tempDir . '/productos_generados.csv';
        file_put_contents($csvFile, $csvContent);
        
        $message = "Archivo CSV generado con éxito con " . count(explode("\n", $csvContent)) - 1 . " productos.";
        $messageType = 'success';
        $downloadUrl = '../temp/productos_generados.csv';
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
    <title>Generador de CSV - Jersix</title>
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

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            width: calc(33.333% - 1rem);
            margin-bottom: 0.5rem;
        }

        .checkbox-item input {
            width: auto;
            margin-right: 0.5rem;
        }

        .section-title {
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .download-btn {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            margin-top: 1rem;
        }

        .download-btn:hover {
            background-color: #218838;
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
                <h1>Generador de Archivos CSV</h1>
                <div class="admin-controls">
                    <a href="bulk_product_upload.php" class="btn btn-primary"><i class="fas fa-upload"></i> Carga Masiva</a>
                    <a href="products.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver a Productos</a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
                <?php if (isset($downloadUrl)): ?>
                <div style="margin-top: 1rem;">
                    <a href="<?php echo $downloadUrl; ?>" download class="download-btn">
                        <i class="fas fa-download"></i> Descargar Archivo CSV
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="instructions">
                <h3>Generador de archivos CSV para carga masiva</h3>
                <p>Esta herramienta te permite generar rápidamente un archivo CSV con múltiples productos basados en los equipos, tipos de jersey y temporadas que selecciones.</p>
            </div>

            <div class="form-container">
                <form action="csv_generator.php" method="POST">
                    <div class="form-group">
                        <label for="precio_base">Precio Base</label>
                        <input type="number" id="precio_base" name="precio_base" value="899" step="0.01" required>
                        <div class="help-text">Este es el precio base para todos los productos. Se aplicarán variaciones aleatorias.</div>
                    </div>

                    <div class="form-group">
                        <label for="stock_inicial">Stock Inicial</label>
                        <input type="number" id="stock_inicial" name="stock_inicial" value="10" required>
                        <div class="help-text">Cantidad de stock inicial para todos los productos generados.</div>
                    </div>

                    <div class="form-group">
                        <label>Tipos de Jersey</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="tipo_local" name="tipo_jersey[]" value="Local" checked>
                                <label for="tipo_local">Local</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="tipo_visitante" name="tipo_jersey[]" value="Visitante">
                                <label for="tipo_visitante">Visitante</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="tipo_tercero" name="tipo_jersey[]" value="Tercero">
                                <label for="tipo_tercero">Tercera</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="tipo_portera" name="tipo_jersey[]" value="Portero">
                                <label for="tipo_portera">Portero</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="tipo_autentica" name="tipo_jersey[]" value="Autentica">
                                <label for="tipo_autentica">Auténtica</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Temporadas (para equipos no retro)</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="temporada_actual" name="temporada[]" value="2023-2024" checked>
                                <label for="temporada_actual">2023-2024</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="temporada_anterior" name="temporada[]" value="2022-2023">
                                <label for="temporada_anterior">2022-2023</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <h3 class="section-title">Seleccionar Equipos</h3>
                        
                        <h4>Equipos de Clubes</h4>
                        <div class="checkbox-group">
                            <?php foreach ($equipos['Equipos'] as $equipo): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="equipo_<?php echo md5($equipo); ?>" name="equipos[]" value="Equipos|<?php echo $equipo; ?>">
                                <label for="equipo_<?php echo md5($equipo); ?>"><?php echo $equipo; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <h4>Selecciones Nacionales</h4>
                        <div class="checkbox-group">
                            <?php foreach ($equipos['Selecciones'] as $equipo): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="seleccion_<?php echo md5($equipo); ?>" name="equipos[]" value="Selecciones|<?php echo $equipo; ?>">
                                <label for="seleccion_<?php echo md5($equipo); ?>"><?php echo $equipo; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <h4>Equipos Retro</h4>
                        <div class="checkbox-group">
                            <?php foreach ($equipos['Retro'] as $equipo): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="retro_<?php echo md5($equipo); ?>" name="equipos[]" value="Retro|<?php echo $equipo; ?>">
                                <label for="retro_<?php echo md5($equipo); ?>"><?php echo $equipo; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-csv"></i> Generar Archivo CSV
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 