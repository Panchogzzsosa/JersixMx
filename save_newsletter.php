<?php
// Database connection

try {
    $pdo = new PDO('mysql:host=216.245.211.58;dbname=jersixmx_checkout', 'jersixmx_usuario_total', '?O*6o6&Hs&~Q');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        
        if ($email) {
            // Check if email already exists
            $checkStmt = $pdo->prepare('SELECT id FROM newsletter WHERE email = ?');
            $checkStmt->execute([$email]);
            
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este correo ya está suscrito.']);
            } else {
                // Insert new subscription
                $insertStmt = $pdo->prepare('INSERT INTO newsletter (email) VALUES (?)');
                $insertStmt->execute([$email]);
                echo json_encode(['success' => true, 'message' => '¡Gracias por suscribirte!']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Por favor, ingrese un correo válido.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Método no válido.']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud.']);
}