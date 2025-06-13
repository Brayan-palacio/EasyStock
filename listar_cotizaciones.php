<?php
$tituloPagina = 'Listado de Cotizaciones - EasyStock';
session_start();

// Configuración de seguridad de headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['error'] = "Debe iniciar sesión para acceder a esta función";
    header("Location: login.php");
    exit;
}

include 'config/conexion.php';
include 'includes/header.php';

// Paginación
$porPagina = 10;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina - 1) * $porPagina;

// Filtros
$filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtroCliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$filtroFecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';

// Consulta base con filtros
$sql = "SELECT c.*, u.nombre as usuario_nombre 
        FROM cotizaciones c
        JOIN usuarios u ON c.usuario_id = u.id
        WHERE 1=1";

$params = [];
$types = '';

if ($filtroEstado && in_array($filtroEstado, ['pendiente', 'aprobada', 'rechazada'])) {
    $sql .= " AND c.estado = ?";
    $params[] = $filtroEstado;
    $types .= 's';
}

if (!empty($filtroCliente)) {
    $sql .= " AND c.cliente LIKE ?";
    $params[] = "%$filtroCliente%";
    $types .= 's';
}

if (!empty($filtroFecha)) {
    $sql .= " AND DATE(c.fecha_creacion) = ?";
    $params[] = $filtroFecha;
    $types .= 's';
}

// Consulta para total de registros
$stmtTotal = $conexion->prepare($sql);
if (!empty($params)) {
    $stmtTotal->bind_param($types, ...$params);
}
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->num_rows;
$stmtTotal->close();

// Consulta principal con paginación
$sql .= " ORDER BY c.fecha_creacion DESC LIMIT ?, ?";
$params[] = $inicio;
$params[] = $porPagina;
$types .= 'ii';

$stmt = $conexion->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<div class="container mt-4">
    <div class="card p-4 shadow-sm" style="border-top: 4px solid #d4af37;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="color: #1a3a2f;">Listado de Cotizaciones</h2>
            <a href="cotizaciones.php" class="btn" style="background-color: #1a3a2f; color: white;">
                <i class="fas fa-plus me-2"></i>Nueva Cotización
            </a>
        </div>

        <!-- Filtros -->
        <form method="get" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select id="estado" name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                        <option value="aprobada" <?= $filtroEstado === 'aprobada' ? 'selected' : '' ?>>Aprobadas</option>
                        <option value="rechazada" <?= $filtroEstado === 'rechazada' ? 'selected' : '' ?>>Rechazadas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="cliente" class="form-label">Cliente</label>
                    <input type="text" id="cliente" name="cliente" class="form-control" 
                           value="<?= htmlspecialchars($filtroCliente) ?>" placeholder="Filtrar por cliente">
                </div>
                <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha</label>
                    <input type="date" id="fecha" name="fecha" class="form-control" value="<?= $filtroFecha ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn me-2" style="background-color: #1a3a2f; color: white;">
                        <i class="fas fa-filter me-2"></i>Filtrar
                    </button>
                    <a href="listar_cotizaciones.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                </div>
            </div>
        </form>

        <!-- Tabla de cotizaciones -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead style="background-color: #1a3a2f; color: white;">
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Válida hasta</th>
                        <th>Estado</th>
                        <th>Creada por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado->num_rows > 0): ?>
                        <?php while ($cotizacion = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?= $cotizacion['id'] ?></td>
                                <td><?= htmlspecialchars($cotizacion['cliente']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($cotizacion['fecha_creacion'])) ?></td>
                                <td>$<?= number_format($cotizacion['total'], 2, ',', '.') ?></td>
                                <td>
                                    <?php 
                                    $fechaVencimiento = date('d/m/Y', strtotime($cotizacion['fecha_creacion'] . ' + ' . $cotizacion['validez_dias'] . ' days'));
                                    $hoy = new DateTime();
                                    $vencimiento = new DateTime($cotizacion['fecha_creacion']);
                                    $vencimiento->add(new DateInterval('P' . $cotizacion['validez_dias'] . 'D'));
                                    
                                    echo $fechaVencimiento;
                                    if ($hoy > $vencimiento && $cotizacion['estado'] === 'pendiente') {
                                        echo ' <span class="badge bg-warning text-dark">Vencida</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?= $cotizacion['estado'] === 'aprobada' ? 'bg-success' : 
                                           ($cotizacion['estado'] === 'rechazada' ? 'bg-danger' : 'bg-secondary') ?>">
                                        <?= ucfirst($cotizacion['estado']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($cotizacion['usuario_nombre']) ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="ver_cotizacion.php?id=<?= $cotizacion['id'] ?>" 
                                           class="btn btn-sm" style="background-color: #2a5a46; color: white;"
                                           title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="editar_cotizacion.php?id=<?= $cotizacion['id'] ?>" 
                                           class="btn btn-sm btn-primary" title="Editar"
                                           <?= $cotizacion['estado'] !== 'pendiente' ? 'disabled' : '' ?>>
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="controllers/cotizacion/imprimir_cotizacion.php?id=<?= $cotizacion['id'] ?>" 
                                           class="btn btn-sm" style="background-color: #d4af37; color: #1a3a2f;"
                                           title="Imprimir" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-info-circle fa-2x mb-3" style="color: #2a5a46;"></i>
                                <h5>No se encontraron cotizaciones</h5>
                                <p class="text-muted">Utilice los filtros para ajustar su búsqueda</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalRegistros > $porPagina): ?>
            <nav aria-label="Paginación de cotizaciones">
                <ul class="pagination justify-content-center">
                    <?php
                    $totalPaginas = ceil($totalRegistros / $porPagina);
                    $paginasAMostrar = 5;
                    $inicioPaginas = max(1, min($pagina - floor($paginasAMostrar / 2), $totalPaginas - $paginasAMostrar + 1));
                    $finPaginas = min($totalPaginas, $inicioPaginas + $paginasAMostrar - 1);
                    
                    // Botón Anterior
                    if ($pagina > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>" 
                               aria-label="Anterior">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif;
                    
                    // Páginas
                    for ($i = $inicioPaginas; $i <= $finPaginas; $i++): ?>
                        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor;
                    
                    // Botón Siguiente
                    if ($pagina < $totalPaginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>" 
                               aria-label="Siguiente">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="text-center text-muted">
                Mostrando <?= ($inicio + 1) ?> a <?= min($inicio + $porPagina, $totalRegistros) ?> de <?= $totalRegistros ?> cotizaciones
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>