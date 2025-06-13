<?php
ob_start();
$tituloPagina = 'Agregar Grupo - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos (nivel >= 100 para administradores)
if (!isset($_SESSION['id_usuario'])){
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'No tienes permisos para esta acción'
    ];
    header("Location: grupos.php");
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($conexion->real_escape_string($_POST['nombre']));
    $nivel = (int)$_POST['nivel'];
    $estado = in_array($_POST['estado'], ['Activo', 'Inactivo']) ? $_POST['estado'] : 'Activo';

    // Validaciones
    $errores = [];

    if (empty($nombre)) {
        $errores[] = 'El nombre del grupo es requerido';
    } elseif (strlen($nombre) > 100) {
        $errores[] = 'El nombre no debe exceder 100 caracteres';
    }

    if ($nivel < 1 || $nivel > 1000) {
        $errores[] = 'El nivel debe estar entre 1 y 1000';
    }

    // Verificar si el nombre ya existe
    $stmt = $conexion->prepare("SELECT id FROM grupo WHERE nombre = ?");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errores[] = 'Ya existe un grupo con este nombre';
    }

    // Si no hay errores, insertar
    if (empty($errores)) {
        $stmt = $conexion->prepare("INSERT INTO grupo (nombre, nivel, estado) VALUES (?, ?, ?)");
        $stmt->bind_param('sis', $nombre, $nivel, $estado);
        
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Grupo creado exitosamente!'
            ];
            header("Location: grupos.php");
            exit();
        } else {
            $errores[] = 'Error al guardar: ' . $conexion->error;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-header bg-dark text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0 fw-semibold">
                            <i class="fas fa-users-cog me-2"></i> Nuevo Grupo
                        </h2>
                        <a href="grupos.php" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-arrow-left me-1"></i> Regresar
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($errores)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Errores encontrados:</strong>
                            <ul class="mb-0 mt-1">
                                <?php foreach ($errores as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" autocomplete="off">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del Grupo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" 
                                   required maxlength="100" placeholder="Ej: Administradores">
                            <div class="form-text">Máximo 100 caracteres</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nivel" class="form-label">Nivel de Permisos <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="nivel" name="nivel" 
                                   value="<?= htmlspecialchars($_POST['nivel'] ?? 10) ?>" 
                                   min="1" max="1000" required>
                            <div class="form-text">
                                Nivel jerárquico (1-1000). Ej: 100=Admin, 50=Supervisor, 10=Usuario
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Estado <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="estado" id="estado-activo" 
                                       value="Activo" <?= (!isset($_POST['estado'])) || ($_POST['estado'] ?? '') === 'Activo' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="estado-activo">
                                    <span class="badge bg-success rounded-pill px-2 py-1">Activo</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="estado" id="estado-inactivo" 
                                       value="Inactivo" <?= ($_POST['estado'] ?? '') === 'Inactivo' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="estado-inactivo">
                                    <span class="badge bg-secondary rounded-pill px-2 py-1">Inactivo</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end border-top pt-3">
                            <button type="reset" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-undo me-1"></i> Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Grupo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tarjeta de ayuda -->
            <div class="card mt-4 border-0 bg-light">
                <div class="card-body py-3">
                    <h5 class="h6 mb-2">
                        <i class="fas fa-info-circle text-primary me-1"></i> Sobre los grupos
                    </h5>
                    <ul class="mb-0 text-muted small">
                        <li>Los grupos determinan los permisos de los usuarios en el sistema.</li>
                        <li>El <strong>nivel</strong> define la jerarquía (a mayor número, más privilegios).</li>
                        <li>Puedes desactivar grupos temporalmente sin eliminarlos.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<!-- Validación del formulario en cliente -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const formulario = document.querySelector('form');
    
    formulario.addEventListener('submit', function(e) {
        let valid = true;
        
        // Validar nombre
        const nombre = document.getElementById('nombre');
        if (nombre.value.trim() === '') {
            valid = false;
            nombre.classList.add('is-invalid');
        } else {
            nombre.classList.remove('is-invalid');
        }
        
        // Validar nivel
        const nivel = document.getElementById('nivel');
        if (nivel.value < 1 || nivel.value > 1000) {
            valid = false;
            nivel.classList.add('is-invalid');
        } else {
            nivel.classList.remove('is-invalid');
        }
        
        if (!valid) {
            e.preventDefault();
            Swal.fire({
                title: '¡Error!',
                text: 'Por favor completa todos los campos requeridos correctamente',
                icon: 'error',
                confirmButtonText: 'Entendido'
            });
        }
    });
});
</script>