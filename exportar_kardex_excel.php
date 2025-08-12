<?php
require 'config/conexion.php';
require 'vendor/autoload.php'; // Requerir PhpSpreadsheet

// Verificar sesión y permisos
session_start();
if (!isset($_SESSION['id_usuario'])) {
    die('Acceso no autorizado');
}

// Obtener parámetros
$producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

if (!$producto_id) {
    die('ID de producto no especificado');
}

// Función para obtener movimientos (similar a la del kardex.php)
function obtenerMovimientos($conexion, $producto_id, $fecha_inicio = '', $fecha_fin = '') {
    $sql = "SELECT m.*, 
                   p.descripcion as producto_descripcion, 
                   p.codigo_barras,
                   u.nombre as usuario_nombre,
                   DATE_FORMAT(m.fecha, '%Y-%m-%d %H:%i:%s') as fecha_formateada,
                   IFNULL(prov.nombre, 'N/A') as proveedor,
                   IFNULL(cli.nombre, 'N/A') as cliente
            FROM movimientos m
            JOIN productos p ON m.producto_id = p.id
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            LEFT JOIN proveedores prov ON m.proveedor_id = prov.id
            LEFT JOIN clientes cli ON m.cliente_id = cli.id
            WHERE m.producto_id = ?";
    
    $params = [$producto_id];
    
    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $sql .= " AND DATE(m.fecha) BETWEEN ? AND ?";
        $params[] = $fecha_inicio;
        $params[] = $fecha_fin;
    } elseif (!empty($fecha_inicio)) {
        $sql .= " AND DATE(m.fecha) >= ?";
        $params[] = $fecha_inicio;
    } elseif (!empty($fecha_fin)) {
        $sql .= " AND DATE(m.fecha) <= ?";
        $params[] = $fecha_fin;
    }
    
    $sql .= " ORDER BY m.fecha DESC";
    
    $stmt = $conexion->prepare($sql);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener información del producto
function obtenerProducto($conexion, $producto_id) {
    $sql = "SELECT p.*, c.nombre as categoria_nombre 
            FROM productos p
            JOIN categorias c ON p.categoria_id = c.id
            WHERE p.id = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// Obtener datos
$producto = obtenerProducto($conexion, $producto_id);
$movimientos = obtenerMovimientos($conexion, $producto_id, $fecha_inicio, $fecha_fin);

// Crear un nuevo objeto Spreadsheet
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Establecer propiedades del documento
$spreadsheet->getProperties()
    ->setCreator("EasyStock")
    ->setTitle("Kardex de Producto")
    ->setSubject("Reporte de Kardex");

// Encabezados
$sheet->setCellValue('A1', 'Kardex de Producto');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

// Información del producto
$sheet->setCellValue('A3', 'Producto:');
$sheet->setCellValue('B3', $producto['descripcion']);
$sheet->setCellValue('A4', 'Código de Barras:');
$sheet->setCellValue('B4', $producto['codigo_barras']);
$sheet->setCellValue('A5', 'Categoría:');
$sheet->setCellValue('B5', $producto['categoria_nombre']);
$sheet->setCellValue('A6', 'Stock Actual:');
$sheet->setCellValue('B6', $producto['cantidad']);

// Fechas de filtro si existen
if (!empty($fecha_inicio) || !empty($fecha_fin)) {
    $sheet->setCellValue('D3', 'Periodo:');
    $sheet->setCellValue('E3', (!empty($fecha_inicio) ? $fecha_inicio : 'Inicio') . ' - ' . (!empty($fecha_fin) ? $fecha_fin : 'Hoy'));
}

// Encabezados de la tabla
$sheet->setCellValue('A8', 'Fecha');
$sheet->setCellValue('B8', 'Tipo');
$sheet->setCellValue('C8', 'Detalle');
$sheet->setCellValue('D8', 'Cantidad');
$sheet->setCellValue('E8', 'Saldo');
$sheet->setCellValue('F8', 'Responsable');

// Estilos para encabezados
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A2F']]
];
$sheet->getStyle('A8:F8')->applyFromArray($headerStyle);

// Llenar datos
$row = 9;
$saldo = $producto['cantidad'];

foreach ($movimientos as $mov) {
    $tipo_text = '';
    switch($mov['tipo']) {
        case 'entrada': $tipo_text = 'Entrada'; break;
        case 'salida': $tipo_text = 'Salida'; break;
        case 'ajuste_positivo': $tipo_text = 'Ajuste +'; break;
        case 'ajuste_negativo': $tipo_text = 'Ajuste -'; break;
        default: $tipo_text = ucfirst($mov['tipo']);
    }
    
    // Calcular saldo acumulado
    $saldo -= ($mov['tipo'] == 'entrada' ? $mov['cantidad'] : -$mov['cantidad']);
    
    $sheet->setCellValue('A'.$row, date('d/m/Y H:i', strtotime($mov['fecha_formateada'])));
    $sheet->setCellValue('B'.$row, $tipo_text);
    
    $detalle = $mov['motivo'];
    if ($mov['proveedor'] != 'N/A') {
        $detalle .= "\nProv: " . $mov['proveedor'];
    } elseif ($mov['cliente'] != 'N/A') {
        $detalle .= "\nCli: " . $mov['cliente'];
    }
    $sheet->setCellValue('C'.$row, $detalle);
    
    $sheet->setCellValue('D'.$row, (in_array($mov['tipo'], ['entrada', 'ajuste_positivo']) ? '+' : '-') . $mov['cantidad']);
    $sheet->setCellValue('E'.$row, $saldo);
    $sheet->setCellValue('F'.$row, $mov['usuario_nombre'] ?? 'Sistema');
    
    // Estilo para cantidades
    $sheet->getStyle('D'.$row)
        ->getFont()
        ->getColor()
        ->setRGB(in_array($mov['tipo'], ['entrada', 'ajuste_positivo']) ? '28A745' : 'DC3545');
    
    $row++;
}

// Ajustar anchos de columnas
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(12);
$sheet->getColumnDimension('C')->setWidth(40);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(20);

// Autoajustar altura para filas con múltiples líneas
$sheet->getStyle('C9:C'.($row-1))->getAlignment()->setWrapText(true);

// Crear escritor y enviar al navegador
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="kardex_'.mb_substr($producto['descripcion'], 0, 20).'_'.date('Ymd').'.xlsx"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
?>