<?php
function obtenerConfiguracion($conexion, $clave) {
    $stmt = $conexion->prepare("SELECT valor FROM configuracion WHERE clave = ?");
    $stmt->bind_param('s', $clave);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['valor'] ?? 15; // Valor por defecto 15
}

function obtenerDatosEmpresa($conexion) {
    $datos = [];
    $claves = [
        'empresa_nombre', 
        'empresa_ruc', 
        'empresa_direccion', 
        'empresa_telefono', 
        'empresa_email',
        'empresa_logo' // Si guardas el logo en la configuración
    ];
    
    foreach ($claves as $clave) {
        $stmt = $conexion->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $stmt->bind_param('s', $clave);
        $stmt->execute();
        $result = $stmt->get_result();
        $datos[$clave] = $result->fetch_assoc()['valor'] ?? '';
    }
    
    return $datos;
}

?>
