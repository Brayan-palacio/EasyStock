<?php
// Configuración inicial mejorada
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die('Método no permitido');
}

$tituloPagina = 'Reporte de Inventario - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Función para exportar a Excel con formato profesional
function exportarExcel($productos, $filtros, $totales) {
    try {
        require 'vendor/autoload.php';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Estilos reutilizables
        $styles = [
            'header' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1A3A2F']],
                'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'FFFFFF']]]
            ],
            'title' => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1A3A2F']],
                'alignment' => ['horizontal' => 'center']
            ],
            'warning' => [
                'font' => ['color' => ['rgb' => 'FFC107']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF3E0']]
            ],
            'danger' => [
                'font' => ['color' => ['rgb' => 'DC3545']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFEBEE']]
            ],
            'normal' => [
                'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'DDDDDD']]]
            ],
            'footer' => [
                'font' => ['italic' => true, 'size' => 9],
                'alignment' => ['horizontal' => 'right']
            ],
            'kpi' => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F8F9FA']]
            ]
        ];
        
        // Título del reporte
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'REPORTE DE INVENTARIO - EASYSTOCK');
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        
        // Información de filtros
        $sheet->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i'));
        if ($filtros['categoria_id']) {
            $sheet->setCellValue('B2', 'Categoría: ' . $filtros['categoria_nombre']);
        }
        $sheet->setCellValue('J2', 'Mostrar cero: ' . ($filtros['mostrar_cero'] ? 'Sí' : 'No'));
        $sheet->getStyle('A2:J2')->applyFromArray($styles['footer']);
        
        // KPIs
        $sheet->mergeCells('A4:C4');
        $sheet->setCellValue('A4', 'RESUMEN DEL INVENTARIO');
        $sheet->getStyle('A4')->applyFromArray($styles['kpi']);
        
        $sheet->setCellValue('A5', 'Costo Total');
        $sheet->setCellValue('B5', '$' . number_format($totales['inversion'], 2));
        
        $sheet->setCellValue('A6', 'Valor de Venta');
        $sheet->setCellValue('B6', '$' . number_format($totales['valor_venta'], 2));
        
        $sheet->setCellValue('A7', 'Productos');
        $sheet->setCellValue('B7', $totales['productos']);
        $sheet->setCellValue('C7', $totales['unidades'] . ' unidades');
        
        // Encabezados de tabla
        $headers = [
            'Código', 'Descripción', 'Categoría', 'Existencia', 
            'Mínimo', 'Máximo', 'Precio Costo', 'Precio Venta', 
            'Costo Inventario', 'Estado'
        ];
        $sheet->fromArray($headers, null, 'A9');
        $sheet->getStyle('A9:J9')->applyFromArray($styles['header']);
        
        // Datos
        $row = 10;
        foreach ($productos as $p) {
            $sheet->setCellValue('A' . $row, $p['codigo_barras'] ?: 'N/A');
            $sheet->setCellValue('B' . $row, $p['descripcion']);
            $sheet->setCellValue('C' . $row, $p['categoria']);
            $sheet->setCellValue('D' . $row, $p['cantidad']);
            $sheet->setCellValue('E' . $row, $p['stock_minimo']);
            $sheet->setCellValue('F' . $row, $p['stock_maximo'] ?? 'N/A');
            $sheet->setCellValue('G' . $row, $p['precio_compra']);
            $sheet->setCellValue('H' . $row, $p['precio_venta']);
            $sheet->setCellValue('I' . $row, $p['precio_compra'] * $p['cantidad']);
            
            // Estado del inventario
            $estado = '';
            if ($p['cantidad'] <= 0) {
                $estado = 'AGOTADO';
                $sheet->getStyle('D' . $row . ':J' . $row)->applyFromArray($styles['danger']);
            } elseif ($p['cantidad'] <= $p['stock_minimo']) {
                $estado = 'BAJO STOCK';
                $sheet->getStyle('D' . $row . ':J' . $row)->applyFromArray($styles['warning']);
            } else {
                $estado = 'OK';
            }
            $sheet->setCellValue('J' . $row, $estado);
            
            $row++;
        }
        
        // Aplicar estilos generales
        $sheet->getStyle('A10:J' . ($row-1))->applyFromArray($styles['normal']);
        
        // Formato de números
        $sheet->getStyle('G5:H' . $row)
            ->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');
        
        $sheet->getStyle('I5:I' . $row)
            ->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');
        
        // Autoajustar columnas
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Proteger hoja (solo lectura)
        $sheet->getProtection()->setSheet(true);
        
        // Configurar página
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        
        // Generar archivo
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="inventario_'.date('Ymd_His').'.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_end_clean();
        $writer->save('php://output');
        exit;
        
    } catch (\Exception $e) {
        error_log('Error al generar Excel: ' . $e->getMessage());
        die('Ocurrió un error al generar el archivo Excel');
    }
}

