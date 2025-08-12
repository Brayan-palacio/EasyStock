<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Entradas de Inventario - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Procesar formulario de entrada
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $producto_id = (int)$_POST['producto_id'];
        $cantidad = (int)$_POST['cantidad'];
        $motivo = trim(filter_input(INPUT_POST, 'motivo', FILTER_SANITIZE_SPECIAL_CHARS));
        $proveedor_id = !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null;

        // Validaciones
        if ($cantidad <= 0) throw new Exception("La cantidad debe ser mayor a 0");
        if (empty($motivo)) throw new Exception("Debe especificar un motivo");

        // Registrar entrada
        $conexion->begin_transaction();

        // 1. Actualizar stock
        $sql_update = "UPDATE productos SET cantidad = cantidad + ? WHERE id = ?";
        $stmt = $conexion->prepare($sql_update);
        $stmt->bind_param("ii", $cantidad, $producto_id);
        $stmt->execute();

        // 2. Registrar movimiento en Kardex
        $sql_movimiento = "INSERT INTO movimientos 
                          (producto_id, tipo, cantidad, motivo, usuario_id, proveedor_id) 
                          VALUES (?, 'entrada', ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql_movimiento);
        $stmt->bind_param("iisii", $producto_id, $cantidad, $motivo, $_SESSION['id_usuario'], $proveedor_id);
        $stmt->execute();

         $conexion->commit();

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => 'Entrada registrada y stock actualizado correctamente'
        ];
        header("Location: entradas_inventario.php");
        exit();

    } catch (Exception $e) {
        $conexion->rollback();
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => 'Error: ' . $e->getMessage()
        ];
    }
}

// Obtener productos y proveedores
$productos = $conexion->query("SELECT id, descripcion FROM productos ORDER BY descripcion")->fetch_all(MYSQLI_ASSOC);
$proveedores = $conexion->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
?>

<?php if (isset($_SESSION['mensaje'])): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= $_SESSION['mensaje']['tipo'] ?> alert-dismissible fade show">
            <?= $_SESSION['mensaje']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php unset($_SESSION['mensaje']); ?>
<?php endif; ?>

