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
                foreach ($orderItems as $item): 
                    $itemTotal = $item['quantity'] * $item['price'];
                    $total += $itemTotal;
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
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
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
                <tr>
                    <td colspan="4" class="text-end"><strong>Total:</strong></td>
                    <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php
} catch(PDOException $e) {
    echo 'Error al obtener los detalles de la orden: ' . $e->getMessage();
}
?> 