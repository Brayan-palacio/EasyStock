<?php
session_start();

// Validar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Token de seguridad inválido";
    header("Location: listar_cotizaciones.php");
    exit;
}

// Validar sesión y permisos
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['error'] = "Debe iniciar sesión";
    header("Location: login.php");
    exit;
}

include '../../config/conexion.php';

// Obtener datos del formulario
$cotizacion_id = intval($_POST['id']);
$estado = $_POST['estado'];
$comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

// Validar estado
if (!in_array($estado, ['aprobada', 'rechazada'])) {
    $_SESSION['error'] = "Estado inválido";
    header("Location: listar_cotizaciones.php");
    exit;
}

// Actualizar la cotización
$stmt = $conexion->prepare("UPDATE cotizaciones SET estado = ?, comentario = ? WHERE id = ?");
$stmt->bind_param("ssi", $estado, $comentario, $cotizacion_id);

if ($stmt->execute()) {
    $_SESSION['exito'] = "Cotización " . $estado . " correctamente";
} else {
    $_SESSION['error'] = "Error al actualizar la cotización";
}

$stmt->close();
header("Location: ../../ver_cotizacion.php?id=" . $cotizacion_id);
exit;
?>