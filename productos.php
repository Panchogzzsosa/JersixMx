<?php
require_once __DIR__ . '/config/database.php';
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newsletterForm = document.querySelector('.newsletter-form');
            const newsletterInput = document.querySelector('.newsletter-input');
            const newsletterButton = document.querySelector('.newsletter-button');

            function showNotification(message, isSuccess = true) {
                const notification = document.createElement('div');
                notification.className = `notification ${isSuccess ? 'success' : 'error'}`;
                notification.textContent = message;
                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.classList.add('show');
                }, 100);

                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 3000);
            }

            newsletterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = newsletterInput.value.trim();

                if (!email) {
                    showNotification('Por favor, ingrese un correo electrónico', false);
                    return;
                }

                newsletterButton.disabled = true;
                newsletterButton.textContent = 'Enviando...';

                fetch('save_newsletter.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'email=' + encodeURIComponent(email)
                })
                .then(response => response.json())
                .then(data => {
                    showNotification(data.message, data.success);
                    if (data.success) {
                        newsletterInput.value = '';
                    }
                })
                .catch(error => {
                    showNotification('Error al procesar la solicitud. Por favor, intente más tarde.', false);
                })
                .finally(() => {
                    newsletterButton.disabled = false;
                    newsletterButton.textContent = 'Suscribirse';
                });
            });
        });
    </script>
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
            <div class="filter-section">
                <h3>Tallas</h3>
                <ul>
                    <li><label><input type="checkbox" id="filter-size-s" data-size="S"> S</label></li>
                    <li><label><input type="checkbox" id="filter-size-m" data-size="M"> M</label></li>
                    <li><label><input type="checkbox" id="filter-size-l" data-size="L"> L</label></li>
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
                        <h3>Bayern de Múnich Local 24/25</h3>
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
    
</body>
</html>