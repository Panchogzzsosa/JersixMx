<?php
require_once __DIR__ . '/config/database.php';

// Obtener todos los productos de la base de datos
try {
    $pdo = getConnection();
    
    // Obtener categorías para los filtros
    $categories = ['Equipos', 'Retro', 'Selecciones'];
    
    // Consulta para obtener todos los productos
    $query = "SELECT * FROM products ORDER BY name ASC";
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
        if (strpos(strtolower($productCategory), 'retro') !== false) {
            return 'retro';
        } elseif (strpos(strtolower($productCategory), 'selecciones') !== false) {
            return 'selecciones';
        } else {
            return 'local';
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
    <script src="Js/productos.js" defer></script>
    <script src="Js/search.js" defer></script>
    <script src="Js/products-data.js" defer></script>
    <script src="Js/cart.js" defer></script>
    <script src="Js/newsletter.js" defer></script>
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
                <li><a href="mistery-box">Mistery Box</a></li>
                <li><a href="giftcard" class="active">Giftcard</a></li>
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
                    <li><label><input type="checkbox" id="filter-selecciones" data-league="serieA"> Serie A</label></li>
                    <li><label><input type="checkbox" id="filter-selecciones" data-league="bundesliga"> Bundesliga</label></li>
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

            <div class="products-container">
                <?php 
                // Mostrar productos desde la base de datos
                if (!empty($db_products)): 
                    foreach ($db_products as $product): 
                        // Determinar categoría y liga para filtros
                        $productCategory = getProductCategory($product['category']);
                        $productType = getProductType($product['name']);
                        $productLeague = getProductLeague($product['name']);
                        
                        // Generar URL del producto
                        $productUrl = generateProductUrl($product['name'], $product['product_id']);
                ?>
                <div class="product-card" data-category="<?php echo $productType; ?>" data-league="<?php echo $productLeague; ?>">
                    <a href="Productos-equipos/<?php echo $productUrl; ?>">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="price" data-product-id="<?php echo $product['product_id']; ?>">$ <?php echo number_format($product['price'], 2); ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <?php 
                    endforeach; 
                else: 
                    // Si no hay productos en la base de datos, mostrar los productos estáticos originales 
                ?>
                <div class="product-card" data-category="local" data-league="laliga">
                    <a href="Productos-equipos/producto-real-madrid">
                        <img src="img/Jerseys/RealMadridLocal.jpg" alt="Real Madrid Jersey" loading="lazy">
                        <h3>Real Madrid Local 24/25</h3>
                        <p class="price" data-product-id="real_madrid">$ <?php echo isset($products['Real Madrid Local 24/25']) ? number_format($products['Real Madrid Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="laliga">
                    <a href="Productos-equipos/producto-barca">
                        <img src="img/Jerseys/BarcelonaLocal.jpg" alt="Barcelona Jersey" loading="lazy">
                        <h3>Barcelona Local 24/25</h3>
                        <p class="price" data-product-id="barcelona">$ <?php echo isset($products['Barcelona Local 24/25']) ? number_format($products['Barcelona Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="premier">
                    <a href="Productos-equipos/producto-manchester-city">
                        <img src="img/Jerseys/ManchesterCity.png" alt="Manchester City" loading="lazy">
                        <h3>Manchester City Local 24/25</h3>
                        <p class="price" data-product-id="manchester_city">$ <?php echo isset($products['Manchester City Local 24/25']) ? number_format($products['Manchester City Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="bundesliga">
                    <a href="Productos-equipos/producto-bayern-munchen">
                        <img src="img/Jerseys/BayerMunchenLocal.jpg" alt="Bayern de Múnich Jersey" loading="lazy">
                        <h3>Bayern Múnich Local 24/25</h3>
                        <p class="price" data-product-id="bayern_munich">$ <?php echo isset($products['Bayern de Múnich Local 24/25']) ? number_format($products['Bayern de Múnich Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="serieA">
                    <a href="Productos-equipos/producto-ac-milan">
                        <img src="img/Jerseys/MilanLocal.png" alt="AC Milan" loading="lazy">
                        <h3>AC Milan Local 24/25</h3>
                        <p class="price" data-product-id="ac_milan">$ <?php echo isset($products['AC Milan Local 24/25']) ? number_format($products['AC Milan Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="ligue1">
                    <a href="Productos-equipos/producto-Psg">
                        <img src="img/Jerseys/PSGLocal.jpg" alt="PSG" loading="lazy">
                        <h3>PSG Local 24/25</h3>
                        <p class="price" data-product-id="psg">$ <?php echo isset($products['PSG Local 24/25']) ? number_format($products['PSG Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="ligamx">
                    <a href="Productos-equipos/producto-rayados">
                        <img src="img/Jerseys/RayadosLocal.jpg" alt="Rayados Jersey" loading="lazy">
                        <h3>Rayados Local 24/25</h3>
                        <p class="price" data-product-id="rayados">$ <?php echo isset($products['Rayados Local 24/25']) ? number_format($products['Rayados Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="ligamx">
                    <a href="Productos-equipos/producto-tigres">
                        <img src="img/Jerseys/TigresLocal.jpg" alt="Tigres Jersey" loading="lazy">
                        <h3>Tigres Local 24/25</h3>
                        <p class="price" data-product-id="tigres">$ <?php echo isset($products['Tigres Local 24/25']) ? number_format($products['Tigres Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="ligamx">
                    <a href="Productos-equipos/producto-america">
                        <img src="img/Jerseys/AmericaLocal.jpg" alt="América Jersey" loading="lazy">
                        <h3>América Local 24/25</h3>
                        <p class="price" data-product-id="america">$ <?php echo isset($products['América Local 24/25']) ? number_format($products['América Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="ligamx">
                    <a href="Productos-equipos/producto-chivas">
                        <img src="img/Jerseys/ChivasLocal.jpg" alt="Chivas Jersey" loading="lazy">
                        <h3>Chivas Local 24/25</h3>
                        <p class="price" data-product-id="chivas">$ <?php echo isset($products['Chivas Local 24/25']) ? number_format($products['Chivas Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card" data-category="local" data-league="ligamx">
                    <a href="Productos-equipos/producto-cruzazul">
                        <img src="img/Jerseys/CruzAzulLocal.jpg" alt="Cruz Azul Jersey" loading="lazy">
                        <h3>Cruz Azul Local 24/25</h3>
                        <p class="price" data-product-id="cruz_azul">$ <?php echo isset($products['Cruz Azul Local 24/25']) ? number_format($products['Cruz Azul Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
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
                <a href="https://wa.me/+528123584236" class="social-link" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
            <p class="copyright">&copy; 2025 Jersix.mx. Todos los derechos reservados.</p>
        </div>
    </footer>
    <div class="whatsapp-button">
        <a href="https://wa.me/+528123584236" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>
    
    <div id="notification" class="notification"></div>
    
    <script>
        // Script mejorado para filtrar productos correctamente
        document.addEventListener('DOMContentLoaded', function() {
            // Obtener todos los checkboxes de filtros
            const categoryFilters = document.querySelectorAll('[data-category]');
            const leagueFilters = document.querySelectorAll('[data-league]');
            const sizeFilters = document.querySelectorAll('[data-size]');
            
            // Obtener todos los productos
            const productCards = document.querySelectorAll('.product-card');
            
            // Función para aplicar filtros
            function applyFilters() {
                // Obtener filtros activos
                const activeCategories = Array.from(categoryFilters)
                    .filter(filter => filter.checked)
                    .map(filter => filter.getAttribute('data-category'));
                    
                const activeLeagues = Array.from(leagueFilters)
                    .filter(filter => filter.checked)
                    .map(filter => filter.getAttribute('data-league'));
                    
                const activeSizes = Array.from(sizeFilters)
                    .filter(filter => filter.checked)
                    .map(filter => filter.getAttribute('data-size'));
                
                // Si no hay filtros activos, mostrar todos los productos
                if (activeCategories.length === 0 && activeLeagues.length === 0 && activeSizes.length === 0) {
                    productCards.forEach(card => {
                        card.style.display = 'block';
                    });
                    return;
                }
                
                // Filtrar productos
                productCards.forEach(card => {
                    const cardCategory = card.getAttribute('data-category');
                    const cardLeague = card.getAttribute('data-league');
                    
                    // Lógica para verificar si cumple con los filtros
                    let matchesCategory = activeCategories.length === 0 || activeCategories.includes(cardCategory);
                    let matchesLeague = activeLeagues.length === 0 || activeLeagues.includes(cardLeague);
                    
                    // Las tallas se manejarían con JavaScript adicional si estuvieran en la base de datos
                    let matchesSize = activeSizes.length === 0 ? true : false;
                    // Si hay tallas seleccionadas, verificar si el producto tiene esta información
                    // Esto es un ejemplo, ajusta según tu implementación
                    if (activeSizes.length > 0) {
                        // Aquí asumimos que todas las camisetas están disponibles en todas las tallas
                        // Esto debería ajustarse según tu implementación real
                        matchesSize = true;
                    }
                    
                    // Mostrar u ocultar producto
                    if (matchesCategory && matchesLeague && matchesSize) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Verificar si hay productos visibles, si no, mostrar mensaje
                const visibleProducts = document.querySelectorAll('.product-card[style="display: block;"]');
                const noResultsMessage = document.getElementById('no-results-message');
                
                if (visibleProducts.length === 0) {
                    // Si no existe el mensaje, crearlo
                    if (!noResultsMessage) {
                        const message = document.createElement('div');
                        message.id = 'no-results-message';
                        message.className = 'no-results';
                        message.textContent = 'No se encontraron productos con los filtros seleccionados.';
                        
                        const productsContainer = document.querySelector('.products-container');
                        productsContainer.appendChild(message);
                    } else {
                        noResultsMessage.style.display = 'block';
                    }
                } else if (noResultsMessage) {
                    noResultsMessage.style.display = 'none';
                }
            }
            
            // Añadir event listeners a los filtros
            categoryFilters.forEach(filter => {
                filter.addEventListener('change', applyFilters);
            });
            
            leagueFilters.forEach(filter => {
                filter.addEventListener('change', applyFilters);
            });
            
            sizeFilters.forEach(filter => {
                filter.addEventListener('change', applyFilters);
            });
            
            // Añadir estilos para el mensaje sin resultados
            const style = document.createElement('style');
            style.textContent = `
                .no-results {
                    width: 100%;
                    padding: 20px;
                    text-align: center;
                    background-color: #f8f9fa;
                    border-radius: 5px;
                    margin: 20px 0;
                    color: #6c757d;
                    font-size: 16px;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>