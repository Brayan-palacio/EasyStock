<?php
// Configuración de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Iniciar sesión
session_start();

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    die('Acceso no autorizado');
}

// Obtener ID del cliente
$id_cliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_cliente <= 0) {
    die('ID de cliente no válido');
}

// Incluir librería PDF
require_once '../../vendor/tecnickcom/tcpdf/tcpdf.php';

// Conexión a la base de datos
require '../../config/conexion.php';

// Consultar datos del cliente
$sql_cliente = "SELECT * FROM clientes WHERE id = ?";
$stmt = $conexion->prepare($sql_cliente);
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die('Cliente no encontrado');
}

$cliente = $resultado->fetch_assoc();

// Consultar vehículos del cliente
$sql_vehiculos = "SELECT * FROM vehiculos WHERE id_cliente = ?";
$stmt_veh = $conexion->prepare($sql_vehiculos);
$stmt_veh->bind_param("i", $id_cliente);
$stmt_veh->execute();
$vehiculos = $stmt_veh->get_result();

// Crear PDF personalizado
class ClientePDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Ficha de Cliente - EasyStock', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 10, 'Generado el: ' . date('d/m/Y H:i'), 0, 1, 'C');
    }
}

$pdf = new ClientePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('EasyStock');
$pdf->SetTitle('Ficha de Cliente #' . $cliente['id']);
$pdf->AddPage();

// Contenido del reporte
$html = '<h2>Datos del Cliente</h2>
<table border="0.5" cellpadding="4">
    <tr>
        <td width="25%"><b>ID Cliente:</b></td>
        <td width="25%">#' . str_pad($cliente['id'], 4, '0', STR_PAD_LEFT) . '</td>
        <td width="25%"><b>Estado:</b></td>
        <td width="25%">' . $cliente['estado'] . '</td>
    </tr>
    <tr>
        <td><b>Nombre:</b></td>
        <td colspan="3">' . htmlspecialchars($cliente['nombre']) . '</td>
    </tr>
    <tr>
        <td><b>Identificación:</b></td>
        <td>' . htmlspecialchars($cliente['identificacion']) . '</td>
        <td><b>Teléfono:</b></td>
        <td>' . htmlspecialchars($cliente['telefono']) . '</td>
    </tr>
    <tr>
        <td><b>Email:</b></td>
        <td>' . htmlspecialchars($cliente['email']) . '</td>
        <td><b>Fecha Registro:</b></td>
        <td>' . date('d/m/Y H:i', strtotime($cliente['creado_en'])) . '</td>
    </tr>
    <tr>
        <td><b>Dirección:</b></td>
        <td colspan="3">' . htmlspecialchars($cliente['direccion']) . '</td>
    </tr>
</table>';

// Agregar sección de vehículos si existen
if ($vehiculos->num_rows > 0) {
    $html .= '<h2 style="margin-top:20px;">Vehículos Registrados</h2>
    <table border="0.5" cellpadding="4">
        <tr style="background-color:#f2f2f2;">
            <th width="15%">Placa</th>
            <th width="20%">Marca</th>
            <th width="20%">Modelo</th>
            <th width="15%">Año</th>
            <th width="30%">Color</th>
        </tr>';
    
    while ($vehiculo = $vehiculos->fetch_assoc()) {
        $html .= '<tr>
            <td>' . htmlspecialchars($vehiculo['placa']) . '</td>
            <td>' . htmlspecialchars($vehiculo['marca']) . '</td>
            <td>' . htmlspecialchars($vehiculo['modelo']) . '</td>
            <td>' . htmlspecialchars($vehiculo['anio']) . '</td>
            <td>' . htmlspecialchars($vehiculo['color']) . '</td>
        </tr>';
    }
    
    $html .= '</table>';
} else {
    $html .= '<p style="margin-top:20px;"><i>El cliente no tiene vehículos registrados</i></p>';
}

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('ficha_cliente_' . $cliente['id'] . '_' . date('Ymd') . '.pdf', 'I');

$conexion->close();
exit();