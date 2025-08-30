<?php
try {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // Limpiar buffers de output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    require '../config/conexion.php';
    
    // Headers deben ser lo primero
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    // Verificar permisos y sesión
    session_start();
    if (!isset($_SESSION['id_usuario']) || $_SESSION['logged_in'] !== true) {
        throw new Exception('No autorizado');
    }
    
    // Verificar permisos de administrador
    if ($_SESSION['rol_usuario'] !== 'Administrador') {
        throw new Exception('Permisos insuficientes');
    }
    
    // Validar y sanitizar ID
    if (!isset($_GET['id'])) {
        throw new Exception('ID no proporcionado');
    }
    
    $id = (int)$_GET['id'];
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Evitar auto-eliminación
    if ($_SESSION['id_usuario'] == $id) {
        throw new Exception('No puedes eliminarte a ti mismo');
    }
    
    // Obtener imagen para eliminarla (CON PREPARED STATEMENT)
    $imagen = '';
    $stmt_img = $conexion->prepare("SELECT imagen FROM usuarios WHERE id = ?");
    if (!$stmt_img) {
        throw new Exception('Error preparando consulta de imagen');
    }
    
    $stmt_img->bind_param('i', $id);
    if (!$stmt_img->execute()) {
        throw new Exception('Error ejecutando consulta de imagen');
    }
    
    $resultado = $stmt_img->get_result();
    if ($fila = $resultado->fetch_assoc()) {
        $imagen = $fila['imagen'] ?? '';
    }
    $stmt_img->close();
    
    // Eliminar usuario
    $query = "DELETE FROM usuarios WHERE id = ?";
    $stmt = $conexion->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparando consulta de eliminación');
    }
    
    $stmt->bind_param('i', $id);
    $success = $stmt->execute();
    
    if (!$success) {
        throw new Exception('Error ejecutando eliminación: ' . $conexion->error);
    }
    
    // Eliminar imagen si existe
    if ($success && !empty($imagen)) {
        $imagen = basename($imagen); // Prevenir path traversal
        $rutaImagen = '../assets/img/usuarios/' . $imagen; // NOTA: agregué ../
        
        if (file_exists($rutaImagen) && is_file($rutaImagen)) {
            unlink($rutaImagen);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Usuario eliminado correctamente',
        'deleted_id' => $id
    ]);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en eliminar_usuario.php: " . $e->getMessage());
    
    // Respuesta JSON de error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ]);
    exit;
    
} catch (Throwable $t) {
    // Log de errores fatales
    error_log("Error fatal en eliminar_usuario.php: " . $t->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => true
    ]);
    exit;
}