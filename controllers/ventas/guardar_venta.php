<?php
session_start();

// 1. Validación de Seguridad Mejorada
header("Content-Type: application/json");

// Verificar CSRF token
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die(json_encode(['success' => false, 'message' => 'Token CSRF inválido', 'code' => 'CSRF_ERROR']));
}

// Verificar autenticación y permisos
if (empty($_SESSION['id_usuario']) || !isset($_SESSION['rol_usuario'])) {
    die(json_encode(['success' => false, 'message' => 'Acceso no autorizado', 'code' => 'AUTH_ERROR']));
}

// Conexión a la base de datos
require_once __DIR__ . '/../../config/conexion.php';

// 2. Procesamiento de Datos con Validación Estricta
$cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$forma_pago = in_array($_POST['forma_pago'] ?? '', ['contado', 'credito', 'mixto']) ? $_POST['forma_pago'] : 'contado';
$estado_pago = in_array($_POST['estado_pago'] ?? '', ['pagada', 'pendiente']) ? $_POST['estado_pago'] : 'pagada';
$productos = array_filter($_POST['productos'] ?? [], 'is_numeric');
$cantidades = array_filter($_POST['cantidades'] ?? [], 'is_numeric');
$precios = array_filter($_POST['precios'] ?? [], 'is_numeric');
$descuento = filter_input(INPUT_POST, 'descuento', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);

// Validación de datos básicos
if (count($productos) !== count($cantidades) || count($productos) !== count($precios)) {
    die(json_encode(['success' => false, 'message' => 'Datos de productos inconsistentes', 'code' => 'DATA_ERROR']));
}

if (empty($productos)) {
    die(json_encode(['success' => false, 'message' => 'No hay productos en la venta', 'code' => 'NO_PRODUCTS']));
}

try {
    // 3. Transacción para integridad de datos
    $conexion->begin_transaction();

    // 4. Cálculo de totales
    $subtotal = 0;
    $productos_data = [];
    
    foreach ($productos as $index => $producto_id) {
        $producto_id = intval($producto_id);
        $cantidad = intval($cantidades[$index]);
        $precio = floatval($precios[$index]);
        
        // Validación de cantidad
        if ($cantidad <= 0) {
            throw new Exception("Cantidad inválida para el producto ID: $producto_id", 400);
        }
        
        // Verificar existencia y stock del producto
        $stmt = $conexion->prepare("SELECT id, descripcion, cantidad FROM productos WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $producto_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Producto ID: $producto_id no encontrado", 404);
        }
        
        $producto = $result->fetch_assoc();
        if ($producto['cantidad'] < $cantidad) {
            throw new Exception("Stock insuficiente para: {$producto['descripcion']} (Disponible: {$producto['cantidad']})", 409);
        }
        
        $subtotal_detalle = $precio * $cantidad;
        $subtotal += $subtotal_detalle;
        
        $productos_data[] = [
            'id' => $producto_id,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'subtotal' => $subtotal_detalle,
            'descripcion' => $producto['descripcion']
        ];
    }

    $total = max(0, $subtotal - $descuento); // Asegurar que el total no sea negativo

    // 5. Registrar la venta principal
    $stmt = $conexion->prepare("INSERT INTO ventas (
        cliente_id, 
        usuario_id, 
        forma_pago, 
        estado, 
        subtotal, 
        descuento, 
        total, 
        fecha_venta
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param(
        "iissddd", 
        $cliente_id, 
        $_SESSION['id_usuario'], 
        $forma_pago, 
        $estado_pago, 
        $subtotal, 
        $descuento, 
        $total
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error al registrar la venta: " . $conexion->error, 500);
    }
    
    $venta_id = $conexion->insert_id;

    // 6. Registrar detalles y movimientos de inventario
    foreach ($productos_data as $producto) {
        // Insertar detalle de venta
        $stmt = $conexion->prepare("INSERT INTO venta_detalles (
            venta_id, 
            producto_id, 
            cantidad, 
            precio_unitario, 
            subtotal
        ) VALUES (?, ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "iiidd", 
            $venta_id, 
            $producto['id'], 
            $producto['cantidad'], 
            $producto['precio'], 
            $producto['subtotal']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar detalle de venta", 500);
        }

        // Registrar movimiento en el Kardex (SALIDA)
        $stmt = $conexion->prepare("INSERT INTO movimientos (
            producto_id, 
            tipo, 
            cantidad, 
            motivo, 
            usuario_id, 
            venta_id,
            fecha
        ) VALUES (?, 'salida', ?, ?, ?, ?, NOW())");
        
        $motivo = "Venta #$venta_id - " . substr($producto['descripcion'], 0, 50);
        $stmt->bind_param(
            "iisii", 
            $producto['id'], 
            $producto['cantidad'], 
            $motivo, 
            $_SESSION['id_usuario'], 
            $venta_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar movimiento en el Kardex", 500);
        }

        // Actualizar inventario (optimista con verificación)
        $stmt = $conexion->prepare("UPDATE productos 
                                   SET cantidad = cantidad - ? 
                                   WHERE id = ? AND cantidad >= ?");
        $stmt->bind_param("iii", $producto['cantidad'], $producto['id'], $producto['cantidad']);
        
        if (!$stmt->execute() || $conexion->affected_rows === 0) {
            throw new Exception("Error al actualizar inventario del producto ID: {$producto['id']}", 500);
        }
    }

    // 7. Registrar pagos si existen
    if (!empty($_POST['pagos'])) {
        $pagos = json_decode($_POST['pagos'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Formato de pagos inválido", 400);
        }
        
        foreach ($pagos as $pago) {
            if (empty($pago['metodo']) || !is_numeric($pago['monto']) || $pago['monto'] <= 0) {
                continue; // Omitir pagos inválidos
            }
            
            $stmt = $conexion->prepare("INSERT INTO pagos (
                venta_id, 
                metodo, 
                monto, 
                referencia
            ) VALUES (?, ?, ?, ?)");
            
            $referencia = !empty($pago['referencia']) ? substr($pago['referencia'], 0, 100) : null;
            $stmt->bind_param(
                "isds", 
                $venta_id, 
                $pago['metodo'], 
                $pago['monto'], 
                $referencia
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error al registrar pago", 500);
            }
        }
    }

    // 8. Confirmar transacción
    $conexion->commit();

    // 9. Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Venta registrada correctamente',
        'data' => [
            'venta_id' => $venta_id,
            'folio' => str_pad($venta_id, 8, '0', STR_PAD_LEFT),
            'total' => number_format($total, 2),
            'fecha' => date('d/m/Y H:i:s')
        ]
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conexion->in_transaction) {
        $conexion->rollback();
    }
    
    // Registrar error detallado
    error_log("Error en guardar_venta.php: " . $e->getMessage());
    
    // Respuesta de error controlada
    $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    http_response_code($code);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => 'EXCEPTION_' . $code
    ]);
}