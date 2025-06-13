<?php
include 'conexion.php';

// Obtener los datos de la categoría
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM categorias WHERE id = $id";
    $resultado = $conexion->query($sql);
    $categoria = $resultado->fetch_assoc();
}

// Actualizar los datos de la categoría
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];

    $sql = "UPDATE categorias SET nombre = '$nombre', descripcion = '$descripcion' WHERE id = $id";
    if ($conexion->query($sql)) {
        header("Location: categorias.php");
    } else {
        echo "Error: " . $conexion->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Categoría</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Editar Categoría</h1>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $categoria['id'] ?>">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= $categoria['nombre'] ?>" required>
            </div>
            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= $categoria['descripcion'] ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="categorias.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>








<?php
include 'config/conexion.php'; // Archivo de conexión a la base de datos

// Verificar si se recibió un ID para editar
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Consultar el producto actual
    $sql_producto = "SELECT * FROM productos WHERE id = $id";
    $resultado_producto = $conexion->query($sql_producto);

    if ($resultado_producto->num_rows == 0) {
        die("Producto no encontrado");
    }

    $producto = $resultado_producto->fetch_assoc();

    // Obtener las categorías para el formulario
    $sql_categorias = "SELECT * FROM categorias";
    $resultado_categorias = $conexion->query($sql_categorias);
} else {
    die("ID no proporcionado");
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descripcion = $_POST['descripcion'];
    $categoria_id = $_POST['categoria_id'] ?: 'NULL';
    $cantidad = $_POST['cantidad'];
    $precio_compra = $_POST['precio_compra'];
    $precio_venta = $_POST['precio_venta'];

    $sql_actualizar = "UPDATE productos SET 
                        descripcion = '$descripcion', 
                        cantidad = $cantidad,
                        precio_compra = $precio_compra, 
                        precio_venta = $precio_venta, 
                        categoria_id = $categoria_id 
                      WHERE id = $id";

    if ($conexion->query($sql_actualizar)) {
        echo json_encode(['success' => true, 'message' => 'Producto actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $conexion->error]);
    }
    exit; // Salir para evitar cargar el HTML
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .barra-azul {
            height: 5px;
            background-color: #007bff;
            margin-bottom: 20px;
        }
        .form-control-icon {
            position: absolute;
            top: 50%;
            left: 25px;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .form-group {
            position: relative;
        }
        .form-control {
            padding-left: 40px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h4>Editar Producto</h4>
        <div class="barra-azul"></div>
        <form id="form-editar-producto" method="POST">
        <div class="row mb-3">
                <div class="col-md-12 form-group">
                <i class="fa fa-cube form-control-icon"></i>
                <label for="descripcion" class="form-label">Descripción</label>
                <input type="text" class="form-control" id="descripcion" name="descripcion" placeholder="Descripción" value="<?= $producto['descripcion'] ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="categoria_id" class="form-label">Categoría</label>
                <select class="form-select" id="categoria_id" name="categoria_id">
                    <option value="">Sin Categoría</option>
                    <?php while ($cat = $resultado_categorias->fetch_assoc()) { ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $producto['categoria_id'] ? 'selected' : '' ?>>
                            <?= $cat['nombre'] ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 form-group">
                <label for="cantidad" class="form-label">Cantidad</label>
                <input type="number" class="form-control" id="cantidad" name="cantidad" value="<?= $producto['cantidad'] ?>" required>
            </div>
                
                <div class="col-md-4 form-group">
                <label for="cantidad" class="form-label">Precio de compra</label>
                <input type="number" class="form-control" id="precio_compra" name="precio_compra" value="<?= $producto['precio_compra'] ?>" required>
                </div>
                <div class="col-md-4 form-group">
                <label for="cantidad" class="form-label">Precio de venta</label>
                <input type="number" class="form-control" id="precio_venta" name="precio_venta" value="<?= $producto['precio_venta'] ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-success">Guardar Cambios</button>
            <a href="productos.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>