// Manejo de exportación a Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $filtros = [
        'categoria_id' => $_GET['categoria_id'] ?? null,
        'mostrar_cero' => isset($_GET['mostrar_cero']),
        'categoria_nombre' => ''
    ];
    
    // Obtener nombre de categoría si existe
    if ($filtros['categoria_id']) {
        $stmt = $conexion->prepare("SELECT nombre FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $filtros['categoria_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $filtros['categoria_nombre'] = $result->fetch_row()[0];
        }
    }
    
    // Obtener productos para exportación
    $sql = "SELECT p.id, p.descripcion, p.codigo_barras, 
                   p.cantidad, p.stock_minimo, p.stock_maximo,
                   p.precio_compra, p.precio_venta, 
                   c.nombre as categoria
            FROM productos p
            JOIN categorias c ON p.categoria_id = c.id
            " . (!$filtros['mostrar_cero'] ? " WHERE p.cantidad > 0" : "") . "
            " . ($filtros['categoria_id'] ? ($filtros['mostrar_cero'] ? " WHERE " : " AND ") . " p.categoria_id = ?" : "") . "
            ORDER BY c.nombre, p.descripcion";

    $stmt = $conexion->prepare($sql);
    if ($filtros['categoria_id']) {
        $stmt->bind_param("i", $filtros['categoria_id']);
    }
    $stmt->execute();
    $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calcular totales
    $totales = [
        'inversion' => 0,
        'valor_venta' => 0,
        'productos' => count($productos),
        'unidades' => 0
    ];
    
    foreach ($productos as $p) {
        $totales['inversion'] += $p['precio_compra'] * $p['cantidad'];
        $totales['valor_venta'] += $p['precio_venta'] * $p['cantidad'];
        $totales['unidades'] += $p['cantidad'];
    }
    
    exportarExcel($productos, $filtros, $totales);
    exit;
}

// Obtener parámetros de filtro para la vista normal
$categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
$mostrar_cero = isset($_GET['mostrar_cero']) ? true : false;

// Consulta base con filtros
$sql = "SELECT p.id, p.descripcion, p.codigo_barras, 
               p.cantidad, p.stock_minimo, p.stock_maximo,
               p.precio_compra, p.precio_venta, 
               c.nombre as categoria,
               (p.precio_compra * p.cantidad) as costo_inventario,
               FORMAT(p.precio_compra, 2, 'es_CO') as precio_compra_f,
               FORMAT(p.precio_venta, 2, 'es_CO') as precio_venta_f,
               FORMAT((p.precio_compra * p.cantidad), 2, 'es_CO') as costo_inventario_f
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.id
        " . (!$mostrar_cero ? " WHERE p.cantidad > 0" : "") . "
        " . ($categoria_id ? ($mostrar_cero ? " WHERE " : " AND ") . " p.categoria_id = ?" : "") . "
        ORDER BY c.nombre, p.descripcion";

