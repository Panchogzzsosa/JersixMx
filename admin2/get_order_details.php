<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Acceso denegado');
}

if (!isset($_GET['order_id'])) {
    exit('ID de orden no proporcionado');
}



// Incluir el archivo de configuración de la base de datos
require_once __DIR__ . '/../config/database.php';

// Función para calcular el precio real de un producto
function calcularPrecioReal($item) {
    // Precio base del producto
    $precioBase = floatval($item['price']);
    $precioFinal = $precioBase;
    
    // Verificar si tiene personalización (nombre o número)
    $tienePersonalizacion = !empty($item['personalization_name']) || !empty($item['personalization_number']);
    
    // Verificar si tiene parche
    $tieneParche = false;
    if (!empty($item['personalization_patch'])) {
        // Considerar varios casos especiales
        if ($item['personalization_patch'] === '1') {
            // Para jerseys, "1" significa que tiene parche
            $tieneParche = true;
        } else if ($item['personalization_patch'] !== '0' && 
                  $item['personalization_patch'] !== '2' && 
                  $item['personalization_patch'] !== '3' &&
                  strpos($item['personalization_patch'], 'TIPO:') !== 0 &&
                  strpos($item['personalization_patch'], 'RCP:') !== 0) {
            $tieneParche = true;
        }
    }
    
    // Verificar si es una jersey o camiseta
    $nombreProducto = strtolower($item['product_name']);
    $esJersey = (strpos($nombreProducto, 'jersey') !== false || 
                strpos($nombreProducto, 'camiseta') !== false ||
                strpos($nombreProducto, 'milan') !== false ||  // Agregar palabras clave comunes
                strpos($nombreProducto, 'manchester') !== false ||
                strpos($nombreProducto, 'barcelona') !== false ||
                strpos($nombreProducto, 'real madrid') !== false) &&
                strpos($nombreProducto, 'gift card') === false;
    
    // Si es jersey y tiene personalización, añadir costo
    if ($esJersey) {
        if ($tienePersonalizacion) {
            $precioFinal += 100; // Personalización: +$100
        }
        
        if ($tieneParche) {
            $precioFinal += 50; // Parche: +$50
        }
    }
    
    // Calcular subtotal (precio final * cantidad)
    $subtotal = $item['quantity'] * $precioFinal;
    
    return [
        'precio_base' => $precioBase,
        'precio_final' => $precioFinal,
        'subtotal' => $subtotal,
        'tiene_personalizacion' => $tienePersonalizacion,
        'tiene_parche' => $tieneParche,
        'es_jersey' => $esJersey
    ];
}

