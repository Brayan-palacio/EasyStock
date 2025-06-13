<?php
// Verificación de sesión y permisos
require_once 'config/conexion.php';
require_once 'config/funciones.php';


// Obtener productos y proveedores
$productos = $conexion->query("SELECT id, descripcion, codigo_barras FROM productos ORDER BY descripcion");
$proveedores = $conexion->query("SELECT id, nombre FROM proveedores ORDER BY nombre");

$tituloPagina = 'Registrar Nueva Compra - EasyStock';
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="card shadow-lg mt-4">
        <div class="card-header bg-success text-white">
            <h2 class="h4 mb-0"><i class="fas fa-cart-plus me-2"></i> Nueva Compra</h2>
        </div>
        
        <div class="card-body">
            <form id="formCompra" action="procesar_compra.php" method="post">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Proveedor *</label>
                        <select class="form-select" name="proveedor_id" required>
                            <option value="">Seleccionar proveedor</option>
                            <?php while($prov = $proveedores->fetch_assoc()): ?>
                            <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">N° Documento</label>
                        <input type="text" class="form-control" name="numero_documento" placeholder="Factura, Remisión, etc.">
                    </div>
                </div>
                
                <h5 class="border-bottom pb-2 mb-3">Productos Comprados</h5>
                
                <div id="productos-container">
                    <!-- Producto inicial -->
                    <div class="row producto-fila mb-3">
                        <div class="col-md-5">
                            <select class="form-select producto-select" name="productos[0][id]" required>
                                <option value="">Seleccionar producto</option>
                                <?php while($prod = $productos->fetch_assoc()): ?>
                                <option value="<?= $prod['id'] ?>" 
                                        data-precio="<?= $prod['precio_compra'] ?? 0 ?>">
                                    <?= htmlspecialchars($prod['descripcion']) ?> (<?= $prod['codigo_barras'] ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control cantidad" name="productos[0][cantidad]" min="1" value="1" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control precio" name="productos[0][precio]" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger btn-eliminar-fila" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-3">
                    <button type="button" id="btn-agregar-producto" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Agregar Producto
                    </button>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="2"></textarea>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="submit" class="btn btn-success btn-lg mt-3">
                            <i class="fas fa-save me-2"></i> Registrar Compra
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript para manejar filas dinámicas
document.addEventListener('DOMContentLoaded', function() {
    // Agregar nueva fila de producto
    document.getElementById('btn-agregar-producto').addEventListener('click', function() {
        const container = document.getElementById('productos-container');
        const index = container.children.length;
        
        const nuevaFila = document.createElement('div');
        nuevaFila.className = 'row producto-fila mb-3';
        nuevaFila.innerHTML = `
            <div class="col-md-5">
                <select class="form-select producto-select" name="productos[${index}][id]" required>
                    <option value="">Seleccionar producto</option>
                    <?php 
                    $productos->data_seek(0); // Resetear puntero
                    while($prod = $productos->fetch_assoc()): ?>
                    <option value="<?= $prod['id'] ?>" 
                            data-precio="<?= $prod['precio_compra'] ?? 0 ?>">
                        <?= htmlspecialchars($prod['descripcion']) ?> (<?= $prod['codigo_barras'] ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control cantidad" name="productos[${index}][cantidad]" min="1" value="1" required>
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control precio" name="productos[${index}][precio]" step="0.01" min="0" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-eliminar-fila">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        
        container.appendChild(nuevaFila);
        
        // Habilitar botones de eliminar en todas las filas
        document.querySelectorAll('.btn-eliminar-fila').forEach(btn => {
            btn.disabled = false;
        });
    });
    
    // Eliminar fila de producto
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-eliminar-fila')) {
            const fila = e.target.closest('.producto-fila');
            if (document.querySelectorAll('.producto-fila').length > 1) {
                fila.remove();
                
                // Reindexar los nombres de los campos
                document.querySelectorAll('.producto-fila').forEach((fila, index) => {
                    fila.querySelectorAll('[name^="productos["]').forEach(input => {
                        input.name = input.name.replace(/productos\[\d+\]/, `productos[${index}]`);
                    });
                });
            }
        }
    });
    
    // Autocompletar precio al seleccionar producto
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('producto-select')) {
            const precio = e.target.selectedOptions[0].dataset.precio;
            const precioInput = e.target.closest('.producto-fila').querySelector('.precio');
            if (precio && precio > 0) {
                precioInput.value = precio;
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>