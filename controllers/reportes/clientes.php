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

// Incluir la librería para generar PDF (ej. TCPDF, FPDF, etc.)
require_once '../../vendor/tecnickcom/tcpdf/tcpdf.php'; // Ajusta la ruta según tu estructura

// Conexión a la base de datos
require '../../config/conexion.php';

// Crear nuevo documento PDF
class MYPDF extends TCPDF {
    // Cabecera del documento
    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Reporte de Clientes - EasyStock', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 1, 'Generado el: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $this->Ln(5);
    }

    // Pie de página
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Crear instancia de PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('EasyStock');
$pdf->SetTitle('Reporte de Clientes');
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

// Consulta a la base de datos
$sql = "SELECT id, nombre, identificacion, email, telefono, direccion, estado, 
               DATE_FORMAT(creado_en, '%d/%m/%Y %H:%i') as fecha_registro
        FROM clientes
        ORDER BY nombre ASC";
$resultado = $conexion->query($sql);

// Contenido del reporte
$html = '<table border="1" cellpadding="4">
            <thead>
                <tr style="background-color:#f2f2f2;">
                    <th width="8%"><b>ID</b></th>
                    <th width="20%"><b>Nombre</b></th>
                    <th width="20%"><b>Identificación</b></th>
                    <th width="15%"><b>Email</b></th>
                    <th width="15%"><b>Teléfono</b></th>
                    <th width="10%"><b>Estado</b></th>
                    <th width="12%"><b>Registro</b></th>
                </tr>
            </thead>
            <tbody>';

while ($cliente = $resultado->fetch_assoc()) {
    $html .= '<tr>
                <td width="8%">#' . str_pad($cliente['id'], 4, '0', STR_PAD_LEFT) . '</td>
                <td width="20%">' . htmlspecialchars($cliente['nombre']) . '</td>
                <td width="20%">' . htmlspecialchars($cliente['identificacion']) . '</td>
                <td width="15%">' . htmlspecialchars($cliente['email']) . '</td>
                <td width="15%">' . htmlspecialchars($cliente['telefono']) . '</td>
                <td width="10%">' . ($cliente['estado'] === 'Activo' ? 'Activo' : '<span style="color:red;">Inactivo</span>') . '</td>
                <td width="12%">' . $cliente['fecha_registro'] . '</td>
              </tr>';
}

$html .= '</tbody></table>';

// Escribir contenido
$pdf->writeHTML($html, true, false, true, false, '');

// Cerrar y generar PDF
$pdf->Output('reporte_clientes_' . date('Ymd_His') . '.pdf', 'I');

// Cerrar conexión
$conexion->close();
exit();