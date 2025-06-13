<?php
// Verificar sesión y permisos
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar permisos (roles permitidos)
$rolesPermitidos = ['Administrador', 'mecanico', 'supervisor']; // Agregar más roles si es necesario
if (!in_array($_SESSION['rol_usuario'], $rolesPermitidos)) {
    header("Location: acceso_denegado.php");
    exit();
}

include 'config/conexion.php';
include_once('includes/header.php');

// Validar y sanitizar ID del cliente
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'ID de cliente no válido'
    ];
    header("Location: clientes.php");
    exit();
}

$id_cliente = (int)$_GET['id'];

// Obtener información del cliente con consulta preparada
$stmt_cliente = $conexion->prepare("SELECT id, nombre, telefono FROM clientes WHERE id = ?");
$stmt_cliente->bind_param("i", $id_cliente);
$stmt_cliente->execute();
$resultado_cliente = $stmt_cliente->get_result();

if ($resultado_cliente->num_rows === 0) {
    $_SESSION['mensaje'] = [
        'tipo' => 'warning',
        'texto' => 'Cliente no encontrado'
    ];
    header("Location: clientes.php");
    exit();
}

$cliente = $resultado_cliente->fetch_assoc();

// Configuración de paginación
$por_pagina = isset($_GET['registros']) && in_array($_GET['registros'], [5, 10, 15, 25]) ? (int)$_GET['registros'] : 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = max(0, ($pagina - 1) * $por_pagina); // Evitar valores negativos

// Filtros
$busqueda = isset($_GET['busqueda']) ? trim($conexion->real_escape_string($_GET['busqueda'])) : '';
$filtroEstado = isset($_GET['estado']) && in_array($_GET['estado'], ['Activo', 'Inactivo', 'En Taller']) ? $_GET['estado'] : '';

// Construcción dinámica de la consulta
$condiciones = ["v.id_cliente = ?"];
$parametros = [$id_cliente];
$tipos = 'i'; // Tipo para id_cliente

if (!empty($busqueda)) {
    $condiciones[] = "(v.marca LIKE ? OR v.modelo LIKE ? OR v.matricula LIKE ?)";
    array_push($parametros, "%$busqueda%", "%$busqueda%", "%$busqueda%");
    $tipos .= 'sss';
}

if (!empty($filtroEstado)) {
    $condiciones[] = "v.estado = ?";
    $parametros[] = $filtroEstado;
    $tipos .= 's';
}

$where = implode(' AND ', $condiciones);

// Consulta principal con paginación
$sql = "SELECT SQL_CALC_FOUND_ROWS v.* 
        FROM vehiculos v
        WHERE $where
        ORDER BY v.marca, v.modelo 
        LIMIT ?, ?";

$stmt = $conexion->prepare($sql);

// Añadir parámetros de paginación
array_push($parametros, $inicio, $por_pagina);
$tipos .= 'ii';

// Vincular parámetros dinámicamente
$stmt->bind_param($tipos, ...$parametros);
$stmt->execute();
$resultado = $stmt->get_result();

// Total de registros para paginación
$total_registros = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas = max(1, ceil($total_registros / $por_pagina)); // Mínimo 1 página

// Obtener estados únicos para filtro
$estadosVehiculos = $conexion->query("SELECT DISTINCT estado FROM vehiculos WHERE id_cliente = $id_cliente")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="card shadow-lg rounded-3 border-0">
        <!-- Encabezado con información del cliente -->
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="h5 mb-1 fw-bold">
                        <i class="fas fa-car me-2"></i>Vehículos de <?= htmlspecialchars($cliente['nombre']) ?>
                    </h2>
                    <div class="small">
                        <i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($cliente['telefono']) ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="clientes.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i> Volver
                    </a>
                    <a href="agregar_vehiculo.php?id_cliente=<?= $id_cliente ?>" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i> Nuevo Vehículo
                    </a>
                </div>
            </div>
        </div>

        <!-- Filtros avanzados -->
        <div class="card-header bg-light">
            <form method="GET" class="row g-2">
                <input type="hidden" name="id" value="<?= $id_cliente ?>">
                
                <div class="col-md-4">
                    <input type="text" name="busqueda" class="form-control form-control-sm" 
                           placeholder="Buscar por marca, modelo o matrícula" 
                           value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                
                <div class="col-md-3">
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estadosVehiculos as $estado): ?>
                            <option value="<?= htmlspecialchars($estado['estado']) ?>" 
                                <?= $filtroEstado === $estado['estado'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado['estado']) ?>
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
                    <a href="vehiculos_cliente.php?id=<?= $id_cliente ?>" class="btn btn-sm btn-outline-secondary w-100">
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
                            <th>Marca/Modelo</th>
                            <th width="100">Año</th>
                            <th width="120">Matrícula</th>
                            <th width="100">Color</th>
                            <th width="120">Estado</th>
                            <th width="150" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado->num_rows > 0): ?>
                            <?php while ($vehiculo = $resultado->fetch_assoc()): ?>
                                <tr>
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
                                        <div class="d-flex align-items-center">
                                            <span class="color-indicator" style="background-color: <?= htmlspecialchars($vehiculo['color']) ?>;"></span>
                                            <?= htmlspecialchars($vehiculo['color']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= 
                                            $vehiculo['estado'] == 'Activo' ? 'bg-success' : 
                                            ($vehiculo['estado'] == 'En Taller' ? 'bg-warning text-dark' : 'bg-secondary')
                                        ?> rounded-pill">
                                            <?= htmlspecialchars($vehiculo['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="historial_vehiculo.php?id=<?= $vehiculo['id'] ?>" 
                                               class="btn btn-outline-info"
                                               data-bs-toggle="tooltip"
                                               title="Historial">
                                                <i class="fas fa-history"></i>
                                            </a>
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
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-car-side fa-3x text-muted mb-3"></i>
                                        <h5>No se encontraron vehículos</h5>
                                        <p class="text-muted">
                                            <?php if (!empty($busqueda) || !empty($filtroEstado)): ?>
                                                Prueba con otros criterios de búsqueda
                                            <?php else: ?>
                                                Este cliente no tiene vehículos registrados
                                            <?php endif; ?>
                                        </p>
                                        <a href="agregar_vehiculo.php?id_cliente=<?= $id_cliente ?>" class="btn btn-primary mt-2">
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

    // Eliminar vehículo con confirmación
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
                           Esta acción no se puede deshacer y eliminará el historial asociado.
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
});
</script>

<style>
.color-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 6px;
    border: 1px solid #dee2e6;
}
.empty-state {
    padding: 2rem;
    text-align: center;
}
.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
}
</style>