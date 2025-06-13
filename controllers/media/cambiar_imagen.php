<?php
session_start();
include 'config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["imagen"])) {
    $id_usuario = $_SESSION['id_usuario'];

    // Directorio donde se guardarán las imágenes
    $directorio = "uploads/";
    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }

    // Obtener detalles del archivo
    $imagen = $_FILES["imagen"];
    $extension = strtolower(pathinfo($imagen["name"], PATHINFO_EXTENSION));

    // Validar formatos permitidos
    $extensionesPermitidas = ["jpg", "jpeg", "png", "gif"];
    if (!in_array($extension, $extensionesPermitidas)) {
        die("Error: Formato de imagen no permitido.");
    }

    // Nombre fijo basado en el ID del usuario (se sobrescribe si ya existe)
    $nombreArchivo = "perfil_" . $id_usuario . "." . $extension;
    $rutaFinal = $directorio . $nombreArchivo;

    // Eliminar la imagen anterior si existe (evita archivos duplicados)
    foreach ($extensionesPermitidas as $ext) {
        $rutaAntigua = $directorio . "perfil_" . $id_usuario . "." . $ext;
        if (file_exists($rutaAntigua)) {
            unlink($rutaAntigua);
        }
    }

    // Mover la nueva imagen al directorio 'uploads' con el mismo nombre
    if (move_uploaded_file($imagen["tmp_name"], $rutaFinal)) {
        // Guardar la ruta en la base de datos
        $query = "UPDATE usuarios SET imagen = ? WHERE id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("si", $rutaFinal, $id_usuario);

        if ($stmt->execute()) {
            // Actualizar la sesión con la nueva imagen
            $_SESSION['imagen'] = $rutaFinal;
            header("Location: perfil.php?success=1");
            exit();
        } else {
            die("Error al actualizar la imagen en la base de datos.");
        }
    } else {
        die("Error al subir la imagen.");
    }
} else {
    die("Acceso no válido.");
}
?>
