<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Validar existencia de archivos de configuración
$config_files = ['config/conexion.php', 'config/config.php'];
foreach ($config_files as $file) {
    if (!file_exists($file)) {
        error_log("Error crítico: Archivo $file no encontrado");
        die('Error del sistema. Contacte al administrador.');
    }
}

include 'config/conexion.php';
require 'config/config.php';

// Verificar sesión de forma segura
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

// Validar y sanitizar datos de usuario
$id_usuario = (int)$_SESSION['id_usuario']; // Force integer to prevent SQL injection

// Consulta segura con prepared statements
$query = "SELECT nombre, usuario, rol_usuario, imagen FROM usuarios WHERE id = ? AND estado = 'Activo'";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Usuario no existe o está inactivo
    session_destroy();
    header("Location: login.php");
    exit();
}

$usuario = $result->fetch_assoc();

// Sanitizar outputs
$nombre_usuario = htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8');
$rol_usuario = htmlspecialchars($usuario['rol_usuario'], ENT_QUOTES, 'UTF-8');
$imagen_usuario = 'assets/img/usuarios/' . basename($usuario['imagen']); // Previene path traversal

// Función de permisos
function tienePermiso($rolesPermitidos) {
    if (!isset($_SESSION['rol_usuario'])) return false;
    return in_array($_SESSION['rol_usuario'], (array)$rolesPermitidos);
}

