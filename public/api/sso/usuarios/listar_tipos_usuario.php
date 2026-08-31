<?php
// 1. Cargamos nuestro archivo central de rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// 2. Cargamos Composer usando la clave del array
require_once $rutas['autoload'];


try {
    // 3. Cargamos el .env usando la ruta definida en rutas.php
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
//require_once $rutas['auditoria'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

// 1. Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli);

// 2. 🛡️ Validar si el rol tiene permiso para este endpoint y método
validarPermisoEndpoint($mysqli, $userAuth);

$id_auditor = 99; 
$esAuditor = ($userAuth['idtipousuario'] == $id_auditor);

// 3. Filtrar roles si no es auditor
$where = "";
if (!$esAuditor) {
    $where = " WHERE idtipousuario != $id_auditor ";
}

$sql = "SELECT idtipousuario, descripcion FROM tiposusuario $where ORDER BY idtipousuario ASC";
$res = $mysqli->query($sql);

$tipos = [];
while ($f = $res->fetch_assoc()) {
    $tipos[] = $f;
}

echo json_encode(["status" => "ok", "data" => $tipos]);
$mysqli->close();