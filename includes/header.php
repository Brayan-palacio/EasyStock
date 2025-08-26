<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'config/conexion.php';
require 'config/config.php';

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario
$id_usuario = $_SESSION['id_usuario'];

// Consulta para obtener los datos del usuario
$query = "SELECT nombre, usuario, rol_usuario, imagen FROM usuarios WHERE id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

// Asignar variables para la vista
$nombre_usuario = $usuario['nombre'];
$rol_usuario = $usuario['rol_usuario'];
$imagen_usuario = 'assets/img/usuarios/' . $usuario['imagen']; // Ruta completa

// Función para verificar permisos
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
    <title><?php echo isset($tituloPagina) ? $tituloPagina : "EasyStock"; ?></title>
    <link rel="icon" href="img/EasyStock-barra.png" type="image/png" />
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts - Inter + JetBrains Mono -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Glightbox para multimedia -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/glightbox/3.2.0/css/glightbox.min.css">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>

    <style>
        :root {
            --primary: #1a3a2f;
            --primary-light: #2a5a46;
            --secondary: #d4af37;
            --secondary-light: #e8c96a;
            --accent: #4e8cff;
            --light-bg: #f8fafc;
            --dark-bg: #1e293b;
            --sidebar-width: 240px;
            --header-height: 60px;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: #333;
            overflow-x: hidden;
            font-weight: 400;
            line-height: 1.6;
        }
        
        /* Sidebar Profesional */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, var(--primary) 0%, var(--dark-bg) 100%);
            color: white;
            transition: var(--transition);
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .sidebar-brand {
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 0 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: var(--transition);
        }
        
        .sidebar-brand h2 {
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
            white-space: nowrap;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }
        
        .sidebar-brand img {
            height: 30px;
            margin-right: 10px;
            filter: brightness(1) invert(0);
            transition: var(--transition);
        }
        
        .sidebar-content {
            padding: 15px 0;
            height: calc(100vh - var(--header-height));
            overflow-y: auto;
        }
        
        .sidebar-content::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.85);
            border-radius: 6px;
            margin: 3px 10px;
            padding: 10px 15px;
            transition: var(--transition);
            font-weight: 500;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            font-size: 0.9rem;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            transform: translateX(0);
        }
        
        .nav-link.active {
            background: linear-gradient(90deg, rgba(212, 175, 55, 0.15) 0%, transparent 100%);
            color: var(--secondary-light);
            font-weight: 600;
        }
        
        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--secondary);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .nav-link.active i {
            color: var(--secondary);
            transform: scale(1.05);
        }
        
        .nav-link .arrow {
            margin-left: auto;
            font-size: 0.75rem;
            transition: transform 0.3s ease;
        }
        
        .nav-link[aria-expanded="true"] .arrow {
            transform: rotate(180deg);
        }
        
        .submenu {
            padding-left: 10px;
        }
        
        .submenu .nav-link {
            margin: 2px 0;
            padding: 8px 12px 8px 38px;
            font-size: 0.85rem;
            background: transparent;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .submenu .nav-link:hover, .submenu .nav-link.active {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }
        
        .submenu .nav-link.active {
            color: var(--secondary-light);
        }

        .submenu {
            transition: all 0.3s ease;
        }

        .collapse:not(.show) {
            display: none;
        }

        .collapsing {
            height: 0;
            overflow: hidden;
            transition: height 0.35s ease;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding-top: var(--header-height);
            min-height: 100vh;
            transition: var(--transition);
            background-color: var(--light-bg);
        }
        
        /* Header Profesional */
        .main-header {
            height: var(--header-height);
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            z-index: 999;
            background: white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04); /* Sombra más suave */
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            transition: var(--transition);
        }
        
        .header-left {
            display: flex;
            align-items: center;
        }
        
        #sidebarToggle {
            display: none;
        }
        
        .date-time {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
        }
        
        #currentDate {
            color: #555;
            font-weight: 500;
        }
        
        #currentTime {
            background: var(--primary);
            padding: 4px 8px;
            border-radius: 20px;
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
            margin-left: 8px;
        }
        
        /* User Dropdown Premium */
        .user-dropdown {
            display: flex;
            align-items: center;
            cursor: pointer;
            position: relative;
            padding: 6px 10px;
            border-radius: 50px;
            transition: var(--transition);
        }

        .user-dropdown:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 2px solid var(--secondary);
            box-shadow: 0 0 0 2px var(--light-bg);
            transition: var(--transition);
        }

        .user-dropdown:hover .user-avatar {
            transform: scale(1.05);
            box-shadow: 0 0 0 3px var(--secondary-light);
        }

        /* Notificaciones */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: bold;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1100;
                width: var(--sidebar-width);
            }
            
            .sidebar.show {
                transform: translateX(0);
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.2);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-header {
                left: 0;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1050;
                opacity: 0;
                visibility: hidden;
                transition: var(--transition);
            }
            
            .sidebar-overlay.show {
                opacity: 1;
                visibility: visible;
            }

            /* Mostramos el botón de toggle solo en móviles */
            #sidebarToggle {
                display: block;
                background: transparent;
                border: none;
                color: var(--primary);
                font-size: 1.2rem;
                margin-right: 15px;
                transition: var(--transition);
            }
            
            #sidebarToggle:hover {
                color: var(--secondary);
                transform: scale(1.1);
            }
        }
        
        /* Animaciones */
        .animate-fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        }
        
        /* Estilo para elementos deshabilitados */
        .nav-item.disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Indicador visual para administradores */
        .user-role.admin {
            color: var(--secondary) !important;
            font-weight: 600;
        }
        
        .notification-item.unread {
            background-color: #f8f9fa;
        }

        .notification-item:hover {
            background-color: #e9ecef;
        }
        
        .menu-oculto {
            display: none;
        }
        
        .avatar-container {
            width: 36px;
            height: 36px;
            display: inline-block;
            position: relative;
        }

        .user-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .avatar-icon {
            width: 100%;
            height: 100%;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #dee2e6;
        }

        .badge.bg-premium {
            background: linear-gradient(135deg, #ffd700 0%, #ffbf00 100%);
            color: #000;
            padding: 0.15em 0.35em;
            font-size: 0.6rem;
            border: 1px solid #fff;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-name {
            font-weight: 500;
            display: block;
            line-height: 1.2;
            font-size: 0.9rem;
        }

        .user-role {
            display: block;
            line-height: 1.2;
            font-size: 0.7rem;
        }
        
        /* Tooltips para barra colapsada */
        .tooltip-inner {
            background-color: var(--primary);
            font-size: 0.75rem;
        }
        
        .bs-tooltip-auto[data-popper-placement^=right] .tooltip-arrow::before, 
        .bs-tooltip-end .tooltip-arrow::before {
            border-right-color: var(--primary);
        }
    </style>
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