<?php
$tituloPagina = 'Agregar clientes - EasyStock';
// Configuración de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Iniciar sesión y verificar permisos
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Conexión a la base de datos
require 'config/conexion.php';

// Inicializar variables
$errores = [];
$valores = [
    'nombre' => '',
    'identificacion' => '',
    'email' => '',
    'telefono' => '',
    'direccion' => ''
];

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar y validar datos
    $valores['nombre'] = trim($_POST['nombre'] ?? '');
    $valores['identificacion'] = trim($_POST['identificacion'] ?? '');
    $valores['email'] = trim($_POST['email'] ?? '');
    $valores['telefono'] = trim($_POST['telefono'] ?? '');
    $valores['direccion'] = trim($_POST['direccion'] ?? '');

    // Validaciones
    if (empty($valores['nombre'])) {
        $errores['nombre'] = 'El nombre es obligatorio';
    } elseif (strlen($valores['nombre']) > 100) {
        $errores['nombre'] = 'El nombre no puede exceder los 100 caracteres';
    }

    if (empty($valores['identificacion'])) {
        $errores['identificacion'] = 'La identificación es obligatoria';
    } elseif (strlen($valores['identificacion']) > 20) {
        $errores['identificacion'] = 'La identificación no puede exceder los 20 caracteres';
    }

    if (!empty($valores['email']) && !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'El email no es válido';
    } elseif (strlen($valores['email']) > 100) {
        $errores['email'] = 'El email no puede exceder los 100 caracteres';
    }

    if (!empty($valores['telefono']) && strlen($valores['telefono']) > 20) {
        $errores['telefono'] = 'El teléfono no puede exceder los 20 caracteres';
    }

    // Verificar si la identificación ya existe
    if (empty($errores['identificacion'])) {
        $stmt = $conexion->prepare("SELECT id FROM clientes WHERE identificacion = ?");
        $stmt->bind_param("s", $valores['identificacion']);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errores['identificacion'] = 'Esta identificación ya está registrada';
        }
        $stmt->close();
    }

    // Si no hay errores, insertar en la base de datos
    if (empty($errores)) {
        try {
            $stmt = $conexion->prepare("INSERT INTO clientes 
                                      (nombre, identificacion, email, telefono, direccion, estado, creado_en) 
                                      VALUES (?, ?, ?, ?, ?, 'Activo', NOW())");
            
            $stmt->bind_param("sssss", 
                $valores['nombre'],
                $valores['identificacion'],
                $valores['email'],
                $valores['telefono'],
                $valores['direccion']
            );
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = [
                    'tipo' => 'success',
                    'texto' => 'Cliente agregado correctamente'
                ];
                header("Location: clientes.php");
                exit();
            } else {
                throw new Exception("Error al guardar el cliente");
            }
        } catch (Exception $e) {
            $errores['general'] = 'Ocurrió un error al guardar el cliente. Por favor intenta nuevamente.';
            error_log($e->getMessage());
        }
    } else {
        $errores['general'] = 'Por favor corrige los errores en el formulario';
    }
}

