<?php
// imprimir_reporte.php
session_start();
include 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    exit('Acceso denegado.');
}

$fecha_inicio = $_GET['fecha_inicio'] ?? '1970-01-01';
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$fecha_fin_completa = $fecha_fin . ' 23:59:59';

$sql = "SELECT v.*, p.nombre as producto_nombre 
        FROM ventas v 
        LEFT JOIN productos p ON v.producto_id = p.id 
        WHERE v.fecha BETWEEN ? AND ?
        ORDER BY v.fecha ASC";
        
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin_completa);
$stmt->execute();
$ventas = $stmt->get_result();

// --- NUEVA LÓGICA PARA CALCULAR TOTALES POR MÉTODO DE PAGO ---
$total_usd_general = 0;
$total_items_vendidos = 0;
$totales_por_metodo = [];

while($venta = $ventas->fetch_assoc()) {
    $total_usd_general += $venta['total_usd'];
    $total_items_vendidos += $venta['cantidad_vendida'];
    $metodo = $venta['metodo_pago'];

    // Si el método de pago no está en nuestro array de totales, lo inicializamos
    if (!isset($totales_por_metodo[$metodo])) {
        $totales_por_metodo[$metodo] = 0;
    }
    // Sumamos el total de la venta al método de pago correspondiente
    $totales_por_metodo[$metodo] += $venta['total_usd'];
}
mysqli_data_seek($ventas, 0); // Reiniciar el puntero para la tabla de ventas
// --- FIN DE LA NUEVA LÓGICA ---
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; color: #333; }
        h1, h2 { text-align: center; color: #0A2647; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #f2f7ff; }
        .resumen-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .resumen, .resumen-metodos { padding: 20px; border: 1px solid #0A2647; border-radius: 8px; }
        .resumen h2, .resumen-metodos h2 { margin: 0 0 15px 0; border-bottom: 2px solid #FF6C22; padding-bottom: 10px; }
        .resumen p, .resumen-metodos p { margin: 8px 0; font-size: 1.1em; display: flex; justify-content: space-between; }
        .resumen p strong, .resumen-metodos p strong { color: #144272; }
        .total-general { font-size: 1.3em; font-weight: bold; color: #0A2647; }
        @media print {
            body { margin: 0; color: #000; }
            .no-print { display: none; }
            .resumen-grid { grid-template-columns: 1fr 1fr; } /* Mantener columnas al imprimir */
        }
    </style>
</head>
<body onload="window.print()">
    <button class="no-print" onclick="window.print()">🖨️ Imprimir de nuevo</button>
    
    <h1>Reporte de Ventas</h1>
    <h2>Desde el <?= date('d/m/Y', strtotime($fecha_inicio)) ?> hasta el <?= date('d/m/Y', strtotime($fecha_fin)) ?></h2>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Método de Pago</th>
                <th>Total (USD)</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($ventas->num_rows > 0): ?>
                <?php while($venta = $ventas->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></td>
                    <td><?= htmlspecialchars($venta['producto_nombre'] ?? 'Producto Eliminado') ?></td>
                    <td><?= $venta['cantidad_vendida'] ?></td>
                    <td><?= htmlspecialchars($venta['metodo_pago']) ?></td>
                    <td>$<?= number_format($venta['total_usd'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No se encontraron ventas en este período.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="resumen-grid">
        <div class="resumen-metodos">
            <h2>Ingresos por Método de Pago</h2>
            <?php if (!empty($totales_por_metodo)): ?>
                <?php foreach ($totales_por_metodo as $metodo => $total): ?>
                    <p><strong><?= htmlspecialchars($metodo) ?>:</strong> <span>$<?= number_format($total, 2) ?></span></p>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No hay ingresos para desglosar.</p>
            <?php endif; ?>
        </div>
        <div class="resumen">
            <h2>Resumen General del Período</h2>
            <p><strong>Total de Items Vendidos:</strong> <span><?= $total_items_vendidos ?></span></p>
            <p class="total-general"><strong>Ingresos Totales (SUMA):</strong> <span>$<?= number_format($total_usd_general, 2) ?></span></p>
        </div>
    </div>

</body>
</html>