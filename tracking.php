<?php
session_start();
require_once 'config/database.php';

// Clase para gestionar el rastreo de DHL
class DHLTracker {
    private $apiKey = '0lwVV1XadZM2zAKPQeIx3GJ1I2tz6oki';
    private $baseUrl = 'https://api-eu.dhl.com/track/shipments';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Obtiene la información de rastreo para un número de guía
     * @param string $trackingNumber Número de guía
     * @param string $service Servicio (opcional)
     * @param string $originCountryCode Código de país de origen (opcional)
     * @param string $requesterCountryCode Código de país del solicitante (opcional)
     * @param string $language Idioma preferido (opcional)
     * @return array Información de rastreo
     */
    public function trackShipment($trackingNumber, $service = null, $originCountryCode = null, $requesterCountryCode = null, $language = 'es') {
        // Construir la URL con los parámetros
        $params = ['trackingNumber' => $trackingNumber];
        
        if ($service) {
            $params['service'] = $service;
        }
        
        if ($originCountryCode) {
            $params['originCountryCode'] = $originCountryCode;
        }
        
        if ($requesterCountryCode) {
            $params['requesterCountryCode'] = $requesterCountryCode;
        }
        
        if ($language) {
            $params['language'] = $language;
        }
        
        $url = $this->baseUrl . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'DHL-API-Key: ' . $this->apiKey,
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        if (curl_errno($ch)) {
            curl_close($ch);
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . curl_error($ch)
            ];
        }
        
        curl_close($ch);
        
        // Verificar si la respuesta es un error (application/problem+json)
        if (strpos($contentType, 'application/problem+json') !== false) {
            $errorData = json_decode($response, true);
            return [
                'success' => false,
                'error' => isset($errorData['detail']) ? $errorData['detail'] : 'Error en la API de DHL (Código: ' . $httpCode . ')',
                'errorData' => $errorData
            ];
        }
        
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
$apiKey = '0lwVV1XadZM2zAKPQeIx3GJ1I2tz6oki'; // API key de DHL
$trackingNumber = isset($_GET['tracking']) ? trim($_GET['tracking']) : '';
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$service = isset($_GET['service']) ? trim($_GET['service']) : null;
$originCountryCode = isset($_GET['origin_country']) ? trim($_GET['origin_country']) : null;
$requesterCountryCode = isset($_GET['requester_country']) ? trim($_GET['requester_country']) : null;
$language = isset($_GET['language']) ? trim($_GET['language']) : 'es';
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
        $result = $tracker->trackShipment($trackingNumber, $service, $originCountryCode, $requesterCountryCode, $language);
        
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
    // Asegurarse de que la fecha esté en formato ISO 8601
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
    <title>Seguimiento de Pedido | JersixMx</title>
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
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-2PPJD4LWKZ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-2PPJD4LWKZ');
    </script>
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

        .nombre{
            color: #828282;
        }
        
        /* Estilos para el buscador y resultados */
        .search-container {
            position: relative;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-top: 5px;
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .search-result-item {
            display: flex;
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .search-result-item:hover {
            background-color: #f8f9fa;
        }
        
        .search-result-item .result-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 10px;
        }
        
        .search-result-item .result-info {
            flex: 1;
        }
        
        .search-result-item h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #333;
        }
        
        .search-result-item p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .search-result-item .result-price {
            font-weight: 600;
            color:rgb(65, 87, 114);
            font-size: 14px;
        }
        
