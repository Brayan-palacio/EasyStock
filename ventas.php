<?php
$tituloPagina = 'Gestión de Ventas - EasyStock';
include 'config/conexion.php';
include 'config/funciones.php';
include_once 'includes/header.php';

// Verificar sesión y permisos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Configuración
$registrosPorPagina = obtenerConfiguracion($conexion, 'registros_por_pagina');
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Manejo de mensajes flash
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

// Filtros
$filtros = [
    'busqueda' => $_GET['busqueda'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'cliente_id' => $_GET['cliente_id'] ?? ''
];

// Consulta para obtener el total de ventas (para paginación)
$sqlTotal = "SELECT COUNT(*) AS total FROM ventas v WHERE 1=1";
$sqlData = "SELECT v.id, v.fecha_venta, v.total, c.nombre AS cliente,
                   GROUP_CONCAT(p.descripcion SEPARATOR ', ') AS productos,
                   SUM(dv.cantidad) AS total_items
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            JOIN venta_detalles dv ON v.id = dv.venta_id
            JOIN productos p ON dv.producto_id = p.id
            WHERE 1=1";

// Aplicar filtros
if (!empty($filtros['busqueda'])) {
    $busqueda = "%{$filtros['busqueda']}%";
    $sqlTotal .= " AND (v.id LIKE ? OR c.nombre LIKE ? OR v.total LIKE ?)";
    $sqlData .= " AND (v.id LIKE ? OR c.nombre LIKE ? OR v.total LIKE ?)";
}

if (!empty($filtros['fecha_desde'])) {
    $sqlTotal .= " AND v.fecha_venta >= ?";
    $sqlData .= " AND v.fecha_venta >= ?";
}

if (!empty($filtros['fecha_hasta'])) {
    $sqlTotal .= " AND v.fecha_venta <= ?";
    $sqlData .= " AND v.fecha_venta <= ?";
}

if (!empty($filtros['cliente_id'])) {
    $sqlTotal .= " AND v.cliente_id = ?";
    $sqlData .= " AND v.cliente_id = ?";
}

$sqlData .= " GROUP BY v.id ORDER BY v.fecha_venta DESC LIMIT $offset, $registrosPorPagina";

// Preparar y ejecutar consultas
$stmtTotal = $conexion->prepare($sqlTotal);
$stmtData = $conexion->prepare($sqlData);

// Bind parameters si hay filtros
if (!empty($filtros['busqueda']) || !empty($filtros['fecha_desde']) || !empty($filtros['fecha_hasta']) || !empty($filtros['cliente_id'])) {
    $types = '';
    $params = [];
    
    if (!empty($filtros['busqueda'])) {
        $types .= 'sss';
        array_push($params, $busqueda, $busqueda, $busqueda);
    }
    
    if (!empty($filtros['fecha_desde'])) {
        $types .= 's';
        array_push($params, $filtros['fecha_desde']);
    }
    
    if (!empty($filtros['fecha_hasta'])) {
        $types .= 's';
        array_push($params, $filtros['fecha_hasta']);
    }
    
    if (!empty($filtros['cliente_id'])) {
        $types .= 'i';
        array_push($params, $filtros['cliente_id']);
    }
    
    $stmtTotal->bind_param($types, ...$params);
    $stmtData->bind_param($types, ...$params);
}

$stmtTotal->execute();
$resultadoTotal = $stmtTotal->get_result();
$totalVentas = $resultadoTotal->fetch_assoc()['total'];
$totalPaginas = ceil($totalVentas / $registrosPorPagina);

$stmtData->execute();
$resultado = $stmtData->get_result();
?>

<div class="container-fluid py-4">
    <div class="card shadow-lg border-0 rounded-3">
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0 fw-semibold">
                    <i class="fas fa-receipt me-2"></i> Historial de Ventas
                </h2>
                <div>
                    <?php if(tienePermiso(['Administrador', 'Usuario', 'Supervisor'])): ?>
                    <a href="nueva_venta.php" class="btn btn-sm btn-outline-light me-2">
                        <i class="fas fa-plus-circle me-1"></i> Nueva Venta
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-<?= $mensaje['tipo'] ?> alert-dismissible fade show mb-4" role="alert">
                    <i class="fas <?= $mensaje['tipo'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                    <?= htmlspecialchars($mensaje['texto']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Resumen Estadístico -->
            <div class="row mb-4 g-3">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small">Ventas Totales</h6>
                            <h3 class="mb-0"><?= number_format($totalVentas) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small">Ventas Hoy</h6>
                            <h3 class="mb-0">
                                <?php 
                                $hoy = $conexion->query("SELECT COUNT(*) AS total FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetch_assoc()['total'];
                                echo number_format($hoy);
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small">Promedio por Venta</h6>
                            <h3 class="mb-0">
                                <?php 
                                $promedio = $conexion->query("SELECT AVG(total) AS promedio FROM ventas")->fetch_assoc()['promedio'];
                                echo '$' . number_format($promedio, 0);
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small">Total Vendido</h6>
                            <h3 class="mb-0">
                                <?php 
                                $totalVendido = $conexion->query("SELECT SUM(total) AS total FROM ventas")->fetch_assoc()['total'];
                                echo '$' . number_format($totalVendido ?? 0, 0);
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros Mejorados -->
            <form method="GET" id="filtrosForm" class="row mb-4 g-3">
                <div class="col-md-3">
                    <input type="text" name="busqueda" class="form-control form-control-sm" 
                           placeholder="Buscar..." value="<?= htmlspecialchars($filtros['busqueda']) ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="fecha_desde" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($filtros['fecha_desde']) ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($filtros['fecha_hasta']) ?>">
                </div>
                <div class="col-md-3">
                    <select name="cliente_id" class="form-select form-select-sm">
                        <option value="">Todos los clientes</option>
                        <?php
                        $clientes = $conexion->query("SELECT id, nombre FROM clientes ORDER BY nombre");
                        while ($cliente = $clientes->fetch_assoc()): ?>
                            <option value="<?= $cliente['id'] ?>" <?= $filtros['cliente_id'] == $cliente['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cliente['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                        <a href="ventas.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </div>
            </form>
            
            <!-- Tabla de ventas -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaVentas">
                    <thead class="table-light">
                        <tr>
                            <th width="80">ID</th>
                            <th>Cliente</th>
                            <th>Productos</th>
                            <th width="120">Items</th>
                            <th width="120">Total</th>
                            <th width="120">Fecha</th>
                            <th width="120" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado->num_rows > 0): ?>
                            <?php while ($venta = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold">#<?= str_pad($venta['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= htmlspecialchars($venta['cliente'] ?? 'Consumidor Final') ?></td>
                                    <td>
                                        <span class="d-inline-block text-truncate" style="max-width: 250px;" 
                                              data-bs-toggle="tooltip" title="<?= htmlspecialchars($venta['productos']) ?>">
                                            <?= htmlspecialchars($venta['productos']) ?>
                                        </span>
                                    </td>
                                    <td><?= $venta['total_items'] ?></td>
                                    <td class="fw-bold text-success">$<?= number_format($venta['total'], 0, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($venta['fecha_venta'])) ?></td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="detalle_venta.php?id=<?= $venta['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="tooltip" 
                                               title="Ver detalle">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="controllers/ventas/imprimir_venta.php?id=<?= $venta['id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary"
                                               data-bs-toggle="tooltip" 
                                               title="Imprimir"
                                               target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <?php if ($_SESSION['rol_usuario'] === 'Administrador'): ?>
                                                <button class="btn btn-sm btn-outline-danger btn-eliminar" 
                                                        data-id="<?= $venta['id'] ?>"
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
                                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                        <h5>No hay ventas registradas</h5>
                                        <p class="text-muted"><?= array_filter($filtros) ? 'Intenta con otros filtros' : 'Comienza registrando una nueva venta' ?></p>
                                        <a href="nueva_venta.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle me-1"></i> Nueva Venta
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación Mejorada -->
            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($paginaActual > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaActual - 1])) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $inicio = max(1, $paginaActual - 2);
                        $fin = min($totalPaginas, $paginaActual + 2);
                        
                        if ($inicio > 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $inicio; $i <= $fin; $i++): ?>
                            <li class="page-item <?= $i == $paginaActual ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;
                        
                        if ($fin < $totalPaginas) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        ?>
                        
                        <?php if ($paginaActual < $totalPaginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaActual + 1])) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $totalPaginas])) ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    
