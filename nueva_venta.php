<?php
$tituloPagina = 'Agregar venta - EasyStock';
session_start();

// Configuración de seguridad de headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: frame-ancestors 'none'");

// Verificación robusta de sesión
if (empty($_SESSION['id_usuario']) || !is_numeric($_SESSION['id_usuario'])) {
    session_regenerate_id(true);
    session_destroy();
    header("Location: login.php");
    exit();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'config/conexion.php';
date_default_timezone_set('America/Bogota');

// Consulta preparada para productos
$sql_productos = "SELECT id, nombre, categoria_id, precio_venta, cantidad, 
                         descripcion, fecha_creacion, codigo_barras,
                         FORMAT(precio_venta, 2, 'es_CO') AS precio_formateado
                  FROM productos 
                  WHERE descripcion IS NOT NULL";

$stmt = $conexion->prepare($sql_productos);
$stmt->execute();
$resultado_productos = $stmt->get_result();

// Consulta para clientes
$sql_clientes = "SELECT id, nombre, identificacion, telefono, direccion 
                FROM clientes 
                ORDER BY CASE WHEN id = 1 THEN 0 ELSE 1 END, nombre ASC";
$clientes = $conexion->query($sql_clientes);

echo '
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <?php include 'ventas_navbar.php'; ?>
    <div class="card p-4 shadow-sm" style="border-top: 4px solid #2a5a46;">
        <h2 class="mb-4 text-center" style="color: #1a3a2f;">
            <i class="fas fa-cash-register me-2"></i> Registrar Nueva Venta
        </h2>
        
        <!-- Modal para mensajes -->
        <div class="modal fade" id="mensajeModal" tabindex="-1" aria-labelledby="mensajeModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="mensajeModalLabel">
                            <i class="fas fa-info-circle me-2"></i> Mensaje
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="mensajeModalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de búsqueda y cliente -->
        <div class="row mb-4 g-3">
            <div class="col-md-6">
                <div class="search-container">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="busqueda" class="form-control border-start-0" 
                               placeholder="Buscar producto o escanear código de barras" autofocus>
                        <button class="btn btn-outline-secondary" id="btn-buscar" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="position-relative">
                        <ul id="resultados-busqueda" class="list-group mt-1 position-absolute w-100 shadow" style="display: none; z-index: 1000;"></ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="customer-selector">
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="fas fa-user text-muted"></i>
                        </span>
                        <select class="form-select" id="cliente" name="cliente_id" required>
                            <option value="1" selected>CLIENTE GENERAL</option>
                            <?php while($cliente = $clientes->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($cliente['id'], ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($cliente['nombre']) ?> - 
                                    <?= htmlspecialchars($cliente['identificacion']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="button" class="btn btn-primary" 
                                data-bs-toggle="modal" data-bs-target="#nuevoClienteModal">
                            <i class="fas fa-plus me-1"></i> Nuevo
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para nuevo cliente -->
        <div class="modal fade" id="nuevoClienteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header text-white py-3" style="background: linear-gradient(135deg, #2a5a46 0%, #3a7a66 100%);">
                        <h5 class="modal-title fw-bold">
                            <i class="fas fa-user-plus me-2"></i> Nuevo Cliente
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="alert-container"></div>
                        
                        <form id="form-nuevo-cliente" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row g-3">
                                <!-- Campo Nombre -->
                                <div class="col-md-6">
                                    <label for="modal-nombre" class="form-label">
                                        <i class="fas fa-user me-1"></i> Nombre Completo <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="modal-nombre" name="nombre" required maxlength="100">
                                    <div class="invalid-feedback" id="nombre-error"></div>
                                </div>
                                
                                <!-- Campo Identificación -->
                                <div class="col-md-6">
                                    <label for="modal-identificacion" class="form-label">
                                        <i class="fas fa-id-card me-1"></i> Identificación <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="modal-identificacion" name="identificacion" required maxlength="20">
                                    <div class="invalid-feedback" id="identificacion-error"></div>
                                </div>
                                
                                <!-- Campo Email -->
                                <div class="col-md-6">
                                    <label for="modal-email" class="form-label">
                                        <i class="fas fa-envelope me-1"></i> Email
                                    </label>
                                    <input type="email" class="form-control" id="modal-email" name="email" maxlength="100">
                                    <div class="invalid-feedback" id="email-error"></div>
                                </div>
                                
                                <!-- Campo Teléfono -->
                                <div class="col-md-6">
                                    <label for="modal-telefono" class="form-label">
                                        <i class="fas fa-phone me-1"></i> Teléfono
                                    </label>
                                    <input type="tel" class="form-control" id="modal-telefono" name="telefono" maxlength="20">
                                    <div class="invalid-feedback" id="telefono-error"></div>
                                </div>
                                
                                <!-- Campo Dirección -->
                                <div class="col-12">
                                    <label for="modal-direccion" class="form-label">
                                        <i class="fas fa-map-marker-alt me-1"></i> Dirección
                                    </label>
                                    <textarea class="form-control" id="modal-direccion" name="direccion" rows="2"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-success" onclick="guardarCliente()">
                            <i class="fas fa-save me-1"></i> Guardar Cliente
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de productos seleccionados -->
        <div class="table-responsive">
            <form id="form-venta">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" id="cliente_id" name="cliente_id" value="1">
                
                <table class="table table-hover align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th width="30%">Producto</th>
                            <th width="15%">Precio Unitario</th>
                            <th width="15%">Cantidad</th>
                            <th width="15%">Subtotal</th>
                            <th width="15%">Fecha</th>
                            <th width="10%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="productos-lista" class="border-top-0">
                        <tr id="empty-state" class="text-center text-muted py-4">
                            <td colspan="6">
                                <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                                <p class="mb-0">No hay productos agregados</p>
                                <small>Busca y agrega productos para comenzar</small>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Sección de métodos de pago -->
                <div class="row mb-3 g-3">
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body">
                                <label for="forma_pago" class="form-label fw-bold">
                                    <i class="fas fa-credit-card me-1"></i> Forma de Pago
                                </label>
                                <select class="form-select" id="forma_pago" name="forma_pago" required>
                                    <option value="contado">Contado</option>
                                    <option value="credito">Crédito</option>
                                    <option value="mixto">Mixto</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body">
                                <label for="estado_pago" class="form-label fw-bold">
                                    <i class="fas fa-info-circle me-1"></i> Estado
                                </label>
                                <select class="form-select" id="estado_pago" name="estado_pago" required>
                                    <option value="pagada">Pagada</option>
                                    <option value="pendiente">Pendiente</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm border-0 bg-light">
                            <div class="card-body text-center">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-receipt me-1"></i> Total Venta
                                </label>
                                <div class="display-6 fw-bold text-success">
                                    $<span id="display-total-venta">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección de pagos -->
                <div id="seccion-pagos" class="mb-3">
                    <div class="card p-3 mb-3 border-0 shadow-sm">
                        <h5 class="mb-3">
                            <i class="fas fa-money-bill-wave me-2"></i> Pagos
                        </h5>
                        
                        <div class="row pago-item mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Método</label>
                                <select class="form-select metodo-pago" name="metodos_pago[]" required>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Monto</label>
                                <input type="text" class="form-control monto-pago text-end" 
                                       placeholder="0,00"
                                       pattern="[0-9]+([,][0-9]{1,2})?" 
                                       oninput="this.value = this.value.replace(/[^0-9,]/g, '')" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Referencia</label>
                                <input type="text" class="form-control referencia-pago" name="referencias_pago[]">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger btn-eliminar-pago" style="display: none;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Campo "Paga con" para calcular vuelto -->
                        <div class="row mt-3">
                            <div class="col-md-4 offset-md-8">
                                <div class="input-group mb-3">
                                    <span class="input-group-text bg-light">
                                        <i class="fas fa-money-bill me-1"></i> Paga con
                                    </span>
                                    <input type="text" class="form-control text-end" id="paga-con" 
                                           placeholder="0,00" 
                                           oninput="this.value = this.value.replace(/[^0-9,]/g, '')">
                                    <button type="button" class="btn btn-outline-secondary" onclick="calcularVuelto()">
                                        <i class="fas fa-calculator me-1"></i> Calcular
                                    </button>
                                </div>
                                <div class="alert alert-info mb-0" id="vuelto-container" style="display: none;">
                                    <i class="fas fa-exchange-alt me-2"></i>
                                    <strong>Vuelto:</strong> $<span id="vuelto">0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" id="btn-agregar-pago" class="btn btn-outline-primary mb-3">
                        <i class="fas fa-plus-circle me-2"></i> Agregar Pago
                    </button>
                </div>

                <!-- Sección de crédito -->
                <div id="seccion-credito" class="mb-3" style="display: none;">
                    <div class="card p-3 border-0 shadow-sm">
                        <h5 class="mb-3">
                            <i class="fas fa-calendar-alt me-2"></i> Detalles de Crédito
                        </h5>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="dias_credito" class="form-label">
                                    <i class="fas fa-clock me-1"></i> Días de Crédito
                                </label>
                                <input type="number" class="form-control" id="dias_credito" name="dias_credito" min="1" value="30">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="fecha_vencimiento" class="form-label">
                                    <i class="fas fa-calendar-day me-1"></i> Fecha Vencimiento
                                </label>
                                <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="monto_credito" class="form-label">
                                    <i class="fas fa-money-bill-wave me-1"></i> Monto a Crédito
                                </label>
                                <input type="text" class="form-control text-end" id="monto_credito" name="monto_credito" value="0,00" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resumen de la compra -->
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i> Resumen de la Compra
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2">
                                    <i class="fas fa-boxes me-2"></i> Productos
                                </h6>
                                <div id="resumen-productos" class="mb-3" style="max-height: 200px; overflow-y: auto;">
                                    <p class="text-muted text-center py-3">
                                        <i class="fas fa-shopping-cart me-2"></i> No hay productos agregados
                                    </p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2">
                                    <i class="fas fa-money-bill-wave me-2"></i> Totales y Pagos
                                </h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span>$<span id="resumen-subtotal">0.00</span></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total:</span>
                                    <span class="fw-bold">$<span id="resumen-total">0.00</span></span>
                                </div>
                                
                                <div id="resumen-pagos" class="mt-3">
                                    <h6 class="border-bottom pb-2">
                                        <i class="fas fa-credit-card me-2"></i> Métodos de Pago
                                    </h6>
                                    <p class="text-muted text-center py-3">
                                        <i class="fas fa-money-bill-alt me-2"></i> No se han registrado pagos
                                    </p>
                                </div>
                                
                                <div id="resumen-vuelto" class="mt-3 alert alert-info mb-0" style="display: none;">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold">
                                            <i class="fas fa-exchange-alt me-2"></i> Vuelto:
                                        </span>
                                        <span>$<span id="resumen-vuelto-monto">0.00</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total y botón para finalizar venta -->
                <div class="d-flex justify-content-between align-items-center mt-4 p-3 bg-light rounded">
                    <div>
                        <h3 class="m-0 text-dark">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Total: 
                            <span class="text-success">$<span id="total-venta">0.00</span></span>
                        </h3>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-success btn-lg px-4 py-2 shadow-sm">
                            <i class="fas fa-check-circle me-2"></i> Finalizar Venta
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Constantes y elementos del DOM
const productos = <?= json_encode($resultado_productos->fetch_all(MYSQLI_ASSOC), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const listaProductos = document.getElementById('productos-lista');
const emptyState = document.getElementById('empty-state');
const busqueda = document.getElementById('busqueda');
const resultadosBusqueda = document.getElementById('resultados-busqueda');
const selectCliente = document.getElementById('cliente');
const inputClienteId = document.getElementById('cliente_id');
const formaPago = document.getElementById('forma_pago');
const estadoPago = document.getElementById('estado_pago');
const seccionPagos = document.getElementById('seccion-pagos');
const btnAgregarPago = document.getElementById('btn-agregar-pago');
const seccionCredito = document.getElementById('seccion-credito');
const diasCredito = document.getElementById('dias_credito');
const fechaVencimiento = document.getElementById('fecha_vencimiento');
const montoCredito = document.getElementById('monto_credito');

// Utilidades
function escapeHtml(unsafe) {
    return unsafe?.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;") || '';
}

function mostrarMensajeSwal(title, text, icon, timer = null) {
    const config = {
        icon,
        title,
        text,
        confirmButtonColor: '#2a5a46'
    };
    if (timer) config.timer = timer;
    return Swal.fire(config);
}

function mostrarMensajeModal(mensaje, tipo = 'info') {
    const mensajeModalBody = document.getElementById('mensajeModalBody');
    const mensajeModalLabel = document.getElementById('mensajeModalLabel');
    
    mensajeModalBody.className = 'modal-body';
    
    const iconos = {
        error: 'fa-exclamation-triangle text-danger',
        success: 'fa-check-circle text-success',
        info: 'fa-info-circle text-primary',
        warning: 'fa-exclamation-circle text-warning'
    };
    
    mensajeModalLabel.textContent = tipo === 'error' ? 'Error' : tipo === 'success' ? 'Éxito' : 'Mensaje';
    mensajeModalBody.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${iconos[tipo]} fa-2x me-3"></i>
            <div>${escapeHtml(mensaje)}</div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('mensajeModal')).show();
}

function formatearNumero(num) {
    return new Intl.NumberFormat('es-CO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

// Funciones de negocio
function actualizarTotal() {
    let totalVenta = 0;
    document.querySelectorAll('.subtotal').forEach(subtotalElem => {
        const subtotalTexto = subtotalElem.textContent.replace(/[^\d,]/g, '').replace(',', '.');
        const subtotal = parseFloat(subtotalTexto) || 0;
        if (!isNaN(subtotal)) {
            totalVenta += subtotal;
        }
    });

    const totalFormateado = formatearNumero(totalVenta);
    document.getElementById('total-venta').textContent = totalFormateado;
    document.getElementById('display-total-venta').textContent = totalFormateado;
    
    // Mostrar/ocultar empty state
    const filasProductos = document.querySelectorAll('#productos-lista tr:not(#empty-state)');
    if (filasProductos.length > 0) {
        emptyState.style.display = 'none';
    } else {
        emptyState.style.display = '';
    }
    
    return totalVenta;
}

function crearFilaProducto(producto, cantidadDisponible) {
    const precioFormateado = producto.precio_formateado || 
                           (producto.precio_venta ? 
                            new Intl.NumberFormat('es-CO').format(producto.precio_venta) : 
                            '0,00');
    
    const fila = document.createElement('tr');
    fila.className = 'fade-in';
    fila.innerHTML = `
        <td>
            <div class="fw-bold">${escapeHtml(producto.descripcion || producto.nombre)}</div>
            ${producto.codigo_barras ? `<small class="text-muted">Código: ${escapeHtml(producto.codigo_barras)}</small>` : ''}
        </td>
        <td class="text-end">$${precioFormateado}</td>
        <td>
            <input type="number" class="form-control cantidad" value="1" min="1" max="${cantidadDisponible}" data-product-id="${producto.id}">
        </td>
        <td class="text-end subtotal">$${precioFormateado}</td>
        <td><input type="date" class="form-control" value="<?= date('Y-m-d') ?>" readonly></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger eliminar">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
        <input type="hidden" name="productos[]" value="${producto.id}">
        <input type="hidden" name="cantidades[]" value="1">
        <input type="hidden" name="precios[]" value="${producto.precio_venta}">
    `;
    
    return fila;
}

function configurarEventosFila(fila, producto, cantidadDisponible) {
    const inputCantidad = fila.querySelector('.cantidad');
    const btnEliminar = fila.querySelector('.eliminar');
    
    inputCantidad.addEventListener('change', function() {
        const cantidad = Math.max(1, Math.min(cantidadDisponible, parseInt(this.value) || 1));
        this.value = cantidad;

        const precio = parseFloat(producto.precio_venta) || 0;
        const subtotal = cantidad * precio;
        
        fila.querySelector('.subtotal').textContent = `$${subtotal.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        fila.querySelector('input[name="cantidades[]"]').value = cantidad;

        actualizarTotal();
        actualizarResumen();
    });

    btnEliminar.addEventListener('click', () => {
        fila.classList.add('fade-out');
        setTimeout(() => {
            fila.remove();
            actualizarTotal();
            actualizarResumen();
        }, 300);
    });
}

function agregarFila(producto) {
    if (!producto?.id) {
        mostrarMensajeModal('Producto no válido', 'error');
        return;
    }

    const filaExistente = document.querySelector(`input[value='${producto.id}']`);
    const cantidadDisponible = parseInt(producto.cantidad) || 0;

    if (filaExistente) {
        const fila = filaExistente.closest('tr');
        const inputCantidad = fila.querySelector('.cantidad');
        let cantidad = parseInt(inputCantidad.value) || 1;

        if (cantidad < cantidadDisponible) {
            cantidad += 1;
            inputCantidad.value = cantidad;

            const subtotal = cantidad * (parseFloat(producto.precio_venta) || 0);
            fila.querySelector('.subtotal').textContent = `$${new Intl.NumberFormat('es-CO').format(subtotal)}`;
            fila.querySelector('input[name="cantidades[]"]').value = cantidad;

            actualizarTotal();
            actualizarResumen();
        } else {
            mostrarMensajeModal(`No hay suficiente inventario para ${escapeHtml(producto.descripcion || 'este producto')}.`, 'error');
        }
    } else {
        if (cantidadDisponible <= 0) {
            mostrarMensajeModal('Este producto no tiene inventario disponible.', 'error');
            return;
        }

        const fila = crearFilaProducto(producto, cantidadDisponible);
        listaProductos.appendChild(fila);
        configurarEventosFila(fila, producto, cantidadDisponible);
        actualizarTotal();
        actualizarResumen();
    }
}

// Funciones de API
async function buscarProductos(texto) {
    try {
        const response = await fetch(`controllers/ventas/buscar_producto.php?q=${encodeURIComponent(texto)}`);
        if (!response.ok) throw new Error('Error en la respuesta');
        return await response.json();
    } catch (error) {
        console.error('Error en la búsqueda:', error);
        mostrarMensajeModal('Error al buscar productos', 'error');
        return [];
    }
}

async function guardarCliente() {
    const form = document.getElementById('form-nuevo-cliente');
    const formData = new FormData(form);
    
    try {
        // Validar formulario antes de enviar
        if (!validarFormularioCliente()) {
            return;
        }
        
        // Mostrar carga
        mostrarMensajeSwal('Guardando cliente...', '', 'info', null, false);
        
        // Enviar datos
        const response = await fetch('controllers/clientes/guardar_cliente.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => null);
            throw new Error(errorData?.message || `Error HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            // Mostrar errores específicos si existen
            if (data.errors) {
                mostrarErroresFormulario(data.errors);
                throw new Error('Por favor corrige los errores en el formulario');
            }
            throw new Error(data.message || 'Error al guardar el cliente');
        }
        
        // Cerrar modal y mostrar mensaje
        const modal = document.getElementById('nuevoClienteModal');
        const bsModal = bootstrap.Modal.getInstance(modal);
        bsModal.hide();
        
        // Limpieza forzada
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        modal.style.display = 'none';
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.paddingRight = '';
        
        // Mostrar SweetAlert después
        mostrarMensajeSwal('Éxito', 'Cliente guardado', 'success');

        agregarClienteAlSelect(data.cliente);
        
    } catch (error) {
        console.error('Error al guardar cliente:', error);
        mostrarMensajeSwal('Error', error.message, 'error');
    }
}

function validarFormularioCliente() {
    let isValid = true;
    const form = document.getElementById('form-nuevo-cliente');
    
    // Limpiar errores previos
    form.querySelectorAll('.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
        const errorElement = el.nextElementSibling;
        if (errorElement && errorElement.classList.contains('invalid-feedback')) {
            errorElement.textContent = '';
        }
    });
    
    // Validar nombre (requerido, máximo 100 caracteres)
    const nombre = form.querySelector('[name="nombre"]');
    if (!nombre.value.trim()) {
        mostrarErrorCampo(nombre, 'El nombre es obligatorio');
        isValid = false;
    } else if (nombre.value.length > 100) {
        mostrarErrorCampo(nombre, 'El nombre no puede exceder 100 caracteres');
        isValid = false;
    }
    
    // Validar identificación (requerido, máximo 20 caracteres)
    const identificacion = form.querySelector('[name="identificacion"]');
    if (!identificacion.value.trim()) {
        mostrarErrorCampo(identificacion, 'La identificación es obligatoria');
        isValid = false;
    } else if (identificacion.value.length > 20) {
        mostrarErrorCampo(identificacion, 'La identificación no puede exceder 20 caracteres');
        isValid = false;
    }
    
    // Validar email (si está presente)
    const email = form.querySelector('[name="email"]');
    if (email && email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        mostrarErrorCampo(email, 'Ingrese un email válido');
        isValid = false;
    } else if (email && email.value.length > 100) {
        mostrarErrorCampo(email, 'El email no puede exceder 100 caracteres');
        isValid = false;
    }
    
    // Validar teléfono (si está presente)
    const telefono = form.querySelector('[name="telefono"]');
    if (telefono && telefono.value.length > 20) {
        mostrarErrorCampo(telefono, 'El teléfono no puede exceder 20 caracteres');
        isValid = false;
    }
    
    // Enfocar el primer campo con error
    if (!isValid) {
        const firstError = form.querySelector('.is-invalid');
        if (firstError) {
            firstError.focus();
        }
    }
    
    return isValid;
}

function mostrarErrorCampo(campo, mensaje) {
    campo.classList.add('is-invalid');
    let errorElement = campo.nextElementSibling;
    
    if (!errorElement || !errorElement.classList.contains('invalid-feedback')) {
        errorElement = document.createElement('div');
        errorElement.className = 'invalid-feedback';
        campo.parentNode.appendChild(errorElement);
    }
    
    errorElement.textContent = mensaje;
}

function mostrarErroresFormulario(errors) {
    for (const [field, message] of Object.entries(errors)) {
        const campo = document.querySelector(`[name="${field}"]`);
        if (campo) {
            mostrarErrorCampo(campo, message);
        }
    }
}

function agregarClienteAlSelect(cliente) {
    if (!cliente || !cliente.id || !cliente.nombre || !cliente.identificacion) {
        console.error('Datos del cliente incompletos:', cliente);
        return;
    }

    const selectCliente = document.getElementById('cliente');
    
    if (!selectCliente) {
        console.error('Elemento select de cliente no encontrado');
        return;
    }

    // Crear la nueva opción
    const option = document.createElement('option');
    option.value = cliente.id;
    option.textContent = `${cliente.nombre} - ${cliente.identificacion}`;
    option.selected = true;

    // Insertar después de la opción "CLIENTE GENERAL" (índice 0)
    if (selectCliente.options.length > 0) {
        selectCliente.insertBefore(option, selectCliente.options[1]);
    } else {
        selectCliente.appendChild(option);
    }

    // Actualizar también el campo oculto
    const inputClienteId = document.getElementById('cliente_id');
    if (inputClienteId) {
        inputClienteId.value = cliente.id;
    }

    // Forzar actualización del select
    selectCliente.dispatchEvent(new Event('change'));
}

// Funciones para calcular vuelto
function calcularVuelto() {
    const totalVenta = parseFloat(
        document.getElementById('total-venta').textContent
            .replace(/[^\d,]/g, '')
            .replace(',', '.')
    ) || 0;
    
    const pagaCon = parseFloat(
        document.getElementById('paga-con').value
            .replace(/[^\d,]/g, '')
            .replace(',', '.')
    ) || 0;
    
    if (pagaCon > 0) {
        const vuelto = pagaCon - totalVenta;
        const vueltoContainer = document.getElementById('vuelto-container');
        const vueltoElement = document.getElementById('vuelto');
        
        if (vuelto >= 0) {
            vueltoElement.textContent = formatearNumero(vuelto);
            vueltoContainer.style.display = 'block';
            vueltoContainer.className = 'alert alert-info mb-0';
            
            // Autocompletar el primer pago con el monto recibido
            const primerPago = document.querySelector('.monto-pago');
            if (primerPago) {
                primerPago.value = pagaCon.toFixed(2).replace('.', ',');
                actualizarMontos();
            }
        } else {
            vueltoElement.textContent = formatearNumero(0);
            vueltoContainer.style.display = 'block';
            vueltoContainer.className = 'alert alert-warning mb-0';
            vueltoContainer.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Falta:</strong> $${formatearNumero(Math.abs(vuelto))} - 
                El monto es insuficiente
            `;
        }
        
        // Actualizar resumen
        actualizarResumen();
    }
}

function actualizarMontos() {
    const totalVenta = parseFloat(
        document.getElementById('total-venta').textContent
            .replace(/[^\d,]/g, '')
            .replace(',', '.')
    ) || 0;
    
    let sumaPagos = 0;
    document.querySelectorAll('.monto-pago').forEach(input => {
        sumaPagos += parseFloat(
            input.value.replace(/[^\d,]/g, '').replace(',', '.')
        ) || 0;
    });
    
    // Actualizar displays
    document.getElementById('display-total-venta').textContent = formatearNumero(totalVenta);
    document.getElementById('monto_credito').value = (totalVenta - sumaPagos).toFixed(2).replace('.', ',');
    
    // Calcular vuelto si hay un monto en "Paga con"
    const pagaCon = document.getElementById('paga-con').value;
    if (pagaCon && pagaCon.trim() !== '') {
        calcularVuelto();
    }
    
    // Actualizar resumen
    actualizarResumen();
}

// Funciones para el resumen de compra
function actualizarResumen() {
    // Actualizar lista de productos
    const resumenProductos = document.getElementById('resumen-productos');
    resumenProductos.innerHTML = '';
    
    const filasProductos = document.querySelectorAll('#productos-lista tr:not(#empty-state)');
    if (filasProductos.length === 0) {
        resumenProductos.innerHTML = `
            <p class="text-muted text-center py-3">
                <i class="fas fa-shopping-cart me-2"></i> No hay productos agregados
            </p>
        `;
    } else {
        filasProductos.forEach(fila => {
            const producto = fila.querySelector('td:first-child').innerHTML;
            const cantidad = fila.querySelector('.cantidad').value;
            const precio = fila.querySelector('td:nth-child(2)').textContent;
            const subtotal = fila.querySelector('.subtotal').textContent;
            
            const item = document.createElement('div');
            item.className = 'd-flex justify-content-between border-bottom py-2';
            item.innerHTML = `
                <div>
                    <span class="fw-bold">${cantidad}x</span> ${producto}
                </div>
                <div class="text-end">
                    ${subtotal}
                </div>
            `;
            resumenProductos.appendChild(item);
        });
    }
    
    // Actualizar totales
    const totalVenta = parseFloat(
        document.getElementById('total-venta').textContent
            .replace(/[^\d,]/g, '')
            .replace(',', '.')
    ) || 0;
    
    document.getElementById('resumen-total').textContent = formatearNumero(totalVenta);
    document.getElementById('resumen-subtotal').textContent = formatearNumero(totalVenta);
    
    // Actualizar métodos de pago
    const resumenPagos = document.getElementById('resumen-pagos');
    resumenPagos.innerHTML = `
        <h6 class="border-bottom pb-2">
            <i class="fas fa-credit-card me-2"></i> Métodos de Pago
        </h6>
    `;
    
    const pagos = document.querySelectorAll('.pago-item');
    if (pagos.length === 0) {
        resumenPagos.innerHTML += `
            <p class="text-muted text-center py-3">
                <i class="fas fa-money-bill-alt me-2"></i> No se han registrado pagos
            </p>
        `;
    } else {
        pagos.forEach(pago => {
            const metodo = pago.querySelector('.metodo-pago').value;
            const monto = pago.querySelector('.monto-pago').value || '0';
            const referencia = pago.querySelector('.referencia-pago').value || '';
            
            const item = document.createElement('div');
            item.className = 'd-flex justify-content-between border-bottom py-2';
            item.innerHTML = `
                <div>
                    <span class="text-capitalize">${metodo}</span>
                    ${referencia ? `<small class="text-muted d-block">Ref: ${referencia}</small>` : ''}
                </div>
                <div class="text-end">
                    $${monto}
                </div>
            `;
            resumenPagos.appendChild(item);
        });
    }
    
    // Actualizar vuelto si existe
    const vueltoContainer = document.getElementById('resumen-vuelto');
    const pagaCon = document.getElementById('paga-con').value;
    
    if (pagaCon && pagaCon.trim() !== '') {
        const vuelto = parseFloat(
            document.getElementById('vuelto').textContent
                .replace(/[^\d,]/g, '')
                .replace(',', '.')
        ) || 0;
        
        if (vuelto > 0) {
            vueltoContainer.style.display = 'block';
            document.getElementById('resumen-vuelto-monto').textContent = formatearNumero(vuelto);
        } else {
            vueltoContainer.style.display = 'none';
        }
    } else {
        vueltoContainer.style.display = 'none';
    }
}

// Funciones para guardar venta
async function guardarVenta(event) {
    event.preventDefault();
    const btnSubmit = event.target.querySelector('button[type="submit"]');
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...';
    
    try {
        // 1. Obtener y formatear el total de la venta
        const totalVenta = parseFloat(
            document.getElementById('total-venta').textContent
                .replace(/[^\d,]/g, '')
                .replace(',', '.')
        ) || 0;

        // 2. Calcular suma de pagos con precisión
        let sumaPagos = 0;
        const pagos = [];
        
        document.querySelectorAll('.pago-item').forEach((item) => {
            const montoInput = item.querySelector('.monto-pago');
            const monto = parseFloat(
                montoInput.value.replace(/[^\d,]/g, '').replace(',', '.')
            ) || 0;
            
            sumaPagos += monto;
            
            pagos.push({
                metodo: item.querySelector('.metodo-pago').value,
                monto: monto,
                referencia: item.querySelector('.referencia-pago').value || ''
            });
        });

        // 3. Validación básica (sin forzar exactitud)
        if (sumaPagos <= 0) {
            throw new Error('Debe ingresar al menos un pago');
        }

        // 4. Enviar datos al servidor
        mostrarMensajeSwal('Procesando venta...', '', 'info');
        
        const formData = new FormData(document.getElementById('form-venta'));
        formData.append('cliente_id', document.getElementById('cliente_id').value || 0);
        formData.append('pagos', JSON.stringify(pagos));
        formData.append('total', totalVenta.toFixed(2));
        
        const response = await fetch('controllers/ventas/guardar_venta.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Error al procesar la venta');
        
        mostrarMensajeSwal('¡Venta realizada!', 'La venta se ha registrado correctamente', 'success', 3000)
            .then(() => {
                window.location.href = 'ventas.php';
            });
    } catch (error) {
        mostrarMensajeSwal('Error', error.message, 'error');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fas fa-check-circle me-2"></i> Finalizar Venta';
    }
}

// Event Listeners
selectCliente.addEventListener('change', function() {
    inputClienteId.value = this.value;
});

let timeoutBusqueda;
busqueda.addEventListener('input', function() {
    clearTimeout(timeoutBusqueda);
    const texto = this.value.trim();
    
    if (texto.length === 0) {
        resultadosBusqueda.style.display = 'none';
        resultadosBusqueda.innerHTML = '';
        return;
    }

    timeoutBusqueda = setTimeout(async () => {
        const productos = await buscarProductos(texto);
        resultadosBusqueda.innerHTML = '';
        
        if (productos.length === 0) {
            const item = document.createElement('li');
            item.classList.add('list-group-item', 'text-center', 'py-3');
            item.innerHTML = '<i class="fas fa-search me-2"></i> No se encontraron productos';
            resultadosBusqueda.appendChild(item);
        } else {
            productos.forEach(producto => {
                const item = document.createElement('li');
                item.classList.add('list-group-item', 'list-group-item-action');
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold">${escapeHtml(producto.descripcion || producto.nombre)}</div>
                            ${producto.codigo_barras ? `<small class="text-muted">Código: ${escapeHtml(producto.codigo_barras)}</small>` : ''}
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$${producto.precio_formateado || formatearNumero(producto.precio_venta || 0)}</div>
                            <small class="text-muted">Disponible: ${producto.cantidad || 0}</small>
                        </div>
                    </div>
                `;
                item.addEventListener('click', () => {
                    agregarFila(producto);
                    busqueda.value = '';
                    resultadosBusqueda.style.display = 'none';
                    busqueda.focus();
                });
                resultadosBusqueda.appendChild(item);
            });
        }
        resultadosBusqueda.style.display = 'block';
    }, 300);
});

busqueda.addEventListener('keydown', async function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const texto = this.value.trim();
        
        if (texto.length > 0) {
            const productos = await buscarProductos(texto);
            if (productos.length > 0) {
                agregarFila(productos[0]);
                this.value = '';
                resultadosBusqueda.style.display = 'none';
            } else {
                mostrarMensajeModal('Producto no encontrado', 'error');
            }
        }
    }
});

