<?php
$tituloPagina = 'Reportes de Ventas - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos (deberías implementar tu sistema de permisos aquí)
// if (!tienePermiso('ver_reportes')) {
//     header('Location: acceso_denegado.php');
//     exit;
// }

// Inicializar variables
$ventas = [];
$fecha_inicio = date('Y-m-01'); // Primer día del mes por defecto
$fecha_fin = date('Y-m-d');     // Fecha actual por defecto
$tipo_reporte = 'mensual';      // Valor por defecto
$totalVentas = 0;
$estadisticas = [
    'total_ventas' => 0,
    'cantidad_ventas' => 0,
    'promedio_venta' => 0
];
$graficoData = [];
$clientesFrecuentes = [];
$productosMasVendidos = [];

// Manejo del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_reporte = $_POST['tipo_reporte'] ?? 'mensual';

    // Validar y establecer fechas según el tipo de reporte
    switch ($tipo_reporte) {
        case 'diario':
            $fecha_inicio = $fecha_fin = date('Y-m-d');
            break;
        case 'mensual':
            $fecha_inicio = date('Y-m-01');
            $fecha_fin = date('Y-m-d');
            break;
        case 'rango':
            $fecha_inicio = $_POST['fecha_inicio'] ?? $fecha_inicio;
            $fecha_fin = $_POST['fecha_fin'] ?? $fecha_fin;
            // Validar que fecha fin no sea menor que fecha inicio
            if ($fecha_fin < $fecha_inicio) {
                $fecha_fin = $fecha_inicio;
            }
            break;
        case 'anual':
            $fecha_inicio = date('Y-01-01');
            $fecha_fin = date('Y-m-d');
            break;
    }
}

// Consulta principal de ventas (optimizada con alias cortos)
$sql = "SELECT 
            v.id, 
            v.fecha_venta,
            v.total,
            c.nombre AS cliente_nombre,
            GROUP_CONCAT(DISTINCT CONCAT(p.descripcion, ' (', vd.cantidad, ' x $', FORMAT(vd.precio_unitario, 2), ')') SEPARATOR '; ') AS productos_vendidos,
            SUM(vd.cantidad) AS total_items
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        JOIN venta_detalles vd ON v.id = vd.venta_id 
        JOIN productos p ON vd.producto_id = p.id
        WHERE v.fecha_venta BETWEEN ? AND ?
        GROUP BY v.id
        ORDER BY v.fecha_venta DESC";

