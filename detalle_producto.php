<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Check if user has permission to view details
include 'config/funciones.php';
include 'config/conexion.php';


// Get product ID from URL
$id_producto = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Prepare and execute query
$stmt = $conexion->prepare("SELECT 
    p.*, 
    c.nombre AS categoria,
    u.nombre AS usuario_creador
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    LEFT JOIN usuarios u ON p.fecha_creacion = u.id
    WHERE p.id = ?");
$stmt->bind_param("i", $id_producto);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();

if (!$producto) {
    header("Location: productos.php?mensaje=error&texto=Producto no encontrado");
    exit();
}



include 'includes/header.php';
?>
<div class="container-fluid px-4">
    <div class="card shadow-lg mt-4">
        <div class="card-header bg-primary text-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">
                    <i class="fas fa-box-open me-2"></i> Detalles del Producto
                </h2>
                <a href="productos.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i> Volver
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Product Info Section -->
            <div class="row mb-4">
                <div class="col-md-3 text-center">
                    <?php if (!empty($producto['imagen'])): ?>
                    <img src="assets/img/productos/<?= htmlspecialchars($producto['imagen']) ?>" 
                         class="img-fluid rounded mb-3" style="max-height: 200px;">
                    <?php else: ?>
                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                         style="height: 200px; width: 200px;">
                        <i class="fas fa-box fa-4x text-muted"></i>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-9">
                    <h3><?= htmlspecialchars($producto['descripcion']) ?></h3>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <p><strong>Código:</strong> <?= htmlspecialchars($producto['codigo_barras']) ?></p>
                            <p><strong>Categoría:</strong> <?= htmlspecialchars($producto['categoria']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Stock Actual:</strong> 
                                <span class="badge bg-<?= $producto['cantidad'] > 10 ? 'success' : ($producto['cantidad'] > 0 ? 'warning' : 'danger') ?>">
                                    <?= $producto['cantidad'] ?>
                                </span>
                            </p>
                            <p><strong>Mínimo Requerido:</strong> <?= $producto['inventario_minimo'] ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Precio Compra:</strong> <?= formatoMoneda($producto['precio_compra']) ?></p>
                            <p><strong>Precio Venta:</strong> <?= formatoMoneda($producto['precio_venta']) ?></p>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <p><strong>Registrado por:</strong> <?= htmlspecialchars($producto['usuario_creador'] ?? 'Sistema') ?></p>
                        <p><strong>Fecha creación:</strong> <?= date('d/m/Y H:i', strtotime($producto['fecha_creacion'])) ?></p>
                        <?php if ($producto['fecha_modificacion']): ?>
                        <p><strong>Última modificación:</strong> <?= date('d/m/Y H:i', strtotime($producto['fecha_modificacion'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Movement History Section -->
            <div class="mt-5">
                <h4 class="border-bottom pb-2">
                    <i class="fas fa-history me-2"></i> Historial de Movimientos
                </h4>
                
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Stock</th>
                                <th>Usuario</th>
                                <th>Comentario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movimientos)): ?>
                                <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($mov['fecha'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $mov['cantidad'] > 0 ? 'success' : 'danger' ?>">
                                            <?= htmlspecialchars($mov['tipo_movimiento']) ?>
                                        </span>
                                    </td>
                                    <td><?= abs($mov['cantidad']) ?></td>
                                    <td><?= $mov['stock_resultante'] ?></td>
                                    <td><?= htmlspecialchars($mov['usuario'] ?? 'Sistema') ?></td>
                                    <td><?= !empty($mov['comentario']) ? htmlspecialchars($mov['comentario']) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-3 text-muted">
                                        No hay movimientos registrados para este producto
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card-footer bg-light">
            <div class="d-flex justify-content-between">
                <div>
                    <span class="text-muted small">ID: <?= $producto['id'] ?></span>
                </div>
                <div>
                    <?php if(tienePermiso(['Administrador', 'Supervisor'])): ?>
                    <a href="editar_producto.php?id=<?= $producto['id'] ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit me-1"></i> Editar
                    </a>
                    <?php endif; ?>
                    
                    <?php if(tienePermiso(['Administrador'])): ?>
                    <button class="btn btn-sm btn-<?= $producto['activo'] ? 'danger' : 'success' ?> ms-2" 
                            id="btnToggleEstado"
                            data-id="<?= $producto['id'] ?>"
                            data-estado="<?= $producto['activo'] ?>">
                        <i class="fas fa-<?= $producto['activo'] ? 'times' : 'check' ?> me-1"></i>
                        <?= $producto['activo'] ? 'Desactivar' : 'Activar' ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Toggle product status
    document.getElementById('btnToggleEstado')?.addEventListener('click', function() {
        const id = this.dataset.id;
        const nuevoEstado = this.dataset.estado === '1' ? '0' : '1';
        const accion = nuevoEstado === '1' ? 'activar' : 'desactivar';
        
        Swal.fire({
            title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} producto?`,
            text: `Está a punto de ${accion} este producto.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: nuevoEstado === '1' ? '#28a745' : '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Sí, ${accion}`,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`controllers/productos/toggle_estado.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        estado: nuevoEstado
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: '¡Éxito!',
                            text: data.message,
                            icon: 'success'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire("Error", data.message, "error");
                    }
                });
            }
        });
    });
});
</script>