<?php
require_once '../../config/conexion.php';

// Verificar permisos y sesión
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit();
}

// Obtener parámetros del formulario
$tipo_reporte = $_POST['tipo_reporte'] ?? 'mensual';
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
$formato = $_POST['formato'] ?? 'pdf';

// Validar fechas
if ($fecha_fin < $fecha_inicio) {
    $fecha_fin = $fecha_inicio;
}

// Consulta principal de ventas
$sql = "SELECT 
            ventas.id, 
            ventas.fecha_venta,
            ventas.total,
            clientes.nombre AS cliente_nombre,
            GROUP_CONCAT(DISTINCT CONCAT(productos.descripcion, ' (', venta_detalles.cantidad, ' x $', FORMAT(venta_detalles.precio_unitario, 2), ')') SEPARATOR '; ') AS productos_vendidos,
            SUM(venta_detalles.cantidad) AS total_items
        FROM ventas 
        LEFT JOIN clientes ON ventas.cliente_id = clientes.id
        JOIN venta_detalles ON ventas.id = venta_detalles.venta_id 
        JOIN productos ON venta_detalles.producto_id = productos.id
        WHERE ventas.fecha_venta BETWEEN ? AND ?
        GROUP BY ventas.id
        ORDER BY ventas.fecha_venta DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt->execute();
$ventas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estadísticas generales
$sql_estadisticas = "SELECT 
                        SUM(total) AS total_ventas,
                        COUNT(id) AS cantidad_ventas,
                        AVG(total) AS promedio_venta
                     FROM ventas
                     WHERE fecha_venta BETWEEN ? AND ?";

$stmt_estadisticas = $conexion->prepare($sql_estadisticas);
$stmt_estadisticas->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt_estadisticas->execute();
$estadisticas = $stmt_estadisticas->get_result()->fetch_assoc();
$stmt_estadisticas->close();

// Generar el reporte según el formato solicitado
if ($formato === 'pdf') {
    generarPDF($ventas, $estadisticas, $fecha_inicio, $fecha_fin, $tipo_reporte);
} elseif ($formato === 'excel') {
    generarExcel($ventas, $estadisticas, $fecha_inicio, $fecha_fin, $tipo_reporte);
} else {
    die("Formato de reporte no válido");
}

