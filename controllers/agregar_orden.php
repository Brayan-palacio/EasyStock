<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Nueva Orden - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos y sesión
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Debes iniciar sesión para crear órdenes'
    ];
    header("Location: login.php");
    exit();
}
// Verificar permisos (solo admin y mecánicos pueden ver vehículos)
$rolesPermitidos = ['Admin', 'mecanico', 'supervisor']; // Agregar más roles si es necesario
if (!in_array($_SESSION['rol'], $rolesPermitidos)) {
    header("Location: acceso_denegado.php");
    exit();
}
// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Clase para manejar errores
class OrdenException extends Exception {}

// Obtener datos para el formulario con consultas preparadas
function obtenerDatosIniciales($conexion) {
    $datos = [];
    
    // Obtener clientes
    $stmt = $conexion->prepare("SELECT id, nombre, telefono FROM clientes ORDER BY nombre");
    $stmt->execute();
    $datos['clientes'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Obtener técnicos
    $stmt = $conexion->prepare("SELECT id, nombre FROM usuarios ORDER BY nombre");
    $stmt->execute();
    $datos['tecnicos'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Obtener productos activos (limitado a 200 para rendimiento)
    $stmt = $conexion->prepare("SELECT id, codigo_barras, descripcion, precio_venta, cantidad 
                               FROM productos WHERE activo = 1 ORDER BY descripcion LIMIT 200");
    $stmt->execute();
    $datos['productos'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Obtener servicios activos
    $stmt = $conexion->prepare("SELECT id, codigo, descripcion, precio 
                               FROM servicios WHERE estado = 'Activo' ORDER BY descripcion");
    $stmt->execute();
    $datos['servicios'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return $datos;
}

$datosIniciales = obtenerDatosIniciales($conexion);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new OrdenException("Token de seguridad inválido");
        }

        // Validar datos básicos
        $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        
        if (!$cliente_id) {
            throw new OrdenException("Seleccione un cliente válido");
        }

        $tecnico_id = filter_input(INPUT_POST, 'tecnico_id', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'default' => null]
        ]);

        $fecha_entrega = null;
        if (!empty($_POST['fecha_entrega'])) {
            $fecha_entrega = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['fecha_entrega']);
            if (!$fecha_entrega) {
                throw new OrdenException("Formato de fecha inválido");
            }
            $fecha_entrega = $fecha_entrega->format('Y-m-d H:i:s');
        }

        $observaciones = filter_input(INPUT_POST, 'observaciones', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

        // Validar items de la orden
        if (empty($_POST['items'])) {
            throw new OrdenException("La orden debe tener al menos un item");
        }

        $items = json_decode($_POST['items'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new OrdenException("Error al procesar los items de la orden");
        }

        // Calcular total y validar items
        $total = 0;
        $productosActualizar = [];
        
        foreach ($items as $item) {
            if (!isset($item['tipo'], $item['id'], $item['precio'])) {
                throw new OrdenException("Datos de item incompletos");
            }
            
            if ($item['tipo'] === 'producto') {
                if (!isset($item['cantidad']) || $item['cantidad'] < 1) {
                    throw new OrdenException("Cantidad inválida para producto");
                }
                
                $total += $item['cantidad'] * $item['precio'];
                
                // Acumular productos para actualizar stock
                if (!isset($productosActualizar[$item['id']])) {
                    $productosActualizar[$item['id']] = 0;
                }
                $productosActualizar[$item['id']] += $item['cantidad'];
                
            } elseif ($item['tipo'] === 'servicio') {
                $total += $item['precio'];
            } else {
                throw new OrdenException("Tipo de item desconocido");
            }
        }

        if ($total <= 0) {
            throw new OrdenException("El total de la orden debe ser mayor a cero");
        }

        // Iniciar transacción
        $conexion->begin_transaction();

        try {
            // Insertar orden principal
            $query = "INSERT INTO ordenes 
                     (cliente_id, tecnico_id, fecha_entrega, total, observaciones, creado_por) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param("iisdsi", $cliente_id, $tecnico_id, $fecha_entrega, $total, $observaciones, $_SESSION['id_usuario']);
            
            if (!$stmt->execute()) {
                throw new OrdenException("Error al crear la orden: " . $conexion->error);
            }

            $orden_id = $conexion->insert_id;

            // Insertar items de la orden y actualizar stock
            foreach ($items as $item) {
                if ($item['tipo'] === 'producto') {
                    // Verificar stock antes de insertar
                    $stmt = $conexion->prepare("SELECT cantidad FROM productos WHERE id = ? FOR UPDATE");
                    $stmt->bind_param("i", $item['id']);
                    $stmt->execute();
                    $stock = $stmt->get_result()->fetch_assoc()['cantidad'];
                    
                    if ($stock < $item['cantidad']) {
                        throw new OrdenException("No hay suficiente stock para el producto: " . $item['descripcion']);
                    }
                    
                    // Insertar detalle
                    $query = "INSERT INTO orden_detalles 
                              (orden_id, producto_id, descripcion, cantidad, precio_unitario) 
                              VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conexion->prepare($query);
                    $stmt->bind_param("iisid", $orden_id, $item['id'], $item['descripcion'], $item['cantidad'], $item['precio']);
                    
                    if (!$stmt->execute()) {
                        throw new OrdenException("Error al agregar productos a la orden");
                    }
                    
                } elseif ($item['tipo'] === 'servicio') {
                    $query = "INSERT INTO orden_detalles 
                              (orden_id, servicio_id, descripcion, cantidad, precio_unitario) 
                              VALUES (?, ?, ?, 1, ?)";
                    $stmt = $conexion->prepare($query);
                    $stmt->bind_param("iisd", $orden_id, $item['id'], $item['descripcion'], $item['precio']);
                    
                    if (!$stmt->execute()) {
                        throw new OrdenException("Error al agregar servicios a la orden");
                    }
                }
            }
            
            // Actualizar stock de productos
            foreach ($productosActualizar as $producto_id => $cantidad) {
                $stmt = $conexion->prepare("UPDATE productos SET cantidad = cantidad - ? WHERE id = ?");
                $stmt->bind_param("ii", $cantidad, $producto_id);
                
                if (!$stmt->execute()) {
                    throw new OrdenException("Error al actualizar el stock");
                }
            }

            // Confirmar transacción
            $conexion->commit();

            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Orden #' . str_pad($orden_id, 5, '0', STR_PAD_LEFT) . ' creada exitosamente'
            ];
            header("Location: ver_orden.php?id=$orden_id");
            exit();

        } catch (Exception $e) {
            $conexion->rollback();
            throw $e;
        }

    } catch (OrdenException $e) {
        $error = $e->getMessage();
        error_log("Error al crear orden: " . $error);
    }
}
?>

<style>
    .card-form {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .card-header {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
    }
    .search-box {
        position: relative;
    }
    .search-box i {
        position: absolute;
        top: 50%;
        left: 15px;
        transform: translateY(-50%);
        color: #6c757d;
    }
    .search-input {
        padding-left: 40px;
    }
    .item-list {
        max-height: 400px;
        overflow-y: auto;
    }
    .item-card {
        border-left: 4px solid #1a3a2f;
        transition: all 0.3s;
    }
    .item-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .total-display {
        font-size: 1.5rem;
        font-weight: bold;
        color: #1a3a2f;
    }
    .tab-content {
        padding: 20px;
        border-left: 1px solid #dee2e6;
        border-right: 1px solid #dee2e6;
        border-bottom: 1px solid #dee2e6;
        border-radius: 0 0 8px 8px;
    }
    .badge-producto {
        background-color: #0d6efd;
    }
    .badge-servicio {
        background-color: #198754;
    }
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    .loading-spinner {
        color: white;
        font-size: 3rem;
    }
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card card-form">
                <div class="card-header text-center py-3">
                    <h3 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Nueva Orden de Servicio</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form id="form-orden" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="items" id="items-orden" value="">

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select class="form-select select2-busqueda" id="cliente_id" name="cliente_id" required>
                                    <option value="">Seleccionar cliente...</option>
                                    <?php foreach ($datosIniciales['clientes'] as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>" 
                                            <?= (isset($_POST['cliente_id'])) && $_POST['cliente_id'] == $cliente['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cliente['nombre']) ?> (<?= htmlspecialchars($cliente['telefono']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="tecnico_id" class="form-label">Técnico Asignado</label>
                                <select class="form-select select2-basico" id="tecnico_id" name="tecnico_id">
                                    <option value="">Seleccionar técnico...</option>
                                    <?php foreach ($datosIniciales['tecnicos'] as $tecnico): ?>
                                        <option value="<?= $tecnico['id'] ?>" <?= (isset($_POST['tecnico_id'])) && $_POST['tecnico_id'] == $tecnico['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tecnico['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_entrega" class="form-label">Fecha Estimada de Entrega</label>
                                <input type="datetime-local" class="form-control" id="fecha_entrega" name="fecha_entrega"
                                       min="<?= date('Y-m-d\TH:i') ?>" 
                                       value="<?= isset($_POST['fecha_entrega']) ? htmlspecialchars($_POST['fecha_entrega']) : '' ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="1"><?= 
                                    isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : '' 
                                ?></textarea>
                            </div>
                        </div>

                        <div class="mb-4">
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="productos-tab" data-bs-toggle="tab" data-bs-target="#productos" type="button" role="tab">
                                        <i class="fas fa-boxes me-1"></i> Productos
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="servicios-tab" data-bs-toggle="tab" data-bs-target="#servicios" type="button" role="tab">
                                        <i class="fas fa-tools me-1"></i> Servicios
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="myTabContent">
                                <!-- Pestaña de Productos -->
                                <div class="tab-pane fade show active" id="productos" role="tabpanel">
                                    <div class="search-box mb-3">
                                        <i class="fas fa-search"></i>
                                        <input type="text" class="form-control search-input" id="buscar-producto" placeholder="Buscar producto...">
                                    </div>
                                    
                                    <div class="item-list">
                                        <div class="row">
                                            <?php foreach ($datosIniciales['productos'] as $producto): ?>
                                                <div class="col-md-6 mb-3 producto-item" 
                                                     data-id="<?= $producto['id'] ?>" 
                                                     data-descripcion="<?= htmlspecialchars($producto['descripcion']) ?>"
                                                     data-precio="<?= number_format($producto['precio_venta'], 2, '.', '') ?>"
                                                     data-stock="<?= $producto['cantidad'] ?>">
                                                    <div class="card item-card h-100">
                                                        <div class="card-body">
                                                            <h6 class="card-title"><?= htmlspecialchars($producto['descripcion']) ?></h6>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <span class="badge bg-secondary me-2"><?= htmlspecialchars($producto['codigo_barras']) ?></span>
                                                                    <span class="text-muted stock-display">Stock: <?= $producto['cantidad'] ?></span>
                                                                </div>
                                                                <span class="fw-bold precio-display">$<?= number_format($producto['precio_venta'], 2) ?></span>
                                                            </div>
                                                            <div class="mt-2 d-flex align-items-center">
                                                                <input type="number" class="form-control form-control-sm cantidad-producto" 
                                                                       min="1" max="<?= $producto['cantidad'] ?>" value="1" style="width: 70px;">
                                                                <button class="btn btn-sm btn-success ms-2 agregar-item">
                                                                    <i class="fas fa-plus"></i> Agregar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Pestaña de Servicios -->
                                <div class="tab-pane fade" id="servicios" role="tabpanel">
                                    <div class="search-box mb-3">
                                        <i class="fas fa-search"></i>
                                        <input type="text" class="form-control search-input" id="buscar-servicio" placeholder="Buscar servicio...">
                                    </div>
                                    
                                    <div class="item-list">
                                        <div class="row">
                                            <?php foreach ($datosIniciales['servicios'] as $servicio): ?>
                                                <div class="col-md-6 mb-3 servicio-item" 
                                                     data-id="<?= $servicio['id'] ?>" 
                                                     data-descripcion="<?= htmlspecialchars($servicio['descripcion']) ?>"
                                                     data-precio="<?= number_format($servicio['precio'], 2, '.', '') ?>">
                                                    <div class="card item-card h-100">
                                                        <div class="card-body">
                                                            <h6 class="card-title"><?= htmlspecialchars($servicio['descripcion']) ?></h6>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="badge bg-secondary"><?= htmlspecialchars($servicio['codigo']) ?></span>
                                                                <span class="fw-bold precio-display">$<?= number_format($servicio['precio'], 2) ?></span>
                                                            </div>
                                                            <div class="mt-2 text-end">
                                                                <button class="btn btn-sm btn-success agregar-item">
                                                                    <i class="fas fa-plus"></i> Agregar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items agregados -->
                        <div class="mb-4">
                            <h5 class="mb-3"><i class="fas fa-list-check me-2"></i> Items en la Orden</h5>
                            <div class="table-responsive">
                                <table class="table" id="tabla-items">
                                    <thead>
                                        <tr>
                                            <th width="50">#</th>
                                            <th>Descripción</th>
                                            <th width="100">Tipo</th>
                                            <th width="120">Cantidad</th>
                                            <th width="150">Precio Unitario</th>
                                            <th width="150">Subtotal</th>
                                            <th width="50"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-agregados">
                                        <tr id="sin-items">
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fas fa-info-circle me-2"></i> No hay items agregados
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5" class="text-end fw-bold">Subtotal:</td>
                                            <td id="subtotal-display">$0.00</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-end fw-bold">IVA (16%):</td>
                                            <td id="iva-display">$0.00</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-end fw-bold">Total:</td>
                                            <td class="total-display">$0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="ordenes.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success px-4" id="btn-guardar" disabled>
                                <i class="fas fa-save me-1"></i> Guardar Orden
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading overlay -->
<div class="loading-overlay" id="loading-overlay">
    <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Inicializar Select2
    $('.select2-basico').select2({
        placeholder: "Seleccionar...",
        width: '100%'
    });
    
    $('.select2-busqueda').select2({
        placeholder: "Buscar cliente...",
        minimumInputLength: 2,
        width: '100%'
    });

    const itemsOrden = [];
    let contadorItems = 0;

    // Función para actualizar el total y el input hidden
    function actualizarOrden() {
        let subtotal = 0;
        const tablaBody = document.getElementById('items-agregados');
        const sinItems = document.getElementById('sin-items');
        
        // Limpiar tabla
        tablaBody.innerHTML = '';
        
        if (itemsOrden.length === 0) {
            tablaBody.appendChild(sinItems);
            document.getElementById('btn-guardar').disabled = true;
            document.getElementById('subtotal-display').textContent = '$0.00';
            document.getElementById('iva-display').textContent = '$0.00';
            document.querySelector('.total-display').textContent = '$0.00';
            document.getElementById('items-orden').value = '';
            return;
        }
        
        // Llenar tabla con items
        itemsOrden.forEach((item, index) => {
            const subtotalItem = item.tipo === 'producto' ? item.cantidad * item.precio : item.precio;
            subtotal += subtotalItem;
            
            const row = document.createElement('tr');
            row.dataset.index = index;
            row.innerHTML = `
                <td>${index + 1}</td>
                <td>
                    <strong>${item.descripcion}</strong>
                    ${item.tipo === 'producto' ? 
                     `<small class="d-block text-muted">Stock disponible: ${item.stockDisponible}</small>` : ''}
                </td>
                <td>
                    <span class="badge ${item.tipo === 'producto' ? 'badge-producto' : 'badge-servicio'}">
                        ${item.tipo === 'producto' ? 'Producto' : 'Servicio'}
                    </span>
                </td>
                <td>
                    ${item.tipo === 'producto' ? 
                     `<input type="number" min="1" max="${item.stockDisponible}" value="${item.cantidad}" 
                      class="form-control form-control-sm actualizar-cantidad" style="width: 70px;">` : 
                     '1'}
                </td>
                <td>$${item.precio.toFixed(2)}</td>
                <td>$${subtotalItem.toFixed(2)}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger eliminar-item">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            tablaBody.appendChild(row);
        });
        
        // Calcular impuestos y total
        const iva = subtotal * 0.16;
        const total = subtotal + iva;
        
        // Actualizar totales
        document.getElementById('subtotal-display').textContent = `$${subtotal.toFixed(2)}`;
        document.getElementById('iva-display').textContent = `$${iva.toFixed(2)}`;
        document.querySelector('.total-display').textContent = `$${total.toFixed(2)}`;
        document.getElementById('items-orden').value = JSON.stringify(itemsOrden);
        document.getElementById('btn-guardar').disabled = false;
        
        // Agregar eventos a los nuevos elementos
        document.querySelectorAll('.actualizar-cantidad').forEach(input => {
            input.addEventListener('change', function() {
                const index = this.closest('tr').dataset.index;
                const nuevaCantidad = parseInt(this.value);
                
                if (nuevaCantidad < 1) {
                    this.value = 1;
                    return;
                }
                
                if (nuevaCantidad > itemsOrden[index].stockDisponible) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Stock insuficiente',
                        text: `La cantidad no puede ser mayor a ${itemsOrden[index].stockDisponible}`,
                    });
                    this.value = itemsOrden[index].stockDisponible;
                    return;
                }
                
                itemsOrden[index].cantidad = parseInt(this.value);
                actualizarOrden();
            });
        });
        
        document.querySelectorAll('.eliminar-item').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = this.closest('tr').dataset.index;
                itemsOrden.splice(index, 1);
                actualizarOrden();
            });
        });
    }

    // Agregar productos/servicios
    document.querySelectorAll('.agregar-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.producto-item, .servicio-item');
            const tipo = card.classList.contains('producto-item') ? 'producto' : 'servicio';
            
            const nuevoItem = {
                id: parseInt(card.dataset.id),
                tipo: tipo,
                descripcion: card.dataset.descripcion,
                precio: parseFloat(card.dataset.precio),
                stockDisponible: tipo === 'producto' ? parseInt(card.dataset.stock) : 0
            };
            
            if (tipo === 'producto') {
                const inputCantidad = card.querySelector('.cantidad-producto');
                nuevoItem.cantidad = parseInt(inputCantidad.value);
                
                // Validar cantidad
                if (isNaN(nuevoItem.cantidad) || nuevoItem.cantidad < 1) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Cantidad inválida',
                        text: 'Ingrese una cantidad válida',
                    });
                    return;
                }
                
                if (nuevoItem.cantidad > nuevoItem.stockDisponible) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Stock insuficiente',
                        text: `Solo hay ${nuevoItem.stockDisponible} unidades disponibles`,
                    });
                    return;
                }
                
                // Verificar si el producto ya está en la orden
                const itemExistente = itemsOrden.findIndex(item => 
                    item.tipo === 'producto' && item.id === nuevoItem.id
                );
                
                if (itemExistente !== -1) {
                    // Actualizar cantidad si no excede el stock
                    const nuevaCantidadTotal = itemsOrden[itemExistente].cantidad + nuevoItem.cantidad;
                    
                    if (nuevaCantidadTotal > nuevoItem.stockDisponible) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Stock insuficiente',
                            text: `No hay suficiente stock para agregar ${nuevoItem.cantidad} unidades más`,
                        });
                        return;
                    }
                    
                    itemsOrden[itemExistente].cantidad = nuevaCantidadTotal;
                } else {
                    itemsOrden.push(nuevoItem);
                }
                
                // Resetear input
                inputCantidad.value = 1;
                
            } else {
                // Para servicios, solo agregar si no existe
                const existe = itemsOrden.some(item => 
                    item.tipo === 'servicio' && item.id === nuevoItem.id
                );
                
                if (!existe) {
                    nuevoItem.cantidad = 1;
                    itemsOrden.push(nuevoItem);
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Servicio ya agregado',
                        text: 'Este servicio ya está en la orden',
                    });
                    return;
                }
            }
            
            actualizarOrden();
        });
    });

    // Búsqueda en tiempo real
    document.getElementById('buscar-producto').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('.producto-item').forEach(item => {
            const descripcion = item.dataset.descripcion.toLowerCase();
            item.style.display = descripcion.includes(searchTerm) ? 'block' : 'none';
        });
    });
    
    document.getElementById('buscar-servicio').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('.servicio-item').forEach(item => {
            const descripcion = item.dataset.descripcion.toLowerCase();
            item.style.display = descripcion.includes(searchTerm) ? 'block' : 'none';
        });
    });

    // Validación del formulario
    document.getElementById('form-orden').addEventListener('submit', function(e) {
        if (itemsOrden.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Orden vacía',
                text: 'Debe agregar al menos un item a la orden',
            });
            return;
        }
        
        // Mostrar loading
        document.getElementById('loading-overlay').style.display = 'flex';
        
        // Validar cliente seleccionado
        const clienteSelect = document.getElementById('cliente_id');
        if (!clienteSelect.value) {
            e.preventDefault();
            document.getElementById('loading-overlay').style.display = 'none';
            Swal.fire({
                icon: 'error',
                title: 'Cliente requerido',
                text: 'Debe seleccionar un cliente para la orden',
            });
            clienteSelect.focus();
        }
    });
    
    // Validar cantidad al cambiar en productos
    document.querySelectorAll('.cantidad-producto').forEach(input => {
        input.addEventListener('change', function() {
            const max = parseInt(this.max);
            const value = parseInt(this.value);
            
            if (isNaN(value) || value < 1) {
                this.value = 1;
            } else if (value > max) {
                this.value = max;
            }
        });
    });
});
</script>