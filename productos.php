<?php
$tituloPagina = 'Gestión de Productos - EasyStock';
include 'config/conexion.php';
include_once 'controllers/productos/productosController.php';
include 'config/funciones.php';
include_once 'includes/header.php';

// Configuración de paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina = obtenerConfiguracion($conexion, 'registros_por_pagina');
$inicio = ($pagina > 1) ? ($pagina * $porPagina - $porPagina) : 0;

// Obtener productos y total
$productos = obtenerProductos($conexion, $inicio, $porPagina);
$totalProductos = obtenerTotalProductos($conexion);
$totalPaginas = ceil($totalProductos / $porPagina);
?>

<?php
// Obtener configuración
$umbralBajo = obtenerConfiguracion($conexion, 'inventario_minimo');

// Obtener TODOS los productos con stock bajo (sin paginación)
$todosBajoStock = obtenerTodosProductosBajoStock($conexion, $umbralBajo);

// Mostrar alerta solo en primera página si hay productos bajo stock
if (!empty($todosBajoStock) && $pagina == 1): ?>
<div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div>
            <h5 class="alert-heading mb-1">¡Alerta Global de Stock Bajo!</h5>
            <p class="mb-2">
                <span class="badge bg-danger rounded-pill"><?= count($todosBajoStock) ?></span>
                productos están por debajo del nivel mínimo (<?= $umbralBajo ?> unidades)
            </p>
            
            <div class="table-responsive mt-3">
                <table class="table table-sm table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th width="100">Stock</th>
                            <th width="120">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($todosBajoStock as $index => $producto): ?>
                        <tr class="align-middle">
                            <td><?= $index + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($producto['imagen'])): ?>
                                    <img src='assets/img/productos/<?= htmlspecialchars($producto['imagen']) ?>' 
                                         class="rounded-circle me-2" width="36" height="36">
                                    <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" 
                                         style="width:36px;height:36px;">
                                        <i class="fas fa-box text-muted"></i>
                                    </div>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($producto['descripcion']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($producto['categoria']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-danger bg-opacity-10 text-danger">
                                    <?= $producto['cantidad'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="editar_producto.php?id=<?= $producto['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary"
                                   title="Reabastecer">
                                    <i class="fas fa-arrow-up"></i>
                                </a>
                                <a href="kardex.php?id=<?= $producto['id'] ?>" 
                                   class="btn btn-sm btn-outline-info"
                                   title="Ver historial">
                                    <i class="fas fa-history"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-2 small text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Estos productos necesitan atención inmediata
            </div>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
<style>
    .rounded-circle {
    transition: all 0.3s ease;
}
.rounded-circle:hover {
    transform: scale(1.05);
    box-shadow: 0 0 0 2px rgba(0,0,0,0.1);
}
</style>

<div class="container-fluid px-4">
    <?php if (!empty($_GET['mensaje']) && !empty($_GET['texto'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_GET['mensaje']) ?> alert-dismissible fade show mt-3" role="alert">
            <i class="fas <?= $_GET['mensaje'] === 'success' ? 'fa-check-circle' : 'fa-info-circle' ?> me-2"></i>
            <?= htmlspecialchars($_GET['texto']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg border-0 rounded-3 overflow-hidden mb-4 mt-3">
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0 fw-semibold">
                    <i class="fas fa-boxes me-2"></i> Gestión de Productos
                </h2>
                <div>
                    <?php if(tienePermiso(['Administrador', 'Supervisor'])): ?>
                    <a href="agregar_producto.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-plus-circle me-1"></i> Nuevo Producto
                    </a>
                        <?php endif; ?>
                    <a href="productos_inactivos.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-trash-alt me-1"></i> Ver Inactivos
                    </a>
                    <button class="btn btn-primary btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fas fa-file-export me-1"></i> Exportar
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">
        <?php if (isset($_SESSION['mensaje'])): ?>
    <script>
    $(document).ready(function() {
        Swal.fire({
            title: '<?= $_SESSION['mensaje']['tipo'] === 'success' ? 'Éxito' : 'Error' ?>',
            text: '<?= addslashes($_SESSION['mensaje']['texto']) ?>',
            icon: '<?= $_SESSION['mensaje']['tipo'] ?>',
            confirmButtonText: 'Aceptar'
        });
    });
    </script>
    <?php unset($_SESSION['mensaje']); ?>
<?php endif; ?>
            <!-- Filtros avanzados -->
            <div class="row mb-4 g-3">
                <div class="col-md-3">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Buscar...">
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" id="filterCategory">
                        <option value="">Todas las categorías</option>
                        <?php 
                        $categorias = $conexion->query("SELECT id, nombre FROM categorias");
                        while ($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($cat['nombre']) ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" id="filterStock">
                        <option value="">Todo el stock</option>
                        <option value="low">Stock bajo</option>
                        <option value="medium">Stock medio</option>
                        <option value="high">Stock alto</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="resetFilters">
                        <i class="fas fa-sync-alt me-1"></i> Limpiar
                    </button>
                </div>
            </div>

            <!-- Tabla de productos -->
            <div class="table-responsive">
                <table id="tablaProductos" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Categoría</th>
                            <th width="120">Stock</th>
                            <th width="120">P. Compra</th>
                            <th width="120">P. Venta</th>
                            <th width="120">Estado</th>
                            <?php if(tienePermiso(['Administrador', 'Supervisor'])): ?>
                            <th width="100">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($productos) > 0): ?>
                            <?php foreach ($productos as $index => $producto): 
                                $stockClass = '';
                                if ($producto['cantidad'] <= 5) {
                                    $stockClass = 'bg-danger-light text-danger';
                                } elseif ($producto['cantidad'] <= 15) {
                                    $stockClass = 'bg-warning-light text-warning';
                                }else {
                                    $stockClass = 'bg-warning-light text-success w';
                                }
                            ?>
                                <tr>
                                    <td><?= $inicio + $index + 1 ?></td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars($producto['codigo_barras']) ?></span>
                                    </td>
                                    <td class="align-middle">
    <div class="d-flex align-items-center">
        <?php if (!empty($producto['imagen']) && file_exists('assets/img/productos/'.$producto['imagen'])): ?>
            <img src='assets/img/productos/<?= htmlspecialchars($producto['imagen']) ?>' 
                 class="rounded-circle object-fit-cover me-2" 
                 width="36" 
                 height="36" 
                 alt="<?= htmlspecialchars($producto['descripcion']) ?>"
                 onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\'rounded-circle bg-light d-flex align-items-center justify-content-center me-2\' style=\'width:36px;height:36px;\'><i class=\'fas fa-box text-muted\'></i></div>';">
        <?php else: ?>
            <div class="rounded-circle bg-dark bg-opacity-10 d-flex align-items-center justify-content-center me-2" 
     style="width:36px;height:36px;">
     <?php 
$icono = $iconos[$producto['categoria_id']] ?? 'fa-box';
?>
<i class="fas <?= $icono ?> text-muted"></i>
</div>
        <?php endif; ?>
        <span class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($producto['descripcion']) ?>">
            <?= htmlspecialchars($producto['descripcion']) ?>
        </span>
    </div>
</td>
                                    <td><?= htmlspecialchars($producto['categoria']) ?></td>
                                    <td>
                                        <span class="badge <?= $stockClass ?>">
                                            <?= htmlspecialchars($producto['cantidad']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold"><?= formatoMoneda($producto['precio_compra']) ?></td>
                                    <td class="fw-bold text-success"><?= formatoMoneda($producto['precio_venta']) ?></td>
                                    <td>
                                        <span class="badge <?= $producto['activo'] == 1 ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $producto['activo'] == 1 ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <?php if(tienePermiso(['Administrador', 'Supervisor'])): ?>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="editar_producto.php?id=<?= htmlspecialchars($producto['id']) ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Editar"
                                               data-bs-toggle="tooltip">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm <?= $producto['activo'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" 
                                                    data-id="<?= $producto['id'] ?>"
                                                    data-action="<?= $producto['activo'] ? 'desactivar' : 'activar' ?>"
                                                    title="<?= $producto['activo'] ? 'Desactivar' : 'Activar' ?>"
                                                    data-bs-toggle="tooltip">
                                                <i class="fas <?= $producto['activo'] ? 'fa-times' : 'fa-check' ?>"></i>
                                            </button>
                                            <a href="kardex.php?id=<?= $producto['id'] ?>" 
                                               class="btn btn-sm btn-outline-info" 
                                               title="Ver Kardex"
                                               data-bs-toggle="tooltip">
                                                <i class="fas fa-warehouse"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                        <h5>No se encontraron productos</h5>
                                        <p class="text-muted">Agrega nuevos productos para comenzar</p>
                                        <a href="agregar_producto.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle me-1"></i> Agregar Producto
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $pagina == 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina - 1 ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $pagina == $i ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $pagina == $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina + 1 ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Exportar -->
<!-- Modal Exportar -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="fas fa-file-export me-2"></i>Exportar Productos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="exportForm" method="GET" action="controllers/productos/exportar_productos.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Formato de exportación</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="formato" id="formatoExcel" value="excel" checked>
                            <label class="form-check-label" for="formatoExcel">
                                <i class="fas fa-file-excel text-success me-1"></i> Excel (.xlsx)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="formato" id="formatoPDF" value="pdf">
                            <label class="form-check-label" for="formatoPDF">
                                <i class="fas fa-file-pdf text-danger me-1"></i> PDF (.pdf)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="formato" id="formatoCSV" value="csv">
                            <label class="form-check-label" for="formatoCSV">
                                <i class="fas fa-file-csv text-info me-1"></i> CSV (.csv)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Filtros Adicionales</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="fechaInicio" class="form-label small">Fecha inicial</label>
                                <input type="date" class="form-control form-control-sm" id="fechaInicio" name="fecha_inicio">
                            </div>
                            <div class="col-md-6">
                                <label for="fechaFin" class="form-label small">Fecha final</label>
                                <input type="date" class="form-control form-control-sm" id="fechaFin" name="fecha_fin">
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info py-2 small">
                        <i class="fas fa-info-circle me-2"></i> Si no selecciona fechas, se exportarán todos los productos activos.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnExportar">
                        <i class="fas fa-download me-1"></i> Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Tooltips
    document.addEventListener("DOMContentLoaded", function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Eliminar producto
        document.querySelectorAll("[data-action='desactivar'], [data-action='activar']").forEach(button => {
    button.addEventListener("click", function() {
        const id = this.dataset.id;
        const action = this.dataset.action;
        const actionText = action === 'desactivar' ? 'desactivar' : 'activar';
        
        Swal.fire({
            title: `¿${actionText.charAt(0).toUpperCase() + actionText.slice(1)} producto?`,
            html: `Estás a punto de ${actionText} este producto.`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: action === 'desactivar' ? "#d33" : "#28a745",
            cancelButtonColor: "#6c757d",
            confirmButtonText: `Sí, ${actionText}`,
            cancelButtonText: "Cancelar",
            backdrop: true
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`controllers/productos/accion_producto.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        action: action
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: "¡Éxito!",
                            text: data.message,
                            icon: "success"
                        }).then(() => location.reload());
                    } else {
                        Swal.fire("Error", data.message, "error");
                    }
                })
                .catch(error => {
                    Swal.fire("Error", "Ocurrió un error", "error");
                });
            }
        });
    });
});

        // Filtros avanzados
        const searchInput = document.getElementById('searchInput');
        const filterCategory = document.getElementById('filterCategory');
        const filterStock = document.getElementById('filterStock');
        const resetFilters = document.getElementById('resetFilters');

        function aplicarFiltros() {
            const searchValue = searchInput.value.toLowerCase();
            const categoryValue = filterCategory.value.toLowerCase();
            const stockValue = filterStock.value;
            
            document.querySelectorAll("#tablaProductos tbody tr").forEach(row => {
                const descripcion = row.cells[2].textContent.toLowerCase();
                const categoria = row.cells[3].textContent.toLowerCase();
                const stock = parseInt(row.cells[4].textContent);
                
                let matchSearch = descripcion.includes(searchValue) || row.cells[1].textContent.includes(searchValue);
                let matchCategory = categoryValue === '' || categoria === categoryValue;
                let matchStock = true;
                
                if (stockValue === 'low') {
                    matchStock = stock <= 5;
                } else if (stockValue === 'medium') {
                    matchStock = stock > 5 && stock <= 15;
                } else if (stockValue === 'high') {
                    matchStock = stock > 15;
                }
                
                row.style.display = matchSearch && matchCategory && matchStock ? '' : 'none';
            });
        }

        searchInput.addEventListener('keyup', aplicarFiltros);
        filterCategory.addEventListener('change', aplicarFiltros);
        filterStock.addEventListener('change', aplicarFiltros);
        
        resetFilters.addEventListener('click', function() {
            searchInput.value = '';
            filterCategory.value = '';
            filterStock.value = '';
            aplicarFiltros();
        });
    });
// Configuración del modal de exportación
$('#exportModal').on('show.bs.modal', function() {
        const today = new Date().toISOString().split('T')[0];
        const firstDay = new Date(new Date().setDate(1)).toISOString().split('T')[0];
        
        $(this).find('input[name="fecha_inicio"]').val(firstDay);
        $(this).find('input[name="fecha_fin"]').val(today);
    });

    // Validación del formulario de exportación
    $('#exportForm').on('submit', function(e) {
        e.preventDefault();
        
        const formato = $('input[name="formato"]:checked').val();
        const fechaInicio = $('input[name="fecha_inicio"]').val();
        const fechaFin = $('input[name="fecha_fin"]').val();
        
        // Validación de fechas
        if ((fechaInicio && !fechaFin) || (!fechaInicio && fechaFin)) {
            Swal.fire({
                title: 'Rango incompleto',
                text: 'Debes seleccionar ambas fechas o ninguna',
                icon: 'warning'
            });
            return;
        }
        
        if (fechaInicio && fechaFin && new Date(fechaInicio) > new Date(fechaFin)) {
            Swal.fire("Error", "La fecha inicial no puede ser mayor a la final", "error");
            return;
        }
        
        // Mostrar estado de carga
        const btn = $('#btnExportar');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Exportando...');
        
        // Construir URL de exportación
        let url = `controllers/productos/exportar_productos.php?formato=${formato}`;
        if (fechaInicio && fechaFin) {
            url += `&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
        }
        
        // Redireccionar para descargar
        window.location.href = url;
        
        // Revertir el botón después de 3 segundos (por si falla la descarga)
        setTimeout(() => {
            btn.prop('disabled', false).html('<i class="fas fa-download me-1"></i> Exportar');
        }, 3000);
    });

    // Función para formatear moneda (disponible globalmente)
    window.formatoMoneda = function(valor) {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'USD'
        }).format(valor);
    };

</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Obtener el umbral de bajo stock (podrías pasarlo desde PHP o hacer una petición AJAX)
    const umbralBajo = <?= $umbralBajo ?>; 
    
    // Contar productos bajo stock
    let lowStockCount = 0;
    document.querySelectorAll('td:nth-child(5)').forEach(cell => {
        const stock = parseInt(cell.textContent);
        if(stock <= umbralBajo) lowStockCount++;
    });
    
    if(lowStockCount > 0) {
        Swal.fire({
            title: '¡Stock Bajo!',
            html: `<p>Tienes <b>${lowStockCount}</b> producto(s) con stock crítico.</p>
                  <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Revisa la lista de productos marcados en rojo.</small>`,
            icon: 'warning',
            confirmButtonText: 'Entendido',
            backdrop: true,
            allowOutsideClick: false
        });
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>