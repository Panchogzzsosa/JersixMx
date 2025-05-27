<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Start session with proper configuration
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require 'vendor/autoload.php';
require 'config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Enable comprehensive error logging
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/mail_error.log';
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $logFile);

// Ensure log file exists and is writable
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}

// Set default timezone
date_default_timezone_set('America/Mexico_City');

// Load email configuration
$emailConfig = [
    // Opción 1: Gmail (puede no funcionar en el servidor)
    'gmail' => [
        'host' => 'smtp.gmail.com',
        'username' => 'franciscogzz03@gmail.com',
        'password' => 'hnhg eczh wwyu vvgk',
        'port' => 465,
        'from_name' => 'Jersix',
        'secure' => PHPMailer::ENCRYPTION_SMTPS
    ],
    // Opción 2: SendGrid (registrarse en sendgrid.com y obtener API key)
    'sendgrid' => [
        'host' => 'smtp.sendgrid.net',
        'username' => 'apikey', // Siempre es 'apikey'
        'password' => 'SG.TU_API_KEY_AQUI', // Reemplazar con tu API key de SendGrid
        'port' => 587,
        'from_name' => 'Jersix',
        'secure' => PHPMailer::ENCRYPTION_STARTTLS
    ],
    // Opción 3: Función mail() nativa de PHP
    'php_mail' => [
        'from_email' => 'no-reply@jersix.mx',
        'from_name' => 'Jersix'
    ],
    // Cuál configuración usar: 'gmail', 'sendgrid', o 'php_mail'
    'use' => 'gmail'
];

function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendVerificationEmail($email, $code) {
    global $emailConfig;
    
    // Si estamos usando PHP mail() nativo, no necesitamos PHPMailer
    if ($emailConfig['use'] === 'php_mail') {
        try {
            $from = $emailConfig['php_mail']['from_email'];
            $name = $emailConfig['php_mail']['from_name'];
            
            $subject = 'Código de Verificación - Jerseys Shop';
            $message = "Tu código de verificación es: <b>$code</b>";
            
            // Para enviar un correo HTML, debe establecerse el encabezado Content-type
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: $name <$from>\r\n";
            
            if (mail($email, $subject, $message, $headers)) {
                error_log("Email enviado exitosamente a: $email usando PHP mail()");
                return true;
            } else {
                error_log("Error al enviar email usando PHP mail() a: $email");
                return false;
            }
        } catch (Exception $e) {
            error_log("Error en PHP mail(): " . $e->getMessage());
            return false;
        }
    }
    
    // Si llegamos aquí, usamos PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Elegir la configuración a usar
        $config = $emailConfig[$emailConfig['use']];
        
        // Server settings with enhanced debugging and error handling
        $mail->SMTPDebug = 3;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug [$level]: $str");
        };
        
        // Basic SMTP configuration
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['secure'];
        $mail->Port = $config['port'];
        
        // Extended timeout and keep-alive
        $mail->Timeout = 60;
        $mail->SMTPKeepAlive = true;

        // UTF-8 encoding
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Sender and recipient
        $mail->setFrom($config['username'], $config['from_name']);
        $mail->addAddress($email);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('Código de Verificación - Jerseys Shop') . '?=';
        $mail->Body = "Tu código de verificación es: <b>$code</b>";
        $mail->AltBody = "Tu código de verificación es: $code";

        // Send email and log result
        if (!$mail->send()) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            throw new Exception($mail->ErrorInfo);
        }
        
        error_log("Email sent successfully to: $email");
        return true;
    } catch (Exception $e) {
        error_log('Detailed Mailer Error: ' . $e->getMessage());
        error_log('SMTP Debug Info: ' . print_r($mail->SMTPDebug, true));
        return false;
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = file_get_contents('php://input');
        error_log('Received input: ' . $input);
        
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            throw new Exception('Invalid JSON data');
        }
        
        $action = $data['action'] ?? '';

        if ($action === 'send') {
            $email = $data['email'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }

            $code = generateVerificationCode();
            $_SESSION['verification_code'] = $code;
            $_SESSION['verification_email'] = $email;
            $_SESSION['verification_time'] = time();

            if (sendVerificationEmail($email, $code)) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Código enviado']);
            } else {
                throw new Exception('Error al enviar el código');
            }
        } elseif ($action === 'verify') {
            $code = $data['code'] ?? '';
            $email = $data['email'] ?? '';

            if (
                isset($_SESSION['verification_code']) &&
                isset($_SESSION['verification_email']) &&
                isset($_SESSION['verification_time']) &&
                $_SESSION['verification_code'] === $code &&
                $_SESSION['verification_email'] === $email &&
                (time() - $_SESSION['verification_time']) <= 600 // 10 minutes expiration
            ) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Código verificado']);
                // Clear verification data
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_email']);
                unset($_SESSION['verification_time']);
            } else {
                throw new Exception('Código inválido o expirado');
            }
        } else {
            throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        error_log('Error in verify_email.php: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}