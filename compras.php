<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Historial de Compras - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] != 'Administrador' && $_SESSION['rol_usuario'] != 'Compras')) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres permisos de compras'
    ];
    header("Location: index.php");
    exit();
}

// Obtener datos del historial
$compras = $conexion->query("
    SELECT c.*, p.nombre as proveedor, u.nombre as usuario 
    FROM compras c
    JOIN proveedores p ON c.proveedor_id = p.id
    JOIN usuarios u ON c.usuario_id = u.id
    ORDER BY c.fecha DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-4">
    <?php include 'compras_proveedores_navbar.php'; ?>
    <!-- Notificaciones -->
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['mensaje']['tipo']) ?> alert-dismissible fade show mt-3" role="alert">
            <i class="fas <?= $_SESSION['mensaje']['tipo'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($_SESSION['mensaje']['texto']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['mensaje']); ?>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Historial de Compras</h5>
                <a href="nueva_compra.php" class="btn btn-success btn-sm">
                    <i class="fas fa-plus me-1"></i> Nueva Compra
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Usuario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $compra): ?>
                            <tr>
                                <td><?= htmlspecialchars($compra['num_factura']) ?></td>
                                <td><?= htmlspecialchars($compra['proveedor']) ?></td>
                                <td><?= date('d/m/Y', strtotime($compra['fecha'])) ?></td>
                                <td>$<?= number_format($compra['total'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($compra['usuario']) ?></td>
                                <td>
                                    <a href="compras_detalle.php?id=<?= $compra['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>