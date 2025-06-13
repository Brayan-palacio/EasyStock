<?php
include 'config/conexion.php';

if (!isset($_POST['id_venta'], $_POST['cliente_id'])) {
    die("Datos incompletos.");
}

$id_venta = intval($_POST['id_venta']);
$cliente_id = intval($_POST['cliente_id']);

if (!$conexion) {
    die("Error de conexión: " . $conexion->connect_error);
}

$sql = "UPDATE ventas SET cliente_id = ? WHERE id = ?";
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error en la preparación de la consulta: " . $conexion->error);
}

$stmt->bind_param("ii", $cliente_id, $id_venta);
if ($stmt->execute()) {
    // Obtener la URL del archivo de factura
    $url_factura = "http://localhost/inventario/generar_factura.php?id=" . urlencode($id_venta);

    // Abrir en el navegador predeterminado
    if (PHP_OS_FAMILY === 'Windows') {
        shell_exec("start $url_factura");
    } elseif (PHP_OS_FAMILY === 'Linux') {
        shell_exec("xdg-open $url_factura");
    } elseif (PHP_OS_FAMILY === 'Darwin') { // macOS
        shell_exec("open $url_factura");
    }

    // Redirigir al usuario a otra página si es necesario
    header("Location: ventas.php");
    exit;
} else {
    echo "Error al asignar el cliente: " . $stmt->error;
}

$stmt->close();
$conexion->close();
?>
