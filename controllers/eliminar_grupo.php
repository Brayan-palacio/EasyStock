<?php
require '../config/conexion.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = (int)$_GET['id'];

// Verificar si hay usuarios asociados
$check = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE grupo_id = ?");
$check->bind_param('i', $id);
$check->execute();
$tieneUsuarios = $check->get_result()->fetch_row()[0] > 0;

if ($tieneUsuarios) {
    echo json_encode(['success' => false, 'message' => 'No se puede eliminar: tiene usuarios asociados']);
    exit;
}

$stmt = $conexion->prepare("DELETE FROM grupo WHERE id = ? AND nivel < 100");
$stmt->bind_param('i', $id);
$success = $stmt->execute();

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Grupo eliminado' : 'Error al eliminar'
]);