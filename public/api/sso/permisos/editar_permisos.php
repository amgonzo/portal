<?php

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

$idpermiso = $_POST['idpermiso'] ?? null;
$clave     = trim($_POST['clave'] ?? '');
$endpoint  = $_POST['endpoint'] ?? null;
$metodo    = $_POST['metodo'] ?? 'ALL';
$desc      = $_POST['descripcion'] ?? '';

if (!$idpermiso || !$clave) {
    exit(json_encode([
        "status" => "error",
        "msg" => "La clave es obligatoria"
    ]));
}

$mysqli->begin_transaction();

try {

    // 1. Verificar que la clave no pertenezca a otro permiso
    $check = $mysqli->prepare("
        SELECT idpermiso
        FROM permisos
        WHERE clavepermiso = ?
          AND idpermiso != ?
        LIMIT 1
    ");

    $check->bind_param("si", $clave, $idpermiso);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        throw new Exception("La clave ya existe en otro permiso");
    }

    // 2. Actualizar el permiso global
    $stmt = $mysqli->prepare("
        UPDATE permisos
        SET clavepermiso = ?,
            endpoint = ?,
            metodo = ?,
            descripcion = ?
        WHERE idpermiso = ?
    ");

    $stmt->bind_param(
        "ssssi",
        $clave,
        $endpoint,
        $metodo,
        $desc,
        $idpermiso
    );

    $stmt->execute();

    $mysqli->commit();

    registrarLog(
        $mysqli,
        'editar_permiso',
        'permisos',
        $idpermiso,
        null,
        $_POST
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