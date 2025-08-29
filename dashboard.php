<?php
// dashboard.php (VERSIÓN SEGURA)
session_start();

// VERIFICACIÓN DE SEGURIDAD OBLIGATORIA
if (!isset($_SESSION['id_usuario']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$tituloPagina = 'Panel de Control - EasyStock';
include 'config/conexion.php';

// Configuración
define('FORMATO_MONEDA', '$%s');
define('ITEMS_POR_SECCION', 5);
$ultima_semana = date('Y-m-d', strtotime('-7 days'));

// FUNCIÓN SEGURA PARA CONSULTAS
function ejecutarConsultaSegura($conexion, $sql, $tipos = '', $parametros = []) {
    $stmt = $conexion->prepare($sql);
    if ($tipos && $parametros) {
        $stmt->bind_param($tipos, ...$parametros);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// CONSULTAS SEGURAS (con prepared statements)
$result_ventas = ejecutarConsultaSegura($conexion, 
    "SELECT COUNT(*) AS total FROM ventas WHERE DATE(fecha_venta) >= ?",
    's', [$ultima_semana]
);
$ventas_7dias = $result_ventas->fetch_assoc()['total'];

$result_productos = ejecutarConsultaSegura($conexion,
    "SELECT COUNT(*) AS total FROM productos WHERE DATE(fecha_creacion) >= ?",
    's', [$ultima_semana]
);
$productos_7dias = $result_productos->fetch_assoc()['total'];

$result_clientes = ejecutarConsultaSegura($conexion,
    "SELECT COUNT(*) AS total FROM clientes WHERE DATE(creado_en) >= ?", 
    's', [$ultima_semana]
);
$clientes_7dias = $result_clientes->fetch_assoc()['total'];

// Consulta para gráfico de ventas
$resultado = ejecutarConsultaSegura($conexion,
    "SELECT DATE_FORMAT(fecha_venta, '%M') AS mes, 
            SUM(total) AS total_mes
     FROM ventas
     WHERE fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY MONTH(fecha_venta)
     ORDER BY MONTH(fecha_venta)"
);

$meses = [];
$ventas = [];

while ($fila = $resultado->fetch_assoc()) {
    $meses[] = $fila['mes'];
    $ventas[] = $fila['total_mes'];
}

function obtenerTotal($conexion, $tabla, $filtro_mes = false) {
    $query_str = "SELECT COUNT(*) AS total FROM $tabla";
    
    if ($filtro_mes && $tabla === 'ventas') {
        $mes_actual = date('Y-m');
        $query = $conexion->prepare("$query_str WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?");
        $query->bind_param('s', $mes_actual);
    } else {
        $query = $conexion->prepare($query_str);
    }
    
    $query->execute();
    $result = $query->get_result();
    return $result->fetch_assoc()['total'];
}

// Modifica la función obtenerDatosGraficoVentas para aceptar un año como parámetro
function obtenerDatosGraficoVentas($conexion, $year = null) {
    $year = $year ?: date('Y');
    $query = $conexion->prepare("
        SELECT DATE_FORMAT(fecha_venta, '%Y-%m') AS mes, 
               SUM(total) AS ventas_totales 
        FROM ventas 
        WHERE YEAR(fecha_venta) = ?
        GROUP BY mes 
        ORDER BY mes
    ");
    $query->bind_param('s', $year);
    $query->execute();
    return $query->get_result();
}

// Obtener años disponibles para el selector
$years_query = ejecutarConsultaSegura($conexion, "SELECT DISTINCT YEAR(fecha_venta) as year FROM ventas ORDER BY year DESC");
$available_years = [];
while ($row = $years_query->fetch_assoc()) {
    $available_years[] = $row['year'];
}

// Datos iniciales para el gráfico (año actual)
$current_year = date('Y');
$datos_ventas = obtenerDatosGraficoVentas($conexion, $current_year);
$meses = [];
$ventas = [];

if ($datos_ventas) {
    while ($fila = $datos_ventas->fetch_assoc()) {
        $meses[] = date('M Y', strtotime($fila['mes']));
        $ventas[] = $fila['ventas_totales'];
    }
}

// Obtener estadísticas principales
$stats = [
    'usuarios' => obtenerTotal($conexion, 'usuarios'),
    'categorias' => obtenerTotal($conexion, 'categorias'),
    'productos' => obtenerTotal($conexion, 'productos'),
    'ventas' => obtenerTotal($conexion, 'ventas'),
    'clientes' => obtenerTotal($conexion, 'clientes')
];

// Consultas para las secciones con manejo de errores
try {
    $productos_mas_vendidos = ejecutarConsultaSegura($conexion, "
        SELECT p.id, p.descripcion AS nombre, p.imagen, 
               SUM(dv.cantidad) AS total_vendido, 
               SUM(dv.cantidad * p.precio_venta) AS total_ganancias 
        FROM productos p 
        JOIN venta_detalles dv ON p.id = dv.producto_id 
        GROUP BY p.id 
        ORDER BY total_vendido DESC 
        LIMIT ".ITEMS_POR_SECCION
    ) ?: throw new Exception("Error en consulta de productos más vendidos");

    // Consulta para obtener las últimas 5 ventas
    $ultimas_ventas = ejecutarConsultaSegura($conexion, "
        SELECT v.id, 
               v.total, 
               COALESCE(c.nombre, 'Venta sin cliente') AS cliente
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        ORDER BY v.fecha_venta DESC
        LIMIT 5
    ") ?: throw new Exception("Error en consulta de últimas ventas");

    // Consulta segura para productos recientes (sin stock)
    $productos_recientes = ejecutarConsultaSegura($conexion, "
        SELECT id, descripcion, precio_venta, categoria_id, fecha_creacion 
        FROM productos 
        ORDER BY fecha_creacion DESC 
        LIMIT ".ITEMS_POR_SECCION
    ) ?: throw new Exception("Error en consulta de productos recientes");

} catch (Exception $e) {
    error_log($e->getMessage());
    // Asignar resultados vacíos en caso de error
    $productos_mas_vendidos = $ultimas_ventas = $productos_recientes = false;
}

// Datos para gráficos
$datos_ventas = obtenerDatosGraficoVentas($conexion);
$meses = [];
$ventas = [];

if ($datos_ventas) {
    while ($fila = $datos_ventas->fetch_assoc()) {
        $meses[] = date('M Y', strtotime($fila['mes']));
        $ventas[] = $fila['ventas_totales'];
    }
}

include_once('includes/header.php');
?>

<!-- CSS adicional para el dashboard -->
<style>
    :root {
        --primary: #1a3a2f;
        --primary-light: #2a5a46;
        --secondary: #d4af37;
        --secondary-light: #e8c96a;
        --accent: #4e8cff;
        --light-bg: #f8fafc;
        --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    
    .stat-card {
        border-radius: 12px;
        border: none;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        height: 100%;
        overflow: hidden;
        padding-top: 3px;
        padding-left: 10px;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
    }
    
    .stat-icon {
        font-size: 2.5rem;
        opacity: 0.15;
        position: absolute;
        right: 20px;
        top: 20px;
        color: inherit;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.25rem;
    }
    
    .stat-title {
        color: #64748b;
        font-weight: 500;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .card-header-custom {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: white;
        font-weight: 600;
        border-radius: 10px 10px 0 0 !important;
        padding: 1rem 1.5rem;
    }
    
    .table-responsive {
        border-radius: 0 0 10px 10px;
    }
    
    .product-img {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 4px;
    }
    
    .badge-sold {
        background-color: rgba(212, 175, 55, 0.15);
        color: var(--secondary);
    }
    
    .chart-container {
        position: relative;
        height: 300px;
    }
    
    .progress-thin {
        height: 6px;
    }
    
    .text-warning {
        color: var(--secondary) !important;
    }
    
    .empty-state {
        padding: 2rem;
        text-align: center;
        color: #64748b;
    }
    
    .empty-state i {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: #e2e8f0;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                
                <div class="d-flex">
                    
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas principales -->
    <div class="row mb-4">
        <?php
        $indicadores = [
            ['usuarios', 'Usuarios', 'user-tie', 'primary'],
            ['categorias', 'Categorías', 'tags', 'success'],
            ['productos', 'Productos', 'box-open', 'info'],
            ['ventas', 'Ventas', 'receipt', 'warning'],
            ['clientes', 'Clientes', 'users', 'danger']
        ];

        foreach ($indicadores as $ind): ?>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stat-card bg-white border-0">
                    <div class="card-body position-relative">
                        <i class="fas fa-<?= $ind[2] ?> stat-icon"></i>
                        <h6 class="stat-title"><?= $ind[1] ?></h6>
                        <h3 class="stat-value"><?= number_format($stats[$ind[0]], 0, ',', '.') ?></h3>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-<?= $ind[3] ?>-light text-<?= $ind[3] ?> me-2">
                                <i class="fas fa-<?= $ind[0] == 'ventas' ? 'chart-line' : 'database' ?> me-1"></i>
                                 <?= $ind[0] == 'ventas' ? 'Este mes (' . obtenerTotal($conexion, 'ventas', true) . ')' : 'Total' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Gráfico y resumen -->
    <div class="row mb-4">
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Ventas últimos 6 meses</h6>
                        <select class="form-select form-select-sm w-auto bg-white" id="yearSelector">
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?= $year ?>" <?= $year == $current_year ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mb-4">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-header card-header-custom">
            <h6 class="m-0 font-weight-bold">Resumen de los Últimos 7 Días</h6>
        </div>
        <div class="card-body">

            <div class="mb-4">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Ventas</span>
                    <strong><?= $ventas_7dias ?></strong>
                </div>
                <div class="progress progress-thin">
                    <div class="progress-bar bg-success" style="width: <?= min($ventas_7dias * 10, 100) ?>%"></div>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Productos nuevos</span>
                    <strong><?= $productos_7dias ?></strong>
                </div>
                <div class="progress progress-thin">
                    <div class="progress-bar bg-info" style="width: <?= min($productos_7dias * 20, 100) ?>%"></div>
                </div>
            </div>

            <div class="mb-0">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Clientes nuevos</span>
                    <strong><?= $clientes_7dias ?></strong>
                </div>
                <div class="progress progress-thin">
                    <div class="progress-bar bg-warning" style="width: <?= min($clientes_7dias * 25, 100) ?>%"></div>
                </div>
            </div>

        </div>
    </div>
</div>


    </div>

    <!-- Secciones de datos -->
    <div class="row">
        <!-- Productos más vendidos -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Productos más vendidos</h6>
                        <a href="productos.php" class="btn btn-sm btn-outline-light">Ver todos</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <?php if($productos_mas_vendidos && $productos_mas_vendidos->num_rows > 0): ?>
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-end">Vendidos</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($producto = $productos_mas_vendidos->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if(!empty($producto['imagen'])): ?>
                                                    <img src='assets/img/productos/<?= htmlspecialchars($producto['imagen']) ?>'
                                                         class="product-img me-2" alt="<?= htmlspecialchars($producto['nombre']) ?>">
                                                <?php else: ?>
                                                    <div class="product-img me-2 bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-box text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars(mb_strimwidth($producto['nombre'], 0, 20, '...')) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge badge-sold"><?= $producto['total_vendido'] ?></span>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?= sprintf(FORMATO_MONEDA, number_format($producto['total_ganancias'], 0, ',', '.')) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h5>No hay datos disponibles</h5>
                            <p class="small">No se encontraron productos vendidos</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Últimas ventas -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Últimas ventas</h6>
                        <a href="ventas.php" class="btn btn-sm btn-outline-light">Ver todas</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <?php if($ultimas_ventas && $ultimas_ventas->num_rows > 0): ?>
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th># Venta</th>
                                    <th>Cliente</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($venta = $ultimas_ventas->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?= str_pad($venta['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($venta['cliente'], 0, 15, '...')) ?></td>
                                        <td class="text-end fw-bold">
                                            <?= sprintf(FORMATO_MONEDA, number_format($venta['total'], 0, ',', '.')) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h5>No hay ventas recientes</h5>
                            <p class="small">No se encontraron registros de ventas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let salesChart;
        
        function initChart(labels, data) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            if (salesChart) {
                salesChart.destroy();
            }
            
            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Ventas Totales',
                        data: data,
                        backgroundColor: 'rgba(26, 58, 47, 0.1)',
                        borderColor: 'rgba(26, 58, 47, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: 'rgba(212, 175, 55, 1)',
                        pointBorderColor: '#fff',
                        pointRadius: 5,
                        pointHoverRadius: 7
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
                            callbacks: {
                                label: function(context) {
                                    return 'Ventas: ' + new Intl.NumberFormat('es-ES', {
                                        style: 'currency',
                                        currency: 'USD'
                                    }).format(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-ES');
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
        }
        
        // Inicializar el gráfico con los datos iniciales
        initChart(<?= json_encode($meses) ?>, <?= json_encode($ventas) ?>);
        
        // Manejar el cambio de año
        document.getElementById('yearSelector').addEventListener('change', function() {
            const year = this.value;
            
            fetch(`api/get_sales_data.php?year=${year}`)
                .then(response => response.json())
                .then(data => {
                    initChart(data.labels, data.ventas);
                })
                .catch(error => console.error('Error:', error));
        });
    });
</script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($meses) ?>,
                datasets: [{
                    label: 'Ventas Totales',
                    data: <?= json_encode($ventas) ?>,
                    backgroundColor: 'rgba(26, 58, 47, 0.1)',
                    borderColor: 'rgba(26, 58, 47, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgba(212, 175, 55, 1)',
                    pointBorderColor: '#fff',
                    pointRadius: 5,
                    pointHoverRadius: 7
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
                        callbacks: {
                            label: function(context) {
                                return 'Ventas: ' + new Intl.NumberFormat('es-ES', {
                                    style: 'currency',
                                    currency: 'USD'
                                }).format(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString('es-ES');
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
    });
</script>
<?php include_once('includes/footer.php'); ?>