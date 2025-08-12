<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Ajustes de Inventario - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos (solo administradores/inventario)
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] != 'Administrador' && $_SESSION['rol_usuario'] != 'Inventario')) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres permisos de administrador o inventario'
    ];
    header("Location: index.php");
    exit();
}

// Función para registrar ajustes en el Kardex
function registrarAjuste($conexion, $producto_id, $tipo, $cantidad, $motivo, $usuario_id) {
    $sql = "INSERT INTO movimientos 
            (producto_id, tipo, cantidad, motivo, usuario_id, fecha) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conexion->prepare($sql);
    $tipo_mov = $tipo === 'entrada' ? 'ajuste_positivo' : 'ajuste_negativo';
    $stmt->bind_param("isisi", $producto_id, $tipo_mov, $cantidad, $motivo, $usuario_id);
    return $stmt->execute();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $producto_id = (int)$_POST['producto_id'];
        $tipo_ajuste = $_POST['tipo_ajuste'];
        $cantidad = (int)$_POST['cantidad'];
        $motivo = trim(filter_input(INPUT_POST, 'motivo', FILTER_SANITIZE_SPECIAL_CHARS));
        
        // Validaciones
        if ($cantidad <= 0) throw new Exception("La cantidad debe ser mayor a 0");
        if (empty($motivo)) throw new Exception("Debe especificar un motivo");
        
        // Iniciar transacción
        $conexion->begin_transaction();
        
        // 1. Actualizar stock según el tipo de ajuste
        $operador = ($tipo_ajuste === 'entrada') ? '+' : '-';
        $sql_update = "UPDATE productos SET cantidad = cantidad $operador ? WHERE id = ?";
        $stmt = $conexion->prepare($sql_update);
        $stmt->bind_param("ii", $cantidad, $producto_id);
        $stmt->execute();
        
        // 2. Registrar movimiento en Kardex
        if (!registrarAjuste($conexion, $producto_id, $tipo_ajuste, $cantidad, $motivo, $_SESSION['id_usuario'])) {
            throw new Exception("Error al registrar el ajuste en el Kardex");
        }
        
        $conexion->commit();
        
        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => 'Ajuste registrado y stock actualizado correctamente'
        ];
        header("Location: ajustes_inventario.php");
        exit();
        
    } catch (Exception $e) {
        $conexion->rollback();
        $error = $e->getMessage();
    }
}