try {
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener detalles de la orden
    $stmt = $pdo->prepare("
        SELECT o.*, oi.*, p.name as product_name, p.image_url
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE o.order_id = ?
    ");
    
    $stmt->execute([$_GET['order_id']]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orderItems)) {
        exit('Orden no encontrada');
    }

    // Usar el primer item para la información general de la orden
    $order = $orderItems[0];
?>

<style>
    .discount-row {
        background-color: #f8fff8;
    }
    
    .discount-amount {
        color: #28a745;
        font-weight: 500;
    }
    
    .final-total {
        background-color: #f8f9fa;
        border-top: 2px solid #dee2e6;
    }
    
    .text-right {
        text-align: right;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h4>Información del Cliente</h4>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
        </div>
        <div class="col-md-6">
            <h4>Dirección de Envío</h4>
            <p><?php echo htmlspecialchars($order['street']); ?></p>
            <p><?php echo htmlspecialchars($order['colony']); ?></p>
            <p><?php echo htmlspecialchars($order['city']) . ', ' . htmlspecialchars($order['state']); ?></p>
            <p><?php echo htmlspecialchars($order['zip_code']); ?></p>
        </div>
    </div>

    <h4>Productos</h4>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Talla</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Total</th>
                    <th>Personalización</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                $hasGiftCardDiscount = !empty($order['payment_notes']) && 
                    (strpos($order['payment_notes'], 'Gift Card aplicada') !== false || 
                     strpos($order['payment_notes'], 'Pago realizado completamente con Gift Card') !== false);
                
                $hasPromoDiscount = !empty($order['payment_notes']) && 
                     strpos($order['payment_notes'], 'Código promocional aplicado') !== false;
                
                $giftCardAmount = 0;
                if ($hasGiftCardDiscount) {
                    // Extraer el monto de descuento de Gift Card de las notas de pago
                    preg_match('/Gift Card aplicada: .+\- Monto: \$([0-9.]+)/', $order['payment_notes'], $matches);
                    if (isset($matches[1])) {
                        $giftCardAmount = floatval($matches[1]);
                    } else if (strpos($order['payment_notes'], 'Pago realizado completamente con Gift Card') !== false) {
                        // Si fue un pago completo, el descuento será igual al total
                        $giftCardAmount = $order['total_amount'];
                    }
                }
                
                $promoAmount = 0;
                if ($hasPromoDiscount) {
                    // Extraer el monto de descuento de Código Promocional de las notas de pago
                    preg_match('/Código promocional aplicado: .+\- Descuento: \$([0-9.]+)/', $order['payment_notes'], $matches);
                    if (isset($matches[1])) {
                        $promoAmount = floatval($matches[1]);
                    }
                }
                
                foreach ($orderItems as $item): 
                    // Usar nuestra función para calcular el precio real
                    $priceData = calcularPrecioReal($item);
                    
                    // Usar el precio calculado para mostrar en la interfaz
                    $displayPrice = $priceData['precio_final'];
                    $itemTotal = $priceData['subtotal'];
                    $total += $itemTotal;
                    
                    // Información de personalización para la interfaz
                    $tienePersonalizacion = $priceData['tiene_personalizacion'];
                    $tieneParche = $priceData['tiene_parche'];
                    $esJersey = $priceData['es_jersey'];
                ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                         style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($item['size']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>
                            <?php if ($esJersey && ($tienePersonalizacion || $tieneParche)): ?>
                                <div style="text-decoration: line-through; color: #888; font-size: 0.9em;">$<?php echo number_format($priceData['precio_base'], 2); ?></div>
                                <div style="font-weight: bold; margin-top: 3px;">$<?php echo number_format($displayPrice, 2); ?></div>
                                <?php if ($tienePersonalizacion): ?>
                                    <div style="color: #007bff; font-size: 0.8em; margin-top: 2px;">+$100 personalización</div>
                                <?php endif; ?>
                                <?php if ($tieneParche): ?>
                                    <div style="color: #28a745; font-size: 0.8em; margin-top: 2px;">+$50 parche</div>
                                <?php endif; ?>
                            <?php else: ?>
                                $<?php echo number_format($displayPrice, 2); ?>
                            <?php endif; ?>
                        </td>
                        <td>$<?php echo number_format($itemTotal, 2); ?></td>
                        <td>
                            <?php if (!empty($item['personalization_name']) || !empty($item['personalization_number']) || !empty($item['personalization_patch'])): ?>
                                <div style="font-size: 0.9em;">
                                    <?php if (!empty($item['personalization_name'])): ?>
                                        <div>
                                            <span style="color: #6b7280;">Nombre:</span> 
                                            <span style="font-weight: 500;"><?php echo htmlspecialchars($item['personalization_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['personalization_number'])): ?>
                                        <div style="margin-top: 2px;">
                                            <span style="color: #6b7280;">Número:</span> 
                                            <span style="font-weight: 500;"><?php echo htmlspecialchars($item['personalization_number']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['personalization_patch'])): ?>
                                        <?php if (strpos($item['personalization_patch'], 'TIPO:') === 0): ?>
                                            <?php
                                                // Extraer y decodificar el tipo
                                                $tipoEncoded = substr($item['personalization_patch'], 5);
                                                $tipo = base64_decode($tipoEncoded);
                                            ?>
                                            <div style="margin-top: 2px;">
                                                <span style="color: #6b7280;">Tipo:</span> 
                                                <span style="font-weight: 500;"><?php echo htmlspecialchars($tipo); ?></span>
                                            </div>
                                        <?php elseif ($item['product_name'] === 'Mystery Box'): ?>
                                            <div style="margin-top: 2px;">
                                                <span style="color: #6b7280;">Tipo:</span> 
                                                <span style="font-weight: 500;"><?php 
                                                    $tipos = [
                                                        '1' => 'Champions League',
                                                        '2' => 'Liga MX',
                                                        '3' => 'Liga Europea'
                                                    ];
                                                    echo isset($tipos[$item['personalization_patch']]) ? $tipos[$item['personalization_patch']] : 'No especificado';
                                                ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div style="margin-top: 2px; color: #16a34a;">
                                                <i class="fas fa-check-circle" style="margin-right: 2px;"></i>Parche
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #6b7280;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="order-total">
                    <td colspan="5" class="text-right"><strong>Total</strong></td>
                    <td><strong>$<?php echo number_format($total, 2); ?> MXN</strong></td>
                </tr>
                <?php if ($hasGiftCardDiscount && $giftCardAmount > 0): ?>
                <tr class="discount-row">
                    <td colspan="5" class="text-right">Descuento con Gift Card</td>
                    <td class="discount-amount">-$<?php echo number_format($giftCardAmount, 2); ?> MXN</td>
                </tr>
                <tr class="final-total">
                    <td colspan="5" class="text-right"><strong>Total Pagado</strong></td>
                    <td><strong>$<?php echo number_format(max(0, $total - $giftCardAmount), 2); ?> MXN</strong></td>
                </tr>
                <?php endif; ?>
                <?php if ($hasPromoDiscount && $promoAmount > 0): ?>
                <tr class="discount-row">
                    <td colspan="5" class="text-right">Descuento con Código Promocional</td>
                    <td class="discount-amount">-$<?php echo number_format($promoAmount, 2); ?> MXN</td>
                </tr>
                <tr class="final-total">
                    <td colspan="5" class="text-right"><strong>Total Final Pagado</strong></td>
                    <td><strong>$<?php echo number_format(max(0, ($total - $giftCardAmount) - $promoAmount), 2); ?> MXN</strong></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
} catch(PDOException $e) {
    echo 'Error al obtener los detalles de la orden: ' . $e->getMessage();
}
?> 