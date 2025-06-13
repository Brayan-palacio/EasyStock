<?php
// Configuración de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Iniciar buffer y sesión
ob_start();
session_start();

// Verificar sesión y permisos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    ob_end_flush();
    exit();
}

// Conexión segura a la base de datos
require 'config/conexion.php';
include 'config/funciones.php';

// Configuración de paginación
$por_pagina = obtenerConfiguracion($conexion, 'registros_por_pagina');
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = max(0, ($pagina - 1) * $por_pagina);

// Filtros y búsqueda con sanitización
$busqueda = isset($_GET['busqueda']) ? trim($conexion->real_escape_string($_GET['busqueda'])) : '';
$filtroEstado = isset($_GET['estado']) && in_array($_GET['estado'], ['Activo', 'Inactivo']) ? $_GET['estado'] : '';

// Construcción dinámica de la consulta
$condiciones = [];
$parametros = [];
$tipos = '';

if (!empty($busqueda)) {
    $condiciones[] = "(nombre LIKE CONCAT('%', ?, '%') OR identificacion LIKE CONCAT('%', ?, '%') OR email LIKE CONCAT('%', ?, '%') OR telefono LIKE CONCAT('%', ?, '%') OR direccion LIKE CONCAT('%', ?, '%'))";
    $parametros = array_merge($parametros, [$busqueda, $busqueda, $busqueda, $busqueda, $busqueda]);
    $tipos .= 'sssss';
}

if (!empty($filtroEstado)) {
    $condiciones[] = "estado = ?";
    $parametros[] = $filtroEstado;
    $tipos .= 's';
}

$where = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';

// Consulta principal con paginación
$sql = "SELECT SQL_CALC_FOUND_ROWS id, nombre, identificacion, email, telefono, direccion, estado,
               DATE_FORMAT(creado_en, '%d/%m/%Y %H:%i') as fecha_registro,
               DATE_FORMAT(actualizado_en, '%d/%m/%Y %H:%i') as fecha_actualizacion
        FROM clientes
        $where
        ORDER BY nombre ASC
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
$total_paginas = max(1, ceil($total_registros / $por_pagina));

// Asegurar que la página actual esté dentro del rango válido
$pagina = max(1, min($pagina, $total_paginas));

include_once('includes/header.php');
?>

