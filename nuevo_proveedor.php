<?php
session_start();
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Nuevo Proveedor - EasyStock';
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

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $datos = [
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

        // Insertar nuevo proveedor
        $sql = "INSERT INTO proveedores 
                (nombre, nit, telefono, email, direccion, tipo_identificacion, limite_credito, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ssssssd", ...array_values($datos));

        if ($stmt->execute()) {
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Proveedor guardado correctamente'
            ];
            header("Location: proveedores.php");
            exit();
        } else {
            throw new Exception("Error al guardar el proveedor: " . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['mensaje'] = [
            'tipo' => 'danger',
            'texto' => $e->getMessage()
        ];
        // Mantener los datos del formulario para mostrarlos nuevamente
        $proveedorDatos = $_POST;
    }
}
?>

<style>
    .card-nuevo-proveedor {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    .card-header-nuevo-proveedor {
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5e4f 100%);
        color: white;
    }
    .required-field::after {
        content: " *";
        color: red;
    }
</style>

<div class="container py-4">
    <?php include 'compras_proveedores_navbar.php'; ?>
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
            <div class="card card-nuevo-proveedor">
                <div class="card-header card-header-nuevo-proveedor">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-truck me-2"></i>Nuevo Proveedor</h4>
                        <a href="proveedores.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" id="formProveedor" onsubmit="return validarFormulario()">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required-field">Nombre</label>
                                <input type="text" class="form-control" name="nombre" id="nombre" required maxlength="100" 
                                       value="<?= isset($proveedorDatos['nombre']) ? htmlspecialchars($proveedorDatos['nombre']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required-field">NIT/RUC</label>
                                <input type="text" class="form-control" name="nit" id="nit" required maxlength="20"
                                       value="<?= isset($proveedorDatos['nit']) ? htmlspecialchars($proveedorDatos['nit']) : '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo Identificación</label>
                                <select class="form-select" name="tipo_identificacion" id="tipo_identificacion">
                                    <option value="RUC" <?= (isset($proveedorDatos['tipo_identificacion']) && $proveedorDatos['tipo_identificacion'] == 'RUC') ? 'selected' : '' ?>>RUC</option>
                                    <option value="Cédula" <?= (isset($proveedorDatos['tipo_identificacion']) && $proveedorDatos['tipo_identificacion'] == 'Cédula') ? 'selected' : '' ?>>Cédula</option>
                                    <option value="Pasaporte" <?= (isset($proveedorDatos['tipo_identificacion']) && $proveedorDatos['tipo_identificacion'] == 'Pasaporte') ? 'selected' : '' ?>>Pasaporte</option>
                                    <option value="Otro" <?= (isset($proveedorDatos['tipo_identificacion']) && $proveedorDatos['tipo_identificacion'] == 'Otro') ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Límite de Crédito ($)</label>
                                <input type="number" class="form-control" name="limite_credito" id="limite_credito" step="0.01" min="0"
                                       value="<?= isset($proveedorDatos['limite_credito']) ? htmlspecialchars($proveedorDatos['limite_credito']) : '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono" id="telefono" maxlength="15"
                                       value="<?= isset($proveedorDatos['telefono']) ? htmlspecialchars($proveedorDatos['telefono']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="email" maxlength="100"
                                       value="<?= isset($proveedorDatos['email']) ? htmlspecialchars($proveedorDatos['email']) : '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" name="direccion" id="direccion" rows="2" maxlength="255"><?= isset($proveedorDatos['direccion']) ? htmlspecialchars($proveedorDatos['direccion']) : '' ?></textarea>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="proveedores.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Guardar Proveedor
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