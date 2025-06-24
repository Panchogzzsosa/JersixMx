<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

// Conexión a la base de datos
$pdo = getConnection();

// Obtener todas las ventas
$stmt = $pdo->prepare("
    SELECT s.sale_id, p.name as producto, s.size, s.price, s.location, s.sale_type, s.sale_date, s.notes
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    ORDER BY s.sale_date DESC
");
$stmt->execute();
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="ventas-jersix.csv"');

$output = fopen('php://output', 'w');
// Encabezados
fputcsv($output, ['#', 'Producto', 'Talla', 'Precio', 'Ubicacion', 'Tipo', 'Fecha', 'Notas']);

$contador = 1;
foreach ($ventas as $venta) {
    fputcsv($output, [
        $contador++,
        $venta['producto'],
        $venta['size'],
        number_format($venta['price'],2),
        $venta['location'],
        ($venta['sale_type']=='online'?'Online':'Offline'),
        date('d/m/Y',strtotime($venta['sale_date'])),
        $venta['notes']
    ]);
}
fclose($output);
exit; 