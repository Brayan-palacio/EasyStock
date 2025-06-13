<?php
session_start();

// Configuración de seguridad de headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {  // Corregido paréntesis faltante
    $_SESSION['error'] = "Debe iniciar sesión para acceder a esta función";
    header("Location: ../../login.php");
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Token de seguridad inválido";
    header("Location: ../../cotizaciones.php");
    exit;
}

include '../../config/conexion.php'; 
date_default_timezone_set('America/Bogota');

// Validar datos recibidos
$cliente = trim($_POST['cliente'] ?? '');
$contacto = trim($_POST['contacto'] ?? '');
$notas = trim($_POST['notas'] ?? '');
$productos = $_POST['productos'] ?? [];
$cantidades = $_POST['cantidades'] ?? [];
$precios = $_POST['precios'] ?? [];

// Validaciones básicas
if (empty($cliente)) {
    $_SESSION['error'] = "El nombre del cliente es obligatorio";
    header("Location: cotizaciones.php");
    exit;
}

if (count($productos) === 0 || count($cantidades) === 0 || count($precios) === 0) {
    $_SESSION['error'] = "Debe incluir al menos un producto en la cotización";
    header("Location: cotizaciones.php");
    exit;
}

if (count($productos) !== count($cantidades) || count($productos) !== count($precios)) {
    $_SESSION['error'] = "Datos de productos inconsistentes";
    header("Location: cotizaciones.php");
    exit;
}

try {
    // Iniciar transacción
    $conexion->begin_transaction();

    // Insertar cabecera de cotización
    $stmt = $conexion->prepare("INSERT INTO cotizaciones 
                              (cliente, contacto, notas, total, usuario_id, fecha_creacion, validez_dias) 
                              VALUES (?, ?, ?, ?, ?, NOW(), 30)");
    
    // Calcular total
    $total = 0;
    foreach ($precios as $index => $precio) {
        $total += floatval($precio) * max(1, intval($cantidades[$index] ?? 1));
    }
    
    $stmt->bind_param("sssdi", $cliente, $contacto, $notas, $total, $_SESSION['id_usuario']);
    $stmt->execute();
    $cotizacion_id = $conexion->insert_id;
    $stmt->close();

    // Insertar detalles de cotización (con validación mejorada)
    $stmt = $conexion->prepare("INSERT INTO cotizacion_detalles 
                              (cotizacion_id, producto_id, cantidad, precio_unitario, subtotal) 
                              VALUES (?, ?, ?, ?, ?)");
    
    foreach ($productos as $index => $producto_id) {
        $producto_id = intval($producto_id);
        $cantidad = max(1, intval($cantidades[$index] ?? 1));  // Cantidad mínima 1
        $precio_unitario = floatval($precios[$index] ?? 0);
        $subtotal = $cantidad * $precio_unitario;
        
        $stmt->bind_param("iiidd", $cotizacion_id, $producto_id, $cantidad, $precio_unitario, $subtotal);
        $stmt->execute();
    }
    
    $stmt->close();

    // Confirmar transacción
    $conexion->commit();

    // Registrar en el historial (forma segura)
    $accion = "Creó la cotización #".intval($cotizacion_id)." para ".$conexion->real_escape_string($cliente);
    $usuario_id = intval($_SESSION['id_usuario']);
    $conexion->query("INSERT INTO historial (usuario_id, accion, fecha) VALUES ($usuario_id, '$accion', NOW())");

    // Redirigir con mensaje de éxito
    $_SESSION['exito'] = "Cotización #$cotizacion_id guardada correctamente";
    header("Location: ../../ver_cotizacion.php?id=$cotizacion_id");     
    exit;

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conexion) {
        $conexion->rollback();
    }
    
    // Registrar error detallado
    error_log("Error al guardar cotización [".date('Y-m-d H:i:s')."]: " . $e->getMessage()."\nStack trace:\n".$e->getTraceAsString());
    
    $_SESSION['error'] = "Error al guardar la cotización. Por favor intente nuevamente.";
    // No mostrar detalles del error al usuario por seguridad
    header("Location: listar_cotizaciones.php");
    exit;
}