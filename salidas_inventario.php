<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Salidas de Inventario - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $producto_id = (int)$_POST['producto_id'];
        $cantidad = (int)$_POST['cantidad'];
        $motivo = trim(filter_input(INPUT_POST, 'motivo', FILTER_SANITIZE_SPECIAL_CHARS));
        $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;

        // Validaciones
        if ($cantidad <= 0) throw new Exception("La cantidad debe ser mayor a 0");
        if (empty($motivo)) throw new Exception("Debe especificar un motivo");

        // Verificar stock disponible
        $stock_actual = $conexion->query("SELECT cantidad FROM productos WHERE id = $producto_id")->fetch_assoc()['cantidad'];
        if ($stock_actual < $cantidad) throw new Exception("Stock insuficiente (Disponible: $stock_actual)");

        // Registrar salida (transacción)
        $conexion->begin_transaction();

        // 1. Actualizar stock
        $conexion->query("UPDATE productos SET cantidad = cantidad - $cantidad WHERE id = $producto_id");

        // 2. Registrar movimiento
        $sql = "INSERT INTO movimientos 
                (producto_id, tipo, cantidad, motivo, usuario_id, cliente_id) 
                VALUES (?, 'salida', ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("iisii", $producto_id, $cantidad, $motivo, $_SESSION['id_usuario'], $cliente_id);
        $stmt->execute();

        $conexion->commit();

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => 'Salida registrada y stock actualizado correctamente'
        ];
        header("Location: salidas_inventario.php");
        exit();

    } catch (Exception $e) {
        $conexion->rollback();
        $error = $e->getMessage();
    }
}

