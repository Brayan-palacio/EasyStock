<?php
$tituloPagina = 'Administrar Grupos - EasyStock';
include_once 'config/conexion.php';
include_once 'config/funciones.php';
include_once 'includes/header.php';

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Paginación (nueva funcionalidad)
$registrosPorPagina = obtenerConfiguracion($conexion, 'registros_por_pagina');
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Búsqueda/filtrado (nueva funcionalidad)
$busqueda = isset($_GET['busqueda']) ? $conexion->real_escape_string($_GET['busqueda']) : '';
$filtroEstado = isset($_GET['estado']) && in_array($_GET['estado'], ['Activo', 'Inactivo']) ? $_GET['estado'] : '';

// Consulta optimizada con filtros y paginación
$query = "SELECT SQL_CALC_FOUND_ROWS id, nombre, nivel, estado, creado_en 
          FROM grupo 
          WHERE (? = '' OR nombre LIKE CONCAT('%', ?, '%'))
          AND (? = '' OR estado = ?)
          ORDER BY nivel DESC, nombre ASC
          LIMIT ? OFFSET ?";

$stmt = $conexion->prepare($query);
$stmt->bind_param('ssssii', $busqueda, $busqueda, $filtroEstado, $filtroEstado, $registrosPorPagina, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Total de registros para paginación
$totalRegistros = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<div class="container-fluid py-4">
    <div class="card shadow-lg border-0 rounded-3">
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="h5 mb-0 fw-semibold">
                    <i class="fas fa-users-cog me-2"></i> Administrar Grupos
                </h2>
                <div class="d-flex gap-2">
                    <a href="agregar_grupo.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-plus-circle me-1"></i> Nuevo Grupo
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Filtros (nueva sección) -->
        <div class="card-header border-top bg-light">
            <form method="get" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="busqueda" class="form-control form-control-sm" 
                           placeholder="Buscar grupo..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col-md-3">
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="Activo" <?= $filtroEstado === 'Activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="Inactivo" <?= $filtroEstado === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="grupos.php" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fas fa-sync-alt me-1"></i> Limpiar
                    </a>
                </div>
            </form>
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
            
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tablaGrupos">
                    <thead class="table-light">
                        <tr>
                            <th width="80">ID</th>
                            <th>Nombre del Grupo</th>
                            <th width="120">Nivel</th>
                            <th width="120">Estado</th>
                            <th width="150">Creado en</th>
                            <th width="120" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($grupo = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold">#<?= str_pad($grupo['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= htmlspecialchars($grupo['nombre']) ?></td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary py-2 px-3 rounded-pill">
                                            Nivel <?= htmlspecialchars($grupo['nivel']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $grupo['estado'] == 'Activo' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                                            <?= htmlspecialchars($grupo['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($grupo['creado_en'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="editar_grupo.php?id=<?= $grupo['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="tooltip" 
                                               title="Editar grupo">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger btn-eliminar" 
                                                    data-id="<?= $grupo['id'] ?>"
                                                    data-nombre="<?= htmlspecialchars($grupo['nombre']) ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="Eliminar grupo">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                                        <h5>No se encontraron grupos</h5>
                                        <p class="text-muted"><?= ($busqueda || $filtroEstado) ? 'Prueba con otros filtros' : 'Comienza agregando un nuevo grupo' ?></p>
                                        <a href="agregar_grupo.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle me-1"></i> Agregar Grupo
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación (nueva sección) -->
            <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginación" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($filtroEstado) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == $paginaActual ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($filtroEstado) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($filtroEstado) ?>">
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

<!-- JavaScript (optimizado) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tooltips
    const tooltips = new bootstrap.Tooltip(document.body, {
        selector: '[data-bs-toggle="tooltip"]'
    });

    // Eliminar grupo (optimizado)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-eliminar') && !e.target.closest('.btn-eliminar').disabled) {
            const button = e.target.closest('.btn-eliminar');
            const id = button.dataset.id;
            const nombre = button.dataset.nombre;
            
            Swal.fire({
                title: `¿Eliminar grupo "${nombre}"?`,
                html: `<div class="text-start">
                       <p>Estás a punto de eliminar el grupo <strong>${nombre}</strong>.</p>
                       <div class="alert alert-warning p-2 mt-2">
                           <i class="fas fa-exclamation-triangle me-2"></i>
                           Esta acción afectará a los usuarios asociados.
                       </div>
                       </div>`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Eliminar",
                cancelButtonText: "Cancelar",
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    try {
                        const response = await fetch(`controllers/eliminar_grupo.php?id=${id}`);
                        const data = await response.json();
                        if (!data.success) throw new Error(data.message);
                        return data;
                    } catch (error) {
                        Swal.showValidationMessage(`Error: ${error.message}`);
                    }
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: "¡Eliminado!",
                        text: `El grupo "${nombre}" ha sido eliminado.`,
                        icon: "success",
                        confirmButtonText: "Aceptar"
                    }).then(() => location.reload());
                }
            });
        }
    });
});
</script>