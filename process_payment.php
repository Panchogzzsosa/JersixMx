<?php
require_once 'vendor/autoload.php';

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

try {
    // Configure credentials
    MercadoPagoConfig::setAccessToken('TEST-6508159281724345-012716-7b8ae032875284d169250de531a5040c-485581473');
    $client = new PreferenceClient();
    
    // Generate a unique external reference
    $external_reference = uniqid('ORDER_', true);
    
    // Get request body
    $request_body = file_get_contents('php://input');
    $request_data = json_decode($request_body, true);

    // Create preference data
    $preference_data = [
        "items" => $request_data['items'] ?? [
            [
                "title" => "Producto de Prueba",
                "quantity" => 1,
                "unit_price" => 10
            ]
        ],
        "back_urls" => [
            "success" => "https://jersix.mx/success.php",
            "failure" => "https://jersix.mx/failure.php",
            "pending" => "https://jersix.mx/pending.php"
        ],
        "auto_return" => "approved",
        "notification_url" => "https://jersix.mx/webhook.php",
        "external_reference" => $external_reference,
        "payer" => [
            "first_name" => $request_data['payer']['fullname'] ?? '',
            "email" => $request_data['payer']['email'] ?? '',
            "phone" => [
                "number" => $request_data['payer']['phone'] ?? ''
            ],
            "address" => $request_data['payer']['address'] ?? []
        ]
    ];
    
    // Create preference using the client
    $preference = $client->create($preference_data);
    
    // Set headers for JSON response
    header('Content-Type: application/json');
    
    // Return preference ID with debug info and external reference
    $response = [
        'preferenceId' => $preference->id,
        'external_reference' => $external_reference,
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo json_encode($response);
    
} catch (Exception $e) {
    // Set headers for JSON response
    header('Content-Type: application/json');
    header('HTTP/1.1 500 Internal Server Error');
    
    echo json_encode([
        'error' => $e->getMessage(),
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>