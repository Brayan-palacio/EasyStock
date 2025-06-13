<?php
session_start(); // Inicia sesión para almacenar mensajes
include 'config/conexion.php';

if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
    $id = intval($_GET["id"]);

    // Obtener el nombre de la imagen antes de eliminar
    $stmt = $conexion->prepare("SELECT nombre FROM imagenes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $nombre = $row["nombre"];
        $ruta = "uploads/" . $nombre;

        // Intentar eliminar el archivo del servidor
        if (file_exists($ruta)) {
            unlink($ruta);
        }

        // Eliminar el registro de la base de datos
        $stmt = $conexion->prepare("DELETE FROM imagenes WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Imagen eliminada correctamente.";
            $_SESSION['tipo_mensaje'] = "success"; // Tipo de alerta
        } else {
            $_SESSION['mensaje'] = "Error al eliminar la imagen.";
            $_SESSION['tipo_mensaje'] = "error";
        }
    } else {
        $_SESSION['mensaje'] = "Imagen no encontrada.";
        $_SESSION['tipo_mensaje'] = "warning";
    }

    // Redirigir a media.php con mensaje
    header("Location: media.php");
    exit();
} else {
    $_SESSION['mensaje'] = "ID inválido.";
    $_SESSION['tipo_mensaje'] = "error";
    header("Location: media.php");
    exit();
}
?>
