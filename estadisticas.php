<?php
$tituloPagina = 'Estadísticas de Ventas - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos y sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Configuración de fechas por defecto (últimos 30 días)
$fecha_inicio = date('Y-m-d', strtotime('-30 days'));
$fecha_fin = date('Y-m-d');

// Manejo del formulario de filtrado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? $fecha_inicio;
    $fecha_fin = $_POST['fecha_fin'] ?? $fecha_fin;
    
    // Validar fechas
    if ($fecha_fin < $fecha_inicio) {
        $fecha_fin = $fecha_inicio;
    }
}

// Obtener estadísticas generales (consulta optimizada)
$sql_estadisticas = "SELECT 
                        SUM(total) AS total_ventas,
                        COUNT(id) AS cantidad_ventas,
                        AVG(total) AS promedio_venta,
                        MIN(total) AS venta_minima,
                        MAX(total) AS venta_maxima,
                        COUNT(DISTINCT cliente_id) AS clientes_unicos
                     FROM ventas
                     WHERE fecha_venta BETWEEN ? AND ?";

$stmt_estadisticas = $conexion->prepare($sql_estadisticas);
$stmt_estadisticas->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt_estadisticas->execute();
$result_estadisticas = $stmt_estadisticas->get_result();
$estadisticas = $result_estadisticas->fetch_assoc() ?? [
    'total_ventas' => 0,
    'cantidad_ventas' => 0,
    'promedio_venta' => 0,
    'venta_minima' => 0,
    'venta_maxima' => 0,
    'clientes_unicos' => 0
];
$stmt_estadisticas->close();

// Ventas por día para el gráfico (optimizado)
$sql_grafico = "SELECT 
                    DATE(fecha_venta) AS fecha,
                    SUM(total) AS total_dia,
                    COUNT(id) AS transacciones_dia
                FROM ventas
                WHERE fecha_venta BETWEEN ? AND ?
                GROUP BY DATE(fecha_venta)
                ORDER BY fecha";

$stmt_grafico = $conexion->prepare($sql_grafico);
$stmt_grafico->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt_grafico->execute();
$result_grafico = $stmt_grafico->get_result();
$graficoData = [];
while ($row = $result_grafico->fetch_assoc()) {
    $graficoData[] = $row;
}
$stmt_grafico->close();

// Ventas por categoría de producto (mejorado)
$sql_categorias = "SELECT 
                      c.nombre AS categoria,
                      SUM(vd.cantidad * vd.precio_unitario) AS total,
                      SUM(vd.cantidad) AS cantidad_vendida
                   FROM venta_detalles vd
                   JOIN productos p ON vd.producto_id = p.id
                   JOIN categorias c ON p.categoria_id = c.id
                   JOIN ventas v ON vd.venta_id = v.id
                   WHERE v.fecha_venta BETWEEN ? AND ?
                   GROUP BY c.id
                   ORDER BY total DESC
                   LIMIT 10";

$stmt_categorias = $conexion->prepare($sql_categorias);
$stmt_categorias->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt_categorias->execute();
$result_categorias = $stmt_categorias->get_result();
$ventasPorCategoria = $result_categorias->fetch_all(MYSQLI_ASSOC);
$stmt_categorias->close();




// Clientes más frecuentes (mejorado)
$sql_clientes = "SELECT 
                    c.nombre,
                    COUNT(v.id) AS compras,
                    SUM(v.total) AS total_gastado,
                    MAX(v.fecha_venta) AS ultima_compra
                 FROM ventas v
                 JOIN clientes c ON v.cliente_id = c.id
                 WHERE v.fecha_venta BETWEEN ? AND ?
                 GROUP BY c.id
                 ORDER BY compras DESC, total_gastado DESC
                 LIMIT 5";

$stmt_clientes = $conexion->prepare($sql_clientes);
$stmt_clientes->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt_clientes->execute();
$result_clientes = $stmt_clientes->get_result();
$clientesFrecuentes = $result_clientes->fetch_all(MYSQLI_ASSOC);
$stmt_clientes->close();
?>

