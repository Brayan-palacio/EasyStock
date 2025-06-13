<?php
// Verificar sesión y permisos
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar permisos (solo admin y mecánicos pueden ver vehículos)
$rolesPermitidos = ['Admin', 'mecanico', 'supervisor']; // Agregar más roles si es necesario
if (!in_array($_SESSION['rol_usuario'], $rolesPermitidos)) {
    header("Location: acceso_denegado.php");
    exit();
}

include 'config/conexion.php';
include_once('includes/header.php');

// Configuración de paginación desde la configuración del sistema
$configPorPagina = $conexion->query("SELECT valor FROM configuracion WHERE clave = 'registros_por_pagina'")->fetch_row()[0] ?? 15;
$por_pagina = isset($_GET['registros']) ? (int)$_GET['registros'] : $configPorPagina;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = ($pagina > 1) ? ($pagina * $por_pagina - $por_pagina) : 0;

// Búsqueda con prepared statements y filtros avanzados
$busqueda = isset($_GET['busqueda']) ? trim($conexion->real_escape_string($_GET['busqueda'])) : '';
$filtroEstado = isset($_GET['estado']) && in_array($_GET['estado'], ['Activo', 'Inactivo']) ? $_GET['estado'] : '';
$filtroTipo = isset($_GET['tipo']) ? $conexion->real_escape_string($_GET['tipo']) : '';

// Construcción dinámica de la consulta
$condiciones = [];
$parametros = [];
$tipos = '';

if (!empty($busqueda)) {
    $condiciones[] = "(v.marca LIKE ? OR v.modelo LIKE ? OR v.matricula LIKE ? OR c.nombre LIKE ?)";
    $parametros = array_merge($parametros, ["%$busqueda%", "%$busqueda%", "%$busqueda%", "%$busqueda%"]);
    $tipos .= 'ssss';
}

if (!empty($filtroEstado)) {
    $condiciones[] = "v.estado = ?";
    $parametros[] = $filtroEstado;
    $tipos .= 's';
}

if (!empty($filtroTipo)) {
    $condiciones[] = "v.tipo = ?";
    $parametros[] = $filtroTipo;
    $tipos .= 's';
}

$where = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';

// Consulta principal con paginación
$sql = "SELECT SQL_CALC_FOUND_ROWS v.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono
        FROM vehiculos v
        LEFT JOIN clientes c ON v.id_cliente = c.id
        $where
        ORDER BY v.marca, v.modelo 
        LIMIT ?, ?";

$stmt = $conexion->prepare($sql);

// Bind parameters dinámico
if (!empty($parametros)) {
    $parametros = array_merge($parametros, [$inicio, $por_pagina]);
    $tipos .= 'ii';
    $stmt->bind_param($tipos, ...$parametros);
} else {
    $stmt->bind_param('ii', $inicio, $por_pagina);
}

$stmt->execute();
$resultado = $stmt->get_result();


// Total de registros para paginación
$total_registros = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas = ceil($total_registros / $por_pagina);
?>

