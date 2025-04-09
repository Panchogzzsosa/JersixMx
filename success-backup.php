<?php
// Obtener el ID de la orden si está disponible
$order_id = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : 'N/A';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Compra Exitosa! - JersixMx</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            text-align: center;
        }
        .success-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin-top: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .success-icon {
            color: #28a745;
            font-size: 60px;
            margin-bottom: 20px;
        }
        h1 {
            color: #28a745;
            margin-bottom: 20px;
        }
        .order-details {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h1>¡Compra Exitosa!</h1>
        <p>Tu pedido ha sido recibido y está siendo procesado.</p>
        
        <div class="order-details">
            <h2>Detalles del Pedido</h2>
            <p><strong>Número de Pedido:</strong> #<?php echo $order_id; ?></p>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>
            <p><strong>Estado:</strong> Procesando</p>
        </div>
        
        <p>Hemos enviado un correo electrónico de confirmación con los detalles de tu compra. Si no lo recibes en los próximos minutos, revisa tu carpeta de spam.</p>
        
        <p>Si tienes alguna pregunta sobre tu pedido, no dudes en contactarnos.</p>
        
        <a href="index.php" class="button">Volver a la Tienda</a>
    </div>
    
    <script>
    // Script para limpiar el carrito después de una compra exitosa
    document.addEventListener('DOMContentLoaded', function() {
        // Limpiar el carrito si no se ha limpiado ya
        if (localStorage.getItem('cart')) {
            localStorage.removeItem('cart');
            console.log('Carrito limpiado exitosamente');
        }
    });
    </script>
</body>
</html> 