$esAdmin = tienePermiso('Administrador');
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($tituloPagina) ? htmlspecialchars($tituloPagina) : "EasyStock"; ?></title>
    <link rel="icon" href="img/EasyStock-barra.png" type="image/png" />
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS propio (externalizado) -->
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar Overlay (para móviles) -->
    <div class="sidebar-overlay"></div>
    
    <!-- Sidebar Profesional -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="img/EasyStock-barra1-removebg-preview.png" alt="EasyStock" class="brand-logo">
            <h2>EASYSTOCK</h2>
        </div>
        
        <div class="sidebar-content pt-2">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <!-- Accesos -->
                <?php if($esAdmin): ?>
                <li class="nav-item">
                    <a href="grupos.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['grupos.php', 'administrar_usuarios.php'])) ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Accesos">
                        <i class="fas fa-user-lock"></i>
                        <span>Accesos</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if(tienePermiso(['Administrador', 'Special'])): ?>
                <!-- Categorías -->
                <li class="nav-item">
                    <a href="categorias.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'categorias.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Categorías">
                        <i class="fas fa-tags"></i>
                        <span>Categorías</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Productos -->
                <li class="nav-item">
                    <a href="productos.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'productos.php') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Productos">
                        <i class="fas fa-box-open"></i>
                        <span>Productos</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="agregar_inventario.php" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['agregar_inventario.php', 'ajustes_inventario.php', 'productos_bajos.php', 'informe_inventario.php', 'informe_movimientos.php', 'kardex.php']) ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Inventario">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Inventario</span>
                    </a>
                </li>

                <?php if(tienePermiso(['Administrador', 'Supervisor'])): ?>
                <!-- Media -->
                <li class="nav-item">
                    <a href="media.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'media.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Multimedia">
                        <i class="fas fa-photo-video"></i>
                        <span>Multimedia</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Ventas -->
                <li class="nav-item">
                    <a href="ventas.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['ventas.php', 'nueva_venta.php', 'listar_cotizaciones.php'])) ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Ventas">
                        <i class="fas fa-cash-register"></i>
                        <span>Ventas</span>
                    </a>
                </li>
       
                <li class="nav-item">
                    <a href="compras.php" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['compras.php', 'nueva_compra.php', 'proveedores.php', 'agregar_proveedor.php']) ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Compras/Proveedores">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Compras/Proveedores</span>
                    </a>
                </li>

                <!-- Clientes -->
                <li class="nav-item">
                    <a href="clientes.php" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['clientes.php', 'agregar_cliente.php', 'vehiculos.php']) ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Clientes">
                        <i class="fas fa-users"></i>
                        <span>Clientes</span>
                    </a>
                </li>
                
                <?php if(tienePermiso(['Administrador', 'Usuario', 'Supervisor'])): ?>
                    <div class="menu-oculto">
                    <li class="nav-item">
                        <a href="vehiculos.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'vehiculos.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Vehículos">
                            <i class="fas fa-car"></i>
                            <span>Vehículos</span>
                        </a>
                    </li>
                    </div>
                <?php endif; ?>
                
                <!-- Taller -->
                <div class="menu-oculto">
                <li class="nav-item">
                    <a class="nav-link collapsed" data-bs-toggle="collapse" href="#tallerMenu" aria-expanded="<?php echo (in_array(basename($_SERVER['PHP_SELF']), ['ordenes.php', 'nueva_orden.php']) ? 'true' : 'false'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Taller">
                        <i class="fas fa-tools"></i>
                        <span>Taller</span>
                        <i class="fas fa-angle-down arrow ms-auto"></i>
                    </a>
                    <div class="collapse <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['ordenes.php', 'nueva_orden.php']) ? 'show' : ''); ?>" id="tallerMenu">
                        <ul class="submenu nav flex-column">
                            <li class="nav-item">
                                <a href="ordenes.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'ordenes.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Órdenes">
                                    <i class="fas fa-clipboard-check"></i>
                                    <span>Órdenes</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="agregar_orden.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'nueva_orden.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Nueva Orden">
                                    <i class="fas fa-plus-square"></i>
                                    <span>Nueva Orden</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="servicios.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'servicios.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Servicios">
                                    <i class="fas fa-concierge-bell"></i>
                                    <span>Servicios</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                </div>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header Profesional -->
        <header class="main-header">
            <div class="header-left">
                <button class="btn btn-sm" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="date-time d-none d-md-flex">
                    <span id="currentDate" class="me-2"></span>
                    <span id="currentTime" class="badge"></span>
                </div>
            </div>
            
            <div class="header-right d-flex align-items-center">
                <!-- Notificaciones -->
                <div class="dropdown me-3">
                    <a class="position-relative" href="#" role="button" id="dropdownNotificaciones" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell fs-5 text-muted"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="contadorNotificaciones">
                            0
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end animate-fade-in p-0" style="width: 300px; max-height: 350px; overflow-y: auto;" aria-labelledby="dropdownNotificaciones">
                        <li><h6 class="dropdown-header bg-light py-2">Notificaciones</h6></li>
                        <div id="listaNotificaciones">
                            <!-- Las notificaciones se cargarán aquí dinámicamente -->
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary spinner-border-sm" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </div>
                        </div>
                        <li><hr class="dropdown-divider m-0"></li>
                        <li><a class="dropdown-item text-center text-primary py-2" href="notificaciones.php">Ver todas</a></li>
                    </ul>
                </div>
                
                <!-- Usuario Premium -->
                <div class="dropdown">
                    <div class="user-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <!-- Contenedor de avatar mejorado -->
                        <div class="avatar-container position-relative">
                            <img src="<?= htmlspecialchars($imagen_usuario) ?>" 
                                 alt="Avatar de <?= htmlspecialchars($nombre_usuario) ?>"
                                 class="user-avatar rounded-circle"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%236c757d\'><path d=\'M12 2a5 5 0 1 0 5 5 5 5 0 0 0-5-5zm0 8a3 3 0 1 1 3-3 3 3 0 0 1-3 3zm9 11v-1a7 7 0 0 0-7-7h-4a7 7 0 0 0-7 7v1h2v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1z\'/></svg>'; this.classList.add('bg-light')">
                            <!-- Indicador Premium -->
                            <?php if(tienePermiso(['Administrador'])): ?>
                            <span class="position-absolute bottom-0 end-0 badge bg-premium">
                                <i class="fas fa-crown"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="user-info d-none d-lg-block">
                            <span class="user-name"><?= htmlspecialchars($nombre_usuario) ?></span>
                            <span class="user-role small text-muted"><?= htmlspecialchars($rol_usuario) ?></span>
                        </div>
                        <i class="fas fa-chevron-down ms-2 d-none d-lg-block"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end animate__animated animate__fadeIn">
                        <li><h6 class="dropdown-header">Hola, <?= explode(' ', $nombre_usuario)[0] ?></h6></li>
                        <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-circle me-2"></i> Mi Perfil</a></li>
                        <li><a class="dropdown-item" href="configuracion.php"><i class="fas fa-cog me-2"></i> Configuración</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="controllers/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <!-- Contenido Principal -->
         <script>