include_once('includes/header.php');
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-header bg-dark text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0 fw-bold">
                            <i class="fas fa-user-plus me-2"></i> Agregar Nuevo Cliente
                        </h2>
                        <a href="clientes.php" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-arrow-left me-1"></i> Volver
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($errores['general'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= htmlspecialchars($errores['general']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="form-cliente">
                        <div class="row g-3">
                            <!-- Campo Nombre -->
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errores['nombre']) ? 'is-invalid' : '' ?>" 
                                       id="nombre" name="nombre" 
                                       value="<?= htmlspecialchars($valores['nombre']) ?>" 
                                       required maxlength="100">
                                <?php if (isset($errores['nombre'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($errores['nombre']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Campo Identificación -->
                            <div class="col-md-6">
                                <label for="identificacion" class="form-label">Identificación <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errores['identificacion']) ? 'is-invalid' : '' ?>" 
                                       id="identificacion" name="identificacion" 
                                       value="<?= htmlspecialchars($valores['identificacion']) ?>" 
                                       required maxlength="20">
                                <?php if (isset($errores['identificacion'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($errores['identificacion']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Campo Email -->
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control <?= isset($errores['email']) ? 'is-invalid' : '' ?>" 
                                       id="email" name="email" 
                                       value="<?= htmlspecialchars($valores['email']) ?>" 
                                       maxlength="100">
                                <?php if (isset($errores['email'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($errores['email']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Campo Teléfono -->
                            <div class="col-md-6">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control <?= isset($errores['telefono']) ? 'is-invalid' : '' ?>" 
                                       id="telefono" name="telefono" 
                                       value="<?= htmlspecialchars($valores['telefono']) ?>" 
                                       maxlength="20">
                                <?php if (isset($errores['telefono'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($errores['telefono']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Campo Dirección -->
                            <div class="col-12">
                                <label for="direccion" class="form-label">Dirección</label>
                                <textarea class="form-control" id="direccion" name="direccion" 
                                          rows="2"><?= htmlspecialchars($valores['direccion']) ?></textarea>
                            </div>
                            
                            <!-- Botones -->
                            <div class="col-12 mt-4">
                                <div class="d-flex justify-content-between">
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-undo me-1"></i> Limpiar
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Guardar Cliente
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

<?php include_once('includes/footer.php'); ?>

<!-- JavaScript para validación en el cliente -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById('form-cliente');
    
    form.addEventListener('submit', function(e) {
        let valid = true;
        
        // Validar nombre
        const nombre = document.getElementById('nombre');
        if (nombre.value.trim() === '') {
            nombre.classList.add('is-invalid');
            nombre.nextElementSibling.textContent = 'El nombre es obligatorio';
            valid = false;
        } else if (nombre.value.length > 100) {
            nombre.classList.add('is-invalid');
            nombre.nextElementSibling.textContent = 'El nombre no puede exceder los 100 caracteres';
            valid = false;
        } else {
            nombre.classList.remove('is-invalid');
        }
        
        // Validar identificación
        const identificacion = document.getElementById('identificacion');
        if (identificacion.value.trim() === '') {
            identificacion.classList.add('is-invalid');
            identificacion.nextElementSibling.textContent = 'La identificación es obligatoria';
            valid = false;
        } else if (identificacion.value.length > 20) {
            identificacion.classList.add('is-invalid');
            identificacion.nextElementSibling.textContent = 'La identificación no puede exceder los 20 caracteres';
            valid = false;
        } else {
            identificacion.classList.remove('is-invalid');
        }
        
        // Validar email si está presente
        const email = document.getElementById('email');
        if (email.value.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
            email.classList.add('is-invalid');
            email.nextElementSibling.textContent = 'El email no es válido';
            valid = false;
        } else if (email.value.length > 100) {
            email.classList.add('is-invalid');
            email.nextElementSibling.textContent = 'El email no puede exceder los 100 caracteres';
            valid = false;
        } else {
            email.classList.remove('is-invalid');
        }
        
        // Validar teléfono si está presente
        const telefono = document.getElementById('telefono');
        if (telefono.value.length > 20) {
            telefono.classList.add('is-invalid');
            telefono.nextElementSibling.textContent = 'El teléfono no puede exceder los 20 caracteres';
            valid = false;
        } else {
            telefono.classList.remove('is-invalid');
        }
        
        if (!valid) {
            e.preventDefault();
            
            // Mostrar mensaje de error general
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show mb-3';
            alert.innerHTML = `
                <i class="fas fa-exclamation-circle me-2"></i>
                Por favor corrige los errores en el formulario
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            const cardBody = document.querySelector('.card-body');
            if (cardBody.firstChild.className !== 'alert alert-danger alert-dismissible fade show') {
                cardBody.insertBefore(alert, cardBody.firstChild);
            }
            
            // Desplazarse al primer error
            const firstInvalid = document.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
        }
    });
    
    // Limpiar validaciones al modificar los campos
    document.querySelectorAll('input, textarea').forEach(input => {
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
            }
        });
    });
});
</script>

<style>
.card {
    border-top: 4px solid #2a5a46;
}
.form-label {
    font-weight: 500;
}
.is-invalid {
    border-color: #dc3545;
}
</style>