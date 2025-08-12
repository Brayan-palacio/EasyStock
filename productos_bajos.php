<?php
// 1. CONFIGURACIÓN INICIAL MEJORADA
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die('Método no permitido');
}

// Autoload de Composer (mejor práctica)
require __DIR__ . '../vendor/autoload.php';

// Configuración de zona horaria
date_default_timezone_set('America/Bogota');

// Iniciar sesión y verificar autenticación
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// 2. FUNCIÓN PARA EXPORTAR A EXCEL MEJORADA
function exportarExcel($productos, $nivel_alerta, $categoria_nombre = null) {
    try {
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
            'critical' => [
                'font' => ['color' => ['rgb' => 'FF0000']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFEBEE']]
            ],
            'warning' => [
                'font' => ['color' => ['rgb' => 'FF8C00']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF3E0']]
            ],
            'normal' => [
                'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'DDDDDD']]]
            ]
        ];
        
        // Título del reporte
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'REPORTE DE PRODUCTOS BAJOS EN INVENTARIO - EASYSTOCK');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1A3A2F']],
            'alignment' => ['horizontal' => 'center']
        ]);
        
        // Información de filtros
        $sheet->setCellValue('A2', 'Nivel de alerta: ' . $nivel_alerta);
        if ($categoria_nombre) {
            $sheet->setCellValue('B2', 'Categoría: ' . $categoria_nombre);
        }
        $sheet->setCellValue('G2', 'Generado: ' . date('d/m/Y H:i'));
        $sheet->getStyle('A2:G2')->getFont()->setItalic(true)->setSize(9);
        
        // Encabezados de tabla
        $headers = [
            'ID', 'Código', 'Descripción', 'Categoría', 
            'Stock', 'Precio Compra', 'Precio Venta'
        ];
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:G4')->applyFromArray($styles['header']);
        
        // Datos
        $row = 5;
        foreach ($productos as $producto) {
            $sheet->setCellValue('A' . $row, $producto['id']);
            $sheet->setCellValue('B' . $row, $producto['codigo_barras']);
            $sheet->setCellValue('C' . $row, $producto['descripcion']);
            $sheet->setCellValue('D' . $row, $producto['categoria']);
            $sheet->setCellValue('E' . $row, $producto['cantidad']);
            $sheet->setCellValue('F' . $row, $producto['precio_compra']);
            $sheet->setCellValue('G' . $row, $producto['precio_venta']);
            
            // Estilo condicional para stock bajo
            if ($producto['cantidad'] <= ($nivel_alerta * 0.2)) {
                $sheet->getStyle('E' . $row)->applyFromArray($styles['critical']);
            } elseif ($producto['cantidad'] <= ($nivel_alerta * 0.5)) {
                $sheet->getStyle('E' . $row)->applyFromArray($styles['warning']);
            }
            
            $row++;
        }
        
        // Aplicar estilos generales
        $sheet->getStyle('A5:G' . ($row-1))->applyFromArray($styles['normal']);
        
        // Formato de números
        $sheet->getStyle('F5:G' . $row)
            ->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');
        
        // Autoajustar columnas
        foreach (range('A', 'G') as $col) {
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
        header('Content-Disposition: attachment;filename="productos_bajos_'.date('Ymd_His').'.xlsx"');
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

// 3. FUNCIÓN PARA EXPORTAR A PDF MEJORADA
function exportarPDF($productos, $nivel_alerta, $categoria_nombre = null) {
    try {
        // Configuración TCPDF
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        // Información del documento
        $pdf->SetCreator('EasyStock');
        $pdf->SetAuthor('Sistema de Inventario');
        $pdf->SetTitle('Reporte de Productos Bajos');
        $pdf->SetSubject('Generado automáticamente');
        
        // Configurar márgenes
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 25);
        
        // Fuente por defecto
        $pdf->SetFont('helvetica', '', 10);
        
        // Agregar página
        $pdf->AddPage();
        
        // Logo y título
        $pdf->Image(__DIR__ . '../assets/img/logo.png', 15, 15, 30, 0, 'PNG');
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'REPORTE DE PRODUCTOS BAJOS EN INVENTARIO', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Información de filtros
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 0, 'Nivel de alerta: ' . $nivel_alerta, 0, 1);
        if ($categoria_nombre) {
            $pdf->Cell(0, 0, 'Categoría: ' . $categoria_nombre, 0, 1);
        }
        $pdf->Cell(0, 0, 'Generado: ' . date('d/m/Y H:i'), 0, 1);
        $pdf->Ln(10);
        
        // Tabla
        $header = ['Producto', 'Categoría', 'Stock', 'Precio Compra', 'Precio Venta', 'Estado'];
        $widths = [70, 40, 20, 30, 30, 20];
        
        // Estilo de encabezado
        $pdf->SetFillColor(26, 58, 47); // Verde oscuro
        $pdf->SetTextColor(255);
        $pdf->SetDrawColor(26, 58, 47);
        $pdf->SetLineWidth(0.3);
        $pdf->SetFont('helvetica', 'B', 10);
        
        // Encabezados
        for ($i = 0; $i < count($header); $i++) {
            $pdf->Cell($widths[$i], 7, $header[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Datos
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0);
        $fill = false;
        
        foreach ($productos as $producto) {
            // Determinar estado y color
            if ($producto['cantidad'] <= ($nivel_alerta * 0.2)) {
                $estado = 'CRÍTICO';
                $pdf->SetTextColor(220, 53, 69); // Rojo
            } elseif ($producto['cantidad'] <= ($nivel_alerta * 0.5)) {
                $estado = 'BAJO';
                $pdf->SetTextColor(253, 126, 20); // Naranja
            } else {
                $estado = 'ALERTA';
                $pdf->SetTextColor(255, 193, 7); // Amarillo
            }
            
            // Filtrar descripción larga
            $descripcion = strlen($producto['descripcion']) > 50 ? 
                substr($producto['descripcion'], 0, 47) . '...' : 
                $producto['descripcion'];
            
            $pdf->Cell($widths[0], 6, $descripcion, 'LR', 0, 'L', $fill);
            $pdf->Cell($widths[1], 6, $producto['categoria'], 'LR', 0, 'L', $fill);
            $pdf->Cell($widths[2], 6, $producto['cantidad'], 'LR', 0, 'C', $fill);
            $pdf->Cell($widths[3], 6, '$' . number_format($producto['precio_compra'], 2), 'LR', 0, 'R', $fill);
            $pdf->Cell($widths[4], 6, '$' . number_format($producto['precio_venta'], 2), 'LR', 0, 'R', $fill);
            $pdf->Cell($widths[5], 6, $estado, 'LR', 0, 'C', $fill);
            $pdf->Ln();
            
            $fill = !$fill;
            $pdf->SetTextColor(0);
        }
        
        // Cierre de tabla
        $pdf->Cell(array_sum($widths), 0, '', 'T');
        $pdf->Ln(10);
        
        // Resumen
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 0, 'Total productos: ' . count($productos), 0, 1);
        
        // Pie de página
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Página ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'C');
        
        // Generar PDF
        ob_end_clean();
        $pdf->Output('productos_bajos_'.date('Ymd_His').'.pdf', 'D');
        exit;
        
    } catch (\Exception $e) {
        error_log('Error al generar PDF: ' . $e->getMessage());
        die('Ocurrió un error al generar el archivo PDF');
    }
}

