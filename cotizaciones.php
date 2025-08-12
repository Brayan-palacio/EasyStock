<?php
$tituloPagina = 'Crear cotización';
session_start();

// Configuración de seguridad de headers (coherente con login.php)
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Verificación robusta de sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

include 'config/conexion.php';
date_default_timezone_set('America/Bogota');

// Consulta preparada para productos
$sql_productos = "SELECT id, nombre, categoria_id, precio_compra, 
                         precio_venta, cantidad, imagen, descripcion, fecha_creacion,
                         FORMAT(precio_venta, 0, 'es_CO') AS precio_formateado
                  FROM productos 
                  WHERE descripcion IS NOT NULL";

$stmt = $conexion->prepare($sql_productos);
$stmt->execute();
$resultado_productos = $stmt->get_result();

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <?php include 'ventas_navbar.php'; ?>
    
    <div class="card p-4 shadow-sm" style="border-top: 4px solid #d4af37;">
        <h2 class="mb-4 text-center" style="color: #1a3a2f;">Cotizaciones</h2>
        
        <!-- Modal para mensajes - Estilo coherente -->
        <div class="modal fade" id="mensajeModal" tabindex="-1" aria-labelledby="mensajeModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #1a3a2f; color: white;">
                        <h5 class="modal-title" id="mensajeModalLabel">Mensaje</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="mensajeModalBody">
                        <!-- Aquí se mostrará el mensaje -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del cliente -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="cliente" class="form-label">Cliente</label>
                    <input type="text" class="form-control" id="cliente" placeholder="Nombre del cliente">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="contacto" class="form-label">Contacto</label>
                    <input type="text" class="form-control" id="contacto" placeholder="Teléfono o email">
                </div>
            </div>
        </div>

        <!-- Barra de búsqueda - Estilo consistente -->
        <div class="mb-3">
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
        
        <!-- Lista de productos cotizados -->
        <div class="table-responsive">
            <form method="POST" action="controllers/cotizacion/guardar_cotizacion.php" id="form-cotizacion">
                <input type="hidden" name="cliente" id="input-cliente">
                <input type="hidden" name="contacto" id="input-contacto">
                <!-- Token CSRF para seguridad -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <table class="table table-hover">
                    <thead style="background-color: #1a3a2f; color: white;">
                        <tr>
                            <th>Producto</th>
                            <th>Precio Unitario</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="productos-lista">
                        <!-- Aquí se agregarán los productos dinámicamente -->
                    </tbody>
                </table>
                
                <!-- Notas y total -->
                <div class="mb-3">
                    <label for="notas" class="form-label">Notas adicionales</label>
                    <textarea class="form-control" id="notas" name="notas" rows="2" placeholder="Detalles adicionales de la cotización"></textarea>
                </div>
                
                <!-- Total y botones -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <h4 style="color: #1a3a2f;">Total: $<span id="total-cotizacion">0</span></h4>
                    <div>
                        <button type="button" class="btn btn-secondary me-2" id="btn-limpiar">
                            <i class="fas fa-broom me-2"></i>Limpiar
                        </button>
                        <button type="submit" class="btn" style="background-color: #d4af37; color: #1a3a2f;">
                            <i class="fas fa-file-alt me-2"></i>Guardar Cotización
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Datos y configuración inicial
    const productos = <?= json_encode($resultado_productos->fetch_all(MYSQLI_ASSOC), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const listaProductos = document.getElementById('productos-lista');
    const busqueda = document.getElementById('busqueda');
    const resultadosBusqueda = document.getElementById('resultados-busqueda');
    const btnLimpiar = document.getElementById('btn-limpiar');

    // Función para mostrar mensajes en un modal
    function mostrarMensaje(mensaje, tipo = 'info') {
        const mensajeModalBody = document.getElementById('mensajeModalBody');
        const mensajeModalLabel = document.getElementById('mensajeModalLabel');
        
        mensajeModalBody.className = 'modal-body';
        
        if (tipo === 'error') {
            mensajeModalBody.classList.add('text-danger');
            mensajeModalLabel.textContent = 'Error';
        } else if (tipo === 'success') {
            mensajeModalBody.classList.add('text-success');
            mensajeModalLabel.textContent = 'Éxito';
        } else {
            mensajeModalLabel.textContent = 'Mensaje';
        }
        
        mensajeModalBody.innerHTML = `<i class="fas ${tipo === 'error' ? 'fa-exclamation-triangle' : tipo === 'success' ? 'fa-check-circle' : 'fa-info-circle'} me-2"></i> ${mensaje}`;
        
        const mensajeModal = new bootstrap.Modal(document.getElementById('mensajeModal'));
        mensajeModal.show();
    }

    // Función para actualizar el total de la cotización
    function actualizarTotal() {
        let total = 0;
        document.querySelectorAll('.subtotal').forEach(subtotalElem => {
            const subtotalTexto = subtotalElem.textContent.replace(/[^\d,]/g, '').replace(',', '.');
            const subtotal = parseFloat(subtotalTexto) || 0;
            if (!isNaN(subtotal)) {
                total += subtotal;
            }
        });

        document.getElementById('total-cotizacion').textContent = new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(total);
    }

    // Función para agregar o actualizar una fila de producto
    function agregarFila(producto) {
        if (!producto || !producto.id) {
            mostrarMensaje('Producto no válido', 'error');
            return;
        }

        const filaExistente = document.querySelector(`input[value='${producto.id}']`);

        if (filaExistente) {
            const fila = filaExistente.closest('tr');
            const inputCantidad = fila.querySelector('.cantidad');
            let cantidad = parseInt(inputCantidad.value) || 1;
            cantidad += 1;
            inputCantidad.value = cantidad;

            const subtotal = cantidad * (parseFloat(producto.precio_venta) || 0);
            fila.querySelector('.subtotal').textContent = `$${new Intl.NumberFormat('es-CO').format(subtotal)}`;
            fila.querySelector('input[name="cantidades[]"]').value = cantidad;

            actualizarTotal();
        } else {
            const fila = document.createElement('tr');
            fila.innerHTML = `
                <td>${producto.descripcion || producto.nombre} ${producto.codigo_barras ? `(Código: ${producto.codigo_barras})` : ''}</td>
                <td>$${(producto.precio_formateado || '0').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}</td>
                <td>
                    <input type="number" class="form-control cantidad" value="1" min="1" data-product-id="${producto.id}">
                </td>
                <td class="subtotal">$${(producto.precio_venta * 1).toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td>
                    <button type="button" class="btn btn-danger eliminar" style="background-color: #dc3545;">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
                <input type="hidden" name="productos[]" value="${producto.id}">
                <input type="hidden" name="cantidades[]" value="1">
                <input type="hidden" name="precios[]" value="${producto.precio_venta}">
            `;
            listaProductos.appendChild(fila);

            // Evento para cambio de cantidad
            fila.querySelector('.cantidad').addEventListener('change', function() {
                const cantidad = Math.max(1, parseInt(this.value) || 1);
                this.value = cantidad;

                const precio = parseFloat(producto.precio_venta) || 0;
                const subtotal = cantidad * precio;
                
                fila.querySelector('.subtotal').textContent = `$${subtotal.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                fila.querySelector('input[name="cantidades[]"]').value = cantidad;

                actualizarTotal();
            });

            // Evento para eliminar producto
            fila.querySelector('.eliminar').addEventListener('click', () => {
                fila.classList.add('fade-out');
                setTimeout(() => {
                    fila.remove();
                    actualizarTotal();
                }, 300);
            });
        }
    }

    // Evento para buscar productos
    let timeoutBusqueda;
    busqueda.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        const texto = this.value.trim();
        
        if (texto.length === 0) {
            resultadosBusqueda.style.display = 'none';
            resultadosBusqueda.innerHTML = '';
            return;
        }

        timeoutBusqueda = setTimeout(() => {
            fetch(`controllers/ventas/buscar_producto.php?q=${encodeURIComponent(texto)}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error en la respuesta');
                    return response.json();
                })
                .then(productos => {
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
                                    <span>${producto.descripcion || producto.nombre}</span>
                                    <small class="text-muted">$${(producto.precio_venta || 0).toLocaleString('es-CO')}</small>
                                </div>
                                ${producto.codigo_barras ? `<small class="text-muted">Código: ${producto.codigo_barras}</small>` : ''}
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
                })
                .catch(error => {
                    console.error('Error en la búsqueda:', error);
                    mostrarMensaje('Error al buscar productos', 'error');
                });
        }, 300);
    });

    // Evento para tecla Enter
    busqueda.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const texto = this.value.trim();
            
            if (texto.length > 0) {
                fetch(`controllers/ventas/buscar_producto.php?q=${encodeURIComponent(texto)}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Error en la respuesta');
                        return response.json();
                    })
                    .then(productos => {
                        if (productos.length > 0) {
                            agregarFila(productos[0]);
                            this.value = '';
                            resultadosBusqueda.style.display = 'none';
                        } else {
                            mostrarMensaje('Producto no encontrado', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error en la búsqueda:', error);
                        mostrarMensaje('Error al buscar el producto', 'error');
                    });
            }
        }
    });

    // Limpiar cotización
    btnLimpiar.addEventListener('click', function() {
        listaProductos.innerHTML = '';
        document.getElementById('total-cotizacion').textContent = '0';
        document.getElementById('cliente').value = '';
        document.getElementById('contacto').value = '';
        document.getElementById('notas').value = '';
        busqueda.focus();
    });

    // Validar formulario antes de enviar
    document.getElementById('form-cotizacion').addEventListener('submit', function(e) {
    // Capturar valores
    const cliente = document.getElementById('cliente').value.trim();
    document.getElementById('input-cliente').value = cliente;
    
    const contacto = document.getElementById('contacto').value.trim();
    document.getElementById('input-contacto').value = contacto;

    // Validación
    if (document.querySelectorAll('#productos-lista tr').length === 0) {
        e.preventDefault();
        mostrarMensaje('Debe agregar al menos un producto', 'error');
        return;
    }
    
    if (!cliente) {
        e.preventDefault();
        mostrarMensaje('Nombre del cliente es obligatorio', 'error');
        document.getElementById('cliente').focus();
    }
});

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!busqueda.contains(e.target) && !resultadosBusqueda.contains(e.target)) {
            resultadosBusqueda.style.display = 'none';
        }
    });
    
</script>

<style>
    /* Estilos adicionales para cotizaciones */
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
    
    /* Estilo especial para botón de cotización */
    .btn-cotizacion {
        background-color: #d4af37;
        color: #1a3a2f;
        border: none;
    }
    
    .btn-cotizacion:hover {
        background-color: #e8c96a;
        color: #1a3a2f;
    }
</style>

<?php include_once 'includes/footer.php'; ?>