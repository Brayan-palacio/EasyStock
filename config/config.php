<?php
// Obtener configuración
function getConfig($clave, $default = '') {
    global $conexion;
    $stmt = $conexion->prepare("SELECT valor FROM configuracion WHERE clave = ?");
    $stmt->bind_param('s', $clave);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_row()[0] : $default;
}

// Configuración global
$nombreSistema = getConfig('nombre_sistema', 'Taller El Mamey');
$logo = getConfig('logo', 'logo_default.png');
?>