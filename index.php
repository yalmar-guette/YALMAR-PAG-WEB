<?php
// index.php
session_start();
include 'db.php';

// 1. PRIMERO, SIEMPRE VERIFICAMOS QUE HAYA UNA SESIÓN ACTIVA
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// 2. SOLO SI LA SESIÓN EXISTE, DEFINIMOS LAS VARIABLES DEL USUARIO
$nombre_completo = $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];

// --- LÓGICA AUTOMÁTICA PARA TASA DE CAMBIO DEL BCV ---
// Si el usuario envía una tasa manual, esa tiene prioridad para la sesión.
if (isset($_POST['actualizar_tasa'])) {
    $_SESSION['tasa_ves'] = floatval($_POST['tasa_ves']);
}

// Si no hay una tasa definida en la sesión, la buscamos automáticamente.
if (!isset($_SESSION['tasa_ves'])) {
    // Usamos un servicio gratuito que extrae los datos del BCV en formato JSON
    $respuesta_api = @file_get_contents("https://pydolarvenezuela.vercel.app/api/v1/dollar/unit/bcv");
    
    if ($respuesta_api) {
        $datos = json_decode($respuesta_api, true);
        if (isset($datos['price'])) {
            $_SESSION['tasa_ves'] = floatval($datos['price']);
        } else {
            // Tasa de respaldo actualizada
            $_SESSION['tasa_ves'] = 190; 
        }
    } else {
        // Tasa de respaldo actualizada
        $_SESSION['tasa_ves'] = 190;
    }
}
$tasa_ves = $_SESSION['tasa_ves'];
// --- FIN DE LA LÓGICA DE TASA ---


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- LÓGICA PARA VENTA DE MÚLTIPLES PRODUCTOS ---
    if (isset($_POST['registrar_venta_multiple'])) {
        $productos_venta = $_POST['producto_id']; 
        $cantidades_venta = $_POST['cantidad'];
        $metodo_pago = $_POST['metodo_pago'];

        if (!empty($productos_venta) && !empty($metodo_pago)) {
            foreach ($productos_venta as $index => $producto_id) {
                $cantidad = (int)$cantidades_venta[$index];
                
                if ($producto_id > 0 && $cantidad > 0) {
                    $sql = "SELECT precio_usd, cantidad as stock FROM productos WHERE id = ?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("i", $producto_id);
                    $stmt->execute();
                    $producto = $stmt->get_result()->fetch_assoc();

                    if ($producto && $cantidad <= $producto['stock']) {
                        $total_usd = $producto['precio_usd'] * $cantidad;
                        $nueva_cantidad = $producto['stock'] - $cantidad;
                        
                        $sql_update = "UPDATE productos SET cantidad = ? WHERE id = ?";
                        $stmt_update = $conexion->prepare($sql_update);
                        $stmt_update->bind_param("ii", $nueva_cantidad, $producto_id);
                        $stmt_update->execute();

                        $sql_venta = "INSERT INTO ventas (producto_id, cantidad_vendida, total_usd, metodo_pago) VALUES (?, ?, ?, ?)";
                        $stmt_venta = $conexion->prepare($sql_venta);
                        $stmt_venta->bind_param("iids", $producto_id, $cantidad, $total_usd, $metodo_pago);
                        $stmt_venta->execute();
                    }
                }
            }
        }
    }
    
    // Lógica para añadir/eliminar productos (no relacionada con venta múltiple)
    if (!isset($_POST['registrar_venta_multiple']) && !isset($_POST['actualizar_tasa'])) {
        if (isset($_POST['agregar_producto'])) {
            $nombre = $_POST['nombre'];
            $precio_usd = $_POST['precio_usd'];
            $cantidad = $_POST['cantidad'];
            
            $sql = "INSERT INTO productos (nombre, precio_usd, cantidad) VALUES (?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("sdi", $nombre, $precio_usd, $cantidad);
            $stmt->execute();
        }

        if (isset($_POST['eliminar_producto'])) {
            $producto_id = $_POST['producto_id'];
            $sql = "DELETE FROM productos WHERE id = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $producto_id);
            $stmt->execute();
        }
    }
    
    header("Location: index.php");
    exit();
}

