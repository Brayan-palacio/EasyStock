<?php
$tituloPagina = 'Estadísticas de Ventas - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
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
    
    // Validar que fecha fin no sea menor que fecha inicio
    if ($fecha_fin < $fecha_inicio) {
        $fecha_fin = $fecha_inicio;
    }
}

// Obtener estadísticas generales
$sql_estadisticas = "SELECT 
                        SUM(total) AS total_ventas,
                        COUNT(id) AS cantidad_ventas,
                        AVG(total) AS promedio_venta,
                        MIN(total) AS venta_minima,
                        MAX(total) AS venta_maxima
                     FROM ventas
                     WHERE fecha_venta BETWEEN ? AND ?";

$stmt_estadisticas = $conexion->prepare($sql_estadisticas);
$stmt_estadisticas->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt_estadisticas->execute();
$result_estadisticas = $stmt_estadisticas->get_result();
$estadisticas = $result_estadisticas->fetch_assoc();
$stmt_estadisticas->close();

// Ventas por día para el gráfico
$sql_grafico = "SELECT 
                    DATE(fecha_venta) AS fecha,
                    SUM(total) AS total_dia
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

// Ventas por categoría de producto
$sql_categorias = "SELECT 
                      categorias.nombre AS categoria,
                      SUM(venta_detalles.cantidad * venta_detalles.precio_unitario) AS total
                   FROM venta_detalles
                   JOIN productos ON venta_detalles.producto_id = productos.id
                   JOIN categorias ON productos.categoria_id = categorias.id
                   JOIN ventas ON venta_detalles.venta_id = ventas.id
                   WHERE ventas.fecha_venta BETWEEN ? AND ?
                   GROUP BY categorias.id
                   ORDER BY total DESC";

$stmt_categorias = $conexion->prepare($sql_categorias);
$stmt_categorias->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt_categorias->execute();
$result_categorias = $stmt_categorias->get_result();
$ventasPorCategoria = $result_categorias->fetch_all(MYSQLI_ASSOC);
$stmt_categorias->close();

// Ventas por mecánico/servicio
$sql_servicios = "SELECT 
                     servicios.descripcion AS servicio,
                     COUNT(ordenes_servicio.id) AS cantidad,
                     SUM(ordenes_servicio.total) AS total
                  FROM ordenes_servicio
                  JOIN servicios ON ordenes_servicio.servicio_id = servicios.id
                  WHERE ordenes_servicio.fecha BETWEEN ? AND ?
                  GROUP BY servicios.id
                  ORDER BY total DESC
                  LIMIT 10";

$stmt_servicios = $conexion->prepare($sql_servicios);
$stmt_servicios->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt_servicios->execute();
$result_servicios = $stmt_servicios->get_result();
$ventasPorServicio = $result_servicios->fetch_all(MYSQLI_ASSOC);
$stmt_servicios->close();

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

$stmt_clientes = $conexion->prepare($sql_clientes);
$stmt_clientes->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt_clientes->execute();
$result_clientes = $stmt_clientes->get_result();
$clientesFrecuentes = $result_clientes->fetch_all(MYSQLI_ASSOC);
$stmt_clientes->close();
?>

<div class="container-fluid mt-4">
    <div class="card shadow-lg p-4 rounded-4 border-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fw-bold text-primary mb-0">
                <i class="fas fa-chart-bar me-2"></i>Estadísticas de Ventas
            </h1>
            <div>
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Imprimir
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?= htmlspecialchars($fecha_inicio) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?= htmlspecialchars($fecha_fin) ?>" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="estadisticas.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-sync-alt me-1"></i> Restablecer
                    </a>
                </div>
            </div>
        </form>

        <!-- Resumen estadístico -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Ventas</h5>
                        <h2 class="card-text">$<?= number_format($estadisticas['total_ventas'] ?? 0, 2) ?></h2>
                        <p class="small mb-0"><?= $estadisticas['cantidad_ventas'] ?? 0 ?> transacciones</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Venta Promedio</h5>
                        <h2 class="card-text">$<?= number_format($estadisticas['promedio_venta'] ?? 0, 2) ?></h2>
                        <p class="small mb-0">por transacción</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Venta Mínima</h5>
                        <h2 class="card-text">$<?= number_format($estadisticas['venta_minima'] ?? 0, 2) ?></h2>
                        <p class="small mb-0">valor más bajo</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h5 class="card-title">Venta Máxima</h5>
                        <h2 class="card-text">$<?= number_format($estadisticas['venta_maxima'] ?? 0, 2) ?></h2>
                        <p class="small mb-0">valor más alto</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos principales -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Evolución de Ventas Diarias</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ventasChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Clientes Más Frecuentes</h5>
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
            </div>
        </div>

        <!-- Gráficos secundarios -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Ventas por Categoría de Producto</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="categoriasChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Servicios Más Solicitados</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($ventasPorServicio)): ?>
                            <canvas id="serviciosChart" height="250"></canvas>
                        <?php else: ?>
                            <div class="alert alert-info">
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

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Colores para gráficos
        const colores = [
            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
            '#5a5c69', '#858796', '#3a3b45', '#2e59d9', '#17a673'
        ];

        // Gráfico de ventas diarias
        const ctxVentas = document.getElementById('ventasChart').getContext('2d');
        new Chart(ctxVentas, {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(function($item) {
                    return "'" . date('d/m', strtotime($item['fecha'])) . "'";
                }, $graficoData)) ?>],
                datasets: [{
                    label: 'Ventas por día',
                    data: [<?= implode(',', array_column($graficoData, 'total_dia')) ?>],
                    backgroundColor: colores[0],
                    borderColor: colores[0],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
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

        // Gráfico de ventas por categoría
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
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': $' + context.raw.toLocaleString('es-CO');
                            }
                        }
                    },
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Gráfico de servicios más solicitados (si hay datos)
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
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': $' + context.raw.toLocaleString('es-CO') + 
                                           ' (' + context.raw + ' servicios)';
                                }
                            }
                        },
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
        <?php endif; ?>
    });
</script>

<?php include_once 'includes/footer.php'; ?>