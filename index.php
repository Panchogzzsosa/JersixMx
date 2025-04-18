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
    <script src="Js/index.js"></script>
    <script src="Js/search.js"></script>
    <script src="Js/cart.js" defer></script>
    <script src="Js/cookie-consent.js"></script>
    <script src="Js/newsletter.js" defer></script>
    <style>
        .nombre {
            color:#606060;
        }

        .collection-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) {
            .collection-grid {
                display: flex;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .collection-item {
                flex: 0 0 auto;
                width: 80%;
                box-sizing: border-box;
            }
        }
    </style>
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
                <li><a href="mistery-box">Mystery Box</a></li>
                <li><a href="giftcard" class="active">Giftcard</a></li>
                <li><a href="tracking.php">Seguimiento</a></li>
            </ul>
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Buscar productos..." id="searchInput">
                <button class="search-button" onclick="performSearch(document.querySelector('.search-input').value)">
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
            <?php
            // Función para obtener productos aleatorios con caché
            function getRandomProducts($limit = 4) {
                $cacheFile = 'cache/random_products.json';
                $cacheExpiry = 1000; // 2 días en segundos (2 * 24 * 60 * 60)
                
                // Verificar si existe el directorio cache, si no, crearlo
                if (!file_exists('cache')) {
                    mkdir('cache', 0777, true);
                }
                
                // Verificar si existe el cache y no ha expirado
                if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheExpiry)) {
                    return json_decode(file_get_contents($cacheFile), true);
                }
                
                try {
                    // Conexión a la base de datos
                    $pdo = new PDO('mysql:host=216.245.211.58;dbname=jersixmx_checkout', 'jersixmx_usuario_total', '?O*6o6&Hs&~Q');
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Seleccionar productos aleatorios que estén activos y con stock
                    $stmt = $pdo->prepare("
                        SELECT * FROM products 
                        WHERE status = 1 
                        AND stock > 0 
                        ORDER BY RAND() 
                        LIMIT :limit
                    ");
                    
                    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                    $stmt->execute();
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Guardar en caché
                    file_put_contents($cacheFile, json_encode($products));
                    
                    return $products;
                } catch(PDOException $e) {
                    // En caso de error, retornar array vacío
                    return [];
                }
            }

            // Obtener los productos aleatorios
            $randomProducts = getRandomProducts(3);
            ?>
            <h2>Lo Más Vendido</h2>
            <div class="collection-grid">
                <?php foreach ($randomProducts as $product): ?>
                    <div class="collection-item">
                        <a href="Productos-equipos/producto.php?id=<?php echo $product['product_id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 loading="lazy">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        </a>
                    </div>
                <?php endforeach; ?>
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
                        <h3>Mystery Box</h3>
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
                            <th>Largo (cm)</th>
                            <th>Ancho (cm)</th>
                            <th>Altura (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>S</td>
                            <td>69-71</td>
                            <td>53-55</td>
                            <td>162-170</td>
                        </tr>
                        <tr>
                            <td>M</td>
                            <td>71-73</td>
                            <td>55-57</td>
                            <td>170-176</td>
                        </tr>
                        <tr>
                            <td>L</td>
                            <td>73-75</td>
                            <td>57-58</td>
                            <td>176-182</td>
                        </tr>
                        <tr>
                            <td>XL</td>
                            <td>75-78</td>
                            <td>58-60</td>
                            <td>182-190</td>
                        </tr>
                        <tr>
                            <td>XXL</td>
                            <td>78-81</td>
                            <td>60-62</td>
                            <td>190-195</td>
                        </tr>
                    </tbody>
                </table>
                <div class="size-guide-info">
                    <h3>¿Cómo medir?</h3>
                    <ul>
                        <li><strong>Largo:</strong> Mide desde el cuello hasta abajo de la jersey</li>
                        <li><strong>Ancho:</strong> Mide desde axila a axila, es decir, de un lado al otro del pecho.</li>
                        <li><strong>Altura:</strong> Altura de la persona que usaría esa talla</li>
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
                <a href="https://wa.me/+528129157795" class="social-link" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
            <p class="copyright">&copy; 2025 Jersix.mx. Todos los derechos reservados. | <a class="nombre" href="https://franciscogonzalez.netlify.app/" target="_blank">Francisco Gonzalez Sosa</a></p>
        </div>
    </footer>
    <div class="whatsapp-button">
        <a href="https://wa.me/+528129157795" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>
<div id="notification" class="notification"></div>
</body>
</html>