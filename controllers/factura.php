<?php
include 'config/conexion.php';
include 'config/funciones.php';

// Obtener datos de la empresa
$empresa = obtenerDatosEmpresa($conexion);

$venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venta_id <= 0) {
    die("ID de venta inválido");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Venta</title>
    <style>
        .cabecera-comprobante {
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .logo-empresa {
            max-height: 80px;
        }
        .datos-empresa {
            font-size: 14px;
            line-height: 1.4;
        }
    </style>
</head>
<body>

    <!-- Cabecera del comprobante -->
    <div class="cabecera-comprobante">
        <div style="display: flex; justify-content: space-between;">
            <div>
                <?php if (!empty($empresa['logo_empresa'])): ?>
                    <img src="assets/img/<?= htmlspecialchars($empresa['logo_empresa']) ?>" 
                         alt="Logo" class="logo-empresa">
                <?php endif; ?>
            </div>
            <div class="datos-empresa" style="text-align: right;">
                <h2><?= htmlspecialchars($empresa['empresa_nombre']) ?></h2>
                <p><strong>RUC:</strong> <?= htmlspecialchars($empresa['empresa_ruc']) ?></p>
                <p><?= nl2br(htmlspecialchars($empresa['empresa_direccion'])) ?></p>
                <p><strong>Teléfono:</strong> <?= htmlspecialchars($empresa['empresa_telefono']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($empresa['empresa_email']) ?></p>
            </div>
        </div>
    </div>

    <!-- Datos del comprobante -->
    <div style="margin-bottom: 30px;">
        <h3 style="text-align: center;">COMPROBANTE DE VENTA</h3>
        <p><strong>N°:</strong> <?= htmlspecialchars($venta['numero_comprobante']) ?></p>
        <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($venta['fecha_venta'])) ?></p>
    </div>

    <!-- Resto del contenido del comprobante -->
    <!-- ... -->

</body>
</html>