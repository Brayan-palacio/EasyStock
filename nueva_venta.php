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
                 ORDER BY nombre ASC";
$clientes = $conexion->query($sql_clientes);

echo '
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="card p-4 shadow-sm" style="border-top: 4px solid #2a5a46;">
        <h2 class="mb-4 text-center" style="color: #1a3a2f;">Ventas</h2>
        
        <!-- Modal para mensajes -->
        <div class="modal fade" id="mensajeModal" tabindex="-1" aria-labelledby="mensajeModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #1a3a2f; color: white;">
                        <h5 class="modal-title" id="mensajeModalLabel">Mensaje</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="mensajeModalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de búsqueda y cliente -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="busqueda" class="form-control" 
                           placeholder="Buscar producto o escanear código de barras" autofocus>
                    <button class="btn" id="btn-buscar" style="background-color: #1a3a2f; color: white;">
                        <i class="fa fa-search"></i>
                    </button>
                </div>
                <ul id="resultados-busqueda" class="list-group mt-1" style="display: none;"></ul>
            </div>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <select class="form-select" id="cliente" name="cliente_id" required>
                        <option value="0">Seleccione un cliente</option>
                        <?php while($cliente = $clientes->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($cliente['id'], ENT_QUOTES) ?>">
                                <?= htmlspecialchars($cliente['nombre']) ?> - 
                                <?= htmlspecialchars($cliente['identificacion']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="button" class="btn" style="background-color: #1a3a2f; color: white;" 
                            data-bs-toggle="modal" data-bs-target="#nuevoClienteModal">
                        <i class="fas fa-plus"></i> Nuevo
                    </button>
                </div>
            </div>
        </div>

<!-- Modal para nuevo cliente -->
<div class="modal fade" id="nuevoClienteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white py-3"style="background:linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white;">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-user-plus me-2"></i> Nuevo Cliente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="alert-container"></div>
                
                <form id="form-nuevo-cliente">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="row g-3">
                        <!-- Campo Nombre -->
                        <div class="col-md-6">
                            <label for="modal-nombre" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modal-nombre" name="nombre" required maxlength="100">
                            <div class="invalid-feedback" id="nombre-error"></div>
                        </div>
                        
                        <!-- Campo Identificación -->
                        <div class="col-md-6">
                            <label for="modal-identificacion" class="form-label">Identificación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modal-identificacion" name="identificacion" required maxlength="20">
                            <div class="invalid-feedback" id="identificacion-error"></div>
                        </div>
                        
                        <!-- Campo Email -->
                        <div class="col-md-6">
                            <label for="modal-email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="modal-email" name="email" maxlength="100">
                            <div class="invalid-feedback" id="email-error"></div>
                        </div>
                        
                        <!-- Campo Teléfono -->
                        <div class="col-md-6">
                            <label for="modal-telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="modal-telefono" name="telefono" maxlength="20">
                            <div class="invalid-feedback" id="telefono-error"></div>
                        </div>
                        
                        <!-- Campo Dirección -->
                        <div class="col-12">
                            <label for="modal-direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="modal-direccion" name="direccion" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="guardarCliente()">
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
                <input type="hidden" id="cliente_id" name="cliente_id" value="0">
                
                <table class="table table-hover">
                    <thead style="background-color: #1a3a2f; color: white;">
                        <tr>
                            <th>Producto</th>
                            <th>Precio Unitario</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="productos-lista"></tbody>
                </table>

                <!-- Sección de métodos de pago -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="forma_pago" class="form-label">Forma de Pago</label>
                        <select class="form-select" id="forma_pago" name="forma_pago" required>
                            <option value="contado">Contado</option>
                            <option value="credito">Crédito</option>
                            <option value="mixto">Mixto</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="estado_pago" class="form-label">Estado</label>
                        <select class="form-select" id="estado_pago" name="estado_pago" required>
                            <option value="pagada">Pagada</option>
                            <option value="pendiente">Pendiente</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Total Venta</label>
                        <div class="form-control" style="background-color: #f8f9fa; font-weight: bold;">
                            $<span id="display-total-venta">0</span>
                        </div>
                    </div>
                </div>

                <!-- Sección de pagos -->
                <div id="seccion-pagos" class="mb-3">
                    <div class="card p-3 mb-3">
                        <h5 class="mb-3">Pagos</h5>
                        
                        <div class="row pago-item">
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
                                <input type="text" class="form-control monto-pago" 
                                       pattern="[0-9]+([,][0-9]{1,2})?" 
                                       oninput="this.value = this.value.replace(/[^0-9,]/g, '')" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Referencia</label>
                                <input type="text" class="form-control referencia-pago" name="referencias_pago[]">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-eliminar-pago" style="display: none;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" id="btn-agregar-pago" class="btn btn-secondary mb-3">
                        <i class="fas fa-plus me-2"></i>Agregar Pago
                    </button>
                </div>

                <!-- Sección de crédito -->
                <div id="seccion-credito" class="mb-3" style="display: none;">
                    <div class="card p-3">
                        <h5 class="mb-3">Detalles de Crédito</h5>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <label for="dias_credito" class="form-label">Días de Crédito</label>
                                <input type="number" class="form-control" id="dias_credito" name="dias_credito" min="1" value="30">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="fecha_vencimiento" class="form-label">Fecha Vencimiento</label>
                                <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="monto_credito" class="form-label">Monto a Crédito</label>
                                <input type="number" class="form-control" id="monto_credito" name="monto_credito" step="0.01" min="0" value="0" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total y botón para finalizar venta -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <h4 style="color: #1a3a2f;">Total: $<span id="total-venta">0</span></h4>
                    <button type="submit" class="btn" style="background-color: #2a5a46; color: white;">
                        <i class="fas fa-check-circle me-2"></i> Finalizar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Constantes y elementos del DOM
const productos = <?= json_encode($resultado_productos->fetch_all(MYSQLI_ASSOC), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const listaProductos = document.getElementById('productos-lista');
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
        error: 'fa-exclamation-triangle',
        success: 'fa-check-circle',
        info: 'fa-info-circle'
    };
    
    mensajeModalBody.classList.add(tipo === 'error' ? 'text-danger' : tipo === 'success' ? 'text-success' : '');
    mensajeModalLabel.textContent = tipo === 'error' ? 'Error' : tipo === 'success' ? 'Éxito' : 'Mensaje';
    mensajeModalBody.innerHTML = `<i class="fas ${iconos[tipo]} me-2"></i> ${escapeHtml(mensaje)}`;
    
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

    document.getElementById('total-venta').textContent = formatearNumero(totalVenta);
    document.getElementById('display-total-venta').textContent = formatearNumero(totalVenta);
    return totalVenta;
}

function crearFilaProducto(producto, cantidadDisponible) {
    const precioFormateado = producto.precio_formateado || 
                           (producto.precio_venta ? 
                            new Intl.NumberFormat('es-CO').format(producto.precio_venta) : 
                            '0');
    
    const fila = document.createElement('tr');
    fila.innerHTML = `
        <td>${escapeHtml(producto.descripcion || producto.nombre)} ${producto.codigo_barras ? `(Código: ${escapeHtml(producto.codigo_barras)})` : ''}</td>
        <td>$${precioFormateado}</td>
        <td>
            <input type="number" class="form-control cantidad" value="1" min="1" max="${cantidadDisponible}" data-product-id="${producto.id}">
        </td>
        <td class="subtotal">$${precioFormateado}</td>
        <td><input type="date" class="form-control" value="<?= date('Y-m-d') ?>" readonly></td>
        <td>
            <button type="button" class="btn btn-danger eliminar" style="background-color: #dc3545;">
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
    });

    btnEliminar.addEventListener('click', () => {
        fila.classList.add('fade-out');
        setTimeout(() => {
            fila.remove();
            actualizarTotal();
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

// Función para validar el formulario antes de enviar
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

// Función para mostrar errores en campos específicos
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

// Función para mostrar errores del servidor en el formulario
function mostrarErroresFormulario(errors) {
    for (const [field, message] of Object.entries(errors)) {
        const campo = document.querySelector(`[name="${field}"]`);
        if (campo) {
            mostrarErrorCampo(campo, message);
        }
    }
}

// Función para agregar el nuevo cliente al select
function agregarClienteAlSelect(cliente) {
    if (!cliente || !cliente.id || !cliente.nombre || !cliente.identificacion) {
        console.error('Datos del cliente incompletos:', cliente);
        return;
    }

    // CORRECCIÓN: Usar el ID correcto ('cliente' en lugar de 'selectCliente')
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

    // Insertar después de la opción "Seleccione un cliente" (índice 0)
    if (selectCliente.options.length > 0) {
        selectCliente.insertBefore(option, selectCliente.options[1]);
    } else {
        selectCliente.appendChild(option);
    }

    // Actualizar también el campo oculto si existe
    const inputClienteId = document.getElementById('cliente_id');
    if (inputClienteId) {
        inputClienteId.value = cliente.id;
    }

    // Forzar actualización del select (necesario para algunos frameworks)
    selectCliente.dispatchEvent(new Event('change'));
}


// Función mejorada para mostrar mensajes (asumiendo que usas SweetAlert2)
function mostrarMensajeSwal(title, text, icon, timer = null, showConfirmButton = true) {
    return Swal.fire({
        title,
        text,
        icon,
        timer,
        showConfirmButton,
        timerProgressBar: timer ? true : false,
        didOpen: (toast) => {
            if (timer) {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        }
    });
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
    
    // Auto-ajustar para pago al contado
    if (formaPago.value === 'contado') {
        const primerPago = document.querySelector('.monto-pago');
        if (primerPago) {
            primerPago.value = totalVenta.toFixed(2).replace('.', ',');
            sumaPagos = totalVenta;
        }
    }
    
    // Actualizar displays
    document.getElementById('display-total-venta').textContent = formatearNumero(totalVenta);
    document.getElementById('monto_credito').value = (totalVenta - sumaPagos).toFixed(2);
}

async function guardarVenta(event) {
    event.preventDefault();
    const btnSubmit = event.target.querySelector('button[type="submit"]');
    btnSubmit.disabled = true;
    
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
            
            // Forzar exactitud en pago al contado
            if (formaPago.value === 'contado' && montoInput === document.querySelector('.pago-item:first-child .monto-pago')) {
                montoInput.value = totalVenta.toFixed(2).replace('.', ',');
                sumaPagos = totalVenta;
            } else {
                sumaPagos += monto;
            }
            
            pagos.push({
                metodo: item.querySelector('.metodo-pago').value,
                monto: monto,
                referencia: item.querySelector('.referencia-pago').value || ''
            });
        });

        // 3. Validación estricta pero con manejo de decimales
        if (formaPago.value === 'contado' && Math.abs(sumaPagos - totalVenta) > 0.001) {
            throw new Error(`La suma de pagos (${sumaPagos.toFixed(2)}) debe ser exactamente igual al total (${totalVenta.toFixed(2)})`);
        }

        // 4. Enviar datos al servidor
        mostrarMensajeSwal('Procesando venta...', '', 'info');
        
        const formData = new FormData(document.getElementById('form-venta'));
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
            item.classList.add('list-group-item');
            item.textContent = 'No se encontraron productos';
            resultadosBusqueda.appendChild(item);
        } else {
            productos.forEach(producto => {
                const item = document.createElement('li');
                item.classList.add('list-group-item', 'list-group-item-action');
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <span>${escapeHtml(producto.descripcion || producto.nombre)}</span>
                        <small class="text-muted">$${producto.precio_formateado || formatearNumero(producto.precio_venta || 0)}</small>
                    </div>
                    ${producto.codigo_barras ? `<small class="text-muted">Código: ${escapeHtml(producto.codigo_barras)}</small>` : ''}
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
        nuevoPago.remove();
        actualizarMontos();
    });
    
    // Resetear valores
    nuevoPago.querySelector('.monto-pago').value = '';
    nuevoPago.querySelector('.referencia-pago').value = '';
    
    // Evento para actualizar montos cuando cambia
    nuevoPago.querySelector('.monto-pago').addEventListener('change', actualizarMontos);
    
    pagoContainer.appendChild(nuevoPago);
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
</script>

<style>
    .fade-out {
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    #resultados-busqueda {
        max-height: 300px;
        overflow-y: auto;
        position: absolute;
        width: calc(100% - 30px);
        z-index: 1000;
    }
    
    #resultados-busqueda .list-group-item {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    #resultados-busqueda .list-group-item:hover {
        background-color: #f8f9fa;
    }
    
    .table th {
        font-weight: 600;
    }
    
    .btn {
        transition: all 0.2s ease;
    }
    
    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .swal2-popup {
        font-family: inherit;
    }
    
    .swal2-confirm {
        background-color: #2a5a46 !important;
    }
    
    .swal2-title {
        color: #1a3a2f;
    }
    
    #cliente {
        flex-grow: 1;
    }
    
    td {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    
    .monto-pago {
        text-align: right;
    }
</style>

<?php include_once 'includes/footer.php'; ?>