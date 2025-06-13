<?php
// Configuración de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Iniciar sesión
session_start();

// Verificar sesión y permisos
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(403);
    die(json_encode(['error' => 'No autorizado']));
}

// Conexión a la base de datos
require '../../config/conexion.php';

// Validar entrada
if (!isset($_GET['id']) || !isset($_GET['estado'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Parámetros inválidos']));
}

$id = (int)$_GET['id'];
$estado = $_GET['estado'] === 'Activo' ? 'Activo' : 'Inactivo';

// Actualizar estado en la base de datos
try {
    $stmt = $conexion->prepare("UPDATE clientes SET estado = ?, actualizado_en = NOW() WHERE id = ?");
    $stmt->bind_param("si", $estado, $id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente no encontrado']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en el servidor: ' . $e->getMessage()]);
}

$conexion->close();
?>