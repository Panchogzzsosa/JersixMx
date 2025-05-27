<?php
// Este archivo es para depuración del carrito y product_id

header('Content-Type: application/json');
// Permitir CORS para facilitar pruebas
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log de la solicitud
$logFile = __DIR__ . '/cart_debug.log';
$timestamp = date('Y-m-d H:i:s');

// Obtener datos de la solicitud
$method = $_SERVER['REQUEST_METHOD'];
$data = [];

if ($method == 'POST') {
    // Leer datos del cuerpo de la solicitud
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: [];
    file_put_contents($logFile, "[$timestamp] POST: " . print_r($data, true) . "\n", FILE_APPEND);
} else {
    // Leer datos de query string
    $data = $_GET;
    file_put_contents($logFile, "[$timestamp] GET: " . print_r($data, true) . "\n", FILE_APPEND);
}

// Si hay un carrito en localStorage (enviado en la solicitud)
if (isset($data['cart'])) {
    $cart = json_decode($data['cart'], true);
    
    // Agregar información sobre el carrito
    $response = [
        'timestamp' => $timestamp,
        'total_items' => count($cart),
        'items' => []
    ];
    
    // Analizar cada elemento
    foreach ($cart as $index => $item) {
        $response['items'][] = [
            'index' => $index,
            'id' => $item['id'] ?? 'no_id',
            'product_id' => $item['product_id'] ?? 'no_product_id',
            'title' => $item['title'] ?? 'sin_titulo',
            'quantity' => $item['quantity'] ?? 1,
            'price' => $item['price'] ?? 0
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
} else {
    // Instrucciones para usar
    echo json_encode([
        'status' => 'ready',
        'message' => 'Usa este endpoint para diagnosticar el carrito',
        'usage' => [
            'GET' => 'Envía el carrito como ?cart=JSON.stringify(localStorage.getItem("cart"))',
            'POST' => 'Envía el carrito en el cuerpo como {cart: JSON.stringify(localStorage.getItem("cart"))}'
        ],
        'timestamp' => $timestamp
    ], JSON_PRETTY_PRINT);
}
