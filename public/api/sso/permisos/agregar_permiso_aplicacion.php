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
require_once $rutas['auditoria'];

header('Content-Type: application/json');

$userAuth = validarTokenAPI($mysqli);

$idpermiso    = $_POST['idpermiso'] ?? null;
$idaplicacion = $_POST['idaplicacion'] ?? null;

if (!$idpermiso || !$idaplicacion) {
    exit(json_encode([
        "status" => "error",
        "msg" => "Faltan datos obligatorios"
    ]));
}

$mysqli->begin_transaction();

try {

    // Verificar que el permiso exista
    $checkPermiso = $mysqli->prepare("
        SELECT idpermiso
        FROM permisos
        WHERE idpermiso = ?
        LIMIT 1
    ");
    $checkPermiso->bind_param("i", $idpermiso);
    $checkPermiso->execute();

    if ($checkPermiso->get_result()->num_rows === 0) {
        throw new Exception("El permiso no existe");
    }

    // Verificar que la aplicación exista
    $checkApp = $mysqli->prepare("
        SELECT idaplicacion
        FROM aplicaciones
        WHERE idaplicacion = ?
        LIMIT 1
    ");
    $checkApp->bind_param("i", $idaplicacion);
    $checkApp->execute();

    if ($checkApp->get_result()->num_rows === 0) {
        throw new Exception("La aplicación no existe");
    }

    // Verificar si ya está asociado
    $checkRel = $mysqli->prepare("
        SELECT 1
        FROM aplicaciones_permisos
        WHERE idaplicacion = ?
          AND idpermiso = ?
        LIMIT 1
    ");
    $checkRel->bind_param("ii", $idaplicacion, $idpermiso);
    $checkRel->execute();

    if ($checkRel->get_result()->num_rows > 0) {
        throw new Exception("El permiso ya está asociado a esta aplicación");
    }

    // Crear solamente la relación
    $stmt = $mysqli->prepare("
        INSERT INTO aplicaciones_permisos
            (idaplicacion, idpermiso)
        VALUES (?, ?)
    ");
    $stmt->bind_param("ii", $idaplicacion, $idpermiso);
    $stmt->execute();

    $mysqli->commit();

    registrarLog(
        $mysqli,
        'asociar_permiso_aplicacion',
        'aplicaciones_permisos',
        $idpermiso,
        null,
        [
            'idpermiso' => $idpermiso,
            'idaplicacion' => $idaplicacion
        ]
    );

    echo json_encode([
        "status" => "ok"
    ]);

} catch (Exception $e) {

    $mysqli->rollback();

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}