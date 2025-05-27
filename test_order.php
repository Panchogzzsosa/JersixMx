<?php
// Script para probar el procesamiento de órdenes sin necesidad de PayPal

// Configuración de headers
header('Content-Type: application/json');

// Definir la ruta del archivo de log
$logFile = __DIR__ . '/logs/test_order.log';
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}

// Función para escribir en el log
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

writeLog("=== INICIO DE PRUEBA DE ORDEN ===");

// Obtener datos de la solicitud
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Procesar datos POST
    $orderData = [
        'fullname' => $_POST['fullname'] ?? 'Usuario de Prueba',
        'email' => $_POST['email'] ?? 'test@example.com',
        'phone' => $_POST['phone'] ?? '5512345678',
        'street' => $_POST['street'] ?? 'Calle Prueba 123',
        'colonia' => $_POST['colonia'] ?? 'Col. Test',
        'city' => $_POST['city'] ?? 'Ciudad Test',
        'state' => $_POST['state'] ?? 'Estado de Prueba',
        'postal' => $_POST['postal'] ?? '12345',
        'payment_id' => $_POST['payment_id'] ?? 'TEST-' . time(),
        'cart_items' => $_POST['cart_items'] ?? '[]'
    ];
    
    // Registrar los datos recibidos
    writeLog("Datos recibidos: " . print_r($orderData, true));
    
    // Decodificar items del carrito
    try {
        $cartItems = json_decode($orderData['cart_items'], true);
        if (!is_array($cartItems)) {
            throw new Exception("Los items del carrito no son un array válido");
        }
        
        writeLog("Items del carrito decodificados correctamente: " . count($cartItems) . " items");
        
        // Verificar product_id en cada item
        foreach ($cartItems as $index => $item) {
            $productId = $item['product_id'] ?? 'no_product_id';
            $title = $item['title'] ?? 'sin_título';
            writeLog("Item #$index: Producto '$title' - product_id: $productId");
        }
        
        // Redireccionar a process_order.php con los mismos datos
        $redirectUrl = "process_order.php";
        
        // Mostrar respuesta simulada
        echo json_encode([
            'success' => true,
            'message' => 'Prueba completada con éxito',
            'data' => [
                'received_data' => $orderData,
                'cart_items' => $cartItems,
                'next_url' => $redirectUrl
            ]
        ]);
        
    } catch (Exception $e) {
        writeLog("ERROR: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    // Si es una solicitud GET, mostrar formulario de prueba
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Prueba de Órdenes - JerSix</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            h1 {
                color: #2c3e50;
            }
            .form-group {
                margin-bottom: 15px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            input, textarea {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-sizing: border-box;
            }
            textarea {
                height: 150px;
                font-family: monospace;
            }
            button {
                background: #3498db;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
            }
            button:hover {
                background: #2980b9;
            }
            .info {
                background: #ecf0f1;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Prueba de Órdenes - JerSix</h1>
            
            <div class="info">
                <p>Esta página te permite probar el procesamiento de órdenes sin necesidad de usar PayPal.</p>
                <p>Completa el formulario y pulsa "Enviar" para simular un checkout.</p>
            </div>
            
            <form id="test-form" method="post" action="test_order.php">
                <div class="form-group">
                    <label for="fullname">Nombre completo:</label>
                    <input type="text" id="fullname" name="fullname" value="Usuario de Prueba" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="test@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Teléfono:</label>
                    <input type="text" id="phone" name="phone" value="5512345678" required>
                </div>
                
                <div class="form-group">
                    <label for="street">Calle y número:</label>
                    <input type="text" id="street" name="street" value="Calle Prueba 123" required>
                </div>
                
                <div class="form-group">
                    <label for="colonia">Colonia:</label>
                    <input type="text" id="colonia" name="colonia" value="Col. Test" required>
                </div>
                
                <div class="form-group">
                    <label for="city">Ciudad:</label>
                    <input type="text" id="city" name="city" value="Ciudad Test" required>
                </div>
                
                <div class="form-group">
                    <label for="state">Estado:</label>
                    <input type="text" id="state" name="state" value="Estado de Prueba" required>
                </div>
                
                <div class="form-group">
                    <label for="postal">Código postal:</label>
                    <input type="text" id="postal" name="postal" value="12345" required>
                </div>
                
                <div class="form-group">
                    <label for="payment_id">ID de pago (simulado):</label>
                    <input type="text" id="payment_id" name="payment_id" value="TEST-<?php echo time(); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="cart_items">Items del carrito (JSON):</label>
                    <textarea id="cart_items" name="cart_items" required></textarea>
                    <button type="button" id="load-cart">Cargar desde localStorage</button>
                </div>
                
                <button type="submit">Enviar</button>
            </form>
            
            <div id="result" style="margin-top: 20px;"></div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Cargar carrito desde localStorage
                document.getElementById('load-cart').addEventListener('click', function() {
                    const cart = JSON.parse(localStorage.getItem('cart')) || [];
                    
                    // Procesar carrito para enviar
                    const processedCart = cart.map(item => {
                        return {
                            ...item,
                            product_id: parseInt(item.product_id) || 0
                        };
                    });
                    
                    document.getElementById('cart_items').value = JSON.stringify(processedCart, null, 2);
                });
                
                // Manejar envío del formulario
                document.getElementById('test-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    // Enviar solicitud AJAX
                    fetch('test_order.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Mostrar resultado
                        const resultDiv = document.getElementById('result');
                        resultDiv.innerHTML = `
                            <h2>Resultado:</h2>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                            ${data.success ? 
                                `<p>¿Quieres continuar con el procesamiento real de la orden?</p>
                                <button id="process-real">Procesar orden real</button>` : ''}
                        `;
                        
                        // Añadir evento para procesar orden real
                        if (data.success) {
                            document.getElementById('process-real').addEventListener('click', function() {
                                fetch('process_order.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(processData => {
                                    resultDiv.innerHTML += `
                                        <h3>Resultado del procesamiento real:</h3>
                                        <pre>${JSON.stringify(processData, null, 2)}</pre>
                                    `;
                                })
                                .catch(error => {
                                    resultDiv.innerHTML += `
                                        <h3>Error en el procesamiento real:</h3>
                                        <pre>${error.message}</pre>
                                    `;
                                });
                            });
                        }
                    })
                    .catch(error => {
                        document.getElementById('result').innerHTML = `
                            <h2>Error:</h2>
                            <pre>${error.message}</pre>
                        `;
                    });
                });
            });
        </script>
    </body>
    </html>
    <?php
} 