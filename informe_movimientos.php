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

$tituloPagina = 'Reporte de Movimientos - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Función para exportar a Excel con formato profesional
function exportarExcel($movimientos, $filtros) {
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
            'entrada' => [
                'font' => ['color' => ['rgb' => '28a745']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E6FFED']]
            ],
            'salida' => [
                'font' => ['color' => ['rgb' => 'dc3545']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFE6E6']]
            ],
            'normal' => [
                'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'DDDDDD']]]
            ],
            'footer' => [
                'font' => ['italic' => true, 'size' => 9],
                'alignment' => ['horizontal' => 'right']
            ]
        ];
        
        // Título del reporte
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'REPORTE DE MOVIMIENTOS DE INVENTARIO - EASYSTOCK');
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        
        // Información de filtros
        $sheet->setCellValue('A2', 'Fecha inicio: ' . date('d/m/Y', strtotime($filtros['fecha_inicio'])));
        $sheet->setCellValue('B2', 'Fecha fin: ' . date('d/m/Y', strtotime($filtros['fecha_fin'])));
        if ($filtros['tipo_movimiento']) {
            $sheet->setCellValue('C2', 'Tipo: ' . ucfirst(str_replace('_', ' ', $filtros['tipo_movimiento'])));
        }
        $sheet->setCellValue('H2', 'Generado: ' . date('d/m/Y H:i'));
        $sheet->getStyle('A2:H2')->applyFromArray($styles['footer']);
        
        // Encabezados de tabla
        $headers = [
            'Fecha/Hora', 'Producto', 'Código', 'Movimiento', 
            'Tipo', 'Stock Anterior', 'Cantidad', 'Stock Actual'
        ];
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:H4')->applyFromArray($styles['header']);
        
        // Datos
        $row = 5;
        foreach ($movimientos as $m) {
            $es_entrada = in_array($m['tipo'], ['entrada', 'ajuste_positivo', 'devolucion']);
            $signo = $es_entrada ? '+' : '-';
            
            $sheet->setCellValue('A' . $row, $m['fecha_formateada']);
            $sheet->setCellValue('B' . $row, $m['producto_descripcion']);
            $sheet->setCellValue('C' . $row, $m['codigo_barras']);
            $sheet->setCellValue('D' . $row, $m['motivo'] ?: 'N/A');
            
            // Tipo de movimiento
            $tipo = match($m['tipo']) {
                'entrada' => 'Entrada',
                'salida' => 'Salida',
                'ajuste_positivo' => 'Ajuste +',
                'ajuste_negativo' => 'Ajuste -',
                'devolucion' => 'Devolución',
                default => $m['tipo']
            };
            $sheet->setCellValue('E' . $row, $tipo);
            
            $sheet->setCellValue('F' . $row, $m['stock_anterior'] ?? 'N/A');
            $sheet->setCellValue('G' . $row, $signo . $m['cantidad']);
            $sheet->setCellValue('H' . $row, $m['stock_actual'] ?? 'N/A');
            
            // Aplicar estilo según tipo de movimiento
            if ($es_entrada) {
                $sheet->getStyle('G' . $row)->applyFromArray($styles['entrada']);
            } else {
                $sheet->getStyle('G' . $row)->applyFromArray($styles['salida']);
            }
            
            $row++;
        }
        
        // Aplicar estilos generales
        $sheet->getStyle('A5:H' . ($row-1))->applyFromArray($styles['normal']);
        
        // Autoajustar columnas
        foreach (range('A', 'H') as $col) {
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
        header('Content-Disposition: attachment;filename="movimientos_inventario_'.date('Ymd_His').'.xlsx"');
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
        'fecha_inicio' => $_GET['fecha_inicio'] ?? date('Y-m-01'),
        'fecha_fin' => $_GET['fecha_fin'] ?? date('Y-m-d'),
        'tipo_movimiento' => $_GET['tipo_movimiento'] ?? '',
        'busqueda' => $_GET['busqueda'] ?? '',
        'usuario_id' => $_GET['usuario_id'] ?? 0,
        'producto_id' => $_GET['producto_id'] ?? 0
    ];
    
    // Obtener movimientos para exportación
    $sql = "SELECT m.*, 
                   p.descripcion as producto_descripcion, 
                   p.codigo_barras,
                   u.nombre as usuario_nombre,
                   DATE_FORMAT(m.fecha, '%d/%m/%Y %H:%i') as fecha_formateada
            FROM movimientos m
            JOIN productos p ON m.producto_id = p.id
            JOIN usuarios u ON m.usuario_id = u.id
            WHERE DATE(m.fecha) BETWEEN ? AND ?
            " . ($filtros['tipo_movimiento'] ? " AND m.tipo = ?" : "") . "
            " . ($filtros['usuario_id'] ? " AND m.usuario_id = ?" : "") . "
            " . ($filtros['producto_id'] ? " AND m.producto_id = ?" : "") . "
            " . ($filtros['busqueda'] ? " AND (p.descripcion LIKE ? OR p.codigo_barras LIKE ? OR m.motivo LIKE ?)" : "") . "
            ORDER BY m.fecha DESC";

    $stmt = $conexion->prepare($sql);

    // Bind parameters dinámicamente
    $params = [$filtros['fecha_inicio'], $filtros['fecha_fin']];
    $types = 'ss';

    if ($filtros['tipo_movimiento']) {
        $params[] = $filtros['tipo_movimiento'];
        $types .= 's';
    }

    if ($filtros['usuario_id']) {
        $params[] = $filtros['usuario_id'];
        $types .= 'i';
    }

    if ($filtros['producto_id']) {
        $params[] = $filtros['producto_id'];
        $types .= 'i';
    }

    if ($filtros['busqueda']) {
        $busqueda_like = "%{$filtros['busqueda']}%";
        $params[] = $busqueda_like;
        $params[] = $busqueda_like;
        $params[] = $busqueda_like;
        $types .= 'sss';
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    exportarExcel($movimientos, $filtros);
    exit;
}

// Obtener parámetros de filtro para la vista normal
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$tipo_movimiento = isset($_GET['tipo_movimiento']) ? $_GET['tipo_movimiento'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$producto_id = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

// Consulta base con filtros
$sql = "SELECT m.*, 
               p.descripcion as producto_descripcion, 
               p.codigo_barras,
               u.nombre as usuario_nombre,
               DATE_FORMAT(m.fecha, '%d/%m/%Y %H:%i') as fecha_formateada,
               CASE 
                   WHEN m.tipo = 'entrada' THEN 'bg-success'
                   WHEN m.tipo = 'salida' THEN 'bg-danger'
                   WHEN m.tipo = 'ajuste_positivo' THEN 'bg-warning text-dark'
                   WHEN m.tipo = 'ajuste_negativo' THEN 'bg-warning text-dark'
                   WHEN m.tipo = 'devolucion' THEN 'bg-info'
                   ELSE 'bg-secondary'
               END as clase_tipo
        FROM movimientos m
        JOIN productos p ON m.producto_id = p.id
        JOIN usuarios u ON m.usuario_id = u.id
        WHERE DATE(m.fecha) BETWEEN ? AND ?
        " . ($tipo_movimiento ? " AND m.tipo = ?" : "") . "
        " . ($usuario_id ? " AND m.usuario_id = ?" : "") . "
        " . ($producto_id ? " AND m.producto_id = ?" : "") . "
        " . ($busqueda ? " AND (p.descripcion LIKE ? OR p.codigo_barras LIKE ? OR m.motivo LIKE ?)" : "") . "
        ORDER BY m.fecha DESC";

$stmt = $conexion->prepare($sql);

// Bind parameters dinámicamente
$params = [$fecha_inicio, $fecha_fin];
$types = 'ss';

if ($tipo_movimiento) {
    $params[] = $tipo_movimiento;
    $types .= 's';
}

if ($usuario_id) {
    $params[] = $usuario_id;
    $types .= 'i';
}

if ($producto_id) {
    $params[] = $producto_id;
    $types .= 'i';
}

if ($busqueda) {
    $busqueda_like = "%$busqueda%";
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $types .= 'sss';
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener datos para filtros
$usuarios = $conexion->query("SELECT id, nombre FROM usuarios ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$productos = $conexion->query("SELECT id, descripcion FROM productos ORDER BY descripcion")->fetch_all(MYSQLI_ASSOC);
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
        .card-movimientos {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .card-header-movimientos {
            background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
            color: white;
        }
        .filtros-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .badge-movimiento {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        .table th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
        }
        .cantidad-entrada {
            color: #28a745;
            font-weight: bold;
        }
        .cantidad-salida {
            color: #dc3545;
            font-weight: bold;
        }
        .movimiento-kit {
            background-color: #e83e8c;
            color: white;
            padding: 0.2em 0.4em;
            border-radius: 3px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php include 'inventario_navbar.php'; ?>
        <div class="card card-movimientos mb-4">
            <div class="card-header card-header-movimientos">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Reporte de Movimientos</h4>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download me-1"></i> Exportar
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><button class="dropdown-item" onclick="exportarExcel()">
                                <i class="fas fa-file-excel me-2 text-success"></i> Excel
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
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" value="<?= $fecha_inicio ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" name="fecha_fin" value="<?= $fecha_fin ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Tipo Movimiento</label>
                            <select class="form-select" name="tipo_movimiento">
                                <option value="">Todos</option>
                                <option value="entrada" <?= $tipo_movimiento == 'entrada' ? 'selected' : '' ?>>Entradas</option>
                                <option value="salida" <?= $tipo_movimiento == 'salida' ? 'selected' : '' ?>>Salidas</option>
                                <option value="ajuste_positivo" <?= $tipo_movimiento == 'ajuste_positivo' ? 'selected' : '' ?>>Ajustes (+)</option>
                                <option value="ajuste_negativo" <?= $tipo_movimiento == 'ajuste_negativo' ? 'selected' : '' ?>>Ajustes (-)</option>
                                <option value="devolucion" <?= $tipo_movimiento == 'devolucion' ? 'selected' : '' ?>>Devoluciones</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Usuario</label>
                            <select class="form-select" name="usuario_id">
                                <option value="">Todos</option>
                                <?php foreach ($usuarios as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $usuario_id == $u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Producto</label>
                            <select class="form-select" name="producto_id">
                                <option value="">Todos</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $producto_id == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['descripcion']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Buscar (Producto, Código, Motivo)</label>
                            <input type="text" class="form-control" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
                        </div>
                        
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="informe_movimientos.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Resumen -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Mostrando movimientos del <strong><?= date('d/m/Y', strtotime($fecha_inicio)) ?></strong> al 
                            <strong><?= date('d/m/Y', strtotime($fecha_fin)) ?></strong>. 
                            Total: <strong><?= count($movimientos) ?></strong> registros.
                        </div>
                    </div>
                </div>
                
                <!-- Lista de movimientos -->
                <?php if (count($movimientos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabla-movimientos">
                            <thead class="table-light">
                                <tr>
                                    <th width="120">Fecha/Hora</th>
                                    <th>Producto</th>
                                    <th width="150">Movimiento</th>
                                    <th width="80">Tipo</th>
                                    <th width="80">Había</th>
                                    <th width="100">Cantidad</th>
                                    <th width="80">Hay</th>
                                    <th width="120">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimientos as $m): 
                                    $es_entrada = in_array($m['tipo'], ['entrada', 'ajuste_positivo', 'devolucion']);
                                    $clase_cantidad = $es_entrada ? 'cantidad-entrada' : 'cantidad-salida';
                                    $signo = $es_entrada ? '+' : '-';
                                ?>
                                    <tr>
                                        <td><?= $m['fecha_formateada'] ?></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($m['producto_descripcion']) ?></div>
                                            <small class="text-muted"><?= $m['codigo_barras'] ? 'Código: ' . htmlspecialchars($m['codigo_barras']) : 'Sin código' ?></small>
                                        </td>
                                        <td>
                                            <?= $m['motivo'] ? htmlspecialchars($m['motivo']) : 'N/A' ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-movimiento <?= $m['clase_tipo'] ?>">
                                                <?= match($m['tipo']) {
                                                    'entrada' => 'Entrada',
                                                    'salida' => 'Salida',
                                                    'ajuste_positivo' => 'Ajuste +',
                                                    'ajuste_negativo' => 'Ajuste -',
                                                    'devolucion' => 'Devolución',
                                                    default => $m['tipo']
                                                } ?>
                                            </span>
                                        </td>
                                        <td><?= $m['stock_anterior'] ?? 'N/A' ?></td>
                                        <td class="<?= $clase_cantidad ?>"><?= $signo . $m['cantidad'] ?></td>
                                        <td><?= $m['stock_actual'] ?? 'N/A' ?></td>
                                        <td><?= htmlspecialchars($m['usuario_nombre']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-exchange-alt fa-4x text-muted mb-4"></i>
                        <h3>No hay movimientos registrados</h3>
                        <p class="text-muted">No se encontraron movimientos con los filtros actuales</p>
                        <a href="informe_movimientos.php" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-1"></i> Reiniciar filtros
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Función mejorada para exportar a Excel
    function exportarExcel() {
        // Obtener todos los parámetros del formulario
        const params = new URLSearchParams();
        
        // Agregar todos los filtros
        params.append('export', 'excel');
        params.append('fecha_inicio', $('input[name="fecha_inicio"]').val());
        params.append('fecha_fin', $('input[name="fecha_fin"]').val());
        params.append('tipo_movimiento', $('select[name="tipo_movimiento"]').val());
        params.append('busqueda', $('input[name="busqueda"]').val());
        params.append('usuario_id', $('select[name="usuario_id"]').val());
        params.append('producto_id', $('select[name="producto_id"]').val());
        
        // Mostrar loading
        const btn = $('button[onclick="exportarExcel()"]');
        const originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Generando...').prop('disabled', true);
        
        // Crear iframe para la descarga
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = `informe_movimientos.php?${params.toString()}`;
        
        iframe.onload = function() {
            btn.html(originalText).prop('disabled', false);
            document.body.removeChild(iframe);
            
            Swal.fire({
                icon: 'success',
                title: 'Exportación completada',
                text: 'El archivo Excel se ha generado correctamente',
                timer: 3000,
                showConfirmButton: false
            });
        };
        
        iframe.onerror = function() {
            btn.html(originalText).prop('disabled', false);
            document.body.removeChild(iframe);
            
            Swal.fire({
                icon: 'error',
                title: 'Error en exportación',
                text: 'Ocurrió un problema al generar el archivo',
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