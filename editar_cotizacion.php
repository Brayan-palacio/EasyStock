<?php
$tituloPagina = 'Editar Cotización';
session_start();

// Configuración de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['error'] = "Debe iniciar sesión para acceder a esta función";
    header("Location: login.php");
    exit;
}

include 'config/conexion.php';

// Obtener ID de cotización
$cotizacion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cotizacion_id <= 0) {
    $_SESSION['error'] = "Cotización no especificada";
    header("Location: listar_cotizaciones.php");
    exit;
}

// Consultar cotización
$stmt = $conexion->prepare("SELECT * FROM cotizaciones WHERE id = ?");
$stmt->bind_param("i", $cotizacion_id);
$stmt->execute();
$cotizacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cotizacion) {
    $_SESSION['error'] = "Cotización no encontrada";
    header("Location: listar_cotizaciones.php");
    exit;
}

// Verificar que la cotización esté pendiente
if ($cotizacion['estado'] !== 'pendiente') {
    $_SESSION['error'] = "Solo se pueden editar cotizaciones en estado pendiente";
    header("Location: ver_cotizacion.php?id=" . $cotizacion_id);
    exit;
}

// Consultar productos disponibles
$productos = $conexion->query("SELECT * FROM productos ORDER BY descripcion");

// Consultar detalles actuales de la cotización
$stmt = $conexion->prepare("SELECT cd.*, p.descripcion as producto_descripcion 
                           FROM cotizacion_detalles cd
                           JOIN productos p ON cd.producto_id = p.id
                           WHERE cd.cotizacion_id = ?");
$stmt->bind_param("i", $cotizacion_id);
$stmt->execute();
$detalles_actuales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token de seguridad inválido";
        header("Location: editar_cotizacion.php?id=" . $cotizacion_id);
        exit;
    }

    // Obtener datos del formulario
    $cliente = trim($_POST['cliente']);
    $contacto = trim($_POST['contacto']);
    $notas = trim($_POST['notas']);
    $validez_dias = intval($_POST['validez_dias']);
    
    // Validar datos básicos
    if (empty($cliente)) {
        $_SESSION['error'] = "El nombre del cliente es obligatorio";
        header("Location: editar_cotizacion.php?id=" . $cotizacion_id);
        exit;
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // Actualizar cabecera de cotización
        $stmt = $conexion->prepare("UPDATE cotizaciones 
                                   SET cliente = ?, contacto = ?, notas = ?, validez_dias = ?
                                   WHERE id = ?");
        $stmt->bind_param("sssii", $cliente, $contacto, $notas, $validez_dias, $cotizacion_id);
        $stmt->execute();
        $stmt->close();

        // Eliminar detalles antiguos
        $stmt = $conexion->prepare("DELETE FROM cotizacion_detalles WHERE cotizacion_id = ?");
        $stmt->bind_param("i", $cotizacion_id);
        $stmt->execute();
        $stmt->close();

        // Insertar nuevos detalles
        if (isset($_POST['producto_id'])) {
            $total = 0;
            
            foreach ($_POST['producto_id'] as $index => $producto_id) {
                $cantidad = intval($_POST['cantidad'][$index]);
                $precio_unitario = floatval($_POST['precio_unitario'][$index]);
                
                if ($producto_id > 0 && $cantidad > 0) {
                    $subtotal = $cantidad * $precio_unitario;
                    $total += $subtotal;
                    
                    $stmt = $conexion->prepare("INSERT INTO cotizacion_detalles 
                                              (cotizacion_id, producto_id, cantidad, precio_unitario, subtotal)
                                              VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiidd", $cotizacion_id, $producto_id, $cantidad, $precio_unitario, $subtotal);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            // Actualizar total
            $stmt = $conexion->prepare("UPDATE cotizaciones SET total = ? WHERE id = ?");
            $stmt->bind_param("di", $total, $cotizacion_id);
            $stmt->execute();
            $stmt->close();
        }

        $conexion->commit();
        $_SESSION['exito'] = "Cotización actualizada correctamente";
        header("Location: ver_cotizacion.php?id=" . $cotizacion_id);
        exit;
        
    } catch (Exception $e) {
        $conexion->rollback();
        $_SESSION['error'] = "Error al actualizar la cotización: " . $e->getMessage();
        header("Location: editar_cotizacion.php?id=" . $cotizacion_id);
        exit;
    }
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="card p-4 shadow-sm" style="border-top: 4px solid #d4af37;">
        <h2 style="color: #1a3a2f;">Editar Cotización #<?= $cotizacion['id'] ?></h2>
        
        <form method="POST" id="formCotizacion">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header" style="background-color: #1a3a2f; color: white;">
                            <strong>Información del Cliente</strong>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="cliente" class="form-label">Nombre del Cliente *</label>
                                <input type="text" class="form-control" id="cliente" name="cliente" 
                                       value="<?= htmlspecialchars($cotizacion['cliente']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="contacto" class="form-label">Contacto</label>
                                <input type="text" class="form-control" id="contacto" name="contacto" 
                                       value="<?= htmlspecialchars($cotizacion['contacto']) ?>">
                            </div>
                            <div class="mb-3">
                                <label for="notas" class="form-label">Notas</label>
                                <textarea class="form-control" id="notas" name="notas" rows="3"><?= htmlspecialchars($cotizacion['notas']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header" style="background-color: #1a3a2f; color: white;">
                            <strong>Configuración de la Cotización</strong>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="validez_dias" class="form-label">Validez (días)</label>
                                <input type="number" class="form-control" id="validez_dias" name="validez_dias" 
                                       value="<?= $cotizacion['validez_dias'] ?>" min="1" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-hover" id="tablaProductos">
                    <thead style="background-color: #1a3a2f; color: white;">
                        <tr>
                            <th>Producto</th>
                            <th>Precio Unitario</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles_actuales as $index => $detalle): ?>
                            <tr class="fila-producto">
                                <td>
                                    <select class="form-control producto-select" name="producto_id[]" required>
                                        <option value="">Seleccione un producto</option>
                                        <?php while ($producto = $productos->fetch_assoc()): ?>
                                            <option value="<?= $producto['id'] ?>" 
                                                <?= $producto['id'] == $detalle['producto_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($producto['descripcion']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <?php $productos->data_seek(0); // Reiniciar el puntero del resultset ?>
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control precio" 
                                           name="precio_unitario[]" value="<?= $detalle['precio_unitario'] ?>" required>
                                </td>
                                <td>
                                    <input type="number" class="form-control cantidad" 
                                           name="cantidad[]" value="<?= $detalle['cantidad'] ?>" min="1" required>
                                </td>
                                <td class="subtotal">$<?= number_format($detalle['subtotal'], 2, ',', '.') ?></td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-eliminar-fila">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                            <td id="total-cotizacion">$<?= number_format($cotizacion['total'], 2, ',', '.') ?></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="5">
                                <button type="button" id="btn-agregar-producto" class="btn btn-sm" 
                                        style="background-color: #d4af37; color: #1a3a2f;">
                                    <i class="fas fa-plus me-2"></i>Agregar Producto
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="ver_cotizacion.php?id=<?= $cotizacion['id'] ?>" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Plantilla para nuevas filas de productos -->
<template id="plantilla-fila-producto">
    <tr class="fila-producto">
        <td>
            <select class="form-control producto-select" name="producto_id[]" required>
                <option value="">Seleccione un producto</option>
                <?php while ($producto = $productos->fetch_assoc()): ?>
                    <option value="<?= $producto['id'] ?>">
                        <?= htmlspecialchars($producto['descripcion']) ?>
                    </option>
                <?php endwhile; ?>
                <?php $productos->data_seek(0); ?>
            </select>
        </td>
        <td>
            <input type="number" step="0.01" class="form-control precio" name="precio_unitario[]" required>
        </td>
        <td>
            <input type="number" class="form-control cantidad" name="cantidad[]" min="1" required>
        </td>
        <td class="subtotal">$0.00</td>
        <td>
            <button type="button" class="btn btn-danger btn-eliminar-fila">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Agregar nueva fila de producto
    document.getElementById('btn-agregar-producto').addEventListener('click', function() {
        const plantilla = document.getElementById('plantilla-fila-producto');
        const nuevaFila = plantilla.content.cloneNode(true);
        document.querySelector('#tablaProductos tbody').appendChild(nuevaFila);
    });

    // Eliminar fila de producto
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-eliminar-fila') || 
            e.target.closest('.btn-eliminar-fila')) {
            const fila = e.target.closest('.fila-producto');
            if (document.querySelectorAll('.fila-producto').length > 1) {
                fila.remove();
                calcularTotal();
            } else {
                alert('Debe haber al menos un producto en la cotización');
            }
        }
    });

    // Calcular subtotal y total cuando cambian los valores
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('precio') || e.target.classList.contains('cantidad')) {
            const fila = e.target.closest('.fila-producto');
            const precio = parseFloat(fila.querySelector('.precio').value) || 0;
            const cantidad = parseInt(fila.querySelector('.cantidad').value) || 0;
            const subtotal = precio * cantidad;
            
            fila.querySelector('.subtotal').textContent = '$' + subtotal.toFixed(2).replace('.', ',');
            calcularTotal();
        }
    });

    // Función para calcular el total
    function calcularTotal() {
        let total = 0;
        document.querySelectorAll('.fila-producto').forEach(fila => {
            const precio = parseFloat(fila.querySelector('.precio').value) || 0;
            const cantidad = parseInt(fila.querySelector('.cantidad').value) || 0;
            total += precio * cantidad;
        });
        
        document.getElementById('total-cotizacion').textContent = '$' + total.toFixed(2).replace('.', ',');
    }

    // Cargar precios cuando se selecciona un producto
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('producto-select') && e.target.value) {
            const productoId = e.target.value;
            const fila = e.target.closest('.fila-producto');
            
            // Aquí podrías hacer una petición AJAX para obtener el precio del producto
            // Por simplicidad, lo dejamos como responsabilidad del usuario
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>