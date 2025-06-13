<?php
// productosController.php

function obtenerProductos($conexion, $inicio = 0, $porPagina = 10) {
    $query = $conexion->prepare("
        SELECT p.*, c.nombre AS categoria 
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.activo = 1
        ORDER BY p.id DESC  -- ¡Nuevos productos primero!
        LIMIT ?, ?
    ");
    $query->bind_param("ii", $inicio, $porPagina);
    $query->execute();
    $result = $query->get_result();
    
    $productos = [];
    while ($fila = $result->fetch_assoc()) {
        $productos[] = $fila;
    }
    return $productos;
}

function obtenerTotalProductos($conexion) {
    $query = $conexion->prepare("SELECT COUNT(*) AS total FROM productos WHERE activo = 1");
    $query->execute();
    $result = $query->get_result();
    return $result->fetch_assoc()['total'];
}

function formatoMoneda($valor) {
    return '$' . number_format($valor, 0, ',', '.');
}

function agregarProducto($conexion, $descripcion, $precio_compra, $precio_venta, $cantidad, $categoria_id, $codigo_barras, $imagen) {
    try {
        $sql = "
            INSERT INTO productos (
                descripcion, 
                precio_compra, 
                precio_venta, 
                cantidad, 
                categoria_id, 
                codigo_barras, 
                imagen,
                fecha_creacion,
                activo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ";
        
        $stmt = $conexion->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conexion->error);
        }
        
        $stmt->bind_param(
            "sddiiss",
            $descripcion,
            $precio_compra,
            $precio_venta,
            $cantidad,
            $categoria_id,
            $codigo_barras,
            $imagen
        );
        
        $resultado = $stmt->execute();
        
        if (!$resultado) {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error en agregarProducto: " . $e->getMessage());
        return false;
    }
}
function obtenerProductoPorId($conexion, $id) {
    $stmt = $conexion->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    return $resultado->fetch_assoc();
}

function actualizarProducto($conexion, $id, $descripcion, $precio_compra, $precio_venta, $cantidad, $categoria_id, $codigo_barras, $imagen) {
    $stmt = $conexion->prepare("UPDATE productos SET 
                               descripcion = ?, 
                               precio_compra = ?, 
                               precio_venta = ?, 
                               cantidad = ?, 
                               categoria_id = ?, 
                               codigo_barras = ?, 
                               imagen = ?,
                               actualizado_en = NOW()
                               WHERE id = ?");
    
    $stmt->bind_param('sddiissi', $descripcion, $precio_compra, $precio_venta, $cantidad, $categoria_id, $codigo_barras, $imagen, $id);
    return $stmt->execute();
}
function obtenerTodosProductosBajoStock($conexion, $umbralBajo) {
    $sql = "SELECT p.*, c.nombre as categoria 
            FROM productos p 
            JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.cantidad <= ? AND p.activo = 1
            ORDER BY p.cantidad ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $umbralBajo);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>