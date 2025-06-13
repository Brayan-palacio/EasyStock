<?php
$tituloPagina = 'Kardex de Productos - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos (solo administradores)
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol_usuario'] != 'Administrador' && $_SESSION['rol_usuario'] != 'Supervisor') {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres nivel de administrador para acceder'
    ];
    header("Location: index.php");
    exit();
}

// Obtener el ID del producto si se pasa por URL
$producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Función para obtener el kardex de un producto específico
function obtenerKardex($conexion, $producto_id) {
    $sql = "SELECT k.*, p.descripcion, p.codigo_barras, 
                   IFNULL(u.nombre, 'Sistema') as usuario,
                   DATE_FORMAT(k.fecha_movimiento, '%d/%m/%Y %H:%i') as fecha_formateada
            FROM kardex k
            JOIN productos p ON k.producto_id = p.id
            LEFT JOIN usuarios u ON k.usuario_id = u.id
            WHERE k.producto_id = ?
            ORDER BY k.fecha_movimiento DESC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener información del producto
function obtenerProducto($conexion, $producto_id) {
    $sql = "SELECT p.*, c.nombre as categoria 
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
$movimientos = $producto_id ? obtenerKardex($conexion, $producto_id) : [];
$producto = $producto_id ? obtenerProducto($conexion, $producto_id) : null;
?>

<div class="container-fluid px-4">
    <div class="card shadow-lg border-0 rounded-3 overflow-hidden mb-4 mt-3">
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0 fw-semibold">
                    <i class="fas fa-warehouse me-2"></i> Kardex de Productos
                </h2>
                <div>
                    <a href="productos.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i> Volver a Productos
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">
            <?php if($producto_id && $producto): ?>
                <!-- Información del Producto -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <?php if(!empty($producto['imagen'])): ?>
                                <img src='assets/img/productos/<?= htmlspecialchars($producto['imagen']) ?>'
                                     class="rounded-circle me-3" width="60" height="60" 
                                     alt="<?= htmlspecialchars($producto['descripcion']) ?>">
                            <?php else: ?>
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" 
                                     style="width:60px;height:60px;">
                                    <i class="fas fa-box text-muted fa-2x"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="mb-1"><?= htmlspecialchars($producto['descripcion']) ?></h4>
                                <div class="d-flex flex-wrap gap-3">
                                    <span class="text-muted">
                                        <i class="fas fa-barcode me-1"></i> <?= htmlspecialchars($producto['codigo_barras']) ?>
                                    </span>
                                    <span class="text-muted">
                                        <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($producto['categoria']) ?>
                                    </span>
                                    <span class="badge bg-<?= $producto['cantidad'] > 5 ? 'success' : 'danger' ?>">
                                        Stock: <?= htmlspecialchars($producto['cantidad']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="d-flex flex-column">
                            <span class="text-muted">Precio Compra: 
                                <strong><?= '$' . number_format($producto['precio_compra'], 2) ?></strong>
                            </span>
                            <span class="text-muted">Precio Venta: 
                                <strong class="text-success"><?= '$' . number_format($producto['precio_venta'], 2) ?></strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Movimientos -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="120">Fecha</th>
                                <th width="100">Tipo</th>
                                <th>Descripción</th>
                                <th width="100">Entrada</th>
                                <th width="100">Salida</th>
                                <th width="100">Saldo</th>
                                <th width="120">Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($movimientos) > 0): ?>
                                <?php 
                                $saldo = 0;
                                foreach($movimientos as $mov): 
                                    $saldo += $mov['entrada'] - $mov['salida'];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mov['fecha_formateada']) ?></td>
                                        <td>
                                            <?php 
                                            $badgeClass = [
                                                'ENTRADA' => 'bg-success',
                                                'SALIDA' => 'bg-danger',
                                                'AJUSTE' => 'bg-warning',
                                                'TRASPASO' => 'bg-info'
                                            ][$mov['tipo_movimiento']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars($mov['tipo_movimiento']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($mov['descripcion']) ?></td>
                                        <td class="text-success fw-bold"><?= $mov['entrada'] > 0 ? $mov['entrada'] : '-' ?></td>
                                        <td class="text-danger fw-bold"><?= $mov['salida'] > 0 ? $mov['salida'] : '-' ?></td>
                                        <td class="fw-bold"><?= $saldo ?></td>
                                        <td><?= htmlspecialchars($mov['usuario']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                            <h5>No hay movimientos registrados</h5>
                                            <p class="text-muted">Este producto no tiene movimientos en el kardex</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Vista cuando no se ha seleccionado un producto -->
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                    <h3>Seleccione un producto para ver su kardex</h3>
                    <p class="text-muted mb-4">Debe elegir un producto desde la página de gestión de productos</p>
                    <a href="productos.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i> Volver a Productos
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>