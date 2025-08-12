<?php
session_start();
header('Content-Type: application/json');

// 1. Validar CSRF y sesión
if (empty($_SESSION['id_usuario']) || empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['error' => 'Acceso no autorizado']));
}

include 'config/conexion.php';

// 2. Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$carrito = $data['carrito'];
$cliente_id = $data['clienteId'] ?? null;
$usuario_id = $_SESSION['id_usuario'];

try {
    $conexion->begin_transaction();

    // 3. Insertar venta
    $sqlVenta = "INSERT INTO ventas (
        cliente_id, usuario_id, subtotal, descuento, porcentaje_iva, impuestos, total, forma_pago, estado
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completada')";
    
    $stmtVenta = $conexion->prepare($sqlVenta);
    $stmtVenta->bind_param(
        "iiddddss",
        $cliente_id,
        $usuario_id,
        $data['subtotal'],
        $data['descuento'],
        $data['porcentaje_iva'],
        $data['subtotal'] * ($data['porcentaje_iva'] / 100), // impuestos
        $data['total'],
        $data['formaPago']
    );
    $stmtVenta->execute();
    $venta_id = $conexion->insert_id;

    // 4. Insertar detalles y actualizar stock
    foreach ($carrito as $item) {
        // Detalle
        $sqlDetalle = "INSERT INTO venta_detalles (
            venta_id, producto_id, cantidad, precio_unitario, subtotal
        ) VALUES (?, ?, ?, ?, ?)";
        
        $stmtDetalle = $conexion->prepare($sqlDetalle);
        $subtotal = $item['precio'] * $item['cantidad'];
        $stmtDetalle->bind_param(
            "iiidd",
            $venta_id,
            $item['id'],
            $item['cantidad'],
            $item['precio'],
            $subtotal
        );
        $stmtDetalle->execute();

        // Actualizar stock
        $sqlStock = "UPDATE productos SET cantidad = cantidad - ? WHERE id = ?";
        $stmtStock = $conexion->prepare($sqlStock);
        $stmtStock->bind_param("ii", $item['cantidad'], $item['id']);
        $stmtStock->execute();
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'id_venta' => $venta_id]);

} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}