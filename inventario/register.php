<?php
// register.php
include 'db.php';

$mensaje = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['nombre_usuario'];
    $contrasena = $_POST['contrasena'];
    $nombre = $_POST['nombre']; // Nuevo campo
    $apellido = $_POST['apellido']; // Nuevo campo

    if (empty($usuario) || empty($contrasena) || empty($nombre) || empty($apellido)) {
        $mensaje = "Por favor, complete todos los campos.";
    } else {
        $password_hash = password_hash($contrasena, PASSWORD_BCRYPT);
        
        // SQL actualizado para incluir nombre y apellido
        $sql = "INSERT INTO usuarios (nombre_usuario, password_hash, nombre, apellido) VALUES (?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        // Se añaden dos "s" (string) para los nuevos campos
        $stmt->bind_param("ssss", $usuario, $password_hash, $nombre, $apellido);
        
        if ($stmt->execute()) {
            $mensaje = "¡Usuario creado exitosamente! Serás redirigido para iniciar sesión.";
            header("refresh:3;url=login.php");
        } else {
            $mensaje = "Error: El nombre de usuario ya existe.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Usuario</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <h2>Crear Cuenta</h2>
        <p>Crea tu cuenta para acceder al sistema.</p>
        <form action="register.php" method="post">
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="text" name="apellido" placeholder="Apellido" required>
            <hr style="border-color: #ddd; border-style: solid; margin: 15px 0;">
            <input type="text" name="nombre_usuario" placeholder="Nombre de Usuario" required>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
            <button type="submit">Crear Usuario</button>
        </form>
        <?php if(!empty($mensaje)): ?>
            <p class="mensaje"><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>