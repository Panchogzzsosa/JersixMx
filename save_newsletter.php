<?php
// Database connection
require_once __DIR__ . '/config/database.php';

// Establecer el tipo de contenido como JSON
header('Content-Type: application/json');

try {
    // Obtener la conexión a la base de datos
    $pdo = getConnection();
    
    // Verificar que sea una solicitud POST y que se haya enviado un correo
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        
        if ($email) {
            // Verificar si el correo ya existe
            $checkStmt = $pdo->prepare('SELECT id FROM newsletter WHERE email = ?');
            $checkStmt->execute([$email]);
            
            if ($checkStmt->fetch()) {
                // El correo ya existe
                echo json_encode([
                    'success' => false, 
                    'message' => 'Este correo ya está suscrito.'
                ]);
            } else {
                // Insertar nueva suscripción
                $insertStmt = $pdo->prepare('INSERT INTO newsletter (email) VALUES (?)');
                $insertStmt->execute([$email]);
                
                // Respuesta exitosa
                echo json_encode([
                    'success' => true, 
                    'message' => '¡Gracias por suscribirte!'
                ]);
            }
        } else {
            // Correo inválido
            echo json_encode([
                'success' => false, 
                'message' => 'Por favor, ingresa un correo válido.'
            ]);
        }
    } else {
        // Método no válido o falta el correo
        echo json_encode([
            'success' => false, 
            'message' => 'Método no válido o datos incompletos.'
        ]);
    }
} catch(PDOException $e) {
    // Error en la base de datos
    error_log('Error en save_newsletter.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ]);
}