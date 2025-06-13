<?php
require '../config/conexion.php';
header('Content-Type: application/json');

// Verificar permisos y sesión
session_start();
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = (int)$_GET['id'];

// Evitar auto-eliminación
if ($_SESSION['id_usuario'] == $id) {
    echo json_encode(['success' => false, 'message' => 'No puedes eliminarte a ti mismo']);
    exit;
}

// Obtener imagen para eliminarla
$imagen = $conexion->query("SELECT imagen FROM usuarios WHERE id = $id")->fetch_assoc()['imagen'];

// Eliminar usuario
$query = "DELETE FROM usuarios WHERE id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param('i', $id);
$success = $stmt->execute();

// Eliminar imagen si existe
if ($success && !empty($imagen)) {
    $rutaImagen = 'assets/img/usuarios/' . $imagen;
    if (file_exists($rutaImagen)) {
        unlink($rutaImagen);
    }
}

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Usuario eliminado' : 'Error al eliminar'
]);