<?php
require('fpdf186/fpdf.php');
include 'config/conexion.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de venta no válido.");
}

$id_venta = intval($_GET['id']);

// Obtener los datos de la venta
$sql_venta = "SELECT * FROM ventas WHERE id = ?";
$stmt_venta = $conexion->prepare($sql_venta);
$stmt_venta->bind_param("i", $id_venta);
$stmt_venta->execute();
$result_venta = $stmt_venta->get_result();
$venta = $result_venta->fetch_assoc();

if (!$venta) {
    die("Venta no encontrada.");
}

// Obtener detalles de los productos de la venta
$sql_detalles = "SELECT p.descripcion AS producto, dv.cantidad, precio_venta 
                 FROM detalle_ventas dv
                 JOIN productos p ON dv.producto_id = p.id
                 WHERE dv.venta_id = ?";
$stmt_detalle = $conexion->prepare($sql_detalles);
$stmt_detalle->bind_param("i", $id_venta);
$stmt_detalle->execute();
$result_detalle = $stmt_detalle->get_result();

// Obtener los datos del cliente
$sql_cliente = "SELECT c.nombre, c.direccion, c.email 
                FROM clientes c 
                JOIN ventas v ON c.id = v.cliente_id 
                WHERE v.id = ?";
$stmt_cliente = $conexion->prepare($sql_cliente);
$stmt_cliente->bind_param("i", $id_venta);
$stmt_cliente->execute();
$result_cliente = $stmt_cliente->get_result();
$cliente = $result_cliente->fetch_assoc();

$nombre_cliente = $cliente['nombre'] ?? "Cliente no registrado";
$direccion_cliente = $cliente['direccion'] ?? "Sin dirección";
$email_cliente = $cliente['email'] ?? "Sin correo";

// Crear el PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(190, 10, 'FACTURA', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(190, 10, "Factura Numero: " . $id_venta, 0, 1, 'C');
$pdf->Ln(5);

// Datos del Taller
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetX(120);
$pdf->Cell(0, 10, 'TALLER EL MAMEY', 0, 1, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->SetX(120);
$pdf->Cell(0, 6, 'NIT: 1063647063', 0, 1, 'R');
$pdf->SetX(120);
$pdf->Cell(0, 6, 'Correo: sarayespitia4@gmail.com', 0, 1, 'R');
$pdf->SetX(120);
$pdf->Cell(0, 6, 'Tel: 322 6102915', 0, 1, 'R');
$pdf->SetX(120);
$pdf->Cell(0, 6, 'Direccion: El Mamey', 0, 1, 'R');
$pdf->Ln(5);

// Línea separadora
$pdf->Cell(190, 0, '', 'T', 1, 'C');
$pdf->Ln(5);

// Datos del Cliente
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'DATOS DEL CLIENTE', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 6, 'Nombre: ' . $nombre_cliente, 0, 1, 'L');
$pdf->MultiCell(190, 6, 'Direccion: ' . $direccion_cliente);
$pdf->Cell(0, 6, 'Correo: ' . $email_cliente, 0, 1, 'L');
$pdf->Ln(10);

// Tabla de productos
$pdf->SetFillColor(200, 220, 255);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(80, 10, 'Descripcion', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Cantidad', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Precio', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Total', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$total = 0;
while ($fila = $result_detalle->fetch_assoc()) {
    $subtotal = $fila['cantidad'] * $fila['precio_venta'];
    $total += $subtotal;

    $pdf->Cell(80, 10, utf8_decode($fila['producto']), 1);
    $pdf->Cell(30, 10, number_format($fila['cantidad']), 1, 0, 'C');
    $pdf->Cell(40, 10, "$" . number_format($fila['precio_venta'], 2), 1, 0, 'C');
    $pdf->Cell(40, 10, "$" . number_format($subtotal, 2), 1, 1, 'C');
}

// Cálculo del IVA y total
$iva = $total * 0.19;
$pdf->Cell(150, 10, 'Sub Total', 1, 0, 'R');
$pdf->Cell(40, 10, "$" . number_format($total, 2), 1, 1, 'C');
$pdf->Cell(150, 10, 'IVA (19%)', 1, 0, 'R');
$pdf->Cell(40, 10, "$" . number_format($iva, 2), 1, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(150, 10, 'TOTAL', 1, 0, 'R');
$pdf->Cell(40, 10, "$" . number_format($total + $iva, 2), 1, 1, 'C');

$pdf->Ln(10);
$pdf->Cell(190, 10, 'MUCHAS GRACIAS', 0, 1, 'C');

// Generar PDF
$pdf->Output();
?>
