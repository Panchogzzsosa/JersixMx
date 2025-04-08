<?php
session_start();
require_once 'config/database.php';

// Clase para gestionar el rastreo de DHL
class DHLTracker {
    private $apiKey; 
    private $baseUrl = 'https://api-eu.dhl.com/track/shipments';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Obtiene la información de rastreo para un número de guía
     * @param string $trackingNumber Número de guía
     * @return array Información de rastreo
     */
    public function trackShipment($trackingNumber) {
        $url = $this->baseUrl . '?trackingNumber=' . urlencode($trackingNumber);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'DHL-API-Key: ' . $this->apiKey,
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            curl_close($ch);
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . curl_error($ch)
            ];
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode != 200) {
            return [
                'success' => false,
                'error' => isset($data['detail']) ? $data['detail'] : 'Error en la API de DHL (Código: ' . $httpCode . ')'
            ];
        }
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
}

// Configurar variables
$apiKey = 'ApiKeyAuth'; // Reemplazar con la clave API real de DHL
$trackingNumber = isset($_GET['tracking']) ? trim($_GET['tracking']) : '';
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$error = '';
$trackingInfo = null;
$orderInfo = null;

// Al inicio de la página, después de inicializar las variables
// Añadir una variable para verificar si es una búsqueda inicial o no
$isInitialSearch = empty($trackingNumber);

try {
    $pdo = getConnection();
    
    // Si tenemos un ID de orden, obtener los detalles
    if ($orderId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $orderInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si la orden tiene número de seguimiento, usarlo
        if ($orderInfo && !empty($orderInfo['tracking_number'])) {
            $trackingNumber = $orderInfo['tracking_number'];
        }
    }
    
    // Realizar seguimiento si hay un número
    if (!empty($trackingNumber)) {
        $tracker = new DHLTracker($apiKey);
        $result = $tracker->trackShipment($trackingNumber);
        
        if ($result['success']) {
            $trackingInfo = $result['data'];
        } else {
            $error = $result['error'];
        }
    }
    
} catch (Exception $e) {
    $error = "Error al conectar con la base de datos: " . $e->getMessage();
}

// Función para convertir fechas a formato legible
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('d/m/Y H:i');
}

