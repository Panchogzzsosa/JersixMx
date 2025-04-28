<?php
/**
 * Script para procesar la cola de correos pendientes
 * Este archivo puede ejecutarse manualmente o configurarse como tarea CRON
 */

// Configuración de logger
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/email_queue.log';

// Función para escribir en el log
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage; // También mostrar en consola
}

// Información de remitente
$fromEmail = 'no-reply@jersix.mx';
$fromName = 'JerSix';

// Dirección del servidor SMTP y puerto
$smtpServer = 'localhost';
$smtpPort = 25;

// Carpeta con correos en cola
$mailQueueFolder = __DIR__ . '/mail_queue';
if (!file_exists($mailQueueFolder)) {
    mkdir($mailQueueFolder, 0777, true);
    writeLog("Carpeta de cola de correos creada: $mailQueueFolder");
}

// Obtener archivos de la cola
$queueFiles = glob($mailQueueFolder . '/*.json');
$totalFiles = count($queueFiles);

writeLog("Iniciando procesamiento de cola de correos ($totalFiles en cola)");

// Procesar cada archivo
$processedCount = 0;
$successCount = 0;
$errorCount = 0;

foreach ($queueFiles as $index => $queueFile) {
    $processedCount++;
    $fileName = basename($queueFile);
    
    writeLog("[$processedCount/$totalFiles] Procesando $fileName");
    
    // Leer datos del correo
    $emailData = json_decode(file_get_contents($queueFile), true);
    if (!$emailData) {
        writeLog("ERROR: No se pudo decodificar el archivo $fileName");
        $errorCount++;
        continue;
    }
    
    // Extraer datos
    $to = $emailData['to'] ?? '';
    $subject = $emailData['subject'] ?? '';
    $body = $emailData['body'] ?? '';
    $headers = $emailData['headers'] ?? [];
    $orderId = $emailData['order_id'] ?? 'desconocido';
    
    if (empty($to) || empty($body)) {
        writeLog("ERROR: Faltan datos esenciales en $fileName");
        $errorCount++;
        continue;
    }
    
    // Convertir headers de array a string si es necesario
    if (is_array($headers)) {
        // Asegurarse de que el remitente sea jersixmx@gmail.com
        $hasFromHeader = false;
        foreach ($headers as $index => $header) {
            if (strpos($header, 'From:') === 0) {
                $headers[$index] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
                $hasFromHeader = true;
            }
            if (strpos($header, 'Reply-To:') === 0) {
                $headers[$index] = 'Reply-To: ' . $fromEmail;
            }
        }
        
        if (!$hasFromHeader) {
            $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
            $headers[] = 'Reply-To: ' . $fromEmail;
        }
        
        $headers = implode("\r\n", $headers);
    } else {
        // Si ya es string, asegurarse que tenga los remitentes correctos
        if (strpos($headers, 'From:') === false) {
            $headers .= "\r\nFrom: " . $fromName . " <" . $fromEmail . ">";
        }
        if (strpos($headers, 'Reply-To:') === false) {
            $headers .= "\r\nReply-To: " . $fromEmail;
        }
    }
    
    try {
        // Cargar PHPMailer y configuración
        require_once __DIR__ . '/configure_gmail_smtp.php';
        
        // Enviar correo con Gmail SMTP en lugar de mail() nativo
        $mailSent = sendGmailEmail($to, $subject, $body);
        
        if ($mailSent) {
            writeLog("ÉXITO: Correo para pedido #$orderId enviado a $to usando Gmail SMTP");
            $successCount++;
            
            // Mover a carpeta de procesados
            $processedFolder = $mailQueueFolder . '/processed';
            if (!file_exists($processedFolder)) {
                mkdir($processedFolder, 0777, true);
            }
            
            rename($queueFile, $processedFolder . '/' . $fileName);
        } else {
            writeLog("ERROR: Gmail SMTP no pudo enviar el correo a $to (pedido #$orderId)");
            $errorCount++;
        }
    } catch (Exception $e) {
        writeLog("ERROR: Excepción al enviar correo - " . $e->getMessage());
        $errorCount++;
    }
    
    // Pequeña pausa para no sobrecargar el servidor
    usleep(500000); // 0.5 segundos
}

// Resumen
writeLog("Procesamiento completado:");
writeLog("- Total archivos: $totalFiles");
writeLog("- Enviados exitosamente: $successCount");
writeLog("- Con errores: $errorCount"); 