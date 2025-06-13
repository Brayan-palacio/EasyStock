<?php
require 'config/conexion.php';
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$id = (int)$_GET['id'];

// Marcar como leída
$query = "UPDATE notificaciones SET leida = TRUE WHERE id = ? AND usuario_id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param('ii', $id, $_SESSION['id_usuario']);
$success = $stmt->execute();

// Obtener nuevo contador
$queryCount = "SELECT COUNT(*) as total FROM notificaciones WHERE usuario_id = ? AND leida = FALSE";
$stmtCount = $conexion->prepare($queryCount);
$stmtCount->bind_param('i', $_SESSION['id_usuario']);
$stmtCount->execute();
$count = $stmtCount->get_result()->fetch_assoc()['total'];

echo json_encode([
    'success' => $success,
    'nuevoContador' => (int)$count
]);