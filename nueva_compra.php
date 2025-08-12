<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Nueva Compra - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] != 'Administrador' && $_SESSION['rol_usuario'] != 'Compras')) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres permisos de compras'
    ];
    header("Location: index.php");
    exit();
}

// Procesar compra
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conexion->begin_transaction();
        
        // Validar campos obligatorios
        $camposRequeridos = ['proveedor_id', 'factura', 'fecha', 'productos', 'cantidades', 'precios'];
        foreach ($camposRequeridos as $campo) {
            if (empty($_POST[$campo])) {
                throw new Exception("El campo $campo es requerido");
            }
        }

        // 1. Registrar la compra
        $proveedor_id = (int)$_POST['proveedor_id'];
        $factura = trim($_POST['factura']);
        $fecha = $_POST['fecha'];
        $observaciones = !empty($_POST['observaciones']) ? trim($_POST['observaciones']) : null;
        $total = 0;

        $sql_compra = "INSERT INTO compras 
                      (proveedor_id, num_factura, fecha, total, observaciones, usuario_id) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql_compra);
        $stmt->bind_param("issdsi", $proveedor_id, $factura, $fecha, $total, $observaciones, $_SESSION['id_usuario']);
        $stmt->execute();
        $compra_id = $conexion->insert_id;

        // 2. Procesar productos
        foreach ($_POST['productos'] as $index => $producto_id) {
            $producto_id = (int)$producto_id;
            $cantidad = (int)$_POST['cantidades'][$index];
            $precio = (float)$_POST['precios'][$index];
            
            if ($cantidad <= 0 || $precio <= 0) {
                throw new Exception("Cantidad y precio deben ser mayores a cero");
            }

            // Actualizar stock
            $conexion->query("UPDATE productos SET cantidad = cantidad + $cantidad WHERE id = $producto_id");
            
            // Registrar detalle
            $sql_detalle = "INSERT INTO compras_detalle 
                          (compra_id, producto_id, cantidad, precio_unitario) 
                          VALUES (?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql_detalle);
            $stmt->bind_param("iiid", $compra_id, $producto_id, $cantidad, $precio);
            $stmt->execute();
            
            // Registrar en Kardex
            $sql_kardex = "INSERT INTO movimientos 
                         (producto_id, tipo, cantidad, motivo, usuario_id, proveedor_id) 
                         VALUES (?, 'entrada', ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql_kardex);
            $motivo = "Compra #$compra_id - Factura: $factura";
            $stmt->bind_param("iisii", $producto_id, $cantidad, $motivo, $_SESSION['id_usuario'], $proveedor_id);
            $stmt->execute();
            
            $total += ($cantidad * $precio);
        }

        // 3. Actualizar total de la compra
        $conexion->query("UPDATE compras SET total = $total WHERE id = $compra_id");
        
        $conexion->commit();
        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Compra registrada exitosamente (Factura #$factura)"
        ];
        header("Location: compras.php");
        exit();

    } catch (Exception $e) {
        $conexion->rollback();
        $error = $e->getMessage();
    }
}

// Obtener datos para el formulario
$proveedores = $conexion->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$productos = $conexion->query("SELECT id, descripcion, precio_compra FROM productos ORDER BY descripcion")->fetch_all(MYSQLI_ASSOC);
?>

<style>
    .card-compras {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header-compras {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
    }
    .table-detalle tbody tr:last-child td {
        border-bottom: 2px solid #dee2e6;
    }
    #resultados-producto {
        max-height: 300px;
        overflow-y: auto;
        position: absolute;
        width: calc(100% - 30px);
        z-index: 1000;
        display: none;
    }
</style>

