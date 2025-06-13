<?php
include 'config/conexion.php';

// Verificar si se pasó el parámetro 'id' y si es un número válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'ID de producto no válido']);
    exit;
}

$id = (int)$_GET['id']; // Convertir a entero

// Consultar el producto
$result = $conexion->query("SELECT * FROM productos WHERE id = $id");
if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Producto no encontrado']);
    exit;
}

$producto = $result->fetch_assoc();

// Obtener las categorías
$result_categorias = $conexion->query("SELECT * FROM categorias");
$categorias = [];
while ($row = $result_categorias->fetch_assoc()) {
    $categorias[] = $row;
}

// Respuesta JSON con los datos del producto y las categorías
echo json_encode([
    'id' => $producto['id'],
    'descripcion' => $producto['descripcion'],
    'categoria_id' => $producto['categoria_id'],
    'cantidad' => $producto['cantidad'],
    'precio_compra' => $producto['precio_compra'],
    'precio_venta' => $producto['precio_venta'],
    'categorias' => $categorias
]);
?>