$stmt = $conexion->prepare($sql);
if ($categoria_id) {
    $stmt->bind_param("i", $categoria_id);
}
$stmt->execute();
$productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener categorías para el filtro
$categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Calcular totales
$total_inversion = 0;
$total_productos = 0;
$total_valor_venta = 0;
foreach ($productos as $p) {
    $total_inversion += $p['precio_compra'] * $p['cantidad'];
    $total_valor_venta += $p['precio_venta'] * $p['cantidad'];
    $total_productos += $p['cantidad'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tituloPagina ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .card-inventario {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .card-header-inventario {
            background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
            color: white;
        }
        .badge-inventario {
            font-size: 0.85rem;
        }
        .bg-inventario-minimo {
            background-color: #ffc107;
            color: #212529;
        }
        .bg-inventario-critico {
            background-color: #dc3545;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(26, 58, 47, 0.05);
        }
        .filtros-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .kpi-card {
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            height: 100%;
        }
        .kpi-title {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .kpi-value {
            font-size: 1.5rem;
            font-weight: 600;
        }
        .progress-thin {
            height: 6px;
        }
        .stock-critico {
            background-color: #FFEBEE;
        }
        .stock-bajo {
            background-color: #FFF3E0;
        }
        .export-btn {
            position: relative;
        }
        .export-loading {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php include 'inventario_navbar.php'; ?>
        <div class="card card-inventario mb-4">
            <div class="card-header card-header-inventario">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-boxes me-2"></i>Reporte de Inventario</h4>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download me-1"></i> Exportar
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><button class="dropdown-item" onclick="exportarExcel()" id="exportExcelBtn">
                                <i class="fas fa-file-excel me-2 text-success"></i> Excel
                                <span class="export-loading" id="excelLoading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </span>
                            </button></li>
                            <li><button class="dropdown-item" onclick="window.print()">
                                <i class="fas fa-print me-2 text-primary"></i> Imprimir
                            </button></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Filtros -->
                <div class="filtros-container">
                    <form method="get" class="row g-3" id="filtrosForm">
                        <div class="col-md-5">
                            <label class="form-label">Departamento/Categoría</label>
                            <select class="form-select" name="categoria_id">
                                <option value="">Todos los departamentos</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $categoria_id == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="form-check form-switch mt-4 pt-1">
                                <input class="form-check-input" type="checkbox" id="mostrar_cero" name="mostrar_cero" <?= $mostrar_cero ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mostrar_cero">Mostrar productos con existencia cero</label>
                            </div>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="informe_inventario.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- KPIs -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="kpi-card border-start border-4 border-primary">
                            <div class="kpi-title">Costo Total del Inventario</div>
                            <div class="kpi-value text-primary">$<?= number_format($total_inversion, 2) ?></div>
                            <small class="text-muted">Inversión en productos</small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="kpi-card border-start border-4 border-success">
                            <div class="kpi-title">Valor Total de Venta</div>
                            <div class="kpi-value text-success">$<?= number_format($total_valor_venta, 2) ?></div>
                            <small class="text-muted">Potencial de venta</small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="kpi-card border-start border-4 border-info">
                            <div class="kpi-title">Productos en Inventario</div>
                            <div class="kpi-value text-info"><?= count($productos) ?></div>
                            <small class="text-muted"><?= $total_productos ?> unidades totales</small>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de productos -->
                <?php if (count($productos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabla-inventario">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Categoría</th>
                                    <th>Existencia</th>
                                    <th>Mínimo</th>
                                    <th>Máximo</th>
                                    <th>Precio Costo</th>
                                    <th>Precio Venta</th>
                                    <th>Costo Inventario</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $p): 
                                    $clase_fila = '';
                                    if ($p['cantidad'] <= 0) {
                                        $clase_fila = 'stock-critico';
                                    } elseif ($p['cantidad'] <= $p['stock_minimo']) {
                                        $clase_fila = 'stock-bajo';
                                    }
                                ?>
                                    <tr class="<?= $clase_fila ?>">
                                        <td><?= $p['codigo_barras'] ? htmlspecialchars($p['codigo_barras']) : '<span class="text-muted">N/A</span>' ?></td>
                                        <td><?= htmlspecialchars($p['descripcion']) ?></td>
                                        <td><?= htmlspecialchars($p['categoria']) ?></td>
                                        <td class="fw-bold"><?= $p['cantidad'] ?></td>
                                        <td><?= $p['stock_minimo'] ?></td>
                                        <td><?= $p['stock_maximo'] ?? 'N/A' ?></td>
                                        <td>$<?= $p['precio_compra_f'] ?></td>
                                        <td>$<?= $p['precio_venta_f'] ?></td>
                                        <td class="fw-bold">$<?= $p['costo_inventario_f'] ?></td>
                                        <td>
                                            <a href="productos.php?editar=<?= $p['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary me-1"
                                               title="Modificar producto">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="ajustes_inventario.php?producto_id=<?= $p['id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary"
                                               title="Ajustar inventario">
                                                <i class="fas fa-sliders-h"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Resumen por categoría -->
                    <div class="card mt-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Resumen por Categoría</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php 
                                // Agrupar por categoría
                                $resumen_categorias = [];
                                foreach ($productos as $p) {
                                    if (!isset($resumen_categorias[$p['categoria']])) {
                                        $resumen_categorias[$p['categoria']] = [
                                            'productos' => 0,
                                            'inversion' => 0,
                                            'unidades' => 0
                                        ];
                                    }
                                    $resumen_categorias[$p['categoria']]['productos']++;
                                    $resumen_categorias[$p['categoria']]['inversion'] += $p['precio_compra'] * $p['cantidad'];
                                    $resumen_categorias[$p['categoria']]['unidades'] += $p['cantidad'];
                                }
                                
                                // Ordenar por inversión (mayor a menor)
                                uasort($resumen_categorias, function($a, $b) {
                                    return $b['inversion'] <=> $a['inversion'];
                                });
                                
                                foreach ($resumen_categorias as $categoria => $datos): 
                                    $porcentaje = ($datos['inversion'] / $total_inversion) * 100;
                                ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?= htmlspecialchars($categoria) ?></span>
                                            <span>$<?= number_format($datos['inversion'], 2) ?> (<?= round($porcentaje) ?>%)</span>
                                        </div>
                                        <div class="progress progress-thin">
                                            <div class="progress-bar" 
                                                 role="progressbar" 
                                                 style="width: <?= $porcentaje ?>%; background-color: <?= sprintf('#%06X', mt_rand(0, 0xFFFFFF)) ?>"
                                                 aria-valuenow="<?= $porcentaje ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?= $datos['productos'] ?> productos, <?= $datos['unidades'] ?> unidades
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                        <h3>No hay productos en inventario</h3>
                        <p class="text-muted">No se encontraron productos con los filtros actuales</p>
                        <a href="informe_inventario.php" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-1"></i> Reiniciar filtros
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Función para exportar a Excel
    function exportarExcel() {
        // Mostrar loading
        const btn = $('#exportExcelBtn');
        const loading = $('#excelLoading');
        btn.prop('disabled', true);
        loading.show();
        
        // Obtener todos los parámetros del formulario
        const formData = $('#filtrosForm').serialize();
        
        // Crear iframe para la descarga
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = `informe_inventario.php?export=excel&${formData}`;
        
        iframe.onload = function() {
            btn.prop('disabled', false);
            loading.hide();
            document.body.removeChild(iframe);
            
            Swal.fire({
                icon: 'success',
                title: 'Exportación completada',
                text: 'El reporte se ha exportado a Excel correctamente',
                timer: 3000,
                showConfirmButton: false
            });
        };
        
        iframe.onerror = function() {
            btn.prop('disabled', false);
            loading.hide();
            document.body.removeChild(iframe);
            
            Swal.fire({
                icon: 'error',
                title: 'Error en exportación',
                text: 'Ocurrió un problema al generar el archivo Excel',
                timer: 3000,
                showConfirmButton: false
            });
        };
        
        document.body.appendChild(iframe);
    }

    // Inicializar tooltips
    $(function () {
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
    </script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>