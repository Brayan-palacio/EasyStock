<?php
ob_start();
session_start();

// Verificar sesión y permisos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    ob_end_flush();
    exit();
}

// Verificar permisos (solo admin y mecánicos pueden agregar vehículos)
$rolesPermitidos = ['Admin', 'mecanico', 'supervisor']; // Roles permitidos
if (!in_array($_SESSION['rol'], $rolesPermitidos)) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'No tienes permisos para esta acción'
    ];
    header("Location: vehiculos.php");
    exit();
}

include 'config/conexion.php';
include_once('includes/header.php');

// Obtener lista de clientes activos
// Obtener lista de clientes para el select
$clientes = $conexion->query("SELECT id, nombre FROM clientes ORDER BY nombre");
// Tipos de vehículos predefinidos
$tiposVehiculo = [
    'Sedán', 'Hatchback', 'SUV', 'Camioneta', 'Pickup', 
    'Deportivo', 'Furgoneta', 'Motocicleta', 'Autobús', 'Otro'
];

// Estados posibles
$estadosVehiculo = ['Activo', 'Inactivo', 'En Taller', 'En Reparación', 'Baja Temporal'];

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanitizar datos
    $id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
    $marca = trim($conexion->real_escape_string($_POST['marca']));
    $modelo = trim($conexion->real_escape_string($_POST['modelo']));
    $anio = filter_input(INPUT_POST, 'anio', FILTER_VALIDATE_INT);
    $matricula = trim($conexion->real_escape_string($_POST['matricula']));
    $color = trim($conexion->real_escape_string($_POST['color']));
    $kilometraje = filter_input(INPUT_POST, 'kilometraje', FILTER_VALIDATE_INT);
    $tipo = isset($_POST['tipo']) ? $conexion->real_escape_string($_POST['tipo']) : '';
    $estado = $conexion->real_escape_string($_POST['estado']);
    $observaciones = trim($conexion->real_escape_string($_POST['observaciones'] ?? ''));

    // Validaciones
    $errores = [];
    
    if (empty($id_cliente)) {
        $errores[] = "Debe seleccionar un cliente válido";
    }
    
    if (empty($marca) || strlen($marca) < 2) {
        $errores[] = "La marca debe tener al menos 2 caracteres";
    }
    
    if (empty($modelo) || strlen($modelo) < 2) {
        $errores[] = "El modelo debe tener al menos 2 caracteres";
    }
    
    $anioActual = date('Y');
    if (empty($anio) || $anio < 1900 || $anio > $anioActual + 1) {
        $errores[] = "El año debe estar entre 1900 y ".($anioActual + 1);
    }
    
    if (empty($matricula) || strlen($matricula) < 4) {
        $errores[] = "La matrícula debe tener al menos 4 caracteres";
    } else {
        // Verificar si la matrícula ya existe
        $checkMatricula = $conexion->prepare("SELECT id FROM vehiculos WHERE matricula = ?");
        $checkMatricula->bind_param('s', $matricula);
        $checkMatricula->execute();
        if ($checkMatricula->get_result()->num_rows > 0) {
            $errores[] = "La matrícula ya está registrada para otro vehículo";
        }
    }
    
    if ($kilometraje === false || $kilometraje < 0) {
        $kilometraje = null; // Permitir null si no se especifica
    }

    // Procesar imagen si se subió
    $imagenNombre = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($extension, $extensionesPermitidas)) {
            // Generar nombre único para la imagen
            $imagenNombre = 'veh_' . time() . '.' . $extension;
            $rutaDestino = 'assets/img/vehiculos/' . $imagenNombre;
            
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
                $errores[] = "Error al subir la imagen del vehículo";
            }
        } else {
            $errores[] = "Formato de imagen no válido. Use JPG, PNG o GIF";
        }
    }

    // Si no hay errores, insertar en la base de datos
    if (empty($errores)) {
        try {
            $conexion->begin_transaction();
            
            $stmt = $conexion->prepare("INSERT INTO vehiculos 
                                      (id_cliente, marca, modelo, anio, matricula, color, 
                                       kilometraje, tipo, estado, observaciones, imagen, creado_por) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ississsssssi", 
                $id_cliente, $marca, $modelo, $anio, $matricula, $color,
                $kilometraje, $tipo, $estado, $observaciones, $imagenNombre, $_SESSION['id_usuario']);
            
            if ($stmt->execute()) {
                $id_vehiculo = $conexion->insert_id;
                
                // Registrar en el historial
                $accion = "Vehículo creado (ID: $id_vehiculo)";
                $historial = $conexion->prepare("INSERT INTO historial_vehiculos 
                                                (vehiculo_id, accion, usuario_id) 
                                                VALUES (?, ?, ?)");
                $historial->bind_param("isi", $id_vehiculo, $accion, $_SESSION['id_usuario']);
                $historial->execute();
                
                $conexion->commit();
                
                $_SESSION['mensaje'] = [
                    'tipo' => 'success',
                    'texto' => 'Vehículo agregado correctamente. ID: ' . $id_vehiculo
                ];
                header("Location: detalle_vehiculo.php?id=$id_vehiculo");
                exit();
            } else {
                throw new Exception("Error al guardar el vehículo: " . $conexion->error);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            
            // Eliminar imagen si se subió pero falló el registro
            if ($imagenNombre && file_exists($rutaDestino)) {
                unlink($rutaDestino);
            }
            
            $errores[] = $e->getMessage();
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-header bg-dark text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0 fw-bold">
                            <i class="fas fa-car me-2"></i> Registrar Nuevo Vehículo
                        </h2>
                        <a href="vehiculos.php" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-arrow-left me-1"></i> Volver
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($errores)): ?>
                        <div class="alert alert-danger">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Errores encontrados:</h5>
                            <ul class="mb-0">
                                <?php foreach ($errores as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="agregar_vehiculo.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <!-- Columna Izquierda -->
                            <div class="col-md-6">
                                <!-- Sección: Información del Cliente -->
                                <div class="mb-4">
                                    <h5 class="fw-bold border-bottom pb-2 mb-3">
                                        <i class="fas fa-user me-2"></i> Datos del Cliente
                                    </h5>
                                    
                                    <div class="mb-3">
                                        <label for="id_cliente" class="form-label">Cliente <span class="text-danger">*</span></label>
                                        <select class="form-select" id="id_cliente" name="id_cliente" required>
                                            <option value="">Seleccionar cliente...</option>
                                            <?php while ($cliente = $clientes->fetch_assoc()): ?>
                                                <option value="<?= $cliente['id'] ?>" 
                                                    <?= (isset($_POST['id_cliente']) && $_POST['id_cliente'] == $cliente['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cliente['nombre']) ?> 
                                                    (<?= htmlspecialchars($cliente['telefono']) ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="invalid-feedback">Por favor seleccione un cliente</div>
                                    </div>
                                </div>
                                
                                <!-- Sección: Información Básica del Vehículo -->
                                <div class="mb-4">
                                    <h5 class="fw-bold border-bottom pb-2 mb-3">
                                        <i class="fas fa-car me-2"></i> Datos Básicos
                                    </h5>
                                    
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="marca" class="form-label">Marca <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="marca" name="marca" 
                                                       value="<?= isset($_POST['marca']) ? htmlspecialchars($_POST['marca']) : '' ?>" 
                                                       required minlength="2">
                                                <div class="invalid-feedback">Ingrese una marca válida</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="modelo" class="form-label">Modelo <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="modelo" name="modelo" 
                                                       value="<?= isset($_POST['modelo']) ? htmlspecialchars($_POST['modelo']) : '' ?>" 
                                                       required minlength="2">
                                                <div class="invalid-feedback">Ingrese un modelo válido</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="anio" class="form-label">Año <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="anio" name="anio" 
                                                       value="<?= isset($_POST['anio']) ? htmlspecialchars($_POST['anio']) : date('Y') ?>" 
                                                       min="1900" max="<?= date('Y') + 1 ?>" required>
                                                <div class="invalid-feedback">Ingrese un año válido</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="tipo" class="form-label">Tipo de Vehículo</label>
                                                <select class="form-select" id="tipo" name="tipo">
                                                    <option value="">Seleccionar tipo...</option>
                                                    <?php foreach ($tiposVehiculo as $tipo): ?>
                                                        <option value="<?= htmlspecialchars($tipo) ?>" 
                                                            <?= (isset($_POST['tipo']) && $_POST['tipo'] == $tipo) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($tipo) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Columna Derecha -->
                            <div class="col-md-6">
                                <!-- Sección: Identificación del Vehículo -->
                                <div class="mb-4">
                                    <h5 class="fw-bold border-bottom pb-2 mb-3">
                                        <i class="fas fa-id-card me-2"></i> Identificación
                                    </h5>
                                    
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="matricula" class="form-label">Matrícula/Placa <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control text-uppercase" id="matricula" name="matricula" 
                                                       value="<?= isset($_POST['matricula']) ? htmlspecialchars($_POST['matricula']) : '' ?>" 
                                                       required minlength="4" maxlength="15">
                                                <div class="invalid-feedback">Ingrese una matrícula válida</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="color" class="form-label">Color</label>
                                                <input type="text" class="form-control" id="color" name="color" 
                                                       value="<?= isset($_POST['color']) ? htmlspecialchars($_POST['color']) : '' ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="kilometraje" class="form-label">Kilometraje</label>
                                                <input type="number" class="form-control" id="kilometraje" name="kilometraje" 
                                                       value="<?= isset($_POST['kilometraje']) ? htmlspecialchars($_POST['kilometraje']) : '' ?>" 
                                                       min="0" step="1">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                                                <select class="form-select" id="estado" name="estado" required>
                                                    <?php foreach ($estadosVehiculo as $estado): ?>
                                                        <option value="<?= htmlspecialchars($estado) ?>" 
                                                            <?= (isset($_POST['estado']) && $_POST['estado'] == $estado) ? 'selected' : 
                                                               ($estado == 'Activo' ? 'selected' : '') ?>>
                                                            <?= htmlspecialchars($estado) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sección: Imagen y Observaciones -->
                                <div class="mb-4">
                                    <h5 class="fw-bold border-bottom pb-2 mb-3">
                                        <i class="fas fa-image me-2"></i> Fotografía y Notas
                                    </h5>
                                    
                                    <div class="mb-3">
                                        <label for="imagen" class="form-label">Foto del Vehículo</label>
                                        <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                                        <div class="form-text">Formatos: JPG, PNG, GIF (Máx. 2MB)</div>
                                        <div class="mt-2 text-center" id="imagen-preview-container" style="display: none;">
                                            <img id="imagen-preview" class="img-thumbnail" style="max-height: 150px;">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="observaciones" class="form-label">Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" 
                                                  rows="3"><?= isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : '' ?></textarea>
                                        <div class="form-text">Notas adicionales sobre el vehículo</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-eraser me-1"></i> Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Registrar Vehículo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once('includes/footer.php'); ?>

<!-- JavaScript para validación y mejoras de UI -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Validación del formulario
    const forms = document.querySelector('.needs-validation');
    forms.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
    
    // Convertir matrícula a mayúsculas automáticamente
    document.getElementById('matricula').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    // Validación del año
    const anioInput = document.getElementById('anio');
    anioInput.addEventListener('change', function() {
        const currentYear = new Date().getFullYear();
        if (this.value < 1900 || this.value > currentYear + 1) {
            this.setCustomValidity('El año debe estar entre 1900 y ' + (currentYear + 1));
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Preview de imagen
    const imagenInput = document.getElementById('imagen');
    const previewContainer = document.getElementById('imagen-preview-container');
    const preview = document.getElementById('imagen-preview');
    
    imagenInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewContainer.style.display = 'block';
            }
            reader.readAsDataURL(file);
        } else {
            previewContainer.style.display = 'none';
        }
    });
    
    // Autocompletar marcas populares
    const marcasPopulares = ['Toyota', 'Nissan', 'Honda', 'Hyundai', 'Kia', 'Chevrolet', 'Ford', 'Volkswagen', 'Mazda', 'BMW'];
    const marcaInput = document.getElementById('marca');
    
    marcaInput.addEventListener('input', function() {
        const datalist = document.createElement('datalist');
        datalist.id = 'marcas-sugeridas';
        
        marcasPopulares.forEach(marca => {
            if (marca.toLowerCase().includes(this.value.toLowerCase())) {
                const option = document.createElement('option');
                option.value = marca;
                datalist.appendChild(option);
            }
        });
        
        // Eliminar datalist anterior si existe
        const oldDatalist = document.getElementById('marcas-sugeridas');
        if (oldDatalist) {
            document.body.removeChild(oldDatalist);
        }
        
        if (datalist.hasChildNodes()) {
            document.body.appendChild(datalist);
            this.setAttribute('list', 'marcas-sugeridas');
        } else {
            this.removeAttribute('list');
        }
    });
});
</script>

<style>
/* Estilos personalizados */
.card {
    border-radius: 10px;
    overflow: hidden;
}
.color-indicator {
    display: inline-block;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    margin-right: 5px;
    border: 1px solid #dee2e6;
}
</style>