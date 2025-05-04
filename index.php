<!DOCTYPE html>
<?php
require_once 'config/database.php';

// Obtener el banner activo
try {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM banner_config WHERE activo = 1 LIMIT 1");
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener imágenes del banner ordenadas por posición
    $stmt = $pdo->query("SELECT * FROM banner_images ORDER BY FIELD(position, 'imagen1', 'imagen2', 'imagen3')");
    $bannerImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear array asociativo para fácil acceso
    $bannerPositions = [
        'imagen1' => null,
        'imagen2' => null,
        'imagen3' => null
    ];
    
    foreach ($bannerImages as $image) {
        $bannerPositions[$image['position']] = $image;
    }

    // Obtener productos destacados con posiciones específicas
    $stmt = $pdo->query("
        SELECT p.*, fp.position
        FROM products p
        INNER JOIN featured_products fp ON p.product_id = fp.product_id
        WHERE p.status = 1
        AND fp.position IN ('producto1', 'producto2', 'producto3')
        ORDER BY FIELD(fp.position, 'producto1', 'producto2', 'producto3')
    ");
    $featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear array asociativo para productos destacados
    $productPositions = [
        'producto1' => null,
        'producto2' => null,
        'producto3' => null
    ];

    // Asignar productos con posiciones específicas
    foreach ($featuredProducts as $product) {
        if (!empty($product['position'])) {
            $productPositions[$product['position']] = $product;
        }
    }
} catch(PDOException $e) {
    $banner = null;
    $bannerImages = [];
    $bannerPositions = [
        'imagen1' => null,
        'imagen2' => null,
        'imagen3' => null
    ];
    $productPositions = [
        'producto1' => null,
        'producto2' => null,
        'producto3' => null
    ];
}
?>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JersixMx</title>
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
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-2PPJD4LWKZ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-2PPJD4LWKZ');
    </script>
    <style>
        .nombre {
            color:#606060;
        }

        .collection-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .payment-methods {
            display: block;
            margin-top: 8px;
            opacity: 0.7;
        }
        
        .payment-methods i {
            margin: 0 3px;
            color: #888;
            transition: opacity 0.3s ease;
            font-size: 12px;
        }
        
        .payment-methods i:hover {
            opacity: 1;
        }

        /* Estilos para la sección de reseñas */
        .reviews-container {
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 0;
            width: 100%;
            align-items: center;
        }
        .reviews-slider {
            display: flex;
            gap: 25px;
            width: auto;
        }
        .review-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            border: 1.5px solid #000000;
            flex-shrink: 0;
            width: 370px;
            scroll-snap-align: start;
        }
        .review-card:hover {
            transform: translateY(-5px);
        }
        .review-header {
            margin-bottom: 20px;
            position: relative;
        }
        .review-info h3 {
            margin: 0;
            font-size: 1.1em;
            color: #333;
            font-weight: 500;
        }
        .product-name {
            color: #666;
            font-size: 0.9em;
            margin: 4px 0 0 0;
        }
        .review-stars {
            color: #FFD600;
            margin: 4px 0 0 0;
            font-size: 1em;
        }
        .review-title {
            color: #333;
            font-size: 1.1em;
            margin: 6px 0 4px 0;
            font-weight: 500;
        }
        .review-text {
            color: #666;
            line-height: 1.6;
            font-size: 0.95em;
            margin: 0 0 10px 0;
            word-break: break-word;
        }
        .review-images {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 5px 0;
        }
        .review-images img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: box-shadow 0.2s;
        }
        .review-images img:hover {
            box-shadow: 0 0 0 3px #FFD60044;
        }
        .review-date {
            color: #999;
            font-size: 0.85em;
            margin-top: auto;
            text-align: right;
            padding-top: 15px;
        }
        @media (max-width: 1024px) {
            .review-card {
                width: 320px;
            }
        }
        @media (max-width: 768px) {
            .review-card {
                width: 90vw;
                min-width: 260px;
                max-width: 98vw;
                padding: 20px;
                scroll-snap-align: center;
            }
            
            .review-images img {
                width: 100px;
                height: 100px;
            }
        }
        @media (max-width: 480px) {
            .reviews-container {
                padding: 15px;
            }

            .review-images img {
                width: 80px;
                height: 80px;
            }

            .review-title {
                font-size: 1em;
            }

            .review-text {
                font-size: 0.9em;
            }
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
            .payment-methods {
                margin-top: 5px;
            }
            .payment-methods i {
                margin: 0 2px;
                font-size: 11px;
            }
            .site-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1002;
                width: 100vw;
                transition: transform 0.3s;
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
                border-bottom: none !important;
            }
            .site-banner.hide {
                transform: translateY(-100%);
            }
            .navbar {
                position: fixed;
                left: 0;
                right: 0;
                z-index: 1001;
                top: 0;
                transition: top 0.3s;
                margin-top: 0 !important;
                box-shadow: none !important;
                border-top: none !important;
            }
            main {
                margin-top: 0 !important;
                transition: margin-top 0.2s;
            }
            main.banner-visible {
                /* margin-top eliminado, ahora será dinámico por JS */
            }
        }

        .reviews {
            padding: 30px 0;
        }

        .reviews h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.5em;
            color: #333;
        }

        .best-sellers h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8em;
            color: #333;
        }

        /* Lightbox */
        .lightbox-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }
        .lightbox-overlay.active {
            display: flex;
        }
        .lightbox-img {
            max-width: 90vw;
            max-height: 80vh;
            border-radius: 10px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.4);
            background: #fff;
        }
        .lightbox-close {
            position: absolute;
            top: 30px;
            right: 40px;
            font-size: 2.5em;
            color: #fff;
            background: none;
            border: none;
            cursor: pointer;
            z-index: 10000;
        }
        .review-recomienda {
            margin: 4px 0 0 0;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <?php if ($banner): ?>
    <div class="site-banner" style="background-color: <?php echo htmlspecialchars($banner['color_fondo']); ?>; color: <?php echo htmlspecialchars($banner['color_texto']); ?>; padding: 10px 0; text-align: center; width: 100%; position: relative; z-index: 1000;">
        <div class="banner-content">
            <span>
                <?php 
                $mensaje = htmlspecialchars($banner['mensaje']);
                echo $mensaje . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $mensaje . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $mensaje . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; '; 
                ?>
            </span>
            <span>
                <?php 
                echo $mensaje . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $mensaje . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $mensaje . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; '; 
                ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
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
                <li><a href="mistery-box">Mystery Pack</a></li>
                <li><a href="giftcard" class="active">Gift Cards</a></li>
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
                <?php
                // Imágenes por defecto
                $defaultImages = [
                    'imagen1' => 'img/Imagen1.png',
                    'imagen2' => 'img/Imagen2.png',
                    'imagen3' => 'img/Imagen3.png'
                ];

                foreach (['imagen1', 'imagen2', 'imagen3'] as $position):
                    $isActive = $position === 'imagen1' ? 'active' : '';
                    // Usar la imagen subida si existe, si no, usar la imagen por defecto
                    $imageUrl = isset($bannerPositions[$position]) && !empty($bannerPositions[$position]['image_url']) 
                               ? $bannerPositions[$position]['image_url'] 
                               : $defaultImages[$position];
                ?>
                    <div class="carousel-slide <?php echo $isActive; ?>">
                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                             alt="Banner <?php echo ucfirst(str_replace('imagen', '', $position)); ?>" 
                             loading="lazy">
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="best-sellers">
            <h2>Lo Más Vendido</h2>
            <div class="collection-grid">
                <?php foreach ($productPositions as $position => $product): ?>
                    <?php if ($product): ?>
                    <div class="collection-item">
                        <a href="Productos-equipos/producto.php?id=<?php echo $product['product_id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 loading="lazy">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        </a>
                    </div>
                    <?php endif; ?>
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
                    <a href="productos.php?filter=retro">
                        <img src="img/AlemaniaRetro.webp" alt="Colección Retro">
                        <h3>Colección Retro</h3>
                    </a>
                </div>
                <div class="collection-item">
                    <a href="productos.php">
                    <img src="img/BarcelonaV1.jpg" alt="Jerseys 2024/2025" loading="lazy">
                    <h3>Jerseys 2024/2025</h3>
                 </a>
                </div>
                <div class="collection-item">
                    <a href="mistery-box">
                        <img src="img/MysteryBox.webp" alt="Mystery Box" loading="lazy">
                        <h3>Mystery Box</h3>
                    </a>
                </div>
            </div>
        </section>

        <section class="reviews">
            <h2>Reseñas de Nuestros Clientes</h2>
            <div class="reviews-container">
                <div class="reviews-slider">
                    <?php
                    try {
                        $pdo = getConnection();
                        $stmt = $pdo->query("SELECT r.*, p.name as producto_nombre 
                                           FROM resenas r 
                                           JOIN products p ON r.producto_id = p.product_id 
                                           ORDER BY r.fecha_creacion DESC");
                        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($reviews as $review):
                            $stars = '';
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $review['calificacion']) {
                                    $stars .= '<i class="fas fa-star"></i>';
                                } else if ($i - 0.5 <= $review['calificacion']) {
                                    $stars .= '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    $stars .= '<i class="far fa-star"></i>';
                                }
                            }
                    ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="review-info">
                                    <h3><?php echo htmlspecialchars($review['nombre']); ?></h3>
                                    <p class="product-name">
                                        <a href="Productos-equipos/producto.php?id=<?php echo $review['producto_id']; ?>" style="color: #666; text-decoration: underline; cursor: pointer;">
                                            <?php echo htmlspecialchars($review['producto_nombre']); ?>
                                        </a>
                                    </p>
                                    <div class="review-stars">
                                        <?php echo $stars; ?>
                                    </div>
                                    <?php if (isset($review['recomienda'])): ?>
                                        <div class="review-recomienda">
                                            <?php if ($review['recomienda']): ?>
                                                <span title="Recomendado" style="color: #43a047; font-size: 1em;"><i class="fas fa-thumbs-up"></i> Recomendado</span>
                                            <?php else: ?>
                                                <span title="No recomendado" style="color: #e53935; font-size: 1em;"><i class="fas fa-thumbs-down"></i> No recomendado</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h4 class="review-title"><?php echo htmlspecialchars($review['titulo']); ?></h4>
                            <p class="review-text"><?php echo htmlspecialchars($review['contenido']); ?></p>
                            <?php if ($review['imagen_path']): ?>
                                <div class="review-images">
                                    <?php 
                                    $images = json_decode($review['imagen_path'], true);
                                    if (is_array($images)) {
                                        foreach ($images as $image) {
                                            echo '<img src="' . htmlspecialchars($image) . '" alt="Reseña de ' . htmlspecialchars($review['nombre']) . '">';
                                        }
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                            <p class="review-date"><?php echo date('d/m/Y', strtotime($review['fecha_creacion'])); ?></p>
                        </div>
                    <?php 
                        endforeach;
                    } catch(PDOException $e) {
                        echo '<p class="error">No se pudieron cargar las reseñas en este momento.</p>';
                    }
                    ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Sobre JersixMx</h3>
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
            <p class="copyright">
                &copy; 2025 Jersix.mx. Todos los derechos reservados. | 
                <a class="nombre" href="https://franciscogonzalez.netlify.app/" target="_blank">Francisco Gonzalez Sosa</a>
                <span class="payment-methods">
                    <i class="fab fa-cc-visa" title="Visa"></i>
                    <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                    <i class="fab fa-cc-amex" title="American Express"></i>
                    <i class="fab fa-paypal" title="PayPal"></i>
                    <i class="fas fa-university" title="SPEI - Transferencia Bancaria"></i>
                </span>
            </p>
        </div>
    </footer>
    <div class="whatsapp-button">
        <a href="https://wa.me/+528129157795" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>
<div id="notification" class="notification"></div>
<div id="lightbox" class="lightbox-overlay">
    <button class="lightbox-close" aria-label="Cerrar">&times;</button>
    <img src="" alt="Imagen ampliada" class="lightbox-img" />
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var banner = document.querySelector('.site-banner');
        var navbar = document.querySelector('.navbar');
        var main = document.querySelector('main');
        var heroCarousel = document.querySelector('.hero-carousel');
        function adjustHeroCarouselMargin() {
            if (!navbar || !heroCarousel) return;
            if (window.innerWidth <= 768) {
                var navbarHeight = navbar.offsetHeight || 60;
                if (banner) {
                    var bannerVisible = !banner.classList.contains('hide');
                    var bannerHeight = bannerVisible ? banner.offsetHeight : 0;
                    navbar.style.top = bannerVisible ? bannerHeight + 'px' : '0px';
                } else {
                    navbar.style.top = '0px';
                }
                heroCarousel.style.marginTop = navbarHeight + 'px';
                if(main) main.style.marginTop = '0px';
            } else {
                heroCarousel.style.marginTop = '0px';
                if(navbar) navbar.style.top = '';
                if(main) main.style.marginTop = '0px';
            }
        }
        function handleScrollAndBanner() {
            if (banner) {
                if (window.scrollY > 10) {
                    banner.classList.add('hide');
                    if(navbar) navbar.classList.remove('banner-visible');
                    if(main) main.classList.remove('banner-visible');
                } else {
                    banner.classList.remove('hide');
                    if(navbar) navbar.classList.add('banner-visible');
                    if(main) main.classList.add('banner-visible');
                }
            }
            adjustHeroCarouselMargin();
        }
        handleScrollAndBanner();
        window.addEventListener('scroll', handleScrollAndBanner);
        window.addEventListener('resize', handleScrollAndBanner);
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.reviews-container');
        const cards = document.querySelectorAll('.review-card');
        let currentIndex = 0;
        function getVisibleCards() {
            if(window.innerWidth <= 768) return 1;
            if(window.innerWidth <= 1024) return 2;
            return 3;
        }
        function autoMove() {
            const visibleCards = getVisibleCards();
            const totalCards = cards.length;
            const maxIndex = Math.max(0, totalCards - visibleCards);
            if (totalCards > visibleCards && currentIndex < maxIndex) {
                currentIndex++;
                const card = cards[currentIndex];
                if(card) {
                    const left = card.offsetLeft - container.offsetLeft;
                    container.scrollTo({left, behavior: 'smooth'});
                }
            }
        }
        window.addEventListener('resize', () => {
            currentIndex = 0;
            if(cards[0]) {
                const left = cards[0].offsetLeft - container.offsetLeft;
                container.scrollTo({left, behavior: 'smooth'});
            }
        });
        setInterval(autoMove, 5000);
    });
</script>
<script>
    // Lightbox para imágenes de reseñas
    document.addEventListener('DOMContentLoaded', function() {
        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.querySelector('.lightbox-img');
        const closeBtn = document.querySelector('.lightbox-close');
        document.querySelectorAll('.review-images img').forEach(img => {
            img.addEventListener('click', function() {
                lightbox.classList.add('active');
                lightboxImg.src = this.src;
                lightboxImg.alt = this.alt;
            });
        });
        closeBtn.addEventListener('click', function() {
            lightbox.classList.remove('active');
            lightboxImg.src = '';
        });
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                lightbox.classList.remove('active');
                lightboxImg.src = '';
            }
        });
    });
</script>
</body>
</html>