<?php
session_start(); // Asegurarse siempre de iniciar sesión primero

$tituloPagina = 'Gestión de Categorías - EasyStock';
include 'config/conexion.php';

// Verificar sesión y permisos antes de enviar cualquier salida
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}
// Verificar modo mantenimiento (en tu archivo de configuración)
$configMantenimiento = $conexion->query("SELECT valor FROM configuracion WHERE clave = 'modo_mantenimiento'")->fetch_row()[0] ?? 0;
if ($configMantenimiento && !(isset($_SESSION['nivel_grupo']) && $_SESSION['nivel_grupo'] >= 100)) {
    include 'vistas/mantenimiento.php';
    exit();
}

// Función para obtener todas las categorías
function obtenerCategorias($conexion) {
    $query = $conexion->prepare("SELECT * FROM categorias ORDER BY nombre");
    $query->execute();
    return $query->get_result();
}

// Función para validar datos de categoría
function validarCategoria($nombre, $descripcion = '') {
    $errores = [];

    if (empty(trim($nombre))) {
        $errores[] = "El nombre de la categoría es obligatorio";
    } elseif (strlen(trim($nombre)) > 100) {
        $errores[] = "El nombre no puede exceder los 100 caracteres";
    }

    if (strlen(trim($descripcion)) > 255) {
        $errores[] = "La descripción no puede exceder los 255 caracteres";
    }

    return $errores;
}

// Procesar acciones
try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Validar token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Token de seguridad inválido");
        }

        // Crear nueva categoría
        if (isset($_POST['crear'])) {
            $nombre = trim($_POST['nombre']);
            $descripcion = trim($_POST['descripcion']);

            $errores = validarCategoria($nombre, $descripcion);

            if (empty($errores)) {
                $stmt = $conexion->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
                $stmt->bind_param("ss", $nombre, $descripcion);
                $stmt->execute();

                $_SESSION['mensaje'] = [
                    'tipo' => 'success',
                    'texto' => 'Categoría creada exitosamente'
                ];
            } else {
                throw new Exception(implode("<br>", $errores));
            }
        }

        // Actualizar categoría
        if (isset($_POST['actualizar'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $nombre = trim($_POST['nombre']);
            $descripcion = trim($_POST['descripcion']);

            if (!$id) {
                throw new Exception("ID de categoría inválido");
            }

            $errores = validarCategoria($nombre, $descripcion);

            if (empty($errores)) {
                $stmt = $conexion->prepare("UPDATE categorias SET nombre = ?, descripcion = ? WHERE id = ?");
                $stmt->bind_param("ssi", $nombre, $descripcion, $id);
                $stmt->execute();

                $_SESSION['mensaje'] = [
                    'tipo' => 'success',
                    'texto' => 'Categoría actualizada exitosamente'
                ];
            } else {
                throw new Exception(implode("<br>", $errores));
            }
        }

        // Eliminar categoría
        if (isset($_POST['eliminar'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$id) {
                throw new Exception("ID de categoría inválido");
            }

            // Verificar si la categoría está en uso
            $stmt = $conexion->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_row()[0];

            if ($count > 0) {
                throw new Exception("No se puede eliminar la categoría porque está asociada a productos");
            }

            $stmt = $conexion->prepare("DELETE FROM categorias WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Categoría eliminada exitosamente'
            ];
        }

        header("Location: categorias.php");
        exit();
    }
} catch (Exception $e) {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => $e->getMessage()
    ];
}

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$categorias = obtenerCategorias($conexion);

