<?php
require_once '../config/conexion.php';

header('Content-Type: application/json');

$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$codigoBarras = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';

$sql = "SELECT id, descripcion, codigo_barras, cantidad 
        FROM productos 
        WHERE ";

if (!empty($codigoBarras)) {
    // Búsqueda exacta por código de barras
    $sql .= "codigo_barras = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $codigoBarras);
} else {
    // Búsqueda por texto (descripción o código parcial)
    $sql .= "(descripcion LIKE ? OR codigo_barras LIKE ?) ORDER BY descripcion LIMIT 10";
    $stmt = $conexion->prepare($sql);
    $term = "%{$searchTerm}%";
    $stmt->bind_param("ss", $term, $term);
}

$stmt->execute();
$result = $stmt->get_result();

echo json_encode($result->fetch_all(MYSQLI_ASSOC));