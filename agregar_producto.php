<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Agregar Producto - EasyStock';
include 'config/conexion.php';
include_once 'controllers/productos/productosController.php';
include_once 'includes/header.php';

// Verificar sesión y permisos mejorado
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres nivel 30+ para esta acción'
    ];
    header("Location: login.php");
    exit();
}

// Obtener categorías con manejo de errores mejorado
try {
    $sql_categorias = "SELECT id, nombre FROM categorias ORDER BY nombre";
    $resultado_categorias = $conexion->query($sql_categorias);
    
    if (!$resultado_categorias) {
        throw new Exception("Error al obtener categorías: " . $conexion->error);
    }
    
    $categorias = $resultado_categorias->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => $e->getMessage()
    ];
    header("Location: productos.php");
    exit();
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitización mejorada
        $datos = [
            'descripcion' => trim(filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_SPECIAL_CHARS)),
            'precio_compra' => (float)str_replace(['.', ','], ['', '.'], $_POST['precio_compra']),
            'precio_venta' => (float)str_replace(['.', ','], ['', '.'], $_POST['precio_venta']),
            'cantidad' => (int)$_POST['cantidad'],
            'categoria_id' => !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null,
            'codigo_barras' => preg_replace('/[^0-9]/', '', $_POST['codigo_barras']),
            'imagen' => null
        ];

        // Validaciones mejoradas
        $errores = [];
        
        if (empty($datos['descripcion'])) {
            $errores[] = "La descripción es obligatoria";
        } elseif (strlen($datos['descripcion']) < 3) {
            $errores[] = "La descripción debe tener al menos 3 caracteres";
        }
        
        if ($datos['precio_compra'] <= 0) {
            $errores[] = "El precio de compra debe ser mayor a 0";
        }
        
        if ($datos['precio_venta'] <= $datos['precio_compra']) {
            $errores[] = "El precio de venta debe ser mayor al de compra";
        }
        
        if ($datos['cantidad'] < 0) {
            $errores[] = "La cantidad no puede ser negativa";
        }
        
        if (empty($datos['categoria_id'])) {
            $errores[] = "Debe seleccionar una categoría";
        }

        // Procesar imagen con mejor manejo
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $directorio = 'assets/img/productos/';
            if (!is_dir($directorio)) {
                if (!mkdir($directorio, 0755, true)) {
                    throw new Exception("No se pudo crear el directorio para imágenes");
                }
            }

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

            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaCompleta)) {
                throw new Exception("Error al guardar la imagen");
            }
            
            $datos['imagen'] = $nombreArchivo;
        }

        if (!empty($errores)) {
            throw new Exception(implode("<br>", $errores));
        }

        // Insertar producto usando tu función existente
        $respuesta = agregarProducto(
            $conexion,
            $datos['descripcion'],
            $datos['precio_compra'],
            $datos['precio_venta'],
            $datos['cantidad'],
            $datos['categoria_id'],
            $datos['codigo_barras'],
            $datos['imagen']
        );

        if (!$respuesta) {
            // Eliminar imagen si falla la inserción
            if ($datos['imagen'] !== null && file_exists($directorio . $datos['imagen'])) {
                unlink($directorio . $datos['imagen']);
            }
            throw new Exception("Error al agregar el producto en la base de datos");
        }

        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => 'Producto agregado exitosamente'
        ];
        header("Location: productos.php");
        exit();

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
        overflow: hidden;
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
        z-index: 5;
    }
    .form-control-with-icon {
        padding-left: 40px;
        position: relative;
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
    .input-group-text {
        background-color: #f8f9fa;
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
                    <h4 class="mb-0"><i class="fas fa-box-open me-2"></i>Agregar Nuevo Producto</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= nl2br(htmlspecialchars($error)) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form id="form-agregar-producto" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row mb-4">
                            <!-- En la sección del formulario (cambiar el input de código de barras) -->
<div class="col-md-6 mb-3">
    <label for="codigo_barras" class="form-label">Código de Barras 
        <span class="text-muted small">(Escanea con el lector)</span>
    </label>
    <div class="input-group">
        <input type="text" 
               class="form-control" 
               id="codigo_barras" 
               name="codigo_barras" 
               placeholder="Pase el código de barras por el lector"
               autocomplete="off"
               autocorrect="off"
               autocapitalize="off"
               spellcheck="false"
               value="<?= isset($_POST['codigo_barras']) ? htmlspecialchars($_POST['codigo_barras']) : '' ?>">
        <button class="btn btn-outline-secondary" type="button" id="btn-generar-codigo">
            <i class="fas fa-barcode"></i> Generar
        </button>
    </div>
    <div class="form-text small">El código se capturará automáticamente al escanear</div>
</div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="categoria_id" class="form-label">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select" id="categoria_id" name="categoria_id" required>
                                    <option value="" disabled selected>Seleccione una categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?= $categoria['id'] ?>" 
                                            <?= (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $categoria['id']) ? 'selected' : '' ?>>
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
                                           value="<?= isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : '' ?>"
                                           required minlength="3" maxlength="255">
                                    <div class="invalid-feedback">Debe ingresar una descripción válida (mín. 3 caracteres)</div>
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <label for="cantidad" class="form-label">Stock Inicial <span class="text-danger">*</span></label>
                                <div class="form-group position-relative">
                                    <i class="fas fa-boxes form-icon"></i>
                                    <input type="number" class="form-control form-control-with-icon" 
                                           id="cantidad" name="cantidad" 
                                           value="<?= isset($_POST['cantidad']) ? htmlspecialchars($_POST['cantidad']) : '0' ?>" 
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
                                           value="<?= isset($_POST['precio_compra']) ? htmlspecialchars($_POST['precio_compra']) : '' ?>" 
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
                                           value="<?= isset($_POST['precio_venta']) ? htmlspecialchars($_POST['precio_venta']) : '' ?>" 
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
                                <div class="form-text">Formatos permitidos: JPG, PNG, WEBP (Max 2MB)</div>
                                <div id="preview-container" class="mt-2 text-center"></div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="productos.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-save me-1"></i> Guardar Producto
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Vista previa de la imagen (se mantiene igual)
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
                img.alt = 'Vista previa de la imagen';
                previewContainer.appendChild(img);
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });

    // 2. Configuración MEJORADA para lectores de código de barras (detección automática)
    const codigoBarrasInput = document.getElementById('codigo_barras');
    let codigoTemporal = '';
    let timeoutEscaneo = null;
    
    // Solo permitir números
    codigoBarrasInput.addEventListener('input', function(e) {
        // Filtrar solo números
        this.value = this.value.replace(/\D/g, '');
        
        // Detectar escaneo automático (sin Enter)
        clearTimeout(timeoutEscaneo);
        codigoTemporal = this.value;
        
        timeoutEscaneo = setTimeout(() => {
            if (codigoTemporal.length >= 8) { // Longitud mínima para código válido
                procesarCodigoBarras(codigoTemporal);
            }
        }, 100); // Pequeño delay para captura rápida
    });
    
    // Configurar también el evento keydown por compatibilidad
    codigoBarrasInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            procesarCodigoBarras(this.value.trim());
        }
    });
    
    // Auto-seleccionar el contenido al hacer clic
    codigoBarrasInput.addEventListener('click', function() {
        this.select();
    });

    function procesarCodigoBarras(codigo) {
        // Validar código (8-13 dígitos para EAN/UPC)
        const esCodigoValido = /^[0-9]{8,13}$/.test(codigo);
        
        if (esCodigoValido) {
            // Feedback visual
            codigoBarrasInput.classList.add('is-valid');
            codigoBarrasInput.classList.remove('is-invalid');
            
            // Auto-enfocar siguiente campo con pequeño retraso
            setTimeout(() => {
                document.getElementById('descripcion').focus();
            }, 50);
            
            // Mostrar confirmación sutil (opcional)
            mostrarTooltip('✔ Código válido', 'success');
        } else {
            // Feedback de error
            codigoBarrasInput.classList.add('is-invalid');
            codigoBarrasInput.classList.remove('is-valid');
            
            // Mostrar error y seleccionar para reescaneo
            mostrarTooltip('✘ Código inválido (8-13 dígitos)', 'danger');
            codigoBarrasInput.select();
        }
    }

    // Función para mostrar tooltip de feedback
    function mostrarTooltip(mensaje, tipo) {
        const tooltip = new bootstrap.Tooltip(codigoBarrasInput, {
            title: mensaje,
            trigger: 'manual',
            placement: 'top',
            customClass: `tooltip-${tipo}`
        });
        
        tooltip.show();
        
        // Ocultar después de 1.5 segundos
        setTimeout(() => {
            tooltip.hide();
        }, 1500);
    }

    // 3. Formateo de precios (se mantiene igual)
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

    // 4. Validación del formulario (se mantiene igual)
    const form = document.getElementById('form-agregar-producto');
    
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

    // 5. Auto-calcular precio de venta (se mantiene igual)
    const margenRecomendado = 1.1;
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

    // 6. Generador de código alternativo (mejorado)
    document.getElementById('btn-generar-codigo').addEventListener('click', function() {
        const randomNum = Math.floor(10000000 + Math.random() * 90000000);
        codigoBarrasInput.value = randomNum.toString();
        
        // Procesar como si fuera escaneado
        procesarCodigoBarras(codigoBarrasInput.value);
        
        // Mostrar confirmación
        mostrarTooltip('Código generado automáticamente', 'info');
    });

    // Estilos CSS adicionales para los tooltips
    const style = document.createElement('style');
    style.textContent = `
        .tooltip-success .tooltip-inner {
            background-color: #28a745;
        }
        .tooltip-danger .tooltip-inner {
            background-color: #dc3545;
        }
        .tooltip-info .tooltip-inner {
            background-color: #17a2b8;
        }
        .is-valid {
            border-color: #28a745 !important;
        }
        .is-invalid {
            border-color: #dc3545 !important;
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>