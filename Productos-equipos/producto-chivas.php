<?php
// Database connection
require_once __DIR__ . '/../config/database.php';

try {

    // Get product stock and price from database
    $stmt = $pdo->prepare('SELECT stock, price FROM products WHERE name LIKE ?');
    $stmt->execute(['%Chivas%']);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $stock = $product ? $product['stock'] : 0;
    $price = $product ? $product['price'] : '799.00'; // Get price from database or use default

    // Newsletter subscription handling
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if ($email) {
            $checkStmt = $pdo->prepare('SELECT id FROM newsletter WHERE email = ?');
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este correo ya está suscrito.']);
            } else {
                $insertStmt = $pdo->prepare('INSERT INTO newsletter (email) VALUES (?)');
                $insertStmt->execute([$email]);
                echo json_encode(['success' => true, 'message' => '¡Gracias por suscribirte!']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Por favor, ingrese un correo válido.']);
        }
        exit;
    }
} catch(PDOException $e) {
    $stock = 0; // Default value if database connection fails
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'message' => 'Error en el servidor. Por favor, intente más tarde.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chivas Local 24/25 - JerseyZone</title>
    <link rel="stylesheet" href="../Css/index.css">
    <link rel="stylesheet" href="../Css/productos.css">
    <link rel="stylesheet" href="../Css/cart.css">
    <link rel="stylesheet" href="../Css/notificacion.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <script src="../Js/Producto-equipos.js" defer></script>
    <script src="../Js/search.js" defer></script>
    <script src="../Js/products-data.js" defer></script>
    <script src="../Js/cart.js" defer></script>
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

                fetch('../save_newsletter.php', {
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
    <style>
        /* Size Guide Styles */
        .size-guide-btn {
            background: none;
            border: none;
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 10px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .size-guide-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .size-guide-table th,
        .size-guide-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }

        .size-guide-table th {
            background-color: #f5f5f5;
        }
        .product-detail {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }

        .product-image-container {
            position: relative;
            overflow: hidden;
        }

        .product-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .product-image-container:hover .product-image {
            transform: none;
            cursor: default;
        }

        .product-thumbnails {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            border-radius: 4px;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            object-fit: cover;
        }

        .thumbnail:hover,
        .thumbnail.active {
            opacity: 1;
        }

        .product-info {
            padding: 1rem 0;
        }

        .product-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .product-price {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1.5rem;
        }

        .size-selector {
            margin-bottom: 2rem;
        }

        .size-selector h3 {
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .size-options {
            display: flex;
            gap: 1rem;
        }

        .size-option {
            width: 50px;
            height: 50px;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .size-option:hover {
            border-color: #333;
        }

        .size-option.selected {
            background: #333;
            color: white;
            border-color: #333;
        }

        .shipping-info {
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: #666;
        }

        .personalization {
            margin-bottom: 2rem;
        }

        .quantity-selector {
            margin-bottom: 2rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .quantity-input {
            width: 60px;
            height: 40px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .personalization select {
            width: 100%;
            padding: 0.8rem;
            margin-top: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .personalization-input {
            width: 100%;
            padding: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        #personalization-fields {
            margin-top: 1rem;
        }

        .add-to-cart-btn {
            width: 100%;
            padding: 1rem;
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .add-to-cart-btn:hover {
            background: #000;
        }

        @media (max-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr;
                
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
            <div class="logo"><img src="../img/LogoNav.png" alt="JerSix Logo"></div>
            <ul class="nav-links">
                <li></li>
                <li></li>
                <li></li>
                <li><a href="../index">Inicio</a></li>
                <li><a href="../productos">Productos</a></li>
                <li><a href="../mistery-box">Mistery Box</a></li>
                <li><a href="../giftcard" class="active">Giftcard</a></li>
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

    <main>
        <div class="product-detail">
            <div class="product-image-container">
                <img src="../img/Jerseys/ChivasLocal.jpg" alt="Chivas Jersey" class="product-image" id="mainImage" loading="lazy">
                <div class="product-thumbnails">
                    <img src="../img/Jerseys/ChivasLocal.jpg" alt="Chivas Jersey Front" class="thumbnail active" onclick="changeImage(this)" loading="lazy">
                    <img src="../img/Jerseys/Chivas2.jpg" alt="Chivas Jersey Back" class="thumbnail" onclick="changeImage(this)" loading="lazy">
                    <img src="../img/Jerseys/Chivas3.jpg" alt="Chivas Jersey Detail" class="thumbnail" onclick="changeImage(this)" loading="lazy">
                </div>
            </div>
            <div class="product-info">
                <h1 class="product-title">Chivas Local 24/25</h1>
                <p class="product-price" data-product-id="chivas">$ <?php echo $price; ?></p>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const priceElement = document.querySelector('.product-price');
                    const productId = priceElement.getAttribute('data-product-id');

                    function updatePrice() {
                        fetch(`../get_product_price.php?id=${productId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    priceElement.textContent = `$ ${parseFloat(data.price).toFixed(2)}`;
                                }
                            })
                            .catch(error => console.error('Error fetching price:', error));
                    }

                    // Update price initially
                    updatePrice();

                    // Update price every 30 seconds
                    setInterval(updatePrice, 30000);
                });
                </script>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const priceElement = document.querySelector('.product-price');
                    const productId = priceElement.getAttribute('data-product-id');

                    function updatePrice() {
                        fetch(`../get_product_price.php?id=${productId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    priceElement.textContent = `$ ${parseFloat(data.price).toFixed(2)}`;
                                }
                            })
                            .catch(error => console.error('Error fetching price:', error));
                    }

                    // Update price initially
                    updatePrice();

                    // Update price every 30 seconds
                    setInterval(updatePrice, 30000);
                });
                </script>
                <div class="shipping-info">
                    <p>Envío gratis a TODO MÉXICO 🇲🇽</p>
                </div>
                <div class="size-selector">
                    <h3>Talla <button class="size-guide-btn" onclick="document.getElementById('sizeGuideModal').style.display='block'">Guía de tallas</button></h3>
                    <div class="size-options">
                        <div class="size-option">S</div>
                        <div class="size-option">M</div>
                        <div class="size-option">L</div>
                    </div>
                </div>

                <!-- Size Guide Modal -->
                <div id="sizeGuideModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="document.getElementById('sizeGuideModal').style.display='none'">&times;</span>
                        <h2>Guía de Tallas</h2>
                        <table class="size-guide-table">
                            <thead>
                                <tr>
                                    <th>Talla</th>
                                    <th>Pecho (cm)</th>
                                    <th>Largo (cm)</th>
                                    <th>Hombros (cm)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>S</td>
                                    <td>96-101</td>
                                    <td>71</td>
                                    <td>44</td>
                                </tr>
                                <tr>
                                    <td>M</td>
                                    <td>101-106</td>
                                    <td>73</td>
                                    <td>46</td>
                                </tr>
                                <tr>
                                    <td>L</td>
                                    <td>106-111</td>
                                    <td>75</td>
                                    <td>48</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="quantity-selector">
                    <h3>Cantidad</h3>
                    <div class="quantity-controls">
                        <button class="quantity-btn minus">-</button>
                        <input type="number" class="quantity-input" value="1" min="1" max="<?php echo $stock; ?>">
                        <button class="quantity-btn plus">+</button>
                    </div>
                    <br>
                    <div class="stock-info" style="background-color: #f8f9fa; padding: 12px 16px; border-radius: 6px; margin-top: 10px; font-size: 0.95rem; color: #495057; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-box" style="color: #6c757d;"></i>
                        <span>Stock disponible: <strong><?php echo $stock; ?></strong> unidades</span>
                    </div>
                </div>
                <div class="personalization" style="display: none;">
                    <h3>Personalizar Camiseta</h3>
                    <select id="personalization-select" disabled>
                        <option value="none">Sin Personalizar</option>
                        <option value="custom">Personalizar</option>
                    </select>
                    <div id="personalization-fields" style="display: none;">
                        <input type="text" placeholder="Nombre en la camiseta" class="personalization-input" id="jersey-name" maxlength="20" disabled>
                        <input type="number" placeholder="Número" class="personalization-input" id="jersey-number" min="1" max="99" disabled>
                    </div>
                </div>
                <button class="add-to-cart-btn">Agregar al Carrito</button>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Sobre Jersix</h3>
                <p>Somos una tienda especializada en jerseys deportivos y casuales de alta calidad. Nuestro compromiso es ofrecer diseños únicos y materiales premium para nuestros clientes.</p>
            </div>
            <div class="footer-section">
                <h3>Preguntas Frecuentes</h3>
                <ul>
                    <li><a href="../Preguntas_Frecuentes.html">Envíos y Entregas</a></li>
                    <li><a href="../Preguntas_Frecuentes.html">Devoluciones</a></li>
                    <li><a href="../Preguntas_Frecuentes.html">Métodos de Pago</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Generales</h3>
                <ul>
                    <li><a href="../PoliticaDevolucion">Politica de Devoluciones</a></li>
                    <li><a href="../aviso_privacidad">Aviso de Privacidad</a></li>
                    <li><a href="../TerminosYcondicones">Terminos y Condiciones</a></li>
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