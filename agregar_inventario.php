<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Agregar Inventario - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] != 'Administrador' && $_SESSION['rol_usuario'] != 'Inventario')) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres permisos de administrador o inventario'
    ];
    header("Location: index.php");
    exit();
}

// Variables iniciales
$producto = null;
$error = null;
$proveedores = $conexion->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Procesar búsqueda de producto
if (isset($_GET['buscar_producto'])) {
    $codigo = trim($_GET['codigo']);
    
    if (!empty($codigo)) {
        $sql = "SELECT p.*, c.nombre as categoria, 
                       FORMAT(p.precio_compra, 2, 'es_CO') as precio_compra_f,
                       FORMAT(p.precio_venta, 2, 'es_CO') as precio_venta_f,
                       FORMAT(p.precio_mayoreo, 2, 'es_CO') as precio_mayoreo_f
                FROM productos p
                JOIN categorias c ON p.categoria_id = c.id
                WHERE p.codigo_barras = ? OR p.id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $codigo, $codigo);
        $stmt->execute();
        $producto = $stmt->get_result()->fetch_assoc();
        
        if (!$producto) {
            $error = "No se encontró un producto con ese código";
        }
    }
}

// Procesar agregar inventario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['producto_id'])) {
    try {
        $producto_id = (int)$_POST['producto_id'];
        $cantidad = (int)$_POST['cantidad'];
        $precio_compra = (float)str_replace(['.', ','], ['', '.'], $_POST['precio_compra']);
        $precio_venta = (float)str_replace(['.', ','], ['', '.'], $_POST['precio_venta']);
        $precio_mayoreo = (float)str_replace(['.', ','], ['', '.'], $_POST['precio_mayoreo']);
        $proveedor_id = !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null;
        $notas = trim(filter_input(INPUT_POST, 'notas', FILTER_SANITIZE_SPECIAL_CHARS));

        // Validaciones
        if ($cantidad <= 0) throw new Exception("La cantidad debe ser mayor a 0");
        if ($precio_compra <= 0) throw new Exception("El precio de compra debe ser mayor a 0");
        if ($precio_venta <= $precio_compra) throw new Exception("El precio de venta debe ser mayor al de compra");

        // Iniciar transacción
        $conexion->begin_transaction();

        // 1. Actualizar producto (stock y precios)
        $sql_update = "UPDATE productos 
                      SET cantidad = cantidad + ?, 
                          precio_compra = ?, 
                          precio_venta = ?, 
                          precio_mayoreo = ?
                      WHERE id = ?";
        $stmt = $conexion->prepare($sql_update);
        $stmt->bind_param("idddi", $cantidad, $precio_compra, $precio_venta, $precio_mayoreo, $producto_id);
        $stmt->execute();

        // 2. Registrar movimiento en Kardex
        $sql_movimiento = "INSERT INTO movimientos 
                          (producto_id, tipo, cantidad, motivo, usuario_id, proveedor_id, notas) 
                          VALUES (?, 'entrada', ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql_movimiento);
        $motivo = "Reabastecimiento de inventario" . ($proveedor_id ? " (Proveedor)" : "");
        $stmt->bind_param("iisiis", $producto_id, $cantidad, $motivo, $_SESSION['id_usuario'], $proveedor_id, $notas);
        $stmt->execute();

        $conexion->commit();

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => "Se agregaron $cantidad unidades al inventario correctamente"
        ];
        header("Location: agregar_inventario.php");
        exit();

    } catch (Exception $e) {
        $conexion->rollback();
        $error = $e->getMessage();
    }
}
?>

