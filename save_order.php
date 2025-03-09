<?php
header('Content-Type: application/json');

// Enable error reporting and logging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/logs/payment_errors.log');

// Get the raw POST data
$json = file_get_contents('php://input');

// Log the raw input for debugging with more detail
error_log('Raw input received (length: ' . strlen($json) . '): ' . $json);

// Check if input is empty
if (empty($json)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos. Por favor, envíe los datos del pedido.']);
    exit;
}

// Basic sanitization of the input
$json = trim($json);

// Remove BOM if present
$json = preg_replace('/^\xEF\xBB\xBF/', '', $json);

// Remove HTML tags and decode HTML entities before JSON processing
$json = html_entity_decode(strip_tags($json), ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Ensure proper UTF-8 encoding
if (!mb_check_encoding($json, 'UTF-8')) {
    $detected_encoding = mb_detect_encoding($json, mb_detect_order(), true);
    error_log('Invalid encoding detected. Found: ' . ($detected_encoding ?: 'unknown'));
    $json = mb_convert_encoding($json, 'UTF-8', $detected_encoding ?: 'auto');
}

// Remove any control characters that might interfere with JSON parsing
$json = preg_replace('/[\x00-\x1F\x7F]/u', '', $json);

// Additional cleanup for common JSON-breaking characters
$json = str_replace(
    array('\\', '\"', '\'', "\r", "\n", "\t"),
    array('\\\\', '\\"', '\\\'', '', '', ''),
    $json);


// Validate JSON structure before decoding
if (!preg_match('/^[\[\{].*[\}\]]$/', trim($json))) {
    error_log('Invalid JSON structure detected. Raw data: ' . $json);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Estructura JSON inválida. Por favor, verifique el formato de los datos.']);
    exit;
}

// Additional JSON validation
try {
    $jsonValidator = json_decode($json);
    if ($jsonValidator === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON pre-validation error: ' . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Error en el formato de los datos enviados']);
        exit;
    }
} catch (Exception $e) {
    error_log('JSON validation exception: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Error al validar el formato de los datos']);
    exit;
}

// Decode the sanitized JSON with detailed error reporting
$data = json_decode($json, true);
$json_error = json_last_error();

if ($json_error !== JSON_ERROR_NONE) {
    $error_msg = 'Error al procesar JSON: ';
    switch ($json_error) {
        case JSON_ERROR_DEPTH:
            $error_msg .= 'Excedido el máximo nivel de anidamiento';
            break;
        case JSON_ERROR_STATE_MISMATCH:
            $error_msg .= 'Modos o estados inválidos';
            break;
        case JSON_ERROR_CTRL_CHAR:
            $error_msg .= 'Carácter de control encontrado';
            break;
        case JSON_ERROR_SYNTAX:
            $error_msg .= 'Error de sintaxis';
            break;
        case JSON_ERROR_UTF8:
            $error_msg .= 'Caracteres UTF-8 mal formados';
            break;
        default:
            $error_msg .= json_last_error_msg();
    }
    error_log('JSON decode error: ' . $error_msg . '\nInput JSON: ' . $json);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit;
}

if (!$data) {
    $json_error = json_last_error_msg();
    error_log('Invalid JSON data received: ' . $json . '\nJSON Error: ' . $json_error);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data: ' . $json_error]);
    exit;
}

// Log the decoded data structure
error_log('Decoded data structure: ' . print_r($data, true));

// Validate required fields
if (!isset($data['payer']['fullname']) || !isset($data['payer']['email']) || !isset($data['payer']['phone'])) {
    error_log('Missing required payer information');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Información del pagador incompleta. Por favor, complete todos los campos requeridos.']);
    exit;
}

// Validate address fields
if (!isset($data['payer']['address']) ||
    !isset($data['payer']['address']['street_name']) ||
    !isset($data['payer']['address']['city']) ||
    !isset($data['payer']['address']['state']) ||
    !isset($data['payer']['address']['zip_code'])) {
    error_log('Missing required address information');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Información de dirección incompleta. Por favor, complete todos los campos de dirección.']);
    exit;
}

try {
    // Validate required data with detailed logging
    if (!isset($data['payer'])) {
        error_log('Missing payer data in request');
        throw new Exception('Datos del pagador no encontrados');
    }
    if (!isset($data['items']) || empty($data['items'])) {
        error_log('Missing or empty items array in request');
        throw new Exception('No se encontraron artículos en el pedido');
    }

    // Log successful data validation
    error_log('Order data validation successful. Processing payment...');

    // Calculate total amount
    $total_amount = 0;
    foreach ($data['items'] as $item) {
        $total_amount += $item['unit_price'] * $item['quantity'];
    }

    // Return success response
    echo json_encode(['success' => true, 'total_amount' => $total_amount]);
    exit;

} catch (Exception $e) {
    // General error handling
    error_log('Error processing order: ' . $e->getMessage() . '\nStack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al procesar el pedido. Por favor, inténtalo de nuevo.']);
    exit;
}
?>