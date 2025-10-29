<?php
// login.php
session_start();
include 'db.php';

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['nombre_usuario'];
    $contrasena = $_POST['contrasena'];

    // SQL actualizado para traer también nombre y apellido
    $sql = "SELECT id, password_hash, nombre_usuario, nombre, apellido FROM usuarios WHERE nombre_usuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $user = $resultado->fetch_assoc();
        if (password_verify($contrasena, $user['password_hash'])) {
            // Guardamos los nuevos datos en la sesión
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['nombre_usuario'] = $user['nombre_usuario'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['apellido'] = $user['apellido'];
            header("Location: index.php");
            exit();
        }
    }
    $error = "Nombre de usuario o contraseña incorrectos.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - Sistema de Inventario</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <h2>Bienvenido</h2>
        <p>Inicia sesión para administrar tu inventario.</p>
        <form action="login.php" method="post">
            <input type="text" name="nombre_usuario" placeholder="Nombre de Usuario" required>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
            <button type="submit">Entrar</button>
        </form>
        <?php if(!empty($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>