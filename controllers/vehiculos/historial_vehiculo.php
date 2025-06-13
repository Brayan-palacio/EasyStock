<?php
ob_start();
session_start();

// Verificar sesión y permisos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    ob_end_flush();
    exit();
}

// Roles permitidos para ver historial
$rolesPermitidos = ['Administrador', 'mecanico', 'vendedor', 'supervisor'];
if (!in_array($_SESSION['rol'], $rolesPermitidos)) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'No tienes permisos para acceder a esta sección'
    ];
    header("Location: vehiculos.php");
    exit();
}

include 'config/conexion.php';
include_once('includes/header.php');

// Validar ID del vehículo
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'ID de vehículo no válido'
    ];
    header("Location: vehiculos.php");
    exit();
}

$id_vehiculo = (int)$_GET['id'];

// Obtener información básica del vehículo con datos del cliente
$queryVehiculo = "SELECT v.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono
                 FROM vehiculos v
                 JOIN clientes c ON v.id_cliente = c.id
                 WHERE v.id = ?";
$stmtVehiculo = $conexion->prepare($queryVehiculo);
$stmtVehiculo->bind_param("i", $id_vehiculo);
$stmtVehiculo->execute();
$resultadoVehiculo = $stmtVehiculo->get_result();

if ($resultadoVehiculo->num_rows === 0) {
    $_SESSION['mensaje'] = [
        'tipo' => 'warning',
        'texto' => 'Vehículo no encontrado'
    ];
    header("Location: vehiculos.php");
    exit();
}

$vehiculo = $resultadoVehiculo->fetch_assoc();

// Configuración de paginación
$registrosPorPagina = isset($_GET['registros']) ? (int)$_GET['registros'] : 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Filtros
$busqueda = isset($_GET['busqueda']) ? $conexion->real_escape_string($_GET['busqueda']) : '';
$filtroTipo = isset($_GET['tipo']) && in_array($_GET['tipo'], ['Mantenimiento', 'Reparación', 'Inspección', 'Otro']) ? $_GET['tipo'] : '';
$filtroFecha = isset($_GET['fecha']) ? $conexion->real_escape_string($_GET['fecha']) : '';

// Construcción dinámica de la consulta
$condiciones = ["h.id_vehiculo = ?"];
$parametros = [$id_vehiculo];
$tipos = 'i';

if (!empty($busqueda)) {
    $condiciones[] = "(h.descripcion LIKE ? OR h.detalles LIKE ? OR u.nombre LIKE ?)";
    $parametros = array_merge($parametros, ["%$busqueda%", "%$busqueda%", "%$busqueda%"]);
    $tipos .= 'sss';
}

if (!empty($filtroTipo)) {
    $condiciones[] = "h.tipo = ?";
    $parametros[] = $filtroTipo;
    $tipos .= 's';
}

if (!empty($filtroFecha)) {
    $condiciones[] = "DATE(h.fecha) = ?";
    $parametros[] = $filtroFecha;
    $tipos .= 's';
}

$where = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';

// Consulta del historial con paginación
$queryHistorial = "SELECT SQL_CALC_FOUND_ROWS 
                    h.id, h.tipo, h.descripcion, h.fecha, h.costo, h.kilometraje,
                    u.nombre AS tecnico_nombre, u.id AS tecnico_id
                  FROM historial_vehiculos h
                  LEFT JOIN usuarios u ON h.id_tecnico = u.id
                  $where
                  ORDER BY h.fecha DESC, h.id DESC
                  LIMIT ?, ?";

$stmtHistorial = $conexion->prepare($queryHistorial);

// Bind parameters dinámico
$parametros = array_merge($parametros, [$offset, $registrosPorPagina]);
$tipos .= 'ii';
$stmtHistorial->bind_param($tipos, ...$parametros);
$stmtHistorial->execute();
$historial = $stmtHistorial->get_result();

// Total de registros para paginación
$totalRegistros = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Tipos de servicio para filtro
$tiposServicio = ['Mantenimiento', 'Reparación', 'Inspección', 'Otro'];
?>

