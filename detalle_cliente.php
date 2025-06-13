<?php
// Verificar sesión y permisos
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
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
$stmt_cliente = $conexion->prepare("SELECT * FROM clientes WHERE id = ?");
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

// Obtener vehículos del cliente con paginación
$por_pagina_vehiculos = 5;
$pagina_vehiculos = isset($_GET['pagina_veh']) ? (int)$_GET['pagina_veh'] : 1;
$inicio_vehiculos = max(0, ($pagina_vehiculos - 1) * $por_pagina_vehiculos);

$sql_vehiculos = "SELECT SQL_CALC_FOUND_ROWS * FROM vehiculos 
                 WHERE id_cliente = ? 
                 ORDER BY marca, modelo 
                 LIMIT ?, ?";
$stmt_vehiculos = $conexion->prepare($sql_vehiculos);
$stmt_vehiculos->bind_param("iii", $id_cliente, $inicio_vehiculos, $por_pagina_vehiculos);
$stmt_vehiculos->execute();
$vehiculos = $stmt_vehiculos->get_result();

$total_vehiculos = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas_vehiculos = max(1, ceil($total_vehiculos / $por_pagina_vehiculos));

// Obtener historial reciente con paginación
$por_pagina_historial = 5;
$pagina_historial = isset($_GET['pagina_hist']) ? (int)$_GET['pagina_hist'] : 1;
$inicio_historial = max(0, ($pagina_historial - 1) * $por_pagina_historial);

$sql_historial = "SELECT SQL_CALC_FOUND_ROWS h.*, v.marca, v.modelo, v.matricula, 
                 CONCAT(u.nombre) as tecnico
                 FROM historial_vehiculos h
                 JOIN vehiculos v ON h.id_vehiculo = v.id
                 LEFT JOIN usuarios u ON h.id_tecnico = u.id
                 WHERE v.id_cliente = ?
                 ORDER BY h.fecha DESC, h.id DESC
                 LIMIT ?, ?";
$stmt_historial = $conexion->prepare($sql_historial);
$stmt_historial->bind_param("iii", $id_cliente, $inicio_historial, $por_pagina_historial);
$stmt_historial->execute();
$historial = $stmt_historial->get_result();

$total_historial = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas_historial = max(1, ceil($total_historial / $por_pagina_historial));

