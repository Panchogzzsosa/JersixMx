<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Acceso denegado');
}

if (!isset($_GET['order_id'])) {
    exit('ID de orden no proporcionado');
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=checkout', 'root', '');
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
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="4" class="text-end"><strong>Total:</strong></td>
                    <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
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