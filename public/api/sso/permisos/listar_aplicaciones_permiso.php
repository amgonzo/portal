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

$idpermiso = $_GET['idpermiso'] ?? null;

if (!$idpermiso) {
    echo json_encode([
        "status" => "error",
        "msg" => "Falta el id del permiso"
    ]);
    exit;
}

try {

    $stmt = $mysqli->prepare("
        SELECT
            a.idaplicacion,
            a.nombre,
            a.slug
        FROM aplicaciones a
        INNER JOIN aplicaciones_permisos ap
            ON ap.idaplicacion = a.idaplicacion
        WHERE ap.idpermiso = ?
        ORDER BY a.nombre ASC
    ");

    $stmt->bind_param("i", $idpermiso);
    $stmt->execute();

    $resultado = $stmt->get_result();

    $aplicaciones = [];

    while ($row = $resultado->fetch_assoc()) {
        $aplicaciones[] = $row;
    }

    echo json_encode([
        "status" => "ok",
        "data" => $aplicaciones
    ]);

} catch (Exception $e) {

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}