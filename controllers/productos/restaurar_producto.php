<?php
include '../../config/conexion.php';
header('Content-Type: application/json');

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$id = (int)$_GET['id'];

$stmt = $conexion->prepare("UPDATE productos SET activo = 1 WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No se pudo restaurar']);
}
?>
