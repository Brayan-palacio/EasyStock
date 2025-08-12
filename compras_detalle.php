<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Detalle de Compra - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener ID de compra
$compra_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($compra_id <= 0) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Compra no especificada'
    ];
    header("Location: compras.php");
    exit();
}

// Consulta principal
$sql = "SELECT 
            c.*, 
            p.nombre as proveedor,
            u.nombre as usuario,
            a.nombre as almacen
        FROM compras c
        JOIN proveedores p ON c.proveedor_id = p.id
        JOIN usuarios u ON c.usuario_id = u.id
        LEFT JOIN almacenes a ON c.almacen_id = a.id
        WHERE c.id = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $compra_id);
$stmt->execute();
$compra = $stmt->get_result()->fetch_assoc();

if (!$compra) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Compra no encontrada'
    ];
    header("Location: compras.php");
    exit();
}

// Consulta detalle
$detalle = $conexion->query("
    SELECT 
        d.*,
        pr.descripcion as producto,
        pr.codigo_barras,
        pr.unidad_medida
    FROM compras_detalle d
    JOIN productos pr ON d.producto_id = pr.id
    WHERE d.compra_id = $compra_id
")->fetch_all(MYSQLI_ASSOC);

// Consulta movimientos relacionados en Kardex
$movimientos = $conexion->query("
    SELECT 
        m.*,
        p.descripcion as producto
    FROM movimientos m
    JOIN productos p ON m.producto_id = p.id
    WHERE m.referencia = '{$compra['num_factura']}'
    ORDER BY m.fecha DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<style>
    .card-compra {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header-compra {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
    }
    .badge-estado {
        font-size: 0.9rem;
        padding: 0.35rem 0.6rem;
    }
    .estado-pendiente {
        background-color: #ffc107;
        color: #212529;
    }
    .estado-recibido {
        background-color: #28a745;
    }
    .estado-anulado {
        background-color: #dc3545;
    }
    .table-detalle th {
        background-color: #f8f9fa;
    }
    .documento-link {
        transition: all 0.3s;
    }
    .documento-link:hover {
        transform: translateY(-2px);
    }
</style>

<div class="container py-4">
    <!-- Encabezado de la compra -->
    <div class="card card-compra mb-4">
        <div class="card-header card-header-compra">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-file-invoice me-2"></i>
                    Factura #<?= htmlspecialchars($compra['num_factura']) ?>
                </h4>
                <span class="badge badge-estado estado-<?= strtolower($compra['estado']) ?>">
                    <?= strtoupper($compra['estado']) ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <h5>Proveedor</h5>
                        <div class="ps-3">
                            <p class="mb-1"><strong><?= htmlspecialchars($compra['proveedor']) ?></strong></p>
                            <p class="mb-1 text-muted small">Factura: <?= htmlspecialchars($compra['num_factura']) ?></p>
                            <p class="mb-1 text-muted small">Fecha: <?= date('d/m/Y', strtotime($compra['fecha'])) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <h5>Información de Compra</h5>
                        <div class="ps-3">
                            <p class="mb-1"><strong>Registrado por:</strong> <?= htmlspecialchars($compra['usuario']) ?></p>
                            <p class="mb-1"><strong>Almacén:</strong> <?= htmlspecialchars($compra['almacen'] ?? 'N/A') ?></p>
                            <p class="mb-1"><strong>Fecha registro:</strong> <?= date('d/m/Y H:i', strtotime($compra['fecha_registro'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documentos adjuntos -->
            <?php if (!empty($compra['documento_pdf']) || !empty($compra['documento_xml'])): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h5>Documentos</h5>
                        <div class="d-flex gap-3">
                            <?php if (!empty($compra['documento_pdf'])): ?>
                                <a href="<?= htmlspecialchars($compra['documento_pdf']) ?>" 
                                   class="btn btn-outline-danger documento-link"
                                   target="_blank">
                                    <i class="fas fa-file-pdf me-2"></i> Ver PDF
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($compra['documento_xml'])): ?>
                                <a href="<?= htmlspecialchars($compra['documento_xml']) ?>" 
                                   class="btn btn-outline-primary documento-link"
                                   target="_blank">
                                    <i class="fas fa-file-code me-2"></i> Ver XML
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detalle de productos -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-boxes me-2"></i> Productos Comprados</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-detalle mb-0">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="35%">Producto</th>
                            <th width="10%">Código</th>
                            <th width="10%">Cantidad</th>
                            <th width="10%">Unidad</th>
                            <th width="15%">P. Unitario</th>
                            <th width="15%">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contador = 1;
                        foreach ($detalle as $item): 
                            $subtotal = $item['cantidad'] * $item['precio_unitario'];
                        ?>
                            <tr>
                                <td><?= $contador++ ?></td>
                                <td>
                                    <?= htmlspecialchars($item['producto']) ?>
                                    <?php if (!empty($item['lote'])): ?>
                                        <br><small class="text-muted">Lote: <?= htmlspecialchars($item['lote']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['codigo_barras']) ?></td>
                                <td><?= number_format($item['cantidad'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($item['unidad_medida']) ?></td>
                                <td>$<?= number_format($item['precio_unitario'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($subtotal, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-group-divider">
                        <tr>
                            <td colspan="5"></td>
                            <th class="text-end">Subtotal:</th>
                            <td>$<?= number_format($compra['total'], 0, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td colspan="5"></td>
                            <th class="text-end">IVA (<?= $compra['total'] > 0 ? number_format($compra['iva']/$compra['total']*100, 2) : '0' ?>%)</th>
                            <td>$<?= number_format($compra['iva'], 2) ?></td>
                        </tr>
                        <tr>
                            <td colspan="5"></td>
                            <th class="text-end">Total:</th>
                            <td class="fw-bold">$<?= number_format($compra['total'], 0, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Movimientos en Kardex -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i> Movimientos Relacionados</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($movimientos)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($mov['fecha'])) ?></td>
                                    <td><?= htmlspecialchars($mov['producto']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $mov['tipo'] === 'entrada' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($mov['tipo']) ?>
                                        </span>
                                    </td>
                                    <td><?= $mov['cantidad'] ?></td>
                                    <td><?= htmlspecialchars($mov['usuario_id']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    No se encontraron movimientos relacionados en el Kardex
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botones de acción -->
    <div class="d-flex justify-content-between mt-4">
        <a href="compras.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Volver a Compras
        </a>
        <div class="btn-group">
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Imprimir
            </button>
                <a href="generar_pdf_compra.php?id=<?= $compra_id ?>" class="btn btn-outline-danger no-print">
                    <i class="fas fa-file-pdf me-2"></i> Exportar a PDF
                </a>
            <?php if ($compra['estado'] == 'Pendiente' && $_SESSION['rol_usuario'] == 'Administrador'): ?>
                <a href="acciones/anular_compra.php?id=<?= $compra_id ?>" 
                   class="btn btn-outline-danger"
                   onclick="return confirm('¿Confirmar anulación de esta compra?')">
                    <i class="fas fa-ban me-2"></i> Anular Compra
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>