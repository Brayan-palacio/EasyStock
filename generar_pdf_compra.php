<?php
// Iniciar sesión y verificar autenticación primero
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    exit(json_encode([
        'success' => false,
        'message' => 'Acceso no autorizado: No hay sesión activa',
        'data' => null
    ]));
}

// Cargar dependencias
require_once('config/conexion.php');
require_once('vendor/autoload.php');

use TCPDF as TCPDF;

// Validar y sanitizar entrada
$compra_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$compra_id) {
    http_response_code(400);
    exit(json_encode([
        'success' => false,
        'message' => 'ID de compra no válido',
        'data' => null
    ]));
}

// Obtener datos de la compra con consultas preparadas
try {
    // Consulta principal de la compra
    $sql_compra = "SELECT c.*, p.nombre as proveedor, u.nombre as usuario, a.nombre as almacen 
                  FROM compras c
                  JOIN proveedores p ON c.proveedor_id = p.id
                  JOIN usuarios u ON c.usuario_id = u.id
                  LEFT JOIN almacenes a ON c.almacen_id = a.id
                  WHERE c.id = ?";
    
    $stmt_compra = $conexion->prepare($sql_compra);
    $stmt_compra->bind_param("i", $compra_id);
    $stmt_compra->execute();
    $compra = $stmt_compra->get_result()->fetch_assoc();
    
    if (!$compra) {
        http_response_code(404);
        exit(json_encode([
            'success' => false,
            'message' => 'Compra no encontrada',
            'data' => null
        ]));
    }
    
    // Consulta del detalle con prepared statements
    $sql_detalle = "SELECT d.*, pr.descripcion as producto, pr.codigo_barras, pr.unidad_medida
                   FROM compras_detalle d
                   JOIN productos pr ON d.producto_id = pr.id
                   WHERE d.compra_id = ?";
    
    $stmt_detalle = $conexion->prepare($sql_detalle);
    $stmt_detalle->bind_param("i", $compra_id);
    $stmt_detalle->execute();
    $detalle = $stmt_detalle->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode([
        'success' => false,
        'message' => 'Error al obtener datos de la compra: ' . $e->getMessage(),
        'data' => null
    ]));
}

/**
 * Clase personalizada para el PDF con configuraciones comunes
 */