// Ahora sí incluimos el header, después de todo lo anterior
include_once 'includes/header.php';
?>
    <!-- jQuery primero -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Luego slimScroll -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>



    <style>
        .card-form {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            font-weight: 600;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .badge-categoria {
            background-color: #e9ecef;
            color: #495057;
            font-weight: 500;
        }
        .action-buttons .btn {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .divider {
            height: 3px;
            background: linear-gradient(90deg, #1a3a2f, #d4af37);
            margin: 1.5rem 0;
            border-radius: 3px;
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <?php include 'productos_navbar.php'; ?>
        <!-- Mensajes de alerta -->
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

        <div class="row">
            <!-- Formulario para agregar categoría -->
            <div class="col-lg-4 mb-4">
                <div class="card card-form">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nueva Categoría</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="form-categoria">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       placeholder="Ej: Repuestos" required maxlength="100">
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" 
                                          rows="3" placeholder="Descripción opcional" maxlength="255"></textarea>
                            </div>
                            
                            <button type="submit" name="crear" class="btn btn-success w-100">
                                <i class="fas fa-save me-1"></i> Guardar Categoría
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista de categorías -->
            <div class="col-lg-8">
                <div class="card card-form">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-list me-2"></i>Listado de Categorías</h4>
                        <span class="badge bg-primary"><?= $categorias->num_rows ?> registros</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th width="120">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($categorias->num_rows > 0): ?>
                                        <?php $contador = 1; ?>
                                        <?php while ($categoria = $categorias->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $contador++ ?></td>
                                                <td>
                                                    <span class="badge badge-categoria">
                                                        <?= htmlspecialchars($categoria['nombre']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($categoria['descripcion'] ?: 'Sin descripción') ?></td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-outline-warning btn-sm me-1" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalEditar"
                                                            title="Editar Categoría"
                                                            data-id="<?= $categoria['id'] ?>"
                                                            data-nombre="<?= htmlspecialchars($categoria['nombre']) ?>"
                                                            data-descripcion="<?= htmlspecialchars($categoria['descripcion']) ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="id" value="<?= $categoria['id'] ?>">
                                                        <button type="button" class="btn btn-outline-danger btn-sm btn-eliminar" 
                                                                title="Eliminar Categoría"
                                                                data-id="<?= $categoria['id'] ?>">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                                <h5>No hay categorías registradas</h5>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar categoría -->
    <div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="modalEditarLabel">
                        <i class="fas fa-edit me-2"></i> Editar Categoría
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="id" id="editar-id">
                        
                        <div class="mb-3">
                            <label for="editar-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editar-nombre" name="nombre" required maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label for="editar-descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="editar-descripcion" name="descripcion" rows="3" maxlength="255"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </button>
                        <button type="submit" name="actualizar" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Configurar modal de edición
        const modalEditar = document.getElementById('modalEditar');
        if (modalEditar) {
            modalEditar.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const nombre = button.getAttribute('data-nombre');
                const descripcion = button.getAttribute('data-descripcion');
                
                document.getElementById('editar-id').value = id;
                document.getElementById('editar-nombre').value = nombre;
                document.getElementById('editar-descripcion').value = descripcion;
            });
        }

        // Validación del formulario
        document.getElementById('form-categoria').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            
            if (nombre.length === 0) {
                e.preventDefault();
                alert('El nombre de la categoría es obligatorio');
                document.getElementById('nombre').focus();
            }
        });
$(document).ready(function() {
    // Configurar modal de edición (existente)
    const modalEditar = document.getElementById('modalEditar');
    if (modalEditar) {
        modalEditar.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            const descripcion = button.getAttribute('data-descripcion');
            
            document.getElementById('editar-id').value = id;
            document.getElementById('editar-nombre').value = nombre;
            document.getElementById('editar-descripcion').value = descripcion;
        });
    }

    // Manejar eliminación con SweetAlert2
    $('.btn-eliminar').on('click', function() {
        const id = $(this).data('id');
        const csrfToken = $('input[name="csrf_token"]').val();
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡No podrás revertir esta acción!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Crear formulario dinámico para enviar la solicitud
                const form = $('<form>').attr({
                    method: 'POST',
                    action: 'categorias.php'
                }).append(
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'csrf_token',
                        value: csrfToken
                    }),
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'id',
                        value: id
                    }),
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'eliminar',
                        value: '1'
                    })
                );
                
                $('body').append(form);
                form.submit();
            }
        });
    });

    // Validación del formulario (existente)
    document.getElementById('form-categoria').addEventListener('submit', function(e) {
        const nombre = document.getElementById('nombre').value.trim();
        
        if (nombre.length === 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Error',
                text: 'El nombre de la categoría es obligatorio',
                icon: 'error'
            });
            document.getElementById('nombre').focus();
        }
    });
});
    </script>
<?php include_once 'includes/footer.php'; ?>