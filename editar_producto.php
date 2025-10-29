<?php
// editar_producto.php
session_start();
include 'db.php';

// Proteger la página
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$producto_id = $_GET['id'];

// Manejar la actualización del producto cuando se envía el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $precio_usd = $_POST['precio_usd'];
    $cantidad = $_POST['cantidad'];

    $sql = "UPDATE productos SET nombre = ?, precio_usd = ?, cantidad = ? WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sdii", $nombre, $precio_usd, $cantidad, $producto_id);
    
    if ($stmt->execute()) {
        header("Location: index.php"); // Redirigir al inventario principal
        exit();
    } else {
        $mensaje = "Error al actualizar el producto.";
    }
}

// Obtener los datos actuales del producto para mostrar en el formulario
$sql_select = "SELECT nombre, precio_usd, cantidad FROM productos WHERE id = ?";
$stmt_select = $conexion->prepare($sql_select);
$stmt_select->bind_param("i", $producto_id);
$stmt_select->execute();
$resultado = $stmt_select->get_result();
$producto = $resultado->fetch_assoc();

if (!$producto) {
    echo "Producto no encontrado.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container" style="width: 500px;">
        <h2>✏️ Editar Producto</h2>
        <form action="editar_producto.php?id=<?= $producto_id ?>" method="post">
            <label for="nombre">Nombre del Producto:</label>
            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
            
            <label for="precio_usd">Precio en USD:</label>
            <input type="number" step="0.01" min="0" id="precio_usd" name="precio_usd" value="<?= $producto['precio_usd'] ?>" required>
            
            <label for="cantidad">Cantidad (Stock):</label>
            <input type="number" min="0" id="cantidad" name="cantidad" value="<?= $producto['cantidad'] ?>" required>
            
            <button type="submit">Guardar Cambios</button>
        </form>
        <?php if(!empty($mensaje)): ?>
            <p class="error"><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>
        <a href="index.php" class="back-link">Volver al Inventario</a>
    </div>
</body>
</html>