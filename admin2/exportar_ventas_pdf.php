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
    // Método para calcular el número de líneas de un texto en un ancho dado
    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'',(string)$txt);
        $nb = strlen($s);
        if($nb>0 and $s[$nb-1]=="\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i<$nb)
        {
            $c = $s[$i];
            if($c=="\n")
            {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep = $i;
            $l += $cw[$c];
            if($l>$wmax)
            {
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                }
                else
                    $i = $sep+1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
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
    // Preparar los datos de la fila
    $row = [
        $contador++,
        iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $venta['producto']),
        iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $venta['size']),
        '$'.number_format($venta['price'],2),
        iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $venta['location']),
        ($venta['sale_type']=='online'?'Online':'Offline'),
        date('d/m/Y',strtotime($venta['sale_date'])),
        iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $venta['notes'])
    ];
    $widths = [10, 55, 15, 22, 32, 20, 28, 55];
    $aligns = ['C','L','C','R','L','C','C','L'];
    // Calcular la altura necesaria para la celda de Notas
    $nbLinesNotas = $pdf->NbLines($widths[7], $row[7] !== '' ? $row[7] : ' ');
    $h = max(8, $nbLinesNotas * 5);
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    // Imprimir las primeras 7 celdas
    for ($i = 0; $i < 7; $i++) {
        $pdf->SetXY($x + array_sum(array_slice($widths, 0, $i)), $y);
        $pdf->Cell($widths[$i], $h, $row[$i], 1, 0, $aligns[$i], $fill);
    }
    // Imprimir la celda de Notas
    $pdf->SetXY($x + array_sum(array_slice($widths, 0, 7)), $y);
    if (trim($row[7]) === '') {
        // Si la nota está vacía, usar Cell para que la celda tenga la misma altura que la fila
        $pdf->Cell($widths[7], $h, '', 1, 0, $aligns[7], $fill);
    } else {
        // Si hay nota, usar MultiCell
        $pdf->MultiCell($widths[7], 5, $row[7], 1, $aligns[7], $fill);
    }
    // Ajustar la posición para la siguiente fila
    $pdf->SetXY($x, $y + $h);
    $fill = !$fill;
}

$pdf->Output('D','Ventas-jersix.pdf');
exit; 