// 4. MANEJO CENTRALIZADO DE EXPORTACIONES
if (isset($_GET['export'])) {
    include 'config/conexion.php';
    
    // Validar y sanitizar parámetros
    $nivel_alerta = filter_input(INPUT_GET, 'nivel', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'default' => 10]
    ]);
    
    $categoria_id = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT);
    
    // Obtener nombre de categoría si existe
    $categoria_nombre = null;
    if ($categoria_id) {
        $stmt = $conexion->prepare("SELECT nombre FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $categoria_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $categoria_nombre = $result->fetch_row()[0];
        }
    }
    
    // Consulta segura con prepared statements
    $sql = "SELECT p.id, p.descripcion, p.codigo_barras, p.cantidad, 
                   p.precio_compra, p.precio_venta, 
                   c.nombre as categoria
            FROM productos p
            JOIN categorias c ON p.categoria_id = c.id
            WHERE p.cantidad <= ?
            " . ($categoria_id ? " AND p.categoria_id = ?" : "") . "
            ORDER BY p.cantidad ASC";
    
    $stmt = $conexion->prepare($sql);
    if ($categoria_id) {
        $stmt->bind_param("ii", $nivel_alerta, $categoria_id);
    } else {
        $stmt->bind_param("i", $nivel_alerta);
    }
    $stmt->execute();
    $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Validar que haya datos
    if (empty($productos)) {
        die('No hay productos que coincidan con los criterios de búsqueda');
    }
    
    // Redireccionar según formato
    switch (strtolower($_GET['export'])) {
        case 'excel':
            exportarExcel($productos, $nivel_alerta, $categoria_nombre);
            break;
            
        case 'pdf':
            exportarPDF($productos, $nivel_alerta, $categoria_nombre);
            break;
            
        default:
            http_response_code(400);
            die('Formato de exportación no válido');
    }
}

