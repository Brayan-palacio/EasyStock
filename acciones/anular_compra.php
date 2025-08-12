<?php
session_start();
include '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol_usuario'] != 'Administrador') {
    die('Acceso denegado');
}

$compra_id = (int)$_GET['id'];

// 1. Revertir movimientos en Kardex
$movimientos = $conexion->query("
    SELECT producto_id, cantidad 
    FROM compras_detalle 
    WHERE compra_id = $compra_id
");

while ($mov = $movimientos->fetch_assoc()) {
    $conexion->query("
        INSERT INTO movimientos 
        (producto_id, tipo, cantidad, motivo, usuario_id) 
        VALUES (
            {$mov['producto_id']}, 
            'salida', 
            {$mov['cantidad']}, 
            'Anulación compra #$compra_id', 
            {$_SESSION['id_usuario']}
        )
    ");
    
    // Revertir stock
    $conexion->query("
        UPDATE productos 
        SET cantidad = cantidad - {$mov['cantidad']} 
        WHERE id = {$mov['producto_id']}
    ");
}

// 2. Marcar como anulada
$conexion->query("UPDATE compras SET estado = 'Anulado' WHERE id = $compra_id");

$_SESSION['mensaje'] = [
    'tipo' => 'success',
    'texto' => 'Compra anulada y stock revertido'
];

header("Location: ../compras.php");
?>