// Estadísticas del cliente
$stats = $conexion->query("
    SELECT 
        COUNT(v.id) as total_vehiculos,
        SUM(CASE WHEN v.estado = 'En Taller' THEN 1 ELSE 0 END) as vehiculos_taller,
        COUNT(h.id) as total_servicios,
        MAX(h.fecha) as ultimo_servicio
    FROM vehiculos v
    LEFT JOIN historial_vehiculos h ON v.id = h.id_vehiculo
    WHERE v.id_cliente = $id_cliente
")->fetch_assoc();
?>

<div class="container-fluid mt-4">
    <div class="card shadow-lg rounded-3 border-0">
        <!-- Encabezado con información del cliente -->
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="h5 mb-1 fw-bold">
                        <i class="fas fa-user me-2"></i><?= htmlspecialchars($cliente['nombre']) ?>
                    </h2>
                    <div class="small">
                        <i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($cliente['telefono']) ?>
                        <span class="mx-2">|</span>
                        <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($cliente['email']) ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="clientes.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i> Volver
                    </a>
                    <?php if (in_array($_SESSION['rol_usuario'], ['Administrador', 'vendedor'])): ?>
                        <a href="editar_cliente.php?id=<?= $id_cliente ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit me-1"></i> Editar
                        </a>
                    <?php endif; ?>
                    <a href="controllers/reportes/cliente.php?id=<?= $id_cliente ?>" class="btn btn-sm btn-outline-light" target="_blank">
                        <i class="fas fa-file-pdf me-1"></i> Reporte
                    </a>
                </div>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="card-body bg-light">
            <div class="row">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= $stats['total_vehiculos'] ?></h3>
                            <p class="text-muted mb-0 small">Vehículos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= $stats['vehiculos_taller'] ?></h3>
                            <p class="text-muted mb-0 small">En Taller</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= $stats['total_servicios'] ?></h3>
                            <p class="text-muted mb-0 small">Servicios</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= $stats['ultimo_servicio'] ? date('d/m/Y', strtotime($stats['ultimo_servicio'])) : 'Nunca' ?></h3>
                            <p class="text-muted mb-0 small">Último Servicio</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información detallada del cliente -->
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-info-circle me-2"></i>Información Básica
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">ID Cliente:</dt>
                                <dd class="col-sm-8">#<?= str_pad($cliente['id'], 5, '0', STR_PAD_LEFT) ?></dd>

                                <dt class="col-sm-4">Nombre:</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($cliente['nombre']) ?></dd>

                                <dt class="col-sm-4">Email:</dt>
                                <dd class="col-sm-8">
                                    <a href="mailto:<?= htmlspecialchars($cliente['email']) ?>">
                                        <?= htmlspecialchars($cliente['email']) ?>
                                    </a>
                                </dd>

                                <dt class="col-sm-4">Teléfono:</dt>
                                <dd class="col-sm-8">
                                    <a href="tel:<?= htmlspecialchars($cliente['telefono']) ?>">
                                        <?= htmlspecialchars($cliente['telefono']) ?>
                                    </a>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-map-marker-alt me-2"></i>Información de Contacto
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">Dirección:</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($cliente['direccion']) ?></dd>

                                <dt class="col-sm-4">Ciudad:</dt>
                                <dd class="col-sm-8">N/A</dd>

                                <dt class="col-sm-4">Código Postal:</dt>
                                <dd class="col-sm-8">N/A</dd>

                                <dt class="col-sm-4">Fecha Registro:</dt>
                                <dd class="col-sm-8"><?= date('d/m/Y H:i', strtotime($cliente['creado_en'])) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de vehículos del cliente -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-car me-2"></i>Vehículos del Cliente
                        <span class="badge bg-white text-dark ms-2"><?= $total_vehiculos ?></span>
                    </div>
                    <div>
                        <a href="agregar_vehiculo.php?id_cliente=<?= $id_cliente ?>" class="btn btn-sm btn-light">
                            <i class="fas fa-plus me-1"></i> Añadir Vehículo
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($vehiculos->num_rows > 0): ?>
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
                                    <?php while ($vehiculo = $vehiculos->fetch_assoc()): ?>
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
                                                    <a href="vehiculo_qr.php?id=<?= $vehiculo['id'] ?>" 
                                                       class="btn btn-outline-secondary"
                                                       data-bs-toggle="tooltip"
                                                       title="Código QR">
                                                        <i class="fas fa-qrcode"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación vehículos -->
                        <?php if ($total_paginas_vehiculos > 1): ?>
                            <nav aria-label="Paginación vehículos" class="mt-3">
                                <ul class="pagination pagination-sm justify-content-center">
                                    <li class="page-item <?= $pagina_vehiculos <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" 
                                           href="?id=<?= $id_cliente ?>&pagina_veh=<?= $pagina_vehiculos - 1 ?>#vehiculos">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_paginas_vehiculos; $i++): ?>
                                        <li class="page-item <?= $i == $pagina_vehiculos ? 'active' : '' ?>">
                                            <a class="page-link" 
                                               href="?id=<?= $id_cliente ?>&pagina_veh=<?= $i ?>#vehiculos">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $pagina_vehiculos >= $total_paginas_vehiculos ? 'disabled' : '' ?>">
                                        <a class="page-link" 
                                           href="?id=<?= $id_cliente ?>&pagina_veh=<?= $pagina_vehiculos + 1 ?>#vehiculos">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state py-4">
                            <i class="fas fa-car-side fa-3x text-muted mb-3"></i>
                            <h5>No hay vehículos registrados</h5>
                            <p class="text-muted">Este cliente no tiene vehículos asociados</p>
                            <a href="agregar_vehiculo.php?id_cliente=<?= $id_cliente ?>" class="btn btn-primary mt-2">
                                <i class="fas fa-plus-circle me-1"></i> Agregar Vehículo
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historial reciente -->
            <div class="card" id="historial">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-clipboard-list me-2"></i>Historial Reciente
                        <span class="badge bg-white text-dark ms-2"><?= $total_historial ?></span>
                    </div>
                    <div>
                        <a href="historial_cliente.php?id=<?= $id_cliente ?>" class="btn btn-sm btn-light">
                            Ver completo <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($historial->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th width="120">Fecha</th>
                                        <th>Vehículo</th>
                                        <th width="120">Tipo</th>
                                        <th>Descripción</th>
                                        <th width="120">Técnico</th>
                                        <th width="100" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($registro = $historial->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($registro['fecha'])) ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($registro['marca']) ?> <?= htmlspecialchars($registro['modelo']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($registro['matricula']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?= 
                                                    $registro['tipo'] == 'Mantenimiento' ? 'bg-primary' : 
                                                    ($registro['tipo'] == 'Reparación' ? 'bg-danger' : 'bg-secondary')
                                                ?> rounded-pill">
                                                    <?= htmlspecialchars($registro['tipo']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= strlen($registro['descripcion']) > 50 ? 
                                                    substr(htmlspecialchars($registro['descripcion']), 0, 50) . '...' : 
                                                    htmlspecialchars($registro['descripcion']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($registro['tecnico'] ?? 'N/A') ?></td>
                                            <td class="text-center">
                                                <a href="detalle_historial.php?id=<?= $registro['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   data-bs-toggle="tooltip"
                                                   title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación historial -->
                        <?php if ($total_paginas_historial > 1): ?>
                            <nav aria-label="Paginación historial" class="mt-3">
                                <ul class="pagination pagination-sm justify-content-center">
                                    <li class="page-item <?= $pagina_historial <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" 
                                           href="?id=<?= $id_cliente ?>&pagina_hist=<?= $pagina_historial - 1 ?>#historial">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_paginas_historial; $i++): ?>
                                        <li class="page-item <?= $i == $pagina_historial ? 'active' : '' ?>">
                                            <a class="page-link" 
                                               href="?id=<?= $id_cliente ?>&pagina_hist=<?= $i ?>#historial">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $pagina_historial >= $total_paginas_historial ? 'disabled' : '' ?>">
                                        <a class="page-link" 
                                           href="?id=<?= $id_cliente ?>&pagina_hist=<?= $pagina_historial + 1 ?>#historial">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h5>No hay registros de historial</h5>
                            <p class="text-muted">No se encontraron servicios registrados para este cliente</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

    // Mostrar mensaje de éxito si existe en la URL
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
        const cleanUrl = window.location.pathname + '?id=<?= $id_cliente ?>';
        window.history.replaceState({}, document.title, cleanUrl);
    }
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