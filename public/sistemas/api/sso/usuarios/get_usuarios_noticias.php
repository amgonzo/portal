<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../auth/auth_middleware.php';

// Traemos solo lo necesario para el select
$sql = "SELECT idusuario, nombreapellido FROM usuarios WHERE baja = 0 ORDER BY nombreapellido ASC";
$res = $mysqli->query($sql);

$usuarios = [];
while ($row = $res->fetch_assoc()) {
    $usuarios[] = $row;
}

echo json_encode(["status" => "ok", "data" => $usuarios]);

$mysqli->close();
