<?php
$tituloPagina = 'Detalle de Cotización';
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

// Obtener ID de cotización
$cotizacion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cotizacion_id <= 0) {
    $_SESSION['error'] = "Cotización no especificada";
    header("Location: listar_cotizaciones.php");
    exit;
}

// Consultar cabecera de cotización
$stmt = $conexion->prepare("SELECT c.*, u.nombre as usuario_nombre 
                           FROM cotizaciones c
                           JOIN usuarios u ON c.usuario_id = u.id
                           WHERE c.id = ?");
$stmt->bind_param("i", $cotizacion_id);
$stmt->execute();
$cotizacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cotizacion) {
    $_SESSION['error'] = "Cotización no encontrada";
    header("Location: listar_cotizaciones.php");
    exit;
}

// Consultar detalles de cotización
$stmt = $conexion->prepare("SELECT cd.*, p.nombre as producto_nombre, p.descripcion as producto_descripcion
                           FROM cotizacion_detalles cd
                           JOIN productos p ON cd.producto_id = p.id
                           WHERE cd.cotizacion_id = ?");
$stmt->bind_param("i", $cotizacion_id);
$stmt->execute();
$detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular fecha de vencimiento
$fecha_creacion = new DateTime($cotizacion['fecha_creacion']);
$fecha_vencimiento = clone $fecha_creacion;
$fecha_vencimiento->add(new DateInterval('P' . $cotizacion['validez_dias'] . 'D'));
$hoy = new DateTime();

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="card p-4 shadow-sm" style="border-top: 4px solid #d4af37;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="color: #1a3a2f;">Cotización #<?= $cotizacion['id'] ?></h2>
            
            <div class="d-flex gap-2">
                <a href="listar_cotizaciones.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
                <a href="controllers/cotizacion/imprimir_cotizacion.php?id=<?= $cotizacion['id'] ?>" 
                   class="btn" style="background-color: #d4af37; color: #1a3a2f;"
                   target="_blank">
                    <i class="fas fa-print me-2"></i>Imprimir
                </a>
                <?php if ($cotizacion['estado'] === 'pendiente'): ?>
                    <a href="editar_cotizacion.php?id=<?= $cotizacion['id'] ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Editar
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Encabezado de cotización -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header" style="background-color: #1a3a2f; color: white;">
                        <strong>Información del Cliente</strong>
                    </div>
                    <div class="card-body">
                        <p><strong>Nombre:</strong> <?= htmlspecialchars($cotizacion['cliente']) ?></p>
                        <p><strong>Contacto:</strong> <?= htmlspecialchars($cotizacion['contacto']) ?></p>
                        <p><strong>Notas:</strong> <?= !empty($cotizacion['notas']) ? nl2br(htmlspecialchars($cotizacion['notas'])) : 'Ninguna' ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header" style="background-color: #1a3a2f; color: white;">
                        <strong>Información de la Cotización</strong>
                    </div>
                    <div class="card-body">
                        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($cotizacion['fecha_creacion'])) ?></p>
                        <p><strong>Válida hasta:</strong> 
                            <?= $fecha_vencimiento->format('d/m/Y') ?>
                            <?php if ($hoy > $fecha_vencimiento && $cotizacion['estado'] === 'pendiente'): ?>
                                <span class="badge bg-warning text-dark">Vencida</span>
                            <?php endif; ?>
                        </p>
                        <p><strong>Estado:</strong> 
                            <span class="badge <?= $cotizacion['estado'] === 'aprobada' ? 'bg-success' : 
                                               ($cotizacion['estado'] === 'rechazada' ? 'bg-danger' : 'bg-secondary') ?>">
                                <?= ucfirst($cotizacion['estado']) ?>
                            </span>
                        </p>
                        <p><strong>Creada por:</strong> <?= htmlspecialchars($cotizacion['usuario_nombre']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalles de la cotización -->
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
                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                        <td class="text-end"><strong>$<?= number_format($cotizacion['total'], 2, ',', '.') ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Acciones adicionales -->
        <?php if ($cotizacion['estado'] === 'pendiente'): ?>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#aprobarModal">
                    <i class="fas fa-check me-2"></i>Aprobar Cotización
                </button>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rechazarModal">
                    <i class="fas fa-times me-2"></i>Rechazar Cotización
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para aprobar cotización -->
<div class="modal fade" id="aprobarModal" tabindex="-1" aria-labelledby="aprobarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #1a3a2f; color: white;">
                <h5 class="modal-title" id="aprobarModalLabel">Aprobar Cotización</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controllers/cotizacion/actualizar_estado_cotizacion.php" method="POST">
                <input type="hidden" name="id" value="<?= $cotizacion['id'] ?>">
                <input type="hidden" name="estado" value="aprobada">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="comentarioAprobar" class="form-label">Comentario (opcional)</label>
                        <textarea class="form-control" id="comentarioAprobar" name="comentario" rows="3"></textarea>
                    </div>
                    <p class="text-muted">¿Está seguro que desea aprobar esta cotización?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar Aprobación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para rechazar cotización -->
<div class="modal fade" id="rechazarModal" tabindex="-1" aria-labelledby="rechazarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #1a3a2f; color: white;">
                <h5 class="modal-title" id="rechazarModalLabel">Rechazar Cotización</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controllers/cotizacion/actualizar_estado_cotizacion.php" method="POST">
                <input type="hidden" name="id" value="<?= $cotizacion['id'] ?>">
                <input type="hidden" name="estado" value="rechazada">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="comentarioRechazar" class="form-label">Motivo del rechazo</label>
                        <textarea class="form-control" id="comentarioRechazar" name="comentario" rows="3" required></textarea>
                    </div>
                    <p class="text-muted">¿Está seguro que desea rechazar esta cotización?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Rechazo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Estilos adicionales para mejorar la visualización */
    .card-header {
        font-weight: 600;
    }
    
    .table th {
        font-weight: 600;
    }
    
    .table td, .table th {
        vertical-align: middle;
    }
    
    .badge {
        font-size: 0.9em;
        padding: 0.5em 0.75em;
    }
    
    @media print {
        .no-print {
            display: none !important;
        }
        
        body {
            background-color: white !important;
            color: black !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
        }
    }
</style>

<?php include_once 'includes/footer.php'; ?>