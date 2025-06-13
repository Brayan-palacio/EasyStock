<?php
include 'config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["imagen"])) {
    $imagen = $_FILES["imagen"];
    $nombre = basename($imagen["name"]);
    $tipo = $imagen["type"];
    $ruta = "uploads/" . $nombre;

    // Mover archivo a la carpeta 'uploads'
    if (move_uploaded_file($imagen["tmp_name"], $ruta)) {
        $query = "INSERT INTO imagenes (nombre, tipo) VALUES ('$nombre', '$tipo')";
        if ($conexion->query($query)) {
            header("Location: media.php"); // Redirige a la lista de imágenes
            exit();
        } else {
            echo "Error al guardar en la base de datos: " . $conexion->error;
        }
    } else {
        echo "Error al subir la imagen.";
    }
}
?>
