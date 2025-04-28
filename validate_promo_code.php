<?php
require_once 'config/database.php';

// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar headers CORS y JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Obtener conexión PDO
    $pdo = getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Log de datos recibidos
        error_log("Datos POST recibidos: " . print_r($_POST, true));
        
        if (!isset($_POST['codigo'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Código promocional no proporcionado'
            ]);
            exit;
        }

        $codigo = $_POST['codigo'];
        $subtotal = isset($_POST['subtotal']) ? floatval($_POST['subtotal']) : 0;

        // Buscar el código promocional
        $stmt = $pdo->prepare("SELECT * FROM codigos_promocionales WHERE codigo = ? AND estado = 'activo'");
        $stmt->execute([$codigo]);
        $promo = $stmt->fetch();

        if (!$promo) {
            echo json_encode([
                'success' => false,
                'message' => 'Código promocional no válido o expirado'
            ]);
            exit;
        }

        $now = new DateTime();
        $fecha_inicio = new DateTime($promo['fecha_inicio']);
        $fecha_fin = new DateTime($promo['fecha_fin']);

        // Validar fechas
        if ($now < $fecha_inicio || $now > $fecha_fin) {
            echo json_encode([
                'success' => false,
                'message' => 'El código promocional no está vigente'
            ]);
            exit;
        }

        // Validar usos
        if ($promo['usos_actuales'] >= $promo['usos_maximos']) {
            echo json_encode([
                'success' => false,
                'message' => 'El código promocional ha alcanzado su límite de usos'
            ]);
            exit;
        }

        // Calcular descuento
        $descuento = 0;
        if ($promo['tipo_descuento'] === 'porcentaje') {
            $descuento = $subtotal * ($promo['descuento'] / 100);
        } else {
            $descuento = $promo['descuento'];
        }

        // Actualizar contador de usos
        $stmt = $pdo->prepare("UPDATE codigos_promocionales SET usos_actuales = usos_actuales + 1 WHERE id = ?");
        $stmt->execute([$promo['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Código de descuento aplicado correctamente',
            'descuento' => $descuento,
            'tipo_descuento' => $promo['tipo_descuento']
        ]);

    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
    }
} catch (Exception $e) {
    error_log("Error en validate_promo_code.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar el código promocional: ' . $e->getMessage()
    ]);
}
?> 