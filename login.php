<?php
session_start();

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Configuración de seguridad de headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");

require 'config/conexion.php';
require 'config/config.php';

// Si el usuario ya está logueado, redirigir al inicio
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestión integral para EasyStock">
    <meta name="author" content="EasyStock">
    <title>Acceso al Sistema - <?= htmlspecialchars($nombreSistema) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Google Fonts - Poppins (más profesional) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" href="img/EasyStock-barra.png" type="image/png" />
    
    <style>
        :root {
            --primary: #1a3a2f;
            --primary-light: #2a5a46;
            --secondary: #d4af37;
            --secondary-light: #e8c96a;
            --accent: #4e8cff;
            --light-bg: #f8fafc;
            --dark-text: #1e293b;
            --gray-text: #64748b;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4AkEEjofV5ZfJgAAAQdJREFUaN7t2bENwjAQRdEHqWgZgQlYgQlYgQlYgQlYgQlYgQlYgQlYgQmcgBVS0CQkIY4d5/5Wr3L8dO3YVw4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADBdVVVtksckx5HnH5PcJ9lXVe3HnK9JXpK8J3lL8pjkOsnFyPNXSc6T7JLsk5wluUlymOQ4yXmSq5HnL5NcJDlLcpjkJMlVkvMk+yS7JGdJrkecv0xykOQ4yWGS4yQnSY6SHCQ5SnKS5HrE+askF0nOkxwkOU5ymOQkyVGSgyRHSU6TXI84f53kMsl5koMkR0kOkpwkeZ/kI8lHkvckb0leR5x/SfKa5CXJc5KnJI9JHpI8JHlI8pjkOclLktckb0ne/gB/5Bf5pV/kFwAAAAAAAADT9QkZQBVQZ0j8JAAAAABJRU5ErkJggg==');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 2rem;
        }
        
        .login-box {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            text-align: center;
            border-top: 4px solid var(--secondary);
            backdrop-filter: blur(5px);
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .brand-logo {
            width: 140px;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }
        
        .brand-logo:hover {
            transform: scale(1.05);
        }
        
        .login-title {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            letter-spacing: -0.5px;
        }
        
        .login-subtitle {
            color: var(--gray-text);
            font-size: 0.95rem;
            margin-bottom: 2rem;
            font-weight: 400;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            font-size: 0.95rem;
            height: auto;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }
        
        .input-group-text {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-right: none;
            color: var(--primary);
            padding: 0.75rem 1rem;
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .input-group .form-control:focus {
            border-left: none;
        }
        
        .btn-login {
            border-radius: 8px;
            background-color: var(--primary);
            border: none;
            color: white;
            padding: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            margin-top: 0.5rem;
            font-size: 0.95rem;
            text-transform: uppercase;
        }
        
        .btn-login:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 58, 47, 0.2);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .forgot-link {
            color: var(--gray-text);
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
            transition: color 0.2s ease;
        }
        
        .forgot-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }
        
        .footer-text {
            margin-top: 2rem;
            color: var(--gray-text);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--gray-text);
            font-size: 0.85rem;
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
            margin: 0 10px;
        }
        
        /* Alertas mejoradas */
        .alert {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
        }
        
        /* Efecto de carga */
        .btn-loading .spinner-border {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .login-box {
                padding: 1.5rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <img src="img/logo_easystock.png" alt="EasyStock" class="brand-logo">
            
            <p class="login-subtitle">Sistema de Gestión de Inventarios</p>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form id="loginForm" action="controllers/procesar_login.php" method="POST" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                        <input type="text" name="usuario" class="form-control" placeholder="Usuario" required autofocus maxlength="50">
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="contraseña" class="form-control" placeholder="Contraseña" required maxlength="255">
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-login" id="loginButton">
                        <i class="fas fa-sign-in-alt me-2"></i> INGRESAR
                    </button>
                </div>
                
                <div class="text-center mt-3">
                    <a href="recuperar-contraseña.php" class="forgot-link">
                        <i class="fas fa-key me-1"></i> ¿Olvidaste tu contraseña?
                    </a>
                </div>
                
                <div class="footer-text">
                    <p>© <?= date('Y') ?> <?= htmlspecialchars($nombreSistema) ?>. Todos los derechos reservados.</p>
                    <p class="small text-muted">v2.1.0</p>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <!-- Validación del formulario -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            
            loginForm.addEventListener('submit', function(e) {
                // Validación simple del cliente
                const usuario = document.querySelector('input[name="usuario"]');
                const contraseña = document.querySelector('input[name="contraseña"]');
                
                if (!usuario.value.trim() || !contraseña.value.trim()) {
                    e.preventDefault();
                    if (!usuario.value.trim()) {
                        usuario.focus();
                    } else {
                        contraseña.focus();
                    }
                    return false;
                }
                
                // Mostrar estado de carga
                loginButton.disabled = true;
                loginButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Ingresando...';
                loginButton.classList.add('btn-loading');
            });
            
            // Limpiar errores al empezar a escribir
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (document.querySelector('.alert')) {
                        document.querySelector('.alert').remove();
                    }
                });
            });
        });
    </script>
</body>
</html>