<div class="container-fluid mt-4">
    <div class="card shadow-lg rounded-3 border-0">
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h2 class="h5 mb-0 fw-bold">
                    <i class="fas fa-car me-2"></i>Gestión de Vehículos
                </h2>
                <div class="d-flex gap-2">
                    <a href="reportes/vehiculos.php?<?= http_build_query($_GET) ?>" 
                       class="btn btn-sm btn-outline-light" target="_blank">
                        <i class="fas fa-file-pdf me-1"></i> Reporte
                    </a>
                    <a href="agregar_vehiculo.php" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i> Nuevo Vehículo
                    </a>
                </div>
            </div>
        </div>

        <!-- Filtros avanzados -->
        <div class="card-header bg-light">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <input type="text" name="busqueda" class="form-control form-control-sm" 
                           placeholder="Buscar..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col-md-2">
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="Activo" <?= $filtroEstado === 'Activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="Inactivo" <?= $filtroEstado === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($tiposVehiculos as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo['tipo']) ?>" 
                                <?= $filtroTipo === $tipo['tipo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tipo['tipo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="registros" class="form-select form-select-sm">
                        <option value="5" <?= $por_pagina == 5 ? 'selected' : '' ?>>5 registros</option>
                        <option value="10" <?= $por_pagina == 10 ? 'selected' : '' ?>>10 registros</option>
                        <option value="15" <?= $por_pagina == 15 ? 'selected' : '' ?>>15 registros</option>
                        <option value="25" <?= $por_pagina == 25 ? 'selected' : '' ?>>25 registros</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="vehiculos.php" class="btn btn-sm btn-outline-secondary w-100">
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

            <!-- Resumen de resultados -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-muted small">
                    Mostrando <?= ($inicio + 1) ?> a <?= min($inicio + $por_pagina, $total_registros) ?> de <?= $total_registros ?> registros
                </div>
                <div>
                    <span class="badge bg-info text-dark">
                        <i class="fas fa-car me-1"></i> <?= $total_registros ?> vehículo(s)
                    </span>
                </div>
            </div>

            <!-- Tabla de vehículos -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="80">ID</th>
                            <th>Marca/Modelo</th>
                            <th width="100">Año</th>
                            <th width="120">Matrícula</th>
                            <th>Cliente</th>
                            <th width="100">Tipo</th>
                            <th width="100">Estado</th>
                            <th width="150" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado->num_rows > 0): ?>
                            <?php while ($vehiculo = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold">#<?= str_pad($vehiculo['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($vehiculo['marca']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($vehiculo['modelo']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($vehiculo['anio']) ?></td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary">
                                            <?= htmlspecialchars($vehiculo['matricula']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($vehiculo['id_cliente']): ?>
                                            <div class="fw-bold"><?= htmlspecialchars($vehiculo['cliente_nombre']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($vehiculo['cliente_telefono']) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">No asignado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($vehiculo['tipo'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge <?= $vehiculo['estado'] == 'Activo' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                                            <?= htmlspecialchars($vehiculo['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="editar_vehiculo.php?id=<?= $vehiculo['id'] ?>" 
                                               class="btn btn-outline-primary"
                                               data-bs-toggle="tooltip" 
                                               title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-outline-danger btn-eliminar" 
                                                    data-id="<?= $vehiculo['id'] ?>"
                                                    data-placa="<?= htmlspecialchars($vehiculo['matricula']) ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <a href="historial_vehiculo.php?id=<?= $vehiculo['id'] ?>" 
                                               class="btn btn-outline-info"
                                               data-bs-toggle="tooltip"
                                               title="Historial">
                                                <i class="fas fa-history"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-car-side fa-3x text-muted mb-3"></i>
                                        <h5>No se encontraron vehículos</h5>
                                        <p class="text-muted">
                                            <?php if (!empty($busqueda) || !empty($filtroEstado) || !empty($filtroTipo)): ?>
                                                Prueba con otros criterios de búsqueda
                                            <?php else: ?>
                                                No hay vehículos registrados. Comienza agregando uno nuevo.
                                            <?php endif; ?>
                                        </p>
                                        <a href="agregar_vehiculo.php" class="btn btn-primary">
                                            <i class="fas fa-plus-circle me-1"></i> Agregar Vehículo
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginación" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                <a class="page-link" 
                                   href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">
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

    // Eliminar vehículo con confirmación mejorada
    document.querySelectorAll(".btn-eliminar").forEach(button => {
        button.addEventListener("click", function() {
            const id = this.dataset.id;
            const placa = this.dataset.placa;
            
            Swal.fire({
                title: `¿Eliminar vehículo ${placa}?`,
                html: `<div class="text-start">
                       <p>Estás a punto de eliminar el vehículo con matrícula <strong>${placa}</strong>.</p>
                       <div class="alert alert-warning p-2 mt-2">
                           <i class="fas fa-exclamation-triangle me-2"></i>
                           Esta acción eliminará también todo el historial asociado al vehículo.
                       </div>
                       </div>`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar",
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`eliminar_vehiculo.php?id=${id}`)
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
                        text: `El vehículo ${placa} ha sido eliminado correctamente.`,
                        icon: "success",
                        confirmButtonText: "Aceptar"
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        });
    });

    // Manejo de mensajes de éxito/error desde URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        const message = urlParams.get('message');
        const tipo = urlParams.get('success') === 'true' ? 'success' : 'error';
        
        Swal.fire({
            title: tipo === 'success' ? '¡Éxito!' : 'Error',
            text: message || (tipo === 'success' ? 'Operación realizada correctamente' : 'Ocurrió un error'),
            icon: tipo,
            confirmButtonText: "Aceptar"
        });
        
        // Limpiar la URL sin recargar la página
        const cleanUrl = window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
    }
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