<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Kardex de Productos - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar sesión y permisos
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] != 'Administrador' && $_SESSION['rol_usuario'] != 'Supervisor')) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres permisos de administrador o supervisor para acceder'
    ];
    header("Location: index.php");
    exit();
}

// Obtener parámetros
$producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

// Función para obtener movimientos con filtros
function obtenerMovimientos($conexion, $producto_id, $fecha_inicio = '', $fecha_fin = '') {
    $sql = "SELECT m.*, 
                   p.descripcion as producto_descripcion, 
                   p.codigo_barras,
                   u.nombre as usuario_nombre,
                   DATE_FORMAT(m.fecha, '%Y-%m-%d %H:%i:%s') as fecha_formateada,
                   IFNULL(prov.nombre, 'N/A') as proveedor,
                   IFNULL(cli.nombre, 'N/A') as cliente
            FROM movimientos m
            JOIN productos p ON m.producto_id = p.id
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            LEFT JOIN proveedores prov ON m.proveedor_id = prov.id
            LEFT JOIN clientes cli ON m.cliente_id = cli.id
            WHERE m.producto_id = ?";
    
    $params = [$producto_id];
    
    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $sql .= " AND DATE(m.fecha) BETWEEN ? AND ?";
        $params[] = $fecha_inicio;
        $params[] = $fecha_fin;
    } elseif (!empty($fecha_inicio)) {
        $sql .= " AND DATE(m.fecha) >= ?";
        $params[] = $fecha_inicio;
    } elseif (!empty($fecha_fin)) {
        $sql .= " AND DATE(m.fecha) <= ?";
        $params[] = $fecha_fin;
    }
    
    $sql .= " ORDER BY m.fecha DESC";
    
    $stmt = $conexion->prepare($sql);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener información del producto
function obtenerProducto($conexion, $producto_id) {
    $sql = "SELECT p.*, c.nombre as categoria_nombre 
            FROM productos p
            JOIN categorias c ON p.categoria_id = c.id
            WHERE p.id = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// Obtener datos
$producto = $producto_id ? obtenerProducto($conexion, $producto_id) : null;
$movimientos = $producto_id ? obtenerMovimientos($conexion, $producto_id, $fecha_inicio, $fecha_fin) : [];
$saldo_actual = $producto ? $producto['cantidad'] : 0;
$saldo_inicial = 0;

// Calcular saldo inicial si hay filtros de fecha
if (!empty($movimientos) && (!empty($fecha_inicio) || !empty($fecha_fin))) {
    $sql_saldo_inicial = "SELECT 
                    SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE 0 END) as total_entradas,
                    SUM(CASE WHEN tipo = 'salida' THEN cantidad ELSE 0 END) as total_salidas
                  FROM movimientos
                  WHERE producto_id = ?";
    
    $params = [$producto_id];
    
    if (!empty($fecha_inicio)) {
        $sql_saldo_inicial .= " AND DATE(fecha) < ?";
        $params[] = $fecha_inicio;
    }
    
    $stmt = $conexion->prepare($sql_saldo_inicial);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $totales_iniciales = $result->fetch_assoc();
    $saldo_inicial = ($totales_iniciales['total_entradas'] ?? 0) - ($totales_iniciales['total_salidas'] ?? 0);
}
?>

<style>
    .card-kardex {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        overflow: hidden;
    }
    .card-header-kardex {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
        padding: 1.5rem;
    }
    .badge-entrada {
        background-color: #28a745;
    }
    .badge-salida {
        background-color: #dc3545;
    }
    .badge-ajuste-positivo {
        background-color: #17a2b8;
    }
    .badge-ajuste-negativo {
        background-color: #6c757d;
    }
    .empty-state {
        padding: 3rem 0;
        text-align: center;
    }
    .filter-container {
        background-color: #f8f9fa;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .search-container {
        position: relative;
    }
    #resultados-busqueda {
        position: absolute;
        z-index: 1000;
        width: 100%;
        max-height: 300px;
        overflow-y: auto;
        display: none;
    }
    .table-responsive {
        overflow-x: auto;
    }
    @media print {
        .no-print {
            display: none !important;
        }
        body {
            background-color: white;
            color: black;
        }
        .card {
            border: none;
            box-shadow: none;
        }
    }
