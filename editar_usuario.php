<?php
ob_start();
$tituloPagina = 'Editar Usuario - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos (ejemplo: nivel >= 50 para editar usuarios)
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres nivel 50+ para esta acción'
    ];
    ob_end_clean();
    header("Location: administrar_usuarios.php");
    exit();
}

// Obtener ID del usuario a editar
$usuarioId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener datos actuales del usuario
$usuarioActual = null;
if ($usuarioId > 0) {
    $query = "SELECT u.*, g.nivel as grupo_nivel 
              FROM usuarios u 
              JOIN grupo g ON u.grupo_id = g.id 
              WHERE u.id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuarioActual = $result->fetch_assoc();
}

// Si no existe el usuario, redirigir
if (!$usuarioActual) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Usuario no encontrado'
    ];
    header("Location: administrar_usuarios.php");
    exit();
}

// Obtener grupos y roles disponibles
$grupos = $conexion->query("SELECT id, nombre FROM grupo WHERE estado = 'Activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$roles = ['Administrador', 'Supervisor', 'Usuario', 'Consulta']; // Ejemplo de roles

// Procesar formulario
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar inputs
    $datos = [
        'nombre' => trim($conexion->real_escape_string($_POST['nombre'])),
        'usuario' => strtolower(trim($conexion->real_escape_string($_POST['usuario']))),
        'rol_usuario' => in_array($_POST['rol_usuario'], $roles) ? $_POST['rol_usuario'] : 'Usuario',
        'grupo_id' => (int)$_POST['grupo_id'],
        'estado' => in_array($_POST['estado'], ['Activo', 'Inactivo']) ? $_POST['estado'] : 'Activo',
        'cambiar_password' => isset($_POST['cambiar_password']),
        'password' => $_POST['password'] ?? ''
    ];

    // Validaciones
    if (empty($datos['nombre'])) $errores[] = "Nombre completo es requerido";
    
    if (empty($datos['usuario'])) {
        $errores[] = "Usuario es requerido";
    } elseif (!preg_match('/^[a-z0-9_]{4,20}$/', $datos['usuario'])) {
        $errores[] = "Usuario solo puede contener letras, números y _ (4-20 caracteres)";
    } else {
        // Verificar si el usuario ya existe (excluyendo el actual)
        $existe = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
        $existe->bind_param('si', $datos['usuario'], $usuarioId);
        $existe->execute();
        if ($existe->get_result()->num_rows > 0) {
            $errores[] = "El usuario ya está en uso por otro registro";
        }
    }

    if ($datos['cambiar_password'] && strlen($datos['password']) < 6) {
        $errores[] = "Contraseña debe tener mínimo 6 caracteres";
    }

    // Procesar imagen
    $imagenNombre = $usuarioActual['imagen'];
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($extension), $extensionesPermitidas)) {
            // Eliminar imagen anterior si existe
            if ($imagenNombre && file_exists('assets/img/usuarios/' . $imagenNombre)) {
                unlink('assets/img/usuarios/' . $imagenNombre);
            }
            
            $imagenNombre = 'user_' . time() . '.' . $extension;
            $rutaDestino = 'assets/img/usuarios/' . $imagenNombre;
            
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
                $errores[] = "Error al subir la imagen";
                $imagenNombre = $usuarioActual['imagen'];
            }
        } else {
            $errores[] = "Formato de imagen no válido (solo JPG, PNG, GIF)";
        }
    }

    // Actualizar si no hay errores
    if (empty($errores)) {
        // Preparar consulta según si se cambia la contraseña o no
        if ($datos['cambiar_password']) {
            $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);
            $query = "UPDATE usuarios SET
                      nombre = ?, usuario = ?, contraseña = ?, rol_usuario = ?, 
                      estado = ?, grupo_id = ?, imagen = ?
                      WHERE id = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param(
                'sssssssi',
                $datos['nombre'],
                $datos['usuario'],
                $passwordHash,
                $datos['rol_usuario'],
                $datos['estado'],
                $datos['grupo_id'],
                $imagenNombre,
                $usuarioId
            );
        } else {
            $query = "UPDATE usuarios SET
                      nombre = ?, usuario = ?, rol_usuario = ?, 
                      estado = ?, grupo_id = ?, imagen = ?
                      WHERE id = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param(
                'ssssssi',
                $datos['nombre'],
                $datos['usuario'],
                $datos['rol_usuario'],
                $datos['estado'],
                $datos['grupo_id'],
                $imagenNombre,
                $usuarioId
            );
        }

        if ($stmt->execute()) {
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Usuario actualizado exitosamente!'
            ];
            header("Location: administrar_usuarios.php");
            exit();
        } else {
            $errores[] = "Error al actualizar: " . $conexion->error;
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-user-edit me-2"></i>Editar Usuario: <?= htmlspecialchars($usuarioActual['nombre']) ?>
                    </h3>
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
                                           value="<?= htmlspecialchars($usuarioActual['nombre']) ?>" required>
                                    <div class="invalid-feedback">Por favor ingrese el nombre</div>
                                </div>
                                
                                <!-- Campo Usuario -->
                                <div class="mb-3">
                                    <label for="usuario" class="form-label">Usuario</label>
                                    <div class="input-group">
                                        <span class="input-group-text">@</span>
                                        <input type="text" class="form-control" id="usuario" name="usuario" 
                                               pattern="[a-z0-9_]{4,20}" 
                                               value="<?= htmlspecialchars($usuarioActual['usuario']) ?>" required>
                                    </div>
                                    <div class="form-text">4-20 caracteres (letras, números, _)</div>
                                    <div class="invalid-feedback">Usuario no válido</div>
                                </div>
                                
                                <!-- Campo Contraseña -->
                                <div class="mb-3">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="cambiar_password" name="cambiar_password">
                                        <label class="form-check-label" for="cambiar_password">Cambiar contraseña</label>
                                    </div>
                                    <div class="input-group password-field" style="display: none;">
                                        <input type="password" class="form-control" id="password" name="password" minlength="6">
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
                                        <?php if ($usuarioActual['imagen'] && file_exists('assets/img/usuarios/' . $usuarioActual['imagen'])): ?>
                                            <img src="assets/img/usuarios/<?= htmlspecialchars($usuarioActual['imagen']) ?>" 
                                                 id="imagen-preview" class="img-thumbnail" style="max-width: 150px;">
                                        <?php else: ?>
                                            <img src="assets/img/usuario-default.png" id="imagen-preview" 
                                                 class="img-thumbnail" style="max-width: 150px;">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Campo Grupo -->
                                <div class="mb-3">
                                    <label for="grupo_id" class="form-label">Grupo</label>
                                    <select class="form-select" id="grupo_id" name="grupo_id" required>
                                        <option value="">Seleccionar grupo...</option>
                                        <?php foreach ($grupos as $grupo): ?>
                                            <option value="<?= $grupo['id'] ?>" 
                                                <?= ($usuarioActual['grupo_id'] == $grupo['id'] || ($_POST['grupo_id'] ?? '') == $grupo['id']) ? 'selected' : '' ?>
                                                <?= ($grupo['id'])?>>
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
                                                <?= ($usuarioActual['rol_usuario'] == $rol || ($_POST['rol_usuario'] ?? '') == $rol) ? 'selected' : '' ?>>
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
                                               value="Activo" <?= ($usuarioActual['estado'] == 'Activo' || ($_POST['estado'] ?? '') == 'Activo') ? 'checked' : '' ?> required>
                                        <label class="form-check-label" for="estado_activo">Activo</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="estado" id="estado_inactivo" 
                                               value="Inactivo" <?= ($usuarioActual['estado'] == 'Inactivo' || ($_POST['estado'] ?? '') == 'Inactivo') ? 'checked' : '' ?>>
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
                                <i class="fas fa-save me-1"></i> Guardar Cambios
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
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(event) {
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

    // Toggle campo contraseña
    const cambiarPassword = document.getElementById('cambiar_password');
    const passwordField = document.querySelector('.password-field');
    
    cambiarPassword.addEventListener('change', function() {
        passwordField.style.display = this.checked ? 'flex' : 'none';
        if (this.checked) {
            passwordField.querySelector('input').required = true;
        } else {
            passwordField.querySelector('input').required = false;
        }
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