<div class="container py-4">
    <?php include 'compras_proveedores_navbar.php'; ?>
    <!-- Notificaciones -->
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['mensaje']['tipo']) ?> alert-dismissible fade show mt-3" role="alert">
            <i class="fas <?= $_SESSION['mensaje']['tipo'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($_SESSION['mensaje']['texto']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['mensaje']); ?>
    <?php endif; ?>
    <div class="card card-compras">
        <div class="card-header card-header-compras">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Nueva Compra</h4>
                <a href="compras.php" class="btn btn-light btn-sm">
                    <i class="fas fa-history me-1"></i> Ver Historial
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Formulario de Compra -->
            <form method="POST" id="form-compra">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                        <select class="form-select" name="proveedor_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">N° Factura <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="factura" required 
                               value="<?= isset($_POST['factura']) ? htmlspecialchars($_POST['factura']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="fecha" 
                               value="<?= isset($_POST['fecha']) ? htmlspecialchars($_POST['fecha']) : date('Y-m-d') ?>" required>
                    </div>
                </div>

                <!-- Productos -->
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-detalle">
                        <thead class="table-light">
                            <tr>
                                <th width="40%">Producto</th>
                                <th width="15%">Precio Compra</th>
                                <th width="15%">Cantidad</th>
                                <th width="15%">Subtotal</th>
                                <th width="15%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="detalle-compra">
                            <!-- Filas dinámicas se agregarán aquí -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end fw-bold">TOTAL:</td>
                                <td colspan="2" class="fw-bold">$<span id="total-compra">0.00</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Agregar Producto -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <input type="text" id="buscar-producto" class="form-control" 
                               placeholder="Buscar producto por código o descripción">
                        <div id="resultados-producto" class="list-group mt-1"></div>
                        <input type="hidden" id="producto-seleccionado-id">
                    </div>
                    <div class="col-md-2">
                        <input type="number" id="cantidad-producto" class="form-control" placeholder="Cantidad" min="1" value="1">
                    </div>
                    <div class="col-md-2">
                        <input type="number" id="precio-producto" class="form-control" placeholder="Precio" step="0.01" min="0.01">
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="btn-agregar" class="btn btn-success w-100">
                            <i class="fas fa-plus me-1"></i> Agregar
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Observaciones</label>
                    <textarea class="form-control" name="observaciones" rows="2"><?= isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : '' ?></textarea>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-1"></i> Registrar Compra
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const buscarProducto = document.getElementById('buscar-producto');
    const resultadosProducto = document.getElementById('resultados-producto');
    const productoSeleccionadoId = document.getElementById('producto-seleccionado-id');
    const cantidadInput = document.getElementById('cantidad-producto');
    const precioInput = document.getElementById('precio-producto');
    const btnAgregar = document.getElementById('btn-agregar');
    const tbody = document.getElementById('detalle-compra');
    const totalCompra = document.getElementById('total-compra');
    let productosAgregados = [];

    function formatearNumero(numero) {
        return new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(numero);
    }

    // Búsqueda de productos
    buscarProducto.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 3) {
            resultadosProducto.style.display = 'none';
            return;
        }

        fetch(`ajax/buscar_productos.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                resultadosProducto.innerHTML = '';
                
                if (data.length === 0) {
                    resultadosProducto.innerHTML = `
                        <div class="list-group-item text-muted">
                            No se encontraron productos
                        </div>`;
                } else {
                    data.forEach(producto => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <span>${escapeHtml(producto.descripcion || producto.nombre)}</span>
                                <small class="text-muted">$${formatearNumero(producto.precio_compra) || '0.00'}</small>
                            </div>
                            ${producto.codigo_barras ? `<small class="text-muted">Código: ${escapeHtml(producto.codigo_barras)}</small>` : ''}
                        `;
                        item.addEventListener('click', () => {
                            productoSeleccionadoId.value = producto.id;
                            buscarProducto.value = producto.descripcion || producto.nombre;
                            precioInput.value = producto.precio_compra || '0';
                            resultadosProducto.style.display = 'none';
                            cantidadInput.focus();
                        });
                        resultadosProducto.appendChild(item);
                    });
                }
                resultadosProducto.style.display = 'block';
            });
    });

    // Ocultar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!buscarProducto.contains(e.target) && !resultadosProducto.contains(e.target)) {
            resultadosProducto.style.display = 'none';
        }
    });

    // Agregar producto al detalle
    btnAgregar.addEventListener('click', function() {
        const productoId = productoSeleccionadoId.value;
        const productoTexto = buscarProducto.value;
        const precio = parseFloat(precioInput.value) || 0;
        const cantidad = parseInt(cantidadInput.value) || 1;

        if (!productoId || productoId === '') {
            alert('Selecciona un producto válido');
            return;
        }

        if (productosAgregados.includes(productoId)) {
            alert('Este producto ya fue agregado');
            return;
        }

        if (precio <= 0 || cantidad <= 0) {
            alert('Precio y cantidad deben ser mayores a cero');
            return;
        }

        // Agregar a la lista
        productosAgregados.push(productoId);

        // Crear fila
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(productoTexto)}
                <input type="hidden" name="productos[]" value="${productoId}">
            </td>
            <td>
                <input type="number" class="form-control precio" name="precios[]" 
                       value="${precio}" step="0.01" min="0.01" required>

            </td>
            <td>
                <input type="number" class="form-control cantidad" name="cantidades[]" 
                       value="${cantidad}" min="1" required>
            </td>
            <td class="subtotal">$${formatearNumero(precio * cantidad)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger eliminar">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);

        // Eventos para la nueva fila
        tr.querySelector('.precio').addEventListener('change', actualizarSubtotal);
        tr.querySelector('.cantidad').addEventListener('change', actualizarSubtotal);
        tr.querySelector('.eliminar').addEventListener('click', function() {
            productosAgregados = productosAgregados.filter(id => id !== productoId);
            tr.remove();
            calcularTotal();
        });

        // Resetear campos
        buscarProducto.value = '';
        productoSeleccionadoId.value = '';
        cantidadInput.value = '1';
        precioInput.value = '';
        calcularTotal();
    });

    // Actualizar subtotal de una fila
    function actualizarSubtotal(e) {
        const tr = e.target.closest('tr');
        const precio = parseFloat(tr.querySelector('.precio').value) || 0;
        const cantidad = parseInt(tr.querySelector('.cantidad').value) || 1;
        tr.querySelector('.subtotal').textContent = `$${formatearNumero(precio * cantidad)}`;
        calcularTotal();
    }

    // Calcular total general
    function calcularTotal() {
        let total = 0;
        document.querySelectorAll('.subtotal').forEach(td => {
            total += parseFloat(td.textContent.replace('$', '')) || 0;
        });
        totalCompra.textContent = formatearNumero(total);
    }

    // Función para escapar HTML
    function escapeHtml(unsafe) {
        return unsafe?.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;") || '';
    }
});
</script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>