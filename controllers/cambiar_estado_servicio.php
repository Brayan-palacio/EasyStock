<?php
require 'config/conexion.php';
header('Content-Type: application/json');

// Verificar autenticación y permisos
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['nivel_grupo'] < 30) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Validar datos de entrada
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$estado = in_array($_GET['estado'] ?? '', ['Activo', 'Inactivo']) ? $_GET['estado'] : null;

if (!$id || !$estado) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Actualizar estado
try {
    $stmt = $conexion->prepare("UPDATE servicios SET estado = ? WHERE id = ?");
    $stmt->bind_param("si", $estado, $id);
    $success = $stmt->execute();
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Estado actualizado' : 'Error al actualizar'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}