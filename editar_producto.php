<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Editar Producto - EasyStock';
include 'config/conexion.php';
include_once 'controllers/productos/productosController.php';
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

// Obtener ID del producto a editar
$producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($producto_id <= 0) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Producto no especificado'
    ];
    header("Location: productos.php");
    exit();
}

// Obtener datos actuales del producto
$producto = obtenerProductoPorId($conexion, $producto_id);
if (!$producto) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Producto no encontrado'
    ];
    header("Location: productos.php");
    exit();
}

// Obtener categorías
$categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitizar y validar datos
        $datos = [
            'descripcion' => trim($conexion->real_escape_string($_POST['descripcion'])),
            'precio_compra' => (float)str_replace(['.', ','], ['', '.'], $_POST['precio_compra']),
            'precio_venta' => (float)str_replace(['.', ','], ['', '.'], $_POST['precio_venta']),
            'cantidad' => (int)$_POST['cantidad'],
            'categoria_id' => (int)$_POST['categoria_id'],
            'codigo_barras' => preg_replace('/[^0-9]/', '', $_POST['codigo_barras']),
            'imagen_actual' => $producto['imagen']
        ];

        // Validaciones
        $errores = [];
        
        if (empty($datos['descripcion'])) {
            $errores[] = "La descripción es obligatoria";
        }
        
        if ($datos['precio_compra'] <= 0) {
            $errores[] = "Precio de compra debe ser mayor a 0";
        }
        
        if ($datos['precio_venta'] <= $datos['precio_compra']) {
            $errores[] = "El precio de venta debe ser mayor al de compra";
        }
        
        if ($datos['cantidad'] < 0) {
            $errores[] = "La cantidad no puede ser negativa";
        }

        // Procesar nueva imagen si se subió
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $directorio = 'assets/img/productos/';
            
            // Validar tipo de imagen
            $infoImagen = getimagesize($_FILES['imagen']['tmp_name']);
            if (!$infoImagen) {
                throw new Exception("El archivo no es una imagen válida");
            }

            $extensionesPermitidas = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 
                                    'png' => 'image/png', 'webp' => 'image/webp'];
            $mime = $infoImagen['mime'];
            
            if (!in_array($mime, $extensionesPermitidas)) {
                throw new Exception("Solo se permiten imágenes JPG, PNG o WEBP");
            }

            $extension = array_search($mime, $extensionesPermitidas);
            $nombreArchivo = uniqid('prod_', true) . '.' . $extension;
            $rutaCompleta = $directorio . $nombreArchivo;

            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaCompleta)) {
                // Eliminar imagen anterior si no es la default
                if ($datos['imagen_actual'] !== 'default.png' && file_exists($directorio . $datos['imagen_actual'])) {
                    unlink($directorio . $datos['imagen_actual']);
                }
                $datos['imagen'] = $nombreArchivo;
            } else {
                throw new Exception("Error al guardar la nueva imagen");
            }
        } else {
            $datos['imagen'] = $datos['imagen_actual'];
        }

        if (!empty($errores)) {
            throw new Exception(implode("<br>", $errores));
        }

        // Actualizar producto
        $actualizado = actualizarProducto(
            $conexion,
            $producto_id,
            $datos['descripcion'],
            $datos['precio_compra'],
            $datos['precio_venta'],
            $datos['cantidad'],
            $datos['categoria_id'],
            $datos['codigo_barras'],
            $datos['imagen']
        );

        if ($actualizado) {
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Producto actualizado exitosamente'
            ];
            header("Location: productos.php");
            exit();
        } else {
            throw new Exception("Error al actualizar el producto");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<style>
    .card-form {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
        padding: 1.5rem;
    }
    .form-icon {
        position: absolute;
        top: 50%;
        left: 15px;
        transform: translateY(-50%);
        color: #6c757d;
    }
    .form-control-with-icon {
        padding-left: 40px;
    }
    .preview-imagen {
        max-width: 200px;
        max-height: 200px;
        display: block;
        margin: 10px auto;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .divider {
        height: 3px;
        background: linear-gradient(90deg, #1a3a2f, #d4af37);
        margin: 1.5rem 0;
        border-radius: 3px;
        opacity: 0.7;
    }
    .btn-success {
        background-color: #1a3a2f;
        border-color: #1a3a2f;
    }
    .btn-success:hover {
        background-color: #2c5e4f;
        border-color: #2c5e4f;
    }
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card card-form">
                <div class="card-header text-center">
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Producto</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= nl2br(htmlspecialchars($error)) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form id="form-editar-producto" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="codigo_barras" class="form-label">Código de Barras</label>
                                <div class="form-group position-relative">
                                    <i class="fas fa-barcode form-icon"></i>
                                    <input type="text" class="form-control form-control-with-icon" 
                                           id="codigo_barras" name="codigo_barras" 
                                           value="<?= htmlspecialchars($producto['codigo_barras']) ?>"
                                           placeholder="Opcional" pattern="[0-9]*" title="Solo números">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="categoria_id" class="form-label">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select" id="categoria_id" name="categoria_id" required>
                                    <option value="">Seleccione una categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?= $categoria['id'] ?>" 
                                            <?= $producto['categoria_id'] == $categoria['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($categoria['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Por favor seleccione una categoría</div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-12 mb-3">
                                <label for="descripcion" class="form-label">Descripción <span class="text-danger">*</span></label>
                                <div class="form-group position-relative">
                                    <i class="fas fa-box form-icon"></i>
                                    <input type="text" class="form-control form-control-with-icon" 
                                           id="descripcion" name="descripcion" 
                                           placeholder="Nombre del producto" 
                                           value="<?= htmlspecialchars($producto['descripcion']) ?>"
                                           required minlength="3" maxlength="255">
                                    <div class="invalid-feedback">Debe ingresar una descripción válida (mín. 3 caracteres)</div>
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <label for="cantidad" class="form-label">Stock Actual <span class="text-danger">*</span></label>
                                <div class="form-group position-relative">
                                    <i class="fas fa-boxes form-icon"></i>
                                    <input type="number" class="form-control form-control-with-icon" 
                                           id="cantidad" name="cantidad" 
                                           value="<?= htmlspecialchars($producto['cantidad']) ?>" 
                                           min="0" required>
                                    <div class="invalid-feedback">Ingrese una cantidad válida</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="precio_compra" class="form-label">Precio de Compra <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control precio-input" 
                                           id="precio_compra" name="precio_compra" 
                                           value="<?= number_format($producto['precio_compra'], 2, ',', '.') ?>" 
                                           required>
                                    <div class="invalid-feedback">Ingrese un precio válido</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="precio_venta" class="form-label">Precio de Venta <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control precio-input" 
                                           id="precio_venta" name="precio_venta" 
                                           value="<?= number_format($producto['precio_venta'], 2, ',', '.') ?>" 
                                           required>
                                    <div class="invalid-feedback">Ingrese un precio válido</div>
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="row mb-4">
                            <div class="col-md-12 mb-3">
                                <label for="imagen" class="form-label">Imagen del Producto</label>
                                <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                                <div class="form-text">Dejar en blanco para mantener la imagen actual</div>
                                <div id="preview-container" class="mt-2 text-center">
                                    <?php if (!empty($producto['imagen'])): ?>
                                        <img src='assets/img/productos/<?= htmlspecialchars($producto['imagen']) ?>'
                                             class="preview-imagen" 
                                             alt="Imagen actual del producto">
                                        <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($producto['imagen']) ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="productos.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-save me-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Vista previa de la imagen
    const imagenInput = document.getElementById('imagen');
    const previewContainer = document.getElementById('preview-container');
    
    imagenInput.addEventListener('change', function(e) {
        previewContainer.innerHTML = '';
        
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-imagen';
                img.alt = 'Nueva imagen del producto';
                previewContainer.appendChild(img);
            }
            
            reader.readAsDataURL(this.files[0]);
        } else {
            // Mostrar imagen actual si no se selecciona nueva
            <?php if (!empty($producto['imagen'])): ?>
                const img = document.createElement('img');
                img.src = 'assets/img/productos/<?= $producto['imagen'] ?>';
                img.className = 'preview-imagen';
                img.alt = 'Imagen actual del producto';
                previewContainer.appendChild(img);
            <?php endif; ?>
        }
    });

    // Formateo de precios
    document.querySelectorAll('.precio-input').forEach(input => {
        input.addEventListener('input', function() {
            const cursorPosition = this.selectionStart;
            const originalLength = this.value.length;
            
            let value = this.value.replace(/[^\d,]/g, '');
            value = value.replace(/,/g, '');
            
            if (value.length > 0) {
                value = parseInt(value, 10).toLocaleString('es-CO');
            }
            
            this.value = value;
            
            const newLength = this.value.length;
            const newPosition = cursorPosition - (originalLength - newLength);
            this.setSelectionRange(newPosition, newPosition);
        });
    });

    // Validación del formulario
    const form = document.getElementById('form-editar-producto');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!form.checkValidity()) {
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        
        const precioCompra = parseFloat(
            document.getElementById('precio_compra').value.replace(/\./g, '')
        );
        const precioVenta = parseFloat(
            document.getElementById('precio_venta').value.replace(/\./g, '')
        );
        
        if (precioVenta <= precioCompra) {
            Swal.fire({
                title: 'Error en precios',
                html: '<div class="text-start">' +
                      '<p>El precio de venta debe ser mayor al precio de compra.</p>' +
                      '<div class="alert alert-warning p-2 mt-2">' +
                      '<i class="fas fa-exclamation-triangle me-2"></i>' +
                      'Margen mínimo recomendado: 10%' +
                      '</div></div>',
                icon: 'error',
                confirmButtonText: 'Entendido',
                background: '#f8f9fa'
            });
            return;
        }
        
        form.submit();
    });

    // Auto-calcular precio de venta con margen
    const margenRecomendado = 1.1; // 10%
    document.getElementById('precio_compra').addEventListener('blur', function() {
        const precioCompra = parseFloat(this.value.replace(/\./g, ''));
        const precioVentaInput = document.getElementById('precio_venta');
        
        if (!isNaN(precioCompra) && precioCompra > 0) {
            const precioVenta = precioCompra * margenRecomendado;
            precioVentaInput.value = Math.round(precioVenta).toLocaleString('es-CO');
            
            const event = new Event('input');
            precioVentaInput.dispatchEvent(event);
        }
    });
});
</script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>