<?php
header('Content-Type: application/json');

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "checkout";

try {
    // Obtener los datos JSON enviados
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!isset($data['products']) || !is_array($data['products'])) {
        throw new Exception('Datos de productos no válidos');
    }

    // Conectar a la base de datos
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    // Preparar la consulta
    $stmt = $conn->prepare("SELECT product_id, name FROM products WHERE name = ?");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $results = [];
    foreach ($data['products'] as $product) {
        $title = $product['title'];
        $stmt->bind_param("s", $title);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $results[] = [
                'title' => $row['name'],
                'product_id' => (int)$row['product_id']
            ];
        } else {
            // Si no se encuentra el producto, registrar en el log
            error_log("Producto no encontrado: " . $title);
            throw new Exception("No se encontró el producto en la base de datos: " . $title);
        }
    }

    $stmt->close();
    $conn->close();

    if (empty($results)) {
        throw new Exception("No se encontraron productos en la base de datos");
    }

    $total_discount = $discount_amount + $promo_discount;
    $remaining = $order_subtotal - $total_discount;

    echo json_encode([
        'success' => true,
        'products' => $results
    ]);

} catch (Exception $e) {
    error_log("Error en get_product_ids.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar los IDs de los productos: ' . $e->getMessage()
    ]);
}
?> 