// 5. VISTA PRINCIPAL (NO EXPORTACIÓN)
$tituloPagina = 'Productos Bajos en Inventario - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Obtener parámetros de filtro con validación
$nivel_alerta = isset($_GET['nivel']) ? (int)$_GET['nivel'] : 10;
$categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;

// Consulta base con filtros
$sql = "SELECT SQL_CALC_FOUND_ROWS p.id, p.descripcion, p.codigo_barras, p.cantidad, 
               p.precio_compra, p.precio_venta, 
               c.nombre as categoria,
               FORMAT(p.precio_compra, 2, 'es_CO') as precio_compra_f,
               FORMAT(p.precio_venta, 2, 'es_CO') as precio_venta_f
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.id
        WHERE p.cantidad <= ?
        " . ($categoria_id ? " AND p.categoria_id = ?" : "") . "
        ORDER BY p.cantidad ASC
        LIMIT ? OFFSET ?";

$stmt = $conexion->prepare($sql);
if ($categoria_id) {
    $stmt->bind_param("iiii", $nivel_alerta, $categoria_id, $porPagina, $offset);
} else {
    $stmt->bind_param("iii", $nivel_alerta, $porPagina, $offset);
}
$stmt->execute();
$productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$paginas = ceil($total / $porPagina);

// Obtener categorías para el filtro
$categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Calcular totales
$total_inversion = 0;
$total_potencial = 0;
foreach ($productos as $p) {
    $total_inversion += $p['cantidad'] * $p['precio_compra'];
    $total_potencial += $p['cantidad'] * $p['precio_venta'];
}

// Funciones para estilos
function getStockClass($cantidad, $nivel_alerta) {
    if ($cantidad <= ($nivel_alerta * 0.2)) {
        return 'bg-stock-critico';
    } elseif ($cantidad <= ($nivel_alerta * 0.5)) {
        return 'bg-stock-bajo';
    } else {
        return 'bg-stock-medio';
    }
}

function getStockBorderClass($cantidad, $nivel_alerta) {
    if ($cantidad <= ($nivel_alerta * 0.2)) {
        return 'danger';
    } elseif ($cantidad <= ($nivel_alerta * 0.5)) {
        return 'warning';
    } else {
        return 'success';
    }
}