document.addEventListener('click', function(e) {
    if (!busqueda.contains(e.target) && !resultadosBusqueda.contains(e.target)) {
        resultadosBusqueda.style.display = 'none';
    }
});

formaPago.addEventListener('change', function() {
    if (this.value === 'contado') {
        const totalVenta = parseFloat(
            document.getElementById('total-venta').textContent
                .replace(/[^\d,]/g, '')
                .replace(',', '.')
        ) || 0;
        const primerPago = document.querySelector('.monto-pago');
        if (primerPago) {
            primerPago.value = totalVenta.toFixed(2).replace('.', ',');
        }
    }
    
    if (this.value === 'credito' || this.value === 'mixto') {
        seccionCredito.style.display = 'block';
        const fecha = new Date();
        fecha.setDate(fecha.getDate() + parseInt(diasCredito.value));
        fechaVencimiento.valueAsDate = fecha;
        
        if (this.value === 'credito') {
            estadoPago.value = 'pendiente';
            estadoPago.disabled = true;
        } else {
            estadoPago.disabled = false;
        }
    } else {
        seccionCredito.style.display = 'none';
        estadoPago.disabled = false;
    }
    actualizarMontos();
});

diasCredito.addEventListener('change', function() {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() + parseInt(this.value));
    fechaVencimiento.valueAsDate = fecha;
});