$(document).ready(function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover'
        });
    });

    // En pantallas grandes, el botón de toggle está oculto
    // Solo funciona en dispositivos móviles (<= 992px)
    $('#sidebarToggle').click(function() {
        $('.sidebar').toggleClass('show');
        $('.sidebar-overlay').toggleClass('show');
        $('body').toggleClass('overflow-hidden');
    });
    
    // Cargar estado del sidebar desde localStorage
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        $('.sidebar').addClass('collapsed');
    }
    
    // Manejar el comportamiento de los menús desplegables
    $('.nav-link[data-bs-toggle="collapse"]').click(function(e) {
        // Evitar el comportamiento predeterminado
        e.preventDefault();
        
        // Obtener el elemento del menú actual
        const currentMenu = $(this).attr('href');
        const isCurrentMenuOpen = $(currentMenu).hasClass('show');
        
        // Cerrar todos los demás menús
        $('.collapse').not(currentMenu).removeClass('show');
        
        // Actualizar las flechas de todos los menús
        $('.nav-link[data-bs-toggle="collapse"]').attr('aria-expanded', 'false');
        $('.nav-link[data-bs-toggle="collapse"] .arrow').removeClass('fa-angle-up').addClass('fa-angle-down');
        
        // Alternar el menú actual
        if (!isCurrentMenuOpen) {
            $(currentMenu).addClass('show');
            $(this).attr('aria-expanded', 'true');
            $(this).find('.arrow').removeClass('fa-angle-down').addClass('fa-angle-up');
        } else {
            $(currentMenu).removeClass('show');
            $(this).attr('aria-expanded', 'false');
            $(this).find('.arrow').removeClass('fa-angle-up').addClass('fa-angle-down');
        }
        
        // Guardar estado en localStorage
        $('.collapse').each(function() {
            localStorage.setItem(this.id, $(this).hasClass('show') ? 'show' : 'hide');
        });
    });
    
    // Restaurar el estado de los menús desde localStorage
    $('.collapse').each(function() {
        const state = localStorage.getItem(this.id);
        if (state === 'show') {
            $(this).addClass('show');
            $('[href="#' + this.id + '"]').attr('aria-expanded', 'true')
                .find('.arrow').removeClass('fa-angle-down').addClass('fa-angle-up');
        }
    });
    
    // Manejar el overlay en móviles
    $('.sidebar-overlay').click(function() {
        $('.sidebar').removeClass('show');
        $('.sidebar-overlay').removeClass('show');
        $('body').removeClass('overflow-hidden');
    });
    
    // Smooth scroll para el sidebar
    function initSlimScroll() {
        if (typeof $.fn.slimScroll !== 'undefined') {
            $('.sidebar-content').slimScroll({
                height: 'calc(100vh - var(--header-height))',
                position: 'right',
                size: "4px",
                color: 'rgba(255,255,255,0.2)',
                wheelStep: 10,
                touchScrollStep: 100
            });
        } else {
            console.warn('slimScroll no está disponible');
            // Opcional: Usar CSS nativo como fallback
            $('.sidebar-content').css('overflow-y', 'auto');
        }
    }
    
    // Actualizar fecha y hora
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        $('#currentDate').text(now.toLocaleDateString('es-ES', options));
        $('#currentTime').text(now.toLocaleTimeString('es-ES'));
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Efecto hover en tarjetas
    $('.card').hover(
        function() {
            $(this).addClass('shadow-lg');
        },
        function() {
            $(this).removeClass('shadow-lg');
        }
    );
    
    // Notificaciones
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownNotificaciones = document.getElementById('dropdownNotificaciones');
        const listaNotificaciones = document.getElementById('listaNotificaciones');
        const contadorNotificaciones = document.getElementById('contadorNotificaciones');

        // Función para cargar notificaciones
        function cargarNotificaciones() {
            fetch('get_notificaciones.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        listaNotificaciones.innerHTML = `
                            <div class="dropdown-item text-danger">${data.error}</div>
                        `;
                        return;
                    }

                    contadorNotificaciones.textContent = data.sinLeer > 0 ? data.sinLeer : '';

                    if (data.notificaciones.length === 0) {
                        listaNotificaciones.innerHTML = `
                            <div class="dropdown-item text-muted">No hay notificaciones</div>
                        `;
                        return;
                    }

                    let html = '';
                    data.notificaciones.forEach(notif => {
                        html += `
                            <a href="${notif.url || '#'}" class="dropdown-item notification-item ${!notif.leida ? 'unread' : ''}" 
                               data-id="${notif.id}" onclick="marcarComoLeida(event, ${notif.id})">
                                <div class="d-flex justify-content-between">
                                    <strong>${notif.titulo}</strong>
                                    <small class="text-muted">${notif.fecha}</small>
                                </div>
                                <div class="text-truncate">${notif.mensaje}</div>
                            </a>
                        `;
                    });
                    listaNotificaciones.innerHTML = html;
                });
        }

        // Marcar como leída al hacer clic
        window.marcarComoLeida = function(e, id) {
            e.preventDefault();
            const url = e.currentTarget.getAttribute('href');
            
            fetch('marcar_leida.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        e.currentTarget.classList.remove('unread');
                        contadorNotificaciones.textContent = data.nuevoContador > 0 ? data.nuevoContador : '';
                        if (url && url !== '#') {
                            window.location.href = url;
                        }
                    }
                });
        };

        // Cargar notificaciones al abrir el dropdown
        dropdownNotificaciones.addEventListener('show.bs.dropdown', cargarNotificaciones);

        // Actualizar contador periódicamente (cada 2 minutos)
        setInterval(() => {
            if (!document.querySelector('.dropdown-menu.show')) {
                fetch('get_notificaciones.php')
                    .then(response => response.json())
                    .then(data => {
                        contadorNotificaciones.textContent = data.sinLeer > 0 ? data.sinLeer : '';
                    });
            }
        }, 120000);
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>