</style>

<div class="container-fluid py-4">
    <?php include 'inventario_navbar.php'; ?>
    <div class="card card-kardex">
        <div class="card-header card-header-kardex text-center">
            <h4 class="mb-0"><i class="fas fa-history me-2"></i>Kardex de Productos</h4>
        </div>
        
        <div class="card-body">
            <?php if ($producto_id && $producto): ?>
                <!-- Información del Producto -->
                <div class="row mb-4 align-items-center">
                    <div class="col-md-2 text-center">
                        <?php if (!empty($producto['imagen'])): ?>
                            <img src="assets/img/productos/<?= htmlspecialchars($producto['imagen']) ?>" 
                                 class="img-thumbnail" style="max-width: 100px;" 
                                 alt="<?= htmlspecialchars($producto['descripcion']) ?>">
                        <?php else: ?>
                            <div class="bg-light rounded p-3 text-center">
                                <i class="fas fa-box fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h4><?= htmlspecialchars($producto['descripcion']) ?></h4>
                        <div class="d-flex flex-wrap gap-3">
                            <span class="text-muted">
                                <i class="fas fa-barcode me-1"></i> <?= htmlspecialchars($producto['codigo_barras']) ?>
                            </span>
                            <span class="text-muted">
                                <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($producto['categoria_nombre']) ?>
                            </span>
                            <span class="badge bg-<?= $producto['cantidad'] > $producto['stock_minimo'] ? 'success' : 'danger' ?>">
                                <i class="fas fa-boxes me-1"></i> Stock: <?= $producto['cantidad'] ?>
                                (Mín: <?= $producto['stock_minimo'] ?>, Máx: <?= $producto['stock_maximo'] ?>)
                            </span>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="d-flex justify-content-end gap-3">
                            <div class="text-end">
                                <small class="text-muted">Precio Compra</small>
                                <h5 class="mb-0">$<?= number_format($producto['precio_compra'], 2) ?></h5>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">Precio Venta</small>
                                <h5 class="mb-0 text-success">$<?= number_format($producto['precio_venta'], 2) ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="filter-container mb-4 no-print">
                    <form method="get" class="row g-3">
                        <input type="hidden" name="id" value="<?= $producto_id ?>">
                        
                        <div class="col-md-3">
                            <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                   value="<?= htmlspecialchars($fecha_inicio) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="fecha_fin" class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                   value="<?= htmlspecialchars($fecha_fin) ?>">
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="kardex.php?id=<?= $producto_id ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Limpiar
                            </a>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end justify-content-end">
                            <div class="btn-group">
                                <button type="button" class="btn btn-success" onclick="exportarExcel()">
                                    <i class="fas fa-file-excel me-1"></i> Excel
                                </button>
                                <button type="button" class="btn btn-danger" onclick="exportarPDF()">
                                    <i class="fas fa-file-pdf me-1"></i> PDF
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Resumen del Periodo -->
                <?php if (!empty($fecha_inicio) || !empty($fecha_fin)): ?>
                <div class="alert alert-info mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Saldo Inicial:</strong> <?= $saldo_inicial ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Periodo:</strong> 
                            <?= !empty($fecha_inicio) ? date('d/m/Y', strtotime($fecha_inicio)) : 'Inicio' ?> 
                            - 
                            <?= !empty($fecha_fin) ? date('d/m/Y', strtotime($fecha_fin)) : 'Hoy' ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Saldo Final:</strong> <?= $saldo_actual ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tabla de Movimientos -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="150">Fecha</th>
                                <th width="100">Tipo</th>
                                <th>Detalle</th>
                                <th width="100">Cantidad</th>
                                <th width="100">Saldo</th>
                                <th width="150">Responsable</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movimientos)): ?>
                                <?php 
                                $saldo = $saldo_actual;
                                foreach ($movimientos as $mov): 
                                    $saldo -= ($mov['tipo'] == 'entrada' ? $mov['cantidad'] : -$mov['cantidad']);
                                    $badge_class = '';
                                    $tipo_text = '';
                                    
                                    switch($mov['tipo']) {
                                        case 'entrada':
                                            $badge_class = 'badge-entrada';
                                            $tipo_text = 'Entrada';
                                            break;
                                        case 'salida':
                                            $badge_class = 'badge-salida';
                                            $tipo_text = 'Salida';
                                            break;
                                        case 'ajuste_positivo':
                                            $badge_class = 'badge-ajuste-positivo';
                                            $tipo_text = 'Ajuste +';
                                            break;
                                        case 'ajuste_negativo':
                                            $badge_class = 'badge-ajuste-negativo';
                                            $tipo_text = 'Ajuste -';
                                            break;
                                        default:
                                            $badge_class = 'badge-secondary';
                                            $tipo_text = ucfirst($mov['tipo']);
                                    }
                                ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($mov['fecha_formateada'])) ?></td>
                                        <td>
                                            <span class="badge <?= $badge_class ?>">
                                                <?= $tipo_text ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($mov['motivo']) ?>
                                            <?php if ($mov['proveedor'] != 'N/A'): ?>
                                                <br><small class="text-muted">Prov: <?= htmlspecialchars($mov['proveedor']) ?></small>
                                            <?php elseif ($mov['cliente'] != 'N/A'): ?>
                                                <br><small class="text-muted">Cli: <?= htmlspecialchars($mov['cliente']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold <?= in_array($mov['tipo'], ['entrada', 'ajuste_positivo']) ? 'text-success' : 'text-danger' ?>">
                                            <?= in_array($mov['tipo'], ['entrada', 'ajuste_positivo']) ? '+' : '-' ?><?= $mov['cantidad'] ?>
                                        </td>
                                        <td class="fw-bold"><?= $saldo ?></td>
                                        <td><?= htmlspecialchars($mov['usuario_nombre'] ?? 'Sistema') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                            <h5>No hay movimientos registrados</h5>
                                            <p class="text-muted">
                                                <?= (!empty($fecha_inicio) || !empty($fecha_fin)) ? 
                                                    "No hay movimientos para el periodo seleccionado" : 
                                                    "Este producto no tiene movimientos en el kardex" ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Resumen -->
                <div class="row mt-4 no-print">
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Entradas Totales</h6>
                                <h4 class="text-success">+<?= array_reduce($movimientos, function($carry, $item) {
                                    return $carry + (in_array($item['tipo'], ['entrada', 'ajuste_positivo']) ? $item['cantidad'] : 0);
                                }, 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Salidas Totales</h6>
                                <h4 class="text-danger">-<?= array_reduce($movimientos, function($carry, $item) {
                                    return $carry + (in_array($item['tipo'], ['salida', 'ajuste_negativo']) ? $item['cantidad'] : 0);
                                }, 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Saldo Actual</h6>
                                <h4 class="text-primary"><?= $saldo_actual ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Días sin Movimiento</h6>
                                <h4 class="text-warning">
                                    <?php 
                                    if (!empty($movimientos)) {
                                        $ultimo_movimiento = new DateTime($movimientos[0]['fecha_formateada']);
                                        $hoy = new DateTime();
                                        echo $hoy->diff($ultimo_movimiento)->format('%a');
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Búsqueda de producto -->
                <div class="search-container mb-4">
                    <label class="form-label">Buscar Producto</label>
                    <div class="input-group">
                        <input type="text" id="buscar-producto" class="form-control" 
                               placeholder="Escribe código de barras o descripción" autofocus>
                        <button class="btn btn-primary" type="button" id="btn-buscar">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div id="resultados-busqueda" class="list-group mt-1"></div>
                </div>
                
                <!-- Vista cuando no se ha seleccionado un producto -->
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                    <h3>Seleccione un producto para ver su kardex</h3>
                    <p class="text-muted mb-4">Busque por código de barras o descripción del producto</p>
                    <a href="productos.php" class="btn btn-primary">
                        <i class="fas fa-boxes me-1"></i> Ver todos los productos
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Función para exportar a Excel
function exportarExcel() {
    // Construir URL con parámetros
    let url = `exportar_kardex_excel.php?id=<?= $producto_id ?>`;
    
    // Agregar filtros de fecha si existen
    const fechaInicio = document.getElementById('fecha_inicio').value;
    const fechaFin = document.getElementById('fecha_fin').value;
    
    if (fechaInicio) url += `&fecha_inicio=${fechaInicio}`;
    if (fechaFin) url += `&fecha_fin=${fechaFin}`;
    
    // Abrir en nueva pestaña para descargar
    window.open(url, '_blank');
}
function exportarPDF() {
    // Construir URL con parámetros
    let url = `exportar_kardex_pdf.php?id=<?= $producto_id ?>`;
    
    // Agregar filtros de fecha si existen
    const fechaInicio = document.getElementById('fecha_inicio').value;
    const fechaFin = document.getElementById('fecha_fin').value;
    
    if (fechaInicio) url += `&fecha_inicio=${fechaInicio}`;
    if (fechaFin) url += `&fecha_fin=${fechaFin}`;
    
    // Abrir en nueva pestaña para descargar
    window.open(url, '_blank');
}
// Búsqueda de productos
document.addEventListener("DOMContentLoaded", function() {
    const buscarProducto = document.getElementById('buscar-producto');
    const resultadosBusqueda = document.getElementById('resultados-busqueda');
    let timeoutBusqueda = null;
    let codigoTemporal = '';
    let timeoutCodigo = null;

    // Búsqueda dinámica
    buscarProducto.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        const query = this.value.trim();
        
        if (query.length < 3 && !/^\d{8,}$/.test(query)) {
            resultadosBusqueda.style.display = 'none';
            return;
        }

        timeoutBusqueda = setTimeout(() => {
            if (/^\d{8,}$/.test(query)) {
                buscarProductoPorCodigo(query);
            } else {
                buscarProductoPorTexto(query);
            }
        }, 300);
    });

    // Buscar por código de barras
    function buscarProductoPorCodigo(codigo) {
        fetch(`ajax/buscar_productos.php?codigo=${encodeURIComponent(codigo)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 1) {
                    window.location.href = `kardex.php?id=${data[0].id}`;
                } else {
                    mostrarResultadosBusqueda([]);
                    alert('Producto no encontrado');
                }
            });
    }

    // Buscar por texto
    function buscarProductoPorTexto(texto) {
        fetch(`ajax/buscar_productos.php?q=${encodeURIComponent(texto)}`)
            .then(response => response.json())
            .then(data => mostrarResultadosBusqueda(data));
    }

    // Mostrar resultados
    function mostrarResultadosBusqueda(productos) {
        resultadosBusqueda.innerHTML = '';
        
        if (productos.length === 0) {
            const item = document.createElement('div');
            item.className = 'list-group-item text-muted';
            item.textContent = 'No se encontraron productos';
            resultadosBusqueda.appendChild(item);
        } else {
            productos.forEach(producto => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${escapeHtml(producto.descripcion || producto.nombre)}</h6>
                            <small class="text-muted">Stock: ${producto.cantidad}</small>
                        </div>
                        ${producto.codigo_barras ? `<small class="text-muted">Código: ${escapeHtml(producto.codigo_barras)}</small>` : ''}
                    </div>
                `;
                item.addEventListener('click', () => {
                    window.location.href = `kardex.php?id=${producto.id}`;
                });
                resultadosBusqueda.appendChild(item);
            });
        }
        resultadosBusqueda.style.display = 'block';
    }

    // Ocultar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!buscarProducto.contains(e.target) && !resultadosBusqueda.contains(e.target)) {
            resultadosBusqueda.style.display = 'none';
        }
    });

    // Detección de código de barras
    buscarProducto.addEventListener('keydown', function(e) {
        if (/^\d*$/.test(this.value)) {
            clearTimeout(timeoutCodigo);
            codigoTemporal += e.key;
            
            timeoutCodigo = setTimeout(() => {
                if (codigoTemporal.length >= 8) {
                    buscarProductoPorCodigo(codigoTemporal);
                }
                codigoTemporal = '';
            }, 100);
        }
    });

    // Función para escapar HTML
    function escapeHtml(unsafe) {
        return unsafe?.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;") || '';
    }
});
</script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>