<div class="container-fluid py-4">
    <div class="card shadow-lg border-0 rounded-3">
        <!-- Encabezado con información del vehículo -->
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="h5 mb-1 fw-bold">
                        <i class="fas fa-history me-2"></i> Historial de Servicios
                    </h2>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge bg-primary">
                            <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> 
                            (<?= htmlspecialchars($vehiculo['anio']) ?>)
                        </span>
                        <span class="badge bg-secondary">
                            Matrícula: <?= htmlspecialchars($vehiculo['matricula']) ?>
                        </span>
                        <span class="badge bg-info">
                            Cliente: <?= htmlspecialchars($vehiculo['cliente_nombre']) ?>
                        </span>
                        <span class="badge <?= $vehiculo['estado'] == 'Activo' ? 'bg-success' : 'bg-warning' ?>">
                            <?= htmlspecialchars($vehiculo['estado']) ?>
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="vehiculos.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i> Volver
                    </a>
                    <?php if (in_array($_SESSION['rol'], ['Admin', 'mecanico'])): ?>
                        <a href="agregar_servicio.php?id_vehiculo=<?= $id_vehiculo ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-plus me-1"></i> Nuevo Servicio
                        </a>
                        <a href="reportes/historial_vehiculo.php?id=<?= $id_vehiculo ?>" 
                           class="btn btn-sm btn-outline-light" target="_blank">
                            <i class="fas fa-file-pdf me-1"></i> Reporte
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filtros avanzados -->
        <div class="card-header bg-light">
            <form method="GET" class="row g-2">
                <input type="hidden" name="id" value="<?= $id_vehiculo ?>">
                
                <div class="col-md-3">
                    <input type="text" name="busqueda" class="form-control form-control-sm" 
                           placeholder="Buscar en descripción..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                
                <div class="col-md-2">
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($tiposServicio as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo) ?>" 
                                <?= $filtroTipo === $tipo ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tipo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <input type="date" name="fecha" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($filtroFecha) ?>">
                </div>
                
                <div class="col-md-2">
                    <select name="registros" class="form-select form-control-sm">
                        <option value="5" <?= $registrosPorPagina == 5 ? 'selected' : '' ?>>5 registros</option>
                        <option value="10" <?= $registrosPorPagina == 10 ? 'selected' : '' ?>>10 registros</option>
                        <option value="20" <?= $registrosPorPagina == 20 ? 'selected' : '' ?>>20 registros</option>
                        <option value="50" <?= $registrosPorPagina == 50 ? 'selected' : '' ?>>50 registros</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
                
                <div class="col-md-1">
                    <a href="historial_vehiculo.php?id=<?= $id_vehiculo ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="card-body">
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-<?= $_SESSION['mensaje']['tipo'] ?> alert-dismissible fade show" role="alert">
                    <i class="fas <?= $_SESSION['mensaje']['tipo'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                    <?= htmlspecialchars($_SESSION['mensaje']['texto']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['mensaje']); ?>
            <?php endif; ?>

            <!-- Resumen de estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary bg-opacity-10 border-primary">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 text-primary">Total Servicios</h6>
                                    <h4 class="mb-0"><?= $totalRegistros ?></h4>
                                </div>
                                <i class="fas fa-tools fa-2x text-primary opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success bg-opacity-10 border-success">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 text-success">Último Servicio</h6>
                                    <h4 class="mb-0">
                                        <?php if ($totalRegistros > 0): ?>
                                            <?php 
                                                $ultimo = $conexion->query("SELECT DATE_FORMAT(MAX(fecha), '%d/%m/%Y') AS ultima_fecha 
                                                                          FROM historial_vehiculos 
                                                                          WHERE id_vehiculo = $id_vehiculo")->fetch_row()[0];
                                                echo $ultimo ?: 'N/A';
                                            ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </h4>
                                </div>
                                <i class="fas fa-calendar-check fa-2x text-success opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info bg-opacity-10 border-info">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 text-info">Total Invertido</h6>
                                    <h4 class="mb-0">
                                        <?php 
                                            $totalInvertido = $conexion->query("SELECT SUM(costo) FROM historial_vehiculos 
                                                                              WHERE id_vehiculo = $id_vehiculo")->fetch_row()[0];
                                            echo '$' . number_format($totalInvertido ?: 0, 2);
                                        ?>
                                    </h4>
                                </div>
                                <i class="fas fa-dollar-sign fa-2x text-info opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning bg-opacity-10 border-warning">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 text-warning">Kilometraje Actual</h6>
                                    <h4 class="mb-0">
                                        <?= number_format($vehiculo['kilometraje'] ?: 0, 0) ?> km
                                    </h4>
                                </div>
                                <i class="fas fa-tachometer-alt fa-2x text-warning opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de historial -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="120">Fecha</th>
                            <th width="120">Tipo</th>
                            <th>Descripción</th>
                            <th width="150">Técnico</th>
                            <th width="120">Costo</th>
                            <th width="120">Kilometraje</th>
                            <th width="130" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($historial->num_rows > 0): ?>
                            <?php while ($servicio = $historial->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?= date('d/m/Y', strtotime($servicio['fecha'])) ?>
                                        <div class="text-muted small"><?= date('H:i', strtotime($servicio['fecha'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?= $servicio['tipo'] == 'Mantenimiento' ? 'bg-primary' : 
                                               ($servicio['tipo'] == 'Reparación' ? 'bg-danger' : 'bg-secondary') ?> rounded-pill">
                                            <?= htmlspecialchars($servicio['tipo']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($servicio['descripcion']) ?></div>
                                        <div class="text-muted small"><?= substr($servicio['detalles'] ?? '', 0, 50) ?>...</div>
                                    </td>
                                    <td>
                                        <?php if ($servicio['tecnico_id']): ?>
                                            <a href="detalle_tecnico.php?id=<?= $servicio['tecnico_id'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($servicio['tecnico_nombre']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No asignado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold <?= $servicio['costo'] > 0 ? 'text-success' : 'text-muted' ?>">
                                        $<?= number_format($servicio['costo'], 2) ?>
                                    </td>
                                    <td>
                                        <?= number_format($servicio['kilometraje'], 0) ?> km
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="detalle_servicio.php?id=<?= $servicio['id'] ?>" 
                                               class="btn btn-sm btn-outline-info"
                                               data-bs-toggle="tooltip" 
                                               title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if (in_array($_SESSION['rol'], ['Admin', 'mecanico'])): ?>
                                                <a href="editar_servicio.php?id=<?= $servicio['id'] ?>" 
                                                   class="btn btn-sm btn-outline-warning"
                                                   data-bs-toggle="tooltip"
                                                   title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <button class="btn btn-sm btn-outline-danger btn-eliminar-servicio" 
                                                        data-id="<?= $servicio['id'] ?>"
                                                        data-descripcion="<?= htmlspecialchars($servicio['descripcion']) ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                        <h5>No se encontraron registros de servicio</h5>
                                        <p class="text-muted">
                                            <?php if (!empty($busqueda) || !empty($filtroTipo) || !empty($filtroFecha)): ?>
                                                No hay resultados para los filtros aplicados
                                            <?php else: ?>
                                                Este vehículo no tiene registros en el historial
                                            <?php endif; ?>
                                        </p>
                                        <?php if (in_array($_SESSION['rol'], ['Admin', 'mecanico'])): ?>
                                            <a href="agregar_servicio.php?id_vehiculo=<?= $id_vehiculo ?>" class="btn btn-primary">
                                                <i class="fas fa-plus me-1"></i> Agregar Primer Servicio
                                            </a>
                                        <?php endif; ?>
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
                               href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaActual - 1])) ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == $paginaActual ? 'active' : '' ?>">
                                <a class="page-link" 
                                   href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaActual + 1])) ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once('includes/footer.php'); ?>

<!-- JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Eliminar servicio con confirmación
    document.querySelectorAll(".btn-eliminar-servicio").forEach(button => {
        button.addEventListener("click", function() {
            const id = this.dataset.id;
            const descripcion = this.dataset.descripcion;
            
            Swal.fire({
                title: `¿Eliminar servicio?`,
                html: `<div class="text-start">
                       <p>Estás a punto de eliminar el servicio registrado como:</p>
                       <div class="alert alert-danger p-2">
                           <strong>${descripcion}</strong>
                       </div>
                       <p>Esta acción no se puede deshacer.</p>
                       </div>`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar",
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`eliminar_servicio.php?id=${id}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error("Error en la respuesta del servidor");
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.message || "Error al eliminar");
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
                        title: "¡Eliminado!",
                        text: "El servicio ha sido eliminado correctamente",
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

<style>
.empty-state {
    padding: 2rem;
    text-align: center;
}
.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
}
</style>