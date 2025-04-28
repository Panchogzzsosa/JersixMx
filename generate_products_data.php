<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/javascript');

try {
    $pdo = getConnection();
    
    // Verificar si la columna status existe en la tabla products
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'status'");
    $statusColumnExists = ($stmt->rowCount() > 0);
    
    // Consulta para obtener productos activos
    if ($statusColumnExists) {
        // Si la columna status existe, solo mostrar productos activos
        $query = "SELECT * FROM products WHERE status = 1 ORDER BY name ASC";
    } else {
        // Si la columna status no existe, mostrar todos los productos
        $query = "SELECT * FROM products ORDER BY name ASC";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Función para determinar la liga del producto
    function getProductLeague($productName) {
        // Obtener liga basado en nombre
        $ligasMX = [
            'Tigres', 'Rayados', 'América', 'Chivas', 'Cruz Azul', 'Monterrey', 'Guadalajara', 
            'Pumas', 'UNAM', 'León', 'Santos', 'Toluca', 'Atlas', 'Tijuana', 'Xolos', 'Pachuca', 
            'Puebla', 'Querétaro', 'Mazatlán', 'Necaxa', 'San Luis', 'Atlético San Luis', 'Juárez'
        ];
        $premierLeague = [
            'Manchester City', 'Liverpool', 'Manchester United', 'Chelsea', 'Arsenal', 'Tottenham',
            'Leicester', 'Everton', 'Newcastle', 'Wolves', 'West Ham', 'Aston Villa', 'Brighton',
            'Crystal Palace', 'Brentford', 'Leeds', 'Southampton', 'Burnley', 'Watford', 'Norwich'
        ];
        $laLiga = [
            'Real Madrid', 'Barcelona', 'Atletico Madrid', 'Atletico', 'Valencia', 'Sevilla', 'Athletic',
            'Villarreal', 'Real Sociedad', 'Betis', 'Osasuna', 'Celta', 'Espanyol', 'Mallorca',
            'Getafe', 'Cadiz', 'Granada', 'Alaves', 'Elche', 'Rayo Vallecano'
        ];
        $bundesliga = [
            'Bayern', 'Borussia', 'Dortmund', 'Bayern Múnich', 'Leverkusen', 'Leipzig',
            'Wolfsburg', 'Frankfurt', 'Gladbach', 'Hoffenheim', 'Stuttgart', 'Freiburg',
            'Union Berlin', 'Mainz', 'Augsburg', 'Hertha', 'Arminia', 'Köln', 'Bochum', 'Fürth'
        ];
        $serieA = [
            'Milan', 'AC Milan', 'Juventus', 'Inter', 'Roma', 'Napoli', 'Lazio',
            'Atalanta', 'Fiorentina', 'Torino', 'Verona', 'Sassuolo', 'Bologna',
            'Empoli', 'Udinese', 'Sampdoria', 'Spezia', 'Cagliari', 'Genoa', 'Salernitana'
        ];
        $ligue1 = [
            'PSG', 'Monaco', 'París', 'Lyon', 'Marseille', 'Lille', 'Nice', 'Rennes',
            'Lens', 'Strasbourg', 'Nantes', 'Montpellier', 'Brest', 'Angers',
            'Reims', 'Troyes', 'Lorient', 'Clermont', 'Metz', 'Bordeaux'
        ];
        
        // Convertir el nombre a minúsculas para comparación insensible a mayúsculas
        $lowerName = strtolower($productName);
        
        // Buscar coincidencias en cada liga
        foreach ($ligasMX as $equipo) {
            if (stripos($lowerName, strtolower($equipo)) !== false) return 'ligamx';
        }
        foreach ($premierLeague as $equipo) {
            if (stripos($lowerName, strtolower($equipo)) !== false) return 'premier';
        }
        foreach ($laLiga as $equipo) {
            if (stripos($lowerName, strtolower($equipo)) !== false) return 'laliga';
        }
        foreach ($bundesliga as $equipo) {
            if (stripos($lowerName, strtolower($equipo)) !== false) return 'bundesliga';
        }
        foreach ($serieA as $equipo) {
            if (stripos($lowerName, strtolower($equipo)) !== false) return 'serieA';
        }
        foreach ($ligue1 as $equipo) {
            if (stripos($lowerName, strtolower($equipo)) !== false) return 'ligue1';
        }
        
        return '';
    }
    
    // Función para determinar el tipo del producto (para filtros)
    function getProductType($productName) {
        if (preg_match('/\\b(Local|Visitante|Tercera|Portero)\\b/i', $productName, $matches)) {
            $tipo = strtolower($matches[1]);
            
            if ($tipo === 'local') {
                return 'local';
            } elseif ($tipo === 'visitante') {
                return 'visitante';
            } elseif ($tipo === 'tercera') {
                return 'especial';
            } elseif ($tipo === 'portero') {
                return 'especial';
            }
        }
        return 'local'; // Por defecto
    }
    
    // Función para determinar la categoría del producto (para filtros)
    function getProductCategory($productCategory) {
        if (strpos(strtolower($productCategory), 'retro') !== false) {
            return 'retro';
        } elseif (strpos(strtolower($productCategory), 'selecciones') !== false) {
            return 'selecciones';
        } else {
            return 'local';
        }
    }
    
    // Función para extraer el nombre del equipo del nombre del producto
    function getTeamName($productName) {
        // Patrones comunes para nombres de equipos
        $patterns = [
            '/^(.+?)\s+Local/i',
            '/^(.+?)\s+Visitante/i',
            '/^(.+?)\s+Tercera/i',
            '/^(.+?)\s+Portero/i',
            '/^(.+?)\s+\d{2}\/\d{2}/i',  // Para formatos como "Equipo 23/24"
            '/^(.+?)\s+20\d{2}/i'        // Para formatos como "Equipo 2023"
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $productName, $matches)) {
                return trim($matches[1]);
            }
        }
        
        // Si no se encuentra un patrón, devolver el nombre completo
        return $productName;
    }
    
    // Función para generar URL amigable para el producto
    function generateProductUrl($productName, $productId) {
        $name = strtolower($productName);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);
        $name = trim($name, '-');
        
        // Mapear nombres comunes a URLs específicas (mantener compatibilidad con páginas existentes)
        $mapping = [
            'jersey-real-madrid-local' => 'producto-real-madrid',
            'jersey-barcelona-local' => 'producto-barca',
            'jersey-manchester-city-local' => 'producto-manchester-city',
            'jersey-bayern-munchen-local' => 'producto-bayern-munchen',
            'jersey-ac-milan-local' => 'producto-ac-milan',
            'jersey-psg-local' => 'producto-Psg',
            'jersey-rayados-local' => 'producto-rayados',
            'jersey-tigres-local' => 'producto-tigres',
            'jersey-america-local' => 'producto-america',
            'jersey-chivas-local' => 'producto-chivas',
            'jersey-cruz-azul-local' => 'producto-cruzazul'
        ];
        
        // Buscar coincidencias parciales en el mapping
        foreach ($mapping as $pattern => $url) {
            if (strpos($name, $pattern) !== false) {
                return $url;
            }
        }
        
        // Si no hay coincidencia, usar la plantilla general con ID
        return 'producto.php?id=' . $productId;
    }
    
    // Generar el array de productos en JavaScript
    echo "const productsData = [\n";
    
    foreach ($products as $index => $product) {
        $productType = getProductType($product['name']);
        $productCategory = getProductCategory($product['category']);
        $productLeague = getProductLeague($product['name']);
        $teamName = getTeamName($product['name']);
        $productUrl = generateProductUrl($product['name'], $product['product_id']);
        
        // Asegurarse de que la URL de la imagen sea correcta
        $imageUrl = $product['image_url'];
        if (strpos($imageUrl, '../') !== 0 && strpos($imageUrl, 'http') !== 0) {
            $imageUrl = '../' . $imageUrl;
        }
        
        echo "    {\n";
        echo "        id: " . $product['product_id'] . ",\n";
        echo "        name: \"" . addslashes($product['name']) . "\",\n";
        echo "        team: \"" . addslashes($teamName) . "\",\n";
        echo "        category: \"" . addslashes($productCategory) . "\",\n";
        echo "        league: \"" . addslashes($productLeague) . "\",\n";
        echo "        price: \"" . $product['price'] . "\",\n";
        echo "        image: \"" . addslashes($imageUrl) . "\",\n";
        echo "        url: \"../Productos-equipos/" . addslashes($productUrl) . "\",\n";
        echo "        productId: \"" . $product['product_id'] . "\"\n";
        echo "    }" . ($index < count($products) - 1 ? "," : "") . "\n";
    }
    
    echo "];\n\n";
    
    // Agregar la función para actualizar precios sin usar template literals
    echo "// Function to update product prices\n";
    echo "function updateProductPrices() {\n";
    echo "    productsData.forEach(function(product) {\n";
    echo "        fetch('get_product_price.php?id=' + product.productId)\n";
    echo "            .then(function(response) { return response.json(); })\n";
    echo "            .then(function(data) {\n";
    echo "                if (data.success) {\n";
    echo "                    product.price = data.price;\n";
    echo "                    // Update price display in DOM\n";
    echo "                    var priceElement = document.querySelector('[data-product-id=' + product.productId + ']');\n";
    echo "                    if (priceElement) {\n";
    echo "                        priceElement.textContent = '$ ' + parseFloat(data.price).toFixed(2);\n";
    echo "                    }\n";
    echo "                }\n";
    echo "            })\n";
    echo "            .catch(function(error) { console.error('Error fetching price:', error); });\n";
    echo "    });\n";
    echo "}\n\n";
    
    echo "// Update prices initially and every 30 seconds\n";
    echo "document.addEventListener('DOMContentLoaded', function() {\n";
    echo "    updateProductPrices();\n";
    echo "    setInterval(updateProductPrices, 30000);\n";
    echo "});\n";
    
} catch (Exception $e) {
    // En caso de error, generar un array vacío
    echo "const productsData = [];\n";
    echo "console.error('Error loading products data: " . addslashes($e->getMessage()) . "');\n";
}