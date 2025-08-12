<?php
require 'config/conexion.php';
require 'vendor/autoload.php'; // Requerir TCPDF

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

// Calcular saldo inicial si hay filtros de fecha
$saldo_inicial = 0;
if (!empty($movimientos) && (!empty($fecha_inicio) || !empty($fecha_fin))) {
    $sql_saldo_inicial = "SELECT 
                    SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE 0 END) as total_entradas,
                    SUM(CASE WHEN tipo = 'salida' THEN cantidad ELSE 0 END) as total_salidas
                  FROM movimientos
                  WHERE producto_id = ?";
    
    $params = [$producto_id];
    
    if (!empty($fecha_inicio)) {
        $sql_saldo_inicial .= " AND DATE(fecha) < ?";
        $params[] = $fecha_inicio;
    }
    
    $stmt = $conexion->prepare($sql_saldo_inicial);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $totales_iniciales = $result->fetch_assoc();
    $saldo_inicial = ($totales_iniciales['total_entradas'] ?? 0) - ($totales_iniciales['total_salidas'] ?? 0);
}

// Crear PDF
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Información del documento
$pdf->SetCreator('EasyStock');
$pdf->SetAuthor('EasyStock');
$pdf->SetTitle('Kardex de Producto');
$pdf->SetSubject('Reporte de Kardex');

// Margenes
$pdf->SetMargins(10, 15, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Saltos de página automáticos
$pdf->SetAutoPageBreak(TRUE, 15);

// Agregar página
$pdf->AddPage();

// Logo y título
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'KARDEX DE PRODUCTO', 0, 1, 'C');
$pdf->Ln(5);

// Información del producto
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(40, 7, 'Producto:', 0, 0);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 7, $producto['descripcion'], 0, 1);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(40, 7, 'Código Barras:', 0, 0);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 7, $producto['codigo_barras'], 0, 1);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(40, 7, 'Categoría:', 0, 0);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 7, $producto['categoria_nombre'], 0, 1);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(40, 7, 'Stock Actual:', 0, 0);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 7, $producto['cantidad'], 0, 1);

// Periodo si hay filtros
if (!empty($fecha_inicio) || !empty($fecha_fin)) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(40, 7, 'Periodo:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $periodo = (!empty($fecha_inicio) ? date('d/m/Y', strtotime($fecha_inicio)) : 'Inicio') . ' - ' . 
               (!empty($fecha_fin) ? date('d/m/Y', strtotime($fecha_fin)) : 'Hoy');
    $pdf->Cell(0, 7, $periodo, 0, 1);
    
    // Saldos
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(40, 7, 'Saldo Inicial:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(40, 7, $saldo_inicial, 0, 0);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(40, 7, 'Saldo Final:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 7, $producto['cantidad'], 0, 1);
}

$pdf->Ln(10);

// Encabezados de la tabla
$pdf->SetFont('helvetica', 'B', 10);
$header = array('Fecha', 'Tipo', 'Detalle', 'Cantidad', 'Saldo', 'Responsable');
$widths = array(25, 15, 70, 20, 20, 40);

// Cabecera de la tabla con estilo
$pdf->SetFillColor(26, 58, 47); // Verde oscuro
$pdf->SetTextColor(255);
$pdf->SetDrawColor(26, 58, 47);
$pdf->SetLineWidth(0.3);