<div class="container-fluid py-4">
    <div class="card shadow-lg border-0 rounded-3">
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h2 class="h5 mb-0 fw-bold">
                    <i class="fas fa-users me-2"></i> Gestión de Clientes
                </h2>
                <div class="d-flex gap-2">
                    <a href="controllers/reportes/clientes.php" target="_blank" class="btn btn-sm btn-outline-light" id="btn-reporte">
                        <i class="fas fa-file-pdf me-1"></i> Reporte
                    </a>
                    <?php if(tienePermiso(['Administrador', 'Usuario', 'Supervisor'])): ?>
                    <a href="agregar_cliente.php" class="btn btn-sm btn-success" id="btn-nuevo-cliente">
                        <i class="fas fa-user-plus me-1"></i> Nuevo Cliente
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filtros mejorados -->
        <div class="card-header bg-light">
            <form method="GET" class="row g-2" id="form-filtros">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="busqueda" class="form-control form-control-sm" 
                               placeholder="Buscar por nombre, ID, teléfono..." 
                               value="<?= htmlspecialchars($busqueda) ?>"
                               aria-label="Buscar clientes">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="Activo" <?= $filtroEstado === 'Activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="Inactivo" <?= $filtroEstado === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="registros" class="form-select form-select-sm" aria-label="Registros por página">
                        <option value="10" <?= $por_pagina == 10 ? 'selected' : '' ?>>10 registros</option>
                        <option value="15" <?= $por_pagina == 15 ? 'selected' : '' ?>>15 registros</option>
                        <option value="25" <?= $por_pagina == 25 ? 'selected' : '' ?>>25 registros</option>
                        <option value="50" <?= $por_pagina == 50 ? 'selected' : '' ?>>50 registros</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100" id="btn-filtrar">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="clientes.php" class="btn btn-sm btn-outline-secondary w-100" id="btn-limpiar">
                        <i class="fas fa-sync-alt"></i> Limpiar
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

            <!-- Resumen de resultados mejorado -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="text-muted small">
                    Mostrando <span class="fw-bold"><?= ($inicio + 1) ?></span> a 
                    <span class="fw-bold"><?= min($inicio + $por_pagina, $total_registros) ?></span> de 
                    <span class="fw-bold"><?= $total_registros ?></span> registros
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-info text-dark">
                        <i class="fas fa-users me-1"></i> <?= $total_registros ?> cliente(s)
                    </span>
                    <span class="badge bg-<?= $filtroEstado === 'Inactivo' ? 'warning text-dark' : 'success' ?>">
                        <i class="fas fa-<?= $filtroEstado === 'Inactivo' ? 'ban' : 'check' ?> me-1"></i>
                        <?= $filtroEstado ? htmlspecialchars($filtroEstado) : 'Todos' ?>
                    </span>
                </div>
            </div>

            <!-- Tabla de clientes adaptada -->
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tabla-clientes">
                    <thead class="table-light">
                        <tr>
                            <th width="80">ID</th>
                            <th>Nombre</th>
                            <th>Identificación</th>
                            <th>Contacto</th>
                            <th width="150">Registro/Actualización</th>
                            <?php if(tienePermiso(['Administrador', 'Usuario', 'Supervisor'])): ?>
                            <th width="140" class="text-center">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado->num_rows > 0): ?>
                            <?php while ($cliente = $resultado->fetch_assoc()): ?>
                                <tr class="<?= $cliente['estado'] === 'Inactivo' ? 'table-warning' : '' ?>">
                                    <td class="fw-bold">#<?= str_pad($cliente['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td>
                                        <a href="detalle_cliente.php?id=<?= $cliente['id'] ?>" 
                                           class="text-decoration-none <?= $cliente['estado'] === 'Inactivo' ? 'text-muted' : '' ?>">
                                            <?= htmlspecialchars($cliente['nombre']) ?>
                                            <?php if ($cliente['estado'] === 'Inactivo'): ?>
                                                <span class="badge bg-warning text-dark ms-2">Inactivo</span>
                                            <?php endif; ?>
                                        </a>
                                    </td>
                                    <td class="<?= $cliente['estado'] === 'Inactivo' ? 'text-muted' : '' ?>">
                                        <?= htmlspecialchars($cliente['identificacion']) ?>
                                    </td>
                                    <td>
                                        <div class="<?= $cliente['estado'] === 'Inactivo' ? 'text-muted' : '' ?>">
                                            <?= htmlspecialchars($cliente['email']) ?>
                                        </div>
                                        <div class="text-muted small"><?= htmlspecialchars($cliente['telefono']) ?></div>
                                    </td>
                                    <td class="small <?= $cliente['estado'] === 'Inactivo' ? 'text-muted' : '' ?>">
                                        <div><strong>Registro:</strong> <?= htmlspecialchars($cliente['fecha_registro']) ?></div>
                                        <?php if ($cliente['fecha_actualizacion']): ?>
                                            <div><strong>Actualizado:</strong> <?= htmlspecialchars($cliente['fecha_actualizacion']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if(tienePermiso(['Administrador', 'Usuario', 'Supervisor'])): ?>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="editar_cliente.php?id=<?= $cliente['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary btn-editar"
                                               data-bs-toggle="tooltip" 
                                               title="Editar"
                                               data-id="<?= $cliente['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                                <button class="btn btn-sm btn-outline-<?= $cliente['estado'] === 'Inactivo' ? 'success' : 'danger' ?> btn-estado" 
                                                        data-id="<?= $cliente['id'] ?>"
                                                        data-nombre="<?= htmlspecialchars($cliente['nombre']) ?>"
                                                        data-estado="<?= $cliente['estado'] ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="<?= $cliente['estado'] === 'Inactivo' ? 'Activar' : 'Desactivar' ?>">
                                                    <i class="fas fa-<?= $cliente['estado'] === 'Inactivo' ? 'check' : 'ban' ?>"></i>
                                                </button>
                                                <?php endif; ?>
                                            <?php if ($_SESSION['rol_usuario'] === 'Admin'): ?>
                                            <a href="vehiculos_cliente.php?id=<?= $cliente['id'] ?>" 
                                               class="btn btn-sm btn-outline-info"
                                               data-bs-toggle="tooltip"
                                               title="Vehículos">
                                                <i class="fas fa-car"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                        <h5>No se encontraron clientes</h5>
                                        <p class="text-muted">
                                            <?php if (!empty($busqueda) || !empty($filtroEstado)): ?>
                                                Prueba con otros criterios de búsqueda
                                            <?php else: ?>
                                                No hay clientes registrados. Comienza agregando uno nuevo.
                                            <?php endif; ?>
                                        </p>
                                        <a href="agregar_cliente.php" class="btn btn-primary">
                                            <i class="fas fa-user-plus me-1"></i> Agregar Cliente
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación mejorada -->
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginación" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>"
                               aria-label="Primera">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>"
                               aria-label="Anterior">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>

                        <?php 
                        // Mostrar solo un rango de páginas alrededor de la actual
                        $pagina_inicio = max(1, $pagina - 2);
                        $pagina_fin = min($total_paginas, $pagina + 2);
                        
                        if ($pagina_inicio > 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $pagina_inicio; $i <= $pagina_fin; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                <a class="page-link" 
                                   href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; 
                        
                        if ($pagina_fin < $total_paginas) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        ?>

                        <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>"
                               aria-label="Siguiente">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                            <a class="page-link" 
                               href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>"
                               aria-label="Última">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Cerrar conexión
$conexion->close();
include_once('includes/footer.php'); 
?>

<!-- JavaScript mejorado -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tooltips
    const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(el => new bootstrap.Tooltip(el));

    // Cambiar estado del cliente
    document.querySelectorAll(".btn-estado").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            const estadoActual = this.dataset.estado;
            const nuevoEstado = estadoActual === 'Activo' ? 'Inactivo' : 'Activo';
            const accion = estadoActual === 'Activo' ? 'desactivar' : 'activar';
            
            Swal.fire({
                title: `¿${estadoActual === 'Activo' ? 'Desactivar' : 'Activar'} cliente?`,
                html: `<div class="text-start">
                       <p>Estás a punto de <strong>${accion}</strong> al cliente <strong>${nombre}</strong>.</p>
                       <div class="alert alert-${estadoActual === 'Activo' ? 'warning' : 'success'} p-2 mt-2">
                           <i class="fas fa-${estadoActual === 'Activo' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                           ${estadoActual === 'Activo' ? 
                               'El cliente no podrá realizar nuevas compras hasta que sea reactivado.' : 
                               'El cliente podrá realizar compras nuevamente.'}
                       </div>
                       </div>`,
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: estadoActual === 'Activo' ? "#d33" : "#28a745",
                cancelButtonColor: "#6c757d",
                confirmButtonText: estadoActual === 'Activo' ? "Sí, desactivar" : "Sí, activar",
                cancelButtonText: "Cancelar",
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`controllers/clientes/cambiar_estado_cliente.php?id=${id}&estado=${nuevoEstado}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error("Error en la respuesta del servidor");
                            }
                            return response.json();
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Error: ${error.message}`);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: "¡Éxito!",
                        text: `El cliente "${nombre}" ha sido ${nuevoEstado === 'Activo' ? 'activado' : 'desactivado'} correctamente`,
                        icon: "success",
                        confirmButtonText: "Aceptar"
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        });
    });

    // Evento para el botón de reporte
    document.getElementById('btn-reporte').addEventListener('click', function(e) {
        e.preventDefault();
        const url = this.href;
        
        Swal.fire({
            title: 'Generando reporte',
            html: 'Por favor espera mientras se genera el reporte...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                // Abrir el reporte en una nueva pestaña
                window.open(url, '_blank');
                Swal.close();
            }
        });
    });

    // Mejorar experiencia de filtrado
    const formFiltros = document.getElementById('form-filtros');
    const btnLimpiar = document.getElementById('btn-limpiar');
    
    btnLimpiar.addEventListener('click', function(e) {
        e.preventDefault();
        // Resetear el formulario
        formFiltros.reset();
        // Enviar el formulario
        formFiltros.submit();
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
    opacity: 0.7;
}
#tabla-clientes tbody tr {
    transition: all 0.2s ease;
}
#tabla-clientes tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}
.page-item.active .page-link {
    background-color: #2a5a46;
    border-color: #2a5a46;
}
.page-link {
    color: #2a5a46;
}
</style>