$productos = $conexion->query("SELECT * FROM productos ORDER BY nombre ASC");
$ventas = $conexion->query("SELECT v.*, p.nombre as producto_nombre FROM ventas v LEFT JOIN productos p ON v.producto_id = p.id ORDER BY v.fecha DESC");
$productos_para_venta = $conexion->query("SELECT id, nombre, cantidad FROM productos WHERE cantidad > 0 ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Inventario</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <header>
        <h1>Mi Inventario</h1>
        <div class="header-controls">
            <span>Hola, <strong><?= htmlspecialchars($nombre_completo) ?></strong></span>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </header>
    
    <div class="card">
        <h2>⚙️ Controles Generales</h2>
        <form action="index.php" method="post" class="tasa-form">
            <label for="tasa_ves">Tasa del día (1 USD a VES):</label>
            <input type="number" step="0.01" name="tasa_ves" id="tasa_ves" value="<?= $tasa_ves ?>" required>
            <button type="submit" name="actualizar_tasa">Actualizar Tasa</button>
        </form>
    </div>

    <div class="grid-container">
        <div class="card">
            <h2>➕ Añadir Producto</h2>
            <form action="index.php" method="post">
                <input type="text" name="nombre" placeholder="Nombre del Producto" required>
                <input type="number" step="0.01" min="0" name="precio_usd" placeholder="Precio en USD" required>
                <input type="number" min="0" name="cantidad" placeholder="Cantidad Inicial" required>
                <button type="submit" name="agregar_producto">Agregar Producto</button>
            </form>
        </div>
        
        <div class="card">
            <h2>💳 Registrar Venta</h2>
            <form action="index.php" method="post">
                <div id="productos-venta-container">
                    <div class="producto-venta-item">
                        <select name="producto_id[]" required>
                            <option value="">-- Seleccione un Producto --</option>
                            <?php while($p = $productos_para_venta->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Stock: <?= $p['cantidad'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                        <input type="number" name="cantidad[]" placeholder="Cantidad" required min="1">
                    </div>
                </div>
                
                <button type="button" id="add-product-btn">+ Añadir otro producto</button>
                <hr>
                
                <select name="metodo_pago" required>
                   <option value="">-- Elige un método de pago --</option>
                   <option value="Dólares Efectivo">Dólares - Efectivo</option>
                   <option value="Pago Móvil">Pago Móvil (Bs)</option>
                   <option value="Punto de Venta">Punto de Venta (Bs)</option>
                   <option value="Bolívares Efectivo">Bolívares - Efectivo</option>
                   <option value="Zelle / Binance">Zelle / Binance (USD)</option>
                </select>
                <button type="submit" name="registrar_venta_multiple" class="submit-venta">Registrar Venta</button>
            </form>
        </div>
    </div>
    
    <div class="card">
        <h2>📖 Inventario</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Stock</th>
                    <th>Precio (USD)</th>
                    <th>Precio (VES)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($producto = $productos->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($producto['nombre']) ?></td>
                    <td><?= $producto['cantidad'] ?></td>
                    <td>$<?= number_format($producto['precio_usd'], 2) ?></td>
                    <td><?= number_format($producto['precio_usd'] * $tasa_ves, 2) ?> Bs.</td>
                    <td class="acciones">
                        <a href="editar_producto.php?id=<?= $producto['id'] ?>" class="edit-button">Editar</a>
                        <form action="index.php" method="post" onsubmit="return confirm('¿Estás seguro?');" class="inline-form">
                            <input type="hidden" name="producto_id" value="<?= $producto['id'] ?>">
                            <button type="submit" name="eliminar_producto" class="delete-button">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <div class="card">
        <h2>🖨️ Generar Reporte de Ventas</h2>
        <form action="imprimir_reporte.php" method="get" target="_blank" class="report-form">
            <div>
                <label for="fecha_inicio">Desde:</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" required>
            </div>
            <div>
                <label for="fecha_fin">Hasta:</label>
                <input type="date" id="fecha_fin" name="fecha_fin" required>
            </div>
            <button type="submit">Imprimir Reporte</button>
        </form>
    </div>

    <div class="card">
        <h2>📜 Historial de Ventas</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Total (USD)</th>
                    <th>Método de Pago</th>
                </tr>
            </thead>
            <tbody>
                <?php while($venta = $ventas->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></td>
                    <td><?= htmlspecialchars($venta['producto_nombre'] ?? 'Producto Eliminado') ?></td>
                    <td><?= $venta['cantidad_vendida'] ?></td>
                    <td>$<?= number_format($venta['total_usd'], 2) ?></td>
                    <td><?= htmlspecialchars($venta['metodo_pago']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const addProductBtn = document.getElementById('add-product-btn');
    const container = document.getElementById('productos-venta-container');
    const productItemTemplate = container.querySelector('.producto-venta-item').cloneNode(true);

    addProductBtn.addEventListener('click', function () {
        const newItem = productItemTemplate.cloneNode(true);
        newItem.querySelector('select').selectedIndex = 0;
        newItem.querySelector('input').value = '1';
        container.appendChild(newItem);
    });
});
</script>

</body>
</html>
<?php $conexion->close(); ?>