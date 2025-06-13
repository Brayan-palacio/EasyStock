<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tituloPagina = 'Gestión de Medios - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Configuración de directorios
$directorioBase = __DIR__ . '/assets/img/';
$subdirectorios = [
    'productos' => 'Productos',
    'usuarios' => 'Usuarios',
    'proveedores' => 'Proveedores'
];

// Crear directorios si no existen
foreach ($subdirectorios as $dir => $nombre) {
    if (!is_dir($directorioBase . $dir)) {
        mkdir($directorioBase . $dir, 0755, true);
    }
}

// Procesar subida de archivos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    try {
        $subdirectorio = $_POST['subdirectorio'];
        $archivo = $_FILES['archivo'];
        
        // Validar subdirectorio permitido
        if (!array_key_exists($subdirectorio, $subdirectorios)) {
            throw new Exception("Directorio no válido");
        }
        
        // Validar archivo
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $extensionesPermitidas)) {
            throw new Exception("Formato de archivo no permitido");
        }
        
        // Generar nombre único
        $nombreArchivo = uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $rutaCompleta = $directorioBase . $subdirectorio . '/' . $nombreArchivo;
        
        // Mover archivo
        if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
            throw new Exception("Error al subir el archivo");
        }
        
        $_SESSION['mensaje'] = [
            'tipo' => 'success',
            'texto' => 'Archivo subido correctamente'
        ];
        
        header("Location: media.php");
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Procesar eliminación de archivos
if (isset($_GET['eliminar'])) {
    try {
        $rutaArchivo = $_GET['eliminar'];
        
        // Validar que el archivo esté dentro del directorio permitido
        if (!file_exists($directorioBase . $rutaArchivo) || 
            strpos($rutaArchivo, '..') !== false) {
            throw new Exception("Archivo no válido");
        }
        
        if (unlink($directorioBase . $rutaArchivo)) {
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Archivo eliminado correctamente'
            ];
        } else {
            throw new Exception("Error al eliminar el archivo");
        }
        
        header("Location: media.php");
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener archivos de cada directorio
$archivosPorDirectorio = [];
foreach ($subdirectorios as $dir => $nombre) {
    $ruta = $directorioBase . $dir . '/';
    $archivos = [];
    
    if (is_dir($ruta)) {
        foreach (scandir($ruta) as $archivo) {
            if ($archivo !== '.' && $archivo !== '..') {
                $archivos[] = [
                    'nombre' => $archivo,
                    'ruta' => $dir . '/' . $archivo,
                    'tamano' => filesize($ruta . $archivo),
                    'fecha' => filemtime($ruta . $archivo),
                    'tipo' => mime_content_type($ruta . $archivo)
                ];
            }
        }
    }
    
    $archivosPorDirectorio[$dir] = $archivos;
}
?>

<style>
    .media-container {
        background-color: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .media-thumbnail {
        width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: 5px;
        transition: transform 0.3s;
    }
    .media-thumbnail:hover {
        transform: scale(1.05);
    }
    .media-card {
        cursor: pointer;
        transition: all 0.3s;
        border: 1px solid #dee2e6;
    }
    .media-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .badge-file-type {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(0,0,0,0.7);
    }
    .file-info {
        font-size: 0.8rem;
        color: #6c757d;
    }
</style>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-images me-2"></i> Gestión de Medios</h2>
            <p class="text-muted">Administra los archivos multimedia del sistema</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Subir Nuevo Archivo</h5>
                </div>
                <div class="card-body">
                            <?php if (isset($_SESSION['mensaje'])): ?>
    <script>
    $(document).ready(function() {
        Swal.fire({
            title: '<?= $_SESSION['mensaje']['tipo'] === 'success' ? 'Éxito' : 'Error' ?>',
            text: '<?= addslashes($_SESSION['mensaje']['texto']) ?>',
            icon: '<?= $_SESSION['mensaje']['tipo'] ?>',
            confirmButtonText: 'Aceptar'
        });
    });
    </script>
    <?php unset($_SESSION['mensaje']); ?>
<?php endif; ?>
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <div class="col-md-6">
                            <label for="subdirectorio" class="form-label">Categoría</label>
                            <select class="form-select" id="subdirectorio" name="subdirectorio" required>
                                <?php foreach ($subdirectorios as $dir => $nombre): ?>
                                    <option value="<?= $dir ?>"><?= $nombre ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="archivo" class="form-label">Archivo</label>
                            <input type="file" class="form-control" id="archivo" name="archivo" accept="image/*,.pdf" required>
                            <div class="form-text">Formatos permitidos: JPG, PNG, WEBP, PDF (Max 5MB)</div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-1"></i> Subir Archivo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <?php foreach ($subdirectorios as $dir => $nombre): ?>
            <div class="col-12 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-folder me-2"></i><?= $nombre ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($archivosPorDirectorio[$dir])): ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>No hay archivos en esta categoría
                            </div>
                        <?php else: ?>
                            <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
                                <?php foreach ($archivosPorDirectorio[$dir] as $archivo): ?>
                                    <div class="col">
                                        <div class="card media-card h-100">
                                            <div class="position-relative">
                                                <?php if (strpos($archivo['tipo'], 'image/') === 0): ?>
                                                    <img src="assets/img/<?= $archivo['ruta'] ?>" 
                                                         class="card-img-top media-thumbnail" 
                                                         alt="<?= $archivo['nombre'] ?>"
                                                         onclick="previewMedia('assets/img/<?= $archivo['ruta'] ?>')">
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center bg-light" 
                                                         style="height: 150px;"
                                                         onclick="previewMedia('assets/img/<?= $archivo['ruta'] ?>')">
                                                        <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="badge badge-file-type text-white">
                                                    <?= strtoupper(pathinfo($archivo['nombre'], PATHINFO_EXTENSION)) ?>
                                                </span>
                                            </div>
                                            <div class="card-body p-2">
                                                <h6 class="card-title text-truncate mb-1">
                                                    <?= htmlspecialchars($archivo['nombre']) ?>
                                                </h6>
                                                <div class="file-info d-flex justify-content-between">
                                                    <span><?= round($archivo['tamano'] / 1024) ?> KB</span>
                                                    <span><?= date('d/m/Y', $archivo['fecha']) ?></span>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent p-2 d-flex justify-content-between">
                                                <a href="assets/img/<?= $archivo['ruta'] ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   download="<?= $archivo['nombre'] ?>">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        onclick="confirmarEliminacion('<?= $archivo['ruta'] ?>')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal para vista previa -->
<div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vista Previa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="mediaPreview" src="" class="img-fluid" style="max-height: 70vh;">
                <iframe id="pdfPreview" src="" style="width: 100%; height: 70vh; display: none;"></iframe>
                <a id="downloadLink" href="#" class="btn btn-primary mt-3" download>
                    <i class="fas fa-download me-2"></i>Descargar
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Función para vista previa
function previewMedia(url) {
    const extension = url.split('.').pop().toLowerCase();
    const modal = new bootstrap.Modal(document.getElementById('mediaModal'));
    const imgPreview = document.getElementById('mediaPreview');
    const pdfPreview = document.getElementById('pdfPreview');
    const downloadLink = document.getElementById('downloadLink');
    
    if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(extension)) {
        imgPreview.src = url;
        imgPreview.style.display = 'block';
        pdfPreview.style.display = 'none';
    } else if (extension === 'pdf') {
        pdfPreview.src = url;
        pdfPreview.style.display = 'block';
        imgPreview.style.display = 'none';
    }
    
    downloadLink.href = url;
    downloadLink.download = url.split('/').pop();
    modal.show();
}

// Confirmar eliminación
function confirmarEliminacion(ruta) {
    Swal.fire({
        title: '¿Eliminar archivo?',
        html: `Estás a punto de eliminar el archivo <strong>${ruta.split('/').pop()}</strong>.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `media.php?eliminar=${encodeURIComponent(ruta)}`;
        }
    });
}
</script>

<?php
include_once 'includes/footer.php';
ob_end_flush();
?>