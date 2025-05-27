<?php
// Script para actualizar todas las páginas de producto específicas con la nueva funcionalidad de imágenes

// Definir los archivos de producto específicos a actualizar
$productFiles = [
    'Productos-equipos/producto-barca.php',
    'Productos-equipos/producto-real-madrid.php',
    'Productos-equipos/producto-manchester-city.php',
    'Productos-equipos/producto-bayern-munchen.php',
    'Productos-equipos/producto-ac-milan.php',
    'Productos-equipos/producto-Psg.php',
    'Productos-equipos/producto-rayados.php',
    'Productos-equipos/producto-tigres.php',
    'Productos-equipos/producto-america.php',
    'Productos-equipos/producto-chivas.php',
    'Productos-equipos/producto-cruzazul.php'
];

// Patrones a buscar y reemplazar en los archivos
$searchAndReplace = [
    // 1. Actualizar la sección de la imagen del producto para incluir botones de navegación y zoom
    [
        'search' => '/<div class="product-image-container">[\s\S]*?<\/div>\s*<div class="product-thumbnails">[\s\S]*?<\/div>/m',
        'replace' => '<div class="product-image-container">
                <img src="<?php echo isset($product_image) ? $product_image : \'../img/default-product.jpg\'; ?>" alt="<?php echo htmlspecialchars($product_name); ?>" class="product-image" id="mainImage" loading="lazy">
                <div class="product-thumbnails">
                    <?php foreach ($thumbnails as $index => $thumbnail): ?>
                    <img src="<?php echo $thumbnail; ?>" alt="<?php echo htmlspecialchars($product_name); ?> <?php echo $index+1; ?>" 
                         class="thumbnail <?php echo ($index === 0) ? \'active\' : \'\'; ?>" 
                         onclick="changeImage(this)" loading="lazy">
                    <?php endforeach; ?>
                </div>
                
                <!-- Botones de navegación de imágenes -->
                <div class="image-navigation">
                    <button class="prev-image" onclick="changeImageNav(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button class="next-image" onclick="changeImageNav(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                
                <!-- Indicador de zoom -->
                <div class="zoom-indicator">
                    <i class="fas fa-search-plus"></i> Mueva el cursor para hacer zoom
                </div>'
    ],
    
    // 2. Añadir estilos para los elementos de navegación y zoom
    [
        'search' => '/<style>[\s\S]*?\.thumbnail\.active\s*{[^}]*}([\s\S]*?)\.product-info\s*{/m',
        'replace' => '<style>
        /* Estilos anteriores */
        .thumbnail.active {
            opacity: 1;
        }
        
        /* Estilos para navegación de imágenes */
        .image-navigation {
            position: absolute;
            width: 100%;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            padding: 0 10px;
            pointer-events: none;
        }

        .prev-image, .next-image {
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease;
        }

        .prev-image:hover, .next-image:hover {
            background-color: rgba(255, 255, 255, 1);
        }

        .prev-image i, .next-image i {
            color: #333;
            font-size: 14px;
        }

        /* Estilos para indicador de zoom */
        .zoom-indicator {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .zoom-indicator i {
            font-size: 14px;
        }

        .product-image-container:hover .zoom-indicator {
            opacity: 1;
        }

        .product-info {'
    ],
    
    // 3. Actualizar las funciones de JavaScript para la navegación de imágenes y zoom
    [
        'search' => '/function changeImage\(element\) {[\s\S]*?element\.classList\.add\(\'active\'\);(\s*})(\s*<\/script>)/m',
        'replace' => 'function changeImage(element) {
            const mainImage = document.getElementById(\'mainImage\');
            if (!mainImage) return;
            
            mainImage.src = element.src;
            
            // Actualizar la clase "active" de las miniaturas
            const thumbnails = document.querySelectorAll(\'.thumbnail\');
            let currentIndex = 0;
            
            thumbnails.forEach((thumb, index) => {
                thumb.classList.remove(\'active\');
                if (thumb === element) {
                    currentIndex = index;
                    currentImageIndex = index; // Actualizar índice global
                }
            });
            element.classList.add(\'active\');
        }
        
        // Variables para navegación de imágenes
        let currentImageIndex = 0;
        
        // Función para navegar entre imágenes con los botones de navegación
        function changeImageNav(direction) {
            const thumbnails = Array.from(document.querySelectorAll(\'.thumbnail\'));
            const totalImages = thumbnails.length;
            if (totalImages <= 1) return;
            
            // Calcular el nuevo índice
            let newIndex = currentImageIndex + direction;
            
            // Manejar los límites
            if (newIndex < 0) newIndex = totalImages - 1;
            if (newIndex >= totalImages) newIndex = 0;
            
            // Cambiar la imagen
            changeImage(thumbnails[newIndex]);
        }
        
        // Permitir navegación con teclado
        document.addEventListener(\'keydown\', function(e) {
            if (e.key === \'ArrowLeft\') {
                changeImageNav(-1);
            } else if (e.key === \'ArrowRight\') {
                changeImageNav(1);
            }
        });
        
        // Funcionalidad de zoom para la imagen
        document.addEventListener(\'DOMContentLoaded\', function() {
            const mainImage = document.getElementById(\'mainImage\');
            
            if (mainImage) {
                mainImage.addEventListener(\'mousemove\', function(e) {
                    const { left, top, width, height } = this.getBoundingClientRect();
                    const x = (e.clientX - left) / width;
                    const y = (e.clientY - top) / height;
                    
                    // Aplicar transformación para zoom
                    this.style.transformOrigin = `${x * 100}% ${y * 100}%`;
                    this.style.transform = \'scale(1.5)\';
                });
                
                mainImage.addEventListener(\'mouseleave\', function() {
                    this.style.transform = \'scale(1)\';
                });
                
                // Asegúrese de que la primera miniatura está activa
                const firstThumbnail = document.querySelector(\'.thumbnail\');
                if (firstThumbnail) {
                    firstThumbnail.classList.add(\'active\');
                }
            }
        });$2'
    ],
    
    // 4. Actualizar la lógica de generación de miniaturas PHP al inicio del archivo
    [
        'search' => '/\$thumbnails = \[\];[\s\S]*?\$thumbnails\[\] = \$product_image;[\s\S]*?for[\s\S]*?if \(file_exists[\s\S]*?\)/m',
        'replace' => '$thumbnails = [];
    $thumbnails[] = $product_image;
    
    // Intentar encontrar imágenes adicionales basadas en el nombre del equipo
    $team_name = \'\';
    
    // Extraer nombre del equipo del nombre del producto
    $product_name_lower = strtolower($product_name);
    $teams = [
        \'barcelona\' => [\'barca\', \'barcelona\'],
        \'real madrid\' => [\'real madrid\', \'madrid\'],
        \'manchester city\' => [\'manchester city\', \'manchester c\'],
        \'bayern munich\' => [\'bayern\', \'munchen\', \'munich\'],
        \'milan\' => [\'milan\', \'ac milan\'],
        \'psg\' => [\'psg\', \'paris\'],
        \'tigres\' => [\'tigres\'],
        \'rayados\' => [\'rayados\', \'monterrey\'],
        \'america\' => [\'america\', \'águilas\'],
        \'chivas\' => [\'chivas\', \'guadalajara\'],
        \'cruz azul\' => [\'cruz azul\']
    ];
    
    foreach ($teams as $team => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($product_name_lower, $keyword) !== false) {
                $team_name = $team;
                break 2;
            }
        }
    }
    
    // Buscar imágenes relacionadas con el equipo
    if (!empty($team_name)) {
        $team_name_formatted = str_replace(\' \', \'\', ucwords($team_name));
        
        // Patrones de nombre de archivo a buscar
        $patterns = [
            $team_name_formatted . \'Local.jpg\',
            $team_name_formatted . \'Local.png\',
            $team_name_formatted . \'Local.webp\',
            $team_name_formatted . \'1.jpg\',
            $team_name_formatted . \'1.png\',
            $team_name_formatted . \'1.webp\',
            $team_name_formatted . \'2.jpg\',
            $team_name_formatted . \'2.png\',
            $team_name_formatted . \'2.webp\',
            $team_name_formatted . \'3.jpg\',
            $team_name_formatted . \'3.png\',
            $team_name_formatted . \'3.webp\'
        ];
        
        // Variaciones adicionales para algunos equipos
        if ($team_name == \'barcelona\') {
            $patterns[] = \'Barca2.webp\';
            $patterns[] = \'Barca3.png\';
        } elseif ($team_name == \'real madrid\') {
            $patterns[] = \'RealM2.png\';
            $patterns[] = \'RealM3.png\';
        } elseif ($team_name == \'manchester city\') {
            $patterns[] = \'ManchesterCity.png\';
            $patterns[] = \'ManchsterC2.png\';
            $patterns[] = \'ManchesterC3.webp\';
        } elseif ($team_name == \'bayern munich\') {
            $patterns[] = \'BayerMunchenLocal.jpg\';
            $patterns[] = \'BayernMunchen1.jpg\';
            $patterns[] = \'BayernMunchen2.jpg\';
        }
        
        $jersey_dir = $_SERVER[\'DOCUMENT_ROOT\'] . \'/img/Jerseys/\';
        
        // Buscar en directorio de Jerseys
        foreach ($patterns as $pattern) {
            if (file_exists($jersey_dir . $pattern)) {
                $image_path = \'../img/Jerseys/\' . $pattern;
                if (!in_array($image_path, $thumbnails)) {
                    $thumbnails[] = $image_path;
                }
            }
        }
        
        // Si no se encuentran imágenes adicionales, buscar por nombre parcial
        if (count($thumbnails) <= 1) {
            $dir_contents = scandir($jersey_dir);
            foreach ($dir_contents as $file) {
                if ($file == \'.\' || $file == \'..\' || $file == \'.DS_Store\') continue;
                
                $file_lower = strtolower($file);
                foreach ($keywords as $keyword) {
                    $keyword_lower = strtolower($keyword);
                    if (strpos($file_lower, $keyword_lower) !== false) {
                        $image_path = \'../img/Jerseys/\' . $file;
                        if (!in_array($image_path, $thumbnails)) {
                            $thumbnails[] = $image_path;
                        }
                    }
                }
            }
        }
    }
    
    // Si no hay suficientes imágenes, intentar buscar por numeración
    if (count($thumbnails) <= 1) {
        $image_base = pathinfo($product_image, PATHINFO_FILENAME);
        $image_ext = pathinfo($product_image, PATHINFO_EXTENSION);
        $image_dir = pathinfo($product_image, PATHINFO_DIRNAME);
        
        for ($i = 2; $i <= 5; $i++) {
            $possible_thumb = $image_dir . \'/\' . $image_base . $i . \'.\' . $image_ext;
            if (file_exists($_SERVER[\'DOCUMENT_ROOT\'] . \'/\' . $possible_thumb)'
    ]
];

// Procesar cada archivo
$results = [];

foreach ($productFiles as $file) {
    if (file_exists($file)) {
        // Leer el contenido original
        $content = file_get_contents($file);
        $original_content = $content;
        
        // Aplicar cada patrón de búsqueda y reemplazo
        foreach ($searchAndReplace as $pattern) {
            $content = preg_replace($pattern['search'], $pattern['replace'], $content);
        }
        
        // Guardar el archivo si ha habido cambios
        if ($content !== $original_content) {
            if (file_put_contents($file, $content)) {
                $results[$file] = 'Actualizado correctamente';
            } else {
                $results[$file] = 'Error al guardar los cambios';
            }
        } else {
            $results[$file] = 'No se necesitaron cambios';
        }
    } else {
        $results[$file] = 'El archivo no existe';
    }
}

// Mostrar los resultados
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de Imágenes de Productos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        .warning {
            color: orange;
        }
    </style>
</head>
<body>
    <h1>Resultados de la actualización de imágenes</h1>
    
    <table>
        <thead>
            <tr>
                <th>Archivo</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $file => $result): ?>
                <tr>
                    <td><?php echo htmlspecialchars($file); ?></td>
                    <td class="<?php 
                        if ($result === 'Actualizado correctamente') echo 'success';
                        elseif ($result === 'No se necesitaron cambios') echo 'warning';
                        else echo 'error';
                    ?>"><?php echo htmlspecialchars($result); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html> 