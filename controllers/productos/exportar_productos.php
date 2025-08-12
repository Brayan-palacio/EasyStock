<?php
// ==============================================
// CONFIGURACIÓN INICIAL Y SEGURIDAD
// ==============================================
ob_start();
require_once '../../config/conexion.php';
require_once '../../includes/functions.php';

// Verificación de autenticación
session_start();
if (!isset($_SESSION['id_usuario'])) {
    die('🔒 Acceso no autorizado');
}

// ==============================================
// CAPTURA Y VALIDACIÓN DE PARÁMETROS
// ==============================================
$formato = isset($_GET['formato']) ? strtolower(trim($_GET['formato'])) : 'excel';
$fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
$fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;

// Validación de formato
$formatosPermitidos = ['excel', 'csv', 'pdf'];
if (!in_array($formato, $formatosPermitidos)) {
    die("❌ Formato no soportado. Formatos válidos: " . implode(', ', $formatosPermitidos));
}

// Validación de fechas
if (($fechaInicio && !$fechaFin) || (!$fechaInicio && $fechaFin)) {
    die("⚠️ Debes especificar ambas fechas o ninguna");
}

// ==============================================
// CONSULTA A LA BASE DE DATOS
// ==============================================
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
    die("📭 No hay productos para exportar con los criterios seleccionados");
}

// ==============================================
// GENERACIÓN DEL ARCHIVO SEGÚN FORMATO
// ==============================================
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

// ==============================================
// FUNCIÓN PARA EXPORTAR A EXCEL (XLSX)
// ==============================================
function exportarExcel($datos) {
    require_once '../../vendor/autoload.php';
    
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // ------------------------------
        // ESTILOS DEL DOCUMENTO
        // ------------------------------
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1A3A2F'] // Verde oscuro corporativo
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFFFFF']
                ]
            ]
        ];
        
        $bodyStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD']
                ]
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ]
        ];
        
        $numberStyle = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
            ]
        ];
        
        // ------------------------------
        // CONFIGURACIÓN DE HOJA
        // ------------------------------
        // Título del reporte
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'REPORTE DE PRODUCTOS - EASYSTOCK');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '1A3A2F']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            ]
        ]);
        
        // Subtítulo con fecha
        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', 'Generado el ' . date('d/m/Y H:i'));
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 9
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            ]
        ]);
        
        // Espacio entre título y tabla
        $sheet->setCellValue('A3', '');
        
        // Encabezados de tabla (comienzan en fila 4)
        $sheet->setCellValue('A4', 'ID');
        $sheet->setCellValue('B4', 'CÓDIGO');
        $sheet->setCellValue('C4', 'DESCRIPCIÓN');
        $sheet->setCellValue('D4', 'CATEGORÍA');
        $sheet->setCellValue('E4', 'STOCK');
        $sheet->setCellValue('F4', 'PRECIO COMPRA');
        $sheet->setCellValue('G4', 'PRECIO VENTA');
        
        // Aplicar estilos a encabezados
        $sheet->getStyle('A4:G4')->applyFromArray($headerStyle);
        
        // ------------------------------
        // FORMATO DE COLUMNAS
        // ------------------------------
        $sheet->getColumnDimension('A')->setWidth(8);  // ID
        $sheet->getColumnDimension('B')->setWidth(18); // Código
        $sheet->getColumnDimension('C')->setWidth(40); // Descripción
        $sheet->getColumnDimension('D')->setWidth(20); // Categoría
        $sheet->getColumnDimension('E')->setWidth(12); // Stock
        $sheet->getColumnDimension('F')->setWidth(15); // P. Compra
        $sheet->getColumnDimension('G')->setWidth(15); // P. Venta
        
        // Autoajustar altura fila encabezados
        $sheet->getRowDimension(4)->setRowHeight(25);
        
        // ------------------------------
        // LLENADO DE DATOS
        // ------------------------------
        $fila = 5;
        foreach ($datos as $producto) {
            $sheet->setCellValue('A'.$fila, $producto['id']);
            $sheet->setCellValue('B'.$fila, $producto['codigo_barras']);
            $sheet->setCellValue('C'.$fila, $producto['descripcion']);
            $sheet->setCellValue('D'.$fila, $producto['categoria']);
            $sheet->setCellValue('E'.$fila, $producto['cantidad']);
            $sheet->setCellValue('F'.$fila, $producto['precio_compra']);
            $sheet->setCellValue('G'.$fila, $producto['precio_venta']);
            
            // Estilo condicional para stock
            $stockCell = 'E'.$fila;
            if ($producto['cantidad'] <= $producto['stock_minimo']) {
                $sheet->getStyle($stockCell)
                    ->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
                $sheet->getStyle($stockCell)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFEBEE');
            } elseif ($producto['cantidad'] <= ($producto['stock_minimo'] + 10)) {
                $sheet->getStyle($stockCell)
                    ->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKYELLOW);
                $sheet->getStyle($stockCell)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF3E0');
            }
            
            $fila++;
        }
        
        // Aplicar estilo general al cuerpo
        $sheet->getStyle('A5:G'.($fila-1))->applyFromArray($bodyStyle);
        
        // Formato de números
        $sheet->getStyle('E5:E'.$fila)->applyFromArray($numberStyle); // Stock
        $sheet->getStyle('F5:G'.$fila)->getNumberFormat()->setFormatCode('"$"#,##0.00'); // Precios
        
        // Totales y resumen
        $sheet->setCellValue('D'.$fila, 'TOTAL PRODUCTOS:');
        $sheet->setCellValue('E'.$fila, count($datos));
        $sheet->getStyle('D'.$fila.':E'.$fila)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E9ECEF']
            ]
        ]);
        
        // Congelar paneles (encabezados visibles al desplazar)
        $sheet->freezePane('A5');
        
        // Proteger celdas (opcional)
        $sheet->getProtection()->setSheet(true);
        $sheet->getStyle('A5:G'.($fila-1))->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
        
        // ------------------------------
        // GENERACIÓN DEL ARCHIVO
        // ------------------------------
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Productos_EasyStock_'.date('Y-m-d').'.xlsx"');
        header('Cache-Control: max-age=0, must-revalidate');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_end_clean();
        $writer->save('php://output');
        exit;
        
    } catch (\Exception $e) {
        die("❌ Error al generar Excel: " . $e->getMessage());
    }
}

