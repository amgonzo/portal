<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];

header('Content-Type: application/json');

$userAuth = validarTokenAPI($mysqli);

try {

    $sql = "
        SELECT
            idpermiso,
            clavepermiso,
            endpoint,
            metodo,
            descripcion
        FROM permisos
        ORDER BY clavepermiso ASC
    ";

    $resultado = $mysqli->query($sql);

    $permisos = [];

    while ($row = $resultado->fetch_assoc()) {
        $permisos[] = $row;
    }

    echo json_encode([
        "status" => "ok",
        "data" => $permisos
    ]);

} catch (Exception $e) {

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}