btnAgregarPago.addEventListener('click', function() {
    const pagoContainer = seccionPagos.querySelector('.card');
    const nuevoPago = pagoContainer.querySelector('.pago-item').cloneNode(true);
    
    // Habilitar botón de eliminar
    const btnEliminar = nuevoPago.querySelector('.btn-eliminar-pago');
    btnEliminar.style.display = 'block';
    btnEliminar.addEventListener('click', function() {
        nuevoPago.classList.add('fade-out');
        setTimeout(() => {
            nuevoPago.remove();
            actualizarMontos();
        }, 300);
    });
    
    // Resetear valores
    nuevoPago.querySelector('.monto-pago').value = '';
    nuevoPago.querySelector('.referencia-pago').value = '';
    
    // Evento para actualizar montos cuando cambia
    nuevoPago.querySelector('.monto-pago').addEventListener('change', actualizarMontos);
    
    pagoContainer.insertBefore(nuevoPago, pagoContainer.lastElementChild);
    actualizarMontos();
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('monto-pago')) {
        actualizarMontos();
    }
});

// Inicialización
document.getElementById('form-venta').addEventListener('submit', guardarVenta);

// Configurar evento para el primer pago
const primerPago = seccionPagos.querySelector('.pago-item');
primerPago.querySelector('.monto-pago').addEventListener('change', actualizarMontos);

