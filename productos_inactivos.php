<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$tituloPagina = 'Productos Inactivos - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

$categorias = [];
$stmt_cat = $conexion->prepare("SELECT id, nombre FROM categorias");
$stmt_cat->execute();
$result_cat = $stmt_cat->get_result();
while ($cat = $result_cat->fetch_assoc()) {
    $categorias[$cat['id']] = $cat['nombre'];
}
?>

<div class="container-fluid px-4">
    <div class="card shadow-lg border-0 rounded-3 overflow-hidden mt-4">
        <div class="card-header bg-secondary text-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0 fw-semibold">
                    <i class="fas fa-trash-alt me-2"></i> Productos Inactivos
                </h2>
                <a href="productos.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i> Volver a productos activos
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Categoría</th>
                            <th>Stock</th>
                            <th>Precio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conexion->prepare("SELECT * FROM productos WHERE activo = 0 ORDER BY descripcion");
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->num_rows > 0):
                            while ($producto = $result->fetch_assoc()):
                        ?>
                        <tr>
                        <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary">
                                            <?= htmlspecialchars($producto['codigo_barras']) ?>
                                        </span>
                                    </td>
                            <td><?= htmlspecialchars($producto['descripcion']) ?></td>
                            <td><?= isset($categorias[$producto['categoria_id']]) ? htmlspecialchars($categorias[$producto['categoria_id']]) : 'Sin categoría' ?></td>
                            <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                            <?= htmlspecialchars($producto['cantidad']) ?>
                                        </span>
                                    </td>
                            <td class="fw-bold">$<?= number_format($producto['precio_venta'], 0, ',', '.') ?></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-success btn-restaurar" 
                                            data-id="<?= $producto['id'] ?>" 
                                            data-nombre="<?= htmlspecialchars($producto['descripcion']) ?>">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <a href="detalle_producto.php?id=<?= $producto['id'] ?>" class="btn btn-outline-info">
                                        <i class="fas fa-info-circle"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                        <h5>No hay productos inactivos</h5>
                                        <p class="text-muted">Todos los productos están actualmente activos</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
<!-- Scripts adicionales -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Eliminar producto
    document.querySelectorAll(".btn-eliminar").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            
            Swal.fire({
                title: '¿Eliminar producto?',
                html: `Está a punto de eliminar el producto: <b>${nombre}</b>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                backdrop: `
                    rgba(0,0,0,0.7)
                    url("assets/img/alert-warning.gif")
                    center top
                    no-repeat
                `
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar carga mientras se procesa
                    Swal.fire({
                        title: 'Eliminando...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Enviar solicitud AJAX para eliminar
                    fetch(`eliminar_producto.php?id=${id}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Eliminado!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire("Error", data.message, "error");
                        }
                    })
                    .catch(error => {
                        Swal.fire("Error", "Ocurrió un error al eliminar el producto", "error");
                    });
                }
            });
        });
    });

    // Restaurar producto
    document.querySelectorAll(".btn-restaurar").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            
            Swal.fire({
                title: '¿Restaurar producto?',
                html: `Está a punto de restaurar el producto: <b>${nombre}</b>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar carga mientras se procesa
                    Swal.fire({
                        title: 'Restaurando...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Enviar solicitud AJAX para restaurar
                    fetch(`controllers/productos/restaurar_producto.php?id=${id}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Restaurado!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire("Error", data.message, "error");
                        }
                    })
                    .catch(error => {
                        Swal.fire("Error", "Ocurrió un error al restaurar el producto", "error");
                    });
                }
            });
        });
    });

    // Inicializar DataTables (opcional)
    if (document.getElementById('tablaProductosActivos')) {
        $('#tablaProductosActivos').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
            }
        });
    }

    if (document.getElementById('tablaProductosInactivos')) {
        $('#tablaProductosInactivos').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
            }
        });
    }
});
</script>