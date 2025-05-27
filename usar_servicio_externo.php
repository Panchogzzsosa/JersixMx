<?php
// Mostrar página de instrucciones
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Servicio Externo de Correo - JerSix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2, h3 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-top: 25px;
        }
        .container {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        code {
            background-color: #f1f1f1;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        pre {
            background-color: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
            overflow-x: auto;
            font-family: monospace;
        }
        .note {
            background-color: #fff8e1;
            border-left: 5px solid #ffc107;
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 3px;
        }
        .button {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 15px;
        }
        .button:hover {
            background-color: #45a049;
        }
        .step {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Configurar un Servicio Externo de Correo</h1>
        <p>Si no puedes enviar correos directamente desde tu servidor, la mejor solución es utilizar un servicio externo especializado. Estos servicios ofrecen mejor entregabilidad y monitoreo que el envío directo desde PHP.</p>
    </div>
    
    <h2>Opciones Recomendadas</h2>
    
    <div class="step">
        <h3>Opción 1: SendGrid (Recomendada)</h3>
        <p>SendGrid ofrece un plan gratuito de 100 correos por día, suficiente para la mayoría de sitios pequeños.</p>
        
        <ol>
            <li>Regístrate en <a href="https://sendgrid.com/" target="_blank">SendGrid</a></li>
            <li>Crea una clave API en el panel de control</li>
            <li>Instala la biblioteca oficial con Composer:</li>
        </ol>
        
        <pre>composer require sendgrid/sendgrid</pre>
        
        <p>Código para enviar con SendGrid:</p>
        
        <pre>
// Ejemplo de código SendGrid
require 'vendor/autoload.php';

$email = new \SendGrid\Mail\Mail();
$email->setFrom("no-reply@jersix.mx", "JerSix");
$email->setSubject("Confirmación de Pedido #123");
$email->addTo("cliente@example.com", "Nombre Cliente");
$email->addContent("text/html", "&lt;p&gt;Gracias por tu compra...&lt;/p&gt;");

$sendgrid = new \SendGrid('TU_API_KEY');
try {
    $response = $sendgrid->send($email);
    echo "Correo enviado con éxito";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}</pre>
    </div>
    
    <div class="step">
        <h3>Opción 2: Mailgun</h3>
        <p>Mailgun ofrece un plan gratuito con 5,000 correos por mes durante 3 meses, luego requiere pago.</p>
        
        <ol>
            <li>Regístrate en <a href="https://www.mailgun.com/" target="_blank">Mailgun</a></li>
            <li>Configura y verifica tu dominio</li>
            <li>Instala la biblioteca con Composer:</li>
        </ol>
        
        <pre>composer require mailgun/mailgun-php guzzlehttp/psr7 php-http/guzzle6-adapter</pre>
        
        <p>Código para enviar con Mailgun:</p>
        
        <pre>
// Ejemplo de código Mailgun
require 'vendor/autoload.php';
use Mailgun\Mailgun;

$mg = Mailgun::create('TU_API_KEY');
$domain = "mail.jersix.mx";

$mg->messages()->send($domain, [
    'from'    => 'JerSix &lt;no-reply@jersix.mx&gt;',
    'to'      => 'cliente@example.com',
    'subject' => 'Confirmación de Pedido #123',
    'html'    => '&lt;p&gt;Gracias por tu compra...&lt;/p&gt;'
]);

echo "Correo enviado con éxito";</pre>
    </div>
    
    <div class="step">
        <h3>Opción 3: Amazon SES</h3>
        <p>Amazon SES es muy económico ($0.10 por 1,000 correos) y altamente escalable.</p>
        
        <ol>
            <li>Crea una cuenta en <a href="https://aws.amazon.com/es/ses/" target="_blank">Amazon AWS</a></li>
            <li>Configura Amazon SES y verifica tu dominio</li>
            <li>Instala la biblioteca AWS SDK para PHP:</li>
        </ol>
        
        <pre>composer require aws/aws-sdk-php</pre>
        
        <p>Código para enviar con Amazon SES:</p>
        
        <pre>
// Ejemplo de código Amazon SES
require 'vendor/autoload.php';

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

$SesClient = new SesClient([
    'version' => 'latest',
    'region'  => 'us-east-1',
    'credentials' => [
        'key'    => 'TU_AWS_ACCESS_KEY',
        'secret' => 'TU_AWS_SECRET_KEY',
    ],
]);

try {
    $result = $SesClient->sendEmail([
        'Destination' => [
            'ToAddresses' => ['cliente@example.com'],
        ],
        'Message' => [
            'Body' => [
                'Html' => [
                    'Charset' => 'UTF-8',
                    'Data' => '&lt;p&gt;Gracias por tu compra...&lt;/p&gt;',
                ],
            ],
            'Subject' => [
                'Charset' => 'UTF-8',
                'Data' => 'Confirmación de Pedido #123',
            ],
        ],
        'Source' => 'JerSix &lt;no-reply@jersix.mx&gt;',
    ]);
    echo "Correo enviado con éxito";
} catch (AwsException $e) {
    echo "Error: " . $e->getMessage();
}</pre>
    </div>
    
    <h2>Integrando con Tu Tienda</h2>
    <p>Para integrar cualquiera de estas soluciones con tu tienda, necesitarás:</p>
    
    <ol>
        <li>Instalar Composer si no lo tienes: <a href="https://getcomposer.org/download/" target="_blank">https://getcomposer.org/download/</a></li>
        <li>Instalar la biblioteca requerida mediante Composer</li>
        <li>Crear un archivo <code>mailer.php</code> con la configuración del servicio elegido</li>
        <li>Modificar <code>order_confirmation_email.php</code> para usar tu nuevo servicio</li>
    </ol>
    
    <h3>Ejemplo de archivo mailer.php para SendGrid</h3>
    <pre>
&lt;?php
// mailer.php - Configuración de SendGrid
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Función para enviar correo usando SendGrid
 */
function sendMailExternal($to, $subject, $htmlContent, $from = 'no-reply@jersix.mx', $fromName = 'JerSix') {
    // Log para depuración
    if (function_exists('writeLogEmail')) {
        writeLogEmail("Enviando correo a través de SendGrid a: " . $to);
    }
    
    try {
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($from, $fromName);
        $email->setSubject($subject);
        $email->addTo($to);
        $email->addContent("text/html", $htmlContent);
        
        $sendgrid = new \SendGrid('TU_API_KEY');
        $response = $sendgrid->send($email);
        
        $success = $response->statusCode() >= 200 && $response->statusCode() < 300;
        
        if (function_exists('writeLogEmail')) {
            if ($success) {
                writeLogEmail("Correo enviado exitosamente con SendGrid. Código: " . $response->statusCode());
            } else {
                writeLogEmail("Error al enviar correo con SendGrid. Código: " . $response->statusCode());
            }
        }
        
        return $success;
    } catch (Exception $e) {
        if (function_exists('writeLogEmail')) {
            writeLogEmail("Excepción al enviar correo con SendGrid: " . $e->getMessage());
        }
        return false;
    }
}
</pre>

    <h3>Modificación de order_confirmation_email.php</h3>
    <p>En tu archivo <code>order_confirmation_email.php</code>, reemplaza el código de envío de correo por:</p>
    
    <pre>
// Intentar enviar usando el servicio externo
require_once __DIR__ . '/mailer.php';
$mailResult = sendMailExternal($customerEmail, $subject, $htmlMessage, 'no-reply@jersix.mx', 'JerSix');

if ($mailResult) {
    writeLogEmail("Correo enviado exitosamente a " . $customerEmail . " usando servicio externo");
    return true;
} else {
    writeLogEmail("ERROR: Falló el envío de correo usando servicio externo para " . $customerEmail);
    return false;
}
</pre>

    <div class="note">
        <strong>Importante:</strong> La mayoría de servicios de envío de correo requieren verificación de dominio para mejorar la entregabilidad. Sigue sus instrucciones para verificar que eres propietario del dominio jersix.mx.
    </div>
    
    <h2>Diagnóstico y Solución de Problemas</h2>
    <p>Si tienes problemas al implementar cualquiera de estas soluciones:</p>
    
    <ol>
        <li>Verifica que Composer está instalado correctamente</li>
        <li>Asegúrate de que las claves API son correctas</li>
        <li>Revisa los logs de errores de PHP</li>
        <li>Consulta la documentación específica del servicio que estás utilizando</li>
    </ol>
    
    <p><a href="mail_diagnostico.php" class="button">Volver al Diagnóstico</a> <a href="index.php" class="button">Volver al Inicio</a></p>
</body>
</html> 