        .loading, .no-results, .error {
            padding: 15px;
            text-align: center;
            color: #666;
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
            flex-direction: column;
            gap: 20px;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .search-input-container {
            display: flex;
            flex-direction: row;
            align-items: flex-end;
            gap: 10px;
            width: 100%;
        }
        
        .search-input-group {
            flex: 1;
            width: 100%;
        }
        
        .search-input-group input[type="text"] {
            width: 550px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            height: 45px;
        }
        
        @media screen and (max-width: 768px) {
            .search-input-group input[type="text"] {
                width: 300px;
            }
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
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            height: 45px;
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
            padding: 0 25px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            height: 45px;
            white-space: nowrap;
        }
        
        
        
        .search-button span {
            color: #000000;
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
        
        .search-input-group small {
            display: block;
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        /* Aviso legal de DHL */
        .dhl-legal-notice {
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
            padding: 10px;
            margin-top: 25px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        
        .dhl-legal-notice p {
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.5;
        }
        
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 26px;
            }
            
            .page-header p {
                font-size: 16px;
            }
            
            .search-input-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-button {
                width: 100%;
                margin-top: 10px;
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
            
            .search-results {
                position: fixed;
                top: auto;
                left: 0;
                right: 0;
                max-height: 60vh;
                margin: 0;
                border-radius: 0;
            }
        }

        /* Estilos base del navbar */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 36px;
            height: 60px;
            background-color: white;
            width: 100%;
            top: 0;
            position: absolute;
            z-index: 1000;
            border-bottom: 1px solid #f5f5f5;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-links {
            display: flex;
            gap: 24px;
            margin: 0;
            padding: 0;
            list-style: none;
            justify-content: center;
            flex: 1;
        }

        .navbar .logo img {
            height: 100px;
            width: auto;
            display: block;
            object-fit: contain;
            cursor: default;
            position: relative;
            z-index: 1001;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            margin-right: 40px;
            position: relative;
            z-index: 1001;
        }

        .search-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            order: 1;
            position: relative;
            z-index: 1000;
        }

        .search-input {
            padding: 8px 16px;
            border: 1px solid #e5e5e5;
            border-radius: 20px;
            width: 180px;
            font-size: 14px;
        }

        .search-button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            color: #000000;
        }

        .search-button span {
            color: #000000;
        }

        .nav-links a {
            text-decoration: none;
            color: #111;
            font-size: 16px;
            font-weight: 500;
            padding: 8px 12px;
        }

