<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Gestión de Órdenes - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres nivel 20+ para acceder a esta sección'
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

// Obtener parámetros de búsqueda/filtro
$estado = isset($_GET['estado']) ? $conexion->real_escape_string($_GET['estado']) : '';
$cliente = isset($_GET['cliente']) ? $conexion->real_escape_string($_GET['cliente']) : '';
$desde = isset($_GET['desde']) ? $_GET['desde'] : '';
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : '';

// Paginación
$registrosPorPagina = 15;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Consulta principal
$query = "SELECT SQL_CALC_FOUND_ROWS 
            o.id, o.fecha_creacion, o.fecha_entrega, o.estado, o.total,
            c.nombre AS cliente_nombre, c.telefono AS cliente_telefono,
            u.nombre AS tecnico_asignado
          FROM ordenes o
          JOIN clientes c ON o.cliente_id = c.id
          LEFT JOIN usuarios u ON o.tecnico_id = u.id
          WHERE (? = '' OR o.estado = ?)
          AND (? = '' OR c.nombre LIKE CONCAT('%', ?, '%'))
          AND (? = '' OR o.fecha_creacion >= ?)
          AND (? = '' OR o.fecha_creacion <= ?)
          ORDER BY o.fecha_creacion DESC
          LIMIT ? OFFSET ?";

$stmt = $conexion->prepare($query);
$stmt->bind_param('ssssssssii', 
    $estado, $estado,
    $cliente, $cliente,
    $desde, $desde,
    $hasta, $hasta,
    $registrosPorPagina, $offset
);
$stmt->execute();
$ordenes = $stmt->get_result();

// Total de registros para paginación
$totalRegistros = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Estados disponibles
$estadosOrden = ['Pendiente', 'En Proceso', 'Completada', 'Cancelada'];
?>

<style>
    .card-orden {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }
    .card-orden:hover {
        transform: translateY(-3px);
    }
    .badge-estado {
        font-size: 0.85rem;
        padding: 0.5em 0.75em;
        border-radius: 50px;
    }
    .badge-pendiente { background-color: #ffc107; color: #000; }
    .badge-proceso { background-color: #17a2b8; color: #fff; }
    .badge-completada { background-color: #28a745; color: #fff; }
    .badge-cancelada { background-color: #dc3545; color: #fff; }
    .table-hover tbody tr:hover {
        background-color: rgba(26, 58, 47, 0.05);
    }
    .filtros-container {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-clipboard-list me-2"></i> Gestión de Órdenes</h2>
        <a href="agregar_orden.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-1"></i> Nueva Orden
        </a>
    </div>

    <!-- Filtros -->
    <div class="filtros-container mb-4">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos los estados</option>
                    <?php foreach($estadosOrden as $est): ?>
                        <option value="<?= $est ?>" <?= $estado == $est ? 'selected' : '' ?>><?= $est ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="cliente" class="form-label">Cliente</label>
                <input type="text" id="cliente" name="cliente" class="form-control" 
                       value="<?= htmlspecialchars($cliente) ?>" placeholder="Buscar cliente...">
            </div>
            
            <div class="col-md-2">
                <label for="desde" class="form-label">Desde</label>
                <input type="date" id="desde" name="desde" class="form-control" value="<?= htmlspecialchars($desde) ?>">
            </div>
            
            <div class="col-md-2">
                <label for="hasta" class="form-label">Hasta</label>
                <input type="date" id="hasta" name="hasta" class="form-control" value="<?= htmlspecialchars($hasta) ?>">
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- Listado de órdenes -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-<?= $_SESSION['mensaje']['tipo'] ?> alert-dismissible fade show mb-4">
                    <i class="fas <?= $_SESSION['mensaje']['tipo'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                    <?= htmlspecialchars($_SESSION['mensaje']['texto']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['mensaje']); ?>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="100"># Orden</th>
                            <th>Fecha Creación</th>
                            <th>Cliente</th>
                            <th>Técnico</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Entrega</th>
                            <th width="120">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($ordenes->num_rows > 0): ?>
                            <?php while ($orden = $ordenes->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold">#<?= str_pad($orden['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= date('d/m/Y', strtotime($orden['fecha_creacion'])) ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span><?= htmlspecialchars($orden['cliente_nombre']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($orden['cliente_telefono']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($orden['tecnico_asignado'] ?? 'Sin asignar') ?></td>
                                    <td>
                                        <?php 
                                        $claseEstado = strtolower(str_replace(' ', '-', $orden['estado']));
                                        ?>
                                        <span class="badge badge-estado badge-<?= $claseEstado ?>">
                                            <?= htmlspecialchars($orden['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold">$<?= number_format($orden['total'], 2) ?></td>
                                    <td><?= $orden['fecha_entrega'] ? date('d/m/Y', strtotime($orden['fecha_entrega'])) : '--' ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="ver_orden.php?id=<?= $orden['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($orden['estado'] !== 'Completada' && $orden['estado'] !== 'Cancelada'): ?>
                                                <a href="editar_orden.php?id=<?= $orden['id'] ?>" 
                                                   class="btn btn-sm btn-outline-secondary"
                                                   title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                        <h5>No se encontraron órdenes</h5>
                                        <p class="text-muted"><?= ($estado || $cliente || $desde || $hasta) ? 'Prueba con otros filtros' : 'Comienza creando una nueva orden' ?></p>
                                        <a href="agregar_orden.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle me-1"></i> Crear Orden
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
                <nav aria-label="Paginación" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?pagina=<?= $paginaActual - 1 ?>&estado=<?= urlencode($estado) ?>&cliente=<?= urlencode($cliente) ?>&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == $paginaActual ? 'active' : '' ?>">
                                <a class="page-link" 
                                   href="?pagina=<?= $i ?>&estado=<?= urlencode($estado) ?>&cliente=<?= urlencode($cliente) ?>&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?pagina=<?= $paginaActual + 1 ?>&estado=<?= urlencode($estado) ?>&cliente=<?= urlencode($cliente) ?>&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<!-- JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Validación de fechas en filtros
    const desdeInput = document.getElementById('desde');
    const hastaInput = document.getElementById('hasta');
    
    if (desdeInput && hastaInput) {
        desdeInput.addEventListener('change', function() {
            if (hastaInput.value && this.value > hastaInput.value) {
                hastaInput.value = this.value;
            }
        });
        
        hastaInput.addEventListener('change', function() {
            if (desdeInput.value && this.value < desdeInput.value) {
                desdeInput.value = this.value;
            }
        });
    }
});
</script>