if ($stmt = $conexion->prepare($sql)) {
    $stmt->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $ventas = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Estadísticas generales (consulta optimizada)
$sql_estadisticas = "SELECT 
                        SUM(total) AS total_ventas,
                        COUNT(id) AS cantidad_ventas,
                        AVG(total) AS promedio_venta
                     FROM ventas
                     WHERE fecha_venta BETWEEN ? AND ?";

if ($stmt_estadisticas = $conexion->prepare($sql_estadisticas)) {
    $stmt_estadisticas->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt_estadisticas->execute();
    $result_estadisticas = $stmt_estadisticas->get_result();
    $estadisticas = $result_estadisticas->fetch_assoc() ?? $estadisticas;
    $totalVentas = $estadisticas['total_ventas'] ?? 0;
    $stmt_estadisticas->close();
}

// Datos para gráfico de ventas por día (optimizado)
$sql_grafico = "SELECT 
                    DATE(fecha_venta) AS fecha,
                    SUM(total) AS total_dia
                FROM ventas
                WHERE fecha_venta BETWEEN ? AND ?
                GROUP BY DATE(fecha_venta)
                ORDER BY fecha";

if ($stmt_grafico = $conexion->prepare($sql_grafico)) {
    $stmt_grafico->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt_grafico->execute();
    $result_grafico = $stmt_grafico->get_result();
    while ($row = $result_grafico->fetch_assoc()) {
        $graficoData[] = $row;
    }
    $stmt_grafico->close();
}

// Clientes más frecuentes (top 5)
$sql_clientes = "SELECT 
                    c.nombre,
                    COUNT(v.id) AS compras,
                    SUM(v.total) AS total_gastado
                 FROM ventas v
                 JOIN clientes c ON v.cliente_id = c.id
                 WHERE v.fecha_venta BETWEEN ? AND ?
                 GROUP BY c.id
                 ORDER BY compras DESC, total_gastado DESC
                 LIMIT 5";

if ($stmt_clientes = $conexion->prepare($sql_clientes)) {
    $stmt_clientes->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt_clientes->execute();
    $result_clientes = $stmt_clientes->get_result();
    $clientesFrecuentes = $result_clientes->fetch_all(MYSQLI_ASSOC);
    $stmt_clientes->close();
}

// Productos más vendidos (top 5)
$sql_productos = "SELECT 
                    p.descripcion,
                    SUM(vd.cantidad) AS cantidad_vendida,
                    SUM(vd.cantidad * vd.precio_unitario) AS total_ventas
                 FROM venta_detalles vd
                 JOIN productos p ON vd.producto_id = p.id
                 JOIN ventas v ON vd.venta_id = v.id
                 WHERE v.fecha_venta BETWEEN ? AND ?
                 GROUP BY p.id
                 ORDER BY cantidad_vendida DESC, total_ventas DESC
                 LIMIT 5";

if ($stmt_productos = $conexion->prepare($sql_productos)) {
    $stmt_productos->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();
    $productosMasVendidos = $result_productos->fetch_all(MYSQLI_ASSOC);
    $stmt_productos->close();
}
?>

<div class="container-fluid py-4">
    <?php include 'ventas_navbar.php'; ?>
    
    <div class="card shadow-sm rounded-3 border-0 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h4 fw-bold mb-0 text-white">
                    <i class="fas fa-chart-pie me-2"></i>Reportes de Ventas
                </h1>
                
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" id="dropdownExportar" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-download me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="dropdownExportar">
                        <li>
                            <form method="POST" action="controllers/reportes/generar_reporte.php" target="_blank" class="dropdown-item">
                                <input type="hidden" name="tipo_reporte" value="<?= htmlspecialchars($tipo_reporte) ?>">
                                <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                                <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                                <button type="submit" name="formato" value="pdf" class="btn btn-link text-decoration-none w-100 text-start">
                                    <i class="fas fa-file-pdf text-danger me-2"></i> PDF
                                </button>
                            </form>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="controllers/reportes/generar_reporte.php" target="_blank" class="dropdown-item">
                                <input type="hidden" name="tipo_reporte" value="<?= htmlspecialchars($tipo_reporte) ?>">
                                <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                                <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                                <button type="submit" name="formato" value="excel" class="btn btn-link text-decoration-none w-100 text-start">
                                    <i class="fas fa-file-excel text-success me-2"></i> Excel
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Filtros de reporte -->
            <form method="POST" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="tipo_reporte" class="form-label small fw-bold text-muted">Tipo de Reporte</label>
                        <select class="form-select form-select-sm" name="tipo_reporte" id="tipo_reporte" required>
                            <option value="diario" <?= ($tipo_reporte == 'diario') ? 'selected' : '' ?>>Ventas Diarias</option>
                            <option value="mensual" <?= ($tipo_reporte == 'mensual') ? 'selected' : '' ?>>Ventas Mensuales</option>
                            <option value="anual" <?= ($tipo_reporte == 'anual') ? 'selected' : '' ?>>Ventas Anuales</option>
                            <option value="rango" <?= ($tipo_reporte == 'rango') ? 'selected' : '' ?>>Rango Personalizado</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3" id="rango-fechas" style="display: <?= ($tipo_reporte == 'rango') ? 'block' : 'none' ?>;">
                        <label for="fecha_inicio" class="form-label small fw-bold text-muted">Fecha Inicio</label>
                        <input type="date" class="form-control form-control-sm" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    
                    <div class="col-md-3" id="rango-fechas-fin" style="display: <?= ($tipo_reporte == 'rango') ? 'block' : 'none' ?>;">
                        <label for="fecha_fin" class="form-label small fw-bold text-muted">Fecha Fin</label>
                        <input type="date" class="form-control form-control-sm" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>

            <!-- Resumen estadístico -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary bg-opacity-10 border-primary border-opacity-25 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle text-muted">Total Ventas</h6>
                                    <h3 class="card-title text-primary">$<?= number_format($totalVentas, 0, '', '.') ?></h3>
                                </div>
                                <i class="fas fa-dollar-sign text-primary opacity-25 fa-2x"></i>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    <?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success bg-opacity-10 border-success border-opacity-25 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle text-muted">Ventas Promedio</h6>
                                    <h3 class="card-title text-success">$<?= number_format($estadisticas['promedio_venta'] ?? 0, 0, '', '.') ?></h3>
                                </div>
                                <i class="fas fa-chart-bar text-success opacity-25 fa-2x"></i>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-success bg-opacity-10 text-success">
                                    <?= $estadisticas['cantidad_ventas'] ?? 0 ?> transacciones
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info bg-opacity-10 border-info border-opacity-25 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle text-muted">Clientes Activos</h6>
                                    <h3 class="card-title text-info"><?= count($clientesFrecuentes) ?></h3>
                                </div>
                                <i class="fas fa-users text-info opacity-25 fa-2x"></i>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-info bg-opacity-10 text-info">
                                    Top 5 clientes frecuentes
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning bg-opacity-10 border-warning border-opacity-25 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle text-muted">Productos Vendidos</h6>
                                    <h3 class="card-title text-warning"><?= array_sum(array_column($productosMasVendidos, 'cantidad_vendida')) ?></h3>
                                </div>
                                <i class="fas fa-boxes text-warning opacity-25 fa-2x"></i>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-warning bg-opacity-10 text-warning">
                                    Top 5 productos más vendidos
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico y datos principales -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="mb-0 d-flex text-white align-items-center">
                                <i class="fas fa-chart-line me-2"></i>
                                Evolución de Ventas
                                <small class="text-muted ms-auto">
                                    <?= strtoupper($tipo_reporte) ?>
                                </small>
                            </h5>
                        </div>
                        <div class="card-body pt-0">
                            <div class="chart-container" style="position: relative; height: 300px;">
                                <canvas id="ventasChart"></canvas>
                            </div>
                            <div class="mt-3 text-end">
                                <small class="text-muted">
                                    Actualizado: <?= date('d/m/Y H:i') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabla de ventas detalladas -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="mb-0 d-flex text-white align-items-center">
                                <i class="fas fa-list me-2"></i>
                                Detalle de Ventas
                                <span class="badge bg-primary ms-auto">
                                    <?= count($ventas) ?> registros
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($ventas)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover" id="tablaVentas">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="50">#</th>
                                                <th width="120">Fecha</th>
                                                <th>Cliente</th>
                                                <th width="150">Productos</th>
                                                <th width="120" class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ventas as $index => $venta): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td>
                                                <td><?= htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor final') ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary py-0 px-2" data-bs-toggle="modal" data-bs-target="#productosModal<?= $venta['id'] ?>">
                                                        <i class="fas fa-eye me-1"></i> <?= substr_count($venta['productos_vendidos'], ';') + 1 ?> items
                                                    </button>
                                                    
                                                    <!-- Modal para productos -->
                                                    <div class="modal fade" id="productosModal<?= $venta['id'] ?>" tabindex="-1" aria-labelledby="productosModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content border-0 shadow">
                                                                <div class="modal-header bg-primary text-white">
                                                                    <h5 class="modal-title" id="productosModalLabel">
                                                                        <i class="fas fa-receipt me-2"></i>Detalle de Venta #<?= $venta['id'] ?>
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="row mb-3">
                                                                        <div class="col-md-6">
                                                                            <p class="mb-1"><strong><i class="fas fa-calendar-alt me-2"></i>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></p>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <p class="mb-1"><strong><i class="fas fa-user me-2"></i>Cliente:</strong> <?= htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor final') ?></p>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="alert alert-primary py-2">
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                            <strong>Total Venta:</strong>
                                                                            <span class="fw-bold fs-5">$<?= number_format($venta['total'], 0, '', '.') ?></span>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <h6 class="mt-3 mb-2"><i class="fas fa-boxes me-2"></i>Productos:</h6>
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-bordered">
                                                                            <thead class="table-light">
                                                                                <tr>
                                                                                    <th>Producto</th>
                                                                                    <th width="100" class="text-center">Cantidad</th>
                                                                                    <th width="120" class="text-end">P. Unitario</th>
                                                                                    <th width="120" class="text-end">Subtotal</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach (explode('; ', $venta['productos_vendidos']) as $producto): 
                                                                                    // Parsear el formato: "Producto (2 x $10.50)"
                                                                                    preg_match('/^(.*?)\s*\((\d+)\s*x\s*\$([\d,]+\.\d{2})\)$/', $producto, $matches);
                                                                                    if (count($matches) === 4) {
                                                                                        $nombre = $matches[1];
                                                                                        $cantidad = $matches[2];
                                                                                        $precio = str_replace(',', '', $matches[3]);
                                                                                        $subtotal = $cantidad * $precio;
                                                                                ?>
                                                                                <tr>
                                                                                    <td><?= htmlspecialchars($nombre) ?></td>
                                                                                    <td class="text-center"><?= $cantidad ?></td>
                                                                                    <td class="text-end">$<?= number_format($precio, 0, '', '.') ?></td>
                                                                                    <td class="text-end">$<?= number_format($subtotal, 0, '', '.') ?></td>
                                                                                </tr>
                                                                                <?php } endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                                                                        <i class="fas fa-times me-1"></i> Cerrar
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end fw-bold">$<?= number_format($venta['total'], 0, '', '.') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning d-flex align-items-center">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    No se encontraron ventas para el periodo seleccionado.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Top clientes -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="mb-0 d-flex text-white align-items-center">
                                <i class="fas fa-user me-2"></i>
                                Clientes Frecuentes
                                <span class="badge bg-primary ms-auto">
                                    Top 5
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($clientesFrecuentes)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($clientesFrecuentes as $cliente): ?>
                                    <li class="list-group-item border-0 py-2 px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($cliente['nombre']) ?></h6>
                                                <small class="text-muted"><?= $cliente['compras'] ?> compras</small>
                                            </div>
                                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                                $<?= number_format($cliente['total_gastado'], 0, '', '.') ?>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No hay datos de clientes frecuentes.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Top productos -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="mb-0 d-flex text-white align-items-center">
                                <i class="fas fa-boxes me-2"></i>
                                Productos Más Vendidos
                                <span class="badge bg-primary ms-auto">
                                    Top 5
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($productosMasVendidos)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($productosMasVendidos as $producto): ?>
                                    <li class="list-group-item border-0 py-2 px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($producto['descripcion']) ?></h6>
                                                <small class="text-muted"><?= $producto['cantidad_vendida'] ?> unidades</small>
                                            </div>
                                            <span class="badge bg-success bg-opacity-10 text-success">
                                                $<?= number_format($producto['total_ventas'], 0, '', '.') ?>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No hay datos de productos vendidos.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts para gráficos y funcionalidad -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar campos de fecha según tipo de reporte
    const tipoReporte = document.getElementById('tipo_reporte');
    const rangoFechas = document.getElementById('rango-fechas');
    const rangoFechasFin = document.getElementById('rango-fechas-fin');
    
    tipoReporte.addEventListener('change', function() {
        const mostrar = this.value === 'rango';
        rangoFechas.style.display = mostrar ? 'block' : 'none';
        rangoFechasFin.style.display = mostrar ? 'block' : 'none';
    });
    
    // Configurar gráfico de ventas
    const ctx = document.getElementById('ventasChart').getContext('2d');
    const ventasChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?= implode(',', array_map(function($item) {
                return "'" . date('d/m', strtotime($item['fecha'])) . "'";
            }, $graficoData)) ?>],
            datasets: [{
                label: 'Ventas por día',
                data: [<?= implode(',', array_column($graficoData, 'total_dia')) ?>],
                backgroundColor: 'rgba(13, 110, 253, 0.05)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 2,
                tension: 0.1,
                fill: true,
                pointBackgroundColor: 'rgba(13, 110, 253, 1)',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: { size: 14 },
                    bodyFont: { size: 12 },
                    callbacks: {
                        label: function(context) {
                            return ' $' + context.raw.toLocaleString('es-AR');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString('es-AR');
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Inicializar DataTable para la tabla de ventas (si se incluye la librería)
    if ($.fn.DataTable) {
        $('#tablaVentas').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
            },
            dom: '<"top"f>rt<"bottom"lip><"clear">',
            pageLength: 10,
            order: [[1, 'desc']]
        });
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>