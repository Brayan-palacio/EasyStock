<?php
// Desactivar caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Obtener configuración del sistema si es posible
$nombreSistema = 'Taller El Mamey';
$logo = '';
$mensaje = 'El sistema está en mantenimiento programado';

try {
    include_once 'config/conexion.php';
    $config = $conexion->query("SELECT clave, valor FROM configuracion WHERE clave IN ('nombre_sistema', 'logo', 'mensaje_mantenimiento')")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($config as $item) {
        if ($item['clave'] === 'nombre_sistema') $nombreSistema = $item['valor'];
        if ($item['clave'] === 'logo') $logo = $item['valor'];
        if ($item['clave'] === 'mensaje_mantenimiento') $mensaje = $item['valor'];
    }
} catch (Exception $e) {
    // Usar valores por defecto si hay error
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - <?= htmlspecialchars($nombreSistema) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .maintenance-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 600px;
        }
        .maintenance-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .logo-img {
            max-height: 80px;
            margin-bottom: 1rem;
        }
        .maintenance-body {
            padding: 2.5rem;
            background-color: white;
        }
        .countdown {
            font-size: 1.8rem;
            font-weight: bold;
            color: #4a5568;
        }
        .progress {
            height: 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="maintenance-card">
                    <div class="maintenance-header">
                        <?php if (!empty($logo)): ?>
                            <img src="../assets/img/<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($nombreSistema) ?>" class="logo-img">
                        <?php else: ?>
                            <i class="bi bi-tools" style="font-size: 3rem;"></i>
                        <?php endif; ?>
                        <h1 class="h2 mb-0"><?= htmlspecialchars($nombreSistema) ?></h1>
                    </div>
                    <div class="maintenance-body text-center">
                        <div class="mb-4">
                            <i class="bi bi-tools" style="font-size: 3rem; color: #667eea;"></i>
                            <h2 class="h3 mt-3">Sistema en Mantenimiento</h2>
                            <p class="lead"><?= htmlspecialchars($mensaje) ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <div class="countdown mb-2">
                                <span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>
                            </div>
                            <div class="progress">
                                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted">Tiempo estimado para finalizar</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill"></i> Estamos trabajando para mejorar tu experiencia. Por favor intenta nuevamente más tarde.
                        </div>
                        
                        <?php if (isset($_SESSION['id_usuario'])): ?>
                            <a href="index.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Volver al sistema (Admin)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Simulación de cuenta regresiva (puedes reemplazar con valores reales)
    document.addEventListener("DOMContentLoaded", function() {
        let hours = 2;
        let minutes = 30;
        let seconds = 0;
        const totalSeconds = hours * 3600 + minutes * 60 + seconds;
        let remainingSeconds = totalSeconds;
        
        const hoursElement = document.getElementById('hours');
        const minutesElement = document.getElementById('minutes');
        const secondsElement = document.getElementById('seconds');
        const progressBar = document.getElementById('progress-bar');
        
        function updateCountdown() {
            const h = Math.floor(remainingSeconds / 3600);
            const m = Math.floor((remainingSeconds % 3600) / 60);
            const s = remainingSeconds % 60;
            
            hoursElement.textContent = h.toString().padStart(2, '0');
            minutesElement.textContent = m.toString().padStart(2, '0');
            secondsElement.textContent = s.toString().padStart(2, '0');
            
            const progress = 100 - (remainingSeconds / totalSeconds * 100);
            progressBar.style.width = `${progress}%`;
            
            if (remainingSeconds > 0) {
                remainingSeconds--;
                setTimeout(updateCountdown, 1000);
            } else {
                // Cuando termina, intentar recargar
                setTimeout(() => {
                    window.location.reload();
                }, 5000);
            }
        }
        
        updateCountdown();
    });
    </script>
</body>
</html>