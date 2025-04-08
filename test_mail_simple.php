<?php
/**
 * Prueba simple de envío de correos
 * Este script es un test básico para diagnosticar problemas de envío
 */

// Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Simple de Correo</h1>";

// Variables a modificar
$dominio = $_SERVER['HTTP_HOST'] ?? 'jersix.mx';
$para = 'jersixmx@gmail.com'; // Correo del destinatario

echo "<h2>Información del servidor:</h2>";
echo "<ul>";
echo "<li>Dominio: $dominio</li>";
echo "<li>Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li>PHP: " . phpversion() . "</li>";
echo "</ul>";

// Probar envío simple
function probarCorreoSimple() {
    global $dominio, $para;
    
    $remitente = "noreply@$dominio";
    $asunto = "Prueba sencilla desde $dominio - " . date('H:i:s');
    
    // Contenido simple
    $mensaje = "Este es un mensaje de prueba enviado el " . date('Y-m-d H:i:s');
    
    // Cabeceras mínimas
    $cabeceras = "From: Jersix <$remitente>\r\n";
    $cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    echo "<h3>Intentando enviar correo simple:</h3>";
    echo "<ul>";
    echo "<li>Para: $para</li>";
    echo "<li>De: $remitente</li>";
    echo "<li>Asunto: $asunto</li>";
    echo "</ul>";
    
    // Enviar correo
    $resultado = mail($para, $asunto, $mensaje, $cabeceras);
    
    // Mostrar resultado
    if ($resultado) {
        echo "<p style='color:green;font-weight:bold;'>✓ Correo enviado correctamente</p>";
        echo "<p>Revisa tu bandeja de entrada y carpeta de spam.</p>";
    } else {
        echo "<p style='color:red;font-weight:bold;'>✗ Error al enviar el correo</p>";
        echo "<p>La función mail() falló. Posiblemente el servidor de correo no está configurado correctamente.</p>";
    }
}

// Probar envío HTML
function probarCorreoHTML() {
    global $dominio, $para;
    
    $remitente = "noreply@$dominio";
    $asunto = "Prueba HTML desde $dominio - " . date('H:i:s');
    
    // Contenido HTML
    $mensaje = "
    <html>
    <head>
        <title>Prueba de correo HTML</title>
    </head>
    <body>
        <h1>Prueba de correo HTML</h1>
        <p>Este es un mensaje <strong>HTML</strong> enviado el " . date('Y-m-d H:i:s') . "</p>
        <p>Si puedes ver este mensaje con formato, el envío de correos HTML funciona.</p>
    </body>
    </html>";
    
    // Cabeceras para HTML
    $cabeceras = "From: Jersix <$remitente>\r\n";
    $cabeceras .= "MIME-Version: 1.0\r\n";
    $cabeceras .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    echo "<h3>Intentando enviar correo HTML:</h3>";
    echo "<ul>";
    echo "<li>Para: $para</li>";
    echo "<li>De: $remitente</li>";
    echo "<li>Asunto: $asunto</li>";
    echo "</ul>";
    
    // Enviar correo
    $resultado = mail($para, $asunto, $mensaje, $cabeceras);
    
    // Mostrar resultado
    if ($resultado) {
        echo "<p style='color:green;font-weight:bold;'>✓ Correo HTML enviado correctamente</p>";
        echo "<p>Revisa tu bandeja de entrada y carpeta de spam.</p>";
    } else {
        echo "<p style='color:red;font-weight:bold;'>✗ Error al enviar el correo HTML</p>";
        echo "<p>La función mail() falló para contenido HTML.</p>";
    }
}

// Formulario para elegir el tipo de prueba
if (isset($_POST['enviar'])) {
    $tipo = $_POST['tipo'] ?? 'simple';
    
    if ($tipo == 'simple') {
        probarCorreoSimple();
    } else {
        probarCorreoHTML();
    }
    
    echo "<hr>";
}

// Mostrar formulario
echo "<form method='post'>";
echo "<h3>Selecciona el tipo de prueba:</h3>";
echo "<select name='tipo'>";
echo "<option value='simple'>Correo Texto Plano</option>";
echo "<option value='html'>Correo HTML</option>";
echo "</select>";
echo "<br><br>";
echo "<button type='submit' name='enviar'>Ejecutar Prueba</button>";
echo "</form>";

// Recomendaciones
echo "<h2>Recomendaciones si los correos no se envían:</h2>";
echo "<ol>";
echo "<li>Verifica que el servidor tiene instalado y configurado el servicio de correo (sendmail, postfix, etc.)</li>";
echo "<li>Contacta a tu proveedor de hosting para confirmar si permiten la función mail() de PHP</li>";
echo "<li>Solicita a tu proveedor que confirme la configuración del remitente autorizado para tu dominio</li>";
echo "<li>Configura registros SPF y DKIM para tu dominio para mejorar la entrega</li>";
echo "</ol>";
?> 