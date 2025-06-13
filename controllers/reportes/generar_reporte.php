<?php
require_once '../../config/conexion.php';

// Verificar permisos (agrega tu lógica de verificación aquí)

// Obtener parámetros del formulario
$tipo_reporte = $_POST['tipo_reporte'] ?? 'mensual';
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
$formato = $_POST['formato'] ?? 'pdf';

// Validar fechas
if ($fecha_fin < $fecha_inicio) {
    $fecha_fin = $fecha_inicio;
}

// Consulta principal de ventas (similar a la de tu reporte)
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
}

function generarPDF($ventas, $estadisticas, $fecha_inicio, $fecha_fin, $tipo_reporte) {
    require_once '../../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    // Crear nuevo documento PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurar metadatos del documento
    $pdf->SetCreator('EasyStock');
    $pdf->SetAuthor('Sistema EasyStock');
    $pdf->SetTitle('Reporte de Ventas');
    $pdf->SetSubject('Reporte generado automáticamente');
    
    // Establecer márgenes
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Agregar una página
    $pdf->AddPage();
    
    // Estilos CSS básicos
    $style = "
        <style>
            h1 { color: #2c3e50; font-size: 18px; }
            .titulo-reporte { background-color: #3498db; color: white; padding: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #2c3e50; color: white; font-weight: bold; padding: 8px; }
            td { border: 1px solid #ddd; padding: 8px; }
            .resumen { background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; }
        </style>
    ";
    
    // Contenido HTML del PDF
    $html = $style . '
    <div class="titulo-reporte">
        <h1>Reporte de Ventas - EasyStock</h1>
        <p><strong>Periodo:</strong> ' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)) . '</p>
        <p><strong>Tipo:</strong> ' . ucfirst($tipo_reporte) . '</p>
    </div>
    
    <div class="resumen">
        <h3>Resumen Estadístico</h3>
        <p><strong>Total Ventas:</strong> $' . number_format($estadisticas['total_ventas'] ?? 0, 2) . '</p>
        <p><strong>Cantidad de Ventas:</strong> ' . ($estadisticas['cantidad_ventas'] ?? 0) . '</p>
        <p><strong>Venta Promedio:</strong> $' . number_format($estadisticas['promedio_venta'] ?? 0, 2) . '</p>
    </div>
    
    <h3>Detalle de Ventas</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Productos</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>';
    
    // Agregar filas de ventas
    foreach ($ventas as $index => $venta) {
        $html .= '
            <tr>
                <td>' . ($index + 1) . '</td>
                <td>' . date('d/m/Y H:i', strtotime($venta['fecha_venta'])) . '</td>
                <td>' . htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor final') . '</td>
                <td>' . str_replace('; ', '<br>', htmlspecialchars($venta['productos_vendidos'])) . '</td>
                <td>$' . number_format($venta['total'], 2) . '</td>
            </tr>';
    }
    
    $html .= '
        </tbody>
    </table>';
    
    // Escribir el HTML en el PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Generar y descargar el PDF
    $pdf->Output('reporte_ventas_' . date('YmdHis') . '.pdf', 'D');
    exit;
}

function generarExcel($ventas, $estadisticas, $fecha_inicio, $fecha_fin, $tipo_reporte) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment;filename="reporte_ventas_'.date('YmdHis').'.xls"');
    header('Cache-Control: max-age=0');
    
    // Añadir BOM (Byte Order Mark) para forzar UTF-8 en Excel
    echo "\xEF\xBB\xBF";
    
    echo '<table border="1">
        <tr>
            <th colspan="5" style="background-color: #3498db; color: white; font-size: 16px;">Reporte de Ventas - EasyStock</th>
        </tr>
        <tr>
            <th colspan="5">Periodo: '.date('d/m/Y', strtotime($fecha_inicio)).' - '.date('d/m/Y', strtotime($fecha_fin)).'</th>
        </tr>
        <tr>
            <th colspan="5">Tipo: '.ucfirst($tipo_reporte).'</th>
        </tr>
        <tr>
            <th colspan="5" style="background-color: #f8f9fa;">Resumen Estadístico</th>
        </tr>
        <tr>
            <td colspan="2"><strong>Total Ventas:</strong></td>
            <td colspan="3">$'.number_format($estadisticas['total_ventas'] ?? 0, 2).'</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Cantidad de Ventas:</strong></td>
            <td colspan="3">'.($estadisticas['cantidad_ventas'] ?? 0).'</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Venta Promedio:</strong></td>
            <td colspan="3">$'.number_format($estadisticas['promedio_venta'] ?? 0, 2).'</td>
        </tr>
        <tr>
            <th colspan="5" style="background-color: #f8f9fa;">Detalle de Ventas</th>
        </tr>
        <tr style="background-color: #2c3e50; color: white;">
            <th>#</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Productos</th>
            <th>Total</th>
        </tr>';
    
    foreach ($ventas as $index => $venta) {
        echo '<tr>
            <td>'.($index + 1).'</td>
            <td>'.date('d/m/Y H:i', strtotime($venta['fecha_venta'])).'</td>
            <td>'.htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor final').'</td>
            <td>'.str_replace('; ', "\n", htmlspecialchars($venta['productos_vendidos'])).'</td>
            <td>$'.number_format($venta['total'], 2).'</td>
        </tr>';
    }
    
    echo '</table>';
    exit;
}