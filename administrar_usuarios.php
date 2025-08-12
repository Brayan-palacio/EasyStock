<?php
$tituloPagina = 'Administrar Usuarios - EasyStock';
include 'config/conexion.php';
include 'config/funciones.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Configuración de paginación
$registrosPorPagina = obtenerConfiguracion($conexion, 'registros_por_pagina');
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Filtros
$busqueda = isset($_GET['busqueda']) ? $conexion->real_escape_string($_GET['busqueda']) : '';
$filtroEstado = isset($_GET['estado']) && in_array($_GET['estado'], ['Activo', 'Inactivo']) ? $_GET['estado'] : '';
$filtroGrupo = isset($_GET['grupo']) ? (int)$_GET['grupo'] : 0;

// Consulta principal con joins
$query = "SELECT SQL_CALC_FOUND_ROWS 
            u.id, u.nombre, u.usuario, u.rol_usuario, u.estado, 
            u.ultimo_login, u.imagen, g.nombre AS grupo_nombre
          FROM usuarios u
          JOIN grupo g ON u.grupo_id = g.id
          WHERE (? = '' OR u.nombre LIKE CONCAT('%', ?, '%') OR u.usuario LIKE CONCAT('%', ?, '%'))
          AND (? = '' OR u.estado = ?)
          AND (? = 0 OR u.grupo_id = ?)
          ORDER BY u.nombre ASC
          LIMIT ? OFFSET ?";

$stmt = $conexion->prepare($query);
$stmt->bind_param('ssssssiii', $busqueda, $busqueda, $busqueda, $filtroEstado, $filtroEstado, $filtroGrupo, $filtroGrupo, $registrosPorPagina, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Total de registros
$totalRegistros = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Obtener grupos para filtro
$grupos = $conexion->query("SELECT id, nombre FROM grupo WHERE estado = 'Activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid py-4">
    <?php include 'accesos_navbar.php'; ?>
    <div class="card shadow-lg border-0 rounded-3">
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="h5 mb-0 fw-semibold">
                    <i class="fas fa-users-cog me-2"></i> Administrar Usuarios
                </h2>
                <a href="agregar_usuario.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-plus-circle me-1"></i> Nuevo Usuario
                </a>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card-header border-top bg-light">
            <form method="get" class="row g-2">
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
                <div class="col-md-3">
                    <select name="grupo" class="form-select form-select-sm">
                        <option value="0">Todos los grupos</option>
                        <?php foreach ($grupos as $grupo): ?>
                            <option value="<?= $grupo['id'] ?>" <?= $filtroGrupo == $grupo['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($grupo['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="administrar_usuarios.php" class="btn btn-sm btn-outline-secondary w-100">
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
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Grupo</th>
                            <th width="120">Estado</th>
                            <th width="150">Último login</th>
                            <th width="120" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($usuario = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?= $usuario['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
    <img src="<?= !empty($usuario['imagen']) ? 'assets/img/usuarios/' . htmlspecialchars($usuario['imagen']) : 'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%236c757d\'><path d=\'M12 2a5 5 0 1 0 5 5 5 5 0 0 0-5-5zm0 8a3 3 0 1 1 3-3 3 3 0 0 1-3 3zm9 11v-1a7 7 0 0 0-7-7h-4a7 7 0 0 0-7 7v1h2v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1z\'/></svg>' ?>" 
         class="rounded-circle me-2 bg-light" 
         width="32" 
         height="32" 
         alt="Foto perfil"
         onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%236c757d\'><path d=\'M12 2a5 5 0 1 0 5 5 5 5 0 0 0-5-5zm0 8a3 3 0 1 1 3-3 3 3 0 0 1-3 3zm9 11v-1a7 7 0 0 0-7-7h-4a7 7 0 0 0-7 7v1h2v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1z\'/></svg>'; this.classList.add('bg-light')">
    <?= htmlspecialchars($usuario['nombre']) ?>
</div>
                                    </td>
                                    <td>@<?= htmlspecialchars($usuario['usuario']) ?></td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info">
                                            <?= htmlspecialchars($usuario['rol_usuario']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['grupo_nombre']) ?></td>
                                    <td>
                                        <span class="badge <?= $usuario['estado'] == 'Activo' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                                            <?= htmlspecialchars($usuario['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca' ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="editar_usuario.php?id=<?= $usuario['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="tooltip" 
                                               title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($_SESSION['id_usuario'] != $usuario['id']): ?>
                                                <button class="btn btn-sm btn-outline-danger btn-eliminar" 
                                                        data-id="<?= $usuario['id'] ?>"
                                                        data-nombre="<?= htmlspecialchars($usuario['nombre']) ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                        <h5>No se encontraron usuarios</h5>
                                        <p class="text-muted"><?= ($busqueda || $filtroEstado || $filtroGrupo) ? 'Prueba con otros filtros' : 'Comienza agregando un nuevo usuario' ?></p>
                                        <a href="agregar_usuario.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle me-1"></i> Agregar Usuario
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
                            <a class="page-link" href="?pagina=<?= $paginaActual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($filtroEstado) ?>&grupo=<?= $filtroGrupo ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == $paginaActual ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($filtroEstado) ?>&grupo=<?= $filtroGrupo ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($filtroEstado) ?>&grupo=<?= $filtroGrupo ?>">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Eliminar usuario
    document.querySelectorAll(".btn-eliminar").forEach(button => {
        button.addEventListener("click", function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            
            Swal.fire({
                title: `¿Eliminar usuario "${nombre}"?`,
                html: `<div class="text-start">
                       <p>Esta acción eliminará permanentemente al usuario <strong>${nombre}</strong>.</p>
                       <div class="alert alert-danger p-2 mt-2">
                           <i class="fas fa-exclamation-triangle me-2"></i>
                           ¡No podrás revertir esto!
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
                    return fetch(`controllers/eliminar_usuario.php?id=${id}`)
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
                        title: "¡Eliminado!",
                        text: `El usuario "${nombre}" ha sido eliminado.`,
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