<?php
// Iniciar buffer de salida para evitar errores con TCPDF
ob_start();

require_once '../../config/conexion.php';
require_once '../../includes/functions.php';

// Verificar autenticación y permisos
session_start();
if (!isset($_SESSION['id_usuario'])) {
    die('Acceso no autorizado');
}

// Obtener y sanitizar parámetros
$formato = isset($_GET['formato']) ? strtolower(trim($_GET['formato'])) : 'excel';
$fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
$fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;

// Validar formato
$formatosPermitidos = ['excel', 'csv', 'pdf'];
if (!in_array($formato, $formatosPermitidos)) {
    die("Formato no soportado. Use: " . implode(', ', $formatosPermitidos));
}

// Validar fechas
if (($fechaInicio && !$fechaFin) || (!$fechaInicio && $fechaFin)) {
    die("Debes especificar ambas fechas o ninguna");
}

// Consulta SQL preparada para seguridad
$sql = "SELECT p.*, c.nombre as categoria FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.activo = 1";

$params = [];
$types = '';

if ($fechaInicio && $fechaFin) {
    $sql .= " AND p.fecha_creacion BETWEEN ? AND ?";
    $params[] = $fechaInicio;
    $params[] = $fechaFin . ' 23:59:59';
    $types .= 'ss';
}

$stmt = $conexion->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();
$productos = $resultado->fetch_all(MYSQLI_ASSOC);

if (empty($productos)) {
    die("No hay productos para exportar con los criterios seleccionados");
}

// Generar el archivo según el formato
switch ($formato) {
    case 'excel':
        exportarExcel($productos);
        break;
    case 'csv':
        exportarCSV($productos);
        break;
    case 'pdf':
        exportarPDF($productos);
        break;
}

function exportarExcel($datos) {
    require_once '../../vendor/autoload.php';
    
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Estilos para encabezados
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['argb' => 'FFD9D9D9']]
        ]);
        
        // Autoajustar columnas
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(20);
        
        // Encabezados
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Código de Barras');
        $sheet->setCellValue('C1', 'Descripción');
        $sheet->setCellValue('D1', 'Categoría');
        $sheet->setCellValue('E1', 'Stock');
        $sheet->setCellValue('F1', 'Precio Compra');
        $sheet->setCellValue('G1', 'Precio Venta');
        $sheet->setCellValue('H1', 'Fecha Creación');
        
        // Datos
        $fila = 2;
        foreach ($datos as $producto) {
            $sheet->setCellValue('A'.$fila, $producto['id']);
            $sheet->setCellValue('B'.$fila, $producto['codigo_barras']);
            $sheet->setCellValue('C'.$fila, $producto['descripcion']);
            $sheet->setCellValue('D'.$fila, $producto['categoria']);
            $sheet->setCellValue('E'.$fila, $producto['cantidad']);
            $sheet->setCellValue('F'.$fila, $producto['precio_compra']);
            $sheet->setCellValue('G'.$fila, $producto['precio_venta']);
            $sheet->setCellValue('H'.$fila, $producto['fecha_creacion']);
            $fila++;
        }
        
        // Formato de moneda
        $sheet->getStyle('F2:G'.$fila)->getNumberFormat()->setFormatCode('"$"#,##0.00');
        
        // Congelar primera fila
        $sheet->freezePane('A2');
        
        // Configurar headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="productos_'.date('YmdHis').'.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_end_clean();
        $writer->save('php://output');
        exit;
        
    } catch (\Exception $e) {
        die("Error al generar Excel: " . $e->getMessage());
    }
}

function exportarCSV($datos) {
    try {
        // Configurar headers
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment;filename="productos_'.date('YmdHis').'.csv"');
        
        // Abrir output
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Encabezados
        fputcsv($output, [
            'ID', 'Código de Barras', 'Descripción', 'Categoría', 
            'Stock', 'Precio Compra', 'Precio Venta', 'Fecha Creación'
        ], ';');
        
        // Datos
        foreach ($datos as $producto) {
            fputcsv($output, [
                $producto['id'],
                $producto['codigo_barras'],
                $producto['descripcion'],
                $producto['categoria'],
                $producto['cantidad'],
                number_format($producto['precio_compra'], 2, '.', ''),
                number_format($producto['precio_venta'], 2, '.', ''),
                $producto['fecha_creacion']
            ], ';');
        }
        
        ob_end_clean();
        fclose($output);
        exit;
        
    } catch (\Exception $e) {
        die("Error al generar CSV: " . $e->getMessage());
    }
}

function exportarPDF($productos) {
    // Limpieza exhaustiva de buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Incluir TCPDF directamente (evitar problemas de autoloader)
    require_once '../../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    // Clase personalizada con header/footer
    class MYPDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 10, 'Reporte de Productos - EasyStock', 0, 1, 'C');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 10, 'Generado: '.date('d/m/Y H:i'), 0, 1, 'C');
            $this->Ln(5);
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
        }
    }

    // Crear instancia
    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Configuración esencial
    $pdf->SetCreator('EasyStock');
    $pdf->SetAuthor('EasyStock');
    $pdf->SetTitle('Reporte de Productos');
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    // Construir HTML directamente (evitar concatenación compleja)
    $html = '
    <style>
        h2 { color: #0066cc; margin-bottom: 5px; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        th { background-color: #333333; color: white; padding: 5px; text-align: left; }
        td { padding: 5px; border: 1px solid #dddddd; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .stock-bajo { color: #ff0000; font-weight: bold; }
        .stock-medio { color: #ff9900; }
        .stock-alto { color: #00aa00; }
    </style>
    
    <h2>LISTADO DE PRODUCTOS</h2>
    
    <table>
        <tr>
            <th width="10%">ID</th>
            <th width="20%">Código</th>
            <th width="30%">Descripción</th>
            <th width="15%">Categoría</th>
            <th width="10%" class="text-center">Stock</th>
            <th width="15%" class="text-right">P. Venta</th>
        </tr>';

    foreach ($productos as $producto) {
        $stockClass = ($producto['cantidad'] <= 5) ? 'stock-bajo' : 
                     (($producto['cantidad'] <= 15) ? 'stock-medio' : 'stock-alto');
        
        $html .= '
        <tr>
            <td>'.$producto['id'].'</td>
            <td>'.$producto['codigo_barras'].'</td>
            <td>'.htmlspecialchars($producto['descripcion']).'</td>
            <td>'.$producto['categoria'].'</td>
            <td class="text-center '.$stockClass.'">'.$producto['cantidad'].'</td>
            <td class="text-right">$'.number_format($producto['precio_venta'], 2).'</td>
        </tr>';
    }

    $html .= '
    </table>
    
    <div style="margin-top: 20px; font-size: 10px;">
        <strong>Total productos: </strong>'.count($productos).'
        <div style="border-top: 1px solid #333; width: 200px; margin-top: 20px; padding-top: 3px;">
            Firma responsable: _______________________
        </div>
    </div>';

    // Escribir contenido (método más confiable)
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Generar PDF
    $pdf->Output('reporte_productos_'.date('Ymd_His').'.pdf', 'D');
    exit;
}