// Función para obtener nombre del estado en español
function getStatusName($status) {
    $statusMap = [
        'DELIVERED' => 'Entregado',
        'IN_TRANSIT' => 'En tránsito',
        'SHIPPING' => 'Enviando',
        'CREATED' => 'Creado',
        'PICKED_UP' => 'Recogido',
        'OUT_FOR_DELIVERY' => 'En reparto',
        'SHIPMENT_RECEIVED' => 'Envío recibido',
        'DEPARTED' => 'Salida',
        'ARRIVED' => 'Llegada',
        'CUSTOMS' => 'En aduana',
        'EXCEPTION' => 'Excepción'
    ];
    
    return isset($statusMap[$status]) ? $statusMap[$status] : $status;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreo de Pedido | Jersix</title>
    <link rel="stylesheet" href="Css/index.css">
    <link rel="stylesheet" href="Css/cart.css">
    <link rel="stylesheet" href="Css/notificacion.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="shortcut icon" href="img/ICON.png" type="image/x-icon">
    <script src="Js/index.js"></script>
    <script src="Js/search.js"></script>
    <script src="Js/cart.js" defer></script>
    <script src="Js/newsletter.js" defer></script>
    <style>
        /* Solo estilos para el contenido específico de tracking */
        :root {
            --primary-color: #cc0000;
            --primary-hover: #aa0000;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --text-light: #666;
            --border-color: #eee;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --error-color: #dc3545;
            --box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        /* Contenedor de seguimiento */
        .tracking-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .page-header h1 {
            font-size: 32px;
            margin-bottom: 15px;
            color: var(--text-color);
            font-weight: 600;
        }
        
        .page-header p {
            color: var(--text-light);
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .page-header::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background-color: var(--primary-color);
            margin: 20px auto 0;
            border-radius: 2px;
        }
        
        /* Formulario de seguimiento */
        .search-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 40px;
            border-top: 4px solid var(--primary-color);
        }
        
        .search-card-body {
            padding: 30px;
        }
        
        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-input-group {
            flex: 1;
            min-width: 200px;
        }
        
        .search-input-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 16px;
        }
        
        .search-input {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(204, 0, 0, 0.1);
            outline: none;
        }
        
        .search-button {
            background-color: #000000;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: auto;
        }
        
        .search-button:hover {
            background-color: #333333;
            transform: translateY(-2px);
        }
        
        .search-button i {
            font-size: 18px;
        }
        
        /* Mensaje inicial */
        .welcome-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            padding: 40px;
            text-align: center;
            margin-bottom: 40px;
            border-top: 4px solid var(--primary-color);
        }
        
        .welcome-icon {
            font-size: 60px;
            color: var(--primary-color);
            margin-bottom: 25px;
        }
        
        .welcome-card h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--text-color);
        }
        
        .welcome-card p {
            color: var(--text-light);
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .info-box {
            background-color: var(--secondary-color);
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            text-align: left;
            margin-top: 30px;
            border-left: 4px solid var(--warning-color);
        }
        
        .info-box i {
            font-size: 24px;
            color: var(--warning-color);
            margin-right: 15px;
            margin-top: 3px;
        }
        
        .info-box-content p {
            margin-bottom: 10px;
            font-size: 15px;
        }
        
        .info-box-content p:last-child {
            margin-bottom: 0;
        }
        
        /* Mensaje de error */
        .error-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-bottom: 40px;
            border-left: 5px solid var(--error-color);
            display: flex;
            align-items: center;
        }
        
        .error-card i {
            font-size: 24px;
            color: var(--error-color);
            margin-right: 20px;
        }
        
        .error-card p {
            margin: 0;
            color: var(--text-color);
            font-size: 16px;
        }
        
        /* Resultados del tracking */
        .results-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 40px;
        }
        
        .results-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .results-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: var(--primary-color);
        }
        
        .results-header h2 {
            font-size: 22px;
            color: var(--text-color);
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .tracking-badge {
            background-color: var(--secondary-color);
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            margin-top: 15px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .tracking-badge:hover {
            background-color: #eee;
        }
        
        .tracking-badge i {
            color: var(--primary-color);
            font-size: 22px;
            margin-right: 15px;
        }
        
        .tracking-badge .number {
            font-family: monospace;
            font-size: 20px;
            font-weight: 500;
            letter-spacing: 1px;
            color: var(--text-color);
        }
        
        .tracking-badge .copy-tip {
            margin-left: auto;
            font-size: 14px;
            color: var(--text-light);
            opacity: 0;
            transition: var(--transition);
        }
        
        .tracking-badge:hover .copy-tip {
            opacity: 1;
        }
        
        .shipment-info {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
        }
        
        .info-item {
            flex: 1;
            min-width: 200px;
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            background-color: var(--success-color);
            color: white;
        }
        
        .status-badge.transit {
            background-color: var(--warning-color);
            color: #333;
        }
        
        .status-badge.processing {
            background-color: #17a2b8;
        }
        
        /* Timeline de eventos */
        .timeline-section {
            padding: 30px;
        }
        
        .timeline-section h3 {
            font-size: 20px;
            margin-bottom: 30px;
            color: var(--text-color);
            font-weight: 600;
        }
        
        .timeline {
            position: relative;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 24px;
            width: 3px;
            background-color: #ddd;
            border-radius: 3px;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 70px;
            padding-bottom: 30px;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-dot {
            position: absolute;
            left: 15px;
            top: 0;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background-color: white;
            border: 3px solid var(--primary-color);
            z-index: 1;
        }
        
        .timeline-item.active .timeline-dot {
            background-color: var(--primary-color);
        }
        
        .timeline-item.active .timeline-content {
            border-color: var(--primary-color);
        }
        
        .timeline-content {
            background-color: white;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
        }
        
        .timeline-date {
            display: block;
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 10px;
        }
        
        .timeline-title {
            font-weight: 600;
            font-size: 16px;
            color: var(--text-color);
            margin-bottom: 10px;
        }
        
        .timeline-item.active .timeline-title {
            color: var(--primary-color);
        }
        
        .timeline-location {
            color: var(--text-light);
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 26px;
            }
            
            .page-header p {
                font-size: 16px;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-button {
                width: 100%;
            }
            
            .tracking-badge {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .tracking-badge i {
                margin-bottom: 10px;
                margin-right: 0;
            }
            
            .tracking-badge .copy-tip {
                margin-left: 0;
                margin-top: 10px;
                opacity: 1;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-item {
                margin-bottom: 20px;
            }
            
            .timeline-item {
                padding-left: 50px;
            }
        }
    </style>
</head>
<body>
    <div id="cart-overlay" class="cart-overlay"></div>
    <div id="cart-sidebar" class="cart-sidebar">
        <!-- Contenido del carrito se cargará dinámicamente -->
    </div>

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
                <li><a href="index">Inicio</a></li>
                <li><a href="productos">Productos</a></li>
                <li><a href="mistery-box">Mystery Box</a></li>
                <li><a href="giftcard">Giftcard</a></li>
                <li><a href="tracking.php" id="pagina_actual">Seguimiento</a></li>
            </ul>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="position: relative; border: 1px solid #e5e5e5; border-radius: 50px; overflow: hidden; width: 170px;">
                        <input type="text" class="search-input" placeholder="Buscar productos..." id="searchInput" style="border: none; outline: none; width: 100%; padding: 8px 12px; border-radius: 50px; font-size: 14px;">
                    </div>
                    <button onclick="performSearch(document.getElementById('searchInput').value)" style="background: transparent; border: none; padding: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-search" style="font-size: 16px; color: #333;"></i>
                    </button>
                </div>
                <div class="cart-icon">
                    <a href="#" onclick="toggleCart()">
                        <span class="material-symbols-outlined">shopping_cart</span>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <main style="margin-top: 40px;">
        <div class="tracking-container">
            <div class="page-header">
                <h1>Seguimiento de Pedido</h1>
                <p>Rastrea tu envío en tiempo real y conoce la ubicación exacta de tu paquete</p>
            </div>
            
            <!-- Formulario de búsqueda -->
            <div class="search-card">
                <div class="search-card-body">
                    <form action="tracking.php" method="GET" class="search-form">
                        <div class="search-input-group">
                            <label for="tracking">Número de Guía DHL</label>
                            <input type="text" id="tracking" name="tracking" class="search-input" 
                                placeholder="Ej. 1234567890" 
                                value="<?php echo htmlspecialchars($trackingNumber); ?>" required>
                        </div>
                        <button type="submit" class="search-button">
                            <i class="fas fa-search"></i> Rastrear Envío
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Mensaje Inicial -->
            <?php if ($isInitialSearch && !isset($_GET['tracking'])): ?>
            <div class="welcome-card">
                <i class="fas fa-shipping-fast welcome-icon"></i>
                <h2>¡Bienvenido al rastreador de envíos de Jersix!</h2>
                <p>Ingresa el número de guía que recibiste en tu correo electrónico cuando tu pedido fue enviado para ver el estado actual de tu envío y todos los detalles de entrega.</p>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <div class="info-box-content">
                        <p><strong>¿Dónde encuentro mi número de guía?</strong></p>
                        <p>Tu número de guía se te envió por correo electrónico cuando tu pedido fue procesado. Revisa tu bandeja de entrada y busca un correo con el asunto "Tu pedido ha sido enviado" o "Confirmación de envío".</p>
                        <p>También puedes encontrar el número de guía en tu cuenta de usuario, en la sección "Mis pedidos".</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Mensaje de Error -->
            <?php if (!empty($error)): ?>
            <div class="error-card">
                <i class="fas fa-exclamation-circle"></i>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Resultados de seguimiento -->
            <?php if ($trackingInfo): ?>
            <div class="results-card">
                <div class="results-header">
                    <h2>Información de Envío</h2>
                    <div class="tracking-badge" id="copyTrackingNumber">
                        <i class="fas fa-truck"></i>
                        <div class="number"><?php echo htmlspecialchars($trackingNumber); ?></div>
                        <div class="copy-tip"><i class="fas fa-copy"></i> Clic para copiar</div>
                    </div>
                </div>
                
                <?php 
                // Obtener datos del envío
                $shipment = $trackingInfo['shipments'][0] ?? null;
                $currentStatus = $shipment['status'] ?? null;
                $events = $shipment['events'] ?? [];
                
                if ($currentStatus):
                    // Determinar la clase CSS para el estatus
                    $statusClass = '';
                    $statusCode = $currentStatus['statusCode'] ?? '';
                    
                    if ($statusCode === 'DELIVERED') {
                        $statusClass = 'success';
                    } elseif (in_array($statusCode, ['IN_TRANSIT', 'DEPARTED', 'ARRIVED'])) {
                        $statusClass = 'transit';
                    } else {
                        $statusClass = 'processing';
                    }
                ?>
                <div class="shipment-info">
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">Estado actual</div>
                            <div class="info-value">
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo getStatusName($statusCode); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Fecha de envío</div>
                            <div class="info-value">
                                <?php 
                                echo !empty($events) && isset($events[count($events)-1]['timestamp']) 
                                    ? formatDate($events[count($events)-1]['timestamp']) 
                                    : 'No disponible'; 
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Entrega estimada</div>
                            <div class="info-value">
                                <?php 
                                echo isset($shipment['estimatedDeliveryTimeFrame']['estimatedFrom']) 
                                    ? formatDate($shipment['estimatedDeliveryTimeFrame']['estimatedFrom']) 
                                    : 'No disponible'; 
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">Origen</div>
                            <div class="info-value">
                                <?php 
                                echo isset($shipment['origin']['address']['addressLocality']) 
                                    ? htmlspecialchars($shipment['origin']['address']['addressLocality'] . ', ' . ($shipment['origin']['address']['countryCode'] ?? '')) 
                                    : 'No disponible'; 
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Destino</div>
                            <div class="info-value">
                                <?php 
                                echo isset($shipment['destination']['address']['addressLocality']) 
                                    ? htmlspecialchars($shipment['destination']['address']['addressLocality'] . ', ' . ($shipment['destination']['address']['countryCode'] ?? '')) 
                                    : 'No disponible'; 
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Servicio</div>
                            <div class="info-value">
                                <?php 
                                echo isset($shipment['service']) 
                                    ? htmlspecialchars($shipment['service']) 
                                    : 'DHL Express'; 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Timeline de eventos -->
                <?php if (!empty($events)): ?>
                <div class="timeline-section">
                    <h3>Seguimiento Detallado</h3>
                    
                    <div class="timeline">
                        <?php 
                        // Ordenar eventos del más reciente al más antiguo
                        usort($events, function($a, $b) {
                            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
                        });
                        
                        foreach ($events as $index => $event): 
                            $isCurrentEvent = ($index === 0);
                        ?>
                        <div class="timeline-item <?php echo $isCurrentEvent ? 'active' : ''; ?>">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-date"><?php echo formatDate($event['timestamp']); ?></span>
                                <div class="timeline-title"><?php echo htmlspecialchars($event['description']); ?></div>
                                <?php if (isset($event['location']['address'])): ?>
                                <div class="timeline-location">
                                    <?php 
                                    $location = $event['location']['address'];
                                    $locationParts = [];
                                    
                                    if (!empty($location['addressLocality'])) {
                                        $locationParts[] = $location['addressLocality'];
                                    }
                                    
                                    if (!empty($location['postalCode'])) {
                                        $locationParts[] = $location['postalCode'];
                                    }
                                    
                                    if (!empty($location['countryCode'])) {
                                        $locationParts[] = $location['countryCode'];
                                    }
                                    
                                    echo !empty($locationParts) ? '<i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars(implode(', ', $locationParts)) : '';
                                    ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
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
                    <input type="email" id="newsletterEmail" placeholder="Tu correo electrónico" class="newsletter-input">
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
        // Script para copiar el número de seguimiento
        document.addEventListener('DOMContentLoaded', function() {
            const trackingBadge = document.getElementById('copyTrackingNumber');
            if (trackingBadge) {
                trackingBadge.addEventListener('click', function() {
                    const trackingNumber = this.querySelector('.number').textContent;
                    navigator.clipboard.writeText(trackingNumber).then(function() {
                        // Crear notificación
                        const notification = document.createElement('div');
                        notification.className = 'copy-notification';
                        notification.innerHTML = '<i class="fas fa-check"></i> Número de guía copiado';
                        document.body.appendChild(notification);
                        
                        // Mostrar y ocultar después de 2 segundos
                        setTimeout(function() {
                            notification.classList.add('show');
                            setTimeout(function() {
                                notification.classList.remove('show');
                                setTimeout(function() {
                                    notification.remove();
                                }, 300);
                            }, 2000);
                        }, 100);
                    }).catch(function(err) {
                        console.error('Error al copiar: ', err);
                    });
                });
            }
        });
    </script>
</body>
</html>