<style>
    .card-agregar-inventario {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header-agregar-inventario {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
    }
    .info-producto {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .precio-input-group {
        position: relative;
    }
    .btn-calculadora {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 3;
    }
    #calculadora-modal .modal-body {
        padding: 1.5rem;
    }
    .btn-calculadora-num {
        width: 100%;
        height: 100%;
    }
</style>

<div class="container-fluid py-4">
    <?php include 'inventario_navbar.php'; ?>
    <div class="card card-agregar-inventario">
        <div class="card-header card-header-agregar-inventario">
            <h4 class="mb-0"><i class="fas fa-arrow-down me-2"></i>Agregar Inventario</h4>
        </div>
        
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-<?= $_SESSION['mensaje']['tipo'] ?> alert-dismissible fade show">
                    <?= $_SESSION['mensaje']['texto'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['mensaje']); ?>
            <?php endif; ?>

            <!-- Búsqueda de producto -->
            <form method="get" class="mb-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                    <input type="text" class="form-control" name="codigo" 
                           placeholder="Escanear código de barras o ingresar código manualmente" 
                           value="<?= isset($_GET['codigo']) ? htmlspecialchars($_GET['codigo']) : '' ?>" 
                           autofocus>
                    <button type="submit" name="buscar_producto" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Buscar
                    </button>
                </div>
            </form>

            <!-- Formulario para agregar inventario -->
            <?php if ($producto): ?>
                <div class="info-producto">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5><?= htmlspecialchars($producto['descripcion']) ?></h5>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Código: <?= htmlspecialchars($producto['codigo_barras']) ?></small>
                                <small class="text-muted">Categoría: <?= htmlspecialchars($producto['categoria']) ?></small>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <h5 class="<?= $producto['cantidad'] <= $producto['stock_minimo'] ? 'text-danger' : 'text-success' ?>">
                                Stock Actual: <?= $producto['cantidad'] ?>
                                <?php if ($producto['stock_minimo'] > 0): ?>
                                    <small class="text-muted">(Mín: <?= $producto['stock_minimo'] ?>)</small>
                                <?php endif; ?>
                            </h5>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="producto_id" value="<?= $producto['id'] ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Cantidad a Agregar <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="cantidad" min="1" required>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#calculadora-modal">
                                        <i class="fas fa-calculator"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Proveedor (Opcional)</label>
                                <select class="form-select" name="proveedor_id">
                                    <option value="">Seleccionar proveedor...</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Notas</label>
                                <input type="text" class="form-control" name="notas" placeholder="Ej: Factura #12345">
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Precio de Compra <span class="text-danger">*</span></label>
                                <div class="precio-input-group">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" name="precio_compra" 
                                               value="<?= $producto['precio_compra_f'] ?>" required>
                                    </div>
                                    <button type="button" class="btn btn-link btn-calculadora" 
                                            data-bs-toggle="modal" data-bs-target="#calculadora-modal"
                                            data-target-input="precio_compra">
                                        <i class="fas fa-calculator"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Precio de Venta <span class="text-danger">*</span></label>
                                <div class="precio-input-group">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" name="precio_venta" 
                                               value="<?= $producto['precio_venta_f'] ?>" required>
                                    </div>
                                    <button type="button" class="btn btn-link btn-calculadora" 
                                            data-bs-toggle="modal" data-bs-target="#calculadora-modal"
                                            data-target-input="precio_venta">
                                        <i class="fas fa-calculator"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Precio Mayoreo</label>
                                <div class="precio-input-group">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" name="precio_mayoreo" 
                                               value="<?= $producto['precio_mayoreo_f'] ?>">
                                    </div>
                                    <button type="button" class="btn btn-link btn-calculadora" 
                                            data-bs-toggle="modal" data-bs-target="#calculadora-modal"
                                            data-target-input="precio_mayoreo">
                                        <i class="fas fa-calculator"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-1"></i> Agregar al Inventario
                            </button>
                        </div>
                    </form>
                </div>
            <?php elseif (isset($_GET['buscar_producto']) && !$producto): ?>
                <div class="alert alert-warning text-center py-4">
                    <i class="fas fa-box-open fa-3x mb-3"></i>
                    <h4>Producto no encontrado</h4>
                    <p class="mb-0">Verifica el código e intenta nuevamente</p>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center py-4">
                    <i class="fas fa-barcode fa-3x mb-3"></i>
                    <h4>Busca un producto para comenzar</h4>
                    <p class="mb-0">Escanea el código de barras o ingresa el código manualmente</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Calculadora -->
<div class="modal fade" id="calculadora-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Calculadora</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="target-input">
                <div class="mb-3">
                    <input type="text" class="form-control text-end" id="calculadora-display" readonly>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="7">7</button></div>
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="8">8</button></div>
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="9">9</button></div>
                    <div class="col-3"><button class="btn btn-outline-danger btn-calculadora-num" data-val="clear">C</button></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="4">4</button></div>
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="5">5</button></div>
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="6">6</button></div>
                    <div class="col-3"><button class="btn btn-outline-primary btn-calculadora-num" data-val="*">×</button></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="1">1</button></div>
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="2">2</button></div>
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="3">3</button></div>
                    <div class="col-3"><button class="btn btn-outline-primary btn-calculadora-num" data-val="/">÷</button></div>
                </div>
                <div class="row g-2">
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val="0">0</button></div>
                    <div class="col-3"><button class="btn btn-outline-secondary btn-calculadora-num" data-val=".">.</button></div>
                    <div class="col-3"><button class="btn btn-outline-primary btn-calculadora-num" data-val="+">+</button></div>
                    <div class="col-3"><button class="btn btn-outline-primary btn-calculadora-num" data-val="-">-</button></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-calculadora-ok">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Configurar calculadora
    let calculadoraValor = '';
    const calculadoraDisplay = document.getElementById('calculadora-display');
    const targetInput = document.getElementById('target-input');
    
    // Botones de la calculadora
    document.querySelectorAll('.btn-calculadora-num').forEach(btn => {
        btn.addEventListener('click', function() {
            const val = this.getAttribute('data-val');
            
            if (val === 'clear') {
                calculadoraValor = '';
            } else {
                calculadoraValor += val;
            }
            
            calculadoraDisplay.value = calculadoraValor;
        });
    });
    
    // Botones para abrir calculadora en cada campo
    document.querySelectorAll('.btn-calculadora').forEach(btn => {
        btn.addEventListener('click', function() {
            const inputName = this.getAttribute('data-target-input');
            targetInput.value = inputName;
            
            // Cargar valor actual del input
            const input = document.querySelector(`[name="${inputName}"]`);
            calculadoraValor = input.value.replace('$', '');
            calculadoraDisplay.value = calculadoraValor;
        });
    });
    
    // Botón Aceptar de la calculadora
    document.getElementById('btn-calculadora-ok').addEventListener('click', function() {
        if (targetInput.value) {
            const input = document.querySelector(`[name="${targetInput.value}"]`);
            input.value = calculadoraValor;
            
            // Si es un campo de precio, evaluar la expresión matemática
            if (targetInput.value.includes('precio')) {
                try {
                    const resultado = eval(calculadoraValor);
                    if (!isNaN(resultado)) {
                        input.value = resultado.toFixed(2);
                    }
                } catch (e) {
                    console.error("Error en cálculo:", e);
                }
            }
        }
        
        // Cerrar modal
        bootstrap.Modal.getInstance(document.getElementById('calculadora-modal')).hide();
    });
    
    // Auto-calcular precio de venta si es menor que compra
    document.querySelector('[name="precio_compra"]').addEventListener('change', function() {
        const precioCompra = parseFloat(this.value.replace(/[^\d.]/g, '')) || 0;
        const precioVentaInput = document.querySelector('[name="precio_venta"]');
        const precioVenta = parseFloat(precioVentaInput.value.replace(/[^\d.]/g, '')) || 0;
        
        if (precioCompra > 0 && (precioVenta <= precioCompra || precioVenta === 0)) {
            const nuevoPrecioVenta = precioCompra * 1.3; // 30% de margen por defecto
            precioVentaInput.value = nuevoPrecioVenta.toFixed(2);
        }
    });
    
    // Formatear precios al salir del campo
    document.querySelectorAll('[name^="precio_"]').forEach(input => {
        input.addEventListener('blur', function() {
            const valor = parseFloat(this.value.replace(/[^\d.]/g, '')) || 0;
            this.value = valor.toFixed(2);
        });
    });
});
</script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>