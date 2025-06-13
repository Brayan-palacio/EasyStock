<?php
$tituloPagina = 'Comprobante de Venta';
session_start();

// Configuración de seguridad mínima
if (!isset($_SESSION['id_usuario'])) {
    die("Acceso no autorizado");
}

include_once(__DIR__ . '/../../config/conexion.php');
include_once(__DIR__ . '/../../config/funciones.php');

$empresa = obtenerDatosEmpresa($conexion);

// Obtener ID de venta
$venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venta_id <= 0) {
    die("ID de venta inválido");
}

// Consultar cabecera de venta
$stmt = $conexion->prepare("SELECT v.*, u.nombre as vendedor, c.nombre as cliente_nombre, 
                           c.identificacion, c.direccion, c.telefono, c.email
                           FROM ventas v
                           JOIN usuarios u ON v.usuario_id = u.id
                           JOIN clientes c ON v.cliente_id = c.id
                           WHERE v.id = ?");
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$venta) {
    die("Venta no encontrada");
}

// Consultar detalles de venta
$stmt = $conexion->prepare("SELECT vd.*, p.descripcion as producto_nombre, p.codigo_barras
                           FROM venta_detalles vd
                           JOIN productos p ON vd.producto_id = p.id
                           WHERE vd.venta_id = ?");
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Consultar pagos asociados
$stmt = $conexion->prepare("SELECT * FROM pagos WHERE venta_id = ? ORDER BY fecha_pago");
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular saldo pendiente
$total_pagado = array_sum(array_column($pagos, 'monto'));
$saldo_pendiente = $venta['total'] - $total_pagado;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venta #<?= $venta_id ?> - EasyStock</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            color: #333;
            line-height: 1.6;
            background-color: #fff;
            margin: 0;
            padding: 0;
            font-size: 12pt;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #1a3a2f;
            padding-bottom: 15px;
        }
        
        .logo {
            max-width: 140px;
            height: auto;
        }
        
        .titulo-comprobante {
            color: #1a3a2f;
            text-align: center;
            margin: 15px 0;
            font-size: 1.5em;
        }
        
        .info-venta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .info-cliente, .info-factura {
            width: 48%;
            padding: 12px;
            background-color: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #e1e1e1;
            font-size: 0.9em;
        }
        
        .info-factura {
            background-color: #f0f7f4;
            border-color: #cce7e0;
        }
        
        h2, h3, h4 {
            color: #1a3a2f;
            margin-top: 0;
        }
        
        h2 {
            font-size: 1.3em;
            margin-bottom: 8px;
        }
        
        h3 {
            font-size: 1.1em;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
            margin-bottom: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 0.9em;
        }
        
        th {
            background-color: #1a3a2f;
            color: white;
            padding: 8px;
            text-align: left;
        }
        
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .total {
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .metodos-pago {
            margin-top: 20px;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #1a3a2f;
            text-align: center;
            font-size: 0.9em;
            color: #666;
        }
        
        .sello-aprobado {
            position: absolute;
            right: 50px;
            top: 150px;
            opacity: 0.2;
            transform: rotate(-15deg);
            font-size: 3em;
            color: #28a745;
            font-weight: bold;
        }
        
        /* Estilos específicos para impresión */
        @media print {
            body {
                font-size: 11pt;
            }
            
            .no-print {
                display: none !important;
            }
            
            .container {
                padding: 0;
            }
            
            @page {
                margin: 0.5cm;
                size: A4;
            }
            
            .sello-aprobado {
                opacity: 0.1;
            }
        }
        
        /* Botón de impresión solo en pantalla */
        @media screen {
            .print-button {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background-color: #1a3a2f;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                z-index: 1000;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }
            
            .print-button:hover {
                background-color: #2a5a46;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Encabezado -->
        <div class="header">
            <div>
                <img src="<?= isset($_SESSION['logo_empresa']) ? $_SESSION['logo_empresa'] : '../../img/EASYSTOCK.png' ?>" 
                     alt="EasyStock" class="logo">
                <h2><?= htmlspecialchars($empresa['empresa_nombre']) ?></h2>
                <p>
                    <?= nl2br(htmlspecialchars($empresa['empresa_direccion'])) ?? 'Dirección no especificada' ?><br>
                    <strong>Tel:</strong> <?= htmlspecialchars($empresa['empresa_telefono']) ?? 'N/A' ?> | 
                    <strong>RUC:</strong> <?= htmlspecialchars($empresa['empresa_ruc']) ?? 'N/A' ?>
                </p>
            </div>
            <div>
                <h1 class="titulo-comprobante">COMPROBANTE DE VENTA</h1>
                <p><strong>N°:</strong> <?= str_pad($venta_id, 8, '0', STR_PAD_LEFT) ?></p>
                <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></p>
            </div>
        </div>
        
        <!-- Información del cliente y venta -->
        <div class="info-venta">
            <div class="info-cliente">
                <h3>Información del Cliente</h3>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($venta['cliente_nombre']) ?></p>
                <p><strong>Identificación:</strong> <?= htmlspecialchars($venta['identificacion']) ?></p>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($venta['direccion']) ?></p>
                <p><strong>Contacto:</strong> <?= htmlspecialchars($venta['telefono']) ?> | <?= htmlspecialchars($venta['email']) ?></p>
            </div>
            
            <div class="info-factura">
                <h3>Detalles de la Venta</h3>
                <p><strong>Vendedor:</strong> <?= htmlspecialchars($venta['vendedor']) ?></p>
                <p><strong>Forma de Pago:</strong> <?= ucfirst($venta['forma_pago']) ?></p>
                <p><strong>Estado:</strong> 
                    <?php if ($saldo_pendiente <= 0): ?>
                        <span style="color: #28a745; font-weight: bold;">PAGADA</span>
                    <?php else: ?>
                        <span style="color: #dc3545; font-weight: bold;">PENDIENTE: $<?= number_format($saldo_pendiente, 2, ',', '.') ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Detalles de productos -->
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-right">Precio Unitario</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?= !empty($detalle['codigo_barras']) ? $detalle['codigo_barras'] : 'N/A' ?></td>
                        <td><?= htmlspecialchars($detalle['producto_nombre']) ?></td>
                        <td class="text-right">$<?= number_format($detalle['precio_unitario'], 2, ',', '.') ?></td>
                        <td class="text-center"><?= $detalle['cantidad'] ?></td>
                        <td class="text-right">$<?= number_format($detalle['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right total">SUBTOTAL</td>
                    <td class="text-right total">$<?= number_format($venta['subtotal'], 2, ',', '.') ?></td>
                </tr>
                <?php if ($venta['descuento'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-right">DESCUENTO</td>
                    <td class="text-right">-$<?= number_format($venta['descuento'], 2, ',', '.') ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($venta['impuestos'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-right">IVA (<?= $venta['porcentaje_iva'] ?>%)</td>
                    <td class="text-right">$<?= number_format($venta['impuestos'], 2, ',', '.') ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="4" class="text-right total">TOTAL</td>
                    <td class="text-right total">$<?= number_format($venta['total'], 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
        
        <!-- Detalles de pagos -->
        <?php if (!empty($pagos)): ?>
            <div class="metodos-pago">
                <h3>Pagos Registrados</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $pago): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td>
                                <td><?= ucfirst($pago['metodo']) ?></td>
                                <td><?= !empty($pago['referencia']) ? $pago['referencia'] : 'N/A' ?></td>
                                <td class="text-right">$<?= number_format($pago['monto'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total">
                            <td colspan="3" class="text-right">TOTAL PAGADO</td>
                            <td class="text-right">$<?= number_format($total_pagado, 2, ',', '.') ?></td>
                        </tr>
                        <?php if ($saldo_pendiente > 0): ?>
                            <tr class="total" style="color: #dc3545;">
                                <td colspan="3" class="text-right">SALDO PENDIENTE</td>
                                <td class="text-right">$<?= number_format($saldo_pendiente, 2, ',', '.') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Notas -->
        <?php if (!empty($venta['notas'])): ?>
            <div style="margin-top: 20px;">
                <h3>Notas</h3>
                <p><?= nl2br(htmlspecialchars($venta['notas'])) ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Términos y firmas -->
        <div style="margin-top: 30px; display: flex; justify-content: space-between;">
            <div style="width: 45%;">
                <h3>Condiciones</h3>
                <ul style="font-size: 0.8em; padding-left: 15px;">
                    <li>Artículos vendidos no tienen cambio ni devolución</li>
                    <li>Garantías según políticas de la empresa</li>
                    <li>Documento válido como factura</li>
                </ul>
            </div>
            <div style="width: 45%; text-align: center;">
                <div style="margin-top: 50px;">
                    <p>_________________________</p>
                    <p>Firma del Cliente</p>
                </div>
            </div>
        </div>
        
        <!-- Pie de página -->
        <div class="footer">
            <p>EasyStock &copy; <?= date('Y') ?> | Tel: <?= htmlspecialchars($empresa['empresa_telefono']) ?? 'N/A' ?> | Email: <?= htmlspecialchars($empresa['empresa_email']) ?? 'N/A' ?></p>
            <p>¡Gracias por su preferencia!</p>
            <?php if ($saldo_pendiente <= 0): ?>
                <div class="sello-aprobado no-print">PAGADO</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Botón de impresión (solo visible en pantalla) -->
    <button class="print-button no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimir
    </button>
    
    <script>
        // Auto-imprimir si se desea (opcional)
        <?php if (isset($_GET['autoprint']) && $_GET['autoprint'] === '1'): ?>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        <?php endif; ?>
        
        // Cerrar después de imprimir (opcional)
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 500);
        };
    </script>
</body>
</html>