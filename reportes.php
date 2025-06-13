<?php
$tituloPagina = 'Reportes de Ventas - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos


// Inicializar variables
$ventas = [];
$fecha_inicio = date('Y-m-01'); // Primer día del mes por defecto
$fecha_fin = date('Y-m-d');     // Fecha actual por defecto
$tipo_reporte = 'mensual';      // Valor por defecto
$totalVentas = 0;
$totalProductosVendidos = 0;
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

// Consulta principal de ventas
$sql = "SELECT 
            ventas.id, 
            ventas.fecha_venta,
            ventas.total,
            clientes.nombre AS cliente_nombre,
            GROUP_CONCAT(DISTINCT CONCAT(productos.descripcion, ' (', venta_detalles.cantidad, ' x $', FORMAT(venta_detalles.precio_unitario, 2), ')') SEPARATOR '; ') AS productos_vendidos,
            SUM(venta_detalles.cantidad) AS total_items
        FROM ventas 
        LEFT JOIN clientes ON ventas.cliente_id = clientes.id
        JOIN venta_detalles ON ventas.id = venta_detalles.venta_id 
        JOIN productos ON venta_detalles.producto_id = productos.id
        WHERE ventas.fecha_venta BETWEEN ? AND ?
        GROUP BY ventas.id
        ORDER BY ventas.fecha_venta DESC";

if ($stmt = $conexion->prepare($sql)) {
    $stmt->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $ventas = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Estadísticas generales
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
    $estadisticas = $result_estadisticas->fetch_assoc();
    $totalVentas = $estadisticas['total_ventas'] ?? 0;
    $stmt_estadisticas->close();
}

// Datos para gráfico de ventas por día
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

// Clientes más frecuentes
$sql_clientes = "SELECT 
                    clientes.nombre,
                    COUNT(ventas.id) AS compras,
                    SUM(ventas.total) AS total_gastado
                 FROM ventas
                 JOIN clientes ON ventas.cliente_id = clientes.id
                 WHERE ventas.fecha_venta BETWEEN ? AND ?
                 GROUP BY clientes.id
                 ORDER BY compras DESC
                 LIMIT 5";

if ($stmt_clientes = $conexion->prepare($sql_clientes)) {
    $stmt_clientes->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt_clientes->execute();
    $result_clientes = $stmt_clientes->get_result();
    $clientesFrecuentes = $result_clientes->fetch_all(MYSQLI_ASSOC);
    $stmt_clientes->close();
}

// Productos más vendidos
$sql_productos = "SELECT 
                    productos.descripcion,
                    SUM(venta_detalles.cantidad) AS cantidad_vendida,
                    SUM(venta_detalles.cantidad * venta_detalles.precio_unitario) AS total_ventas
                 FROM venta_detalles
                 JOIN productos ON venta_detalles.producto_id = productos.id
                 JOIN ventas ON venta_detalles.venta_id = ventas.id
                 WHERE ventas.fecha_venta BETWEEN ? AND ?
                 GROUP BY productos.id
                 ORDER BY cantidad_vendida DESC
                 LIMIT 5";

if ($stmt_productos = $conexion->prepare($sql_productos)) {
    $stmt_productos->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();
    $productosMasVendidos = $result_productos->fetch_all(MYSQLI_ASSOC);
    $stmt_productos->close();
}
?>

