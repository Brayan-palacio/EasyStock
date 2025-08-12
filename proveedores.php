<?php
session_start();
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Gestión de Proveedores - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol_usuario'] != 'Administrador') {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres permisos de administrador'
    ];
    header("Location: index.php");
    exit();
}

// Eliminar proveedor
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    try {
        // Verificar si tiene compras asociadas
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM compras WHERE proveedor_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $compras = $stmt->get_result()->fetch_row()[0];
        
        if ($compras > 0) {
            throw new Exception("No se puede eliminar: tiene $compras compras asociadas");
        }

        $stmt = $conexion->prepare("DELETE FROM proveedores WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Proveedor eliminado correctamente'
            ];
        } else {
            throw new Exception("Error al eliminar el proveedor: " . $stmt->error);
        }
        
        header("Location: proveedores.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => $e->getMessage()
        ];
        header("Location: proveedores.php");
        exit();
    }
}

// Obtener proveedores con información adicional
$proveedores = $conexion->query("
    SELECT p.*, 
           COUNT(c.id) as total_compras,
           SUM(c.total) as monto_total_compras
    FROM proveedores p
    LEFT JOIN compras c ON c.proveedor_id = p.id
    GROUP BY p.id
    ORDER BY p.nombre
")->fetch_all(MYSQLI_ASSOC);
?>

<style>
    .card-proveedores {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header-proveedores {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
    }
    .badge-compras {
        background-color: #6f42c1;
    }
    .badge-monto {
        background-color: #20c997;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(42, 157, 143, 0.1);
    }
    .acciones-cell {
        white-space: nowrap;
    }
    .required-field::after {
        content: " *";
        color: red;
    }
</style>

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

    <!-- Listado de Proveedores -->
    <div class="card card-proveedores">
        <div class="card-header card-header-proveedores">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-truck me-2"></i>Gestión de Proveedores</h4>
                <div>
                    <a href="nuevo_proveedor.php" class="btn btn-light">
                        <i class="fas fa-plus me-1"></i> Nuevo Proveedor
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($proveedores)): ?>
                <div class="alert alert-info">No hay proveedores registrados</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Identificación</th>
                                <th>Contacto</th>
                                <th>Compras</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proveedores as $prov): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($prov['nombre']) ?></strong>
                                        <?php if ($prov['limite_credito']): ?>
                                            <br>
                                            <small class="text-muted">Límite: $<?= number_format($prov['limite_credito'], 2) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($prov['tipo_identificacion']) ?>: 
                                        <?= htmlspecialchars($prov['nit']) ?>
                                    </td>
                                    <td>
                                        <?php if ($prov['telefono']): ?>
                                            <i class="fas fa-phone me-1"></i> <?= htmlspecialchars($prov['telefono']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($prov['email']): ?>
                                            <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($prov['email']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-compras me-1">
                                            <i class="fas fa-shopping-cart me-1"></i>
                                            <?= (int)$prov['total_compras'] ?>
                                        </span>
                                        <span class="badge badge-monto">
                                            <i class="fas fa-dollar-sign me-1"></i>
                                            <?= number_format($prov['monto_total_compras'] ?? 0, 2) ?>
                                        </span>
                                    </td>
                                    <td class="text-end acciones-cell">
                                        <a href="editar_proveedor.php?id=<?= $prov['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <a href="proveedores.php?eliminar=<?= $prov['id'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('¿Estás seguro de eliminar este proveedor?\n\nNombre: <?= addslashes($prov['nombre']) ?>\nNIT: <?= addslashes($prov['nit']) ?>')">
                                            <i class="fas fa-trash-alt"></i> Eliminar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>