<?php
$tituloPagina = 'Editar Venta';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar si se proporcionó un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ventas.php");
    exit();
}

$id_venta = intval($_GET['id']);

// Obtener datos de la venta
$sql_venta = "SELECT * FROM ventas WHERE id = ?";
$stmt_venta = $conexion->prepare($sql_venta);
$stmt_venta->bind_param("i", $id_venta);
$stmt_venta->execute();
$result_venta = $stmt_venta->get_result();
$venta = $result_venta->fetch_assoc();

if (!$venta) {
    header("Location: ventas.php");
    exit();
}

// Obtener detalles de la venta
$sql_detalles = "SELECT dv.*, p.id as producto_id, p.descripcion, p.precio 
                 FROM detalle_ventas dv
                 JOIN productos p ON dv.producto_id = p.id
                 WHERE dv.venta_id = ?";
$stmt_detalles = $conexion->prepare($sql_detalles);
$stmt_detalles->bind_param("i", $id_venta);
$stmt_detalles->execute();
$detalles = $stmt_detalles->get_result();

// Obtener productos disponibles
$sql_productos = "SELECT id, descripcion, precio FROM productos ORDER BY descripcion";
$result_productos = $conexion->query($sql_productos);
$productos = $result_productos->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="card shadow-lg p-4 rounded-4">
        <h4><i class="fas fa-edit"></i> EDITAR VENTA</h4>
        <hr>
        
        <form action="procesar_editar_venta.php" method="post" id="form-venta">
            <input type="hidden" name="id_venta" value="<?= $venta['id'] ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="fecha" class="form-label">Fecha de Venta</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" 
                           value="<?= date('Y-m-d', strtotime($venta['fecha_venta'])) ?>" required>
                </div>
            </div>
            
            <div class="table-responsive mb-3">
                <table class="table table-bordered" id="tabla-productos">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($detalles->num_rows > 0): ?>
                            <?php while ($detalle = $detalles->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <select class="form-select producto" name="producto[]" required>
                                        <option value="">Seleccionar producto</option>
                                        <?php foreach ($productos as $producto): ?>
                                        <option value="<?= $producto['id'] ?>" 
                                            data-precio="<?= $producto['precio'] ?>"
                                            <?= $producto['id'] == $detalle['producto_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($producto['descripcion']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="form-control cantidad" name="cantidad[]" 
                                           min="1" value="<?= $detalle['cantidad'] ?>" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control precio" 
                                           name="precio[]" value="<?= $detalle['precio'] ?>" readonly>
                                </td>
                                <td>
                                    <input type="text" class="form-control subtotal" 
                                           value="<?= number_format($detalle['cantidad'] * $detalle['precio'], 2) ?>" readonly>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-eliminar-fila">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay productos en esta venta</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" class="btn btn-success" id="btn-agregar-producto">
                    <i class="fas fa-plus"></i> Agregar Producto
                </button>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4 offset-md-8">
                    <div class="input-group mb-2">
                        <span class="input-group-text">Total</span>
                        <input type="text" class="form-control" id="total" name="total" 
                               value="<?= number_format($venta['total'], 2) ?>" readonly>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="ventas.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Incluir jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Función para calcular subtotal y total
    function calcularTotales() {
        let total = 0;
        
        $('#tabla-productos tbody tr').each(function() {
            const $row = $(this);
            if ($row.find('td').length > 1) { // Verificar que no sea la fila de "no hay productos"
                const cantidad = parseFloat($row.find('.cantidad').val()) || 0;
                const precio = parseFloat($row.find('.precio').val()) || 0;
                const subtotal = cantidad * precio;
                
                $row.find('.subtotal').val(subtotal.toFixed(2));
                total += subtotal;
            }
        });
        
        $('#total').val(total.toFixed(2));
    }
    
    // Evento para cambiar producto
    $(document).on('change', '.producto', function() {
        const $row = $(this).closest('tr');
        const precio = $(this).find('option:selected').data('precio');
        $row.find('.precio').val(precio);
        calcularTotales();
    });
    
    // Evento para cambiar cantidad
    $(document).on('input', '.cantidad', function() {
        calcularTotales();
    });
    
    // Plantilla para nueva fila de producto
    const nuevaFilaTemplate = `
        <tr>
            <td>
                <select class="form-select producto" name="producto[]" required>
                    <option value="">Seleccionar producto</option>
                    <?php foreach ($productos as $producto): ?>
                    <option value="<?= $producto['id'] ?>" data-precio="<?= $producto['precio'] ?>">
                        <?= htmlspecialchars($producto['descripcion']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="number" class="form-control cantidad" name="cantidad[]" min="1" value="1" required>
            </td>
            <td>
                <input type="number" step="0.01" class="form-control precio" name="precio[]" readonly>
            </td>
            <td>
                <input type="text" class="form-control subtotal" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-eliminar-fila">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    // Agregar nueva fila de producto
    $('#btn-agregar-producto').click(function() {
        if ($('#tabla-productos tbody tr').length === 1 && $('#tabla-productos tbody tr td').length === 1) {
            // Reemplazar la fila "no hay productos"
            $('#tabla-productos tbody').html(nuevaFilaTemplate);
        } else {
            $('#tabla-productos tbody').append(nuevaFilaTemplate);
        }
    });
    
    // Eliminar fila de producto
    $(document).on('click', '.btn-eliminar-fila', function() {
        const $tbody = $('#tabla-productos tbody');
        const $rows = $tbody.find('tr');
        
        if ($rows.length > 1 || ($rows.length === 1 && $rows.find('td').length > 1)) {
            $(this).closest('tr').remove();
            calcularTotales();
            
            // Si no quedan filas, agregar mensaje
            if ($tbody.find('tr').length === 0) {
                $tbody.append('<tr><td colspan="5" class="text-center">No hay productos en esta venta</td></tr>');
            }
        } else {
            Swal.fire({
                title: 'Advertencia',
                text: 'Debe haber al menos un producto en la venta',
                icon: 'warning',
                confirmButtonText: 'Aceptar'
            });
        }
    });
    
    // Calcular totales al cargar la página
    calcularTotales();
});
</script>

<?php include_once 'includes/footer.php'; ?>