<div class="container-fluid mt-4">
    <div class="card shadow-lg p-4 rounded-4 border-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fw-bold text-primary mb-0">
                <i class="fas fa-chart-pie me-2"></i>Reportes de Ventas
            </h1>
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download me-1"></i> Exportar
                </button>
                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
                    <li>
                        <form method="POST" action="controllers/reportes/generar_reporte.php" target="_blank" class="dropdown-item">
                            <input type="hidden" name="tipo_reporte" value="<?= htmlspecialchars($tipo_reporte) ?>">
                            <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                            <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                            <button type="submit" name="formato" value="pdf" class="btn btn-link text-decoration-none">
                                <i class="fas fa-file-pdf text-danger me-2"></i> PDF
                            </button>
                        </form>
                    </li>
                    <li>
                        <form method="POST" action="controllers/reportes/generar_reporte.php" target="_blank" class="dropdown-item">
                            <input type="hidden" name="tipo_reporte" value="<?= htmlspecialchars($tipo_reporte) ?>">
                            <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                            <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                            <button type="submit" name="formato" value="excel" class="btn btn-link text-decoration-none">
                                <i class="fas fa-file-excel text-success me-2"></i> Excel
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Filtros de reporte -->
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="tipo_reporte" class="form-label">Tipo de Reporte</label>
                    <select class="form-select" name="tipo_reporte" id="tipo_reporte" required>
                        <option value="diario" <?= ($tipo_reporte == 'diario') ? 'selected' : '' ?>>Ventas Diarias</option>
                        <option value="mensual" <?= ($tipo_reporte == 'mensual') ? 'selected' : '' ?>>Ventas Mensuales</option>
                        <option value="anual" <?= ($tipo_reporte == 'anual') ? 'selected' : '' ?>>Ventas Anuales</option>
                        <option value="rango" <?= ($tipo_reporte == 'rango') ? 'selected' : '' ?>>Rango Personalizado</option>
                    </select>
                </div>
                
                <div class="col-md-3" id="rango-fechas" style="display: <?= ($tipo_reporte == 'rango') ? 'block' : 'none' ?>;">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                </div>
                
                <div class="col-md-3" id="rango-fechas-fin" style="display: <?= ($tipo_reporte == 'rango') ? 'block' : 'none' ?>;">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
            </div>
        </form>

        <!-- Resumen estadístico -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Ventas</h5>
                        <h2 class="card-text">$<?= number_format($totalVentas, 2) ?></h2>
                        <p class="small mb-0">Periodo: <?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Ventas Promedio</h5>
                        <h2 class="card-text">$<?= number_format($estadisticas['promedio_venta'] ?? 0, 2) ?></h2>
                        <p class="small mb-0"><?= $estadisticas['cantidad_ventas'] ?? 0 ?> transacciones</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Clientes Activos</h5>
                        <h2 class="card-text"><?= count($clientesFrecuentes) ?></h2>
                        <p class="small mb-0">Top 5 clientes frecuentes</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h5 class="card-title">Productos Vendidos</h5>
                        <h2 class="card-text"><?= array_sum(array_column($productosMasVendidos, 'cantidad_vendida')) ?></h2>
                        <p class="small mb-0">Top 5 productos más vendidos</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico y datos principales -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Evolución de Ventas</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ventasChart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Tabla de ventas detalladas -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Detalle de Ventas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($ventas)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="tablaVentas">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Productos</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ventas as $index => $venta): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td>
                                            <td><?= htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor final') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#productosModal<?= $venta['id'] ?>">
                                                    Ver <?= substr_count($venta['productos_vendidos'], ';') + 1 ?> productos
                                                </button>
                                                
                                                <!-- Modal para productos -->
                                                <div class="modal fade" id="productosModal<?= $venta['id'] ?>" tabindex="-1" aria-labelledby="productosModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-primary text-white">
                                                                <h5 class="modal-title" id="productosModalLabel">Detalle de Venta #<?= $venta['id'] ?></h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></p>
                                                                <p><strong>Cliente:</strong> <?= htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor final') ?></p>
                                                                <p><strong>Total:</strong> $<?= number_format($venta['total'], 2) ?></p>
                                                                <hr>
                                                                <h6>Productos:</h6>
                                                                <ul class="list-group">
                                                                    <?php foreach (explode('; ', $venta['productos_vendidos']) as $producto): ?>
                                                                    <li class="list-group-item"><?= htmlspecialchars($producto) ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="fw-bold">$<?= number_format($venta['total'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                No se encontraron ventas para el periodo seleccionado.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Top clientes -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Clientes Frecuentes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($clientesFrecuentes)): ?>
                            <ul class="list-group">
                                <?php foreach ($clientesFrecuentes as $cliente): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($cliente['nombre']) ?></strong>
                                        <div class="small"><?= $cliente['compras'] ?> compras</div>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">$<?= number_format($cliente['total_gastado'], 2) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No hay datos de clientes frecuentes.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Top productos -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Productos Más Vendidos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($productosMasVendidos)): ?>
                            <ul class="list-group">
                                <?php foreach ($productosMasVendidos as $producto): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($producto['descripcion']) ?></strong>
                                        <div class="small"><?= $producto['cantidad_vendida'] ?> unidades</div>
                                    </div>
                                    <span class="badge bg-success rounded-pill">$<?= number_format($producto['total_ventas'], 2) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="alert alert-info">
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

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Total: $' + context.raw.toLocaleString('es-CO');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString('es-CO');
                            }
                        }
                    }
                }
            }
        });
        
        // Inicializar DataTable si hay datos
        <?php if (!empty($ventas)): ?>
            $('#tablaVentas').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
                },
                order: [[1, 'desc']],
                dom: '<"top"f>rt<"bottom"lip><"clear">',
                responsive: true
            });
        <?php endif; ?>
    });
</script>

<?php include_once 'includes/footer.php'; ?>