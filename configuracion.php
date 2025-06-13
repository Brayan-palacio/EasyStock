<?php
$tituloPagina = 'Configuración del Sistema - EasyStock';
include 'config/conexion.php';
include_once 'includes/header.php';

// Verificar permisos (solo administradores)
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol_usuario'] != 'Administrador') {
    $_SESSION['mensaje'] = [
        'tipo' => 'danger',
        'texto' => 'Requieres nivel de administrador para acceder'
    ];
    header("Location: index.php");
    exit();
}

// Obtener configuración actual
$config = [];
$result = $conexion->query("SELECT clave, valor FROM configuracion");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// Procesar formulario
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y sanitizar datos
    $nombre_sistema = trim($conexion->real_escape_string($_POST['nombre_sistema'] ?? ''));
    $empresa_nombre = trim($conexion->real_escape_string($_POST['empresa_nombre'] ?? ''));
    $empresa_ruc = trim($conexion->real_escape_string($_POST['empresa_ruc'] ?? ''));
    $empresa_direccion = trim($conexion->real_escape_string($_POST['empresa_direccion'] ?? ''));
    $empresa_telefono = trim($conexion->real_escape_string($_POST['empresa_telefono'] ?? ''));
    $empresa_email = trim($conexion->real_escape_string($_POST['empresa_email'] ?? ''));
    $iva_por_defecto = (float)($_POST['iva_por_defecto'] ?? 18.00);
    $facturacion_automatica = isset($_POST['facturacion_automatica']) ? 1 : 0;
    $inventario_minimo = (int)($_POST['inventario_minimo'] ?? 5);
    $dias_alerta = (int)($_POST['dias_alerta'] ?? 7);
    $registros_por_pagina = (int)($_POST['registros_por_pagina'] ?? 15);
    $modo_mantenimiento = isset($_POST['modo_mantenimiento']) ? 1 : 0;
    $mensaje_mantenimiento = trim($conexion->real_escape_string($_POST['mensaje_mantenimiento'] ?? ''));
    
    // Manejo del logo
    $logo = $config['logo'] ?? '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $extensionesPermitidas = ['png', 'svg', 'jpg', 'jpeg'];
        
        if (in_array($extension, $extensionesPermitidas)) {
            // Eliminar logo anterior si existe
            if (!empty($logo) && file_exists('assets/img/' . $logo)) {
                unlink('assets/img/' . $logo);
            }
            
            // Generar nombre único para el nuevo logo
            $nuevoNombreLogo = 'logo_' . time() . '.' . $extension;
            $rutaDestino = 'assets/img/' . $nuevoNombreLogo;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $rutaDestino)) {
                $logo = $nuevoNombreLogo;
            } else {
                $errores[] = "Error al subir el archivo del logo";
            }
        } else {
            $errores[] = "Formato de archivo no permitido. Use PNG, SVG o JPG";
        }
    }
    
    // Validaciones
    if ($iva_por_defecto < 0 || $iva_por_defecto > 30) {
        $errores[] = "El IVA debe estar entre 0% y 30%";
    }
    if (!empty($empresa_email) && !filter_var($empresa_email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El email de la empresa no es válido";
    }
    if ($modo_mantenimiento && empty($mensaje_mantenimiento)) {
        $errores[] = "Debe proporcionar un mensaje cuando el modo mantenimiento está activado";
    }

    // Si no hay errores, actualizar la base de datos
    if (empty($errores)) {
        $conexion->begin_transaction();
        
        try {
            // Configuraciones básicas
            $configuraciones = [
                'nombre_sistema' => $nombre_sistema,
                'logo' => $logo,
                'empresa_nombre' => $empresa_nombre,
                'empresa_ruc' => $empresa_ruc,
                'empresa_direccion' => $empresa_direccion,
                'empresa_telefono' => $empresa_telefono,
                'empresa_email' => $empresa_email,
                'iva_por_defecto' => $iva_por_defecto,
                'facturacion_automatica' => $facturacion_automatica,
                'inventario_minimo' => $inventario_minimo,
                'dias_alerta' => $dias_alerta,
                'registros_por_pagina' => $registros_por_pagina,
                'modo_mantenimiento' => $modo_mantenimiento,
                'mensaje_mantenimiento' => $mensaje_mantenimiento
            ];
            
            // Actualizar cada configuración
            foreach ($configuraciones as $clave => $valor) {
                $stmt = $conexion->prepare("INSERT INTO configuracion (clave, valor, actualizado_en) 
                                          VALUES (?, ?, NOW()) 
                                          ON DUPLICATE KEY UPDATE valor = ?, actualizado_en = NOW()");
                $stmt->bind_param('sss', $clave, $valor, $valor);
                $stmt->execute();
                $stmt->close();
            }
            
            $conexion->commit();
            
            $_SESSION['mensaje'] = [
                'tipo' => 'success',
                'texto' => 'Configuración actualizada correctamente'
            ];
            header("Location: configuracion.php");
            exit();
            
        } catch (Exception $e) {
            $conexion->rollback();
            $errores[] = "Error al actualizar la configuración: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-header bg-dark text-white py-3">
                    <h2 class="h5 mb-0 fw-semibold">
                        <i class="fas fa-cogs me-2"></i> Configuración del Sistema
                    </h2>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($errores)): ?>
                        <div class="alert alert-danger">
                            <strong>Errores:</strong>
                            <ul class="mb-0">
                                <?php foreach ($errores as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data">
                         <!-- Sección: Empresa -->
                        <div class="mb-5">
                            <h5 class="fw-bold border-bottom pb-2 mb-4">
                                <i class="fas fa-building me-2"></i> Información de la Empresa
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="empresa_nombre" class="form-label">Nombre Legal</label>
                                        <input type="text" class="form-control" id="empresa_nombre" name="empresa_nombre" 
                                               value="<?= htmlspecialchars($config['empresa_nombre'] ?? '') ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="empresa_ruc" class="form-label">RUC</label>
                                        <input type="text" class="form-control" id="empresa_ruc" name="empresa_ruc" 
                                               value="<?= htmlspecialchars($config['empresa_ruc'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="empresa_direccion" class="form-label">Dirección</label>
                                        <textarea class="form-control" id="empresa_direccion" name="empresa_direccion"><?= htmlspecialchars($config['empresa_direccion'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="empresa_telefono" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="empresa_telefono" name="empresa_telefono" 
                                               value="<?= htmlspecialchars($config['empresa_telefono'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="empresa_email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="empresa_email" name="empresa_email" 
                                               value="<?= htmlspecialchars($config['empresa_email'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        

                        <!-- Sección: Facturación (con IVA) -->
<div class="mb-5">
    <h5 class="fw-bold border-bottom pb-2 mb-4">
        <i class="fas fa-file-invoice-dollar me-2"></i> Configuración de Facturación
    </h5>
    
    <div class="row g-3">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="iva_por_defecto" class="form-label">IVA (%)</label>
                <?php if (tienePermiso(['Administrador', 'Contabilidad'])): ?>
                    <input type="number" class="form-control" id="iva_por_defecto" name="iva_por_defecto" 
                           step="0.01" min="0" max="30" 
                           value="<?= htmlspecialchars($config['iva_por_defecto'] ?? 18) ?>" required>
                <?php else: ?>
                    <p class="form-control-plaintext bg-light p-2 rounded">
                        <?= htmlspecialchars($config['iva_por_defecto'] ?? 18) ?>%
                    </p>
                <?php endif; ?>
                <div class="form-text">Porcentaje de IVA aplicable por defecto</div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3 pt-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="facturacion_automatica" 
                           name="facturacion_automatica" value="1" 
                           <?= ($config['facturacion_automatica'] ?? 0) ? 'checked' : '' ?>
                           <?= !tienePermiso(['Administrador', 'Contabilidad']) ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="facturacion_automatica">Facturación Automática</label>
                </div>
                <div class="form-text">Generar facturas automáticamente al completar ventas</div>
            </div>
        </div>
    </div>
</div>
                        
                        <!-- Sección: Inventario -->
                        <div class="mb-5">
    <h5 class="fw-bold border-bottom pb-2 mb-4">
        <i class="fas fa-boxes me-2"></i> Configuración de Inventario
    </h5>
    
    <div class="row g-3">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="inventario_minimo" class="form-label">Stock Mínimo</label>
                <?php if (tienePermiso(['Administrador', 'Almacen'])): ?>
                    <input type="number" class="form-control" id="inventario_minimo" name="inventario_minimo" 
                           min="1" value="<?= htmlspecialchars($config['inventario_minimo'] ?? 5) ?>" required>
                <?php else: ?>
                    <p class="form-control-plaintext bg-light p-2 rounded">
                        <?= htmlspecialchars($config['inventario_minimo'] ?? 5) ?>
                    </p>
                <?php endif; ?>
                <div class="form-text">Cantidad mínima para alertas de stock</div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="dias_alerta" class="form-label">Días para Alerta</label>
                <?php if (tienePermiso(['Administrador', 'Almacen'])): ?>
                    <input type="number" class="form-control" id="dias_alerta" name="dias_alerta" 
                           min="1" value="<?= htmlspecialchars($config['dias_alerta'] ?? 7) ?>" required>
                <?php else: ?>
                    <p class="form-control-plaintext bg-light p-2 rounded">
                        <?= htmlspecialchars($config['dias_alerta'] ?? 7) ?> días
                    </p>
                <?php endif; ?>
                <div class="form-text">Días previos para alertas de vencimiento</div>
            </div>
        </div>
    </div>
</div>
                        
                        <!-- Sección: Sistema (solo lectura para no admins) -->
                        <div class="mb-4">
                            <h5 class="fw-bold border-bottom pb-2 mb-4">
                                <i class="fas fa-server me-2"></i> Configuración Técnica
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Registros por Página</label>
                                        <?php if (tienePermiso(['Administrador'])): ?>
                                            <input type="number" class="form-control" name="registros_por_pagina" 
                                                   value="<?= htmlspecialchars($config['registros_por_pagina'] ?? 15) ?>">
                                        <?php else: ?>
                                            <p class="form-control-plaintext">
                                                <?= htmlspecialchars($config['registros_por_pagina'] ?? 15) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3 pt-4">
                                        <label class="form-label">Modo Mantenimiento</label>
                                        <?php if (tienePermiso(['SuperAdmin'])): ?>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="modo_mantenimiento" 
                                                       value="1" <?= ($config['modo_mantenimiento'] ?? 0) ? 'checked' : '' ?>>
                                            </div>
                                        <?php else: ?>
                                            <p class="form-control-plaintext">
                                                <?= ($config['modo_mantenimiento'] ?? 0) ? 'Activado' : 'Desactivado' ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

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
            
<?php include_once 'includes/footer.php'; ?>