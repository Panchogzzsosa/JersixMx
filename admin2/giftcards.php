<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Configuración de la función para escribir logs
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/giftcard_emails.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Cargar PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Cargar configuración de correo
require_once __DIR__ . '/giftcard_config.php';

// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Función para enviar la gift card por correo
function sendGiftCardEmail($recipient, $amount, $code, $senderName, $recipientName = '', $message = '') {
    global $EMAIL_CONFIG;
    writeLog("Intentando enviar gift card por correo a: $recipient");
    
    // Verificar si el correo es válido
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        writeLog("ERROR: Dirección de correo inválida: $recipient");
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Habilitar debug
        $mail->SMTPDebug = 3; // Modo de depuración detallado
        $mail->Debugoutput = function($str, $level) {
            writeLog("DEBUG [$level]: $str");
        };
        
        // Verificar si tenemos la configuración necesaria
        if (empty($EMAIL_CONFIG['smtp_username']) || empty($EMAIL_CONFIG['smtp_password'])) {
            writeLog("ERROR: Faltan credenciales SMTP en la configuración");
            return false;
        }
        
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = $EMAIL_CONFIG['smtp_host'];
        $mail->SMTPAuth   = $EMAIL_CONFIG['smtp_auth'];
        $mail->Username   = $EMAIL_CONFIG['smtp_username'];
        $mail->Password   = $EMAIL_CONFIG['smtp_password'];
        $mail->SMTPSecure = $EMAIL_CONFIG['smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $EMAIL_CONFIG['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        
        // Timeout más largo para conexiones lentas
        $mail->Timeout = 60;
        $mail->SMTPKeepAlive = true;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Destinatarios
        $mail->setFrom($EMAIL_CONFIG['sender_email'], $EMAIL_CONFIG['sender_name']);
        $mail->addAddress($recipient);
        $mail->addReplyTo($EMAIL_CONFIG['sender_email'], $EMAIL_CONFIG['sender_name']);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = "¡Has recibido una Tarjeta de Regalo de JerSix!";
        
        // Preparar el mensaje personalizado
        $recipientGreeting = !empty($recipientName) ? "Hola <strong>$recipientName</strong>," : "Hola,";
        $messageHtml = '';
        if (!empty($message)) {
            $messageHtml = '
            <div style="background-color: #f5f5f5; border-left: 4px solid #ddd; padding: 15px; margin: 15px 0; font-style: italic;">
                "' . htmlspecialchars($message) . '"
            </div>';
        }
        
        // Contenido HTML del correo
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding: 20px 0; }
                .giftcard { 
                    background: linear-gradient(135deg, #1a1a1a 0%, #333333 100%);
                    color: white;
                    padding: 30px;
                    border-radius: 10px;
                    margin: 20px 0;
                    position: relative;
                }
                .amount { 
                    font-size: 28px;
                    font-weight: bold;
                    margin-bottom: 15px;
                }
                .code {
                    background-color: #f5f5f5;
                    padding: 10px;
                    border-radius: 5px;
                    font-weight: bold;
                    text-align: center;
                    font-size: 18px;
                    margin: 15px 0;
                    color: #333;
                }
                .footer {
                    text-align: center;
                    font-size: 12px;
                    color: #777;
                    margin-top: 20px;
                }
                .message {
                    background-color: #f5f5f5;
                    border-left: 4px solid #ddd;
                    padding: 15px;
                    margin: 15px 0;
                    font-style: italic;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <img src='https://jersix.mx/img/LogoNav.png' alt='JerSix Logo' style='max-width: 150px;'>
                    <h1>¡Has recibido una Tarjeta de Regalo!</h1>
                </div>
                
                <p>$recipientGreeting</p>
                
                <p>Has recibido una Tarjeta de Regalo de <strong>" . htmlspecialchars($senderName) . "</strong>. ¡Ahora puedes comprar tus jerseys favoritos en JerSix!</p>
                
                $messageHtml
                
                <div class='giftcard'>
                    <div class='amount'>$" . number_format($amount, 2) . " MXN</div>
                    <p>Utiliza el siguiente código para redimir tu tarjeta:</p>
                    <div class='code'>$code</div>
                </div>
                
                <h3>¿Cómo usar tu tarjeta de regalo?</h3>
                <ol>
                    <li>Visita nuestra tienda en <a href='https://jersix.mx'>jersix.mx</a></li>
                    <li>Selecciona los productos que deseas comprar</li>
                    <li>Al finalizar tu compra, ingresa tu código de regalo</li>
                </ol>
                
                <p>¡Esperamos que disfrutes de tu regalo!</p>
                
                <div class='footer'>
                    <p>Si tienes alguna pregunta, contáctanos en <a href='mailto:contacto@jersix.mx'>contacto@jersix.mx</a></p>
                    <p>&copy; " . date('Y') . " JerSix - Todos los derechos reservados</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->send();
        writeLog("Correo enviado exitosamente a: $recipient");
        return true;
    } catch (Exception $e) {
        writeLog("Error al enviar correo: " . $mail->ErrorInfo);
        return false;
    }
}

// Verificar si existe la columna giftcard_sent en la tabla order_items
try {
    $columnExists = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'giftcard_sent'")->rowCount() > 0;
    
    if (!$columnExists) {
        // Crear la columna si no existe
        $pdo->exec("ALTER TABLE order_items ADD COLUMN giftcard_sent TINYINT(1) DEFAULT 0");
    }
    
    // Verificar si existe la columna giftcard_status para estados múltiples
    $statusColumnExists = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'giftcard_status'")->rowCount() > 0;
    
    if (!$statusColumnExists) {
        // Crear la columna de estado si no existe:
        // 0 = pendiente, 1 = en proceso, 2 = enviada
        $pdo->exec("ALTER TABLE order_items ADD COLUMN giftcard_status VARCHAR(20) DEFAULT 'pendiente'");
        
        // Actualizar los registros existentes basados en giftcard_sent
        $pdo->exec("UPDATE order_items SET giftcard_status = 
                   CASE WHEN giftcard_sent = 1 THEN 'enviada' ELSE 'pendiente' END");
    }
} catch (PDOException $e) {
    $errorMessage = "Error al verificar la estructura de la base de datos: " . $e->getMessage();
}

// Procesar reenvío de gift card
$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'resend') {
        $giftcardId = $_POST['giftcard_id'] ?? 0;
        
        try {
            // Obtener detalles de la gift card
            $stmt = $pdo->prepare("
                SELECT oi.*, o.customer_name, p.name as product_name
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_item_id = ? AND (p.name LIKE '%Tarjeta de Regalo%' OR oi.personalization_name LIKE '%@%')
            ");
            $stmt->execute([$giftcardId]);
            $giftcard = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log para debug
            writeLog("Gift card encontrada: ID=" . $giftcardId . ", Estado enviado=" . (isset($giftcard['giftcard_sent']) ? $giftcard['giftcard_sent'] : 'no definido'));
            
            if ($giftcard) {
                $recipientEmail = $giftcard['personalization_name']; // Email está en personalization_name
                $code = $giftcard['personalization_number']; // Código está en personalization_number
                $amount = $giftcard['price'];
                $senderName = $giftcard['customer_name'];
                
                // Verificar si ya estaba marcada como enviada
                $wasAlreadySent = isset($giftcard['giftcard_sent']) && $giftcard['giftcard_sent'] == 1;
                $currentStatus = $giftcard['giftcard_status'] ?? 'pendiente';
                
                // Extraer información adicional del campo personalization_patch
                $recipientName = '';
                $message = '';
                $customSenderName = '';
                
                if (!empty($giftcard['personalization_patch'])) {
                    try {
                        // Desglosar el string separado por "|"
                        $parts = explode('|', $giftcard['personalization_patch']);
                        
                        foreach ($parts as $part) {
                            if (strpos($part, 'RCP:') === 0) {
                                $recipientName = base64_decode(substr($part, 4));
                            } elseif (strpos($part, 'MSG:') === 0) {
                                $message = base64_decode(substr($part, 4));
                            } elseif (strpos($part, 'SND:') === 0) {
                                $customSenderName = base64_decode(substr($part, 4));
                            }
                        }
                        
                        writeLog("Destinatario decodificado: " . $recipientName);
                        writeLog("Mensaje decodificado: " . $message);
                        writeLog("Remitente decodificado: " . $customSenderName);
                    } catch (Exception $e) {
                        writeLog("Error al decodificar datos: " . $e->getMessage());
                    }
                }
                
                // Usar el nombre de remitente personalizado si está disponible
                if (!empty($customSenderName)) {
                    $senderName = $customSenderName;
                }
                
                // Primero marcar como "en proceso" antes de enviar
                $updateProcessStmt = $pdo->prepare("
                    UPDATE order_items 
                    SET giftcard_status = 'en proceso'
                    WHERE order_item_id = ?
                ");
                $updateProcessStmt->execute([$giftcardId]);
                
                // Enviar correo incluyendo el nombre del destinatario y el mensaje
                if (sendGiftCardEmail($recipientEmail, $amount, $code, $senderName, $recipientName, $message)) {
                    // Actualizar estado de envío en la base de datos
                    $updateStmt = $pdo->prepare("
                        UPDATE order_items 
                        SET giftcard_sent = 1, 
                            giftcard_status = 'enviada'
                        WHERE order_item_id = ?
                    ");
                    $updateStmt->execute([$giftcardId]);
                    
                    // Actualizar el estado de la orden a "processing" (en proceso)
                    $updateOrderStmt = $pdo->prepare("
                        UPDATE orders o
                        JOIN order_items oi ON o.order_id = oi.order_id
                        SET o.status = 'processing'
                        WHERE oi.order_item_id = ?
                    ");
                    $updateOrderStmt->execute([$giftcardId]);
                    
                    // Mensaje personalizado según si es primer envío o reenvío
                    if ($wasAlreadySent) {
                        $successMessage = "Gift card reenviada con éxito a $recipientEmail";
                    } else {
                        $successMessage = "Gift card enviada por primera vez con éxito a $recipientEmail";
                    }
                } else {
                    $errorMessage = "Error al enviar el correo a $recipientEmail. La gift card permanece en estado 'en proceso'.";
                }
            } else {
                $errorMessage = "No se encontró la gift card solicitada";
            }
        } catch (PDOException $e) {
            $errorMessage = "Error en la base de datos: " . $e->getMessage();
        }
    }
    // Nuevo: Manejar acción de marcar como enviada sin reenviar correo
    else if ($_POST['action'] === 'mark_sent') {
        $giftcardId = $_POST['giftcard_id'] ?? 0;
        
        try {
            // Verificar que existe la gift card
            $checkStmt = $pdo->prepare("
                SELECT oi.*, o.customer_name, p.name as product_name
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_item_id = ? AND (p.name LIKE '%Tarjeta de Regalo%' OR oi.personalization_name LIKE '%@%')
            ");
            $checkStmt->execute([$giftcardId]);
            $giftcard = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($giftcard) {
                // Actualizar estado de envío en la base de datos
                $updateStmt = $pdo->prepare("
                    UPDATE order_items 
                    SET giftcard_sent = 1,
                        giftcard_status = 'enviada' 
                    WHERE order_item_id = ?
                ");
                $updateStmt->execute([$giftcardId]);
                
                // Actualizar el estado de la orden a "processing" (en proceso)
                $updateOrderStmt = $pdo->prepare("
                    UPDATE orders o
                    JOIN order_items oi ON o.order_id = oi.order_id
                    SET o.status = 'processing'
                    WHERE oi.order_item_id = ?
                ");
                $updateOrderStmt->execute([$giftcardId]);
                
                $successMessage = "Gift card marcada como enviada";
                writeLog("Gift card ID " . $giftcardId . " marcada como enviada manualmente por admin");
            } else {
                $errorMessage = "No se encontró la gift card solicitada";
            }
        } catch (PDOException $e) {
            $errorMessage = "Error en la base de datos: " . $e->getMessage();
        }
    }
}

// Obtener todas las gift cards
try {
    // Verificar que existe la columna giftcard_sent para diagnóstico
    $columnExists = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'giftcard_sent'")->rowCount() > 0;
    
    if (!$columnExists) {
        writeLog("ADVERTENCIA: La columna giftcard_sent no existe en la tabla order_items");
    } else {
        writeLog("La columna giftcard_sent existe en la tabla order_items");
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            oi.*,
            o.customer_name, 
            o.customer_email, 
            o.created_at,
            o.order_id,
            p.name as product_name,
            IFNULL(oi.giftcard_sent, 0) as giftcard_sent_checked,
            IFNULL(oi.giftcard_status, 'pendiente') as giftcard_status_check,
            oi.personalization_name as recipient_email,
            oi.personalization_number as giftcard_code
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE (
            p.name LIKE '%Tarjeta de Regalo%' 
            OR p.name LIKE '%Gift Card%'
            OR oi.personalization_name LIKE '%@%'
        )
        AND oi.quantity > 0
        ORDER BY 
            CASE 
                WHEN oi.giftcard_sent = 0 THEN 0 
                ELSE 1 
            END,
            o.created_at DESC
    ");
    $stmt->execute();
    $giftcards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Diagnosticar resultados
    writeLog("Se encontraron " . count($giftcards) . " gift cards en el sistema");
    
    if (!empty($giftcards)) {
        foreach ($giftcards as $index => $gc) {
            writeLog("Gift card #{$index}: ID={$gc['order_item_id']}, estado_envio=" . 
                    (isset($gc['giftcard_sent']) ? $gc['giftcard_sent'] : 'null') . 
                    ", estado_comprobado=" . $gc['giftcard_sent_checked'] . 
                    ", estado=" . $gc['giftcard_status_check']);
        }
    }
} catch (PDOException $e) {
    $errorMessage = "Error al obtener las gift cards: " . $e->getMessage();
    $giftcards = [];
    writeLog("ERROR: " . $errorMessage);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Gift Cards - Panel de Administración</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --sidebar-width: 240px;
            --topbar-height: 60px;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #333;
            background-color: #f5f7fa;
            line-height: 1.5;
        }
        
        .dashboard-layout {
            display: grid;
            grid-template-columns: var(--sidebar-width) 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            width: var(--sidebar-width);
            padding-top: 15px;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .sidebar .nav-menu {
            list-style: none;
            padding: 15px 0;
        }
        
        .sidebar .nav-item {
            margin: 5px 0;
        }
        
        .sidebar .nav-item a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-radius: 4px;
            margin: 0 8px;
            transition: var(--transition);
        }
        
        .sidebar .nav-item a i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .sidebar .nav-item a:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-item.active a {
            color: white;
            background: var(--primary-color);
        }
        
        /* Main content */
        .main-content {
            grid-column: 2;
            padding: 30px;
        }
        
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .topbar h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            background: white;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .user-info span {
            margin-right: 15px;
            color: var(--secondary-color);
        }
        
        .user-info .btn {
            margin-left: 10px;
        }
        
        .table-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        tbody tr:hover {
            background-color: #f5f7fa;
        }
        
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            margin-top: 8px;
        }
        
        .badge-success {
            color: #fff;
            background-color: var(--success-color);
        }
        
        .badge-warning {
            color: #212529;
            background-color: var(--warning-color);
        }
        
        .badge-info {
            color: #fff;
            background-color: var(--info-color);
        }
        
        .alert {
            position: relative;
            padding: 1rem 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: var(--border-radius);
        }
        
        .alert-success {
            color: #0f5132;
            background-color: #d1e7dd;
            border-color: #badbcc;
        }
        
        .alert-danger {
            color: #842029;
            background-color: #f8d7da;
            border-color: #f5c2c7;
        }
        
        /* Botones */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 14px;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .text-truncate {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .actions {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding-top: 15px;
        }
        
        .actions button {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 12px;
        }
        
        /* Panel */
        .panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .panel-header {
            padding: 20px;
            border-bottom: 1px solid #eaedf3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .panel-body {
            padding: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                width: 0;
                padding-top: 60px;
            }
            
            .sidebar.active {
                width: var(--sidebar-width);
            }
            
            .main-content {
                grid-column: 1;
                padding-top: calc(var(--topbar-height) + 20px);
            }
            
            .mobile-toggle {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background: var(--primary-color);
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .table-container {
                overflow-x: auto;
            }
        }

        /* Estilos para el modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px 8px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #333;
        }

        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .close:hover {
            color: #333;
        }

        .transactions-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }

        .transactions-table th,
        .transactions-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .transactions-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .transactions-table tr:hover {
            background-color: #f8f9fa;
        }

        .giftcard-summary {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .summary-item {
            text-align: center;
        }

        .summary-item .label {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 5px;
        }

        .summary-item .value {
            font-size: 1.125rem;
            font-weight: 600;
            color: #333;
        }

        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px;
        }

        .loading-spinner i {
            font-size: 2rem;
            color: #007bff;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Jersix.mx</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Inicio</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php">
                        <i class="fas fa-box"></i>
                        <span>Productos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Compras</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="newsletter.php">
                        <i class="fas fa-users"></i>
                        <span>Clientes / Newsletter</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="giftcards.php">
                        <i class="fas fa-gift"></i>
                        <span>Gift Cards</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="promociones.php">
                        <i class="fas fa-percent me-2"></i>
                        <span>Promociones</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="banner_config.php">
                        <i class="fas fa-image"></i>
                        <span>Banner</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="banner_manager.php">
                        <i class="fas fa-images"></i>
                        <span>Fotos y Lo más vendido</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pedidos.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Pedidos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div>
                    <h1>Gestión de Gift Cards</h1>
                </div>
                <div class="user-info">
                    <img src="../img/ICON.png" alt="User" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                    <span><?php echo $_SESSION['admin_name'] ?? 'Administrador'; ?></span>
                </div>
            </div>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Listado de Gift Cards</h3>
                </div>
                <div class="panel-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Orden</th>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th>Monto</th>
                                    <th>Email Destinatario</th>
                                    <th>Código</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($giftcards)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center;">No se encontraron gift cards en el sistema</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($giftcards as $giftcard): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($giftcard['order_item_id']); ?></td>
                                            <td><?php echo htmlspecialchars($giftcard['order_id']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($giftcard['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($giftcard['product_name']); ?></td>
                                            <td>$<?php echo number_format($giftcard['price'], 2); ?> MXN</td>
                                            <td class="text-truncate" title="<?php echo htmlspecialchars($giftcard['recipient_email']); ?>">
                                                <?php echo htmlspecialchars($giftcard['recipient_email']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($giftcard['giftcard_code']); ?></td>
                                            <td>
                                                <?php if (isset($giftcard['giftcard_status_check'])): ?>
                                                    <?php 
                                                    $statusClass = '';
                                                    $statusText = $giftcard['giftcard_status_check'];
                                                    
                                                    switch($statusText) {
                                                        case 'enviada':
                                                            $statusClass = 'badge-success';
                                                            break;
                                                        case 'en proceso':
                                                            $statusClass = 'badge-info';
                                                            break;
                                                        default:
                                                            $statusClass = 'badge-warning';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($statusText); ?></span>
                                                <?php else: ?>
                                                    <?php if (isset($giftcard['giftcard_sent_checked']) && $giftcard['giftcard_sent_checked']): ?>
                                                        <span class="badge badge-success">Enviada</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">Pendiente</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions">
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="resend">
                                                    <input type="hidden" name="giftcard_id" value="<?php echo $giftcard['order_item_id']; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm" title="<?php echo (isset($giftcard['giftcard_sent_checked']) && $giftcard['giftcard_sent_checked']) ? 'Reenviar Gift Card' : 'Enviar Gift Card'; ?>">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </button>
                                                </form>
                                                
                                                <!-- Botón para marcar como enviado sin reenviar correo -->
                                                <?php if (!isset($giftcard['giftcard_sent_checked']) || !$giftcard['giftcard_sent_checked']): ?>
                                                <form method="post" style="display: inline; margin-left: 5px;">
                                                    <input type="hidden" name="action" value="mark_sent">
                                                    <input type="hidden" name="giftcard_id" value="<?php echo $giftcard['order_item_id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" title="Marcar como enviada" onclick="return confirm('¿Confirmar que esta gift card ya fue enviada?');">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Sección de redenciones de gift cards -->
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Redenciones de Gift Cards</h3>
                </div>
                
                <div class="table-container">
                    <?php
                    // Verificar si existe la tabla de redenciones
                    try {
                        $tableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_redemptions'")->rowCount() > 0;
                        $transTableExists = $pdo->query("SHOW TABLES LIKE 'giftcard_transactions'")->rowCount() > 0;
                        
                        if ($tableExists && $transTableExists) {
                            // Obtener todas las redenciones
                            $redemptionStmt = $pdo->query("
                                SELECT 
                                    r.*,
                                    COUNT(t.id) as transactions_count,
                                    IFNULL(SUM(t.amount), 0) as total_redeemed
                                FROM giftcard_redemptions r
                                LEFT JOIN giftcard_transactions t ON r.code = t.code
                                GROUP BY r.id
                                ORDER BY r.updated_at DESC
                            ");
                            $redemptions = $redemptionStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if ($redemptions) {
                                ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Monto Original</th>
                                            <th>Saldo Actual</th>
                                            <th>Estado</th>
                                            <th>Uso</th>
                                            <th>Última Actualización</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($redemptions as $redemption): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($redemption['code']); ?></td>
                                                <td>$<?php echo number_format($redemption['original_amount'], 2); ?></td>
                                                <td>$<?php echo number_format($redemption['balance'], 2); ?></td>
                                                <td>
                                                    <?php if ($redemption['redeemed']): ?>
                                                        <span class="status-badge completed">Redimido</span>
                                                    <?php else: ?>
                                                        <span class="status-badge pending">Activo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    // Usar el valor de total_redeemed de la consulta SQL
                                                    $used = $redemption['total_redeemed'];
                                                    $percent = ($redemption['original_amount'] > 0) ? 
                                                        round(($used / $redemption['original_amount']) * 100, 0) : 0;
                                                    echo "$" . number_format($used, 2) . " (" . $percent . "%)"; 
                                                    ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($redemption['updated_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary view-transactions-btn" 
                                                            data-code="<?php echo htmlspecialchars($redemption['code']); ?>">
                                                        <i class="fas fa-history"></i> Transacciones
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php } else { ?>
                                <div class="empty-state">
                                    <p>No hay redenciones de gift cards registradas</p>
                                </div>
                            <?php }
                        } else { ?>
                            <div class="empty-state">
                                <p>El sistema de redenciones no está configurado aún</p>
                            </div>
                        <?php }
                    } catch (PDOException $e) { ?>
                        <div class="error-message">
                            <p>Error al obtener redenciones: <?php echo $e->getMessage(); ?></p>
                        </div>
                    <?php } ?>
                </div>
            </div>
            
            <!-- Modal para ver transacciones -->
            <div id="transactionsModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-history"></i> Transacciones de Gift Card</h3>
                        <span class="close">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="transactions-list">
                            <!-- Aquí se cargarán las transacciones -->
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Mobile menu toggle (visible on small screens) -->
    <div class="mobile-toggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </div>
    
    <script>
        // Responsive sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (window.innerWidth <= 768) {
                mobileToggle.style.display = 'flex';
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    mobileToggle.style.display = 'flex';
                } else {
                    mobileToggle.style.display = 'none';
                    sidebar.classList.add('active');
                }
            });
            
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
            
            // Mensaje de confirmación al reenviar
            document.querySelectorAll('form[action=""]').forEach(form => {
                form.addEventListener('submit', function(event) {
                    if (!confirm('¿Estás seguro de que deseas reenviar esta gift card?')) {
                        event.preventDefault();
                    }
                });
            });
        });
        
        // Funciones para manejar modales y eventos
        function closeTransactionsModal() {
            document.getElementById('transactionsModal').style.display = 'none';
        }
        
        // Cargar transacciones cuando se hace clic en el botón
        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-transactions-btn');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const code = this.getAttribute('data-code');
                    loadTransactions(code);
                });
            });
        });
        
        function loadTransactions(code) {
            const modal = document.getElementById('transactionsModal');
            const transactionsList = document.getElementById('transactions-list');
            
            // Mostrar modal con animación
            modal.style.display = 'block';
            setTimeout(() => modal.classList.add('show'), 10);
            
            // Mostrar loading spinner
            transactionsList.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                </div>
            `;
            
            // Hacer la petición AJAX
            fetch('get_transactions.php?code=' + encodeURIComponent(code))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const transactions = data.transactions;
                        const redemption = data.redemption || {};
                        
                        let html = `
                            <div class="giftcard-summary">
                                <div class="summary-grid">
                                    <div class="summary-item">
                                        <div class="label">Código</div>
                                        <div class="value">${code}</div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="label">Monto Original</div>
                                        <div class="value">$${parseFloat(redemption.original_amount || 0).toFixed(2)}</div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="label">Saldo Actual</div>
                                        <div class="value">$${parseFloat(redemption.balance || 0).toFixed(2)}</div>
                                    </div>
                                </div>
                            </div>
                        `;

                        if (transactions && transactions.length > 0) {
                            html += `
                                <table class="transactions-table">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Monto</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;

                            transactions.forEach(transaction => {
                                const date = new Date(transaction.transaction_date).toLocaleString('es-MX');
                                html += `
                                    <tr>
                                        <td>#${transaction.order_id}</td>
                                        <td>$${parseFloat(transaction.amount).toFixed(2)}</td>
                                        <td>${date}</td>
                                        <td>
                                            <a href="orders.php?id=${transaction.order_id}" 
                                               class="btn btn-sm btn-primary" 
                                               target="_blank">
                                                <i class="fas fa-eye"></i> Ver Orden
                                            </a>
                                        </td>
                                    </tr>
                                `;
                            });

                            html += `
                                    </tbody>
                                </table>
                            `;
                        } else {
                            html += `
                                <div style="text-align: center; padding: 20px;">
                                    <i class="fas fa-info-circle" style="font-size: 2rem; color: #6c757d; margin-bottom: 10px;"></i>
                                    <p>No hay transacciones registradas para esta gift card.</p>
                                </div>
                            `;
                        }
                        
                        transactionsList.innerHTML = html;
                    } else {
                        transactionsList.innerHTML = `
                            <div style="text-align: center; padding: 20px; color: #dc3545;">
                                <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>${data.message || 'Error al cargar las transacciones'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    transactionsList.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #dc3545;">
                            <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Error al cargar las transacciones</p>
                        </div>
                    `;
                    console.error('Error:', error);
                });
        }

        // Cerrar modal
        document.querySelector('.close').addEventListener('click', function() {
            const modal = document.getElementById('transactionsModal');
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        });

        // Cerrar modal al hacer clic fuera
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('transactionsModal');
            if (event.target === modal) {
                modal.classList.remove('show');
                setTimeout(() => modal.style.display = 'none', 300);
            }
        });
    </script>
</body>
</html> 