// ==============================================
// FUNCIÓN PARA EXPORTAR A CSV
// ==============================================
function exportarCSV($datos) {
    try {
        // Configurar headers
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment;filename="Productos_EasyStock_'.date('Y-m-d').'.csv"');
        header('Cache-Control: max-age=0');
        
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
        
        // Agregar línea de resumen
        fputcsv($output, ['', '', '', '', '', '', '', ''], ';');
        fputcsv($output, [
            '', '', 'TOTAL PRODUCTOS:', count($datos),
            'Mín. Stock:', min(array_column($datos, 'cantidad')),
            'Máx. Stock:', max(array_column($datos, 'cantidad'))
        ], ';');
        
        ob_end_clean();
        fclose($output);
        exit;
        
    } catch (\Exception $e) {
        die("❌ Error al generar CSV: " . $e->getMessage());
    }
}

// ==============================================
// FUNCIÓN PARA EXPORTAR A PDF
// ==============================================
function exportarPDF($productos) {
    // Limpieza exhaustiva de buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Incluir TCPDF
    require_once '../../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    // Clase personalizada con header/footer
    class MYPDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 10, 'Reporte de Productos - EasyStock', 0, 1, 'C');
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
    
    // Configuración del documento
    $pdf->SetCreator('EasyStock');
    $pdf->SetAuthor('EasyStock');
    $pdf->SetTitle('Reporte de Productos');
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    // Estilos CSS para PDF
    $html = '
    <style>
        h2 {
            color: #1A3A2F;
            font-size: 16pt;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #1A3A2F;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 9pt;
        }
        
        th {
            background-color: #1A3A2F;
            color: #FFFFFF;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1A3A2F;
        }
        
        td {
            padding: 7px;
            border: 1px solid #E0E0E0;
        }
        
        tr:nth-child(even) {
            background-color: #F8F9FA;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .stock-bajo {
            color: #DC3545;
            font-weight: bold;
        }
        
        .stock-medio {
            color: #FFC107;
        }
        
        .stock-ok {
            color: #28A745;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #E9ECEF !important;
        }
    </style>

    <h2>REPORTE DE PRODUCTOS</h2>

    <table>
        <thead>
            <tr>
                <th width="8%">ID</th>
                <th width="20%">Código</th>
                <th width="32%">Descripción</th>
                <th width="20%">Categoría</th>
                <th width="10%" class="text-center">Stock</th>
                <th width="10%" class="text-right">P. Venta</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($productos as $producto) {
        $stockClass = '';
        if ($producto['cantidad'] <= $producto['stock_minimo']) {
            $stockClass = 'stock-bajo';
        } elseif ($producto['cantidad'] <= ($producto['stock_minimo'] + 10)) {
            $stockClass = 'stock-medio';
        } else {
            $stockClass = 'stock-ok';
        }
        
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

    // Pie de tabla con resumen
    $html .= '
            <tr class="total-row">
                <td colspan="4">TOTAL PRODUCTOS</td>
                <td class="text-center">'.count($productos).'</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin-top: 20px; font-size: 8pt; color: #666;">
        Generado el '.date('d/m/Y H:i').' por '.htmlspecialchars($_SESSION['nombre_usuario'] ?? 'EasyStock').'
    </div>';

    // Escribir contenido
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Generar PDF
    $pdf->Output('Reporte_Productos_'.date('Y-m-d').'.pdf', 'D');
    exit;
}