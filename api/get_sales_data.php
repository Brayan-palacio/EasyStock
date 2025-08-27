<?php
session_start();
require_once '../config/conexion.php';

// Verificar autenticación
if (!isset($_SESSION['id_usuario']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    exit(json_encode(['error' => 'No autorizado']));
}

// Validar y sanitizar parámetros
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
if ($year < 2020 || $year > date('Y') + 1) {
    $year = date('Y');
}

// Consulta parametrizada
$query = $conexion->prepare("
    SELECT DATE_FORMAT(fecha_venta, '%Y-%m') AS mes, 
           SUM(total) AS ventas_totales 
    FROM ventas 
    WHERE YEAR(fecha_venta) = ?
    GROUP BY mes 
    ORDER BY mes
");
$query->bind_param('i', $year);
$query->execute();
$result = $query->get_result();

$meses = [];
$ventas = [];

while ($fila = $result->fetch_assoc()) {
    $meses[] = date('M Y', strtotime($fila['mes']));
    $ventas[] = floatval($fila['ventas_totales']);
}

header('Content-Type: application/json');
echo json_encode(['labels' => $meses, 'ventas' => $ventas]);