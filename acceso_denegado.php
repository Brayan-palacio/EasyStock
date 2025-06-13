<?php
// Iniciar sesión para poder verificar el rol del usuario
session_start();

// Configurar el código de respuesta HTTP 403 (Prohibido)
http_response_code(403);

// Incluir cabecera común
include_once('includes/header.php');
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-danger rounded-3">
                <div class="card-header bg-danger text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">
                            <i class="fas fa-ban me-2"></i>Acceso Denegado
                        </h3>
                        <span class="badge bg-light text-danger fs-6">
                            Error 403
                        </span>
                    </div>
                </div>
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-lock fa-5x text-danger mb-3"></i>
                        <h2 class="fw-bold text-danger">No tienes permisos suficientes</h2>
                    </div>
                    
                    <p class="lead mb-4">
                        Lo sentimos, pero no estás autorizado para acceder a esta página o realizar esta acción.
                    </p>
                    
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Rol actual:</strong> 
                        <?= isset($_SESSION['rol']) ? htmlspecialchars(ucfirst($_SESSION['rol'])) : 'Invitado' ?>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Volver atrás
                        </a>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home me-1"></i> Ir al inicio
                        </a>
                        <?php if (!isset($_SESSION['id_usuario'])): ?>
                            <a href="login.php" class="btn btn-success">
                                <i class="fas fa-sign-in-alt me-1"></i> Iniciar sesión
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-light text-center">
                    <small class="text-muted">
                        Si crees que esto es un error, por favor contacta al administrador del sistema.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Incluir pie de página común
include_once('includes/footer.php'); 
?>

<!-- Estilos adicionales para esta página -->
<style>
    .card {
        border-width: 2px;
    }
    .fa-lock {
        opacity: 0.8;
    }
</style>
<br>