<div class="container-fluid py-4">
    <?php include 'ventas_navbar.php'; ?>
    
    <div class="card shadow-sm rounded-3 border-0 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h4 fw-bold mb-0 text-white">
                    <i class="fas fa-chart-bar me-2"></i>Estadísticas de Ventas
                </h1>
                
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="dropdownExportar" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-download me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="dropdownExportar">
                        <li>
                            <form method="POST" action="controllers/reportes/generar_reporte_estadisticas.php" target="_blank" class="dropdown-item">
                                <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                                <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                                <button type="submit" name="formato" value="pdf" class="btn btn-link text-decoration-none w-100 text-start">
                                    <i class="fas fa-file-pdf text-danger me-2"></i> PDF
                                </button>
                            </form>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="controllers/reportes/generar_reporte_estadisticas.php" target="_blank" class="dropdown-item">
                                <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                                <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                                <button type="submit" name="formato" value="excel" class="btn btn-link text-decoration-none w-100 text-start">
                                    <i class="fas fa-file-excel text-success me-2"></i> Excel
                                </button>
                            </form>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item" onclick="window.print()">
                                <i class="fas fa-print text-muted me-2"></i> Imprimir
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Filtros -->
            <form method="POST" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label small fw-bold text-muted">Fecha Inicio</label>
                        <input type="date" class="form-control form-control-sm" name="fecha_inicio" 
                               value="<?= htmlspecialchars($fecha_inicio) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_fin" class="form-label small fw-bold text-muted">Fecha Fin</label>
                        <input type="date" class="form-control form-control-sm" name="fecha_fin" 
                               value="<?= htmlspecialchars($fecha_fin) ?>" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <a href="estadisticas.php" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="fas fa-sync-alt me-1"></i> Restablecer
                        </a>
                    </div>
                </div>
            </form>

            <!-- Resumen estadístico mejorado -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary bg-opacity-10 border-primary border-opacity-25 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle text-muted">Total Ventas</h6>
                                    <h3 class="card-title text-primary">$<?= number_format($estadisticas['total_ventas'], 0, '', '.') ?></h3>
                                </div>
                                <i class="fas fa-dollar-sign text-primary opacity-25 fa-2x"></i>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary">
                                    <?= $estadisticas['cantidad_ventas'] ?> transacciones
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
                                    <h6 class="card-subtitle text-muted">Venta Promedio</h6>
                                    <h3 class="card-title text-success">$<?= number_format($estadisticas['promedio_venta'], 0, '', '.') ?></h3>
                                </div>
                                <i class="fas fa-chart-line text-success opacity-25 fa-2x"></i>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-success bg-opacity-10 text-success">
                                    <?= $estadisticas['clientes_unicos'] ?> clientes únicos
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
                                    <h6 class="card-subtitle text-muted">Venta Mínima</h6>
                                    <h3 class="card-title text-info">$<?= number_format($estadisticas['venta_minima'], 0, '', '.') ?></h3>
                                </div>
                                <i class="fas fa-arrow-down text-info opacity-25 fa-2x"></i>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-info bg-opacity-10 text-info">
                                    Valor más bajo
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
                                    <h6 class="card-subtitle text-muted">Venta Máxima</h6>
                                    <h3 class="card-title text-warning">$<?= number_format($estadisticas['venta_maxima'], 0, '', '.') ?></h3>
                                </div>
                                <i class="fas fa-arrow-up text-warning opacity-25 fa-2x"></i>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-warning bg-opacity-10 text-warning">
                                    Valor más alto
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos principales -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="mb-0 d-flex text-white align-items-center">
                                <i class="fas fa-chart-line me-2"></i>
                                Evolución de Ventas Diarias
                                <small class="text-white ms-auto">
                                    <?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?>
                                </small>
                            </h5>
                        </div>
                        <div class="card-body pt-0">
                            <div class="chart-container" style="position: relative; height: 300px;">
                                <canvas id="ventasChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="mb-0 d-flex text-white align-items-center">
                                <i class="fas fa-users me-2"></i>
                                Clientes Más Frecuentes
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
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($cliente['nombre']) ?></h6>
                                                <small class="text-muted">
                                                    <?= $cliente['compras'] ?> compras
                                                </small>
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i>
                                                        Última: <?= date('d/m/Y', strtotime($cliente['ultima_compra'])) ?>
                                                    </small>
                                                </div>
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
                </div>
            </div>

            <!-- Gráficos secundarios -->
            <div class="row g-4 mt-0">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="mb-0 d-flex text-white align-items-center">
                                <i class="fas fa-tags me-2"></i>
                                Ventas por Categoría
                                <span class="badge bg-primary ms-auto">
                                    Top 10
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($ventasPorCategoria)): ?>
                                <div class="chart-container" style="position: relative; height: 250px;">
                                    <canvas id="categoriasChart"></canvas>
                                </div>
                                <div class="mt-3">
                                    <table class="table table-sm table-borderless">
                                        <thead>
                                            <tr>
                                                <th>Categoría</th>
                                                <th class="text-end">Ventas</th>
                                                <th class="text-end">Unidades</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ventasPorCategoria as $categoria): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($categoria['categoria']) ?></td>
                                                <td class="text-end">$<?= number_format($categoria['total'], 0, '', '.') ?></td>
                                                <td class="text-end"><?= number_format($categoria['cantidad_vendida']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No hay datos de ventas por categoría.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="mb-0 d-flex text-white align-items-center">
                                <i class="fas fa-tools me-2"></i>
                                Servicios Más Solicitados
                                <span class="badge bg-primary ms-auto">
                                    Top 10
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($ventasPorServicio)): ?>
                                <div class="chart-container" style="position: relative; height: 250px;">
                                    <canvas id="serviciosChart"></canvas>
                                </div>
                                <div class="mt-3">
                                    <table class="table table-sm table-borderless">
                                        <thead>
                                            <tr>
                                                <th>Servicio</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Cantidad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ventasPorServicio as $servicio): ?>
                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($servicio['servicio']) ?>
                                                    <?php if (!empty($servicio['mecanicos'])): ?>
                                                        <small class="d-block text-muted">
                                                            <i class="fas fa-user-cog me-1"></i>
                                                            <?= htmlspecialchars($servicio['mecanicos']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">$<?= number_format($servicio['total'], 2) ?></td>
                                                <td class="text-end"><?= $servicio['cantidad'] ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No hay datos de servicios realizados.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Colores para gráficos
    const colores = [
        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
        '#5a5c69', '#858796', '#3a3b45', '#2e59d9', '#17a673',
        '#2c9faf', '#f6c23e', '#e74a3b', '#5a5c69', '#858796'
    ];

    // Formateador de moneda
    const formatter = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    });

    // Gráfico de ventas diarias (combinado línea/barra)
    const ctxVentas = document.getElementById('ventasChart').getContext('2d');
    new Chart(ctxVentas, {
        type: 'bar',
        data: {
            labels: [<?= implode(',', array_map(function($item) {
                return "'" . date('d/m', strtotime($item['fecha'])) . "'";
            }, $graficoData)) ?>],
            datasets: [
                {
                    label: 'Ventas ($)',
                    data: [<?= implode(',', array_column($graficoData, 'total_dia')) ?>],
                    backgroundColor: colores[0] + '33',
                    borderColor: colores[0],
                    borderWidth: 2,
                    type: 'line',
                    tension: 0.3,
                    fill: false,
                    yAxisID: 'y'
                },
                {
                    label: 'Transacciones',
                    data: [<?= implode(',', array_column($graficoData, 'transacciones_dia')) ?>],
                    backgroundColor: colores[1] + '33',
                    borderColor: colores[1],
                    borderWidth: 1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label.includes('Ventas')) {
                                return label + ': ' + formatter.format(context.raw);
                            } else {
                                return label + ': ' + context.raw;
                            }
                        }
                    }
                },
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Ventas ($)'
                    },
                    ticks: {
                        callback: function(value) {
                            return formatter.format(value);
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Transacciones'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Gráfico de ventas por categoría (doughnut mejorado)
    const ctxCategorias = document.getElementById('categoriasChart').getContext('2d');
    new Chart(ctxCategorias, {
        type: 'doughnut',
        data: {
            labels: [<?= implode(',', array_map(function($item) {
                return "'" . addslashes($item['categoria']) . "'";
            }, $ventasPorCategoria)) ?>],
            datasets: [{
                data: [<?= implode(',', array_column($ventasPorCategoria, 'total')) ?>],
                backgroundColor: colores,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${formatter.format(value)} (${percentage}%)`;
                        }
                    }
                },
                legend: {
                    position: 'right',
                },
                datalabels: {
                    formatter: (value) => {
                        return formatter.format(value);
                    },
                    color: '#000',
                    font: {
                        weight: 'bold'
                    }
                }
            },
            cutout: '65%'
        },
        plugins: [ChartDataLabels]
    });

    // Gráfico de servicios más solicitados (polar area mejorado)
    <?php if (!empty($ventasPorServicio)): ?>
        const ctxServicios = document.getElementById('serviciosChart').getContext('2d');
        new Chart(ctxServicios, {
            type: 'polarArea',
            data: {
                labels: [<?= implode(',', array_map(function($item) {
                    return "'" . addslashes($item['servicio']) . "'";
                }, $ventasPorServicio)) ?>],
                datasets: [{
                    data: [<?= implode(',', array_column($ventasPorServicio, 'total')) ?>],
                    backgroundColor: colores,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const servicio = <?= json_encode($ventasPorServicio) ?>[context.dataIndex];
                                return [
                                    context.label + ': ' + formatter.format(context.raw),
                                    'Cantidad: ' + servicio.cantidad,
                                    'Mecánicos: ' + (servicio.mecanicos || 'No asignado')
                                ];
                            }
                        }
                    },
                    legend: {
                        position: 'right',
                    },
                    datalabels: {
                        formatter: (value) => {
                            return formatter.format(value);
                        },
                        color: '#fff',
                        font: {
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    r: {
                        pointLabels: {
                            display: true,
                            centerPointLabels: true,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    <?php endif; ?>
});
</script>

<?php include_once 'includes/footer.php'; ?>