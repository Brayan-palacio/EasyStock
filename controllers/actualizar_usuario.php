<?php
session_start();
include 'config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = $_SESSION['id_usuario'];
    $nuevo_nombre = trim($_POST['nombre']);
    
    // Verificar si se recibió un nuevo nombre
    if (!empty($nuevo_nombre)) {
        // Actualizar el nombre en la base de datos
        $query = "UPDATE usuarios SET nombre = ? WHERE id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("si", $nuevo_nombre, $id_usuario);

        if ($stmt->execute()) {
            // Actualizar la sesión para reflejar los cambios
            $_SESSION['nombre'] = $nuevo_nombre;
            header("Location: perfil.php?success=1"); // Redirigir con mensaje de éxito
            exit();
        } else {
            die("Error al actualizar el usuario: " . $conexion->error);
        }
    } else {
        die("El nombre no puede estar vacío.");
    }
} else {
    die("Acceso no válido.");
}
?>