for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($widths[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Restaurar colores
$pdf->SetFillColor(255);
$pdf->SetTextColor(0);
$pdf->SetFont('helvetica', '', 9);

// Calcular saldo acumulado
$saldo = $producto['cantidad'];

// Llenar tabla
foreach ($movimientos as $mov) {
    $tipo_text = '';
    $tipo_color = '';
    switch($mov['tipo']) {
        case 'entrada': 
            $tipo_text = 'ENTRADA';
            $tipo_color = '28A745'; // Verde
            break;
        case 'salida': 
            $tipo_text = 'SALIDA';
            $tipo_color = 'DC3545'; // Rojo
            break;
        case 'ajuste_positivo': 
            $tipo_text = 'AJUSTE +';
            $tipo_color = '17A2B8'; // Azul
            break;
        case 'ajuste_negativo': 
            $tipo_text = 'AJUSTE -';
            $tipo_color = '6C757D'; // Gris
            break;
        default: 
            $tipo_text = strtoupper($mov['tipo']);
            $tipo_color = '000000'; // Negro
    }
    
    // Calcular saldo acumulado
    $saldo -= ($mov['tipo'] == 'entrada' ? $mov['cantidad'] : -$mov['cantidad']);
    
    // Fecha
    $pdf->Cell($widths[0], 6, date('d/m/Y H:i', strtotime($mov['fecha_formateada'])), 'LR', 0, 'L');
    
    // Tipo con color
    $pdf->SetTextColor(hexdec(substr($tipo_color, 0, 2)), hexdec(substr($tipo_color, 2, 2)), hexdec(substr($tipo_color, 4, 2)));
    $pdf->Cell($widths[1], 6, $tipo_text, 'LR', 0, 'C');
    $pdf->SetTextColor(0);
    
    // Detalle
    $detalle = $mov['motivo'];
    if ($mov['proveedor'] != 'N/A') {
        $detalle .= "\nProv: " . $mov['proveedor'];
    } elseif ($mov['cliente'] != 'N/A') {
        $detalle .= "\nCli: " . $mov['cliente'];
    }
    $pdf->MultiCell($widths[2], 6, $detalle, 'LR', 'L', false, 0);
    
    // Cantidad con color
    $pdf->SetTextColor(hexdec(substr($tipo_color, 0, 2)), hexdec(substr($tipo_color, 2, 2)), hexdec(substr($tipo_color, 4, 2)));
    $pdf->Cell($widths[3], 6, (in_array($mov['tipo'], ['entrada', 'ajuste_positivo']) ? '+' : '-') . $mov['cantidad'], 'LR', 0, 'R');
    $pdf->SetTextColor(0);
    
    // Saldo
    $pdf->Cell($widths[4], 6, $saldo, 'LR', 0, 'R');
    
    // Responsable
    $pdf->Cell($widths[5], 6, $mov['usuario_nombre'] ?? 'Sistema', 'LR', 0, 'L');
    
    $pdf->Ln();
    
    // Dibujar línea inferior si es el último elemento
    if ($mov === end($movimientos)) {
        $pdf->Cell(array_sum($widths), 0, '', 'T');
    }
}

// Si no hay movimientos
if (empty($movimientos)) {
    $pdf->Cell(array_sum($widths), 6, 'No hay movimientos registrados', 'LR', 0, 'C');
    $pdf->Ln();
    $pdf->Cell(array_sum($widths), 0, '', 'T');
}

// Resumen al final
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 7, 'RESUMEN DEL PERIODO', 0, 1, 'C');
$pdf->Ln(5);

// Calcular totales
$total_entradas = 0;
$total_salidas = 0;

foreach ($movimientos as $mov) {
    if (in_array($mov['tipo'], ['entrada', 'ajuste_positivo'])) {
        $total_entradas += $mov['cantidad'];
    } else {
        $total_salidas += $mov['cantidad'];
    }
}

// Crear tabla de resumen
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(90, 7, 'Total Entradas:', 1, 0, 'R');
$pdf->Cell(30, 7, '+' . $total_entradas, 1, 0, 'R');
$pdf->SetTextColor(28, 167, 69); // Verde
$pdf->Cell(30, 7, '', 1, 0, 'R');
$pdf->SetTextColor(0);
$pdf->Ln();

$pdf->Cell(90, 7, 'Total Salidas:', 1, 0, 'R');
$pdf->Cell(30, 7, '-' . $total_salidas, 1, 0, 'R');
$pdf->SetTextColor(220, 53, 69); // Rojo
$pdf->Cell(30, 7, '', 1, 0, 'R');
$pdf->SetTextColor(0);
$pdf->Ln();

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 7, 'Saldo Final:', 1, 0, 'R');
$pdf->Cell(30, 7, $producto['cantidad'], 1, 0, 'R');
$pdf->SetTextColor(0, 123, 255); // Azul
$pdf->Cell(30, 7, '', 1, 0, 'R');
$pdf->SetTextColor(0);
$pdf->Ln();

// Pie de página
$pdf->SetY(-15);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Generado el ' . date('d/m/Y H:i'), 0, 0, 'L');
$pdf->Cell(0, 10, 'Página ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'R');

// Salida del PDF
$pdf->Output('kardex_' . mb_substr($producto['descripcion'], 0, 20) . '_' . date('Ymd') . '.pdf', 'D');
exit;
?>