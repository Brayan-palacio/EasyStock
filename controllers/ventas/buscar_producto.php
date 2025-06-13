<?php
// Configuración de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

session_start();

// Validar sesión
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'No autorizado']));
}

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido']));
}

// Conexión a la base de datos
include_once(__DIR__ . '/../../config/conexion.php');

try {
    // Validar y sanitizar entrada
    $q = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING);
    if (empty($q)) {
        exit(json_encode([]));
    }

    // Consulta preparada con búsqueda segura
    $sql = "SELECT id, nombre, descripcion, precio_venta, cantidad, codigo_barras 
            FROM productos 
            WHERE activo = 1 AND cantidad > 0 
            AND (nombre LIKE CONCAT('%', ?, '%') 
                 OR descripcion LIKE CONCAT('%', ?, '%') 
                 OR codigo_barras = ?)
            ORDER BY nombre ASC
            LIMIT 10";

    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conexion->error);
    }

    $stmt->bind_param("sss", $q, $q, $q);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar consulta: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $productos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Formatear precios
    foreach ($productos as &$producto) {
        $producto['precio_formateado'] = number_format($producto['precio_venta'], 0, ',', '.');
    }

    header('Content-Type: application/json');
    echo json_encode($productos);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
} finally {
    if (isset($conexion)) {
        $conexion->close();
    }
}