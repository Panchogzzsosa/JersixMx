<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/fpdf186/fpdf.php';
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

// Crear PDF
class PDF extends FPDF {
    function Header() {
        // Título grande y centrado
        $this->SetFont('Arial','B',18);
        $this->Cell(0,12,'Reporte de Ventas',0,1,'C');
        // Fecha de generación debajo del título
        $this->SetFont('Arial','',10);
        $this->SetTextColor(100,100,100);
        $this->Cell(0,7,'Fecha de generacion: ' . date('d/m/Y H:i'),0,1,'C');
        $this->Ln(2);
        $this->SetTextColor(0,0,0);
        // Encabezados de tabla
        $this->SetFont('Arial','B',10);
        $this->SetFillColor(230,230,230);
        $this->SetDrawColor(180,180,180);
        $this->Cell(10,9,'#',1,0,'C',true);
        $this->Cell(55,9,'Producto',1,0,'C',true);
        $this->Cell(15,9,'Talla',1,0,'C',true);
        $this->Cell(22,9,'Precio',1,0,'C',true);
        $this->Cell(32,9,'Ubicacion',1,0,'C',true);
        $this->Cell(20,9,'Tipo',1,0,'C',true);
        $this->Cell(28,9,'Fecha',1,0,'C',true);
        $this->Cell(55,9,'Notas',1,1,'C',true);
    }
}

$pdf = new PDF('L','mm','A4');
$pdf->SetLeftMargin(30);
$pdf->SetMargins(30,20,10);
$pdf->AddPage();
$pdf->SetFont('Arial','',10);

$contador = 1;
$fill = false;
foreach ($ventas as $venta) {
    $pdf->SetFillColor(245,245,245);
    $pdf->SetDrawColor(200,200,200);
    $pdf->Cell(10,8,$contador++,1,0,'C',$fill);
    $pdf->Cell(55,8,$venta['producto'],1,0,'L',$fill);
    $pdf->Cell(15,8,$venta['size'],1,0,'C',$fill);
    $pdf->Cell(22,8,'$'.number_format($venta['price'],2),1,0,'R',$fill);
    $pdf->Cell(32,8,$venta['location'],1,0,'L',$fill);
    $pdf->Cell(20,8,($venta['sale_type']=='online'?'Online':'Offline'),1,0,'C',$fill);
    $pdf->Cell(28,8,date('d/m/Y',strtotime($venta['sale_date'])),1,0,'C',$fill);
    $nota = mb_strimwidth($venta['notes'], 0, 55, '...');
    $pdf->Cell(55,8,$nota,1,1,'L',$fill);
    $fill = !$fill;
}

$pdf->Output('D','Ventas-jersix.pdf');
exit; 