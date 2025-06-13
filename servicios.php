<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Gestión de Servicios - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres nivel 30+ para acceder a esta sección'
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

// Parámetros de búsqueda y filtrado
$busqueda = isset($_GET['busqueda']) ? $conexion->real_escape_string($_GET['busqueda']) : '';
$estado = isset($_GET['estado']) && in_array($_GET['estado'], ['Activo', 'Inactivo']) ? $_GET['estado'] : '';

// Paginación
$registrosPorPagina = 15;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Consulta principal
$query = "SELECT SQL_CALC_FOUND_ROWS 
            id, codigo, descripcion, precio, duracion, estado
          FROM servicios
          WHERE (? = '' OR descripcion LIKE CONCAT('%', ?, '%') OR codigo LIKE CONCAT('%', ?, '%'))
          AND (? = '' OR estado = ?)
          ORDER BY descripcion ASC
          LIMIT ? OFFSET ?";

$stmt = $conexion->prepare($query);

// Verificar si la preparación fue exitosa
if (!$stmt) {
    die("Error al preparar la consulta: " . $conexion->error);
}

// Vincular parámetros CORREGIDO
$stmt->bind_param('sssssii', 
    $busqueda, $busqueda, $busqueda, // Para las tres primeras condiciones (1 string repetido)
    $estado, $estado,               // Para las dos condiciones de estado (1 string repetido)
    $registrosPorPagina, $offset    // Para los dos valores numéricos
);

// Ejecutar la consulta
if (!$stmt->execute()) {
    die("Error al ejecutar la consulta: " . $stmt->error);
}

$servicios = $stmt->get_result();

// Total de registros
$totalRegistros = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
?>

<style>
    .card-servicios {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .badge-estado {
        font-size: 0.85rem;
        padding: 0.5em 0.75em;
        border-radius: 50px;
    }
    .badge-activo {
        background-color: #28a745;
        color: white;
    }
    .badge-inactivo {
        background-color: #6c757d;
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
    .table-hover tbody tr:hover {
        background-color: rgba(26, 58, 47, 0.05);
    }
    .duracion-badge {
        background-color: #e9ecef;
        color: #495057;
        font-weight: normal;
    }
</style>

<div class="container-fluid py-4">
    <div class="card card-servicios">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fas fa-tools me-2"></i> Servicios</h3>
                <a href="agregar_servicio.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-plus-circle me-1"></i> Nuevo Servicio
                </a>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card-body border-bottom">
            <form method="get" class="row g-3">
                <div class="col-md-8">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control search-input" 
                               name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" 
                               placeholder="Buscar servicios...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="Activo" <?= $estado == 'Activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="Inactivo" <?= $estado == 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
        
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
                            <th width="100">Código</th>
                            <th>Descripción</th>
                            <th width="150">Precio</th>
                            <th width="150">Duración</th>
                            <th width="120">Estado</th>
                            <th width="120" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($servicios->num_rows > 0): ?>
                            <?php while ($servicio = $servicios->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($servicio['codigo']) ?></td>
                                    <td><?= htmlspecialchars($servicio['descripcion']) ?></td>
                                    <td class="fw-bold">$<?= number_format($servicio['precio'], 2) ?></td>
                                    <td>
                                        <span class="badge duracion-badge">
                                            <?= $servicio['duracion'] ? $servicio['duracion'] . ' mins' : '--' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-estado badge-<?= strtolower($servicio['estado']) ?>">
                                            <?= htmlspecialchars($servicio['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="editar_servicio.php?id=<?= $servicio['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger btn-cambiar-estado"
                                                    data-id="<?= $servicio['id'] ?>"
                                                    data-estado="<?= $servicio['estado'] ?>"
                                                    data-descripcion="<?= htmlspecialchars($servicio['descripcion']) ?>"
                                                    title="<?= $servicio['estado'] == 'Activo' ? 'Desactivar' : 'Activar' ?>">
                                                <i class="fas <?= $servicio['estado'] == 'Activo' ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                        <h5>No se encontraron servicios</h5>
                                        <p class="text-muted"><?= ($busqueda || $estado) ? 'Prueba con otros filtros' : 'Comienza agregando un nuevo servicio' ?></p>
                                        <a href="agregar_servicio.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle me-1"></i> Agregar Servicio
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
                               href="?pagina=<?= $paginaActual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == $paginaActual ? 'active' : '' ?>">
                                <a class="page-link" 
                                   href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?pagina=<?= $paginaActual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado) ?>">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Cambiar estado de servicio
    document.querySelectorAll(".btn-cambiar-estado").forEach(button => {
        button.addEventListener("click", function() {
            const id = this.dataset.id;
            const descripcion = this.dataset.descripcion;
            const estadoActual = this.dataset.estado;
            const nuevoEstado = estadoActual == 'Activo' ? 'Inactivo' : 'Activo';
            
            Swal.fire({
                title: `¿Cambiar estado del servicio?`,
                html: `<p>Estás a punto de marcar el servicio <strong>${descripcion}</strong> como <strong>${nuevoEstado}</strong>.</p>`,
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#1a3a2f",
                cancelButtonColor: "#6c757d",
                confirmButtonText: `Sí, marcar como ${nuevoEstado}`,
                cancelButtonText: "Cancelar",
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`cambiar_estado_servicio.php?id=${id}&estado=${nuevoEstado}`)
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.message);
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(
                                `Error: ${error.message}`
                            );
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: "¡Estado actualizado!",
                        text: `El servicio ahora está marcado como ${nuevoEstado}.`,
                        icon: "success",
                        confirmButtonText: "Aceptar"
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        });
    });
});
</script>