function generarPDF($ventas, $estadisticas, $fecha_inicio, $fecha_fin, $tipo_reporte) {
    require_once '../../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    // Crear nuevo documento PDF con orientación horizontal para mejor visualización
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurar metadatos del documento
    $pdf->SetCreator('EasyStock');
    $pdf->SetAuthor('Sistema EasyStock');
    $pdf->SetTitle('Reporte de Ventas - ' . ucfirst($tipo_reporte));
    $pdf->SetSubject('Reporte generado automáticamente');
    
    // Configurar márgenes
    $pdf->SetMargins(10, 20, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Configurar encabezado personalizado
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', 9));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', 8));
    
    // Agregar una página
    $pdf->AddPage();
    
    // Logo de la empresa (opcional)
    $logo = '../../assets/img/logo.png'; // Ajusta la ruta según tu estructura
    if (file_exists($logo)) {
        $pdf->Image($logo, 10, 10, 30, 0, 'PNG');
    }
    
    // Estilos CSS avanzados
    $style = "
        <style>
            .header-reporte { 
                background-color: #2c3e50; 
                color: white; 
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 15px;
            }
            h1 { 
                color: #2c3e50; 
                font-size: 20px; 
                margin-bottom: 5px;
            }
            .resumen-box {
                border: 1px solid #ddd;
                background-color: #f8f9fa;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 15px;
            }
            .resumen-item {
                margin-bottom: 5px;
            }
            .resumen-valor {
                font-weight: bold;
                color: #3498db;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                font-size: 10px;
            }
            th {
                background-color: #3498db;
                color: white;
                font-weight: bold;
                padding: 8px;
                text-align: left;
            }
            td {
                border: 1px solid #ddd;
                padding: 6px;
                vertical-align: top;
            }
            .total-row {
                background-color: #f1f1f1;
                font-weight: bold;
            }
            .productos-list {
                margin: 0;
                padding-left: 15px;
            }
        </style>
    ";
    
    // Contenido HTML del PDF
    $html = $style . '
    <div class="header-reporte">
        <h1>Reporte de Ventas - EasyStock</h1>
        <p><strong>Periodo:</strong> ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)) . '</p>
        <p><strong>Tipo de Reporte:</strong> ' . ucfirst($tipo_reporte) . '</p>
        <p><strong>Generado:</strong> ' . date('d/m/Y H:i') . '</p>
    </div>
    
    <div class="resumen-box">
        <h3>Resumen Estadístico</h3>
        <div class="resumen-item"><strong>Total Ventas:</strong> <span class="resumen-valor">$' . number_format($estadisticas['total_ventas'] ?? 0, 2) . '</span></div>
        <div class="resumen-item"><strong>Cantidad de Ventas:</strong> <span class="resumen-valor">' . ($estadisticas['cantidad_ventas'] ?? 0) . '</span></div>
        <div class="resumen-item"><strong>Venta Promedio:</strong> <span class="resumen-valor">$' . number_format($estadisticas['promedio_venta'] ?? 0, 2) . '</span></div>
    </div>
    
    <h3>Detalle de Ventas</h3>
    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="15%">Fecha</th>
                <th width="20%">Cliente</th>
                <th width="45%">Productos</th>
                <th width="15%">Total</th>
            </tr>
        </thead>
        <tbody>';
    
    // Agregar filas de ventas con mejor formato
    foreach ($ventas as $index => $venta) {
        $productos = explode('; ', $venta['productos_vendidos']);
        $productos_html = '<ul class="productos-list">';
        foreach ($productos as $producto) {
            $productos_html .= '<li>' . htmlspecialchars($producto) . '</li>';
        }
        $productos_html .= '</ul>';
        
        $html .= '
            <tr>
                <td>' . ($index + 1) . '</td>
                <td>' . date('d/m/Y H:i', strtotime($venta['fecha_venta'])) . '</td>
                <td>' . htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor final') . '</td>
                <td>' . $productos_html . '</td>
                <td>$' . number_format($venta['total'], 2) . '</td>
            </tr>';
    }
    
    // Fila de totales
    $html .= '
            <tr class="total-row">
                <td colspan="4" align="right"><strong>Total General:</strong></td>
                <td><strong>$' . number_format($estadisticas['total_ventas'] ?? 0, 2) . '</strong></td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin-top: 20px; font-size: 9px; color: #666; text-align: right;">
        Reporte generado automáticamente por EasyStock - ' . date('d/m/Y H:i') . '
    </div>';
    
    // Escribir el HTML en el PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Generar y descargar el PDF
    $pdf->Output('reporte_ventas_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}

function generarExcel($ventas, $estadisticas, $fecha_inicio, $fecha_fin, $tipo_reporte) {
    require_once '../../vendor/autoload.php';
    
    // Crear nuevo objeto Spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Establecer propiedades del documento
    $spreadsheet->getProperties()
        ->setCreator('EasyStock')
        ->setTitle('Reporte de Ventas - ' . ucfirst($tipo_reporte))
        ->setSubject('Reporte generado automáticamente');
    
    // Estilos
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C3E50']],
        'alignment' => ['horizontal' => 'center']
    ];
    
    $titleStyle = [
        'font' => ['bold' => true, 'size' => 16],
        'alignment' => ['horizontal' => 'center']
    ];
    
    $summaryStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F8F9FA']]
    ];
    
    // Encabezado del reporte
    $sheet->mergeCells('A1:E1');
    $sheet->setCellValue('A1', 'Reporte de Ventas - EasyStock');
    $sheet->getStyle('A1')->applyFromArray($titleStyle);
    
    $sheet->mergeCells('A2:E2');
    $sheet->setCellValue('A2', 'Periodo: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)));
    $sheet->getStyle('A2')->getFont()->setItalic(true);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');
    
    $sheet->mergeCells('A3:E3');
    $sheet->setCellValue('A3', 'Tipo: ' . ucfirst($tipo_reporte));
    $sheet->getStyle('A3')->getFont()->setItalic(true);
    $sheet->getStyle('A3')->getAlignment()->setHorizontal('center');
    
    // Resumen estadístico
    $sheet->mergeCells('A4:E4');
    $sheet->setCellValue('A4', 'Resumen Estadístico');
    $sheet->getStyle('A4')->applyFromArray($summaryStyle);
    
    $sheet->setCellValue('A5', 'Total Ventas:');
    $sheet->setCellValue('B5', '$' . number_format($estadisticas['total_ventas'] ?? 0, 2));
    
    $sheet->setCellValue('A6', 'Cantidad de Ventas:');
    $sheet->setCellValue('B6', $estadisticas['cantidad_ventas'] ?? 0);
    
    $sheet->setCellValue('A7', 'Venta Promedio:');
    $sheet->setCellValue('B7', '$' . number_format($estadisticas['promedio_venta'] ?? 0, 2));
    
    // Encabezados de tabla
    $sheet->mergeCells('A8:E8');
    $sheet->setCellValue('A8', 'Detalle de Ventas');
    $sheet->getStyle('A8')->applyFromArray($summaryStyle);
    
    $sheet->setCellValue('A9', '#');
    $sheet->setCellValue('B9', 'Fecha');
    $sheet->setCellValue('C9', 'Cliente');
    $sheet->setCellValue('D9', 'Productos');
    $sheet->setCellValue('E9', 'Total');
    $sheet->getStyle('A9:E9')->applyFromArray($headerStyle);
    
    // Datos de ventas
    $row = 10;
    foreach ($ventas as $index => $venta) {
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, date('d/m/Y H:i', strtotime($venta['fecha_venta'])));
        $sheet->setCellValue('C' . $row, $venta['cliente_nombre'] ?? 'Consumidor final');
        $sheet->setCellValue('D' . $row, $venta['productos_vendidos']);
        $sheet->setCellValue('E' . $row, $venta['total']);
        
        // Formato de moneda
        $sheet->getStyle('E' . $row)
            ->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');
        
        $row++;
    }
    
    // Total general
    $sheet->setCellValue('D' . $row, 'Total General:');
    $sheet->setCellValue('E' . $row, $estadisticas['total_ventas'] ?? 0);
    $sheet->getStyle('D' . $row . ':E' . $row)->applyFromArray($summaryStyle);
    $sheet->getStyle('E' . $row)
        ->getNumberFormat()
        ->setFormatCode('"$"#,##0.00');
    
    // Autoajustar columnas
    foreach (range('A', 'E') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Proteger hoja (solo lectura)
    $sheet->getProtection()->setSheet(true);
    
    // Configurar página
    $sheet->getPageSetup()
        ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
    
    // Generar archivo Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="reporte_ventas_'.date('Ymd_His').'.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    ob_end_clean();
    $writer->save('php://output');
    exit;
}