class EasyStockPDF extends TCPDF {
    // Cabecera del documento
    public function Header() {
        $logo = 'assets/img/logo_empresa.png';
        
        // Logo (ajustado para dejar más espacio arriba)
        if (file_exists($logo)) {
            $this->Image($logo, 15, 10, 30, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Título "ORDEN DE COMPRA" arriba del todo
        $this->SetY(10); // Posición más arriba
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 6, 'ORDEN DE COMPRA', 0, 1, 'C');
        
        // Información de la empresa debajo del título
        $this->SetY(20); // Posición después del título
        $this->SetFont('helvetica', '', 10);
        
        // Texto de la empresa en líneas separadas con mejor formato
        $empresa_info = [
            'EasyStock',
            'Sistema de Gestión de Inventarios',
            'Tel: (123) 456-7890',
            'Email: info@easystock.com'
        ];
        
        foreach ($empresa_info as $line) {
            $this->Cell(0, 5, $line, 0, 1, 'C');
        }
        
        // Línea divisoria más abajo
        $this->Line(15, 40, $this->getPageWidth()-15, 40); // Línea más baja
    }
    
    
    // Pie de página
    public function Footer() {
        $this->SetY(-20);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 5, 'Documento generado por EasyStock el '.date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->Cell(0, 5, 'Página '.$this->getAliasNumPage().' de '.$this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Crear nuevo documento PDF personalizado
$pdf = new EasyStockPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configuración del documento
$pdf->SetCreator('EasyStock');
$pdf->SetAuthor('Sistema EasyStock');
$pdf->SetTitle('Compra #' . $compra['num_factura']);
$pdf->SetSubject('Documento de Compra');

// Configurar márgenes
$pdf->SetMargins(15, 40, 15); // Margen superior mayor para la cabecera
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 25);

// Agregar página
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Información de la compra
$pdf->SetY(40);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'Datos de la Factura', 0, 1);
$pdf->SetFont('helvetica', '', 10);

// Función para agregar filas de información
function addInfoRow($pdf, $label, $value, $labelWidth = 40) {
    $pdf->Cell($labelWidth, 6, $label, 0, 0);
    $pdf->Cell(0, 6, $value, 0, 1);
}

addInfoRow($pdf, 'Número de Factura:', $compra['num_factura']);
addInfoRow($pdf, 'Fecha Factura:', date('d/m/Y', strtotime($compra['fecha'])));
addInfoRow($pdf, 'Proveedor:', $compra['proveedor']);
addInfoRow($pdf, 'Registrado por:', $compra['usuario']);
addInfoRow($pdf, 'Almacén:', $compra['almacen'] ?? 'N/A');
addInfoRow($pdf, 'Estado:', strtoupper($compra['estado']));

// Espacio antes de la tabla
$pdf->Ln(10);

// Encabezado de la tabla
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(240, 240, 240);

// Definir anchos de columnas
$columnWidths = [
    10,  // #
    80,  // Producto
    20,  // Cantidad
    20,  // Unidad
    25,  // P. Unitario
    25   // Subtotal
];

$headers = ['#', 'Producto', 'Cantidad', 'Unidad', 'P. Unitario', 'Subtotal'];

// Dibujar encabezados
foreach ($headers as $i => $header) {
    $pdf->Cell($columnWidths[$i], 7, $header, 1, 0, 'C', 1);
}
$pdf->Ln();

// Contenido de la tabla
$pdf->SetFont('helvetica', '', 9);
$fill = false;
$rowHeight = 6;

foreach ($detalle as $i => $item) {
    $subtotal = $item['cantidad'] * $item['precio_unitario'];
    
    // Alternar relleno
    $fill = !$fill;
    
    // Número de fila
    $pdf->Cell($columnWidths[0], $rowHeight, $i+1, 'LR', 0, 'C', $fill);
    
    // Producto (con multiCell si es muy largo)
    $pdf->Cell($columnWidths[1], $rowHeight, $item['producto'], 'LR', 0, 'L', $fill);
    
    // Resto de celdas
    $pdf->Cell($columnWidths[2], $rowHeight, number_format($item['cantidad'], 2), 'LR', 0, 'R', $fill);
    $pdf->Cell($columnWidths[3], $rowHeight, $item['unidad_medida'], 'LR', 0, 'C', $fill);
    $pdf->Cell($columnWidths[4], $rowHeight, '$'.number_format($item['precio_unitario'], 2), 'LR', 0, 'R', $fill);
    $pdf->Cell($columnWidths[5], $rowHeight, '$'.number_format($subtotal, 2), 'LR', 0, 'R', $fill);
    
    $pdf->Ln();
}

// Cierre de la tabla
$pdf->Cell(array_sum($columnWidths), 0, '', 'T');
$pdf->Ln(10);

// Totales
$pdf->SetFont('helvetica', 'B', 10);
$labelWidth = 150;
$valueWidth = 30;

$pdf->Cell($labelWidth, 6, 'Subtotal:', 0, 0, 'R');
$pdf->Cell($valueWidth, 6, '$'.number_format($compra['subtotal'], 2), 0, 1, 'R');

$iva_percent = $compra['subtotal'] > 0 ? ($compra['iva']/$compra['subtotal']*100) : 0;
$pdf->Cell($labelWidth, 6, 'IVA ('.number_format($iva_percent, 2).'%):', 0, 0, 'R');
$pdf->Cell($valueWidth, 6, '$'.number_format($compra['iva'], 2), 0, 1, 'R');

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell($labelWidth, 8, 'TOTAL:', 0, 0, 'R');
$pdf->Cell($valueWidth, 8, '$'.number_format($compra['total'], 2), 0, 1, 'R');

// Observaciones
$pdf->SetFont('helvetica', '', 9);
$pdf->Ln(5);
$observaciones = !empty($compra['observaciones']) ? $compra['observaciones'] : 'Ninguna';
$pdf->MultiCell(0, 6, 'Observaciones: ' . $observaciones, 0, 'L');

// Firmas
$pdf->Ln(15);
$signatureWidth = 90;
$pdf->Cell($signatureWidth, 6, '__________________________', 0, 0, 'C');
$pdf->Cell($signatureWidth, 6, '__________________________', 0, 1, 'C');
$pdf->Cell($signatureWidth, 6, 'Responsable de Compras', 0, 0, 'C');
$pdf->Cell($signatureWidth, 6, 'Recibido por', 0, 1, 'C');

// Generar PDF
$pdf->Output('compra_'.$compra['num_factura'].'.pdf', 'I');