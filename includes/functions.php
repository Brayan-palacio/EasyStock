<?php
// functions.php mejorado
function formatoMoneda($valor, $moneda = 'USD') {
    return '$ ' . number_format($valor, 2, '.', ',');
}

function sanitizarInput($input) {
    if (is_array($input)) {
        return array_map('sanitizarInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redireccionar($url, $mensaje = null, $tipo = 'success') {
    if ($mensaje) {
        $_SESSION['flash_message'] = [
            'texto' => $mensaje,
            'tipo' => $tipo
        ];
    }
    header("Location: $url");
    exit;
}

function obtenerFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Otras funciones útiles...
?>