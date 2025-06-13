<?php
require 'config/conexion.php';
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$usuarioId = $_SESSION['id_usuario'];
$limit = 5; // Número de notificaciones a mostrar

// Obtener notificaciones no leídas
$query = "SELECT id, titulo, mensaje, url, creada_en, leida 
          FROM notificaciones 
          WHERE usuario_id = ? 
          ORDER BY creada_en DESC 
          LIMIT ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param('ii', $usuarioId, $limit);
$stmt->execute();
$result = $stmt->get_result();

$notificaciones = [];
while ($row = $result->fetch_assoc()) {
    $notificaciones[] = [
        'id' => $row['id'],
        'titulo' => htmlspecialchars($row['titulo']),
        'mensaje' => htmlspecialchars($row['mensaje']),
        'url' => $row['url'],
        'fecha' => date('d M H:i', strtotime($row['creada_en'])),
        'leida' => (bool)$row['leida']
    ];
}

// Contar notificaciones no leídas
$queryCount = "SELECT COUNT(*) as total FROM notificaciones WHERE usuario_id = ? AND leida = FALSE";
$stmtCount = $conexion->prepare($queryCount);
$stmtCount->bind_param('i', $usuarioId);
$stmtCount->execute();
$count = $stmtCount->get_result()->fetch_assoc()['total'];

echo json_encode([
    'notificaciones' => $notificaciones,
    'sinLeer' => (int)$count
]);