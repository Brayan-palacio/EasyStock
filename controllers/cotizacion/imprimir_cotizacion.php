<?php
$tituloPagina = 'Impresión de Cotización';
session_start();

// Configuración de seguridad mínima
if (!isset($_SESSION['id_usuario'])) {
    die("Acceso no autorizado");
}

include '../../config/conexion.php';
include '../../config/funciones.php';

$empresa = obtenerDatosEmpresa($conexion);

// Obtener ID de cotización
$cotizacion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cotizacion_id <= 0) {
    die("ID de cotización inválido");
}

// Consultar cabecera de cotización
$stmt = $conexion->prepare("SELECT c.*, u.nombre as usuario_nombre 
                           FROM cotizaciones c
                           JOIN usuarios u ON c.usuario_id = u.id
                           WHERE c.id = ?");
$stmt->bind_param("i", $cotizacion_id);
$stmt->execute();
$cotizacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cotizacion) {
    die("Cotización no encontrada");
}

// Consultar detalles de cotización
$stmt = $conexion->prepare("SELECT cd.*, p.nombre as producto_nombre, p.descripcion as producto_descripcion
                           FROM cotizacion_detalles cd
                           JOIN productos p ON cd.producto_id = p.id
                           WHERE cd.cotizacion_id = ?");
$stmt->bind_param("i", $cotizacion_id);
$stmt->execute();
$detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular fecha de vencimiento
$fecha_creacion = new DateTime($cotizacion['fecha_creacion']);
$fecha_vencimiento = clone $fecha_creacion;
$fecha_vencimiento->add(new DateInterval('P' . $cotizacion['validez_dias'] . 'D'));

// Configurar cabeceras para PDF (opcional)
// header('Content-Type: application/pdf');
// header('Content-Disposition: inline; filename="Cotizacion_' . $cotizacion_id . '.pdf"');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización #<?= $cotizacion_id ?> - EasyStock</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            color: #333;
            line-height: 1.6;
            background-color: #fff;
            margin: 0;
            padding: 0;
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
            margin-bottom: 30px;
            border-bottom: 2px solid #1a3a2f;
            padding-bottom: 20px;
        }
        
        .logo {
            max-width: 150px;
        }
        
        .titulo {
            color: #1a3a2f;
            text-align: center;
            margin: 20px 0;
        }
        
        .info-cotizacion {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .info-cliente, .info-factura {
            width: 48%;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border: 1px solid #e1e1e1;
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
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        h3 {
            font-size: 18px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th {
            background-color: #1a3a2f;
            color: white;
            padding: 10px;
            text-align: left;
        }
        
        td {
            padding: 10px;
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
        
        .notas {
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border: 1px solid #e1e1e1;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #1a3a2f;
            text-align: center;
            font-size: 0.9em;
            color: #666;
        }
        
        .estado {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .estado-pendiente {
            background-color: #ffc107;
            color: #000;
        }
        
        .estado-aprobada {
            background-color: #28a745;
            color: #fff;
        }
        
        .estado-rechazada {
            background-color: #dc3545;
            color: #fff;
        }
        
        /* Estilos específicos para impresión */
        @media print {
            body {
                font-size: 12pt;
            }
            
            .no-print {
                display: none !important;
            }
            
            .container {
                padding: 0;
            }
            
            .header, .info-cotizacion, table {
                margin-bottom: 15px;
            }
            
            .logo {
                max-width: 120px;
            }
            
            @page {
                margin: 1cm;
                size: A4;
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
                    Tel: <?= htmlspecialchars($empresa['empresa_telefono']) ?? 'N/A' ?> | 
                    Email: <?= htmlspecialchars($empresa['empresa_email']) ?? 'N/A' ?>
                </p>
            </div>
            <div>
                <h1 class="titulo">COTIZACIÓN</h1>
                <p><strong>N°:</strong> <?= $cotizacion_id ?></p>
                <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($cotizacion['fecha_creacion'])) ?></p>
            </div>
        </div>
        
        <!-- Información del cliente y cotización -->
        <div class="info-cotizacion">
            <div class="info-cliente">
                <h3>Información del Cliente</h3>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($cotizacion['cliente']) ?></p>
                <p><strong>Contacto:</strong> <?= htmlspecialchars($cotizacion['contacto']) ?></p>
            </div>
            
            <div class="info-factura">
                <h3>Detalles de Cotización</h3>
                <p><strong>Estado:</strong> 
                    <span class="estado estado-<?= $cotizacion['estado'] ?>">
                        <?= ucfirst($cotizacion['estado']) ?>
                    </span>
                </p>
                <p><strong>Válida hasta:</strong> <?= $fecha_vencimiento->format('d/m/Y') ?></p>
                <p><strong>Generada por:</strong> <?= htmlspecialchars($cotizacion['usuario_nombre']) ?></p>
            </div>
        </div>
        
        <!-- Detalles de productos -->
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Descripción</th>
                    <th class="text-right">Precio Unitario</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?= htmlspecialchars($detalle['producto_nombre']) ?></td>
                        <td><?= htmlspecialchars($detalle['producto_descripcion']) ?></td>
                        <td class="text-right">$<?= number_format($detalle['precio_unitario'], 2, ',', '.') ?></td>
                        <td class="text-center"><?= $detalle['cantidad'] ?></td>
                        <td class="text-right">$<?= number_format($detalle['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right total">TOTAL</td>
                    <td class="text-right total">$<?= number_format($cotizacion['total'], 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
        
        <!-- Notas -->
        <?php if (!empty($cotizacion['notas']) || !empty($cotizacion['comentario'])): ?>
            <div class="notas">
                <h3>Notas</h3>
                <?php if (!empty($cotizacion['notas'])): ?>
                    <p><?= nl2br(htmlspecialchars($cotizacion['notas'])) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($cotizacion['comentario'])): ?>
                    <h4>Comentario (<?= ucfirst($cotizacion['estado']) ?>):</h4>
                    <p><?= nl2br(htmlspecialchars($cotizacion['comentario'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Términos y condiciones -->
        <div class="terminos">
            <h3>Términos y Condiciones</h3>
            <ul>
                <li>Esta cotización es válida hasta <?= $fecha_vencimiento->format('d/m/Y') ?></li>
                <li>Precios sujetos a cambio sin previo aviso</li>
                <li>Formas de pago: Efectivo, transferencia bancaria</li>
                <li>Tiempo de entrega: A convenir según disponibilidad</li>
            </ul>
        </div>
        
        <!-- Firma -->
        <div style="margin-top: 50px; display: flex; justify-content: space-between;">
            <div style="width: 45%; text-align: center;">
                <p>_________________________</p>
                <p>Firma del Cliente</p>
            </div>
            <div style="width: 45%; text-align: center;">
                <p>_________________________</p>
                <p><?= htmlspecialchars($empresa['empresa_nombre']) ?></p>
            </div>
        </div>
        
        <!-- Pie de página -->
        <div class="footer">
            <p>EasyStock &copy; <?= date('Y') ?> | Tel: <?= htmlspecialchars($empresa['empresa_telefono'])?? 'N/A' ?> | Email: <?= htmlspecialchars($empresa['empresa_email']) ?? 'N/A' ?></p>
            <p>¡Gracias por su preferencia!</p>
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