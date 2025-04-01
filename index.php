<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JerSix - Tu Tienda de Jerseys</title>
    <link rel="stylesheet" href="Css/index.css">
    <link rel="stylesheet" href="Css/cart.css">
    <link rel="stylesheet" href="Css/notificacion.css">
    <link rel="stylesheet" href="Css/cookie-consent.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="img/ICON.png" type="image/x-icon">
    <script src="Js/products-data.js"></script>
    <script src="Js/index.js"></script>
    <script src="Js/search.js"></script>
    <script src="Js/cart.js" defer></script>
    <script src="Js/cookie-consent.js"></script>
    <script src="Js/newsletter.js" defer></script>

</head>
<body>
    <header>
        <nav class="navbar">
            <button class="menu-toggle">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="logo"><img src="img/LogoNav.png" alt="JerSix Logo"></div>
            <ul class="nav-links">
                <li></li>
                <li></li>
                <li></li>
                <li><a href="index" id="pagina_actual">Inicio</a></li>
                <li><a href="productos">Productos</a></li>
                <li><a href="mistery-box">Mistery Box</a></li>
                <li><a href="giftcard" class="active">Giftcard</a></li>
            </ul>
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Buscar productos..." id="searchInput">
                <button class="search-button" onclick="performSearch()">
                    <span class="material-symbols-outlined">search</span>
                </button>
                <div class="search-results" id="searchResults"></div>
            </div>
            <div class="cart-icon">
                <a href="#" onclick="toggleCart()">
                    <span class="material-symbols-outlined">shopping_cart</span>
                </a>
            </div>
        </nav>
    </header>


    <main>
        <section class="hero-carousel">
            <div class="carousel-container">
                <div class="carousel-slide">
                    <img src="img/Imagen1.jpg" alt="Real Madrid Jersey" loading="lazy">
                    <div class="carousel-content">
                        <h1></h1>
                        <p></p>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="img/Imagen2.jpg" alt="Barcelona Jersey" loading="lazy">
                    <div class="carousel-content">
                        <h1></h1>
                        <p></p>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="img/Imagen3.jpg" alt="Manchester City Jersey" loading="lazy">
                    <div class="carousel-content">
                        <h1></h1>
                        <p></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="best-sellers">
            <h2>Los Más Vendidos</h2>
            <div class="products-carousel">
                <div class="product-card">
                    <a href="Productos-equipos/producto-real-madrid">
                        <img src="img/Jerseys/RealMadridLocal.jpg" alt="Real Madrid Local 24/25" loading="lazy">
                        <h3>Real Madrid Local 24/25</h3>
                        <p class="price" data-product-id="real_madrid">$ <?php echo isset($products['Real Madrid Local 24/25']) ? number_format($products['Real Madrid Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card">
                    <a href="Productos-equipos/producto-barca">
                        <img src="img/Jerseys/BarcelonaLocal.jpg" alt="Barcelona Local 24/25" loading="lazy">
                        <h3>Barcelona Local 24/25</h3>
                        <p class="price" data-product-id="barcelona">$ <?php echo isset($products['Barcelona Local 24/25']) ? number_format($products['Barcelona Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
                <div class="product-card">
                    <a href="Productos-equipos/producto-tigres">
                        <img src="img/LoMasVendido/Tigres.jpeg" alt="Tigres Local 24/25" loading="lazy">
                        <h3>Tigres Local 24/25</h3>
                        <p class="price" data-product-id="tigres">$ <?php echo isset($products['Tigres Local 24/25']) ? number_format($products['Tigres Local 24/25'], 2) : '799.00'; ?></p>
                        <button class="add-to-cart">Ver Producto</button>
                    </a>
                </div>
            </div>
        </section>

        <section class="testimonials">
            <h2>¿Por Qué Elegirnos?</h2>
            <div class="testimonials-container">
                <div class="testimonial-card">
                    <span class="material-symbols-outlined" style="font-size: 48px; color: #333;">verified</span>
                    <h3>Calidad Premium</h3>
                    <p class="testimonial-text">Utilizamos los mejores materiales y técnicas de confección para garantizar la durabilidad y comodidad de nuestros jerseys.</p>
                </div>
                <div class="testimonial-card">
                    <span class="material-symbols-outlined" style="font-size: 48px; color: #333;">palette</span>
                    <h3>Diseños Exclusivos</h3>
                    <p class="testimonial-text">Cada diseño es creado cuidadosamente por nuestro equipo de diseñadores para ofrecerte piezas únicas y originales.</p>
                </div>
                <div class="testimonial-card">
                    <span class="material-symbols-outlined" style="font-size: 48px; color: #333;">local_shipping</span>
                    <h3>Envío Rápido</h3>
                    <p class="testimonial-text">Garantizamos entregas rápidas y seguras para que disfrutes de tu nueva prenda lo antes posible.</p>
                </div>
            </div>
        </section>

        <section class="featured-collection">
            <h2>Colección Destacada</h2>
            <div class="collection-grid">
                <div class="collection-item">
                    <a href="productos.php">
                    <img src="img/Retro/AmericaRetro.jpg" alt="Colección Retro América" loading="lazy">
                    <h3>Colección Retro</h3>
                    </a>
                </div>
                <div class="collection-item">
                    <a href="productos.php">
                    <img src="img/Jerseys/RayadosLocal.jpg" alt="Colección Retro Manchester United" loading="lazy">
                    <h3>Jerseys 2024/2025</h3>
                 </a>
                </div>
                <div class="collection-item">
                    <a href="mistery-box">
                        <img src="img/MysteryBox.webp" alt="Colección Retro Japón" loading="lazy">
                        <h3>Mistery Box</h3>
                    </a>
                </div>
            </div>
        </section>

        <section class="size-guide">
            <h2>Guía de Tallas</h2>
            <div class="size-guide-container">
                <table class="size-table">
                    <thead>
                        <tr>
                            <th>Talla</th>
                            <th>Pecho (cm)</th>
                            <th>Largo (cm)</th>
                            <th>Manga (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>S</td>
                            <td>96-101</td>
                            <td>71</td>
                            <td>20</td>
                        </tr>
                        <tr>
                            <td>M</td>
                            <td>101-106</td>
                            <td>73</td>
                            <td>21</td>
                        </tr>
                        <tr>
                            <td>L</td>
                            <td>106-111</td>
                            <td>75</td>
                            <td>22</td>
                        </tr>
                    </tbody>
                </table>
                <div class="size-guide-info">
                    <h3>¿Cómo medir?</h3>
                    <ul>
                        <li><strong>Pecho:</strong> Mide alrededor de la parte más ancha del pecho</li>
                        <li><strong>Largo:</strong> Mide desde el hombro hasta la cadera</li>
                        <li><strong>Manga:</strong> Mide desde el hombro hasta el final de la manga</li>
                    </ul>
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
        <a href="https://wa.me/" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>
<div id="notification" class="notification"></div>
</body>
</html>