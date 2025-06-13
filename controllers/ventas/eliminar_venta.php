<?php
session_start();
include_once(__DIR__ . '/../../config/conexion.php');

header('Content-Type: application/json');

try {
    // Validaciones previas (método, sesión, permisos)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido", 405);
    }

    if (!isset($_SESSION['id_usuario']) || $_SESSION['rol_usuario'] !== 'Administrador') {
        throw new Exception("Acceso no autorizado", 403);
    }

    if (!isset($_POST['id'])) {
        throw new Exception("ID de venta no proporcionado", 400);
    }

    $venta_id = (int)$_POST['id'];

    $conexion->begin_transaction();

    // 1. Primero eliminar los pagos asociados
    $sql_pagos = "DELETE FROM pagos WHERE venta_id = ?";
    $stmt_pagos = $conexion->prepare($sql_pagos);
    $stmt_pagos->bind_param("i", $venta_id);
    $stmt_pagos->execute();

    // 2. Luego eliminar los detalles de venta
    $sql_detalles = "DELETE FROM venta_detalles WHERE venta_id = ?";
    $stmt_detalles = $conexion->prepare($sql_detalles);
    $stmt_detalles->bind_param("i", $venta_id);
    $stmt_detalles->execute();

    // 3. Finalmente eliminar la venta principal
    $sql_venta = "DELETE FROM ventas WHERE id = ?";
    $stmt_venta = $conexion->prepare($sql_venta);
    $stmt_venta->bind_param("i", $venta_id);
    $stmt_venta->execute();

    if ($stmt_venta->affected_rows === 0) {
        throw new Exception("No se encontró la venta con ID: $venta_id", 404);
    }

    $conexion->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Venta y registros asociados eliminados correctamente'
    ]);

} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar: ' . $e->getMessage(),
        'error_details' => $conexion->error ?? null
    ]);
} finally {
    // Cerrar statements
    isset($stmt_pagos) && $stmt_pagos->close();
    isset($stmt_detalles) && $stmt_detalles->close();
    isset($stmt_venta) && $stmt_venta->close();
    $conexion->close();
}
?>