<?php
session_start();

// Configuración de seguridad de headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['error'] = "Debe iniciar sesión para realizar esta acción";
    header("Location: login.php");
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Token de seguridad inválido";
    header("Location: listar_ventas.php");
    exit;
}

include 'config/conexion.php';

// Obtener y validar datos del formulario
$venta_id = isset($_POST['venta_id']) ? intval($_POST['venta_id']) : 0;
$monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0;
$metodo_pago = isset($_POST['metodo_pago']) ? trim($_POST['metodo_pago']) : '';
$referencia = isset($_POST['referencia']) ? trim($_POST['referencia']) : null;

// Validaciones básicas
if ($venta_id <= 0) {
    $_SESSION['error'] = "ID de venta inválido";
    header("Location: listar_ventas.php");
    exit;
}

if ($monto <= 0) {
    $_SESSION['error'] = "El monto debe ser mayor a cero";
    header("Location: detalle_venta.php?id=$venta_id");
    exit;
}

if (empty($metodo_pago)) {
    $_SESSION['error'] = "Debe seleccionar un método de pago";
    header("Location: detalle_venta.php?id=$venta_id");
    exit;
}

try {
    // Iniciar transacción
    $conexion->begin_transaction();

    // 1. Verificar estado actual de la venta y saldo pendiente
    $stmt = $conexion->prepare("SELECT v.total, 
                               (SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.venta_id = v.id) as pagado
                               FROM ventas v 
                               WHERE v.id = ? AND v.estado != 'cancelada'");
    $stmt->bind_param("i", $venta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Venta no encontrada o está cancelada");
    }
    
    $venta = $result->fetch_assoc();
    $saldo_pendiente = $venta['total'] - $venta['pagado'];
    
    if ($monto > $saldo_pendiente) {
        throw new Exception("El monto ingresado ($monto) excede el saldo pendiente ($saldo_pendiente)");
    }

    // 2. Registrar el pago
    $stmt = $conexion->prepare("INSERT INTO pagos 
                               (venta_id, monto, metodo_pago, referencia, fecha_pago, registrado_por)
                               VALUES (?, ?, ?, ?, NOW(), ?)");
    $usuario = $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];
    $stmt->bind_param("idsss", $venta_id, $monto, $metodo_pago, $referencia, $usuario);
    $stmt->execute();
    $pago_id = $conexion->insert_id;
    $stmt->close();

    // 3. Actualizar estado de la venta si se pagó completo
    $nuevo_saldo = $saldo_pendiente - $monto;
    
    if ($nuevo_saldo <= 0) {
        $stmt = $conexion->prepare("UPDATE ventas SET estado = 'completada' WHERE id = ?");
        $stmt->bind_param("i", $venta_id);
        $stmt->execute();
        $stmt->close();
    }

    // 4. Registrar en el historial
    $accion = "Registró pago de $" . number_format($monto, 2) . " para la venta #$venta_id";
    $conexion->query("INSERT INTO historial (usuario_id, accion, fecha) VALUES ({$_SESSION['id_usuario']}, '$accion', NOW())");

    // Confirmar transacción
    $conexion->commit();

    $_SESSION['exito'] = "Pago registrado correctamente";
    header("Location: detalle_venta.php?id=$venta_id");
    exit;

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    $_SESSION['error'] = "Error al registrar pago: " . $e->getMessage();
    header("Location: detalle_venta.php?id=$venta_id");
    exit;
}