<?php
session_start();

// Verificar CSRF token
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => 'Token CSRF inválido']));
}

// Verificar autenticación
if (!isset($_SESSION['id_usuario']) || empty($_SESSION['id_usuario'])) {
    die(json_encode(['success' => false, 'message' => 'No autenticado']));
}

// Conexión a la base de datos
include_once(__DIR__ . '/../../config/conexion.php');

// Obtener datos del POST
$cliente_id = intval($_POST['cliente_id'] ?? 0);
$forma_pago = $_POST['forma_pago'] ?? '';
$estado_pago = $_POST['estado_pago'] ?? '';
$productos = $_POST['productos'] ?? [];
$cantidades = $_POST['cantidades'] ?? [];
$precios = $_POST['precios'] ?? [];
$descuento = floatval($_POST['descuento'] ?? 0); // Nuevo campo para descuentos

// Validaciones básicas
if (empty($productos)) {
    die(json_encode(['success' => false, 'message' => 'No hay productos en la venta']));
}
// Antes de comenzar cualquier operación de base de datos
if (empty($_POST['cliente_id']) || $_POST['cliente_id'] == '0') {
    die(json_encode(['success' => false, 'message' => 'Debe seleccionar un cliente']));
}
try {
    // Iniciar transacción
    $conexion->begin_transaction();

    // Calcular subtotal (suma de todos los productos sin descuentos/impuestos)
    $subtotal = 0;
    foreach ($productos as $index => $producto_id) {
        $producto_id = intval($producto_id);
        $cantidad = intval($cantidades[$index]);
        $precio = floatval($precios[$index]);
        
        if ($cantidad <= 0) {
            throw new Exception("Cantidad inválida para el producto ID: $producto_id");
        }
        
        $subtotal += $precio * $cantidad;
    }

    // Calcular total (subtotal - descuentos + impuestos si los hay)
    $total = $subtotal - $descuento;

    // 1. Insertar la venta con subtotal y fecha actual
    $stmt = $conexion->prepare("INSERT INTO ventas (cliente_id, usuario_id, forma_pago, estado, subtotal, descuento, total, fecha_venta) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param("iissddd", $cliente_id, $_SESSION['id_usuario'], $forma_pago, $estado_pago, $subtotal, $descuento, $total);
    $stmt->execute();
    $venta_id = $conexion->insert_id;

    // 2. Insertar detalles de venta y actualizar inventario
    foreach ($productos as $index => $producto_id) {
        $producto_id = intval($producto_id);
        $cantidad = intval($cantidades[$index]);
        $precio = floatval($precios[$index]);

        // Verificar existencia y stock del producto
        $stmt = $conexion->prepare("SELECT cantidad FROM productos WHERE id = ?");
        $stmt->bind_param("i", $producto_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Producto ID: $producto_id no encontrado");
        }
        
        $stock = $result->fetch_assoc()['cantidad'];
        if ($stock < $cantidad) {
            throw new Exception("Stock insuficiente para el producto ID: $producto_id");
        }

        // Insertar detalle (opcional: puedes agregar precio_original y descuento aquí)
        $stmt = $conexion->prepare("INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario, subtotal) 
                                   VALUES (?, ?, ?, ?, ?)");
        $subtotal_detalle = $precio * $cantidad;
        $stmt->bind_param("iiidd", $venta_id, $producto_id, $cantidad, $precio, $subtotal_detalle);
        $stmt->execute();

        // Actualizar inventario
        $stmt = $conexion->prepare("UPDATE productos SET cantidad = cantidad - ? WHERE id = ?");
        $stmt->bind_param("ii", $cantidad, $producto_id);
        $stmt->execute();
    }

    // 3. Insertar pagos si existen
    if (!empty($_POST['pagos'])) {
        $pagos = json_decode($_POST['pagos'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Formato de pagos inválido");
        }
        
        foreach ($pagos as $pago) {
            if (!isset($pago['metodo']) || !isset($pago['monto']) || !is_numeric($pago['monto'])) {
                throw new Exception("Datos de pago incompletos o inválidos");
            }
            
            $stmt = $conexion->prepare("INSERT INTO pagos (venta_id, metodo, monto, referencia) 
                                       VALUES (?, ?, ?, ?)");
            $referencia = $pago['referencia'] ?? null;
            $stmt->bind_param("isds", $venta_id, $pago['metodo'], $pago['monto'], $referencia);
            $stmt->execute();
        }
    }

    // Confirmar transacción
    $conexion->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Venta registrada correctamente', 
        'venta_id' => $venta_id,
        'subtotal' => $subtotal,
        'descuento' => $descuento,
        'total' => $total,
        'fecha_venta' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    error_log("Error al guardar venta: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error al procesar la venta: ' . $e->getMessage()
    ]);
}