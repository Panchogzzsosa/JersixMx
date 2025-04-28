<?php
/**
 * Funciones para agregar correos a la cola de envío
 * 
 * Este sistema permite enqueuar correos para que sean procesados
 * posteriormente por el script send_email_queue.php
 */

/**
 * Agrega un correo a la cola de envío
 * 
 * @param string $to Dirección de correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $body Cuerpo HTML del correo
 * @param array $headers Cabeceras adicionales (opcional)
 * @param string $order_id ID del pedido (opcional)
 * @return bool True si se encola correctamente
 */
function addToMailQueue($to, $subject, $body, $headers = [], $order_id = null) {
    // Carpeta para la cola de correos
    $mailQueueFolder = __DIR__ . '/mail_queue';
    if (!file_exists($mailQueueFolder)) {
        mkdir($mailQueueFolder, 0777, true);
    }
    
    // Crear ID único para el correo en cola
    $queue_id = uniqid('mail_') . '_' . time();
    
    // Datos a guardar
    $emailData = [
        'to' => $to,
        'subject' => $subject,
        'body' => $body,
        'headers' => $headers,
        'order_id' => $order_id,
        'created_at' => date('Y-m-d H:i:s'),
        'attempts' => 0
    ];
    
    // Guardar en archivo JSON
    $queueFile = $mailQueueFolder . '/' . $queue_id . '.json';
    $result = file_put_contents($queueFile, json_encode($emailData, JSON_PRETTY_PRINT));
    
    // Registrar en log
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/mail_queue.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] ";
    
    if ($result) {
        $logMessage .= "Correo agregado a la cola: $to, asunto: $subject, ID: $queue_id";
        if ($order_id) {
            $logMessage .= ", pedido: $order_id";
        }
    } else {
        $logMessage .= "ERROR: No se pudo agregar el correo a la cola para: $to";
    }
    
    file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
    
    return $result !== false;
}

/**
 * Función para enviar correo inmediatamente o agregarlo a la cola
 * 
 * @param string $to Dirección de correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $body Cuerpo HTML del correo
 * @param bool $useQueue True para usar cola, false para envío inmediato
 * @param string $order_id ID del pedido (opcional)
 * @return bool Resultado del envío o encolado
 */
function sendMail($to, $subject, $body, $useQueue = true, $order_id = null) {
    // Si usa cola, agregar a la cola y terminar
    if ($useQueue) {
        return addToMailQueue($to, $subject, $body, [], $order_id);
    }
    
    // Si no usa cola, intentar envío directo con Gmail SMTP
    try {
        require_once __DIR__ . '/configure_gmail_smtp.php';
        return sendGmailEmail($to, $subject, $body);
    } catch (Exception $e) {
        // Si falla Gmail, intentar con el método del hosting
        require_once __DIR__ . '/configure_hosting_mail.php';
        return sendHostingEmail($to, $subject, $body);
    }
}

/**
 * Ejemplo de uso:
 * 
 * // Para agregar a la cola:
 * addToMailQueue('cliente@ejemplo.com', 'Tu pedido #123', '<p>Gracias por tu compra</p>', [], '123');
 * 
 * // O con la función unificada:
 * sendMail('cliente@ejemplo.com', 'Tu pedido #123', '<p>Gracias por tu compra</p>', true, '123');
 * 
 * // Para envío inmediato:
 * sendMail('cliente@ejemplo.com', 'Tu pedido #123', '<p>Gracias por tu compra</p>', false, '123');
 */ 