        #pagina_actual {
            color: #CA0C0C;
        }

        .nav-links a:hover {
            color: #CA0C0C;
        }

        .cart-icon {
            margin-left: 24px;
            cursor: pointer;
            color: #000000;
            display: flex;
            align-items: center;
            order: 2;
        }

        /* Estilos móviles */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            z-index: 1001;
        }

        .menu-toggle span {
            color: #333;
        }

        @media screen and (max-width: 768px) {
            .navbar {
                padding: 1rem 5%;
                justify-content: space-between;
                align-items: center;
                position: fixed;
                height: 60px;
                width: 100%;
                background: white;
                z-index: 1000;
            }

            .menu-toggle {
                display: block;
                order: 1;
                z-index: 1001;
            }

            .logo {
                order: 2;
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
            }

            .cart-icon {
                order: 3;
                margin-left: auto;
                margin-right: 0;
                display: flex;
                align-items: center;
                height: 100%;
                z-index: 1001;
                position: absolute;
                right: 5%;
            }

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                flex-direction: column;
                background-color: #fff;
                padding: 1rem;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                z-index: 1000;
            }

            .nav-links.active {
                display: flex;
            }

            .search-container {
                display: none;
                
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
                <li><a href="index">Inicio</a></li>
                <li><a href="productos">Productos</a></li>
                <li><a href="mistery-box">Mystery Pack</a></li>
                <li><a href="giftcard" class="active">Gift Cards</a></li>
                <li><a href="tracking.php" id="pagina_actual">Seguimiento</a></li>
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

    <div id="cart-overlay" class="cart-overlay"></div>
    <div id="cart-sidebar" class="cart-sidebar">
        <!-- Contenido del carrito se cargará dinámicamente -->
    </div>

    <main style="margin-top: 40px;">
        <div class="tracking-container">
            <div class="page-header">
                <br>
                <br>
                <h1>Seguimiento de Pedido</h1>
                <p>Rastrea tu envío en tiempo real y conoce la ubicación exacta de tu paquete</p>
            </div>
            
            <!-- Formulario de búsqueda -->
            <div class="search-card">
                <div class="search-card-body">
                    <form action="tracking.php" method="GET" class="search-form">
                        <div class="search-input-container">
                            <div class="search-input-group">
                                <label for="tracking">Número de Guía DHL</label>
                                <input type="text" id="tracking" name="tracking" class="search-input" 
                                    placeholder="Ej. 1234567890" 
                                    value="<?php echo htmlspecialchars($trackingNumber); ?>" required>
                            </div>
                            
                            <button type="submit" class="search-button">
                                <i class="fas fa-search"></i> Rastrear Envío
                            </button>
                        </div>
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
                        <p>Tu número de guía se te envió por correo electrónico cuando tu pedido sea procesado.</p>
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
            <p class="copyright">&copy; 2025 Jersix.mx. Todos los derechos reservados. | <a class="nombre" href="https://franciscogonzalez.netlify.app/" target="_blank">Francisco Gonzalez Sosa</a></p>
        </div>
        
        <!-- Aviso legal de DHL -->
        <div class="dhl-legal-notice">
            <p>Datos de seguimiento proporcionados por Deutsche Post DHL Group. Los datos de seguimiento son información confidencial y se utilizan únicamente para fines legítimos de seguimiento. Los datos se eliminarán 30 días después de la entrega completada. "Entregado por Deutsche Post DHL Group" se muestra cuando se presenta al destinatario.</p>
        </div>
    </footer>
    <div class="whatsapp-button">
        <a href="https://wa.me/+528129157795" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>
    <div id="notification" class="notification"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar buscadores
            function initializeSearch(inputId, wrapperId) {
                const searchInput = document.getElementById(inputId);
                const searchWrapper = searchInput.closest('.search-wrapper');
                let searchResults = searchWrapper.querySelector('.search-results');
                
                if (!searchResults) {
                    searchResults = document.createElement('div');
                    searchResults.className = 'search-results';
                    searchWrapper.appendChild(searchResults);
                }

                function createSearchResultItem(product) {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'search-result-item';
                    
                    let imageUrl = product.image_url || 'img/default-product.jpg';
                    if (!imageUrl.startsWith('http') && !imageUrl.startsWith('/')) {
                        imageUrl = location.pathname.includes('/Productos-equipos/') ? '../' + imageUrl : imageUrl;
                    }
                    
                    let productUrl = location.pathname.includes('/Productos-equipos/') 
                        ? 'producto.php?id=' + product.product_id 
                        : 'Productos-equipos/producto.php?id=' + product.product_id;
                    
                    resultItem.innerHTML = `
                        <img src="${imageUrl}" alt="${product.name}" class="result-image" onerror="this.src='${location.pathname.includes('/Productos-equipos/') ? '../img/default-product.jpg' : 'img/default-product.jpg'}'">
                        <div class="result-info">
                            <h4>${product.name}</h4>
                            <p>${product.category || ''}</p>
                            <span class="result-price">$${parseFloat(product.price).toFixed(2)}</span>
                        </div>
                    `;
                    
                    resultItem.addEventListener('click', () => {
                        window.location.href = productUrl;
                    });
                    
                    return resultItem;
                }

                function performSearch(searchTerm) {
                    searchResults.innerHTML = '';
                    if (!searchTerm || searchTerm.length < 2) {
                        searchResults.style.display = 'none';
                        return;
                    }

                    searchResults.innerHTML = '<div class="loading">Buscando...</div>';
                    searchResults.style.display = 'block';
                    
                    const searchUrl = location.pathname.includes('/Productos-equipos/') ? '../search_products.php' : 'search_products.php';
                    fetch(searchUrl + '?q=' + encodeURIComponent(searchTerm))
                        .then(response => response.json())
                        .then(data => {
                            searchResults.innerHTML = '';
                            
                            if (data.products && data.products.length > 0) {
                                data.products.forEach(product => {
                                    searchResults.appendChild(createSearchResultItem(product));
                                });
                            } else {
                                searchResults.innerHTML = '<div class="no-results">No se encontraron productos</div>';
                            }
                            
                            searchResults.style.display = 'block';
                        })
                        .catch(error => {
                            console.error('Error en la búsqueda:', error);
                            searchResults.innerHTML = '<div class="error">Error al buscar productos</div>';
                            searchResults.style.display = 'block';
                        });
                }

                // Manejar entrada de texto con retraso
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        performSearch(e.target.value);
                    }, 300);
                });

                // Mostrar resultados al enfocar si hay texto
                searchInput.addEventListener('focus', () => {
                    if (searchInput.value && searchInput.value.length >= 2) {
                        performSearch(searchInput.value);
                    }
                });

                // Cerrar resultados al hacer clic fuera
                document.addEventListener('click', (e) => {
                    if (!searchWrapper.contains(e.target)) {
                        searchResults.style.display = 'none';
                    }
                });

                // Exponer la función de búsqueda para el botón
                const searchButton = searchWrapper.querySelector('.search-button-mobile');
                if (searchButton) {
                    searchButton.onclick = () => performSearch(searchInput.value);
                }
            }

            // Inicializar ambos buscadores
            initializeSearch('searchInputMobile', 'search-mobile-container');
            initializeSearch('searchInputDesktop', 'search-desktop');

            // Código existente para el tracking
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
