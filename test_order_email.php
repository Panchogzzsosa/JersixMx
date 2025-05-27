<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir la ruta base del proyecto usando la variable de servidor
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
define('BASE_PATH', $documentRoot);

// Verificar y mostrar las rutas para debugging
echo "Document Root: " . $documentRoot . "\n";
echo "Script Location: " . __FILE__ . "\n";

// Incluir el archivo OrderMailer usando ruta absoluta
$orderMailerPath = $documentRoot . '/includes/OrderMailer.php';
echo "OrderMailer Path: " . $orderMailerPath . "\n";
echo "File exists? " . (file_exists($orderMailerPath) ? "Yes" : "No") . "\n";

require_once $orderMailerPath;

try {
    // Datos de ejemplo para la orden
    $order = [
        'order_id' => 'TEST-' . date('YmdHis'),
        'customer_name' => 'Francisco González',
        'customer_email' => 'franciscogzz03@gmail.com',
        'total' => 4196.00
    ];

    // Items de la orden
    $items = [
        [
            'name' => 'Ajax Visitante 24/25',
            'size' => 'L',
            'price' => 799.00,
            'image' => 'https://jersixmx.com/images/products/ajax-away-2425.jpg',
            'personalization_patch' => '0'
        ],
        [
            'name' => 'Arsenal Local 24/25',
            'size' => 'M',
            'price' => 799.00,
            'image' => 'https://jersixmx.com/images/products/arsenal-home-2425.jpg',
            'personalization_name' => 'Pancho',
            'personalization_number' => '10',
            'personalization_patch' => '1'
        ],
        [
            'name' => 'Mystery Box',
            'size' => 'S',
            'price' => 799.00,
            'image' => 'https://jersixmx.com/images/products/mystery-box.jpg'
        ],
        [
            'name' => 'Tarjeta de Regalo JersixMx',
            'price' => 1000.00,
            'gift_recipient' => 'Francisco González',
            'gift_code' => 'GC-0f19ceb',
            'image' => 'https://jersixmx.com/images/products/gift-card.jpg'
        ]
    ];

    echo "\nVerificando archivos necesarios:\n";
    echo "OrderMailer.php existe: " . (file_exists($documentRoot . '/includes/OrderMailer.php') ? 'Sí' : 'No') . "\n";
    echo "mail_config.php existe: " . (file_exists($documentRoot . '/config/mail_config.php') ? 'Sí' : 'No') . "\n";
    echo "vendor/autoload.php existe: " . (file_exists($documentRoot . '/vendor/autoload.php') ? 'Sí' : 'No') . "\n";

    // Crear instancia del mailer y enviar correos
    $mailer = new OrderMailer($order, $items, $order['customer_email']);
    $result = $mailer->sendConfirmationEmails();

    // Mostrar resultado
    if ($result['success']) {
        echo "¡Correos enviados exitosamente!\n";
        echo "Correo al cliente: " . ($result['customer_email'] ? "Enviado" : "No enviado") . "\n";
        echo "Correo al admin: " . ($result['admin_email'] ? "Enviado" : "No enviado") . "\n";
    } else {
        echo "Error al enviar los correos: " . $result['error'] . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    // Mostrar la traza del error para debugging
    echo "\nDetalles del error:\n";
    echo $e->getTraceAsString() . "\n";
} 