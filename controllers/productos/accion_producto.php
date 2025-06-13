<?php
session_start();
require '../../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acceso no autorizado']));
}

$data = json_decode(file_get_contents('php://input'), true);
$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$action = $data['action'] ?? '';

if (!$id || !in_array($action, ['activar', 'desactivar'])) {
    exit(json_encode(['success' => false, 'message' => 'Datos inválidos']));
}

$nuevoEstado = $action === 'activar' ? 1 : 0;
$stmt = $conexion->prepare("UPDATE productos SET activo = ? WHERE id = ?");
$stmt->bind_param("ii", $nuevoEstado, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => "Producto {$action}do correctamente"]);
} else {
    echo json_encode(['success' => false, 'message' => "Error al {$action} el producto"]);
}