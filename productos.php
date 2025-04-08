<?php
require_once __DIR__ . '/config/database.php';

// Obtener todos los productos de la base de datos
try {
    $pdo = getConnection();
    
    // Obtener categorías para los filtros
    $categories = ['Equipos', 'Retro', 'Selecciones'];
    
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
    $db_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Función para determinar el tipo del producto (para filtros)
    function getProductType($productName) {
        if (preg_match('/\b(Local|Visitante|Tercera|Portero)\b/i', $productName, $matches)) {
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
        // Convertir a minúsculas para una comparación más flexible
        $category = strtolower($productCategory);
        
        if (strpos($category, 'retro') !== false) {
            return 'retro';
        } elseif (strpos($category, 'selecciones') !== false) {
            return 'selecciones';
        } elseif (strpos($category, 'especial') !== false) {
            return 'especial';
        } else {
            return ''; // Dejar vacío si no coincide con ninguna categoría específica
        }
    }
    
    // Función para determinar la liga del producto (para filtros)
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
        $mls = [
            'LA Galaxy', 'Los Angeles FC', 'LAFC', 'Inter Miami', 'Atlanta United', 'Seattle Sounders',
            'New York City FC', 'New York Red Bulls', 'Toronto FC', 'Portland Timbers', 'Orlando City',
            'Chicago Fire', 'DC United', 'Houston Dynamo', 'Columbus Crew', 'FC Cincinnati', 'Nashville SC',
            'Austin FC', 'Minnesota United', 'Colorado Rapids', 'Real Salt Lake', 'San Jose Earthquakes',
            'Vancouver Whitecaps', 'FC Dallas', 'Sporting KC', 'New England Revolution', 'Philadelphia Union',
            'CF Montreal', 'Charlotte FC', 'St. Louis City SC'
        ];
        $eredivisie = [
            'Ajax', 'PSV', 'PSV Eindhoven', 'Feyenoord', 'AZ Alkmaar', 'Twente', 'FC Twente', 'Utrecht', 
            'Vitesse', 'Groningen', 'Heerenveen', 'Willem II', 'Sparta Rotterdam', 'Heracles', 'Almere City', 
            'NEC Nijmegen', 'Go Ahead Eagles', 'RKC Waalwijk', 'Fortuna Sittard', 'PEC Zwolle'
        ];
        $saudiLeague = [
            'Al Hilal', 'Al Nassr', 'Al Ahli', 'Al Ittihad', 'Al Fateh', 'Al Taawoun', 'Al Shabab',
            'Al Fayha', 'Al Riyadh', 'Al Khaleej', 'Al Wehda', 'Al Akhdoud', 'Al Tai', 'Al Ettifaq',
            'Damac', 'Abha'
        ];
        
        $selecciones = [
            'México', 'Argentina', 'Brasil', 'Francia', 'España', 'Alemania', 'Italia', 
            'Portugal', 'Inglaterra', 'Holanda', 'Países Bajos', 'Bélgica', 'Uruguay', 
            'Colombia', 'Chile', 'Ecuador', 'Estados Unidos', 'USA', 'Japón', 'Corea del Sur',
            'Croacia', 'Senegal', 'Marruecos', 'Canadá', 'Qatar', 'Gales', 'Suiza', 'Dinamarca',
            'Polonia', 'Australia', 'Arabia Saudita', 'Túnez', 'Costa Rica', 'Serbia', 'Irán',
            'Ghana', 'Camerún', 'Perú', 'Paraguay', 'Venezuela', 'Bolivia'
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
        foreach ($mls as $equipo) {
            if (stripos($lowerName, strtolower($equipo)) !== false) return 'mls';
        }
        foreach ($eredivisie as $equipo) {
            if (stripos($lowerName, strtolower($equipo)) !== false) return 'eredivisie';
        }
        foreach ($saudiLeague as $equipo) {
            if (stripos($lowerName, strtolower($equipo)) !== false) return 'saudi';
        }
        
        foreach ($selecciones as $seleccion) {
            if (stripos($lowerName, strtolower($seleccion)) !== false) return 'selecciones';
        }
        
        // También comprobamos si la categoría del producto es 'selecciones'
        if (strpos(strtolower($productName), 'selección') !== false || 
            strpos(strtolower($productName), 'seleccion') !== false) {
            return 'selecciones';
        }
        
        return '';
    }
    
    // Función para generar URL amigable para el producto
    function generateProductUrl($productName, $productId = null) {
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
        
        // Si no hay coincidencia y tenemos un ID, usar la plantilla general
        if ($productId) {
            return 'producto.php?id=' . $productId;
        }
        
        // Si no hay ID, generar un slug basado en el nombre
        return 'producto-' . $name;
    }
    
} catch (Exception $e) {
    // En caso de error, crear un array vacío
    $db_products = [];
}

// Verificar si hay un mensaje de error (ej. producto inactivo)
$error_message = '';
if (isset($_GET['error']) && $_GET['error'] == 'producto_inactivo') {
    $error_message = 'El producto que estás buscando no está disponible actualmente.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JerSix - Productos</title>
    <link rel="stylesheet" href="Css/index.css">
    <link rel="stylesheet" href="Css/productos.css">
    <link rel="stylesheet" href="Css/cart.css">
    <link rel="stylesheet" href="Css/notificacion.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="img/ICON.png" type="image/x-icon">
    <script src="generate_products_data.php" defer></script>
    <script src="Js/productos.js" defer></script>
    <script src="Js/search.js" defer></script>
    <script src="Js/cart.js" defer></script>
    <script src="Js/newsletter.js" defer></script>
    <style>
        /* Estilo para el mensaje de error */
        .error-message {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* Estilo para fijar altura de títulos de productos */
        .product-card h3 {
            height: 40px; /* Altura fija para 2 líneas aprox */
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Limitar a 2 líneas */
            -webkit-box-orient: vertical;
            margin: 0;
            padding: 1rem 1rem 0.5rem 1rem;
            font-size: 1rem;
            text-align: center;
            font-weight: 500;
            color: #333;
        }
        
        /* Ajustar espaciado para precio y botón */
        .product-card .price {
            margin: 0.5rem 1rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: #000;
            display: block;
            text-align: center;
        }
        
        .product-card .add-to-cart {
            margin: 0.5rem 1rem 1rem;
            width: calc(100% - 2rem);
            background-color: #000;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
            display: block;
            text-align: center;
        }
        
        .product-card .add-to-cart:hover {
            background-color: #333;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <button class="menu-toggle">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="logo"><a href="index"><img src="img/LogoNav.png" alt="JerSix Logo"></a></div>
            <ul class="nav-links">
                <li></li>
                <li></li>
                <li></li>
                <li><a href="index">Inicio</a></li>
                <li><a href="productos" id = "pagina_actual">Productos</a></li>
                <li><a href="mistery-box">Mystery Box</a></li>
                <li><a href="giftcard" class="active">Giftcard</a></li>
                <li><a href="tracking.php">Seguimiento</a></li>
            </ul>
            <div class="search-container">
                <input type="text" placeholder="Buscar productos..." class="search-input">
                <button class="search-button">
                    <span class="material-symbols-outlined">search</span>
                </button>
            </div>
            <div class="cart-icon">
                <span class="material-symbols-outlined">shopping_cart</span>
            </div>
        </nav>
    </header>

    <main class="products-page">
        <button class="filter-toggle">
            <i class="fas fa-filter"></i> Filtros
        </button>
        <aside class="filters-sidebar">
            <h2>Filtros</h2>
            <div class="filter-section">
                <h3>Categorías</h3>
                <ul>
                    <li><label><input type="checkbox" id="filter-local" data-category="local"> Locales</label></li>
                    <li><label><input type="checkbox" id="filter-visitante" data-category="visitante"> Visitantes</label></li>
                    <li><label><input type="checkbox" id="filter-retro" data-category="retro"> Retro</label></li>
                    <li><label><input type="checkbox" id="filter-especial" data-category="especial"> Edición Especial</label></li>
                </ul>
            </div>
            <div class="filter-section">
                <h3>Ligas</h3>
                <ul>
                    <li><label><input type="checkbox" id="filter-ligamx" data-league="ligamx"> Liga Mx</label></li>
                    <li><label><input type="checkbox" id="filter-premier" data-league="premier"> Premier League</label></li>
                    <li><label><input type="checkbox" id="filter-laliga" data-league="laliga"> LaLiga</label></li>
                    <li><label><input type="checkbox" id="filter-ligue1" data-league="ligue1"> Ligue 1</label></li>
                    <li><label><input type="checkbox" id="filter-seriea" data-league="serieA"> Serie A</label></li>
                    <li><label><input type="checkbox" id="filter-bundesliga" data-league="bundesliga"> Bundesliga</label></li>
                    <li><label><input type="checkbox" id="filter-mls" data-league="mls"> MLS</label></li>
                    <li><label><input type="checkbox" id="filter-eredivisie" data-league="eredivisie"> Eredivisie</label></li>
                    <li><label><input type="checkbox" id="filter-saudi" data-league="saudi"> Liga Profesional Saudí</label></li>
                    <li><label><input type="checkbox" id="filter-selecciones-liga" data-league="selecciones"> Selecciones Nacionales</label></li>
                </ul>
            </div>
        </aside>

        <section class="products-grid">
            <div class="products-header">
                <h1>Nuestros Productos</h1>
                <div class="sort-options">
                    <select id="sort-products">
                        <option value="featured">Destacados</option>
                        <option value="price-low">Precio: Menor a Mayor</option>
                        <option value="price-high">Precio: Mayor a Menor</option>
                        <option value="newest">Más Nuevos</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <div class="products-container">
                <?php 
                // Mostrar productos desde la base de datos
                if (!empty($db_products)): 
                    foreach ($db_products as $product): 
                        // Solo mostrar productos activos
                        $isActive = !$statusColumnExists || ($statusColumnExists && isset($product['status']) && $product['status'] == 1);
                        if ($isActive):
                            // Determinar categoría y liga para filtros
                            $productCategory = getProductCategory($product['category']);
                            $productType = getProductType($product['name']);
                            $productLeague = getProductLeague($product['name']);
                            
                            // Generar URL del producto
                            $productUrl = generateProductUrl($product['name'], $product['product_id']);
                ?>
                <div class="product-card" data-category="<?php echo $productCategory; ?>" data-type="<?php echo $productType; ?>" data-league="<?php echo $productLeague; ?>">
                    <a href="Productos-equipos/<?php echo $productUrl; ?>">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="price" data-product-id="<?php echo $product['product_id']; ?>">$ <?php echo number_format($product['price'], 2); ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <?php 
                        endif; // Fin if isActive
                    endforeach;
                    
                    // Verificar si no hay productos para mostrar después del filtrado
                    $activeProductsCount = count(array_filter($db_products, function($product) use ($statusColumnExists) {
                        return !$statusColumnExists || ($statusColumnExists && isset($product['status']) && $product['status'] == 1);
                    }));
                    
                    if ($activeProductsCount === 0):
                ?>
                <div class="no-products-message">
                    <h3>No hay productos disponibles en este momento</h3>
                    <p>Vuelve a consultar más tarde, estamos trabajando para agregar nuevos productos.</p>
                </div>
                <style>
                    .no-products-message {
                        text-align: center;
                        padding: 50px 20px;
                        background: #f8f9fa;
                        border-radius: 8px;
                        margin: 30px auto;
                        max-width: 600px;
                    }
                    .no-products-message i {
                        font-size: 48px;
                        color: #6c757d;
                        margin-bottom: 20px;
                    }
                    .no-products-message h3 {
                        margin-bottom: 10px;
                        color: #343a40;
                    }
                    .no-products-message p {
                        color: #6c757d;
                    }
                </style>
                <?php 
                    endif;
                else: 
                    // Si no hay productos en la base de datos, mostrar mensaje
                ?>
                <div class="no-products-message">
                    <h3>No hay productos disponibles en este momento</h3>
                    <p>Vuelve a consultar más tarde, estamos trabajando para agregar nuevos productos.</p>
                </div>
                <style>
                    .no-products-message {
                        text-align: center;
                        padding: 50px 20px;
                        background: #f8f9fa;
                        border-radius: 8px;
                        margin: 30px auto;
                        max-width: 600px;
                    }
                    .no-products-message i {
                        font-size: 48px;
                        color: #6c757d;
                        margin-bottom: 20px;
                    }
                    .no-products-message h3 {
                        margin-bottom: 10px;
                        color: #343a40;
                    }
                    .no-products-message p {
                        color: #6c757d;
                    }
                </style>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Sobre JerseyZone</h3>
                <p>Somos una tienda especializada en jerseys deportivos y casuales de alta calidad. Nuestro compromiso es ofrecer diseños únicos y materiales premium para nuestros clientes.</p>
            </div>
            <div class="footer-section">
                <h3>Preguntas Frecuentes</h3>
                <ul>
                    <li><a href="Preguntas_Frecuentes.html">Envíos y Entregas</a></li>
                    <li><a href="Preguntas_Frecuentes.html">Devoluciones</a></li>
                    <li><a href="Preguntas_Frecuentes.html">Métodos de Pago</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Generales</h3>
                <ul>
                    <li><a href="PoliticaDevolucion.html">Politica de Devoluciones</a></li>
                    <li><a href="aviso_privacidad.html">Aviso de Privacidad</a></li>
                    <li><a href="TerminosYcondicones.html">Terminos y Condiciones</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Newsletter</h3>
                <p>Suscríbete para recibir las últimas novedades y ofertas especiales.</p>
                <div class="newsletter-form">
                    <input type="email" placeholder="Tu correo electrónico" class="newsletter-input">
                    <button class="newsletter-button">Suscribirse</button>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="social-links">
                <a href="https://www.tiktok.com/@jersix.mx" class="social-link" target="_blank"><i class="fab fa-tiktok"></i></a>
                <a href="https://www.instagram.com/jersix.mx/" class="social-link" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://wa.me/+528129157795" class="social-link" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
            <p class="copyright">&copy; 2025 Jersix.mx. Todos los derechos reservados.</p>
        </div>
    </footer>
    <div class="whatsapp-button">
        <a href="https://wa.me/+528129157795" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>
    
    <div id="notification" class="notification"></div>
    
    <script>
        // Script mejorado para filtrar productos correctamente
        document.addEventListener('DOMContentLoaded', function() {
            // Obtener todos los checkboxes de filtros
            const categoryFilters = document.querySelectorAll('input[data-category]');
            const leagueFilters = document.querySelectorAll('input[data-league]');
            const sizeFilters = document.querySelectorAll('input[data-size]');
            
            // Obtener todos los productos
            const productCards = document.querySelectorAll('.product-card');
            
            // Agregar event listeners a los filtros
            categoryFilters.forEach(filter => {
                filter.addEventListener('change', applyFilters);
            });
            
            leagueFilters.forEach(filter => {
                filter.addEventListener('change', applyFilters);
            });
            
            sizeFilters.forEach(filter => {
                filter.addEventListener('change', applyFilters);
            });
            
            function applyFilters() {
                console.log('Aplicando filtros...');
                
                // Obtener categorías seleccionadas
                const selectedCategories = Array.from(categoryFilters)
                    .filter(filter => filter.checked)
                    .map(filter => filter.getAttribute('data-category'));
                
                console.log('Categorías seleccionadas:', selectedCategories);
                
                // Obtener ligas seleccionadas
                const selectedLeagues = Array.from(leagueFilters)
                    .filter(filter => filter.checked)
                    .map(filter => filter.getAttribute('data-league'));
                
                console.log('Ligas seleccionadas:', selectedLeagues);
                
                // Si no hay filtros seleccionados, mostrar todos los productos
                if (selectedCategories.length === 0 && selectedLeagues.length === 0) {
                    console.log('No hay filtros activos, mostrando todos los productos');
                    productCards.forEach(card => {
                        card.style.display = 'block';
                    });
                    
                    // Ocultar mensaje de "no hay resultados" si existe
                    const noResultsMsg = document.getElementById('no-results-message');
                    if (noResultsMsg) {
                        noResultsMsg.style.display = 'none';
                    }
                    
                    return;
                }
                
                // Filtrar productos
                let visibleCount = 0;
                
                productCards.forEach(card => {
                    // Para depuración
                    const cardCategory = card.getAttribute('data-category');
                    const cardType = card.getAttribute('data-type'); 
                    const cardLeague = card.getAttribute('data-league');
                    
                    console.log('Producto:', card.querySelector('h3').textContent);
                    console.log('Categoría:', cardCategory);
                    console.log('Tipo:', cardType);
                    console.log('Liga:', cardLeague);
                    
                    // Verificar si coincide con los filtros de categoría
                    let matchesCategory = true;
                    if (selectedCategories.length > 0) {
                        // Para categorías como 'local' y 'visitante', verificamos con data-type
                        if ((selectedCategories.includes('local') && cardType === 'local') ||
                            (selectedCategories.includes('visitante') && cardType === 'visitante')) {
                            matchesCategory = true;
                        }
                        // Para otras categorías como 'retro', 'especial', verificamos con data-category
                        else if (selectedCategories.includes(cardCategory)) {
                            matchesCategory = true;
                        }
                        else {
                            matchesCategory = false;
                        }
                    }
                    
                    console.log('¿Coincide con categoría?', matchesCategory);
                    
                    // Verificar si coincide con los filtros de liga
                    let matchesLeague = selectedLeagues.length === 0 || selectedLeagues.includes(cardLeague);
                    console.log('¿Coincide con liga?', matchesLeague);
                    
                    // Mostrar u ocultar el producto según los filtros
                    if (matchesCategory && matchesLeague) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                console.log('Productos visibles:', visibleCount);
                
                // Verificar si hay productos visibles, si no, mostrar mensaje
                if (visibleCount === 0) {
                    // Si no existe el mensaje, crearlo
                    let noResultsMsg = document.getElementById('no-results-message');
                    if (!noResultsMsg) {
                        noResultsMsg = document.createElement('div');
                        noResultsMsg.id = 'no-results-message';
                        noResultsMsg.className = 'no-products-message';
                        noResultsMsg.innerHTML = `
                            <h3>No hay productos con estos filtros</h3>
                            <p>Intenta con otra combinación de filtros.</p>
                        `;
                        document.querySelector('.products-container').appendChild(noResultsMsg);
                    } else {
                        noResultsMsg.style.display = 'block';
                    }
                } else {
                    // Si hay productos visibles, ocultar mensaje
                    const noResultsMsg = document.getElementById('no-results-message');
                    if (noResultsMsg) {
                        noResultsMsg.style.display = 'none';
                    }
                }
            }
        });
    </script>
</body>
</html>