// Observar cambios en el total
const observer = new MutationObserver(actualizarMontos);
observer.observe(document.getElementById('total-venta'), { childList: true });

// Establecer fecha de vencimiento inicial
const fecha = new Date();
fecha.setDate(fecha.getDate() + parseInt(diasCredito.value));
fechaVencimiento.valueAsDate = fecha;

// Inicializar resumen
actualizarResumen();

// Evento para calcular vuelto al perder foco
document.getElementById('paga-con').addEventListener('blur', calcularVuelto);
</script>

<style>
    /* Animaciones */
    .fade-out {
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Estilos generales */
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .card {
        border-radius: 0.5rem;
        border: none;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        transition: box-shadow 0.2s ease;
    }
    
    .card:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .table th {
        font-weight: 600;
        background-color: #f8f9fa;
    }
    
    .table td {
        vertical-align: middle;
    }
    
    /* Barra de búsqueda */
    .search-container {
        position: relative;
    }
    
    #busqueda {
        border-radius: 0.25rem;
    }
    
    #busqueda:focus {
        box-shadow: none;
        border-color: #86b7fe;
    }
    
    /* Resultados de búsqueda */
    #resultados-busqueda {
        max-height: 300px;
        overflow-y: auto;
        border-radius: 0 0 0.5rem 0.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    #resultados-busqueda .list-group-item {
        cursor: pointer;
        border-left: none;
        border-right: none;
        transition: background-color 0.2s;
    }
    
    #resultados-busqueda .list-group-item:first-child {
        border-top: none;
    }
    
    #resultados-busqueda .list-group-item:last-child {
        border-bottom: none;
        border-radius: 0 0 0.5rem 0.5rem;
    }
    
    #resultados-busqueda .list-group-item:hover {
        background-color: #f1f8ff;
    }
    
    /* Selector de cliente */
    .customer-selector .input-group-text {
        border-right: none;
    }
    
    .customer-selector .form-select {
        border-left: none;
    }
    
    /* Input cantidad */
    .cantidad {
        max-width: 80px;
        text-align: center;
    }
    
    /* Botones */
    .btn-success {
        background-color: #2a5a46;
        border-color: #2a5a46;
    }
    
    .btn-success:hover {
        background-color: #1a3a2f;
        border-color: #1a3a2f;
    }
    
    .btn-outline-danger {
        border-color: #dc3545;
        color: #dc3545;
    }
    
    .btn-outline-danger:hover {
        background-color: #dc3545;
        color: white;
    }
    
    /* Efectos hover */
    .btn-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    /* Estilos para el resumen */
    #resumen-productos {
        scrollbar-width: thin;
        scrollbar-color: #ddd #f8f9fa;
    }
    
    #resumen-productos::-webkit-scrollbar {
        width: 5px;
    }
    
    #resumen-productos::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    #resumen-productos::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 5px;
    }
    
    #resumen-productos::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    .resumen-item {
        transition: all 0.2s ease;
    }
    
    .resumen-item:hover {
        background-color: #f8f9fa;
    }
    
    /* Responsividad */
    @media (max-width: 768px) {
        .table-responsive {
            border: none;
        }
        
        .btn-lg {
            padding: 0.5rem 1rem;
            font-size: 1rem;
        }
        
        .pago-item > div {
            margin-bottom: 1rem;
        }
        
        .pago-item > div:last-child {
            margin-bottom: 0;
        }
    }
    
    /* Estilos para inputs */
    .form-control:focus, .form-select:focus {
        border-color: #2a5a46;
        box-shadow: 0 0 0 0.25rem rgba(42, 90, 70, 0.25);
    }
    
    /* Estilos para SweetAlert */
    .swal2-popup {
        font-family: inherit;
        border-radius: 0.5rem;
    }
    
    .swal2-confirm {
        background-color: #2a5a46 !important;
    }
    
    .swal2-title {
        color: #1a3a2f;
    }
    
    /* Estilos para el empty state */
    #empty-state {
        background-color: #f8f9fa;
    }
    
    #empty-state td {
        padding: 2rem;
    }
    
    /* Estilos para los modales */
    .modal-header {
        border-bottom: none;
    }
    
    .modal-footer {
        border-top: none;
    }
</style>

<?php include_once 'includes/footer.php'; ?>