<!-- Estilo consistente con tu sistema -->
<style>
    .card-entradas {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header-entradas {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
    }
    .is-valid {
    border-color: #28a745 !important;
    box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
}
.is-invalid {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
}
.list-group-item-action:hover {
    background-color: #f8f9fa;
}
</style>

<div class="container-fluid py-4">
    <?php include 'inventario_navbar.php'; ?>
    <div class="card card-entradas">
        <div class="card-header card-header-entradas">
            <h4 class="mb-0"><i class="fas fa-arrow-down me-2"></i>Registrar Entrada de Inventario</h4>
        </div>
        
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
    <label class="form-label">Producto <span class="text-danger">*</span></label>
    <!-- Cambiamos el select por un input con búsqueda -->
     <div class="input-group">
    <input type="text" 
           id="buscar-producto" 
           class="form-control" 
           placeholder="Escribe código de barras o descripción..."
           autocomplete="off">
    <input type="hidden" name="producto_id" id="producto_id" required>
    <span class="input-group-text"><i class="fas fa-search"></i></span>
    </div>
    <!-- Resultados de búsqueda -->
    <div id="resultados-producto" class="list-group mt-2" style="display:none; max-height: 300px; overflow-y: auto;"></div>
    <small class="text-muted">Escribe al menos 3 caracteres</small>
</div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Proveedor</label>
                        <select name="proveedor_id" class="form-select">
    <option value="">Sin proveedor</option>
    <?php foreach ($proveedores as $prov): ?>
        <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
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
                        <input type="text" name="motivo" class="form-control" 
                               placeholder="Ej: Compra #123, Donación, Ajuste..." required>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save me-1"></i> Registrar Entrada
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Historial de entradas recientes -->
    <div class="card mt-4">
        <div class="card-header card-header-entradas">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Últimas Entradas Registradas</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Proveedor</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_entradas = "SELECT m.*, p.descripcion as producto, 
                                         prov.nombre as proveedor, u.nombre as usuario
                                         FROM movimientos m
                                         JOIN productos p ON m.producto_id = p.id
                                         LEFT JOIN proveedores prov ON m.proveedor_id = prov.id
                                         JOIN usuarios u ON m.usuario_id = u.id
                                         WHERE m.tipo = 'entrada'
                                         ORDER BY m.fecha DESC LIMIT 10";
                        $entradas = $conexion->query($sql_entradas);
                        
                        while ($entrada = $entradas->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($entrada['fecha'])) ?></td>
                                <td><?= htmlspecialchars($entrada['producto']) ?></td>
                                <td class="text-success">+<?= $entrada['cantidad'] ?></td>
                                <td><?= htmlspecialchars($entrada['motivo']) ?></td>
                                <td><?= $entrada['proveedor'] ?? 'N/A' ?></td>
                                <td><?= htmlspecialchars($entrada['usuario']) ?></td>
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
    let timeoutBusqueda = null;
    let codigoTemporal = '';
    let timeoutCodigo = null;

    // 1. Configuración del Lector de Códigos de Barras
    buscarProducto.addEventListener('keydown', function(e) {
        // Solo si el campo está vacío o es numérico
        if (/^\d*$/.test(this.value)) {
            clearTimeout(timeoutCodigo);
            codigoTemporal += e.key;
            
            // Resetear después de 100ms (tiempo entre caracteres de un escaneo)
            timeoutCodigo = setTimeout(() => {
                if (codigoTemporal.length >= 8) { // Mínimo para un código válido
                    procesarCodigoBarras(codigoTemporal);
                }
                codigoTemporal = '';
            }, 100);
        }
    });

    // 2. Función para procesar código de barras
    function procesarCodigoBarras(codigo) {
        fetch(`ajax/buscar_productos.php?codigo=${encodeURIComponent(codigo)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 1) {
                    const producto = data[0];
                    buscarProducto.value = producto.descripcion;
                    productoIdInput.value = producto.id;
                    resultadosProducto.style.display = 'none';
                    
                    // Auto-seleccionar cantidad para agilizar
                    document.querySelector('[name="cantidad"]').focus();
                    
                    // Feedback visual
                    buscarProducto.classList.add('is-valid');
                    setTimeout(() => buscarProducto.classList.remove('is-valid'), 2000);
                } else {
                    buscarProducto.classList.add('is-invalid');
                }
            });
    }

    // 3. Búsqueda normal por texto (descripción)
    buscarProducto.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        buscarProducto.classList.remove('is-valid', 'is-invalid');
        
        const query = this.value.trim();
        
        if (query.length < 3) {
            resultadosProducto.style.display = 'none';
            return;
        }

        // Si es un código numérico largo, posiblemente sea un código de barras
        if (/^\d{8,}$/.test(query)) {
            procesarCodigoBarras(query);
            return;
        }

        timeoutBusqueda = setTimeout(() => {
            fetch(`ajax/buscar_productos.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => mostrarResultados(data));
        }, 300);
    });

    // 4. Mostrar resultados de búsqueda
    function mostrarResultados(data) {
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
                item.addEventListener('click', () => {
                    buscarProducto.value = producto.descripcion;
                    productoIdInput.value = producto.id;
                    resultadosProducto.style.display = 'none';
                    document.querySelector('[name="cantidad"]').focus();
                });
                resultadosProducto.appendChild(item);
            });
        }
        resultadosProducto.style.display = 'block';
    }

    // 5. Ocultar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!buscarProducto.contains(e.target) && !resultadosProducto.contains(e.target)) {
            resultadosProducto.style.display = 'none';
        }
    });

    // 6. Tecla ESC para limpiar
    buscarProducto.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            productoIdInput.value = '';
            resultadosProducto.style.display = 'none';
        }
    });
});
</script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>