function getStockHeaderClass($cantidad, $nivel_alerta) {
    if ($cantidad <= ($nivel_alerta * 0.2)) {
        return 'danger bg-opacity-10';
    } elseif ($cantidad <= ($nivel_alerta * 0.5)) {
        return 'warning bg-opacity-10';
    } else {
        return 'success bg-opacity-10';
    }
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
        .card-reporte {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .card-header-reporte {
            background: linear-gradient(135deg, #d4af37 0%, #f1c40f 100%);
            color: #1a3a2f;
        }
        .badge-stock {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
        .bg-stock-critico {
            background-color: #dc3545;
            color: white;
        }
        .bg-stock-bajo {
            background-color: #fd7e14;
            color: white;
        }
        .bg-stock-medio {
            background-color: #ffc107;
            color: #212529;
        }
        .progress {
            height: 1.5rem;
        }
        .progress-bar-striped {
            background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent);
            background-size: 1rem 1rem;
        }
        .table-hover tbody tr {
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .table-hover tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 10;
            background-color: rgba(26, 58, 47, 0.05);
        }
        .filtros-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stock-indicator {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .nivel-alerta-indicator {
            position: relative;
            padding-left: 1.5rem;
        }
        .nivel-alerta-indicator::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: #ffc107;
        }
        @media (max-width: 768px) {
            .card-reporte {
                border-radius: 0;
            }
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        .view-switcher {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php include 'inventario_navbar.php'; ?>
        <div class="card card-reporte mb-4">
            <div class="card-header card-header-reporte position-relative">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Productos Bajos en Inventario</h4>
                    <div>
                        <button class="btn btn-sm btn-dark me-2" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Imprimir
                        </button>
                        <button id="toggleView" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-th"></i> Vista de Tarjetas
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Filtros -->
                <div class="filtros-container mb-4">
                    <button class="btn btn-sm btn-outline-secondary mb-2" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
                        <i class="fas fa-filter"></i> Mostrar/Ocultar Filtros
                    </button>
                    <div class="collapse show" id="filtrosCollapse">
                        <form method="get" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label nivel-alerta-indicator">Nivel de Alerta</label>
                                <input type="number" class="form-control" name="nivel" value="<?= $nivel_alerta ?>" min="1">
                                <small class="text-muted">Mostrar productos con stock ≤ este valor</small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" name="categoria_id">
                                    <option value="">Todas las categorías</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $categoria_id == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
                                <a href="productos_bajos.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Búsqueda en tiempo real -->
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="liveSearch" class="form-control" placeholder="Buscar productos...">
                    </div>
                </div>
                
                <!-- Resumen -->
                <div class="row mb-4" id="resumenTotales">
                    <div class="col-md-4">
                        <div class="card border-warning h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted">Productos con Stock Bajo</h6>
                                        <h2 class="text-warning"><?= count($productos) ?></h2>
                                    </div>
                                    <div class="icon-circle bg-warning bg-opacity-10 text-warning">
                                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-danger h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted">Inversión en Inventario</h6>
                                        <h2 class="text-danger">$<?= number_format($total_inversion, 2) ?></h2>
                                    </div>
                                    <div class="icon-circle bg-danger bg-opacity-10 text-danger">
                                        <i class="fas fa-dollar-sign fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-success h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted">Potencial de Venta</h6>
                                        <h2 class="text-success">$<?= number_format($total_potencial, 2) ?></h2>
                                    </div>
                                    <div class="icon-circle bg-success bg-opacity-10 text-success">
                                        <i class="fas fa-chart-line fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <canvas id="stockChart" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="valueChart" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Lista de productos en tabla -->
                <div id="tableView">
                    <?php if (count($productos) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Categoría</th>
                                        <th>Stock Actual</th>
                                        <th>Nivel de Alerta</th>
                                        <th>Precio Compra</th>
                                        <th>Precio Venta</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $p): 
                                        $porcentaje = min(100, ($p['cantidad'] / $nivel_alerta) * 100);
                                        $clase_badge = getStockClass($p['cantidad'], $nivel_alerta);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($p['descripcion']) ?></div>
                                                <small class="text-muted"><?= $p['codigo_barras'] ? 'Código: ' . htmlspecialchars($p['codigo_barras']) : 'Sin código' ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($p['categoria']) ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="stock-indicator me-2" style="background-color: <?= $p['cantidad'] <= ($nivel_alerta * 0.2) ? '#dc3545' : ($p['cantidad'] <= ($nivel_alerta * 0.5) ? '#fd7e14' : '#ffc107') ?>;"></div>
                                                    <span class="badge <?= $clase_badge ?> badge-stock">
                                                        <?= $p['cantidad'] ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar progress-bar-striped <?= $clase_badge ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= $porcentaje ?>%" 
                                                         aria-valuenow="<?= $porcentaje ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?= round($porcentaje) ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>$<?= $p['precio_compra_f'] ?></td>
                                            <td>$<?= $p['precio_venta_f'] ?></td>
                                            <td>
                                                <a href="entradas_inventario.php?producto_id=<?= $p['id'] ?>" 
                                                   class="btn btn-sm btn-success me-1"
                                                   data-bs-toggle="tooltip" 
                                                   title="Registrar entrada">
                                                    <i class="fas fa-arrow-down"></i>
                                                </a>
                                                <a href="productos.php?editar=<?= $p['id'] ?>" 
                                                   class="btn btn-sm btn-primary"
                                                   data-bs-toggle="tooltip" 
                                                   title="Editar producto">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($paginas > 1): ?>
                            <nav aria-label="Paginación">
                                <ul class="pagination justify-content-center mt-4">
                                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?nivel=<?= $nivel_alerta ?>&categoria_id=<?= $categoria_id ?>&pagina=<?= $pagina - 1 ?>">
                                            Anterior
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $paginas; $i++): ?>
                                        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                            <a class="page-link" href="?nivel=<?= $nivel_alerta ?>&categoria_id=<?= $categoria_id ?>&pagina=<?= $i ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= $pagina >= $paginas ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?nivel=<?= $nivel_alerta ?>&categoria_id=<?= $categoria_id ?>&pagina=<?= $pagina + 1 ?>">
                                            Siguiente
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                            <h3>¡Inventario en buen estado!</h3>
                            <p class="text-muted">No hay productos por debajo del nivel de alerta configurado (≤ <?= $nivel_alerta ?> unidades)</p>
                            <a href="productos.php" class="btn btn-primary">
                                <i class="fas fa-boxes me-1"></i> Ver todos los productos
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Vista de tarjetas (oculta por defecto) -->
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="cardView" style="display: none;">
                    <?php foreach ($productos as $p): 
                        $clase_borde = getStockBorderClass($p['cantidad'], $nivel_alerta);
                        $clase_header = getStockHeaderClass($p['cantidad'], $nivel_alerta);
                    ?>
                        <div class="col">
                            <div class="card h-100 border-<?= $clase_borde ?>">
                                <div class="card-header bg-dark text-white py-3<?= $clase_header ?>">
                                    <h5 class="card-title mb-0"><?= htmlspecialchars($p['descripcion']) ?></h5>
                                    <small class="text-white"><?= htmlspecialchars($p['categoria']) ?></small>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6 class="card-subtitle mb-1 text-muted">Stock Actual</h6>
                                        <div class="d-flex align-items-center">
                                            <div class="stock-indicator me-2" style="background-color: <?= $p['cantidad'] <= ($nivel_alerta * 0.2) ? '#dc3545' : ($p['cantidad'] <= ($nivel_alerta * 0.5) ? '#fd7e14' : '#ffc107') ?>;"></div>
                                            <span class="badge <?= getStockClass($p['cantidad'], $nivel_alerta) ?> badge-stock">
                                                <?= $p['cantidad'] ?> unidades
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6 class="card-subtitle mb-1 text-muted">Nivel de Alerta</h6>
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-striped <?= getStockClass($p['cantidad'], $nivel_alerta) ?>" 
                                                 style="width: <?= min(100, ($p['cantidad'] / $nivel_alerta) * 100) ?>%">
                                                <?= round(min(100, ($p['cantidad'] / $nivel_alerta) * 100)) ?>%
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <h6 class="card-subtitle mb-1 text-muted">Precio Compra</h6>
                                            <p class="mb-0">$<?= $p['precio_compra_f'] ?></p>
                                        </div>
                                        <div class="col-6">
                                            <h6 class="card-subtitle mb-1 text-muted">Precio Venta</h6>
                                            <p class="mb-0">$<?= $p['precio_venta_f'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between">
                                        <a href="entradas_inventario.php?producto_id=<?= $p['id'] ?>" 
                                           class="btn btn-sm btn-success"
                                           data-bs-toggle="tooltip" 
                                           title="Registrar entrada">
                                            <i class="fas fa-arrow-down"></i>
                                        </a>
                                        <a href="productos.php?editar=<?= $p['id'] ?>" 
                                           class="btn btn-sm btn-primary"
                                           data-bs-toggle="tooltip" 
                                           title="Editar producto">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Exportar -->
                <?php if (count($productos) > 0): ?>
                    <div class="d-flex justify-content-end mt-3">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-download me-1"></i> Exportar
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><button class="dropdown-item" onclick="exportarExcel()">
                                    <i class="fas fa-file-excel me-2 text-success"></i> Excel
                                </button></li>
                                <li><button class="dropdown-item" onclick="exportarPDF()">
                                    <i class="fas fa-file-pdf me-2 text-danger"></i> PDF
                                </button></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><button class="dropdown-item" onclick="window.print()">
                                    <i class="fas fa-print me-2 text-primary"></i> Imprimir
                                </button></li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
    // 6. MEJORAS EN EL JAVASCRIPT PARA EXPORTACIÓN
    // Función mejorada para exportar
    function exportarReporte(formato) {
        // Mostrar spinner
        const btn = $(`button[onclick="exportar${formato.toUpperCase()}()"]`);
        const originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Generando...').prop('disabled', true);
        
        // Obtener parámetros
        const params = new URLSearchParams({
            export: formato,
            nivel: $('input[name="nivel"]').val(),
            categoria_id: $('select[name="categoria_id"]').val() || ''
        });
        
        // Crear iframe temporal para la descarga
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = `productos_bajos.php?${params.toString()}`;
        
        iframe.onload = function() {
            btn.html(originalText).prop('disabled', false);
            document.body.removeChild(iframe);
            
            // Mostrar notificación de éxito
            Swal.fire({
                icon: 'success',
                title: 'Exportación completada',
                text: `El archivo ${formato.toUpperCase()} se ha generado correctamente`,
                timer: 3000,
                showConfirmButton: false
            });
        };
        
        iframe.onerror = function() {
            btn.html(originalText).prop('disabled', false);
            document.body.removeChild(iframe);
            
            // Mostrar notificación de error
            Swal.fire({
                icon: 'error',
                title: 'Error en exportación',
                text: 'Ocurrió un error al generar el archivo',
                timer: 3000,
                showConfirmButton: false
            });
        };
        
        document.body.appendChild(iframe);
    }

    // Asignar funciones específicas
    function exportarExcel() { exportarReporte('excel'); }
    function exportarPDF() { exportarReporte('pdf'); }

    // Recordar preferencia de vista
    function guardarPreferenciaVista(vista) {
        localStorage.setItem('preferenciaVistaProductos', vista);
    }

    function cargarPreferenciaVista() {
        const preferencia = localStorage.getItem('preferenciaVistaProductos');
        if (preferencia === 'cards') {
            $('#tableView').hide();
            $('#cardView').show();
            $('#toggleView').html('<i class="fas fa-table"></i> Vista de Tabla');
        }
    }

    // Al cargar la página
    $(document).ready(function() {
        cargarPreferenciaVista();
        
        // Cambiar entre vista de tabla y tarjetas
        $('#toggleView').click(function() {
            $('#tableView').toggle();
            $('#cardView').toggle();
            
            if ($('#tableView').is(':visible')) {
                $(this).html('<i class="fas fa-th"></i> Vista de Tarjetas');
                guardarPreferenciaVista('table');
            } else {
                $(this).html('<i class="fas fa-table"></i> Vista de Tabla');
                guardarPreferenciaVista('cards');
            }
        });
        
        // Búsqueda en tiempo real con debounce
        let timeout;
        $('#liveSearch').keyup(function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const searchTerm = $(this).val().toLowerCase();
                
                if ($('#tableView').is(':visible')) {
                    $('tbody tr').each(function() {
                        const rowText = $(this).text().toLowerCase();
                        $(this).toggle(rowText.includes(searchTerm));
                    });
                } else {
                    $('.col').each(function() {
                        const cardText = $(this).text().toLowerCase();
                        $(this).toggle(cardText.includes(searchTerm));
                    });
                }
            }, 300);
        });

        // Inicializar tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Configurar gráficos
        const stockCtx = document.getElementById('stockChart').getContext('2d');
        const stockChart = new Chart(stockCtx, {
            type: 'doughnut',
            data: {
                labels: ['Crítico', 'Bajo', 'Medio'],
                datasets: [{
                    data: [
                        <?= count(array_filter($productos, fn($p) => $p['cantidad'] <= ($nivel_alerta * 0.2))) ?>,
                        <?= count(array_filter($productos, fn($p) => $p['cantidad'] > ($nivel_alerta * 0.2) && $p['cantidad'] <= ($nivel_alerta * 0.5))) ?>,
                        <?= count(array_filter($productos, fn($p) => $p['cantidad'] > ($nivel_alerta * 0.5))) ?>
                    ],
                    backgroundColor: [
                        '#dc3545',
                        '#fd7e14',
                        '#ffc107'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Niveles de Stock'
                    }
                }
            }
        });

        const valueCtx = document.getElementById('valueChart').getContext('2d');
        const valueChart = new Chart(valueCtx, {
            type: 'bar',
            data: {
                labels: ['Inversión', 'Potencial'],
                datasets: [{
                    label: 'Valor ($)',
                    data: [<?= $total_inversion ?>, <?= $total_potencial ?>],
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(40, 167, 69, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Valor del Inventario'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
    </script>
</body>
</html>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>