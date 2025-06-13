<?php
$tituloPagina = 'Mi Perfil - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario actual
$usuarioId = $_SESSION['id_usuario'];
$query = "SELECT u.*, g.nombre AS grupo_nombre 
          FROM usuarios u 
          JOIN grupo g ON u.grupo_id = g.id 
          WHERE u.id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

if (!$usuario) {
    header("Location: logout.php");
    exit();
}

// Procesar actualización de perfil
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($conexion->real_escape_string($_POST['nombre']));
    $passwordActual = $_POST['password_actual'] ?? '';
    $passwordNueva = $_POST['password_nueva'] ?? '';
    
    // Validaciones básicas
    if (empty($nombre)) {
        $errores[] = "El nombre es requerido";
    }
    
    // Procesar cambio de contraseña si se proporcionó la actual
    $passwordHash = $usuario['contraseña'];
    if (!empty($passwordActual)) {
        if (!password_verify($passwordActual, $usuario['contraseña'])) {
            $errores[] = "La contraseña actual es incorrecta";
        } elseif (strlen($passwordNueva) < 6) {
            $errores[] = "La nueva contraseña debe tener al menos 6 caracteres";
        } else {
            $passwordHash = password_hash($passwordNueva, PASSWORD_DEFAULT);
        }
    }
    
    // Procesar imagen de perfil
    $imagenNombre = $usuario['imagen'];
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($extension), $extensionesPermitidas)) {
            // Eliminar imagen anterior si existe
            if (!empty($imagenNombre)) {
                $rutaAnterior = 'assets/img/usuarios/' . $imagenNombre;
                if (file_exists($rutaAnterior)) {
                    unlink($rutaAnterior);
                }
            }
            
            // Subir nueva imagen
            $imagenNombre = 'user_' . $usuarioId . '_' . time() . '.' . $extension;
            $rutaDestino = 'assets/img/usuarios/' . $imagenNombre;
            
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
                $errores[] = "Error al subir la imagen";
                $imagenNombre = $usuario['imagen'];
            }
        } else {
            $errores[] = "Formato de imagen no válido (solo JPG, PNG, GIF)";
        }
    }
    
    // Actualizar si no hay errores
    if (empty($errores)) {
        $query = "UPDATE usuarios SET 
                  nombre = ?, 
                  contraseña = ?, 
                  imagen = ? 
                  WHERE id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param('sssi', $nombre, $passwordHash, $imagenNombre, $usuarioId);
        
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Perfil actualizado correctamente'
            ];
            
            // Actualizar datos en sesión
            $_SESSION['nombre'] = $nombre;
            $_SESSION['imagen'] = $imagenNombre;
            
            header("Location: perfil.php");
            exit();
        } else {
            $errores[] = "Error al actualizar el perfil: " . $conexion->error;
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white py-3">
                    <h3 class="mb-0"><i class="fas fa-user-circle me-2"></i>Mi Perfil</h3>
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
                    
                    <form method="post" enctype="multipart/form-data">
                        <div class="row g-3">
                            <!-- Columna izquierda -->
                            <div class="col-md-5">
                                <div class="text-center mb-4">
                                    <img src="<?= !empty($usuario['imagen']) ? 'assets/img/usuarios/' . htmlspecialchars($usuario['imagen']) : 'assets/img/usuario-default.png' ?>" 
                                         class="img-thumbnail rounded-circle mb-2" 
                                         id="imagen-preview" 
                                         style="width: 200px; height: 200px; object-fit: cover;"
                                         alt="Foto de perfil">
                                    
                                    <div class="mb-3">
                                        <label for="imagen" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-camera me-1"></i> Cambiar foto
                                            <input type="file" id="imagen" name="imagen" accept="image/*" class="d-none">
                                        </label>
                                        <div class="form-text">Formatos: JPG, PNG, GIF</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h5 class="fw-bold">Información del sistema</h5>
                                    <div class="card bg-light p-3">
                                        <div class="mb-2">
                                            <span class="text-muted">Usuario:</span>
                                            <strong>@<?= htmlspecialchars($usuario['usuario']) ?></strong>
                                        </div>
                                        <div class="mb-2">
                                            <span class="text-muted">Grupo:</span>
                                            <strong><?= htmlspecialchars($usuario['grupo_nombre']) ?></strong>
                                        </div>
                                        <div class="mb-2">
                                            <span class="text-muted">Rol:</span>
                                            <strong><?= htmlspecialchars($usuario['rol_usuario']) ?></strong>
                                        </div>
                                        <div>
                                            <span class="text-muted">Último login:</span>
                                            <strong><?= $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca' ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Columna derecha -->
                            <div class="col-md-7">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre completo</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                                </div>
                                
                                <hr class="my-4">
                                <?php if (tienePermiso(['Administrador', 'Contabilidad'])): ?>
                                <h5 class="fw-bold mb-3">Cambiar contraseña</h5>
                                
                                <div class="mb-3">
                                    
                                    <label for="password_actual" class="form-label">Contraseña actual</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password_actual" name="password_actual">
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Dejar en blanco para no cambiar</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password_nueva" class="form-label">Nueva contraseña</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password_nueva" name="password_nueva">
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Mínimo 6 caracteres</div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Guardar cambios
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
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
});
</script>

 <?php if (isset($_SESSION['mensaje'])): ?>
                <script>
                $(document).ready(function() {
                    Swal.fire({
                        title: '<?= $_SESSION['mensaje']['tipo'] === 'success' ? 'Éxito' : 'Error' ?>',
                        text: '<?= addslashes($_SESSION['mensaje']['texto']) ?>',
                        icon: '<?= $_SESSION['mensaje']['tipo'] ?>',
                        confirmButtonText: 'Aceptar'
                    });
                });
                </script>
                <?php unset($_SESSION['mensaje']); ?>
            <?php endif; ?>

<?php include_once 'includes/footer.php'; ?>