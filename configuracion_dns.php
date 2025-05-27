<?php
include_once 'smtp_configuracion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración DNS para Correos - Jersix.mx</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        h2 {
            color: #3498db;
            margin-top: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .warning-box {
            background-color: #FFF3CD;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .success-box {
            background-color: #D4EDDA;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #ddd;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #2980b9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
        .status-warning {
            color: #ffc107;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        .step {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Configuración DNS para Envío de Correos</h1>
        
        <div class="info-box">
            <p>Esta página te guiará paso a paso para configurar correctamente los registros DNS de tu dominio <strong>jersix.mx</strong> para permitir el envío de correos electrónicos desde tu propio dominio.</p>
        </div>
        
        <?php
        // Verificar la configuración actual de DNS
        $dns_info = verificarConfiguracionDNS();
        ?>
        
        <h2>Estado Actual de tu Configuración DNS</h2>
        
        <table>
            <tr>
                <th>Tipo de Registro</th>
                <th>Estado</th>
                <th>Valor Actual</th>
            </tr>
            <tr>
                <td>Registros MX</td>
                <td class="<?php echo count($dns_info['mx']) > 0 ? 'status-ok' : 'status-error'; ?>">
                    <?php echo count($dns_info['mx']) > 0 ? 'Configurado' : 'No configurado'; ?>
                </td>
                <td>
                    <?php 
                    if (count($dns_info['mx']) > 0) {
                        foreach ($dns_info['mx'] as $mx) {
                            echo "Prioridad " . $mx['prioridad'] . ": " . $mx['host'] . "<br>";
                        }
                    } else {
                        echo "No encontrado";
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td>Registro SPF</td>
                <td class="<?php echo !empty($dns_info['spf']) ? (strpos($dns_info['spf'], '~all') !== false ? 'status-warning' : 'status-ok') : 'status-error'; ?>">
                    <?php 
                    if (!empty($dns_info['spf'])) {
                        echo strpos($dns_info['spf'], '~all') !== false ? 'Configurado (Mejorable)' : 'Configurado';
                    } else {
                        echo "No configurado";
                    }
                    ?>
                </td>
                <td><?php echo !empty($dns_info['spf']) ? $dns_info['spf'] : 'No encontrado'; ?></td>
            </tr>
            <tr>
                <td>Registro DKIM</td>
                <td class="<?php echo !empty($dns_info['dkim']) ? 'status-ok' : 'status-error'; ?>">
                    <?php echo !empty($dns_info['dkim']) ? 'Configurado' : 'No configurado'; ?>
                </td>
                <td><?php echo !empty($dns_info['dkim']) ? substr($dns_info['dkim'], 0, 50) . '...' : 'No encontrado'; ?></td>
            </tr>
        </table>
        
        <?php if (!empty($dns_info['recomendaciones'])): ?>
        <div class="warning-box">
            <h3>Recomendaciones para mejorar tu configuración:</h3>
            <ul>
                <?php foreach ($dns_info['recomendaciones'] as $recomendacion): ?>
                <li><?php echo $recomendacion; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <h2>Pasos para Configurar Correctamente tus Registros DNS</h2>
        
        <div class="step">
            <h3>Paso 1: Configurar Registro MX</h3>
            <p>Los registros MX (Mail Exchange) son necesarios para recibir correos en tu dominio. También ayudan a validar el envío de correos desde tu dominio.</p>
            
            <div class="info-box">
                <p>Agrega los siguientes registros MX a tu dominio jersix.mx a través del panel de control de tu proveedor de DNS:</p>
                
                <table>
                    <tr>
                        <th>Host/Nombre</th>
                        <th>Tipo</th>
                        <th>Prioridad</th>
                        <th>Destino/Valor</th>
                    </tr>
                    <tr>
                        <td>@</td>
                        <td>MX</td>
                        <td>10</td>
                        <td>mail.jersix.mx</td>
                    </tr>
                </table>
                
                <p>Nota: También deberás configurar un registro A para mail.jersix.mx apuntando a la IP de tu servidor: 216.245.211.58</p>
            </div>
        </div>
        
        <div class="step">
            <h3>Paso 2: Configurar o Mejorar tu Registro SPF</h3>
            <p>El registro SPF (Sender Policy Framework) especifica qué servidores están autorizados para enviar correos desde tu dominio. Esto ayuda a prevenir la suplantación de identidad y mejora la entrega de correos.</p>
            
            <div class="info-box">
                <p>Actualiza tu registro SPF con el siguiente valor recomendado:</p>
                
                <pre>v=spf1 a mx ip4:216.245.211.58 -all</pre>
                
                <p>Añade este registro como un registro TXT para el host @ en tu panel DNS.</p>
                
                <p><strong>Explicación:</strong></p>
                <ul>
                    <li><code>v=spf1</code>: La versión del registro SPF.</li>
                    <li><code>a</code>: Permite que cualquier IP del dominio principal pueda enviar correos.</li>
                    <li><code>mx</code>: Permite que cualquier servidor MX definido pueda enviar correos.</li>
                    <li><code>ip4:216.245.211.58</code>: Permite que esta IP específica pueda enviar correos.</li>
                    <li><code>-all</code>: Indica que cualquier otro servidor no debería enviar correos (política estricta, mejor que ~all).</li>
                </ul>
            </div>
        </div>
        
        <div class="step">
            <h3>Paso 3: Configurar Registro DKIM (Opcional pero Recomendado)</h3>
            <p>DKIM (DomainKeys Identified Mail) agrega una firma digital a tus correos, lo que permite a los destinatarios verificar que los correos son legítimos y no han sido alterados.</p>
            
            <div class="info-box">
                <p>La configuración de DKIM requiere generar claves públicas y privadas. Como esto depende de la configuración de tu servidor, es un paso más avanzado.</p>
                
                <p>Para una configuración básica sin DKIM, tus correos aún pueden funcionar, pero tendrás mejor entrega si lo configuras.</p>
                
                <p>Si quieres configurar DKIM en el futuro, necesitarás:</p>
                <ol>
                    <li>Generar un par de claves DKIM (privada/pública).</li>
                    <li>Configurar tu servidor para usar la clave privada al enviar correos.</li>
                    <li>Agregar un registro TXT con la clave pública a tu DNS.</li>
                </ol>
            </div>
        </div>
        
        <div class="step">
            <h3>Paso 4: Verificar Configuración</h3>
            <p>Después de realizar los cambios en tus registros DNS, debes esperar a que se propaguen. Esto puede tomar de 15 minutos a 48 horas, dependiendo de tu proveedor de DNS.</p>
            
            <p>Una vez que se hayan propagado los cambios, puedes verificar tu configuración con estas herramientas:</p>
            
            <ul>
                <li><a href="https://mxtoolbox.com/SuperTool.aspx" target="_blank">MX Toolbox</a> - Verifica tus registros MX, SPF y más.</li>
                <li><a href="https://www.mail-tester.com/" target="_blank">Mail-Tester</a> - Envía un correo de prueba y recibe una calificación de tu configuración.</li>
            </ul>
            
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn">Verificar mi configuración actual</a>
        </div>
        
        <h2>Prueba de Envío de Correo</h2>
        
        <p>Una vez que hayas configurado tus registros DNS correctamente, puedes probar el envío de correos con el formulario a continuación:</p>
        
        <form action="prueba_envio.php" method="post">
            <div style="margin-bottom: 15px;">
                <label for="email" style="display: block; margin-bottom: 5px;">Correo de destino:</label>
                <input type="email" name="email" id="email" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <button type="submit" class="btn">Enviar correo de prueba</button>
        </form>
    </div>
</body>
</html> 