// Obtener productos con stock
$productos = $conexion->query("
    SELECT id, descripcion, cantidad, codigo_barras 
    FROM productos 
    ORDER BY descripcion
")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Estilo consistente con tu sistema -->
<style>
    .card-ajustes {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header-ajustes {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        color: white;
    }
    .badge-positivo {
        background-color: #28a745;
    }
    .badge-negativo {
        background-color: #dc3545;
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

<div class="container-fluid py-4">
    <?php include 'inventario_navbar.php'; ?>
    <div class="card card-ajustes">
        <div class="card-header card-header-ajustes">
            <h4 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Ajustes de Inventario</h4>
        </div>
        
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-<?= $_SESSION['mensaje']['tipo'] ?> alert-dismissible fade show">
                    <?= $_SESSION['mensaje']['texto'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['mensaje']); ?>
            <?php endif; ?>

            <form method="POST">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Producto <span class="text-danger">*</span></label>
                        <input type="text" id="buscar-producto" class="form-control" 
                               placeholder="Buscar por código de barras o descripción" autofocus>
                        <input type="hidden" id="producto_id" name="producto_id" required>
                        <div id="resultados-producto" class="list-group mt-2"></div>
                        <div id="info-producto" class="mt-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted stock-disponible">
                                    <i class="fas fa-boxes me-1"></i> Stock actual: <span id="stock-actual">0</span>
                                </span>
                                <span class="text-muted codigo-barras">
                                    Código: <span id="codigo-barras">N/A</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Ajuste <span class="text-danger">*</span></label>
                        <select class="form-select" name="tipo_ajuste" required>
                            <option value="">Seleccionar...</option>
                            <option value="entrada">Entrada (+)</option>
                            <option value="salida">Salida (-)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="cantidad" min="1" required>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <label class="form-label">Motivo del Ajuste <span class="text-danger">*</span></label>
                        <select class="form-select" name="motivo" required>
                            <option value="">Seleccionar motivo...</option>
                            <option value="Ajuste por conteo físico">Ajuste por conteo físico</option>
                            <option value="Merma o deterioro">Merma o deterioro</option>
                            <option value="Robo o pérdida">Robo o pérdida</option>
                            <option value="Donación">Donación</option>
                            <option value="Error en registro">Error en registro</option>
                            <option value="Devolución de cliente">Devolución de cliente</option>
                            <option value="Uso interno">Uso interno</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <label class="form-label">Notas Adicionales</label>
                        <textarea class="form-control" name="notas" rows="2" placeholder="Detalles adicionales del ajuste"></textarea>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="reset" class="btn btn-outline-secondary me-3">
                        <i class="fas fa-eraser me-1"></i> Limpiar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Registrar Ajuste
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Historial de ajustes recientes -->
    <div class="card mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Últimos Ajustes Registrados</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT m.*, p.descripcion as producto, u.nombre as usuario,
                                       DATE_FORMAT(m.fecha, '%d/%m/%Y %H:%i') as fecha_formateada
                                FROM movimientos m
                                JOIN productos p ON m.producto_id = p.id
                                JOIN usuarios u ON m.usuario_id = u.id
                                WHERE m.tipo IN ('ajuste_positivo', 'ajuste_negativo')
                                ORDER BY m.fecha DESC LIMIT 10";
                        $ajustes = $conexion->query($sql);
                        
                        while ($ajuste = $ajustes->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($ajuste['fecha_formateada']) ?></td>
                                <td><?= htmlspecialchars($ajuste['producto']) ?></td>
                                <td>
                                    <span class="badge <?= $ajuste['tipo'] === 'ajuste_positivo' ? 'badge-positivo' : 'badge-negativo' ?>">
                                        <?= $ajuste['tipo'] === 'ajuste_positivo' ? 'Entrada' : 'Salida' ?>
                                    </span>
                                </td>
                                <td><?= $ajuste['cantidad'] ?></td>
                                <td><?= htmlspecialchars($ajuste['motivo']) ?></td>
                                <td><?= htmlspecialchars($ajuste['usuario']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const buscarProducto = document.getElementById('buscar-producto');
    const resultadosProducto = document.getElementById('resultados-producto');
    const productoIdInput = document.getElementById('producto_id');
    const stockActual = document.getElementById('stock-actual');
    const codigoBarras = document.getElementById('codigo-barras');
    let timeoutBusqueda = null;
    let codigoTemporal = '';
    let timeoutCodigo = null;

    // Búsqueda dinámica de productos
    buscarProducto.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        const query = this.value.trim();
        
        if (query.length < 3 && !/^\d{8,}$/.test(query)) {
            resultadosProducto.style.display = 'none';
            return;
        }

        timeoutBusqueda = setTimeout(() => {
            if (/^\d{8,}$/.test(query)) {
                // Buscar por código de barras
                buscarProductoPorCodigo(query);
            } else {
                // Buscar por texto
                fetch(`ajax/buscar_productos.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => mostrarResultadosBusqueda(data));
            }
        }, 300);
    });

    // Función para buscar por código de barras
    function buscarProductoPorCodigo(codigo) {
        fetch(`ajax/buscar_productos.php?codigo=${encodeURIComponent(codigo)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 1) {
                    seleccionarProducto(data[0]);
                } else {
                    mostrarMensaje('Producto no encontrado', 'No existe un producto con ese código de barras', 'error');
                }
            });
    }

    // Mostrar resultados de búsqueda
    function mostrarResultadosBusqueda(productos) {
        resultadosProducto.innerHTML = '';
        
        if (productos.length === 0) {
            const item = document.createElement('div');
            item.className = 'list-group-item text-muted';
            item.textContent = 'No se encontraron productos';
            resultadosProducto.appendChild(item);
        } else {
            productos.forEach(producto => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${escapeHtml(producto.descripcion || producto.nombre)}</h6>
                            <small class="text-muted">Stock: ${producto.cantidad}</small>
                        </div>
                        ${producto.codigo_barras ? `<small class="text-muted">Código: ${escapeHtml(producto.codigo_barras)}</small>` : ''}
                    </div>
                `;
                item.addEventListener('click', () => {
                    seleccionarProducto(producto);
                });
                resultadosProducto.appendChild(item);
            });
        }
        resultadosProducto.style.display = 'block';
    }

    // Seleccionar producto
    function seleccionarProducto(producto) {
        buscarProducto.value = producto.descripcion || producto.nombre;
        productoIdInput.value = producto.id;
        stockActual.textContent = producto.cantidad;
        codigoBarras.textContent = producto.codigo_barras || 'N/A';
        resultadosProducto.style.display = 'none';
    }

    // Ocultar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!buscarProducto.contains(e.target) && !resultadosProducto.contains(e.target)) {
            resultadosProducto.style.display = 'none';
        }
    });

    // Detección de código de barras
    buscarProducto.addEventListener('keydown', function(e) {
        if (/^\d*$/.test(this.value)) {
            clearTimeout(timeoutCodigo);
            codigoTemporal += e.key;
            
            timeoutCodigo = setTimeout(() => {
                if (codigoTemporal.length >= 8) {
                    buscarProductoPorCodigo(codigoTemporal);
                }
                codigoTemporal = '';
            }, 100);
        }
    });

    // Función para escapar HTML
    function escapeHtml(unsafe) {
        return unsafe?.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;") || '';
    }

    // Función para mostrar mensajes
    function mostrarMensaje(titulo, mensaje, tipo) {
        const alerta = document.createElement('div');
        alerta.className = `alert alert-${tipo} alert-dismissible fade show`;
        alerta.innerHTML = `
            <strong>${titulo}</strong> ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.querySelector('.card-body').prepend(alerta);
    }
});
</script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>