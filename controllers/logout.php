<?php
// controllers/logout.php
session_start();

// Validar que realmente haya una sesión activa
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Limpiar todas las variables de sesión
    $_SESSION = array();
    
    // Destruir la cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir la sesión
    session_destroy();
}

// Redirección segura
header('Location: ../login.php?msg=logout_success');
exit();
?>