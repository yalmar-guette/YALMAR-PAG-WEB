<?php
// db.php
$servidor = "localhost";
$usuario = "root";
$contrasena = ""; // Por defecto, XAMPP no tiene contraseña
$base_de_datos = "tienda_db";

// Crear la conexión
$conexion = new mysqli($servidor, $usuario, $contrasena, $base_de_datos);

// Verificar si hay errores en la conexión
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Establecer el conjunto de caracteres a UTF-8 para evitar problemas con acentos
$conexion->set_charset("utf8mb4");
?>