<?php
$tituloPagina = 'Detalle de Venta';
session_start();

// Configuración de seguridad de headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['error'] = "Debe iniciar sesión para acceder a esta función";
    header("Location: login.php");
    exit;
}

include 'config/conexion.php';

// Obtener ID de venta
$venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venta_id <= 0) {
    $_SESSION['error'] = "Venta no especificada";
    header("Location: listar_ventas.php");
    exit;
}

// Consultar cabecera de venta
$stmt = $conexion->prepare("SELECT v.*, u.nombre as vendedor, c.nombre as cliente_nombre, 
                           c.identificacion, c.direccion, c.telefono, c.email
                           FROM ventas v
                           JOIN usuarios u ON v.usuario_id = u.id
                           JOIN clientes c ON v.cliente_id = c.id
                           WHERE v.id = ?");
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$venta) {
    $_SESSION['error'] = "Venta no encontrada";
    header("Location: listar_ventas.php");
    exit;
}

// Consultar detalles de venta
$stmt = $conexion->prepare("SELECT vd.*, p.nombre as producto_nombre, p.descripcion as producto_descripcion
                           FROM venta_detalles vd
                           JOIN productos p ON vd.producto_id = p.id
                           WHERE vd.venta_id = ?");
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Consultar pagos asociados
$stmt = $conexion->prepare("SELECT * FROM pagos WHERE venta_id = ? ORDER BY fecha_pago");
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular saldo pendiente
$saldo_pendiente = $venta['total'] - array_sum(array_column($pagos, 'monto'));

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="card p-4 shadow-sm" style="border-top: 4px solid #2a5a46;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="color: #1a3a2f;">Venta #<?= $venta['id'] ?></h2>
            
            <div class="d-flex gap-2">
                <a href="ventas.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
                <a href="controllers/ventas/imprimir_venta.php?id=<?= $venta['id'] ?>" 
                   class="btn" style="background-color: #d4af37; color: #1a3a2f;"
                   target="_blank">
                    <i class="fas fa-print me-2"></i>Imprimir
                </a>
                <?php if ($saldo_pendiente > 0 && $venta['estado'] !== 'cancelada'): ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#pagoModal">
                        <i class="fas fa-money-bill-wave me-2"></i>Registrar Pago
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Encabezado de venta -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header" style="background-color: #1a3a2f; color: white;">
                        <strong>Información del Cliente</strong>
                    </div>
                    <div class="card-body">
                        <p><strong>Nombre:</strong> <?= htmlspecialchars($venta['cliente_nombre']) ?></p>
                        <p><strong>Documento:</strong> <?= htmlspecialchars($venta['identificacion'] ?? 'N/A') ?></p>
                        <p><strong>Contacto:</strong> <?= htmlspecialchars($venta['email'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header" style="background-color: #1a3a2f; color: white;">
                        <strong>Información de la Venta</strong>
                    </div>
                    <div class="card-body">
                        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></p>
                        <p><strong>Estado:</strong> 
                            <span class="badge <?= $venta['estado'] === 'completada' ? 'bg-success' : 
                                              ($venta['estado'] === 'cancelada' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                <?= ucfirst($venta['estado']) ?>
                            </span>
                        </p>
                        <p><strong>Método de Pago:</strong> <?= ucfirst($venta['forma_pago'] ?? 'No especificado') ?></p>
                        <p><strong>Atendido por:</strong> <?= htmlspecialchars($venta['vendedor']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalles de la venta -->
        <div class="table-responsive mb-4">
            <table class="table table-hover">
                <thead style="background-color: #1a3a2f; color: white;">
                    <tr>
                        <th>Producto</th>
                        <th>Descripción</th>
                        <th class="text-end">Precio Unitario</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?= htmlspecialchars($detalle['producto_nombre']) ?></td>
                            <td><?= htmlspecialchars($detalle['producto_descripcion']) ?></td>
                            <td class="text-end">$<?= number_format($detalle['precio_unitario'], 2, ',', '.') ?></td>
                            <td class="text-center"><?= $detalle['cantidad'] ?></td>
                            <td class="text-end">$<?= number_format($detalle['subtotal'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end">$<?= number_format($venta['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Impuestos:</strong></td>
                        <td class="text-end">$<?= number_format($venta['impuestos'], 2, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Descuentos:</strong></td>
                        <td class="text-end">$<?= number_format($venta['descuento'], 2, ',', '.') ?></td>
                    </tr>
                    <tr class="table-active">
                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                        <td class="text-end"><strong>$<?= number_format($venta['total'], 2, ',', '.') ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Historial de pagos -->
        <div class="card mb-4">
            <div class="card-header" style="background-color: #1a3a2f; color: white;">
                <strong>Historial de Pagos</strong>
            </div>
            <div class="card-body">
                <?php if (count($pagos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Método</th>
                                    <th class="text-end">Monto</th>
                                    <th>Referencia</th>
                                    <th>Registrado por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagos as $pago): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></td>
                                        <td><?= ucfirst($pago['metodo']) ?></td>
                                        <td class="text-end">$<?= number_format($pago['monto'], 2, ',', '.') ?></td>
                                        <td><?= $pago['referencia'] ?? 'N/A' ?></td>
                                        <td><?= htmlspecialchars($pago['registrado_por'] ?? 'Sistema') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">No se han registrado pagos para esta venta</p>
                <?php endif; ?>

                <div class="d-flex justify-content-between mt-3">
                    <h5 class="mb-0">Saldo Pendiente:</h5>
                    <h4 class="mb-0 <?= $saldo_pendiente > 0 ? 'text-danger' : 'text-success' ?>">
                        $<?= number_format($saldo_pendiente, 2, ',', '.') ?>
                    </h4>
                </div>
            </div>
        </div>

        <!-- Notas -->
        <?php if (!empty($venta['notas'])): ?>
            <div class="card">
                <div class="card-header" style="background-color: #1a3a2f; color: white;">
                    <strong>Notas Adicionales</strong>
                </div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($venta['notas'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para registrar pago -->
<div class="modal fade" id="pagoModal" tabindex="-1" aria-labelledby="pagoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #1a3a2f; color: white;">
                <h5 class="modal-title" id="pagoModalLabel">Registrar Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="registrar_pago.php" method="POST">
                <input type="hidden" name="venta_id" value="<?= $venta['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="monto" class="form-label">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="monto" name="monto" 
                                   min="0.01" max="<?= $saldo_pendiente ?>" step="0.01" 
                                   value="<?= number_format($saldo_pendiente, 2, '.', '') ?>" required>
                        </div>
                        <small class="text-muted">Máximo: $<?= number_format($saldo_pendiente, 2, ',', '.') ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="metodo" class="form-label">Método de Pago</label>
                        <select class="form-select" id="metodo" name="metodo" required>
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia Bancaria</option>
                            <option value="tarjeta">Tarjeta de Crédito/Débito</option>
                            <option value="cheque">Cheque</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="referencia" class="form-label">Referencia (opcional)</label>
                        <input type="text" class="form-control" id="referencia" name="referencia">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>