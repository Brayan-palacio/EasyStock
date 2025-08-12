<?php
session_start();
require 'config/conexion.php';
require 'config/config.php';

// Configuración de seguridad de headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Token de seguridad inválido. Por favor, intente nuevamente.";
        header('Location: recuperar-contraseña.php');
        exit;
    }

    // Validar y sanitizar el email
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Por favor ingrese un correo electrónico válido.";
        header('Location: recuperar-contraseña.php');
        exit;
    }

    // Verificar si el email existe en la base de datos (usando consultas preparadas)
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            // Generar token único para recuperación
            $token = bin2hex(random_bytes(50));
            $expiracion = date("Y-m-d H:i:s", strtotime('+1 hour'));

            // Guardar token en la base de datos
            $stmt = $pdo->prepare("UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE id = ?");
            $stmt->execute([$token, $expiracion, $usuario['id']]);

            // Enviar email con el enlace de recuperación
            $enlace = "https://" . $_SERVER['HTTP_HOST'] . "/restablecer-contraseña.php?token=$token";
            
            // Aquí iría el código para enviar el email (usar PHPMailer o similar)
            // mail($email, "Recuperación de contraseña", "Hola {$usuario['nombre']},\n\nPara restablecer tu contraseña, haz clic en el siguiente enlace:\n\n$enlace\n\nEl enlace expirará en 1 hora.");
            
            $_SESSION['exito'] = "Se ha enviado un enlace de recuperación a tu correo electrónico. Por favor revisa tu bandeja de entrada (y la carpeta de spam).";
            header('Location: recuperar-contraseña.php');
            exit;
        } else {
            // No revelar si el email existe o no (seguridad)
            $_SESSION['exito'] = "Si el correo existe en nuestro sistema, recibirás un enlace de recuperación.";
            header('Location: recuperar-contraseña.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error en recuperación de contraseña: " . $e->getMessage());
        $_SESSION['error'] = "Ocurrió un error al procesar tu solicitud. Por favor intenta más tarde.";
        header('Location: recuperar-contraseña.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recuperación de contraseña para EasyStock">
    <meta name="author" content="EasyStock">
    <title>Recuperar Contraseña - <?= htmlspecialchars($nombreSistema) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Google Fonts - Poppins -->
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
        
        .btn-primary {
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
        
        .btn-primary:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 58, 47, 0.2);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .footer-text {
            margin-top: 2rem;
            color: var(--gray-text);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        /* Alertas mejoradas */
        .alert {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
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
            
            <h1 class="login-title">Recuperar Contraseña</h1>
            <p class="login-subtitle">Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['exito'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['exito'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['exito']); ?>
            <?php endif; ?>
            
            <form id="recoveryForm" action="recuperar-contraseña.php" method="POST" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="Correo electrónico" required autofocus>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary" id="recoveryButton">
                        <i class="fas fa-paper-plane me-2"></i> ENVIAR ENLACE
                    </button>
                </div>
                
                <div class="text-center mt-3">
                    <a href="index.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Volver al inicio de sesión
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
            const recoveryForm = document.getElementById('recoveryForm');
            const recoveryButton = document.getElementById('recoveryButton');
            
            recoveryForm.addEventListener('submit', function(e) {
                // Validación simple del cliente
                const email = document.querySelector('input[name="email"]');
                
                if (!email.value.trim()) {
                    e.preventDefault();
                    email.focus();
                    return false;
                }
                
                // Mostrar estado de carga
                recoveryButton.disabled = true;
                recoveryButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Enviando...';
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