// Obtener datos para formulario
$productos = $conexion->query("SELECT id, descripcion, cantidad FROM productos WHERE cantidad > 0 ORDER BY descripcion")->fetch_all(MYSQLI_ASSOC);
$clientes = $conexion->query("SELECT id, nombre FROM clientes ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Estilo consistente con tu sistema -->
<style>
    .card-salidas {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header-salidas {
        background: linear-gradient(135deg, #8B0000 0%, #FF0000 100%);
        color: white;
    }
    .stock-disponible {
        font-size: 0.85rem;
        color: #6c757d;
    }
</style>

<div class="container py-4">
    <div class="card card-salidas">
        <div class="card-header card-header-salidas">
            <h4 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Registrar Salida de Inventario</h4>
        </div>
        
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="form-salida">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Producto <span class="text-danger">*</span></label>
                        <input type="text" id="buscar-producto" class="form-control" 
                               placeholder="Escribe código de barras o descripción..." autocomplete="off">
                        <input type="hidden" name="producto_id" id="producto_id" required>
                        <div id="resultados-producto" class="list-group mt-2" style="display:none;"></div>
                        <div id="info-producto" class="mt-2" style="display:none;">
                            <span class="stock-disponible">
                                <i class="fas fa-boxes me-1"></i> Stock disponible: <span id="stock-actual">0</span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Cliente (Opcional)</label>
                        <select name="cliente_id" class="form-select">
                            <option value="">Seleccionar cliente...</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" name="cantidad" class="form-control" min="1" required>
                    </div>
                    
                    <div class="col-md-8">
                        <label class="form-label">Motivo <span class="text-danger">*</span></label>
                        <select name="motivo" class="form-select" required>
                            <option value="">Seleccionar motivo...</option>
                            <option value="Venta">Venta</option>
                            <option value="Ajuste de inventario">Ajuste de inventario</option>
                            <option value="Dañado">Dañado</option>
                            <option value="Donación">Donación</option>
                            <option value="Uso interno">Uso interno</option>
                        </select>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <button type="reset" class="btn btn-outline-secondary me-3">
                        <i class="fas fa-eraser me-1"></i> Limpiar
                    </button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="fas fa-check-circle me-1"></i> Registrar Salida
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Historial de salidas recientes -->
    <div class="card mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Últimas Salidas Registradas</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Cliente</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT m.*, p.descripcion as producto, 
                               c.nombre as cliente, u.nombre as usuario
                               FROM movimientos m
                               JOIN productos p ON m.producto_id = p.id
                               LEFT JOIN clientes c ON m.cliente_id = c.id
                               JOIN usuarios u ON m.usuario_id = u.id
                               WHERE m.tipo = 'salida'
                               ORDER BY m.fecha DESC LIMIT 10";
                        $salidas = $conexion->query($sql);
                        
                        while ($salida = $salidas->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($salida['fecha'])) ?></td>
                                <td><?= htmlspecialchars($salida['producto']) ?></td>
                                <td class="text-danger">-<?= $salida['cantidad'] ?></td>
                                <td><?= htmlspecialchars($salida['motivo']) ?></td>
                                <td><?= $salida['cliente'] ?? 'N/A' ?></td>
                                <td><?= htmlspecialchars($salida['usuario']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript para búsqueda dinámica y lector de códigos -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Configuración idéntica a entradas_inventario.php
    const buscarProducto = document.getElementById('buscar-producto');
    const resultadosProducto = document.getElementById('resultados-producto');
    const productoIdInput = document.getElementById('producto_id');
    const infoProducto = document.getElementById('info-producto');
    const stockActual = document.getElementById('stock-actual');
    let timeoutBusqueda = null;
    let codigoTemporal = '';
    let timeoutCodigo = null;

    // Función para procesar código de barras (igual que en entradas)
    function procesarCodigoBarras(codigo) {
        fetch(`ajax/buscar_productos.php?codigo=${encodeURIComponent(codigo)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 1) {
                    seleccionarProducto(data[0]);
                } else {
                    buscarProducto.classList.add('is-invalid');
                }
            });
    }

    // Función para seleccionar producto
    function seleccionarProducto(producto) {
        buscarProducto.value = producto.descripcion;
        productoIdInput.value = producto.id;
        resultadosProducto.style.display = 'none';
        infoProducto.style.display = 'block';
        stockActual.textContent = producto.cantidad;
        
        // Auto-enfocar cantidad y establecer máximo
        const inputCantidad = document.querySelector('[name="cantidad"]');
        inputCantidad.focus();
        inputCantidad.max = producto.cantidad;
    }

    // Búsqueda dinámica (igual que en entradas)
    buscarProducto.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        buscarProducto.classList.remove('is-valid', 'is-invalid');
        
        const query = this.value.trim();
        
        if (query.length < 3 && !/^\d{8,}$/.test(query)) {
            resultadosProducto.style.display = 'none';
            return;
        }

        timeoutBusqueda = setTimeout(() => {
            if (/^\d{8,}$/.test(query)) {
                procesarCodigoBarras(query);
            } else {
                fetch(`ajax/buscar_productos.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        resultadosProducto.innerHTML = '';
                        
                        if (data.length === 0) {
                            resultadosProducto.innerHTML = `
                                <div class="list-group-item text-muted">
                                    <i class="fas fa-exclamation-circle me-2"></i> No se encontraron productos
                                </div>`;
                        } else {
                            data.forEach(producto => {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'list-group-item list-group-item-action';
                                item.innerHTML = `
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">${producto.descripcion}</h6>
                                            <small class="text-muted">Código: ${producto.codigo_barras || 'N/A'}</small>
                                        </div>
                                        <span class="badge bg-${producto.cantidad > 0 ? 'success' : 'danger'}">
                                            ${producto.cantidad} en stock
                                        </span>
                                    </div>
                                `;
                                item.addEventListener('click', () => seleccionarProducto(producto));
                                resultadosProducto.appendChild(item);
                            });
                        }
                        resultadosProducto.style.display = 'block';
                    });
            }
        }, 300);
    });

    // Validación de cantidad en tiempo real
    document.querySelector('[name="cantidad"]').addEventListener('input', function() {
        const max = parseInt(this.max);
        if (parseInt(this.value) > max) {
            this.value = max;
        }
    });
});
</script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>