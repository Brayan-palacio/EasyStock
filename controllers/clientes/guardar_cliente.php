<?php
// Configuración de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Content-Type: application/json");

session_start();

// Respuesta estándar
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'cliente' => null
];

try {
    // Validar sesión
    if (!isset($_SESSION['id_usuario'])) {
        http_response_code(401);
        throw new Exception('No autorizado');
    }

    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido');
    }

    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        throw new Exception('Token CSRF inválido');
    }

    // Conexión a la base de datos
include_once(__DIR__ . '/../../config/conexion.php');

    // Validar y sanitizar datos
    $nombre = trim($_POST['nombre'] ?? '');
    $identificacion = trim($_POST['identificacion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    // Validaciones
    if (empty($nombre)) {
        $response['errors']['nombre'] = 'El nombre es obligatorio';
    } elseif (strlen($nombre) > 100) {
        $response['errors']['nombre'] = 'El nombre no puede exceder los 100 caracteres';
    }

    if (empty($identificacion)) {
        $response['errors']['identificacion'] = 'La identificación es obligatoria';
    } elseif (strlen($identificacion) > 20) {
        $response['errors']['identificacion'] = 'La identificación no puede exceder los 20 caracteres';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['errors']['email'] = 'El email no es válido';
    } elseif (strlen($email) > 100) {
        $response['errors']['email'] = 'El email no puede exceder los 100 caracteres';
    }

    if (!empty($telefono) && strlen($telefono) > 20) {
        $response['errors']['telefono'] = 'El teléfono no puede exceder los 20 caracteres';
    }

    // Si hay errores de validación
    if (!empty($response['errors'])) {
        http_response_code(422); // Unprocessable Entity
        $response['message'] = 'Errores de validación';
        echo json_encode($response);
        exit;
    }

    // Verificar si la identificación ya existe
    $stmt_check = $conexion->prepare("SELECT id FROM clientes WHERE identificacion = ?");
    $stmt_check->bind_param("s", $identificacion);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        http_response_code(409); // Conflict
        $response['errors']['identificacion'] = 'Ya existe un cliente con esta identificación';
        $response['message'] = 'La identificación ya está registrada';
        echo json_encode($response);
        exit;
    }
    $stmt_check->close();

    // Insertar nuevo cliente
    $sql = "INSERT INTO clientes 
            (nombre, identificacion, telefono, email, direccion, estado, creado_en) 
            VALUES (?, ?, ?, ?, ?, 'Activo', NOW())";
    
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conexion->error);
    }

    $stmt->bind_param("sssss", $nombre, $identificacion, $telefono, $email, $direccion);

    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar consulta: " . $stmt->error);
    }

    // Obtener datos del cliente recién creado
    $cliente_id = $stmt->insert_id;
    $stmt->close();

    // Consultar el cliente creado para devolverlo completo
    $stmt_get = $conexion->prepare("SELECT id, nombre, identificacion, email, telefono FROM clientes WHERE id = ?");
    $stmt_get->bind_param("i", $cliente_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $cliente = $result->fetch_assoc();
    $stmt_get->close();

    // Respuesta exitosa
    $response['success'] = true;
    $response['message'] = 'Cliente creado exitosamente';
    $response['cliente'] = $cliente;

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Error en guardar_cliente.php: ' . $e->getMessage());
    
    if (!isset($response['message']) || empty($response['message'])) {
        $response['message'] = 'Error al procesar la solicitud';
    }
    
    if (!headers_sent()) {
        http_response_code(500);
    }
    
    echo json_encode($response);
} finally {
    if (isset($conexion)) {
        $conexion->close();
    }
}