<?php
session_start();

// Verificar sesión y permisos
if (!isset($_SESSION['id_usuario']) || empty($_SESSION['nombre']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Verificar si el usuario ya cerró el mensaje anteriormente
$mostrarMensaje = true;
if (isset($_COOKIE['mensajeBienvenidaOcultado']) && $_COOKIE['mensajeBienvenidaOcultado'] === 'true') {
    $mostrarMensaje = false;
}

include 'includes/header.php';
?>

<style>
    /* Mensaje moderno, accesible y con preferencia de color según sistema */
    .mensaje-bienvenida {
        background: linear-gradient(135deg, #f5f7fa 0%, #e4efe9 100%);
        border-left: 4px solid #2e7d32;
        color: #2e7d32;
        padding: 1.25rem 1.5rem;
        border-radius: 0.375rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        max-width: 1200px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
        margin: 50px auto;
        transition: all 0.3s ease;
    }

    .mensaje-bienvenida::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%232e7d32" fill-opacity="0.05" d="M0,0 L100,0 L100,100 L0,100 Z"></path></svg>');
        background-size: cover;
        z-index: 0;
    }

    .mensaje-contenido {
        position: relative;
        z-index: 1;
        flex-grow: 1;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .mensaje-icono {
        font-size: 1.5rem;
        color: #2e7d32;
        min-width: 24px;
    }

    .mensaje-texto {
        font-size: 1rem;
        font-weight: 500;
        line-height: 1.5;
        margin: 0;
    }

    .mensaje-boton {
        background: none;
        border: none;
        color: #2e7d32;
        font-size: 1.25rem;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: all 0.2s ease;
        position: relative;
        z-index: 1;
        margin-left: 1rem;
        min-width: 32px;
        min-height: 32px;
    }

    .mensaje-boton:hover, .mensaje-boton:focus {
        background-color: rgba(46, 125, 50, 0.1);
        transform: scale(1.1);
    }

    .mensaje-boton:focus {
        outline: 2px solid rgba(46, 125, 50, 0.5);
        outline-offset: 2px;
    }

    /* Modo oscuro */
    @media (prefers-color-scheme: dark) {
        .mensaje-bienvenida {
            background: linear-gradient(135deg, #1a2e22 0%, #0f1a13 100%);
            color: #a5d6a7;
            border-left-color: #81c784;
        }
        
        .mensaje-bienvenida::before {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%2381c784" fill-opacity="0.05" d="M0,0 L100,0 L100,100 L0,100 Z"></path></svg>');
        }
        
        .mensaje-icono, .mensaje-boton {
            color: #81c784;
        }
        
        .mensaje-boton:hover, .mensaje-boton:focus {
            background-color: rgba(129, 199, 132, 0.1);
        }
    }

    /* Responsive design mejorado */
    @media (max-width: 768px) {
        .mensaje-bienvenida {
            flex-direction: row;
            align-items: center;
            padding: 1rem;
            margin: 20px 15px;
        }
        
        .mensaje-contenido {
            margin-bottom: 0;
            flex-wrap: wrap;
        }
        
        .mensaje-boton {
            align-self: center;
            margin-left: auto;
        }
    }

    @media (max-width: 480px) {
        .mensaje-texto {
            font-size: 0.9rem;
        }
        
        .mensaje-icono {
            font-size: 1.2rem;
        }
    }
</style>

<!-- Contenido principal -->
<?php if ($mostrarMensaje): ?>
<div id="mensajeBienvenida" class="mensaje-bienvenida" role="alert" aria-live="polite" style="margin: 20px;">
    <div class="mensaje-contenido">
        <div class="mensaje-icono" aria-hidden="true">
            <i class="fas fa-info-circle"></i>
        </div>
        <div class="mensaje-texto">
            Bienvenido/a <strong><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong> al Sistema de Inventario de <strong>EasyStock</strong>
        </div>
    </div>
    <button class="mensaje-boton" onclick="cerrarMensaje()" aria-label="Cerrar mensaje de bienvenida">
        <i class="fas fa-times" aria-hidden="true"></i>
    </button>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function cerrarMensaje() {
        const mensaje = document.getElementById('mensajeBienvenida');
        if (mensaje) {
            // Animación de desvanecimiento
            mensaje.style.transition = 'opacity 0.3s ease';
            mensaje.style.opacity = '0';
            
            // Esperar a que termine la animación para ocultar
            setTimeout(() => {
                mensaje.style.display = 'none';
                
                // Establecer cookie para recordar la preferencia (30 días)
                document.cookie = "mensajeBienvenidaOcultado=true; max-age=" + (60 * 60 * 24 * 30) + "; path=/; SameSite=Lax";
            }, 300);
        }
    }

    // Cerrar con la tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('mensajeBienvenida')) {
            cerrarMensaje();
        }
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

<?php include 'includes/footer.php'; ?>