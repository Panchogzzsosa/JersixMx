<?php
/**
 * Script para crear y verificar el archivo de log de correos de pedidos
 * Este archivo soluciona el problema de permisos o ausencia del archivo de logs
 */

// Directorio de logs
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    if (mkdir($logDir, 0777, true)) {
        echo "✅ Directorio de logs creado correctamente: $logDir\n";
    } else {
        echo "❌ ERROR: No se pudo crear el directorio de logs: $logDir\n";
        exit(1);
    }
}

// Archivo de log de correos de pedidos
$orderEmailsLog = $logDir . '/order_emails.log';

// Verificar si existe, si no, crearlo
if (!file_exists($orderEmailsLog)) {
    $timestamp = date('Y-m-d H:i:s');
    $initialContent = "[$timestamp] Archivo de log de correos de pedidos creado\n";
    
    if (file_put_contents($orderEmailsLog, $initialContent)) {
        echo "✅ Archivo de log de correos creado correctamente: $orderEmailsLog\n";
        
        // Establecer permisos adecuados
        if (chmod($orderEmailsLog, 0666)) {
            echo "✅ Permisos establecidos correctamente para el archivo de log\n";
        } else {
            echo "⚠️ Advertencia: No se pudieron establecer permisos para el archivo de log\n";
        }
    } else {
        echo "❌ ERROR: No se pudo crear el archivo de log: $orderEmailsLog\n";
        exit(1);
    }
} else {
    echo "✅ El archivo de log ya existe: $orderEmailsLog\n";
    
    // Verificar permisos
    $perms = fileperms($orderEmailsLog);
    $isWritable = is_writable($orderEmailsLog);
    
    if ($isWritable) {
        echo "✅ El archivo de log tiene permisos de escritura\n";
        
        // Añadir una entrada de prueba
        $timestamp = date('Y-m-d H:i:s');
        $testEntry = "[$timestamp] Verificación de escritura en el archivo de log\n";
        
        if (file_put_contents($orderEmailsLog, $testEntry, FILE_APPEND)) {
            echo "✅ Prueba de escritura exitosa\n";
        } else {
            echo "❌ ERROR: Prueba de escritura fallida\n";
        }
    } else {
        echo "❌ ERROR: El archivo de log no tiene permisos de escritura\n";
        
        // Intentar corregir permisos
        if (chmod($orderEmailsLog, 0666)) {
            echo "✅ Permisos corregidos exitosamente\n";
        } else {
            echo "❌ ERROR: No se pudieron corregir los permisos\n";
        }
    }
}

// Verificar otros archivos de log relacionados con correos
$mailErrorLog = $logDir . '/mail_error.log';
if (!file_exists($mailErrorLog)) {
    file_put_contents($mailErrorLog, "[$timestamp] Archivo de log de errores de correo creado\n");
    chmod($mailErrorLog, 0666);
    echo "✅ Archivo de log de errores de correo creado: $mailErrorLog\n";
}

$mailQueueLog = $logDir . '/mail_queue.log';
if (!file_exists($mailQueueLog)) {
    file_put_contents($mailQueueLog, "[$timestamp] Archivo de log de cola de correos creado\n");
    chmod($mailQueueLog, 0666);
    echo "✅ Archivo de log de cola de correos creado: $mailQueueLog\n";
}

// Verificar carpeta de cola de correos
$mailQueueFolder = __DIR__ . '/mail_queue';
if (!file_exists($mailQueueFolder)) {
    if (mkdir($mailQueueFolder, 0777, true)) {
        echo "✅ Carpeta de cola de correos creada: $mailQueueFolder\n";
        
        // Crear subcarpeta processed
        $processedFolder = $mailQueueFolder . '/processed';
        if (mkdir($processedFolder, 0777, true)) {
            echo "✅ Carpeta de correos procesados creada: $processedFolder\n";
        }
    } else {
        echo "❌ ERROR: No se pudo crear la carpeta de cola de correos\n";
    }
} else {
    echo "✅ La carpeta de cola de correos ya existe\n";
}

echo "\n✅ Configuración de logs de correos completada\n";

// Si estamos en un servidor web, añadir un enlace para volver
if (isset($_SERVER['HTTP_HOST'])) {
    echo '<br><a href="index.php">Volver al inicio</a>';
}
?> 