document.querySelectorAll(".btn-eliminar").forEach(button => {
    button.addEventListener("click", function() {
        const id = this.dataset.id;
        
        Swal.fire({
            title: "¿Eliminar venta?",
            text: "Esta acción no se puede deshacer",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar",
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const formData = new FormData();
                    formData.append('id', id);

                    const response = await fetch('controllers/ventas/eliminar_venta.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        const error = await response.json();
                        throw new Error(error.message || `Error HTTP ${response.status}`);
                    }
                    
                    return await response.json();
                    
                } catch (error) {
                    Swal.showValidationMessage(
                        `Error: ${error.message}`
                    );
                    return false;
                }
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                if (result.value && result.value.success) {
                    Swal.fire({
                        title: "¡Eliminada!",
                        text: result.value.message,
                        icon: "success"
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        title: "Error",
                        text: result.value?.message || "Error desconocido al eliminar",
                        icon: "error"
                    });
                }
            }
        });
    });
});
    
    // Filtros con AJAX (opcional)
    document.getElementById('filtrosForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const params = new URLSearchParams(formData).toString();
        
        fetch(`ventas.php?${params}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            document.getElementById('tablaVentas').innerHTML = 
                html.match(/<tbody>([\s\S]*?)<\/tbody>/)[0];
        })
        .catch(error => {
            console.error('Error:', error);
            this.submit(); // Fallback a recarga normal
        });
    });
});
</script>