<?php
session_start();
$conexion = new mysqli('localhost', 'root', '', 'sistema_inventario');
$conexion->set_charset("utf8");

if ($conexion->connect_error) {
    error_log("Error de conexión: " . $conexion->connect_error);
    $_SESSION['error'] = "Ocurrió un problema, intenta más tarde.";
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['contraseña'] ?? '';

    if (empty($usuario) || empty($password)) {
        $_SESSION['error'] = "Usuario y contraseña son requeridos";
        header("Location: ../login.php");
        exit;
    }

    // Consulta modificada para buscar por nombre de usuario
    $sql = "SELECT id, nombre, usuario, contraseña, rol_usuario, estado, imagen, grupo_id 
            FROM usuarios 
            WHERE usuario = ? 
            LIMIT 1";
    
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        error_log("Error en consulta preparada: " . $conexion->error);
        $_SESSION['error'] = "Error interno. Intenta más tarde.";
        header("Location: ../login.php");
        exit;
    }

    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verificar estado del usuario
        if ($user['estado'] !== 'Activo') {
            $_SESSION['error'] = "Tu cuenta está inactiva. Contacta al administrador.";
            header("Location: ../login.php");
            exit;
        }

        // Verificar contraseña
        if (password_verify($password, $user['contraseña'])) {
            // Configurar sesión
            $_SESSION['id_usuario'] = $user['id'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['rol_usuario'] = $user['rol_usuario'];
            $_SESSION['grupo_id'] = $user['grupo_id'];
            $_SESSION['imagen'] = !empty($user['imagen']) ? "assets/img/usuarios/" . $user['imagen'] : "assets/img/usuario-default.png";

            // Actualizar último login
            $fechaHora = date('Y-m-d H:i:s');
            $updateQuery = "UPDATE usuarios SET ultimo_login = ? WHERE id = ?";
            $updateStmt = $conexion->prepare($updateQuery);
            if ($updateStmt) {
                $updateStmt->bind_param("si", $fechaHora, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }

            $stmt->close();
            $conexion->close();
            
            // Redirigir según rol o página por defecto
            header("Location: ../index.php");
            exit;
        }
    }

    // Credenciales incorrectas
    $_SESSION['error'] = "Usuario o contraseña incorrectos";
    if (isset($stmt)) $stmt->close();
    header("Location: ../login.php");
    exit;
}

$conexion->close();
?>