<?php
$tituloPagina = 'Mis Notificaciones - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Marcar todas como leídas si se solicita
if (isset($_GET['marcar_todas']) && $_GET['marcar_todas'] == '1') {
    $query = "UPDATE notificaciones SET leida = TRUE WHERE usuario_id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $_SESSION['id_usuario']);
    $stmt->execute();
    
    $_SESSION['mensaje'] = [
        'tipo' => 'success',
        'texto' => 'Todas las notificaciones marcadas como leídas'
    ];
    header("Location: notificaciones.php");
    exit();
}

// Paginación
$registrosPorPagina = 15;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Obtener notificaciones
$query = "SELECT SQL_CALC_FOUND_ROWS 
            id, titulo, mensaje, url, leida, creada_en 
          FROM notificaciones 
          WHERE usuario_id = ? 
          ORDER BY creada_en DESC 
          LIMIT ? OFFSET ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param('iii', $_SESSION['id_usuario'], $registrosPorPagina, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Total de registros
$totalRegistros = $conexion->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
?>

<div class="container-fluid py-4">
    <div class="card shadow-lg border-0 rounded-3">
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="h5 mb-0 fw-semibold">
                    <i class="fas fa-bell me-2"></i> Mis Notificaciones
                </h2>
                <div class="d-flex gap-2">
                    <a href="notificaciones.php?marcar_todas=1" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-check-double me-1"></i> Marcar todas como leídas
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-<?= $_SESSION['mensaje']['tipo'] ?> alert-dismissible fade show" role="alert">
                    <i class="fas <?= $_SESSION['mensaje']['tipo'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                    <?= htmlspecialchars($_SESSION['mensaje']['texto']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['mensaje']); ?>
            <?php endif; ?>
            
            <div class="list-group">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($notificacion = $result->fetch_assoc()): ?>
                        <a href="<?= $notificacion['url'] ? htmlspecialchars($notificacion['url']) : '#' ?>" 
                           class="list-group-item list-group-item-action border-0 py-3 <?= !$notificacion['leida'] ? 'bg-light' : '' ?>"
                           onclick="marcarComoLeida(<?= $notificacion['id'] ?>, this)">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="me-3">
                                    <h6 class="mb-1 <?= !$notificacion['leida'] ? 'fw-bold' : '' ?>">
                                        <?= htmlspecialchars($notificacion['titulo']) ?>
                                        <?php if (!$notificacion['leida']): ?>
                                            <span class="badge bg-primary ms-2">Nuevo</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="mb-1"><?= htmlspecialchars($notificacion['mensaje']) ?></p>
                                </div>
                                <small class="text-muted"><?= date('d M Y H:i', strtotime($notificacion['creada_en'])) ?></small>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                        <h4>No tienes notificaciones</h4>
                        <p class="text-muted">Cuando tengas notificaciones, aparecerán aquí</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginación" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual - 1 ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == $paginaActual ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual + 1 ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<script>
// Función para marcar como leída
function marcarComoLeida(id, elemento) {
    if (!elemento.classList.contains('bg-light')) return true;
    
    fetch(`marcar_leida.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                elemento.classList.remove('bg-light');
                elemento.querySelector('.fw-bold').classList.remove('fw-bold');
                elemento.querySelector('.badge').remove();
                
                // Actualizar contador en el navbar
                const contadorNav = document.getElementById('contadorNotificaciones');
                if (contadorNav) {
                    const nuevoValor = parseInt(contadorNav.textContent) - 1;
                    contadorNav.textContent = nuevoValor > 0 ? nuevoValor : '';
                }
            }
        });
    
    return true;
}
</script>