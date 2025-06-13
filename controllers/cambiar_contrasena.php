<?php
ob_start(); // Iniciar buffer de salida para evitar errores con header()
session_start();
include 'config/conexion.php';
include 'includes/header.php';

if (!isset($_SESSION['id_usuario'])) {
    die("<div class='alert alert-danger text-center'><strong>Error:</strong> Debes iniciar sesión para cambiar tu contraseña.</div>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = $_SESSION['id_usuario'];
    $contrasena_actual = $_POST['contrasena_actual'];
    $nueva_contrasena = $_POST['nueva_contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];

    if ($nueva_contrasena !== $confirmar_contrasena) {
        header("Location: cambiar_contrasena.php?error=❌ Las nuevas contraseñas no coinciden.");
        exit();
    }

    // Obtener la contraseña actual desde la base de datos
    $query = "SELECT contrasena FROM usuarios WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result(); // Obtener resultado
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: cambiar_contrasena.php?error=⚠️ Usuario no encontrado.");
        exit();
    }

    if (!password_verify($contrasena_actual, $user['contrasena'])) {
        header("Location: cambiar_contrasena.php?error=🔑 La contraseña actual es incorrecta.");
        exit();
    }

    // Hashear la nueva contraseña
    $nueva_contrasena_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);

    // Actualizar la contraseña en la base de datos
    $query = "UPDATE usuarios SET contrasena = ? WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("si", $nueva_contrasena_hash, $id_usuario);

    if ($stmt->execute()) {
        header("Location: cambiar_contrasena.php?success=✅ ¡Tu contraseña ha sido cambiada con éxito!");
        exit();
    } else {
        header("Location: cambiar_contrasena.php?error=❌ Hubo un problema al actualizar la contraseña. Inténtalo nuevamente.");
        exit();
    }
}

ob_end_flush(); // Limpiar el buffer y enviar salida
?>

<div class="container d-flex justify-content-center align-items-center vh-100">
    <form action="cambiar_contrasena.php" method="POST" class="shadow p-4 bg-white rounded text-center" style="max-width: 400px; width: 100%;">
        <h4 class="mb-4">🔐 Cambiar Contraseña</h4>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger w-100">
                <strong><?php echo htmlspecialchars($_GET['error']); ?></strong>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success w-100">
                <strong><?php echo htmlspecialchars($_GET['success']); ?></strong>
            </div>
        <?php endif; ?>

        <div class="text-start">
            <label for="contrasena_actual" class="fw-bold">Contraseña actual:</label>
            <input type="password" name="contrasena_actual" class="form-control" required>

            <label for="nueva_contrasena" class="fw-bold mt-2">Nueva contraseña:</label>
            <input type="password" name="nueva_contrasena" class="form-control" required>

            <label for="confirmar_contrasena" class="fw-bold mt-2">Confirmar nueva contraseña:</label>
            <input type="password" name="confirmar_contrasena" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-success mt-3 w-100">🔄 Cambiar contraseña</button>
    </form> 
</div>


<?php include 'includes/footer.php'; ?>
