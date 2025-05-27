# Configuración del Sistema de Correos para JersixMx

Este documento explica cómo configurar y utilizar el sistema de envío de correos en el hosting.

## Archivos del Sistema

1. `configure_hosting_mail.php` - Configuración para usar las funciones nativas del hosting
2. `configure_gmail_smtp.php` - Configuración para usar Gmail como servidor SMTP
3. `add_to_mail_queue.php` - Funciones para agregar correos a la cola
4. `send_email_queue.php` - Script para procesar la cola de correos
5. `mail_test.php` - Script para probar las configuraciones de correo

## Configuración del Remitente

Los correos se envían desde **no-reply@jersix.mx**. Esta configuración está establecida en:

- `configure_hosting_mail.php` - Variables `$senderEmail` y `$senderName`
- `configure_gmail_smtp.php` - Variables `$senderEmail` y `$senderName`
- `send_email_queue.php` - Variables `$fromEmail` y `$fromName`

Si necesitas cambiar la dirección de remitente, debes modificar estas tres ubicaciones.

## Configuración Inicial

### 1. Dependencias

Asegúrate de tener PHPMailer instalado:

```bash
# Navegar al directorio del proyecto
cd /Applications/XAMPP/xamppfiles/htdocs/JersixMx

# Instalar PHPMailer usando Composer
composer require phpmailer/phpmailer
```

### 2. Configuración de Gmail SMTP

Para usar Gmail SMTP, necesitas:

1. Una cuenta de Gmail con verificación en dos pasos activada
2. Una contraseña de aplicación generada para la web

Pasos para generar la contraseña de aplicación:

1. Ve a https://myaccount.google.com/security
2. En "Iniciar sesión en Google", selecciona "Contraseñas de aplicaciones"
3. Selecciona "Otra (nombre personalizado)" y escribe "JersixMx Web"
4. Copia la contraseña generada y pégala en el archivo `configure_gmail_smtp.php` en la variable `$gmailPassword`

> **Nota importante**: Aunque los correos aparecerán enviados desde **no-reply@jersix.mx**, la autenticación SMTP se realiza con **jersixmx@gmail.com**. Este es el comportamiento esperado.

### 3. Crear Directorios Necesarios

```php
// Estos directorios se crearán automáticamente al usar las funciones
// pero puedes crearlos manualmente si lo prefieres
mkdir -p logs mail_queue mail_queue/processed
chmod 777 logs mail_queue mail_queue/processed
```

## Probar el Sistema

Para verificar que todo funciona correctamente:

1. Abre en tu navegador: `http://tu-dominio.com/mail_test.php`
2. Verifica que ambos métodos envíen correos correctamente
3. Revisa los logs generados en la carpeta `/logs`
4. Comprueba que el remitente sea **no-reply@jersix.mx**

## Uso del Sistema

### Envío Directo

Para enviar un correo inmediatamente:

```php
// Incluir la función
require_once __DIR__ . '/add_to_mail_queue.php';

// Enviar correo directamente (sin cola)
sendMail(
    'cliente@ejemplo.com',        // Destinatario
    'Tu pedido #123',             // Asunto
    '<p>Gracias por tu compra</p>', // Cuerpo HTML
    false,                        // false = sin cola (envío inmediato)
    '123'                         // ID de pedido (opcional)
);
```

### Uso de Cola de Correos (Recomendado)

Para agregar correos a la cola:

```php
// Incluir la función
require_once __DIR__ . '/add_to_mail_queue.php';

// Agregar correo a la cola
sendMail(
    'cliente@ejemplo.com',        // Destinatario
    'Tu pedido #123',             // Asunto
    '<p>Gracias por tu compra</p>', // Cuerpo HTML
    true,                         // true = usar cola
    '123'                         // ID de pedido (opcional)
);
```

### Procesamiento de la Cola

Para procesar la cola de correos, configura una tarea CRON en el panel de control de tu hosting:

```
# Ejecutar cada 30 minutos
*/30 * * * * php /ruta/completa/a/send_email_queue.php
```

O puedes ejecutarlo manualmente cuando lo necesites:

```bash
php send_email_queue.php
```

## Solución de Problemas

### Correos no Enviados

1. Revisa los logs en la carpeta `/logs`
2. Verifica que PHPMailer esté instalado correctamente
3. Comprueba que la contraseña de aplicación de Gmail sea correcta
4. Asegúrate de que el hosting permite envío de correos

### Mejorar la Entrega de Correos

1. Configura registros SPF, DKIM y DMARC para tu dominio
2. Utiliza una dirección de correo que coincida con tu dominio
3. Incluye versión de texto plano junto con HTML (ya implementado)
4. Evita palabras o frases típicas de spam
5. Mantén una relación equilibrada entre texto e imágenes

## Recomendaciones

1. **Usa siempre la cola de correos** para envíos de producción
2. Configura la tarea CRON para procesar la cola regularmente
3. Monitorea los logs para detectar problemas
4. Si hay muchos fallos, considera usar un servicio SMTP externo como SendGrid o Mailgun 