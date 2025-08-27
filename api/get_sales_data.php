<?php
include '../config/conexion.php';

header('Content-Type: application/json');

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

function getSalesData($conexion, $year) {
    $query = $conexion->prepare("
        SELECT DATE_FORMAT(fecha_venta, '%Y-%m') AS mes, 
               SUM(total) AS ventas_totales 
        FROM ventas 
        WHERE YEAR(fecha_venta) = ?
        GROUP BY mes 
        ORDER BY mes
    ");
    $query->bind_param('s', $year);
    $query->execute();
    $result = $query->get_result();
    
    $meses = [];
    $ventas = [];
    
    while ($fila = $result->fetch_assoc()) {
        $meses[] = date('M Y', strtotime($fila['mes']));
        $ventas[] = $fila['ventas_totales'];
    }
    
    return [
        'labels' => $meses,
        'ventas' => $ventas
    ];
}

echo json_encode(getSalesData($conexion, $year));
?>