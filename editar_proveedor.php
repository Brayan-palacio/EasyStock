<?php
session_start();
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Editar Proveedor - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol_usuario'] != 'Administrador') {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres permisos de administrador'
    ];
    header("Location: index.php");
    exit();
}

// Obtener datos del proveedor a editar
$proveedor = null;
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conexion->prepare("SELECT * FROM proveedores WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $proveedor = $result->fetch_assoc();
    
    if (!$proveedor) {
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => 'Proveedor no encontrado'
        ];
        header("Location: proveedores.php");
        exit();
    }
} else {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'ID de proveedor no especificado'
    ];
    header("Location: proveedores.php");
    exit();
}

// Procesar el formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $datos = [
            'id' => (int)$_POST['id'],
            'nombre' => trim($_POST['nombre']),
            'nit' => trim($_POST['nit']),
            'telefono' => trim($_POST['telefono']),
            'email' => trim($_POST['email']),
            'direccion' => trim($_POST['direccion']),
            'tipo_identificacion' => trim($_POST['tipo_identificacion']),
            'limite_credito' => !empty($_POST['limite_credito']) ? (float)$_POST['limite_credito'] : null
        ];

        // Validaciones
        if (empty($datos['nombre'])) {
            throw new Exception("El nombre es obligatorio");
        }
        if (empty($datos['nit'])) {
            throw new Exception("El NIT/RUC es obligatorio");
        }
        if (!empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El email no tiene un formato válido");
        }

        $sql = "UPDATE proveedores SET 
                nombre = ?, nit = ?, telefono = ?, email = ?, direccion = ?, 
                tipo_identificacion = ?, limite_credito = ?, fecha_actualizacion = NOW()
                WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ssssssdi", 
            $datos['nombre'],
            $datos['nit'],
            $datos['telefono'],
            $datos['email'],
            $datos['direccion'],
            $datos['tipo_identificacion'],
            $datos['limite_credito'],
            $datos['id']
        );

        if ($stmt->execute()) {
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Proveedor actualizado correctamente'
            ];
            header("Location: proveedores.php");
            exit();
        } else {
            throw new Exception("Error al actualizar el proveedor: " . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => $e->getMessage()
        ];
        // Mantener los datos del formulario para mostrarlos nuevamente
        $proveedor = $_POST;
    }
}
?>

<style>
    .card-editar-proveedor {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header-editar-proveedor {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
    }
    .required-field::after {
        content: " *";
        color: red;
    }
</style>

<div class="container py-4">
    <!-- Notificaciones -->
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['mensaje']['tipo']) ?> alert-dismissible fade show mt-3" role="alert">
            <i class="fas <?= $_SESSION['mensaje']['tipo'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($_SESSION['mensaje']['texto']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['mensaje']); ?>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-editar-proveedor">
                <div class="card-header card-header-editar-proveedor">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Proveedor</h4>
                        <a href="proveedores.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" id="formEditarProveedor" onsubmit="return validarFormulario()">
                        <input type="hidden" name="id" value="<?= $proveedor['id'] ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required-field">Nombre</label>
                                <input type="text" class="form-control" name="nombre" id="nombre" required maxlength="100" 
                                       value="<?= htmlspecialchars($proveedor['nombre']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required-field">NIT/RUC</label>
                                <input type="text" class="form-control" name="nit" id="nit" required maxlength="20"
                                       value="<?= htmlspecialchars($proveedor['nit']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo Identificación</label>
                                <select class="form-select" name="tipo_identificacion" id="tipo_identificacion">
                                    <option value="RUC" <?= $proveedor['tipo_identificacion'] == 'RUC' ? 'selected' : '' ?>>RUC</option>
                                    <option value="Cédula" <?= $proveedor['tipo_identificacion'] == 'Cédula' ? 'selected' : '' ?>>Cédula</option>
                                    <option value="Pasaporte" <?= $proveedor['tipo_identificacion'] == 'Pasaporte' ? 'selected' : '' ?>>Pasaporte</option>
                                    <option value="Otro" <?= $proveedor['tipo_identificacion'] == 'Otro' ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Límite de Crédito ($)</label>
                                <input type="number" class="form-control" name="limite_credito" id="limite_credito" step="0.01" min="0"
                                       value="<?= htmlspecialchars($proveedor['limite_credito']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono" id="telefono" maxlength="15"
                                       value="<?= htmlspecialchars($proveedor['telefono']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="email" maxlength="100"
                                       value="<?= htmlspecialchars($proveedor['email']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" name="direccion" id="direccion" rows="2" maxlength="255"><?= htmlspecialchars($proveedor['direccion']) ?></textarea>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="proveedores.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success">
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
    // Validación de formulario antes de enviar
    window.validarFormulario = function() {
        const nombre = document.getElementById('nombre').value.trim();
        const nit = document.getElementById('nit').value.trim();
        
        if (!nombre) {
            alert('Por favor ingrese el nombre del proveedor');
            document.getElementById('nombre').focus();
            return false;
        }
        
        if (!nit) {
            alert('Por favor ingrese el NIT/RUC del proveedor');
            document.getElementById('nit').focus();
            return false;
        }
        
        const email = document.getElementById('email').value.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            alert('Por favor ingrese un email válido');
            document.getElementById('email').focus();
            return false;
        }
        
        return true;
    };
});
</script>

<?php 
include_once 'includes/footer.php'; 
ob_end_flush();
?>