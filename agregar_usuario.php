<?php
ob_start();
$tituloPagina = 'Agregar Usuario - EasyStock';
include 'config/conexion.php';
include 'config/funciones.php'; // Asegúrate de incluir funciones
include_once 'includes/header.php';

// Verificar permisos de administrador
if (!isset($_SESSION['id_usuario']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['rol_usuario'] !== 'Administrador') {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'No tienes permisos para agregar usuarios'
    ];
    header("Location: administrar_usuarios.php");
    exit();
}

// Obtener grupos y roles disponibles
$grupos = $conexion->query("SELECT id, nombre FROM grupo WHERE estado = 'Activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$roles = ['Administrador', 'Supervisor', 'Usuario', 'Consulta'];

// Procesar formulario
$errores = [];
$datos = [
    'nombre' => '',
    'usuario' => '',
    'rol_usuario' => 'Usuario',
    'grupo_id' => '',
    'estado' => 'Activo'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar inputs
    $datos['nombre'] = trim($_POST['nombre'] ?? '');
    $datos['usuario'] = strtolower(trim($_POST['usuario'] ?? ''));
    $password = $_POST['password'] ?? '';
    $datos['rol_usuario'] = in_array($_POST['rol_usuario'] ?? '', $roles) ? $_POST['rol_usuario'] : 'Usuario';
    $datos['grupo_id'] = (int)($_POST['grupo_id'] ?? 0);
    $datos['estado'] = in_array($_POST['estado'] ?? '', ['Activo', 'Inactivo']) ? $_POST['estado'] : 'Activo';

    // Validaciones
    if (empty($datos['nombre'])) {
        $errores[] = "Nombre completo es requerido";
    } elseif (strlen($datos['nombre']) < 2) {
        $errores[] = "Nombre debe tener al menos 2 caracteres";
    }
    
    if (empty($datos['usuario'])) {
        $errores[] = "Usuario es requerido";
    } elseif (!preg_match('/^[a-z0-9_]{4,20}$/', $datos['usuario'])) {
        $errores[] = "Usuario solo puede contener letras minúsculas, números y _ (4-20 caracteres)";
    }

    if (strlen($password) < 6) {
        $errores[] = "Contraseña debe tener mínimo 6 caracteres";
    }

    if ($datos['grupo_id'] <= 0) {
        $errores[] = "Debe seleccionar un grupo válido";
    }

    // Procesar imagen
    $imagenNombre = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        // Validar tipo MIME real
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
        finfo_close($finfo);
        
        $mimesPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mime, $mimesPermitidos)) {
            $errores[] = "Tipo de archivo no permitido";
        }
        
        // Validar tamaño (2MB máximo)
        if ($_FILES['imagen']['size'] > 2 * 1024 * 1024) {
            $errores[] = "La imagen no puede ser mayor a 2MB";
        }
        
        if (empty($errores)) {
            $extension = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                default => null
            };
            
            if ($extension) {
                $imagenNombre = 'user_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $rutaDestino = 'assets/img/usuarios/' . $imagenNombre;
                
                if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
                    $errores[] = "Error al subir la imagen";
                    $imagenNombre = null;
                }
            }
        }
    }

    // Insertar si no hay errores
    if (empty($errores)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO usuarios (
                  nombre, usuario, contraseña, rol_usuario, estado, grupo_id, imagen
                  ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conexion->prepare($query);
        $stmt->bind_param(
            'sssssis',
            $datos['nombre'],
            $datos['usuario'],
            $passwordHash,
            $datos['rol_usuario'],
            $datos['estado'],
            $datos['grupo_id'],
            $imagenNombre
        );

        if ($stmt->execute()) {
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Usuario registrado exitosamente!'
            ];
            header("Location: administrar_usuarios.php");
            exit();
        } else {
            // Verificar si es error de usuario duplicado
            if ($conexion->errno === 1062) {
                $errores[] = "El usuario ya existe";
            } else {
                $errores[] = "Error al guardar el usuario";
            }
            
            // Eliminar imagen si falla la inserción
            if ($imagenNombre && file_exists($rutaDestino)) {
                unlink($rutaDestino);
            }
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fas fa-user-plus me-2"></i>Nuevo Usuario</h3>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($errores)): ?>
                        <div class="alert alert-danger">
                            <strong>Errores:</strong>
                            <ul class="mb-0">
                                <?php foreach ($errores as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <!-- Columna Izquierda -->
                            <div class="col-md-6">
                                <!-- Campo Nombre -->
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre Completo</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Por favor ingrese el nombre</div>
                                </div>
                                
                                <!-- Campo Usuario -->
                                <div class="mb-3">
                                    <label for="usuario" class="form-label">Usuario</label>
                                    <div class="input-group">
                                        <span class="input-group-text">@</span>
                                        <input type="text" class="form-control" id="usuario" name="usuario" 
                                               pattern="[a-z0-9_]{4,20}" 
                                               value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-text">4-20 caracteres (letras, números, _)</div>
                                    <div class="invalid-feedback">Usuario no válido</div>
                                </div>
                                
                                <!-- Campo Contraseña -->
                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               minlength="6" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Mínimo 6 caracteres</div>
                                </div>
                            </div>
                            
                            <!-- Columna Derecha -->
                            <div class="col-md-6">
                                <!-- Campo Imagen -->
                                <div class="mb-3">
                                    <label for="imagen" class="form-label">Foto de Perfil</label>
                                    <input class="form-control" type="file" id="imagen" name="imagen" accept="image/*">
                                    <div class="form-text">Formatos: JPG, PNG, GIF (Max 2MB)</div>
                                    <div class="preview mt-2 text-center">
                                        <img src="assets/img/usuario-default.png" id="imagen-preview" 
                                             class="img-thumbnail" style="max-width: 150px;">
                                    </div>
                                </div>
                                
                                <!-- Campo Grupo -->
                                <div class="mb-3">
                                    <label for="grupo_id" class="form-label">Grupo</label>
                                    <select class="form-select" id="grupo_id" name="grupo_id" required>
                                        <option value="">Seleccionar grupo...</option>
                                        <?php foreach ($grupos as $grupo): ?>
                                            <option value="<?= $grupo['id'] ?>" 
                                                <?= ($_POST['grupo_id'] ?? '') == $grupo['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($grupo['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Seleccione un grupo</div>
                                </div>
                                
                                <!-- Campo Rol -->
                                <div class="mb-3">
                                    <label for="rol_usuario" class="form-label">Rol</label>
                                    <select class="form-select" id="rol_usuario" name="rol_usuario" required>
                                        <?php foreach ($roles as $rol): ?>
                                            <option value="<?= $rol ?>" 
                                                <?= ($_POST['rol_usuario'] ?? 'Usuario') == $rol ? 'selected' : '' ?>>
                                                <?= $rol ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Campo Estado -->
                                <div class="mb-3">
                                    <label class="form-label">Estado</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="estado" id="estado_activo" 
                                               value="Activo" <?= ($_POST['estado'] ?? 'Activo') == 'Activo' ? 'checked' : '' ?> required>
                                        <label class="form-check-label" for="estado_activo">Activo</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="estado" id="estado_inactivo" 
                                               value="Inactivo" <?= ($_POST['estado'] ?? '') == 'Inactivo' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="estado_inactivo">Inactivo</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="administrar_usuarios.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Usuario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<!-- JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Validación de formulario
    const forms = document.querySelector('.needs-validation');
    forms.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);

    // Mostrar/ocultar contraseña
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    });

    // Preview de imagen
    const imagenInput = document.getElementById('imagen');
    const imagenPreview = document.getElementById('imagen-preview');
    
    imagenInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagenPreview.src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // Auto lowercase para usuario
    const usuarioInput = document.getElementById('usuario');
    if (usuarioInput) {
        usuarioInput.addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
        });
    }
});
</script>